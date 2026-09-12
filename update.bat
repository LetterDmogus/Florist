@echo off
title Bees Fleur Florist - Update App
cd /d "%~dp0"

:: Aktifkan kode warna ANSI di Windows Console
color 0F

:: Karakter ESC untuk pewarnaan ANSI
for /f %%a in ('echo prompt $E ^| cmd') do set "ESC=%%a"

cls
echo.
echo %ESC%[95m  ======================================================%ESC%[0m
echo %ESC%[1;95m             BEES FLEUR FLORIST - AUTO UPDATE           %ESC%[0m
echo %ESC%[95m  ======================================================%ESC%[0m
echo.

:: 1. CEK KONEKSI INTERNET / GIT REPOSITORY
echo %ESC%[96m  [1/4] Memeriksa repositori Git...%ESC%[0m
git status > nul 2>&1
if errorlevel 1 (
    echo.
    echo %ESC%[91m  [X] Error: Folder ini bukan repositori Git atau Git belum terinstall!%ESC%[0m
    echo %ESC%[90m      Pastikan Git sudah terpasang di komputer ini.%ESC%[0m
    echo.
    pause
    exit /b 1
)

:: 2. TARIK UPDATE DARI GITHUB (GIT PULL)
echo %ESC%[96m  [2/4] Mengambil pembaruan terbaru dari GitHub (LetterDmogus/Florist)...%ESC%[0m

:: Pastikan remote origin mengarah ke URL GitHub yang benar
git remote set-url origin https://github.com/LetterDmogus/Florist.git > nul 2>&1
if errorlevel 1 (
    git remote add origin https://github.com/LetterDmogus/Florist.git > nul 2>&1
)

:: Tarik update dari branch aktif atau origin
git pull origin main 2>nul || git pull origin master 2>nul || git pull
if errorlevel 1 (
    echo.
    echo %ESC%[91m  [X] Gagal melakukan git pull!%ESC%[0m
    echo %ESC%[93m      Kemungkinan ada konflik file lokal atau kendala koneksi internet.%ESC%[0m
    echo.
    pause
    exit /b 1
)
echo %ESC%[92m  [V] Kode aplikasi berhasil diperbarui!%ESC%[0m
echo.

:: 3. CEK DAN JALANKAN DATABASE MIGRATION (JIKA ADA PERUBAHAN TABEL)
echo %ESC%[96m  [3/4] Memeriksa & memperbarui struktur database (migrate)...%ESC%[0m
call php artisan migrate --force
if errorlevel 1 (
    echo %ESC%[93m  [!] Catatan: Tidak ada migrasi baru atau database sudah up-to-date.%ESC%[0m
)
echo.

:: 4. MEMBERSIHKAN & MENGOPTIMASI CACHE LARAVEL
echo %ESC%[96m  [4/4] Mengoptimasi cache Laravel (optimize:clear ^& optimize)...%ESC%[0m
call php artisan optimize:clear
call php artisan optimize

:: Hapus file public\hot jika ada agar tidak bentrok dengan mode dev
if exist "public\hot" del /f /q "public\hot" > nul 2>&1

echo.
echo %ESC%[95m  ======================================================%ESC%[0m
echo %ESC%[1;92m   [V] UPDATE SELESAI! APLIKASI SUDAH VERSI TERBARU.   %ESC%[0m
echo %ESC%[95m  ======================================================%ESC%[0m
echo.
echo %ESC%[97m  Silakan buka atau refresh aplikasi seperti biasa.%ESC%[0m
echo.
pause
