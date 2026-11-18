#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
test -f .env || { echo 'Ejecute primero scripts/install.sh'; exit 1; }
export APP_PORT=18081
export POSTGRES_HOST_PORT=15434
export MONGO_HOST_PORT=37019
export BIND_ADDRESS=127.0.0.1
trap 'docker compose -p soporte_php_pruebas down' EXIT
docker compose -p soporte_php_pruebas up -d --build
docker compose -p soporte_php_pruebas cp tests/recorrido.php web:/tmp/recorrido.php
docker compose -p soporte_php_pruebas exec -T -e RUN_INTEGRATION_TESTS=1 web php /tmp/recorrido.php
docker compose -p soporte_php_pruebas exec -T web php verificar_consistencia.php
