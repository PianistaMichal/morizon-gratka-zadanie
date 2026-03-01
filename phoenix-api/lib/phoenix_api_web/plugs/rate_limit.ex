defmodule PhoenixApiWeb.Plugs.RateLimit do
  @moduledoc """
  Plug that enforces rate limits via `PhoenixApi.RateLimiter`.

  Must be placed after `PhoenixApiWeb.Plugs.Authenticate` so that
  `conn.assigns.current_user` is already populated.

  On limit exceeded, responds with HTTP 429 and a `Retry-After` header
  indicating how many seconds the client should wait before retrying
  (RFC 6585).
  """
  import Plug.Conn
  import Phoenix.Controller

  def init(opts), do: opts

  def call(conn, _opts) do
    user_id = conn.assigns.current_user.id

    case PhoenixApi.RateLimiter.check_and_record(user_id) do
      :ok ->
        conn

      {:error, :user_limit, remaining_ms} ->
        conn
        |> put_resp_header("retry-after", retry_after_seconds(remaining_ms))
        |> put_status(:too_many_requests)
        |> put_view(json: PhoenixApiWeb.ErrorJSON)
        |> render(:"429")
        |> halt()

      {:error, :global_limit, remaining_ms} ->
        conn
        |> put_resp_header("retry-after", retry_after_seconds(remaining_ms))
        |> put_status(:too_many_requests)
        |> put_view(json: PhoenixApiWeb.ErrorJSON)
        |> render(:"429")
        |> halt()
    end
  end

  # Converts milliseconds to a ceiling of whole seconds, as required by RFC 6585.
  defp retry_after_seconds(remaining_ms) do
    remaining_ms
    |> Kernel./(1000)
    |> :math.ceil()
    |> trunc()
    |> to_string()
  end
end
