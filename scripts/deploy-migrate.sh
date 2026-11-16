#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BINARY:-php}"
exec "$PHP_BIN" artisan migrate:deploy
