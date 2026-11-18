param([string]$Destino = "backups/$(Get-Date -Format 'yyyyMMdd-HHmmss')")
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
$estabaActiva = $false
try {
    if (Test-Path -LiteralPath $Destino) { throw 'La carpeta destino ya existe; elija una nueva.' }
    $servicios = docker compose ps --status running --services
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar Docker' }
    $estabaActiva = @($servicios) -contains 'web'
    if ($estabaActiva) {
        docker compose stop web
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo detener la web' }
    }
    New-Item -ItemType Directory -Path $Destino | Out-Null
    docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc -f /tmp/soporte.backup'
    if ($LASTEXITCODE -ne 0) { throw 'Falló pg_dump' }
    docker compose cp postgres:/tmp/soporte.backup "$Destino/postgresql.backup"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la copia PostgreSQL' }
    docker compose exec -T mongodb sh -c 'mongodump --username "$MONGO_INITDB_ROOT_USERNAME" --password "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --db "$MONGO_INITDB_DATABASE" --archive=/tmp/soporte.archive --gzip --quiet'
    if ($LASTEXITCODE -ne 0) { throw 'Falló mongodump' }
    docker compose cp mongodb:/tmp/soporte.archive "$Destino/mongodb.archive.gz"
    if ($LASTEXITCODE -ne 0) { throw 'Falló la copia MongoDB' }
    Write-Output "Respaldo completo en $Destino; incluye las fotos. Conserve ambos archivos juntos."
} finally {
    if ($estabaActiva) { docker compose start web }
    Pop-Location
}
