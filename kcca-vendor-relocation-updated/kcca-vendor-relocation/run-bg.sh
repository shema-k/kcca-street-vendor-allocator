#!/usr/bin/env bash
# Run the KCCA VRSP without a terminal window.
# Starts the database, seeds it once, serves in the background and opens
# the browser. Use stop-bg.sh to stop everything.

set -u
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

PORT="${1:-8080}"

command -v php >/dev/null 2>&1 || { echo "PHP is not installed."; exit 1; }

# 1. Database.
./db.sh start

# 2. Seed once.
COUNT=$(php -r 'require "lib/bootstrap.php"; echo (int) Database::value("SELECT COUNT(*) FROM markets");' 2>/dev/null || echo 0)
if [ "$COUNT" -eq 0 ]; then
  php database/seed.php >/tmp/kcca-seed.log 2>&1
fi

# 3. Free port.
ORIG="$PORT"
while ss -tln 2>/dev/null | grep -q ":${PORT} "; do
  PORT=$((PORT + 1))
  if [ $((PORT - ORIG)) -gt 50 ]; then
    echo "No free port found." >&2; exit 1
  fi
done

# 4. Serve in the background.
nohup php -S "0.0.0.0:${PORT}" -t "$ROOT_DIR" >/tmp/kcca-server.log 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > /tmp/kcca-server.pid
disown 2>/dev/null || true

# 5. Open the browser once the server answers.
(
  for _ in $(seq 1 40); do
    if curl -fsS -o /dev/null "http://localhost:${PORT}/pages/portal.php" 2>/dev/null; then
      ( xdg-open "http://localhost:${PORT}/pages/portal.php" >/dev/null 2>&1 || true ) &
      break
    fi
    sleep 0.25
  done
) &

echo "KCCA VRSP running on http://localhost:${PORT} (pid $SERVER_PID)"
exit 0
