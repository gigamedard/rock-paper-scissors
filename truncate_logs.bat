@echo off
:loop
if exist "D:\dev\PHP\rock-paper-scissors\storage\logs\auth_debug.log" (
    powershell -Command "$content = Get-Content 'D:\dev\PHP\rock-paper-scissors\storage\logs\auth_debug.log'; if ($content.Count -gt 20) { $content | Select-Object -Last 20 | Set-Content 'D:\dev\PHP\rock-paper-scissors\storage\logs\auth_debug.log' }"
)
if exist "D:\dev\PHP\rock-paper-scissors\storage\logs\batch_polling.log" (
    powershell -Command "$content = Get-Content 'D:\dev\PHP\rock-paper-scissors\storage\logs\batch_polling.log'; if ($content.Count -gt 50) { $content | Select-Object -Last 50 | Set-Content 'D:\dev\PHP\rock-paper-scissors\storage\logs\batch_polling.log' }"
)
timeout /t 5 /nobreak > nul
goto loop
