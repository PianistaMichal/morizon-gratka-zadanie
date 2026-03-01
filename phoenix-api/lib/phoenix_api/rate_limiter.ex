defmodule PhoenixApi.RateLimiter do
  @moduledoc """
  OTP GenServer implementing sliding-window rate limiting.

  Default limits:
    - Per user:  5 requests per 10 minutes
    - Global:    1000 requests per hour

  Limits can be overridden via `start_link/1` opts (useful in tests):

      start_link(name: :my_limiter, user_limit: 3, user_window_ms: 1_000,
                 global_limit: 10, global_window_ms: 5_000)
  """
  use GenServer

  @default_user_limit 5
  @default_user_window_ms 10 * 60 * 1_000

  @default_global_limit 1_000
  @default_global_window_ms 60 * 60 * 1_000

  @cleanup_interval_ms 5 * 60 * 1_000

  # --- Client API ---

  def start_link(opts \\ []) do
    {name, opts} = Keyword.pop(opts, :name, __MODULE__)
    GenServer.start_link(__MODULE__, opts, name: name)
  end

  @doc """
  Checks whether the given user is within rate limits and, if so, records
  the request.

  Returns:
    - `:ok` when the request is allowed
    - `{:error, :user_limit, remaining_ms}` when the per-user limit is exceeded
    - `{:error, :global_limit, remaining_ms}` when the global limit is exceeded

  `remaining_ms` indicates how long until the window rolls over enough to allow
  another request — use it to populate the `Retry-After` response header.

  The optional `server` argument allows targeting a specific named process
  (useful in tests).
  """
  def check_and_record(user_id, server \\ __MODULE__) do
    GenServer.call(server, {:check_and_record, user_id})
  end

  # --- Server callbacks ---

  @impl true
  def init(opts) do
    schedule_cleanup()

    config = %{
      user_limit: Keyword.get(opts, :user_limit, @default_user_limit),
      user_window_ms: Keyword.get(opts, :user_window_ms, @default_user_window_ms),
      global_limit: Keyword.get(opts, :global_limit, @default_global_limit),
      global_window_ms: Keyword.get(opts, :global_window_ms, @default_global_window_ms)
    }

    state = %{
      config: config,
      user_requests: %{},
      global_requests: []
    }

    {:ok, state}
  end

  @impl true
  def handle_call({:check_and_record, user_id}, _from, state) do
    now = System.monotonic_time(:millisecond)
    %{config: cfg} = state

    global_cutoff = now - cfg.global_window_ms
    global = Enum.filter(state.global_requests, &(&1 > global_cutoff))

    if length(global) >= cfg.global_limit do
      oldest = Enum.min(global)
      remaining_ms = oldest + cfg.global_window_ms - now
      {:reply, {:error, :global_limit, remaining_ms}, %{state | global_requests: global}}
    else
      user_cutoff = now - cfg.user_window_ms
      user = state.user_requests |> Map.get(user_id, []) |> Enum.filter(&(&1 > user_cutoff))

      if length(user) >= cfg.user_limit do
        oldest = Enum.min(user)
        remaining_ms = oldest + cfg.user_window_ms - now

        new_state = %{state |
          global_requests: global,
          user_requests: Map.put(state.user_requests, user_id, user)
        }

        {:reply, {:error, :user_limit, remaining_ms}, new_state}
      else
        new_state = %{state |
          global_requests: [now | global],
          user_requests: Map.put(state.user_requests, user_id, [now | user])
        }

        {:reply, :ok, new_state}
      end
    end
  end

  @impl true
  def handle_info(:cleanup, state) do
    now = System.monotonic_time(:millisecond)
    %{config: cfg} = state

    global = Enum.filter(state.global_requests, &(&1 > now - cfg.global_window_ms))
    user_cutoff = now - cfg.user_window_ms

    user_requests =
      Enum.reduce(state.user_requests, %{}, fn {user_id, timestamps}, acc ->
        pruned = Enum.filter(timestamps, &(&1 > user_cutoff))
        if pruned == [], do: acc, else: Map.put(acc, user_id, pruned)
      end)

    schedule_cleanup()

    {:noreply, %{state | global_requests: global, user_requests: user_requests}}
  end

  defp schedule_cleanup do
    Process.send_after(self(), :cleanup, @cleanup_interval_ms)
  end
end
