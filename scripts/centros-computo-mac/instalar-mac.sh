#!/bin/bash
# ==============================================================================
#  SISTEMA DE SERVICIOS INFORMÁTICOS - LabGate
#  Instalador para las computadoras Mac de los centros de cómputo
# ==============================================================================
#
#  USO: en la Terminal, dentro de esta carpeta, escribir
#
#      sudo bash instalar-mac.sh
#
#  Pide la contraseña de administrador de la Mac (no se ve mientras se escribe),
#  pregunta el laboratorio y el número de esta computadora, y deja todo listo.
#  Empieza a funcionar en el siguiente inicio de sesión.
#
#  Opcional, para cambiar los tiempos (los mismos valores que en Windows):
#
#      sudo bash instalar-mac.sh --minutos 30 --aviso 60
#      sudo bash instalar-mac.sh --no-apagar-entre 07:00-14:00
#      sudo bash instalar-mac.sh --sin-apagado
#
#  Volver a instalarlo sobre una instalación anterior es seguro: la reemplaza.
# ==============================================================================

ORIGEN="$(cd "$(dirname "$0")" && pwd)"
DESTINO="/Library/Application Support/LabGate"
ETIQUETA="mx.edu.labgate.centrocomputo"
AGENTE="/Library/LaunchAgents/$ETIQUETA.plist"
PERMISO_APAGADO="/etc/sudoers.d/labgate-apagado"
CARPETA_BITACORA="/Library/Logs/LabGate"
ARCHIVO_BITACORA="$CARPETA_BITACORA/centro-computo.log"
ARCHIVOS="equipo-centro-computo.sh aviso.applescript"

URL="http://labgate.example"
MINUTOS=30
AVISO=60
NO_APAGAR_ENTRE=""
SIN_APAGADO=0

rojo()     { printf '\033[31m%s\033[0m\n' "$1"; }
verde()    { printf '\033[32m%s\033[0m\n' "$1"; }
amarillo() { printf '\033[33m%s\033[0m\n' "$1"; }

es_numero() {
    case "$1" in
        '' | *[!0-9]*) return 1 ;;
    esac
    return 0
}

# --- Opciones -------------------------------------------------------------------
while [ $# -gt 0 ]; do
    case "$1" in
        --url)             URL="${2:-}"; shift ;;
        --minutos)         MINUTOS="${2:-}"; shift ;;
        --aviso)           AVISO="${2:-}"; shift ;;
        --no-apagar-entre) NO_APAGAR_ENTRE="${2:-}"; shift ;;
        --sin-apagado)     SIN_APAGADO=1 ;;
        *)
            rojo "  Opción desconocida: $1"
            exit 1
            ;;
    esac
    shift
done

if ! echo "$URL" | grep -Eq '^https?://'; then
    rojo "  La dirección debe empezar con http:// o https://"
    exit 1
fi
URL="${URL%/}"

if ! es_numero "$MINUTOS" || [ "$MINUTOS" -lt 2 ] || [ "$MINUTOS" -gt 480 ]; then
    rojo "  --minutos debe ser un número entre 2 y 480."
    exit 1
fi

if ! es_numero "$AVISO" || [ "$AVISO" -lt 15 ] || [ "$AVISO" -gt 600 ]; then
    rojo "  --aviso debe ser un número entre 15 y 600."
    exit 1
fi

if [ -n "$NO_APAGAR_ENTRE" ] && ! echo "$NO_APAGAR_ENTRE" | grep -Eq '^[0-9]{1,2}:[0-9]{2}-[0-9]{1,2}:[0-9]{2}$'; then
    rojo "  --no-apagar-entre debe tener la forma 07:00-14:00"
    exit 1
fi

# --- Comprobaciones -------------------------------------------------------------
if [ "$(uname)" != "Darwin" ]; then
    rojo "  Este instalador es sólo para Mac."
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo ""
    rojo "  Hace falta instalar con permisos de administrador. Escribe:"
    echo ""
    echo "      sudo bash instalar-mac.sh"
    echo ""
    echo "  y luego la contraseña de administrador de esta Mac."
    echo "  (Mientras la escribes no se ve nada: es normal. Al terminar, pulsa Enter.)"
    echo ""
    exit 1
fi

for archivo in $ARCHIVOS; do
    if [ ! -f "$ORIGEN/$archivo" ]; then
        rojo "  ERROR: no se encontró $archivo junto a este instalador."
        echo "  Copia la carpeta completa a la memoria, no sólo este archivo."
        exit 1
    fi
done

clear
echo ""
echo "  ============================================================"
echo "   Centro de Cómputo LabGate - Instalación en Mac"
echo "  ============================================================"
echo ""

# Google Chrome hace falta para la pantalla de bloqueo.
CHROME=""
for app in "/Applications/Google Chrome.app"; do
    [ -x "$app/Contents/MacOS/Google Chrome" ] && CHROME="$app"
done
if [ -z "$CHROME" ]; then
    CHROME="$(mdfind "kMDItemCFBundleIdentifier == 'com.google.Chrome'" 2>/dev/null | head -n 1)"
fi

if [ -n "$CHROME" ]; then
    verde "  Google Chrome: instalado."
else
    amarillo "  Google Chrome NO está instalado en esta Mac."
    echo "  Sin Chrome no se puede bloquear el equipo: sólo se abriría el sistema"
    echo "  en Safari y se apagaría por inactividad."
    echo ""
    echo "  Lo recomendable es cancelar, instalar Chrome (google.com/chrome) y"
    echo "  volver a ejecutar este instalador."
    echo ""
    read -r -p "  ¿Continuar de todos modos? (S/N): " RESPUESTA
    case "$RESPUESTA" in
        [sS]) ;;
        *)
            echo ""
            echo "  Cancelado. Instala Chrome y vuelve a ejecutarlo."
            echo ""
            exit 0
            ;;
    esac
fi
echo ""

# --- Bloqueo --------------------------------------------------------------------
LAB=0
PC=0
BLOQUEAR_ADMIN=0

echo "  ¿Esta computadora debe quedar BLOQUEADA hasta que el alumno"
echo "  registre su asistencia en ella?"
echo "    S = sí, con bloqueo (lo normal en los laboratorios)"
echo "    N = no, sólo abrir el sistema y apagar por inactividad"
echo ""
while true; do
    read -r -p "  (S/N): " RESPUESTA
    case "$RESPUESTA" in
        [sS]) CON_BLOQUEO=1; break ;;
        [nN]) CON_BLOQUEO=0; break ;;
        *) echo "  Escribe S o N." ;;
    esac
done

if [ "$CON_BLOQUEO" = 1 ]; then
    echo ""
    echo "  ------------------------------------------------------------"
    echo "   LABORATORIOS"
    echo "  ------------------------------------------------------------"
    echo "   El número de cada laboratorio aparece en el sistema, en"
    echo "   Centros de Cómputo, debajo de su nombre."
    echo "  ------------------------------------------------------------"
    echo ""
    # Cualquier número entero positivo: los laboratorios nuevos que se den de
    # alta en el sistema tienen el suyo, y la lista de arriba es de referencia.
    while true; do
        read -r -p "  Número de laboratorio: " LAB
        if es_numero "$LAB" && [ "$((10#$LAB))" -gt 0 ]; then
            LAB=$((10#$LAB))
            break
        fi
        echo "  Valor no válido. Escribe sólo el número, por ejemplo 1."
    done

    echo ""
    echo "  El número de ESTA computadora dentro del laboratorio. No puede"
    echo "  repetirse con ninguna otra del mismo laboratorio, tampoco con las"
    echo "  computadoras Windows."
    echo ""
    while true; do
        read -r -p "  Número de esta computadora: " PC
        if es_numero "$PC" && [ "$((10#$PC))" -gt 0 ]; then
            PC=$((10#$PC))
            break
        fi
        echo "  Escribe sólo el número (por ejemplo 7)."
    done

    # --- Cuentas de administrador ---
    # Por omisión el bloqueo NO se aplica a las cuentas de administrador, para que
    # el personal técnico pueda entrar libre. Si los alumnos también entran con
    # una cuenta de administrador, hay que decirlo o el bloqueo nunca se activaría.
    echo ""
    echo "  ------------------------------------------------------------"
    echo "   CUENTAS DE ESTA MAC"
    echo "  ------------------------------------------------------------"
    dscl . list /Users UniqueID 2>/dev/null | awk '$2 >= 500 {print $1}' | while read -r cuenta; do
        if dseditgroup -o checkmember -m "$cuenta" admin >/dev/null 2>&1; then
            printf '     %-24s %s\n' "$cuenta" "ADMINISTRADOR"
        else
            printf '     %-24s %s\n' "$cuenta" "normal"
        fi
    done
    echo "  ------------------------------------------------------------"
    echo ""
    echo "  ¿La cuenta con la que entran los ALUMNOS es de ADMINISTRADOR?"
    echo "    N = no, es una cuenta normal  (lo recomendable)"
    echo "    S = sí: el bloqueo se aplicará también a los administradores"
    echo ""
    while true; do
        read -r -p "  (S/N): " RESPUESTA
        case "$RESPUESTA" in
            [sS]) BLOQUEAR_ADMIN=1; break ;;
            [nN]) BLOQUEAR_ADMIN=0; break ;;
            *) echo "  Escribe S o N." ;;
        esac
    done
fi

# --- Confirmación ---------------------------------------------------------------
echo ""
echo "  ------------------------------------------------------------"
if [ "$CON_BLOQUEO" = 1 ]; then
    echo "   Se va a configurar como:  Laboratorio $LAB  -  PC #$PC"
    if [ "$BLOQUEAR_ADMIN" = 1 ]; then
        echo "                             Administradores: CON bloqueo"
    else
        echo "                             Administradores: SIN bloqueo"
    fi
else
    echo "   Se va a configurar SIN bloqueo."
fi
if [ "$SIN_APAGADO" = 1 ]; then
    echo "   Apagado automático:       desactivado"
else
    echo "   Se apaga tras:            $MINUTOS minutos sin uso (aviso de $AVISO s)"
    [ -n "$NO_APAGAR_ENTRE" ] && echo "   Nunca se apaga entre:     $NO_APAGAR_ENTRE"
fi
echo "   Sistema:                  $URL"
echo "  ------------------------------------------------------------"
echo ""
read -r -p "  ¿Es correcto? (S/N): " RESPUESTA
case "$RESPUESTA" in
    [sS]) ;;
    *)
        echo ""
        echo "  Cancelado. Vuelve a ejecutarlo cuando quieras."
        echo ""
        exit 0
        ;;
esac
echo ""

# --- Instalación ----------------------------------------------------------------

# 1. Los archivos se copian a una carpeta fija de la Mac, para que todo siga
#    funcionando al retirar la memoria USB. Se les quitan los saltos de línea de
#    Windows por si la memoria se preparó desde una PC: con ellos no funcionan.
mkdir -p "$DESTINO"
for archivo in $ARCHIVOS; do
    tr -d '\r' < "$ORIGEN/$archivo" > "$DESTINO/$archivo"
done
# pantalla.js era de una versión anterior; ya no se usa.
rm -f "$DESTINO/pantalla.js"
chown -R root:wheel "$DESTINO"
chmod 755 "$DESTINO" "$DESTINO/equipo-centro-computo.sh"
chmod 644 "$DESTINO/aviso.applescript"
xattr -cr "$DESTINO" 2>/dev/null

# 2. La configuración de este equipo.
cat > "$DESTINO/config" <<CONFIG
# Configuración del Centro de Cómputo LabGate en esta Mac.
# La escribe instalar-mac.sh; para cambiarla, vuelve a ejecutar el instalador.
URL="$URL"
LABORATORIO=$LAB
MAQUINA=$PC
BLOQUEAR_ADMINISTRADORES=$BLOQUEAR_ADMIN
MINUTOS_INACTIVIDAD=$MINUTOS
SEGUNDOS_AVISO=$AVISO
NO_APAGAR_ENTRE="$NO_APAGAR_ENTRE"
SIN_APAGADO=$SIN_APAGADO
CONFIG
chown root:wheel "$DESTINO/config"
chmod 644 "$DESTINO/config"

# 3. La bitácora la escriben las cuentas de los alumnos, así que debe poder
#    escribirla cualquiera.
mkdir -p "$CARPETA_BITACORA"
chmod 755 "$CARPETA_BITACORA"
touch "$ARCHIVO_BITACORA"
chmod 666 "$ARCHIVO_BITACORA"

# 4. El arranque en cada inicio de sesión (el equivalente de la tarea programada
#    de Windows). KeepAlive: si alguien cierra el vigilante, macOS lo relanza.
#    AbandonProcessGroup: al relanzarlo no se cierra el navegador del alumno.
cat > "$AGENTE" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$ETIQUETA</string>
    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>$DESTINO/equipo-centro-computo.sh</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <dict>
        <key>SuccessfulExit</key>
        <false/>
    </dict>
    <key>ThrottleInterval</key>
    <integer>10</integer>
    <key>LimitLoadToSessionType</key>
    <string>Aqua</string>
    <key>ProcessType</key>
    <string>Interactive</string>
    <key>AbandonProcessGroup</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/dev/null</string>
    <key>StandardErrorPath</key>
    <string>/dev/null</string>
</dict>
</plist>
PLIST
chown root:wheel "$AGENTE"
chmod 644 "$AGENTE"

if ! plutil -lint "$AGENTE" >/dev/null 2>&1; then
    rojo "  ERROR: el archivo de arranque quedó mal escrito ($AGENTE)."
    rm -f "$AGENTE"
    exit 1
fi

# 5. Permiso para apagar. Apagar necesita ser administrador; aquí se permite a
#    las cuentas de esta Mac únicamente eso, el comando exacto de apagado.
APAGADO_OK=1
if [ "$SIN_APAGADO" = 1 ]; then
    rm -f "$PERMISO_APAGADO"
else
    TEMPORAL="$(mktemp /tmp/labgate-apagado.XXXXXX)"
    {
        echo "# Centro de Computo LabGate: permite apagar el equipo por inactividad."
        echo "ALL ALL=(root) NOPASSWD: /sbin/shutdown -h now"
    } > "$TEMPORAL"
    chown root:wheel "$TEMPORAL"
    chmod 440 "$TEMPORAL"

    # Se revisa con visudo antes de dejarlo: un archivo de permisos mal escrito
    # podría estropear sudo en toda la Mac.
    if visudo -cf "$TEMPORAL" >/dev/null 2>&1; then
        mkdir -p /etc/sudoers.d
        mv "$TEMPORAL" "$PERMISO_APAGADO"
    else
        rm -f "$TEMPORAL"
        APAGADO_OK=0
    fi

    # macOS lee esa carpeta sólo si /etc/sudoers la incluye (lo normal).
    if ! grep -Eq '^[#@]includedir (/private)?/etc/sudoers\.d' /etc/sudoers 2>/dev/null; then
        APAGADO_OK=0
    fi
fi

# 6. ¿Se llega al sistema desde aquí?
echo "  Comprobando la conexión con el sistema..."
if [ "$CON_BLOQUEO" = 1 ]; then
    PRUEBA="$URL/api/equipo/estado?centro=$LAB&maquina=$PC"
else
    PRUEBA="$URL/login"
fi
if curl -sf --connect-timeout 5 -m 8 -o /dev/null "$PRUEBA"; then
    CONEXION=1
else
    CONEXION=0
fi

FECHA="$(date '+%Y-%m-%d %H:%M:%S')"
echo "$FECHA  [INFO]  Instalado. Url=$URL  Laboratorio=$LAB  Maquina=$PC  Admin=$BLOQUEAR_ADMIN  Inactividad=$MINUTOS min  Aviso=$AVISO s  Franja='$NO_APAGAR_ENTRE'" >> "$ARCHIVO_BITACORA"

# --- Resumen --------------------------------------------------------------------
echo ""
verde "  Listo. La Mac quedó configurada."
echo ""
echo "    Sistema:        $URL"
if [ "$SIN_APAGADO" = 1 ]; then
    echo "    Apagado:        desactivado"
else
    echo "    Se apaga tras:  $MINUTOS minutos sin uso"
    echo "    Aviso previo:   $AVISO segundos"
    [ -n "$NO_APAGAR_ENTRE" ] && echo "    Nunca entre:    $NO_APAGAR_ENTRE"
fi
if [ "$CON_BLOQUEO" = 1 ]; then
    echo "    Bloqueo:        laboratorio $LAB, máquina #$PC"
    [ "$BLOQUEAR_ADMIN" = 1 ] && echo "                    (también para cuentas de administrador)"
else
    echo "    Bloqueo:        desactivado"
fi
echo "    Bitácora:       $ARCHIVO_BITACORA"
echo ""

if [ "$CONEXION" = 0 ]; then
    amarillo "  AVISO: no se pudo conectar con $URL"
    echo "  Revisa que la Mac esté conectada a la red del instituto. El bloqueo"
    echo "  funcionará en cuanto haya conexión; sin ella, se libera solo a los 90 segundos."
    echo ""
fi

if [ "$APAGADO_OK" = 0 ]; then
    amarillo "  AVISO: no se pudo dar el permiso para apagar."
    echo "  Todo lo demás funciona, pero el equipo no se apagará solo."
    echo ""
fi

if [ "$CON_BLOQUEO" = 1 ]; then
    echo "  ANOTA que esta Mac quedó como PC #$PC del laboratorio $LAB,"
    echo "  para no repetir el número."
    echo ""
fi
echo "  Empieza a funcionar en el próximo inicio de sesión."
echo "  Para comprobarlo: menú Apple (arriba a la izquierda) > Cerrar sesión,"
echo "  y vuelve a entrar con la cuenta de los alumnos."
echo ""
echo "  Ya puedes retirar la memoria USB."
echo ""
