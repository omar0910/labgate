@echo off
REM ============================================================================
REM  SISTEMA DE SERVICIOS INFORMATICOS - LabGate
REM  Instalador para los equipos de los centros de computo
REM
REM  USO: doble clic en este archivo. Nada mas.
REM
REM  Se encarga solo de:
REM    - pedir permisos de administrador
REM    - encontrar el script aunque la memoria USB cambie de letra
REM    - saltar el bloqueo de scripts de Windows
REM ============================================================================

title Instalar sistema del Centro de Computo - LabGate

REM --- Comprobar si ya se esta ejecutando como administrador ---
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo   Pidiendo permisos de administrador...
    echo   Acepta la ventana azul que aparecera.
    echo.
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

REM --- %~dp0 es la carpeta de ESTE archivo, este donde este la memoria ---
cd /d "%~dp0"

echo.
echo   ============================================================
echo    Centro de Computo LabGate - Instalacion del equipo
echo   ============================================================
echo.
echo   Carpeta: %~dp0
echo.

if not exist "%~dp0Equipo-CentroComputo.ps1" (
    echo   ERROR: no se encontro Equipo-CentroComputo.ps1
    echo   junto a este instalador.
    echo.
    echo   Asegurate de copiar los dos archivos juntos a la memoria.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Equipo-CentroComputo.ps1" -Instalar

echo.
echo   ============================================================
echo    Puedes retirar la memoria USB.
echo    Cierra sesion y vuelve a entrar para comprobarlo.
echo   ============================================================
echo.
pause
