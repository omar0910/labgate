@echo off
REM ============================================================================
REM  Prueba segura: el equipo NUNCA se apaga con este archivo.
REM
REM  USO: doble clic. Deja de tocar el equipo 2 minutos para ver el aviso.
REM       Se detiene cerrando esta ventana o con Ctrl+C.
REM ============================================================================

title Prueba - Centro de Computo LabGate

cd /d "%~dp0"

echo.
echo   ============================================================
echo    MODO PRUEBA - el equipo NO se apagara
echo   ============================================================
echo.
echo   Se abrira el navegador y quedara vigilando.
echo   Deja de mover el raton durante 2 minutos para ver el aviso.
echo.
echo   Para detenerlo: cierra esta ventana.
echo.

if not exist "%~dp0Equipo-CentroComputo.ps1" (
    echo   ERROR: no se encontro Equipo-CentroComputo.ps1 junto a este archivo.
    echo.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Equipo-CentroComputo.ps1" -Probar -MinutosInactividad 2 -SegundosAviso 20

pause
