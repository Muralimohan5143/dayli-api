$phpPath = "C:\app\php-8.4.8\php.exe"
$projectPath = "C:\Users\mandl\work\dayli-api"
$logPath = "$projectPath\storage\logs\windows-schedule-run.log"

Set-Location $projectPath

$ts = (Get-Date -Format s)
"---- $ts schedule:run START ----" | Out-File -FilePath $logPath -Append

try {
    & $phpPath artisan schedule:run -vvv 2>&1 | Out-File -FilePath $logPath -Append
    $code = $LASTEXITCODE
    "ExitCode: $code" | Out-File -FilePath $logPath -Append
}
catch {
    "EXCEPTION: $($_.Exception.Message)" | Out-File -FilePath $logPath -Append
    "EXCEPTION FULL: $($_ | Out-String)" | Out-File -FilePath $logPath -Append
}
finally {
    $te = (Get-Date -Format s)
    "---- $te schedule:run END ----`n" | Out-File -FilePath $logPath -Append
}

exit 0