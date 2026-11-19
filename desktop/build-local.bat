@echo off
call "D:\Microsoft Visual Studio\Product\VC\Auxiliary\Build\vcvars64.bat" >nul 2>&1
echo Environment loaded
cd /d "D:\kilo\classicpos-Lite\desktop"
echo Building...
npx tauri build
echo Build exit code: %ERRORLEVEL%