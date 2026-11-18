$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    docker compose exec -T postgres sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "VACUUM (ANALYZE);"'
    if ($LASTEXITCODE -ne 0) { throw 'Falló VACUUM/ANALYZE' }
    Write-Output 'Mantenimiento completado. Consulte los tamaños con las consultas del README.'
} finally { Pop-Location }
