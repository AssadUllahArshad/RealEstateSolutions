param([switch]$Stop, [switch]$NoBrowser, [switch]$Build)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeRoot = Join-Path $projectRoot '.runtime'
$phpPath = Join-Path $runtimeRoot 'php84/php.exe'
$mysqlBase = Join-Path $runtimeRoot 'mysql-8.4.13-winx64'
$mysqlPath = Join-Path $mysqlBase 'bin/mysqld.exe'
$dataPath = Join-Path $runtimeRoot 'mysql-data'
$statePath = Join-Path $runtimeRoot 'local-services.json'
$url = 'http://127.0.0.1:18080'
$services = @()

function Test-Port([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try { return $client.ConnectAsync('127.0.0.1', $Port).Wait(500) -and $client.Connected }
    catch { return $false }
    finally { $client.Dispose() }
}

function Wait-Port([int]$Port, [System.Diagnostics.Process]$Process) {
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if (Test-Port $Port) { return }
        if ($Process.HasExited) { throw "Service exited. Inspect the local service logs in $runtimeRoot" }
        Start-Sleep -Milliseconds 500
    }
    throw "Service did not start on port $Port. Inspect logs in $runtimeRoot"
}

function Save-Service([string]$Kind, [System.Diagnostics.Process]$Process) {
    $script:services += [pscustomobject]@{ Kind = $Kind; Id = $Process.Id; Started = $Process.StartTime.ToUniversalTime().Ticks.ToString() }
    ConvertTo-Json -InputObject @($script:services) | Set-Content -LiteralPath $statePath -Encoding UTF8
}

$mutexName = 'Local\EstateFlow-' + ([System.BitConverter]::ToString([System.Security.Cryptography.SHA256]::Create().ComputeHash([System.Text.Encoding]::UTF8.GetBytes($projectRoot))).Replace('-', ''))
$mutex = New-Object System.Threading.Mutex($false, $mutexName)
$ownsMutex = $false
try {
    $ownsMutex = $mutex.WaitOne(1000)
    if (!$ownsMutex) { throw 'Another EstateFlow launcher is running. Wait for it to finish.' }
    if (Test-Path -LiteralPath $statePath) {
        $loadedServices = Get-Content -Raw -LiteralPath $statePath | ConvertFrom-Json
        $services = @(foreach ($entry in $loadedServices) { if ($null -ne $entry -and $entry.Id) { $entry } })
    }
    if ($Stop) {
        foreach ($service in @($services | Sort-Object Kind -Descending)) {
            if ($service.Kind -eq 'mysql') { continue }
            $expectedPath = if ($service.Kind -eq 'web') { $phpPath } elseif ($service.Kind -eq 'mysql') { $mysqlPath } else { throw 'Unknown managed service.' }
            $process = Get-Process -Id $service.Id -ErrorAction SilentlyContinue
            if ($process -and $process.Path -eq $expectedPath -and $process.StartTime.ToUniversalTime().Ticks.ToString() -eq $service.Started) {
                Stop-Process -Id $process.Id
                if (!$process.WaitForExit(5000)) { throw 'Web server did not stop within five seconds.' }
                Write-Host "Stopped project-local $($service.Kind)."
            } elseif ($process) {
                throw 'Process identity changed. Refusing to stop an unrelated program.'
            }
        }
        $services = @($services | Where-Object Kind -eq 'mysql')
        ConvertTo-Json -InputObject @($services) | Set-Content -LiteralPath $statePath -Encoding UTF8
        Write-Host 'EstateFlow web server stopped. Project-local MySQL remains running; it is not force-terminated.'
        Write-Host 'Existing XAMPP services were not changed.'
        exit 0
    }
    foreach ($required in @($phpPath, $mysqlPath, (Join-Path $dataPath 'auto.cnf'), (Join-Path $projectRoot '.env'), (Join-Path $projectRoot 'vendor/autoload.php'))) {
        if (!(Test-Path -LiteralPath $required)) { throw "Required local file missing: $required. The launcher never initializes or replaces existing databases." }
    }
    $services = @($services | Where-Object { $entry = $_; $p = Get-Process -Id $entry.Id -ErrorAction SilentlyContinue; $p -and $p.StartTime.ToUniversalTime().Ticks.ToString() -eq $entry.Started })
    $env:Path = (Split-Path -Parent $phpPath) + ';' + $env:Path
    $env:TEMP = Join-Path $runtimeRoot 'browser-temp'
    $env:TMP = $env:TEMP
    New-Item -ItemType Directory -Path $env:TEMP -Force | Out-Null
    Set-Location -LiteralPath $projectRoot
    & $phpPath scripts/local-runtime.php config
    if ($LASTEXITCODE -ne 0) { throw 'Local configuration check failed.' }
    if (!(Test-Port 33084)) {
        $mysqlArguments = @('--no-defaults', "--basedir=`"$mysqlBase`"", "--datadir=`"$dataPath`"", "--tmpdir=`"$(Join-Path $runtimeRoot 'browser-temp')`"", '--bind-address=127.0.0.1', '--port=33084', '--mysqlx=OFF', '--skip-name-resolve', '--innodb-buffer-pool-size=64M', '--table-open-cache=256', '--max-connections=30', '--performance-schema=OFF', "--log-error=`"$(Join-Path $runtimeRoot 'local-mysql.log')`"")
        $mysqlProcess = Start-Process -FilePath $mysqlPath -ArgumentList $mysqlArguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
        Save-Service 'mysql' $mysqlProcess
        Wait-Port 33084 $mysqlProcess
    }
    & $phpPath scripts/local-runtime.php login
    if ($LASTEXITCODE -ne 0) { throw 'Database/login preparation failed. No database is reinitialized by this launcher.' }
    & $phpPath scripts/local-runtime.php accountant
    if ($LASTEXITCODE -ne 0) { throw 'Accountant test-login preparation failed.' }
    if ($Build -or !(Test-Path -LiteralPath (Join-Path $projectRoot 'public/build/manifest.json'))) {
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    }
    if (Test-Port 18080) {
        $known = @($services | Where-Object Kind -eq 'web')
        if ($known.Count -eq 0) { throw 'Port 18080 is already occupied by another server. No process was stopped.' }
        Write-Host 'EstateFlow is already running.'
    } else {
        $env:APP_URL = $url
        $routerPath = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        $webProcess = Start-Process -FilePath $phpPath -ArgumentList @('-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M', '-S', '127.0.0.1:18080', "`"$routerPath`"") -WorkingDirectory (Join-Path $projectRoot 'public') -RedirectStandardOutput (Join-Path $runtimeRoot 'local-web.log') -RedirectStandardError (Join-Path $runtimeRoot 'local-web-errors.log') -WindowStyle Hidden -PassThru
        Save-Service 'web' $webProcess
        Wait-Port 18080 $webProcess
    }
    Write-Host "`nEstateFlow: $url/login"
    Write-Host "PHP: $phpPath"
    Write-Host "Local login details: $(Join-Path $runtimeRoot 'local-login.txt')"
    Write-Host "Accountant login details: $(Join-Path $runtimeRoot 'local-accountant-login.txt')"
    Write-Host 'Use STOP_ESTATEFLOW.cmd to stop the web server. MySQL is left running safely.'
    if (!$NoBrowser) { Start-Process "$url/login" -WindowStyle Hidden }
} catch {
    Write-Host "EstateFlow: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    if ($ownsMutex) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
