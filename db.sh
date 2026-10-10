#!/usr/bin/env bash
#
# KCCA VRSP — database bootstrap.
#
# Starts the bundled MariaDB from ~/.kcca-vrsp/db and makes sure the
# application's database and user exist. Safe to run repeatedly: it does
# nothing if the server is already up.
#
#   ./db.sh           start the database (idempotent)
#   ./db.sh status    show whether it is running
#   ./db.sh stop      stop it
#
# To use your own MySQL or MariaDB instead, point the environment at it
# and skip this script entirely:
#
#   KCCA_DB_HOST=localhost KCCA_DB_PORT=3306 KCCA_DB_USER=root \
#   KCCA_DB_PASS=secret ./run.sh

set -euo pipefail

cd "$(dirname "$0")"

DBHOME="${KCCA_DB_HOME:-$HOME/.kcca-vrsp/db}"
DATADIR="$DBHOME/data"
RUNDIR="$DBHOME/run"
TMPDIR_DB="$DBHOME/tmp"
SOCKET="$RUNDIR/mysqld.sock"
PORT="${KCCA_DB_PORT:-3307}"

mkdir -p "$DATADIR" "$RUNDIR" "$TMPDIR_DB"

# Is it already answering?
alive() {
  mysql --socket="$SOCKET" -uroot -N -B -e "SELECT 1" >/dev/null 2>&1
}

start() {
  if alive; then
    echo "  Database already running at $SOCKET"
    return 0
  fi

  # Remove a stale socket from a crashed run; mariadbd will recreate it.
  [ -S "$SOCKET" ] && rm -f "$SOCKET"

  if [ ! -d "$DATADIR/mysql" ]; then
    echo "  Creating a new database directory at $DATADIR …"
    mariadb-install-db --datadir="$DATADIR" \
      --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1
  fi

  echo "  Starting MariaDB …"
  setsid mariadbd --datadir="$DATADIR" \
    --socket="$SOCKET" \
    --port="$PORT" \
    --pid-file="$RUNDIR/mysqld.pid" \
    --tmpdir="$TMPDIR_DB" \
    --bind-address=127.0.0.1 \
    --user="$(whoami)" \
    --log-error="$DBHOME/mariadb.err" \
    >/dev/null 2>&1 </dev/null &
  disown

  for _ in $(seq 1 30); do
    alive && { echo "  Database is up."; return 0; }
    sleep 1
  done

  echo "  MariaDB did not start. Last lines of the log:" >&2
  tail -n 10 "$DBHOME/mariadb.err" >&2 || true
  return 1
}

ensureApp() {
  # The app's own user and database. Root is only ever used here, to create
  # them; the application itself connects as 'kcca'.
  mysql --socket="$SOCKET" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${KCCA_DB_NAME:-kcca_vendor_relocation}\`
  CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS 'kcca'@'localhost' IDENTIFIED BY 'kcca_dev_2026';
CREATE USER IF NOT EXISTS 'kcca'@'127.0.0.1' IDENTIFIED BY 'kcca_dev_2026';
GRANT ALL PRIVILEGES ON \`${KCCA_DB_NAME:-kcca_vendor_relocation}\`.* TO 'kcca'@'localhost';
GRANT ALL PRIVILEGES ON \`${KCCA_DB_NAME:-kcca_vendor_relocation}\`.* TO 'kcca'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
}

case "${1:-start}" in
  start)
    start
    ensureApp
    ;;
  status)
    if alive; then
      echo "running at $SOCKET (port $PORT)"
    else
      echo "not running"; exit 1
    fi
    ;;
  stop)
    if [ -f "$RUNDIR/mysqld.pid" ]; then
      kill "$(cat "$RUNDIR/mysqld.pid")" 2>/dev/null || true
      echo "stopped"
    else
      echo "no pid file; not running"
    fi
    ;;
  *)
    echo "Usage: $0 [start|status|stop]"; exit 1;;
esac