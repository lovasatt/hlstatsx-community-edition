#!/bin/bash
set -e

SQL_FILE="/tmp/install.sql"

TABLE_EXISTS=$(mariadb -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW TABLES LIKE 'hlstats_Options';" "$MYSQL_DATABASE")

if [ -z "$TABLE_EXISTS" ]; then
    echo "--- FIRST RUN ---"
    if [ -f "$SQL_FILE" ]; then
        echo "Cleaning SQL file (removing \\r) and importing..."
        sed 's/\r$//' "$SQL_FILE" | mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
        echo "Import completed successfully."
    else
        echo "ERROR: $SQL_FILE not found! Import skipped."
    fi
else
    echo "Database already contains tables, skipping install.sql import."
fi

if [ ! -z "$HLX_PROXY_KEY" ]; then
    echo "Updating proxy key in the database: $HLX_PROXY_KEY"
    mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" <<-EOSQL
        UPDATE hlstats_Options SET value = '$HLX_PROXY_KEY' WHERE keyname = 'Proxy_Key';
EOSQL
else
    echo "WARNING: HLX_PROXY_KEY environment variable is not set, key not updated."
fi

echo "--- DATABASE INITIALIZATION COMPLETE ---"
