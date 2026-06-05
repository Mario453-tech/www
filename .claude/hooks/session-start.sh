#!/bin/bash
# SessionStart hook — uruchamia MariaDB + laduje schemat dla sesji webowych.
# SessionStart hook — starts MariaDB + loads schema for web sessions.
#
# Uruchamiany tylko w zdalnym srodowisku Claude Code on the web.
# Only runs in the remote Claude Code on the web environment.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

PROJ="${CLAUDE_PROJECT_DIR:-/home/user/www}"
DB_NAME="gra1"
DB_USER="oiltest"
DB_PASS="oiltest"
SOCKET="/run/mysqld/mysqld.sock"
LOG="/tmp/mariadb-session.log"

# --- 1. Napraw wlasciciela katalogu danych (po resecie kontenera bedziemy root).
# --- 1. Fix datadir ownership (after a container reset we run as root).
mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld 2>/dev/null || true
chown -R mysql:mysql /var/lib/mysql 2>/dev/null || true

# --- 2. Uruchom mariadbd jesli nie dziala / Start mariadbd if not running.
if ! mysql -uroot --protocol=socket -e "SELECT 1" >/dev/null 2>&1; then
    setsid mariadbd \
        --user=mysql \
        --datadir=/var/lib/mysql \
        --socket="$SOCKET" \
        --pid-file=/run/mysqld/mysqld.pid \
        --bind-address=127.0.0.1 \
        --port=3306 \
        >"$LOG" 2>&1 < /dev/null &
    disown 2>/dev/null || true

    # Czekaj az wstanie (max 40s) / Wait until up (max 40 s)
    for i in $(seq 1 40); do
        if mysql -uroot --protocol=socket -e "SELECT 1" >/dev/null 2>&1; then
            break
        fi
        sleep 1
    done
fi

# --- 3. Baza danych / Database
mysql -uroot --protocol=socket -e \
    "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

# --- 4. Uzytkownik TCP (root loguje sie przez unix_socket, testy uzywaja 127.0.0.1).
# --- 4. TCP user (root authenticates via unix_socket; tests connect via 127.0.0.1).
mysql -uroot --protocol=socket <<SQL 2>/dev/null || true
CREATE USER IF NOT EXISTS '${DB_USER}'@'%'         IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'  IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# --- 5. Schemat (tylko jesli baza jest pusta) / Schema (only when the db is empty).
TABLE_COUNT=$(mysql -uroot --protocol=socket -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
    --skip-column-names 2>/dev/null || echo "0")

if [ "${TABLE_COUNT:-0}" -lt "10" ]; then
    mysql -uroot --protocol=socket "${DB_NAME}" < "${PROJ}/tests/ci-schema.sql"
fi

# --- 6. Zapisz zmienne srodowiskowe / Export env vars for the session.
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
    cat >> "$CLAUDE_ENV_FILE" <<ENV
export DB_HOST=127.0.0.1
export DB_NAME=${DB_NAME}
export DB_USER=${DB_USER}
export DB_PASS=${DB_PASS}
export DB_CHARSET=utf8mb4
ENV
fi

echo "[session-start] MariaDB OK — ${DB_NAME}@127.0.0.1 (user: ${DB_USER})"
