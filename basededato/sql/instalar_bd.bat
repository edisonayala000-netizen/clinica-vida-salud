@echo off
REM ==================================================================
REM  Crea (o vuelve a crear) la base clinica_vida_salud en PostgreSQL.
REM  Uso: doble clic, o desde la terminal:  instalar_bd.bat
REM ==================================================================
chcp 65001 >nul
setlocal
cd /d "%~dp0"

REM Busca psql.exe en el PATH o en las carpetas habituales de PostgreSQL.
set "PSQL=psql"
where psql >nul 2>nul
if errorlevel 1 (
  for %%v in (18 17 16 15 14) do (
    if exist "C:\Program Files\PostgreSQL\%%v\bin\psql.exe" set "PSQL=C:\Program Files\PostgreSQL\%%v\bin\psql.exe"
  )
)
echo Usando: %PSQL%
set /p PGPASSWORD=Contraseña del usuario postgres: 

"%PSQL%" -U postgres -h localhost -c "DROP DATABASE IF EXISTS clinica_vida_salud" || goto error
"%PSQL%" -U postgres -h localhost -c "CREATE DATABASE clinica_vida_salud ENCODING 'UTF8' TEMPLATE template0" || goto error
"%PSQL%" -U postgres -h localhost -d clinica_vida_salud -f instalar.sql || goto error
echo.
echo Listo. Abra http://localhost/clinica-vida-salud  (usuario admin / Clinica2026*)
pause
exit /b 0
:error
echo.
echo Ocurrio un error. Revise que PostgreSQL este encendido y la contraseña sea correcta.
pause
exit /b 1
