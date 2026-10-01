#!/usr/bin/env bash
set -euo pipefail

if [[ -n "${VEC_TAP_ROOT:-}" ]]; then
  ROOT="$VEC_TAP_ROOT"
elif [[ -f app/bootstrap.php ]]; then
  ROOT="$(pwd)"
else
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fi

if [[ ! -f "$ROOT/app/bootstrap.php" ]]; then
  echo "vec-tap: start must run from the repository root (got $ROOT)" >&2
  exit 1
fi

sudo mkdir -p /run/mysqld
sudo chown mysql:mysql /run/mysqld

if ! sudo mysqladmin --protocol=socket --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null; then
  if [[ -f /run/mysqld/mysqld.pid ]]; then
    pid="$(cat /run/mysqld/mysqld.pid 2>/dev/null || true)"
    if [[ -n "${pid}" ]] && ! kill -0 "${pid}" 2>/dev/null; then
      sudo rm -f /run/mysqld/mysqld.pid
    fi
  fi
  sudo -u mysql /usr/sbin/mariadbd \
    --datadir=/var/lib/mysql \
    --bind-address=127.0.0.1 \
    --socket=/run/mysqld/mysqld.sock \
    --pid-file=/run/mysqld/mysqld.pid \
    --console \
    >/opt/vec-tap/mariadb.log 2>&1 &
  ready=0
  for _ in $(seq 1 60); do
    if sudo mysqladmin --protocol=socket --socket=/run/mysqld/mysqld.sock ping --silent 2>/dev/null; then
      ready=1
      break
    fi
    sleep 1
  done
  if [[ "$ready" -ne 1 ]]; then
    echo "vec-tap: MariaDB did not become ready" >&2
    tail -n 40 /opt/vec-tap/mariadb.log >&2 || true
    exit 1
  fi
fi

php /opt/vec-tap/provision.php "$ROOT"
echo "vec-tap start ok"
