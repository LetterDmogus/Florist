@echo off
title Bees Fleur Florist App
cd /d "%~dp0"

:: Aktifkan kode warna ANSI di Windows Console
color 0F

:: Karakter ESC untuk pewarnaan ANSI
for /f %%a in ('echo prompt $E ^| cmd') do set "ESC=%%a"

cls
echo.
echo %ESC%[95m  ======================================================%ESC%[0m
echo %ESC%[1;95m                  BEES FLEUR FLORIST POS                %ESC%[0m
echo %ESC%[95m  ======================================================%ESC%[0m
echo.

:: 1. CHECKER VENDOR
if exist "vendor\autoload.php" goto check_build
echo %ESC%[93m  [!] Folder vendor belum ditemukan.%ESC%[0m
echo %ESC%[97m      Sedang menginstall library PHP (composer install)...%ESC%[0m
call composer install --no-dev --optimize-autoloader
if errorlevel 1 (
    echo.
    echo %ESC%[91m  [X] Gagal menjalankan composer install!%ESC%[0m
    pause
    exit /b 1
)
echo %ESC%[92m  [V] Library PHP berhasil diinstall!%ESC%[0m
echo.

:check_build
:: 2. CHECKER FRONTEND BUILD
if exist "public\build\manifest.json" goto check_env
echo %ESC%[93m  [!] Aset frontend produksi belum ditemukan.%ESC%[0m
echo %ESC%[97m      Sedang melakukan build frontend...%ESC%[0m
if not exist "node_modules" (
    echo %ESC%[97m      Menginstall node_modules...%ESC%[0m
    call npm install
)
call npm run build
if errorlevel 1 (
    echo.
    echo %ESC%[91m  [X] Gagal melakukan build frontend!%ESC%[0m
    pause
    exit /b 1
)
echo %ESC%[92m  [V] Frontend berhasil di-build!%ESC%[0m
echo.

:check_env
:: 3. CHECKER .ENV
if exist ".env" goto check_storage
echo %ESC%[93m  [!] File .env belum ada, membuat dari .env.example...%ESC%[0m
copy .env.example .env > nul
call php artisan key:generate

:check_storage
:: 4. CHECKER STORAGE LINK
if not exist "public\storage" (
    call php artisan storage:link > nul 2>&1
)

:: Deteksi IP lokal perangkat
set "LOCAL_IP=127.0.0.1"
for /f "tokens=4" %%a in ('route print ^| findstr 0.0.0.0.*0.0.0.0') do (
    if not "%%a"=="" set "LOCAL_IP=%%a"
)

echo %ESC%[96m [1] Akses dari Komputer ini (Lokal):%ESC%[0m
echo %ESC%[97mhttp://127.0.0.1:8000%ESC%[0m atau %ESC%[97mhttp://localhost:8000%ESC%[0m
echo.
echo %ESC%[93m [2] Akses dari HP / Perangkat Lain (Kasir / Gudang):%ESC%[0m
echo %ESC%[1;92mhttp://%LOCAL_IP%:8000%ESC%[0m
echo.
echo %ESC%[90m ------------------------------------------------------%ESC%[0m
echo %ESC%[93m * CATATAN PENTING KONEKSI HP/TABLET:%ESC%[0m
echo %ESC%[97mPastikan HP / Tablet kasir terhubung ke Wi-Fi / Hotspot%ESC%[0m
echo %ESC%[1;95myang SAMA%ESC%[0m%ESC%[97m dengan komputer ini.%ESC%[0m
echo %ESC%[90m ------------------------------------------------------%ESC%[0m
echo.

:: Menjalankan server Laravel di background listening ke semua interface
start /b php artisan serve --host=0.0.0.0 --port=8000 > nul 2>&1

:: Menunggu 2 detik agar server siap
ping 127.0.0.1 -n 3 > nul

:: Membuka aplikasi langsung di browser default
start http://127.0.0.1:8000

echo %ESC%[1;92m [V] STATUS: Server AKTIF dan berjalan lancar!%ESC%[0m
echo.
echo %ESC%[91m [!] JANGAN TUTUP JENDELA INI SELAMA MENGGUNAKAN APLIKASI.%ESC%[0m
echo %ESC%[90m Tekan tombol silang [X] pada jendela ini untuk keluar.%ESC%[0m
echo %ESC%[90m ------------------------------------------------------%ESC%[0m
echo.

:: Menjaga jendela tetap terbuka
pause > nul
