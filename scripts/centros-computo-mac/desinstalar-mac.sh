#!/bin/bash
# ==============================================================================
#  Quita el sistema de esta Mac: el arranque automático, los vigilantes en
#  marcha, las pantallas de bloqueo abiertas y el permiso para apagar.
#
#  USO (en la Terminal, dentro de esta carpeta):
#
#      sudo bash desinstalar-mac.sh
#
#  La bitácora se conserva en /Library/Logs/LabGate/centro-computo.log
# ==============================================================================

ETIQUETA="mx.edu.labgate.centrocomputo"
AGENTE="/Library/LaunchAgents/$ETIQUETA.plist"
DESTINO="/Library/Application Support/LabGate"
PERMISO_APAGADO="/etc/sudoers.d/labgate-apagado"

if [ "$(id -u)" -ne 0 ]; then
    echo ""
    echo "  Hace falta permiso de administrador. Escribe:"
    echo ""
    echo "      sudo bash desinstalar-mac.sh"
    echo ""
    exit 1
fi

echo ""
echo "  ============================================================"
echo "   Quitando el sistema de esta Mac"
echo "  ============================================================"
echo ""

# 1. Se detiene el vigilante en cada sesión abierta. Primero se le quita a macOS
#    la orden de relanzarlo; si no, lo volvería a abrir a los 10 segundos.
for cuenta in $(ps -axo uid=,comm= | awk '/loginwindow/ && $1 != 0 {print $1}' | sort -u); do
    launchctl bootout "gui/$cuenta/$ETIQUETA" >/dev/null 2>&1
done

if [ -f "$AGENTE" ]; then
    rm -f "$AGENTE"
    echo "  Arranque automático eliminado."
else
    echo "  No había arranque automático instalado."
fi

pkill -f 'LabGate/equipo-centro-computo.sh' >/dev/null 2>&1

# 2. Las pantallas de bloqueo que hubieran quedado abiertas.
pkill -f -- '--user-data-dir=/Users/[^ ]*/\.labgate/perfil-sistema' >/dev/null 2>&1

# 3. El permiso para apagar y los archivos del programa.
rm -f "$PERMISO_APAGADO"
rm -rf "$DESTINO"

# 4. Lo que el vigilante guarda en la carpeta de cada cuenta: el perfil de Chrome
#    del sistema (con la sesión del último alumno) y la página de espera.
sleep 1
for carpeta in /Users/*/.labgate; do
    [ -d "$carpeta" ] && rm -rf "$carpeta"
done

echo "$(date '+%Y-%m-%d %H:%M:%S')  [INFO]  Desinstalado de esta Mac." >> /Library/Logs/LabGate/centro-computo.log 2>/dev/null

echo "  Vigilantes detenidos y archivos del programa eliminados."
echo ""
echo "  La bitácora se conserva en /Library/Logs/LabGate/centro-computo.log"
echo ""
