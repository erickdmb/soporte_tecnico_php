#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose exec -T postgres sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "VACUUM (ANALYZE);"'
echo 'Mantenimiento completado. Consulte los tamaños con las consultas del README.'
