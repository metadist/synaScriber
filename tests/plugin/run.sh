#!/usr/bin/env bash
# Plugin quality gate: PHPStan (level 8, plugin and tests) and PHPUnit, run in a
# Synaplan backend container so the plugin is checked against Synaplan's code.
#
#   tests/plugin/run.sh [all|phpstan|phpunit]
#   SYNAPLAN_DIR       Synaplan checkout with the running dev stack (default ../synaplan)
#   SYNAPLAN_SERVICE   compose service to use (default: backend, else worker)
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
synaplan="${SYNAPLAN_DIR:-$repo/../synaplan}"
what="${1:-all}"
case "$what" in all|phpstan|phpunit) ;; *) echo "usage: $0 [all|phpstan|phpunit]" >&2; exit 2 ;; esac

container=""
for service in ${SYNAPLAN_SERVICE:-backend worker}; do
  container="$(docker compose --project-directory "$synaplan" ps -q "$service" 2>/dev/null || true)"
  [ -n "$container" ] && break
done
if [ -z "$container" ]; then
  echo "No running Synaplan backend or worker container in $synaplan (docker compose up -d worker)." >&2
  exit 2
fi

dest=/tmp/synascriber-ci
docker exec "$container" rm -rf "$dest"
docker exec "$container" mkdir -p "$dest/synascriber-plugin" "$dest/tests"
docker cp -q "$repo/synascriber-plugin/backend" "$container:$dest/synascriber-plugin/backend"
docker cp -q "$repo/tests/plugin" "$container:$dest/tests/plugin"
trap 'docker exec "$container" rm -rf "$dest" /tmp/synascriber-phpstan /tmp/synascriber-phpunit-cache >/dev/null 2>&1 || true' EXIT

status=0
if [ "$what" != phpunit ]; then
  echo "== PHPStan (level 8)"
  docker exec -w /var/www/backend "$container" vendor/bin/phpstan analyse -c "$dest/tests/plugin/phpstan.neon" --no-progress --memory-limit=1G || status=1
fi
if [ "$what" != phpstan ]; then
  echo "== PHPUnit"
  docker exec -w "$dest/tests/plugin" "$container" /var/www/backend/vendor/bin/phpunit -c phpunit.xml.dist --display-phpunit-notices || status=1
fi
exit "$status"
