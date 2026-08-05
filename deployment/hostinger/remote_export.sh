#!/usr/bin/env bash
set -euo pipefail

DB_USER=$1
DB_PASS=$2
DB_NAME=$3
OUT_FILE=$4

echo "Exporting database..."
MYSQL_PWD="${DB_PASS}" mysqldump -u "${DB_USER}" "${DB_NAME}" > "${OUT_FILE}"
echo "Database exported successfully to ${OUT_FILE}"
