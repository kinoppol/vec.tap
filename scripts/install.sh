#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

php -r 'foreach (["pdo_mysql","mbstring","json","openssl","fileinfo","session","curl","zip"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: {$ext}\n"); exit(1); } } echo "PHP ".PHP_VERSION." extensions ok\n";'

if ! command -v mysql >/dev/null 2>&1 || [[ ! -x /usr/sbin/mariadbd ]]; then
  echo "MariaDB client and server are required" >&2
  exit 1
fi

mkdir -p storage/logs storage/cache storage/uploads
chmod u+rwx storage storage/logs storage/cache storage/uploads config

install -d -m 755 /opt/vec-tap
cp "$ROOT/scripts/router.php" /opt/vec-tap/router.php
cp "$ROOT/scripts/provision.php" /opt/vec-tap/provision.php
cp "$ROOT/scripts/start.sh" /opt/vec-tap/start.sh
chmod 644 /opt/vec-tap/router.php /opt/vec-tap/provision.php
chmod 755 /opt/vec-tap/start.sh
echo "vec-tap install ok"
