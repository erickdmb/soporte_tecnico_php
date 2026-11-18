#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
origen=${1:?Indique la carpeta de respaldo}
test -f "$origen/postgresql.backup"
test -f "$origen/mongodb.archive.gz"
docker compose exec -T mongodb sh -c 'test "$(mongosh --quiet --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "db.getSiblingDB(\"soporte_php_mongo_restaurada\").getCollectionNames().length")" = "0"'
docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" soporte_php_restaurada'
docker compose cp "$origen/postgresql.backup" postgres:/tmp/restaurar.backup
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d soporte_php_restaurada --no-owner --no-acl --exit-on-error /tmp/restaurar.backup'
docker compose cp "$origen/mongodb.archive.gz" mongodb:/tmp/restaurar.archive.gz
docker compose exec -T mongodb sh -c 'mongorestore --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --archive=/tmp/restaurar.archive.gz --gzip --nsFrom "$MONGO_INITDB_DATABASE.*" --nsTo "soporte_php_mongo_restaurada.*" --stopOnError --quiet'
echo 'Recuperación completa: soporte_php_restaurada y soporte_php_mongo_restaurada. La web sigue usando sus bases originales.'
