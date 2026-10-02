#!/bin/bash
# ==============================================================================
#  Prueba segura: el equipo NUNCA se apaga con este archivo.
#
#  USO (en la Terminal, dentro de esta carpeta, SIN sudo):
#
#      bash probar-mac.sh           el aviso de apagado a los 2 minutos sin uso
#      bash probar-mac.sh 1 7       además, el bloqueo como PC #7 del laboratorio 1
#
#  Se detiene volviendo a la Terminal (Cmd+Tab) y pulsando Control+C.
# ==============================================================================

CARPETA="$(cd "$(dirname "$0")" && pwd)"

if [ "$(id -u)" -eq 0 ]; then
    echo ""
    echo "  La prueba se ejecuta SIN sudo:  bash probar-mac.sh"
    echo ""
    exit 1
fi

if [ ! -f "$CARPETA/equipo-centro-computo.sh" ]; then
    echo ""
    echo "  ERROR: no se encontró equipo-centro-computo.sh junto a este archivo."
    echo ""
    exit 1
fi

# En la prueba el bloqueo se aplica aunque la cuenta sea de administrador: es la
# que suele usar quien prueba.
EXTRA=""
if [ -n "$1" ] && [ -n "$2" ]; then
    EXTRA="--laboratorio $1 --maquina $2 --bloquear-administradores"
fi

bash "$CARPETA/equipo-centro-computo.sh" --probar --minutos 2 --aviso 20 $EXTRA
