@echo off
set INCLUDE=D:\kilo\classicpos-Lite\desktop\sdk-compat\ucrt;D:\kilo\classicpos-Lite\desktop\sdk-compat\um;D:\kilo\classicpos-Lite\desktop\sdk-compat\shared;C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\ucrt;C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\um;C:\Program Files (x86)\Windows Kits\10\include\10.0.26100.0\shared;D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\include
set LIB=C:\Program Files (x86)\Windows Kits\10\lib\10.0.26100.0\ucrt\x64;C:\Program Files (x86)\Windows Kits\10\lib\10.0.26100.0\um\x64;D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\lib\x64
set CC=D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\bin\HostX64\x64\cl.exe
set CXX=D:\Microsoft Visual Studio\Product\VC\Tools\MSVC\14.44.35207\bin\HostX64\x64\cl.exe
cd /d "D:\kilo\classicpos-Lite\desktop"
npx tauri build