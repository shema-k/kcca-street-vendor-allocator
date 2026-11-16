#!/usr/bin/env bash
#
# KCCA VRSP container entrypoint.
#
#   1. wait for MariaDB to accept connections
#   2. seed the database the first time (or on KCCA_AUTO_SEED=force)
#   3. hand over to the command (apache2-foreground)
#
set -euo pipefail

cd /var/www/html

say() { printf '\033[1m[kcca]\033[0m %s\n' "$*"; }
die() { printf '\033[1m[kcca]\033[0m ERROR: %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Runtime directories. Named volumes arrive root-owned; Apache workers run
# as www-data, so hand them over before the server starts.
# ---------------------------------------------------------------------------
mkdir -p /var/lib/php/sessions /var/log/php /tmp/kcca-vrsp-backups
chown -R www-data:www-data /var/lib/php/sessions /var/log/php /tmp/kcca-vrsp-backups
chmod 700 /var/lib/php/sessions /var/log/php
chmod 700 /tmp/kcca-vrsp-backups

# ---------------------------------------------------------------------------
# Wait for the database. The application connects over TCP inside the
# container network, never through the bundled Unix socket.
# ---------------------------------------------------------------------------
wait_for_db() {
    local attempt=0
    local max="${KCCA_DB_WAIT_ATTEMPTS:-90}"

    say "waiting for MariaDB at ${KCCA_DB_HOST}:${KCCA_DB_PORT} (database ${KCCA_DB_NAME})"
    while [ "$attempt" -lt "$max" ]; do
        if php -r '
            try {
                new PDO(
                    sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                        getenv("KCCA_DB_HOST"), getenv("KCCA_DB_PORT"), getenv("KCCA_DB_NAME")),
                    getenv("KCCA_DB_USER"),
                    getenv("KCCA_DB_PASS"),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
                );
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                exit(1);
            }
        ' 2>/dev/null; then
            say "database is answering"
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 2
    done

    die "database did not become ready after $((max * 2))s"
}

# ---------------------------------------------------------------------------
# Seed once. Counting markets is the cheapest truthful "is it installed?"
# test: the seeder is the only thing that writes them.
# ---------------------------------------------------------------------------
market_count() {
    php -r '
        try {
            $pdo = new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                    getenv("KCCA_DB_HOST"), getenv("KCCA_DB_PORT"), getenv("KCCA_DB_NAME")),
                getenv("KCCA_DB_USER"),
                getenv("KCCA_DB_PASS"),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo (int) $pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
        } catch (Throwable $e) {
            echo 0;
        }
    ' 2>/dev/null
}

wait_for_db

if [ "${KCCA_AUTO_SEED:-1}" = "force" ]; then
    say "KCCA_AUTO_SEED=force — rebuilding the database"
    php -d display_errors=stderr database/seed.php || die "seeding failed"
elif [ "${KCCA_AUTO_SEED:-1}" != "0" ]; then
    count="$(market_count)"
    if [ "${count:-0}" -eq 0 ]; then
        say "empty database — applying schema and seeding (about 20 seconds)"
        php -d display_errors=stderr database/seed.php || die "seeding failed"
    else
        say "database already seeded (${count} markets)"
    fi
else
    say "KCCA_AUTO_SEED=0 — leaving the database untouched"
fi

say "starting Apache on port 80"
exec "$@"
