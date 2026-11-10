@echo off
setlocal
if exist "%~dp0..\.runtime\php84\php.exe" (
    set "PATH=%~dp0..\.runtime\php84;%PATH%"
    set "TEMP=%~dp0..\.runtime\browser-temp"
    set "TMP=%~dp0..\.runtime\browser-temp"
    if not exist "%~dp0..\.runtime\browser-temp" mkdir "%~dp0..\.runtime\browser-temp"
    "%~dp0..\.runtime\php84\php.exe" %*
) else (
    php %*
)
exit /b %errorlevel%
