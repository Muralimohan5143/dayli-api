$phpPath = "C:\app\php-8.4.8\php.exe"
$projectPath = "C:\Users\mandl\work\dayli-api"
$logPath = "$projectPath\storage\logs\windows-schedule-run.log"

Set-Location $projectPath

$ts = Get-Date -Format s
"---- $ts schedule:run START ----" | Out-File -FilePath $logPath -Append

& $phpPath artisan schedule:run >> $logPath 2>&1

$code = $LASTEXITCODE
"ExitCode: $code" | Out-File -FilePath $logPath -Append

$te = Get-Date -Format s
"---- $te schedule:run END ----`n" | Out-File -FilePath $logPath -Append

exit $code