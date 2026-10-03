#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-}" == wp ]]; then
  shift
  exec docker compose --project-name oi-staging --env-file .runtime/local.env run --rm cli wp "$@"
fi
exec docker compose --project-name oi-staging --env-file .runtime/local.env "$@"
