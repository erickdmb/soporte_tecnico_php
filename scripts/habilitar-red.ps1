#Requires -RunAsAdministrator
$ErrorActionPreference = 'Stop'
$raiz = Split-Path $PSScriptRoot -Parent
$valores = @{ APP_PORT = '8081'; POSTGRES_HOST_PORT = '5434'; MONGO_HOST_PORT = '27019' }
$archivo = Join-Path $raiz '.env'
if (-not (Test-Path -LiteralPath $archivo)) { throw 'Ejecute primero el instalador para crear .env.' }
foreach ($linea in Get-Content -LiteralPath $archivo) {
    if ($linea -match '^\s*(APP_PORT|POSTGRES_HOST_PORT|MONGO_HOST_PORT)\s*=\s*([0-9]+)\s*(?:#.*)?$') {
        $valores[$Matches[1]] = $Matches[2]
    }
}
$puertos = @()
foreach ($nombre in @('APP_PORT', 'POSTGRES_HOST_PORT', 'MONGO_HOST_PORT')) {
    $puerto = 0
    if (-not [int]::TryParse($valores[$nombre], [ref]$puerto) -or $puerto -lt 1 -or $puerto -gt 65535) {
        throw "Puerto inválido en $nombre."
    }
    $puertos += "$puerto"
}
$regla = Get-NetFirewallRule -Name 'SoportePHP-LAN' -ErrorAction SilentlyContinue
if ($regla) {
    $regla | Set-NetFirewallRule -Enabled True -Direction Inbound -Action Allow -Profile Any -RemoteAddress LocalSubnet
    $regla | Get-NetFirewallPortFilter | Set-NetFirewallPortFilter -Protocol TCP -LocalPort $puertos
} else {
    New-NetFirewallRule -Name 'SoportePHP-LAN' -DisplayName 'Soporte PHP - red local del aula' `
        -Direction Inbound -Action Allow -Protocol TCP -LocalPort $puertos `
        -RemoteAddress LocalSubnet -Profile Any | Out-Null
}
Write-Output "Regla habilitada para la subred local: TCP $($puertos -join ', ')."
