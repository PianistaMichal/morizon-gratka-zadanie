defmodule PhoenixApi.RateLimiterTest do
  use ExUnit.Case, async: true

  # Start a fresh, isolated RateLimiter for each test with small limits
  # so we don't have to fire thousands of requests.
  setup do
    name = :"rate_limiter_#{System.unique_integer([:positive])}"

    start_supervised!({
      PhoenixApi.RateLimiter,
      name: name,
      user_limit: 3,
      user_window_ms: 500,
      global_limit: 5,
      global_window_ms: 1_000
    })

    {:ok, limiter: name}
  end

  describe "per-user limit" do
    test "allows requests up to the limit", %{limiter: limiter} do
      assert PhoenixApi.RateLimiter.check_and_record(:user_a, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:user_a, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:user_a, limiter) == :ok
    end

    test "blocks the request that exceeds the limit", %{limiter: limiter} do
      for _ <- 1..3, do: PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      assert {:error, :user_limit, remaining} =
               PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      assert remaining > 0
    end

    test "users are tracked independently", %{limiter: limiter} do
      for _ <- 1..3, do: PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      # user_a is blocked …
      assert {:error, :user_limit, _remaining} =
               PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      # … but user_b is still fine
      assert PhoenixApi.RateLimiter.check_and_record(:user_b, limiter) == :ok
    end

    test "allows requests again after the window expires", %{limiter: limiter} do
      for _ <- 1..3, do: PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)
      assert {:error, :user_limit, _} = PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      # user_window_ms = 500 ms — wait for the window to roll over
      Process.sleep(600)

      assert PhoenixApi.RateLimiter.check_and_record(:user_a, limiter) == :ok
    end

    test "remaining_ms decreases towards zero as window expires", %{limiter: limiter} do
      for _ <- 1..3, do: PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      {:error, :user_limit, remaining1} = PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)
      Process.sleep(100)
      {:error, :user_limit, remaining2} = PhoenixApi.RateLimiter.check_and_record(:user_a, limiter)

      assert remaining1 > remaining2
    end
  end

  describe "global limit" do
    test "blocks when the global limit is reached regardless of user", %{limiter: limiter} do
      # global_limit = 5; spread across different users so user limit is not hit first
      assert PhoenixApi.RateLimiter.check_and_record(:u1, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:u2, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:u3, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:u4, limiter) == :ok
      assert PhoenixApi.RateLimiter.check_and_record(:u5, limiter) == :ok

      # 6th request — any user — should be rejected due to global limit
      assert {:error, :global_limit, remaining} =
               PhoenixApi.RateLimiter.check_and_record(:u6, limiter)

      assert remaining > 0
    end

    test "allows requests again after the global window expires", %{limiter: limiter} do
      for i <- 1..5, do: PhoenixApi.RateLimiter.check_and_record(i, limiter)
      assert {:error, :global_limit, _} = PhoenixApi.RateLimiter.check_and_record(:overflow, limiter)

      # global_window_ms = 1_000 ms
      Process.sleep(1_100)

      assert PhoenixApi.RateLimiter.check_and_record(:fresh_user, limiter) == :ok
    end

    test "global limit takes precedence over user limit", %{limiter: limiter} do
      # Fill up the global bucket (limit = 5) using 5 different users
      for i <- 1..5, do: PhoenixApi.RateLimiter.check_and_record(i, limiter)

      # Even a brand-new user (0 prior requests) should be rejected
      assert {:error, :global_limit, _remaining} =
               PhoenixApi.RateLimiter.check_and_record(:brand_new_user, limiter)
    end
  end
end
