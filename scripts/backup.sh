#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
destino=${1:-backups/$(date +%Y%m%d-%H%M%S)}
test ! -e "$destino" || { echo 'La carpeta destino ya existe; elija una nueva.'; exit 1; }
servicios=$(docker compose ps --status running --services)
activa=false
trap 'if [ "$activa" = true ]; then docker compose start web; fi' EXIT
if printf '%s\n' "$servicios" | grep -qx web; then
    activa=true
    docker compose stop web
fi
mkdir -p "$destino"
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc -f /tmp/soporte.backup'
docker compose cp postgres:/tmp/soporte.backup "$destino/postgresql.backup"
docker compose exec -T mongodb sh -c 'mongodump --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --db "$MONGO_INITDB_DATABASE" --archive=/tmp/soporte.archive --gzip --quiet'
docker compose cp mongodb:/tmp/soporte.archive "$destino/mongodb.archive.gz"
printf 'Respaldo completo en %s; incluye fotos. Conserve ambos archivos juntos.\n' "$destino"
