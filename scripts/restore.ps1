param([Parameter(Mandatory=$true)][string]$Origen)
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    if (-not (Test-Path "$Origen/postgresql.backup") -or -not (Test-Path "$Origen/mongodb.archive.gz")) { throw 'Faltan archivos del respaldo' }
    # Revisar primero MongoDB; las bases principales nunca se reemplazan.
    docker compose exec -T mongodb sh -c 'test "$(mongosh --quiet --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "db.getSiblingDB(\"soporte_php_mongo_restaurada\").getCollectionNames().length")" = "0"'
    if ($LASTEXITCODE -ne 0) { throw 'El destino MongoDB ya tiene datos o no se pudo consultar' }
    docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" soporte_php_restaurada'
    if ($LASTEXITCODE -ne 0) { throw 'No se creó la base de recuperación. Si ya existe, se conserva.' }
    docker compose cp "$Origen/postgresql.backup" postgres:/tmp/restaurar.backup
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar PostgreSQL' }
    docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d soporte_php_restaurada --no-owner --no-acl --exit-on-error /tmp/restaurar.backup'
    if ($LASTEXITCODE -ne 0) { throw 'Falló pg_restore; revise la base separada de recuperación' }
    docker compose cp "$Origen/mongodb.archive.gz" mongodb:/tmp/restaurar.archive.gz
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar MongoDB' }
    docker compose exec -T mongodb sh -c 'mongorestore --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --archive=/tmp/restaurar.archive.gz --gzip --nsFrom "$MONGO_INITDB_DATABASE.*" --nsTo "soporte_php_mongo_restaurada.*" --stopOnError --quiet'
    if ($LASTEXITCODE -ne 0) { throw 'Falló mongorestore; revise la base separada de recuperación' }
    Write-Output 'Recuperación completa: soporte_php_restaurada y soporte_php_mongo_restaurada. La web sigue usando sus bases originales.'
} finally { Pop-Location }
