#!/usr/bin/env bash
# Repair only the observed Docker Desktop stale-mount failure. Never start a
# deliberately stopped stack, touch database volumes, or restart healthy apps.
set -euo pipefail
cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.."
exec 9>"${XDG_RUNTIME_DIR:-/tmp}/penderie-wsl-recovery.lock"
flock -n 9 || exit 0

docker info >/dev/null 2>&1 || exit 0
test -f backend/public/index.php || exit 0
test -f backend/vendor/autoload.php || exit 0
test -f frontend/package.json || exit 0

api_running=$(docker inspect --format '{{.State.Running}}' penderie-api 2>/dev/null) || exit 0
test "$api_running" = true || exit 0
db_running=$(docker inspect --format '{{.State.Running}}' penderie-db 2>/dev/null) || exit 0
test "$db_running" = true || exit 0

for source_service in api frontend; do
    case "$source_service" in
        api) source_file=/var/www/html/public/index.php ;;
        frontend) source_file=/app/package.json ;;
    esac
    # Exit 42 means a running container actually sees a missing file. Other
    # failures (e.g. a container deliberately stopped) must not trigger recovery.
    if docker compose exec -T "$source_service" sh -c 'test -f "$1" || exit 42' sh "$source_file"; then
        continue
    else
        source_status=$?
    fi
    if test "$source_status" -eq 42; then
        echo "Docker Desktop restored an empty $source_service mount; recreating application containers."
        exec make restart
    fi
done

nginx_error=$(docker inspect --format '{{.State.Error}}' penderie-nginx 2>/dev/null) || exit 0
case "$nginx_error" in
    *'error mounting'*|*'not a directory'*)
        echo 'Docker Desktop could not restore the gateway mount; recreating application containers.'
        exec make restart
        ;;
esac
