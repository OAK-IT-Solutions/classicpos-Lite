@echo off
set VCINSTALLDIR=D:\Microsoft Visual Studio\Product\VC
set VCToolsVersion=14.44.35207
set WindowsSdkDir=C:\Program Files (x86)\Windows Kits\10
set WindowsSDKVersion=10.0.26100.0
set INCLUDE=C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\ucrt;C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\um;C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\shared;D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\include
set LIB=C:\Program Files (x86)\Windows Kits\10\lib\10.0.26100.0\ucrt\x64;C:\Program Files (x86)\Windows Kits\10\lib\10.0.26100.0\um\x64;D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\lib\x64
set CC=D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\bin\HostX64\x64\cl.exe
set CXX=D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\bin\HostX64\x64\cl.exe
cd /d "D:\kilo\classicpos-Lite\desktop"
echo Building with env vars set...
echo INCLUDE=%INCLUDE%
echo LIB=%LIB%
npx tauri build