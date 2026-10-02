@echo off
REM ============================================================================
REM  SISTEMA DE SERVICIOS INFORMATICOS - LabGate
REM  Instalador CON bloqueo hasta registrarse
REM
REM  Igual que INSTALAR.bat, pero ademas deja la computadora bloqueada con el
REM  sistema en pantalla completa hasta que el alumno registre su asistencia
REM  o su entrada de uso libre en ESTA maquina.
REM
REM  USO: doble clic. Pregunta el laboratorio, el numero de esta computadora y
REM  si los alumnos entran con una cuenta de administrador.
REM ============================================================================

title Instalar CON bloqueo - Centro de Computo LabGate

REM --- Permisos de administrador ---
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo   Pidiendo permisos de administrador...
    echo   Acepta la ventana azul que aparecera.
    echo.
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

cd /d "%~dp0"

echo.
echo   ============================================================
echo    Centro de Computo LabGate - Instalacion CON BLOQUEO
echo   ============================================================
echo.

if not exist "%~dp0Equipo-CentroComputo.ps1" (
    echo   ERROR: no se encontro Equipo-CentroComputo.ps1 junto a este archivo.
    echo.
    pause
    exit /b 1
)

echo   Esta computadora quedara bloqueada hasta que el alumno
echo   registre su asistencia en ella.
echo.
echo   ------------------------------------------------------------
echo    LABORATORIOS
echo   ------------------------------------------------------------
echo    El numero de cada laboratorio aparece en el sistema, en
echo    Centros de Computo, debajo de su nombre.
echo   ------------------------------------------------------------
echo.

REM Cualquier numero entero positivo: los laboratorios nuevos que se den de alta
REM en el sistema tienen el suyo, y la lista de arriba es solo de referencia.
:pedir_lab
set "LAB="
set /p LAB=  Numero de laboratorio:
if "%LAB%"=="" goto pedir_lab
echo %LAB%| findstr /r "^[1-9][0-9]*$" >nul
if errorlevel 1 (
    echo   Valor no valido. Escribe solo el numero, por ejemplo 1.
    goto pedir_lab
)

:pedir_maquina
set "PC="
set /p PC=  Numero de ESTA computadora dentro del laboratorio:
if "%PC%"=="" goto pedir_maquina

REM --- Cuentas de administrador ---
REM Por omision el bloqueo NO se aplica a las cuentas de administrador, para que
REM el personal tecnico pueda entrar libre. Si los alumnos tambien entran con una
REM cuenta de administrador, hay que decirlo o el bloqueo nunca se activaria.
echo.
echo   ------------------------------------------------------------
echo    CUENTAS DE ESTA COMPUTADORA
echo   ------------------------------------------------------------
powershell -NoProfile -Command "$adm = @(Get-LocalGroupMember -SID 'S-1-5-32-544' -ErrorAction SilentlyContinue | ForEach-Object { $_.SID.Value }); Get-LocalUser | Where-Object Enabled | ForEach-Object { '     {0,-24} {1}' -f $_.Name, $(if ($adm -contains $_.SID.Value) { 'ADMINISTRADOR' } else { 'normal' }) }"
echo   ------------------------------------------------------------
echo.
echo   La cuenta con la que entran los ALUMNOS es de ADMINISTRADOR?
echo     N = no, es una cuenta normal  (lo recomendable)
echo     S = si: el bloqueo se aplicara tambien a los administradores
echo.

:pedir_admin
set "ADM="
set /p ADM=  (S/N):
if /i "%ADM%"=="N" (
    set "EXTRA="
    set "TEXTO_ADM=Administradores: SIN bloqueo"
    goto confirmar
)
if /i "%ADM%"=="S" (
    set "EXTRA=-BloquearAdministradores"
    set "TEXTO_ADM=Administradores: CON bloqueo"
    goto confirmar
)
echo   Escribe S o N.
goto pedir_admin

:confirmar
echo.
echo   ------------------------------------------------------------
echo    Se va a configurar como:  Laboratorio %LAB%  -  PC #%PC%
echo                              %TEXTO_ADM%
echo   ------------------------------------------------------------
echo.
set "OK="
set /p OK=  Es correcto? (S/N):
if /i not "%OK%"=="S" (
    echo.
    echo   Cancelado. Vuelve a ejecutarlo cuando quieras.
    echo.
    pause
    exit /b
)

echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Equipo-CentroComputo.ps1" -Instalar -Laboratorio %LAB% -Maquina %PC% %EXTRA%

echo.
echo   ============================================================
echo    Puedes retirar la memoria USB.
echo.
echo    ANOTA que esta computadora quedo como PC #%PC%
echo    del laboratorio %LAB%, para no repetir el numero.
echo.
echo    Cierra sesion y vuelve a entrar para comprobarlo.
echo   ============================================================
echo.
pause
