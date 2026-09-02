#!/usr/bin/env bash
#
# Cloud Agent start script: bring up the database on each boot.
#
# Dependency installation and the WordPress bootstrap live in install.sh. This
# script only performs the per-boot runtime reconciliation (starting MariaDB)
# and returns once the server is accepting connections. The PHP development
# server itself is run as a visible "terminals" process (see environment.json).
set -euo pipefail

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

log "Starting MariaDB"
sudo service mariadb start || true

for _ in $(seq 1 30); do
  if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
    log "MariaDB is ready"
    exit 0
  fi
  sleep 1
done

echo "MariaDB did not become ready in time" >&2
exit 1
