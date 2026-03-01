#!/bin/bash

set -e

mix deps.get

mix ecto.create 2>/dev/null || true

mix ecto.migrate

mix run priv/repo/seeds.exs

exec mix phx.server
