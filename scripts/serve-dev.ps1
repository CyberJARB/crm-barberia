# Servidor de desarrollo con auto-reinicio.
#
# Por qué: el PHP 8.5.3 ZTS local se cae esporádicamente (exit 255 sin
# error visible — el mismo fallo que afecta a PHPStan). `php artisan serve`
# es un solo proceso, así que cada caída tumba el servidor entero.
# Este bucle lo reinicia en 1 segundo y deja constancia de cada caída.
#
# Uso:  .\scripts\serve-dev.ps1            (puerto 8000)
#       .\scripts\serve-dev.ps1 -Port 8080

param([int]$Port = 8000)

Set-Location (Split-Path $PSScriptRoot -Parent)

while ($true) {
    Write-Host "[serve-dev] Arrancando php artisan serve en puerto $Port..." -ForegroundColor Cyan
    php artisan serve --port=$Port

    $codigo = $LASTEXITCODE
    Write-Host "[serve-dev] El servidor terminó (exit $codigo) a las $(Get-Date -Format 'HH:mm:ss'). Reiniciando en 1s..." -ForegroundColor Yellow
    Start-Sleep -Seconds 1
}
