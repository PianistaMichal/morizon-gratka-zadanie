defmodule PhoenixApiWeb.Plugs.RateLimit do
  @moduledoc """
  Plug that enforces rate limits via `PhoenixApi.RateLimiter`.

  Must be placed after `PhoenixApiWeb.Plugs.Authenticate` so that
  `conn.assigns.current_user` is already populated.
  """
  import Plug.Conn
  import Phoenix.Controller

  def init(opts), do: opts

  def call(conn, _opts) do
    user_id = conn.assigns.current_user.id

    case PhoenixApi.RateLimiter.check_and_record(user_id) do
      :ok ->
        conn

      {:error, :user_limit} ->
        conn
        |> put_status(:too_many_requests)
        |> put_view(json: PhoenixApiWeb.ErrorJSON)
        |> render(:"429")
        |> halt()

      {:error, :global_limit} ->
        conn
        |> put_status(:too_many_requests)
        |> put_view(json: PhoenixApiWeb.ErrorJSON)
        |> render(:"429")
        |> halt()
    end
  end
end
