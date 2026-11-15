#!/usr/bin/env bash
#
# KCCA Vendor Relocation & Space Allocation System
# Full installation: check the environment, create the database, load the
# schema and seed it, then verify that everything reconciles with the
# published KCCA figures.
#
#   ./install.sh
#
# Override the database settings with environment variables if you are
# pointing at an existing server:
#   KCCA_DB_HOST=db.internal KCCA_DB_NAME=kcca KCCA_DB_USER=kcca \
#   KCCA_DB_PASS=secret ./install.sh

set -euo pipefail

cd "$(dirname "$0")"

DB_HOST="${KCCA_DB_HOST:-127.0.0.1}"
DB_PORT="${KCCA_DB_PORT:-3306}"
DB_NAME="${KCCA_DB_NAME:-kcca_vendor_relocation}"
DB_USER="${KCCA_DB_USER:-kcca}"
DB_PASS="${KCCA_DB_PASS:-kcca_dev_2026}"

say() { printf '\n\033[1m%s\033[0m\n' "$1"; }
ok()  { printf '  \033[32m✓\033[0m %s\n' "$1"; }
bad() { printf '  \033[31m✗\033[0m %s\n' "$1"; exit 1; }

say "1. Checking the environment"
command -v php >/dev/null 2>&1 || bad "PHP is not installed."
PHPV=$(php -r 'echo PHP_VERSION;')
ok "PHP ${PHPV} (needs 8.0 or newer)"
php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' || bad "PHP 8.0 or newer is required."
php -m | grep -qi '^pdo_mysql$' || bad "The pdo_mysql extension is missing."
ok "pdo_mysql available"

command -v mysql >/dev/null 2>&1 || bad "The mysql client is not installed."
ok "mysql client available"

say "2. Testing the database connection"
if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
  ok "Connected to ${DB_HOST}:${DB_PORT} as ${DB_USER}"
else
  bad "Cannot connect to ${DB_HOST}:${DB_PORT} as ${DB_USER}.
      Start the server, or create the user:
        CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '…';
        GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
      Set KCCA_DB_* environment variables if your settings differ."
fi

say "3. Loading the schema and seeding"
# The seeder applies schema.sql itself and drops any existing tables.
php database/seed.php | tail -n 16

say "4. Verifying reconciliation with published KCCA figures"
php database/verify.php | tail -n 8

say "Done"
echo "  Start the system with:   ./run.sh"
echo "  Sign in with:            admin / KCCA VRSP 2026"
echo