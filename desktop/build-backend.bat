@echo off
setlocal enabledelayedexpansion

echo ==========================================
echo  ClassicPOS Desktop - Build Backend Tarball
echo ==========================================
echo.

set "PROJECT_ROOT=%~dp0.."
set "BACKEND_SRC=%PROJECT_ROOT%\backend"
set "BACKEND_TEMP=%PROJECT_ROOT%\desktop\backend-temp"
set "OUTPUT_DIR=%PROJECT_ROOT%\desktop\resources"
set "TARBALL=%OUTPUT_DIR%\backend-bundle.tar"

echo [1/4] Cleaning previous build...
if exist "%BACKEND_TEMP%" rmdir /s /q "%BACKEND_TEMP%"
if exist "%TARBALL%" del /f "%TARBALL%"

echo [2/4] Copying backend to temp directory (robocopy)...
robocopy "%BACKEND_SRC%" "%BACKEND_TEMP%" /E /NFL /NDL /NJH /NJS /NC /NS /NP >nul 2>&1
if %ERRORLEVEL% GEQ 8 (
    echo [ERROR] Robocopy failed with code %ERRORLEVEL%
    exit /b 1
)

echo [3/4] Creating tarball (uncompressed, may take a few minutes)...
cd /d "%BACKEND_TEMP%"
tar -cf "%TARBALL%" *
set "TAR_EXIT=%ERRORLEVEL%"
cd /d "%PROJECT_ROOT%"

echo [4/4] Cleaning temp directory...
rmdir /s /q "%BACKEND_TEMP%"

echo.
if %TAR_EXIT% EQU 0 if exist "%TARBALL%" (
    echo [OK] Backend tarball created successfully
    for %%A in ("%TARBALL%") do set "SIZE=%%~zA"
    set /a "MB=!SIZE! / 1048576"
    echo     Size: !MB! MB
) else (
    echo [ERROR] Failed to create tarball (tar exit code: %TAR_EXIT%)
    exit /b 1
)

echo.
echo Done!
