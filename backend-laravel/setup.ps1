# SmileSync v3 setup — run from backend-laravel:  powershell -ExecutionPolicy Bypass -File .\setup.ps1
$ErrorActionPreference = 'Stop'
$routes = 'routes\api_v1.php'
$t = Get-Content $routes -Raw

# 1. session log on login and logout
$mw = "->middleware(\App\Http\Middleware\SessionLogger::class)"
if ($t -notmatch 'SessionLogger') {
  $t = $t.Replace("Route::post('login', [AuthController::class, 'login']);", "Route::post('login', [AuthController::class, 'login'])$mw;")
  $t = $t.Replace("Route::post('logout', [AuthController::class, 'logout']);", "Route::post('logout', [AuthController::class, 'logout'])$mw;")
}
# 2. public routes (patient register)
if ($t -notmatch 'api_v1_public') {
  $marker = "Route::middleware('auth:api')->group(function () {"
  $t = $t.Replace($marker, "require __DIR__.'/api_v1_public.php';`r`n`r`n$marker")
}
# 3. extra routes (already added by hand? then skip)
if ($t -notmatch 'api_v1_extra') {
  $t = $t.Replace("Route::middleware('auth:api')->group(function () {", "Route::middleware('auth:api')->group(function () {`r`n    require __DIR__.'/api_v1_extra.php';")
}
Set-Content $routes $t -NoNewline
Write-Host 'routes/api_v1.php updated'

# 4. .env: JWT 35 min, debug off
$env = Get-Content .env -Raw
if ($env -match 'JWT_TTL=') { $env = $env -replace 'JWT_TTL=\d+', 'JWT_TTL=35' } else { $env += "`r`nJWT_TTL=35" }
$env = $env -replace 'APP_DEBUG=true', 'APP_DEBUG=false'
Set-Content .env $env -NoNewline
Write-Host '.env updated (JWT_TTL=35, APP_DEBUG=false)'

# 5. schedule: reminders every hour
$console = 'routes\console.php'
$c = Get-Content $console -Raw
if ($c -notmatch 'smilesync:reminders') {
  if ($c -notmatch 'use Illuminate\\Support\\Facades\\Schedule;') { $c = $c -replace '<\?php', "<?php`r`n`r`nuse Illuminate\Support\Facades\Schedule;" }
  $c += "`r`nSchedule::command('smilesync:reminders')->hourly();`r`n"
  Set-Content $console $c -NoNewline
}
Write-Host 'schedule added'

php artisan optimize:clear
php artisan migrate --force
php artisan route:list --path=api/v1 | Select-String 'register-patient|app-settings|stats|chat'
