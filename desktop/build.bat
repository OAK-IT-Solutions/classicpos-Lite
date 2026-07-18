@echo off
REM ClassicPOS Desktop — Build Script
REM Builds static PHP binary and prepares the desktop project

echo ========================================
echo  ClassicPOS Desktop Build
echo ========================================

set DESKTOP_DIR=%~dp0
set BACKEND_DIR=%DESKTOP_DIR%..\backend\
set BUILD_DIR=%DESKTOP_DIR%buildoutput

REM Step 1: Build static PHP binary using Docker
echo.
echo [1/5] Building static PHP binary via Docker...
echo This will take 30-60 minutes on first run (compiling PHP from source).
echo Subsequent builds use Docker cache and are much faster.

docker build -t classicpos-php-builder -f "%DESKTOP_DIR%Dockerfile.php" "%DESKTOP_DIR%" --progress=plain

if %ERRORLEVEL% neq 0 (
    echo ERROR: PHP build failed!
    exit /b 1
)

REM Step 2: Extract the binary
echo.
echo [2/5] Extracting PHP binary...

if not exist "%BUILD_DIR%\binaries" mkdir "%BUILD_DIR%\binaries"

docker create --name classicpos-php-extract classicpos-php-builder
docker cp classicpos-php-extract:/php "%BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe"
docker rm classicpos-php-extract

echo PHP binary extracted to: %BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe

REM Step 3: Verify the binary
echo.
echo [3/5] Verifying PHP binary...
"%BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe" -v
"%BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe" -m

REM Step 4: Copy Laravel backend files
echo.
echo [4/5] Copying Laravel backend...

if not exist "%BUILD_DIR%\app" mkdir "%BUILD_DIR%\app"
xcopy /E /I /Y /Q "%BACKEND_DIR%app" "%BUILD_DIR%\app\app"
xcopy /E /I /Y /Q "%BACKEND_DIR%bootstrap" "%BUILD_DIR%\app\bootstrap"
xcopy /E /I /Y /Q "%BACKEND_DIR%config" "%BUILD_DIR%\app\config"
xcopy /E /I /Y /Q "%BACKEND_DIR%database" "%BUILD_DIR%\app\database"
xcopy /E /I /Y /Q "%BACKEND_DIR%public" "%BUILD_DIR%\app\public"
xcopy /E /I /Y /Q "%BACKEND_DIR%resources" "%BUILD_DIR%\app\resources"
xcopy /E /I /Y /Q "%BACKEND_DIR%routes" "%BUILD_DIR%\app\routes"
xcopy /E /I /Y /Q "%BACKEND_DIR%storage" "%BUILD_DIR%\app\storage"

REM Copy composer dependencies
if not exist "%BUILD_DIR%\app\vendor" (
    echo Installing composer dependencies...
    cd /d "%BACKEND_DIR%"
    composer install --no-dev --optimize-autoloader --working-dir="%BACKEND_DIR%" --output-dir="%BUILD_DIR%\app\vendor"
)

REM Copy .env.offline.example as .env
copy /Y "%BACKEND_DIR%.env.offline.example" "%BUILD_DIR%\app\.env"

REM Generate APP_KEY
cd /d "%BUILD_DIR%\app"
"%BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe" artisan key:generate --force

REM Step 5: Summary
echo.
echo [5/5] Build complete!
echo.
echo Output directory: %BUILD_DIR%
echo PHP binary: %BUILD_DIR%\binaries\php-x86_64-pc-windows-msvc.exe
echo Laravel app: %BUILD_DIR%\app\
echo.
echo Next steps:
echo   1. Copy binaries to desktop/src-tauri/binaries/
echo   2. Run: cd desktop ^& npm install ^& npm run tauri dev
echo.
