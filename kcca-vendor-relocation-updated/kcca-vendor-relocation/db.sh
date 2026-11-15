#!/usr/bin/env bash
#
# KCCA VRSP - the bundled database.
#
#   ./db.sh start     create (first time) and start the database
#   ./db.sh stop      stop it
#   ./db.sh status    say whether it is running
#
# The data lives in ~/.kcca-vrsp/db, outside the project, on port 3307 and a
# private socket, so it never clashes with a MySQL/MariaDB you already run.
#
# To use your own server instead, set KCCA_DB_SOCKET to an empty value and
# point the other KCCA_DB_* variables at it; this script then only checks that
# the server answers:
#   KCCA_DB_HOST=localhost KCCA_DB_PORT=3306 KCCA_DB_SOCKET= \
#   KCCA_DB_USER=kcca KCCA_DB_PASS=secret ./run.sh

set -u

# Debian and Kali keep the server programs in /usr/sbin, which is often not on
# a normal user's PATH.
export PATH="$PATH:/usr/sbin:/usr/local/sbin:/sbin:/usr/local/bin"

DB_NAME="${KCCA_DB_NAME:-kcca_vendor_relocation}"
DB_USER="${KCCA_DB_USER:-kcca}"
DB_PASS="${KCCA_DB_PASS:-kcca_dev_2026}"
DB_PORT="${KCCA_DB_PORT:-3307}"

HOME_DIR="${HOME:-$(getent passwd "$(id -un)" | cut -d: -f6)}"
DBHOME="$HOME_DIR/.kcca-vrsp/db"
DATADIR="$DBHOME/data"
RUNDIR="$DBHOME/run"
TMPDIR_DB="$DBHOME/tmp"
SOCK="$RUNDIR/mysqld.sock"
PIDFILE="$RUNDIR/mysqld.pid"
LOG="$DBHOME/server.log"

say() { printf '  %s\n' "$1"; }
die() { printf '  ERROR: %s\n' "$1" >&2; exit 1; }

# ---- Using the user's own server? Just check it answers. -------------------
if [ "${KCCA_DB_SOCKET+x}" = "x" ] && [ -z "${KCCA_DB_SOCKET}" ]; then
  CLIENT=""
  for c in mariadb mysql; do command -v "$c" >/dev/null 2>&1 && { CLIENT="$c"; break; }; done
  case "${1:-start}" in
    start|status)
      [ -n "$CLIENT" ] || { say "Using your own database server (client not installed, so not checked)."; exit 0; }
      if "$CLIENT" -h "${KCCA_DB_HOST:-127.0.0.1}" -P "${KCCA_DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" -e 'SELECT 1' >/dev/null 2>&1; then
        say "Your own database server is answering."; exit 0
      fi
      die "Cannot connect to your own database server with the KCCA_DB_* settings."
      ;;
    stop) say "Using your own database server; nothing to stop."; exit 0 ;;
  esac
fi

# ---- Find the programs -------------------------------------------------------
SERVER=""
for c in mariadbd mysqld; do command -v "$c" >/dev/null 2>&1 && { SERVER="$c"; break; }; done
CLIENT=""
for c in mariadb mysql; do command -v "$c" >/dev/null 2>&1 && { CLIENT="$c"; break; }; done
ADMIN=""
for c in mariadb-admin mysqladmin; do command -v "$c" >/dev/null 2>&1 && { ADMIN="$c"; break; }; done
INSTALLDB=""
for c in mariadb-install-db mysql_install_db; do command -v "$c" >/dev/null 2>&1 && { INSTALLDB="$c"; break; }; done

need_programs() {
  [ -n "$SERVER" ] && [ -n "$CLIENT" ] && return 0
  cat >&2 <<'MSG'
  ERROR: MariaDB (or MySQL) is not installed.
  On Debian, Ubuntu and Kali install it with:
      sudo apt update && sudo apt install -y mariadb-server mariadb-client php php-mysql
  Then run this again.
MSG
  exit 1
}

running() {
  [ -S "$SOCK" ] && "$CLIENT" --no-defaults -S "$SOCK" -u root -e 'SELECT 1' >/dev/null 2>&1
}

# ---- status / stop -----------------------------------------------------------
case "${1:-start}" in
  status)
    need_programs
    if running; then say "The database is running (port $DB_PORT)."; exit 0; fi
    say "The database is not running."; exit 1
    ;;
  stop)
    need_programs
    if running; then
      if [ -n "$ADMIN" ]; then "$ADMIN" --no-defaults -S "$SOCK" -u root shutdown >/dev/null 2>&1; fi
      for _ in $(seq 1 20); do running || break; sleep 0.5; done
    fi
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null; then
      kill "$(cat "$PIDFILE")" 2>/dev/null || true
    fi
    say "The database is stopped."
    exit 0
    ;;
  start) ;;
  *) echo "Usage: $0 start|stop|status" >&2; exit 2 ;;
esac

# ---- start -------------------------------------------------------------------
need_programs

if running; then
  say "The database is already running."
else
  mkdir -p "$DATADIR" "$RUNDIR" "$TMPDIR_DB"
  chmod 700 "$DBHOME" 2>/dev/null || true

  RUN_AS=()
  if [ "$(id -u)" -eq 0 ]; then RUN_AS=(--user=root); fi

  # First time: create the data directory.
  if [ ! -d "$DATADIR/mysql" ]; then
    say "Creating the database for the first time..."
    if [ -n "$INSTALLDB" ]; then
      "$INSTALLDB" --no-defaults --datadir="$DATADIR" --auth-root-authentication-method=normal \
        --skip-test-db "${RUN_AS[@]}" >"$DBHOME/install.log" 2>&1 \
      || "$INSTALLDB" --no-defaults --datadir="$DATADIR" "${RUN_AS[@]}" >>"$DBHOME/install.log" 2>&1 \
      || die "Could not create the database. See $DBHOME/install.log"
    else
      # MySQL 8 has no install script; the server initialises itself.
      "$SERVER" --no-defaults --initialize-insecure --datadir="$DATADIR" "${RUN_AS[@]}" \
        >"$DBHOME/install.log" 2>&1 || die "Could not create the database. See $DBHOME/install.log"
    fi
  fi

  # A stale socket from a crash would make the server refuse to start.
  rm -f "$SOCK" 2>/dev/null || true

  say "Starting the database..."
  nohup "$SERVER" --no-defaults \
    --datadir="$DATADIR" --socket="$SOCK" --pid-file="$PIDFILE" \
    --port="$DB_PORT" --bind-address=127.0.0.1 --tmpdir="$TMPDIR_DB" \
    "${RUN_AS[@]}" >"$LOG" 2>&1 &
  disown 2>/dev/null || true

  UP=0
  for _ in $(seq 1 60); do
    if running; then UP=1; break; fi
    sleep 0.5
  done
  [ "$UP" -eq 1 ] || die "The database did not start. See $LOG"
fi

# ---- the application database and user (safe to repeat) --------------------
SQL="CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;"

"$CLIENT" --no-defaults -S "$SOCK" -u root -e "$SQL" >/dev/null 2>&1 \
  || die "Could not create the application database and user."

say "The database is ready."
exit 0
