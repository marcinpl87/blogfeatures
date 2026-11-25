#!/bin/bash
# The MCP host ignores envFile, so load the project .env before starting the server.
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
set +u
set -a
# shellcheck disable=SC1091
source "$root/.env"
set +a
set -u
exec /home/m/.config/nvm/versions/node/v24.22.1/bin/node \
  /home/m/.config/nvm/versions/node/v24.22.1/lib/node_modules/@mangerik/wordpress-mcp/dist/index.js
