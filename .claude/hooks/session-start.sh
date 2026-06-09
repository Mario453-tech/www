#!/bin/bash
# SessionStart hook: uruchamia MariaDB w tle, zeby testy PHPUnit z
# tests/MySqlIntegration/ mogly sie polaczyc z baza 'gra1'.
# SessionStart hook: starts MariaDB in the background so PHPUnit tests in
# tests/MySqlIntegration/ can connect to the 'gra1' database.
#
# Idempotentny: jezeli MariaDB juz dziala, nic nie robi.
# Idempotent: if MariaDB is already running, this script is a no-op.

set -euo pipefail

# Tylko w srodowisku Claude Code on the web (kontener z preinstalowana MariaDB).
# Only in the Claude Code on the web environment (container with pre-installed MariaDB).
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
    exit 0
fi

# Jezeli port 3306 juz nasluchuje, MariaDB dziala.
# If port 3306 is already listening, MariaDB is running.
if netstat -tln 2>/dev/null | grep -q ':3306' || \
   cat /proc/net/tcp 2>/dev/null | grep -q ' 0CEA'; then
    echo "[session-start] MariaDB already running on :3306"
    exit 0
fi

# Zainstaluj MariaDB jesli brak binarki.
# Install MariaDB if binary is missing.
if [ ! -x /usr/sbin/mariadbd ]; then
    echo "[session-start] mariadbd not found, installing mariadb-server..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y --fix-missing mariadb-server 2>&1 | tail -3 || true
    if [ ! -x /usr/sbin/mariadbd ]; then
        echo "[session-start] ERROR: could not install mariadbd" >&2
        exit 0
    fi
fi

# Zainicjalizuj katalog danych jezeli pusty.
# Initialize data directory if empty.
if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[session-start] Initializing MariaDB data directory..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /tmp/mariadb-init.log 2>&1 || true
fi

# Wymagane katalogi runtime (socket, pid file).
# Required runtime directories (socket, pid file).
mkdir -p /var/run/mysqld
chown mysql:mysql /var/run/mysqld 2>/dev/null || true

# Start daemona w tle. Logi -> /tmp/mariadb.log (sesja jest ephemeralna).
# Start the daemon in the background. Logs -> /tmp/mariadb.log (ephemeral session).
nohup sudo -u mysql /usr/sbin/mariadbd \
    --datadir=/var/lib/mysql \
    --socket=/var/run/mysqld/mysqld.sock \
    --pid-file=/var/run/mysqld/mariadbd.pid \
    --user=mysql \
    > /tmp/mariadb.log 2>&1 &

# Poczekaj az port 3306 zacznie nasluchiwac (do 20 sekund).
# Wait until port 3306 starts listening (up to 20 seconds).
for i in $(seq 1 20); do
    if cat /proc/net/tcp 2>/dev/null | grep -q ' 0CEA'; then
        echo "[session-start] MariaDB up after ${i}s"
        # Utworz baze gra1 jezeli nie istnieje.
        # Create gra1 database if it does not exist.
        mysql -u root -e "CREATE DATABASE IF NOT EXISTS gra1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
        exit 0
    fi
    sleep 1
done

echo "[session-start] WARNING: MariaDB did not open :3306 within 20s" >&2
tail -20 /tmp/mariadb.log >&2 || true
# Nie blokujemy sesji - testy MySQL po prostu beda failowac z PDOException
# i to bedzie czytelny sygnal w wynikach testow.
# Do not block the session - MySQL tests will fail with PDOException
# which is a clear signal in the test results.
exit 0
