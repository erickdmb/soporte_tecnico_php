param([string]$Destino)
$ErrorActionPreference = 'Stop'
$raiz = (Resolve-Path -LiteralPath (Split-Path $PSScriptRoot -Parent)).Path
if (-not $Destino) { $Destino = Join-Path (Split-Path $raiz -Parent) 'soporte-php-alumnos.zip' }
$destinoAbsoluto = [IO.Path]::GetFullPath($Destino)
if (Test-Path -LiteralPath $destinoAbsoluto) { throw 'El ZIP ya existe. Indique otro archivo con -Destino.' }

# Lista explícita: nunca se incluyen .env, respaldos o volúmenes locales.
$archivos = @()
foreach ($carpeta in @('app', 'database', 'scripts', 'tests', 'docs')) {
    $archivos += Get-ChildItem -LiteralPath (Join-Path $raiz $carpeta) -Recurse -File -Force
}
foreach ($nombre in @('README.md', 'compose.yaml', '.env.example', '.gitignore', '.gitattributes')) {
    $archivos += Get-Item -LiteralPath (Join-Path $raiz $nombre) -Force
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::Open($destinoAbsoluto, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($archivo in $archivos) {
        $relativo = $archivo.FullName.Substring($raiz.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $archivo.FullName, "soporte-php/$relativo") | Out-Null
    }
} finally { $zip.Dispose() }
Write-Output "Entrega creada: $destinoAbsoluto ($($archivos.Count) archivos)."
