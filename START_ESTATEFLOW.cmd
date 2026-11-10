@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\local-app.ps1" %*
if errorlevel 1 echo EstateFlow could not start. See the message above.
pause
