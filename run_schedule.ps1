$phpPath = "C:\app\php-8.4.8\php.exe"
$projectPath = "C:\Users\mandl\work\dayli-api"

Set-Location $projectPath
& $phpPath artisan schedule:run