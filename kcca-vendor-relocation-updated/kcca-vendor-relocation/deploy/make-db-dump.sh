#!/usr/bin/env bash
#
# Export the seeded database for a shared host's phpMyAdmin.
#
#   deploy/make-db-dump.sh
#
# Writes:
#   deploy/dist/kcca-database.sql
#   deploy/dist/kcca-database.sql.gz
#
# The dump is built for a host with a small max_allowed_packet (InfinityFree
# allows 3 MB): extended INSERTs are capped at 512 KB per statement.
#
# It reads from the running Docker stack when one is up, otherwise from the
# bundled/local MariaDB using the KCCA_DB_* environment variables.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="$ROOT/deploy/dist"
SQL="$OUT_DIR/kcca-database.sql"
GZ="$SQL.gz"

mkdir -p "$OUT_DIR"

cd "$ROOT"

write_header() {
    echo "-- KCCA VRSP demo database"
    echo "-- Import this into the database created in the hosting panel."
    echo "-- It creates the tables itself; no CREATE DATABASE / USE is included."
    echo
}

compose_db_up() {
    command -v sg >/dev/null 2>&1 || return 1
    sg docker -c 'docker compose ps -q db' 2>/dev/null | grep -q .
}

dump_from_compose() {
    # The container, not this shell, expands $MARIADB_ROOT_PASSWORD and
    # $MARIADB_DATABASE. bash -s avoids a second layer of quoting through sg.
    sg docker -c 'bash -s' <<'EOS'
docker compose exec -T db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" \
    --single-transaction --quick --skip-add-locks --no-tablespaces \
    --default-character-set=utf8mb4 --net-buffer-length=524288 \
    "$MARIADB_DATABASE"'
EOS
}

if compose_db_up; then
    echo "Dumping from the running Docker stack ..."
    write_header > "$SQL"
    dump_from_compose >> "$SQL"
else
    command -v mariadb-dump >/dev/null 2>&1 || {
        echo "Neither the Docker stack nor mariadb-dump is available." >&2
        exit 1
    }
    export PATH="$PATH:/usr/sbin:/usr/local/sbin"
    SOCKET="${KCCA_DB_SOCKET:-$HOME/.kcca-vrsp/db/run/mysqld.sock}"
    echo "Dumping from the local database ..."
    write_header > "$SQL"
    if [ -S "$SOCKET" ]; then
        mariadb-dump --no-defaults -S "$SOCKET" -u root \
            --single-transaction --quick --skip-add-locks --no-tablespaces \
            --default-character-set=utf8mb4 --net-buffer-length=524288 \
            "${KCCA_DB_NAME:-kcca_vendor_relocation}" >> "$SQL"
    else
        mariadb-dump --no-defaults \
            -h "${KCCA_DB_HOST:-127.0.0.1}" -P "${KCCA_DB_PORT:-3307}" \
            -u "${KCCA_DB_USER:-kcca}" -p"${KCCA_DB_PASS:-kcca_dev_2026}" \
            --single-transaction --quick --skip-add-locks --no-tablespaces \
            --default-character-set=utf8mb4 --net-buffer-length=524288 \
            "${KCCA_DB_NAME:-kcca_vendor_relocation}" >> "$SQL"
    fi
fi

# MariaDB's sandbox-mode header is meaningless to MySQL and to phpMyAdmin.
sed -i '/enable the sandbox mode/d' "$SQL"

# Refuse to ship a dump that lacks tables the application queries.
for table in trader_complaints trade_attire support_items chairman_notices; do
    if ! grep -q "CREATE TABLE \`$table\`" "$SQL"; then
        echo "The database is missing '$table'." >&2
        echo "Run 'php database/seed.php' (or database/ensure_trader_support.sql), then retry." >&2
        exit 1
    fi
done

# A fresh demo should not carry the audit trail of whoever built the dump,
# nor backup rows whose files live on the build machine.
cat >> "$SQL" <<'EOF'

-- Start the hosted copy with an empty audit trail and no dangling backups.
TRUNCATE TABLE `audit_log`;
TRUNCATE TABLE `backups`;
EOF

gzip -9 -c "$SQL" > "$GZ"

printf '\nSQL:    %s (%s)\n' "$SQL" "$(du -h "$SQL" | cut -f1)"
printf 'Gzip:   %s (%s)\n' "$GZ" "$(du -h "$GZ" | cut -f1)"
printf 'Tables: %s\n' "$(grep -c 'CREATE TABLE' "$SQL")"
printf '\nImport the .sql.gz in phpMyAdmin. See deploy/HOSTING.md.\n'
