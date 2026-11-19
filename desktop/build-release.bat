@echo off
call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvarsall.bat" x64
if %ERRORLEVEL% neq 0 (
    echo ERROR: vcvarsall.bat failed
    exit /b 1
)

cd /d "D:\kilo\classicpos-Lite\desktop"

REM Create backend tarball only if vendor/ is populated (skip if empty to preserve existing tarball)
if exist "backend\vendor\autoload.php" (
    echo Trimming unnecessary files from backend...
    REM Remove dev/CI files not needed at runtime
    if exist "backend\database\factories" rd /s /q "backend\database\factories" 2>nul
    if exist "backend\database\seeders" rd /s /q "backend\database\seeders" 2>nul
    if exist "backend\.env.example" del /q "backend\.env.example" 2>nul
    if exist "backend\.env.testing" del /q "backend\.env.testing" 2>nul
    if exist "backend\phpunit.xml" del /q "backend\phpunit.xml" 2>nul
    if exist "backend\phpunit.xml.dist" del /q "backend\phpunit.xml.dist" 2>nul
    if exist "backend\.gitignore" del /q "backend\.gitignore" 2>nul
    if exist "backend\.gitattributes" del /q "backend\.gitattributes" 2>nul
    if exist "backend\tests" rd /s /q "backend\tests" 2>nul
    REM Remove hot reload marker and mix manifest
    if exist "backend\public\hot" del /q "backend\public\hot" 2>nul
    if exist "backend\public\mix-manifest.json" del /q "backend\public\mix-manifest.json" 2>nul
    REM Remove stale bootstrap caches (route/config caches cause 404s on POST routes)
    if exist "backend\bootstrap\cache\*.php" del /q "backend\bootstrap\cache\*.php" 2>nul

    echo Creating backend tarball...
    tar cf src-tauri\backend-bundle.tar -C backend app bootstrap config database lang public resources routes artisan composer.json composer.lock vendor
    echo Backend tarball created.
) else (
    if exist "src-tauri\backend-bundle.tar" (
        echo backend/vendor is empty — using existing backend-bundle.tar
    ) else (
        echo ERROR: backend/vendor is empty and no existing tarball found.
        echo Run "composer install" in backend/ first, or provide a backend-bundle.tar.
        exit /b 1
    )
)

npx tauri build
