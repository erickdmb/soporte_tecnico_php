$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
$variables = @('APP_PORT', 'POSTGRES_HOST_PORT', 'MONGO_HOST_PORT', 'BIND_ADDRESS')
$anteriores = @{}
foreach ($nombre in $variables) { $anteriores[$nombre] = [Environment]::GetEnvironmentVariable($nombre, 'Process') }
try {
    if (-not (Test-Path .env)) { throw 'Ejecute primero scripts/install.ps1' }
    $env:APP_PORT = '18081'
    $env:POSTGRES_HOST_PORT = '15434'
    $env:MONGO_HOST_PORT = '37019'
    $env:BIND_ADDRESS = '127.0.0.1'
    docker compose -p soporte_php_pruebas up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'Falló la instalación aislada de pruebas' }
    docker compose -p soporte_php_pruebas cp tests/recorrido.php web:/tmp/recorrido.php
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar la prueba' }
    docker compose -p soporte_php_pruebas exec -T -e RUN_INTEGRATION_TESTS=1 web php /tmp/recorrido.php
    if ($LASTEXITCODE -ne 0) { throw 'Falló el recorrido' }
    docker compose -p soporte_php_pruebas exec -T web php verificar_consistencia.php
    if ($LASTEXITCODE -ne 0) { throw 'Se detectaron diferencias entre motores' }
} finally {
    # Retirar los contenedores temporales sin eliminar sus volúmenes ni la imagen compartida.
    docker compose -p soporte_php_pruebas down
    foreach ($nombre in $variables) { [Environment]::SetEnvironmentVariable($nombre, $anteriores[$nombre], 'Process') }
    Pop-Location
}
