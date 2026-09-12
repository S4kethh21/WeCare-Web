#!/bin/sh
set -e

# Render assigns dynamic $PORT (defaulting to 10000)
TARGET_PORT="${PORT:-10000}"

echo "Starting WeCare Hospital Server on port ${TARGET_PORT}..."
sed -i -E "s/Listen [0-9]+/Listen ${TARGET_PORT}/g" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost \*:${TARGET_PORT}>/g" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
