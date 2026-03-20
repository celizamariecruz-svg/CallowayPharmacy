@echo off
cd /d "c:\xampp\htdocs\CALLOWAYBACKUP1"
echo Checking PHP syntax for all files...
echo.
echo === api_orders.php ===
c:\xampp\php\php.exe -l api_orders.php
echo.
echo === order_handler.php ===
c:\xampp\php\php.exe -l order_handler.php
echo.
echo === online_order_api.php ===
c:\xampp\php\php.exe -l online_order_api.php
echo.
echo === pos_api.php ===
c:\xampp\php\php.exe -l pos_api.php
echo.
echo === order_status.php ===
c:\xampp\php\php.exe -l order_status.php
echo.
echo === loyalty_qr.php ===
c:\xampp\php\php.exe -l loyalty_qr.php
echo.
echo === pos.php ===
c:\xampp\php\php.exe -l pos.php
echo.
echo Done!
