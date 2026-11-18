$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    if (-not (Test-Path -LiteralPath '.env')) {
        $contenido = Get-Content .env.example -Raw
        $contenido = [regex]::Replace($contenido, '(?m)(?<=\=)cambiar_[^\r\n]+', { [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N') })
        [IO.File]::WriteAllText((Join-Path (Get-Location) '.env'), $contenido, (New-Object Text.UTF8Encoding($false)))
        Write-Output 'Se creó .env con claves locales aleatorias.'
    }
    docker compose up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'Falló la instalación. Revise: docker compose logs --tail 80' }
    Write-Output 'Abra http://localhost:8081 (o el APP_PORT indicado en .env).'
} finally { Pop-Location }
