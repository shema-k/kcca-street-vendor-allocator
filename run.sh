#!/usr/bin/env bash
#
# KCCA VRSP — start the system and open it.
#
#   ./run.sh              serve on http://localhost:8080 and open a browser
#   ./run.sh 9000         serve on another port
#
# Starts the database if it is not already running, seeds it the first
# time, serves, and opens the browser. Press Ctrl+C to stop the web
# server; the database keeps running (./db.sh stop stops it).

set -euo pipefail

cd "$(dirname "$0")"

PORT="${1:-8080}"

command -v php >/dev/null 2>&1 || { echo "PHP is not installed or not on PATH."; exit 1; }

echo
echo "  KCCA VRSP — Kampala Capital City Authority"
echo "  ------------------------------------------"

# 1. The database.
echo
./db.sh start

# 2. Seed once, if it has never been seeded.
COUNT=$(php -r 'require "lib/bootstrap.php"; echo (int) Database::value("SELECT COUNT(*) FROM markets");' 2>/dev/null || echo 0)
if [ "$COUNT" -eq 0 ]; then
  echo "  The database is empty. Seeding it — this takes about 20 seconds."
  php database/seed.php
fi

# 3. A free port. If the one you asked for is taken, use the next one and
#    say so, rather than exiting with a bind error that means nothing.
if ss -tln 2>/dev/null | grep -q ":${PORT} "; then
  USED="$PORT"
  while ss -tln 2>/dev/null | grep -q ":${PORT} "; do
    PORT=$((PORT + 1))
    case $((PORT - USED)) in
      50) echo "  Nothing free between ${USED} and ${PORT}. Stop something first." >&2; exit 1 ;;
    esac
  done
  echo "  Port ${USED} is already in use — using ${PORT} instead."
fi

# 4. Serve. Bind to 0.0.0.0 rather than "localhost": on a system where
#    localhost resolves to ::1, PHP's server listens on IPv6 only and a
#    request to 127.0.0.1 is refused. 0.0.0.0 answers on IPv4, and
#    browsers looking up localhost still reach it.
echo
echo "  Open:      http://localhost:${PORT}"
echo "  Sign in:   admin / KCCA VRSP 2026"
echo "  Portal:    http://localhost:${PORT}/pages/portal.php  (no sign-in)"
echo
echo "  This window is the server. Leave it open."
echo "  Press Ctrl+C to stop it."
echo

# 5. Open the browser, once the server is actually answering. A fixed
#    sleep is a guess; polling means the tab is never opened on a dead
#    server.
( for _ in $(seq 1 40); do
    if curl -fsS -o /dev/null "http://localhost:${PORT}/pages/portal.php" 2>/dev/null; then
      ( xdg-open "http://localhost:${PORT}/pages/portal.php" >/dev/null 2>&1 || true ) &
      break
    fi
    sleep 0.25
  done ) &

php -S "0.0.0.0:${PORT}" -t .