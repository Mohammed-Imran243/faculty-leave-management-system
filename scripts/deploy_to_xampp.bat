@echo off
echo Check 1: Starting Deployment...
set SOURCE=%~dp0
set DEST=C:\xampp\htdocs\faculty-system\

echo Source: %SOURCE%
echo Destination: %DEST%

xcopy /E /I /Y "%SOURCE%*" "%DEST%"

if %ERRORLEVEL% EQU 0 (
    echo Deployment Successful.
) else (
    echo Deployment Failed with error code %ERRORLEVEL%.
)
