# Run from PowerShell:  cd C:\DBMS ; .\serve.ps1
# Or: Right-click serve.ps1 -> Run with PowerShell (may need to allow scripts once)

Set-Location $PSScriptRoot

$ensure = Join-Path $PSScriptRoot "ensure-mysql.bat"
if (Test-Path $ensure) {
    & cmd.exe /c "call `"$ensure`""
}

function Find-PhpExe {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $paths = @(
        "$env:HOSPITAL_PHP",
        "C:\xampp\php\php.exe",
        "D:\xampp\php\php.exe",
        "E:\xampp\php\php.exe",
        "C:\php\php.exe"
    ) | Where-Object { $_ -and (Test-Path $_) }

    foreach ($p in $paths) { return $p }

    foreach ($d in @("C:\wamp64\bin\php", "C:\laragon\bin\php")) {
        if (Test-Path $d) {
            $exe = Get-ChildItem -Path $d -Filter php.exe -Recurse -Depth 3 -ErrorAction SilentlyContinue |
                Select-Object -First 1 -ExpandProperty FullName
            if ($exe) { return $exe }
        }
    }
    return $null
}

$php = Find-PhpExe
if (-not $php) {
    Write-Host ""
    Write-Host "PHP not found. Install XAMPP from https://www.apachefriends.org/" -ForegroundColor Yellow
    Write-Host "Then run:" -ForegroundColor Yellow
    Write-Host ('  & "C:\xampp\php\php.exe" -S 127.0.0.1:5500 -t "' + $PSScriptRoot + '"') -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

Write-Host "Using: $php" -ForegroundColor Green
Write-Host "Open: http://127.0.0.1:5500/`n" -ForegroundColor Green

Start-Process "http://127.0.0.1:5500/"
& $php -S "127.0.0.1:5500" -t $PSScriptRoot
