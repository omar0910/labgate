@echo off
REM ============================================================================
REM  Quita el sistema de este equipo: la tarea programada, los vigilantes en
REM  marcha y la pagina de inicio del navegador.
REM
REM  USO: doble clic.
REM ============================================================================

title Desinstalar - Centro de Computo LabGate

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo   Pidiendo permisos de administrador...
    echo.
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

cd /d "%~dp0"

echo.
echo   ============================================================
echo    Quitando el sistema de este equipo
echo   ============================================================
echo.

REM Se usa la copia instalada en el equipo si existe; si no, la de esta carpeta.
set "ORIGEN=%ProgramData%\LabGate\Equipo-CentroComputo.ps1"
if not exist "%ORIGEN%" set "ORIGEN=%~dp0Equipo-CentroComputo.ps1"

if not exist "%ORIGEN%" (
    echo   ERROR: no se encontro el script ni en el equipo ni en esta carpeta.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%ORIGEN%" -Desinstalar

echo.
pause
