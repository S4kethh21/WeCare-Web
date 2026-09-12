#!/bin/sh
set -e

# Support cloud providers passing dynamic $PORT (Render uses 10000, Cloud Run 8080, standard 80)
TARGET_PORT="${PORT:-80}"

echo "Starting WeCare Hospital Server on port ${TARGET_PORT}..."
sed -i "s/Listen 80/Listen ${TARGET_PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${TARGET_PORT}/g" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
