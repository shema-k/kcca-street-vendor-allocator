#!/usr/bin/env bash
# Stop the background KCCA web server (and optionally the database).
set -u
if [ -f /tmp/kcca-server.pid ]; then
  kill "$(cat /tmp/kcca-server.pid)" 2>/dev/null || true
  rm -f /tmp/kcca-server.pid
  echo "Web server stopped."
fi
# Uncomment the next line to also stop the bundled database:
# ./db.sh stop
exit 0
