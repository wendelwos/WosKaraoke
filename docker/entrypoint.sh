#!/bin/bash
set -e

# Script de inicialização do container da aplicação
# Gera o arquivo .env com as variáveis de ambiente do Docker

echo "==> Gerando arquivo .env para o ambiente Docker..."

cat > /var/www/html/.env << EOF
# Gerado automaticamente pelo entrypoint Docker
DB_TYPE=mysql
DB_HOST=${DB_HOST:-db}
DB_NAME=${DB_NAME:-woskaraoke}
DB_USER=${DB_USER:-karaoke_user}
DB_PASS=${DB_PASS:-karaoke_pass}

GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID:-}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET:-}

PUSHER_APP_ID=${PUSHER_APP_ID:-}
PUSHER_KEY=${PUSHER_KEY:-}
PUSHER_SECRET=${PUSHER_SECRET:-}
PUSHER_CLUSTER=${PUSHER_CLUSTER:-sa1}

APP_DEBUG=${APP_DEBUG:-true}
APP_ENV=${APP_ENV:-development}
EOF

echo "==> .env gerado com sucesso!"
echo "    DB_HOST=${DB_HOST:-db}"
echo "    DB_NAME=${DB_NAME:-woskaraoke}"

# Garantir permissões corretas
chown -R www-data:www-data /var/www/html
chmod 644 /var/www/html/.env

echo "==> Iniciando Apache..."
exec apache2-foreground
