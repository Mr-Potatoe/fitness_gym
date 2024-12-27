@echo off
:: Create a scheduled task to run cleanup_sessions.php every hour

:: Get the current directory
set "SCRIPT_DIR=%~dp0"
set "PHP_PATH=C:\xampp\php\php.exe"
set "SCRIPT_PATH=%SCRIPT_DIR%cleanup_sessions.php"

:: Create the task
schtasks /create /tn "GymSystemCleanup" /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /sc hourly /ru SYSTEM /f

echo Task created successfully. The cleanup script will run every hour.
