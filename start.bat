@echo off
echo Starting School App server...
echo Upload limit: 100M
echo.
php -d upload_max_filesize=100M -d post_max_size=100M -d memory_limit=256M -d max_execution_time=300 -S localhost:8000 router.php
pause
