#!/bin/sh
# M11.5 single shared-hosting cron entrypoint; intentionally no DDL or auto-enable.
set -eu
APP_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PHP_BIN=${PHP_BIN:-php}
LOG_DIR="$APP_DIR/writable/logs"
if [ ! -d "$LOG_DIR" ] || [ ! -w "$LOG_DIR" ]; then
    echo 'M11_TASKS_LOG_DIR_NOT_WRITABLE' >&2
    exit 1
fi
cd "$APP_DIR"
# A daily file makes retention manageable without deleting any logs automatically.
exec "$PHP_BIN" spark tasks:run >> "$LOG_DIR/m11-tasks-$(date +%Y-%m-%d).log" 2>&1
