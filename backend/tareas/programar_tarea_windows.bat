@echo off
REM ==================================================================
REM  Registra en el Programador de tareas de Windows la tarea que envía
REM  los recordatorios por correo cada 5 minutos (CU18).
REM  Uso: doble clic. Para quitarla: schtasks /delete /tn "ClinicaVidaSalud_Notificaciones" /f
REM  Solo funciona mientras esta PC está encendida; en la nube se usa un cron.
REM ==================================================================
chcp 65001 >nul
setlocal
cd /d "%~dp0"

set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
set "SCRIPT=%~dp0enviar_notificaciones.php"

set "URL="
set /p URL=Dirección del sistema (Enter = http://localhost/clinica-vida-salud):
if "%URL%"=="" set "URL=http://localhost/clinica-vida-salud"

schtasks /create /tn "ClinicaVidaSalud_Notificaciones" /sc minute /mo 5 /f ^
  /tr "\"%PHP%\" \"%SCRIPT%\" --url=%URL%" || goto error

echo.
echo Listo: la tarea se ejecuta cada 5 minutos. Revise el resultado en la pantalla Notificaciones del sistema.
pause
exit /b 0
:error
echo.
echo No se pudo crear la tarea. Ejecute este archivo como administrador.
pause
exit /b 1
