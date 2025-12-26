@echo off
REM Simple Windows migration: dump old DB and import into new DB
setlocal
set /p MYSQL_USER=MySQL user (default root):
if "%MYSQL_USER%"=="" set MYSQL_USER=root
set /p MYSQL_DB_OLD=Old DB name (default blood):
if "%MYSQL_DB_OLD%"=="" set MYSQL_DB_OLD=blood
set /p MYSQL_DB_NEW=New DB name (default blood):
if "%MYSQL_DB_NEW%"=="" set MYSQL_DB_NEW=blood
set /p MYSQL_PASS=MySQL password (will be used directly in commands):

set DUMPFILE=%MYSQL_DB_OLD%_dump.sql

echo Dumping %MYSQL_DB_OLD% to %DUMPFILE%...
mysqldump -u %MYSQL_USER% -p%MYSQL_PASS% %MYSQL_DB_OLD% > "%DUMPFILE%"
if errorlevel 1 (
  echo Dump failed. Check credentials and that mysqldump is on PATH.
  pause
  exit /b 1
)

echo Creating database %MYSQL_DB_NEW%...
mysql -u %MYSQL_USER% -p%MYSQL_PASS% -e "CREATE DATABASE IF NOT EXISTS `%MYSQL_DB_NEW%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo Importing dump into %MYSQL_DB_NEW%...
mysql -u %MYSQL_USER% -p%MYSQL_PASS% %MYSQL_DB_NEW% < "%DUMPFILE%"
if errorlevel 1 (
  echo Import failed.
  pause
  exit /b 1
)

echo Migration complete. You can remove the dump file when satisfied: %DUMPFILE%
pause
