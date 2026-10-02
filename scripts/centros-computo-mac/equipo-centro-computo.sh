#!/bin/bash
# ==============================================================================
#  SISTEMA DE SERVICIOS INFORMÁTICOS - LabGate
#  Vigilante de las computadoras Mac de los centros de cómputo
# ==============================================================================
#
#  Es la versión para Mac de Equipo-CentroComputo.ps1 (la de Windows) y hace lo
#  mismo en cada computadora del laboratorio:
#
#    1. Al iniciar sesión, abre el navegador con el sistema.
#    2. Si el equipo sabe qué máquina es (laboratorio y número), lo deja
#       bloqueado con el sistema en pantalla completa hasta que el alumno
#       registre su asistencia o su entrada de uso libre en ESTA máquina. Cuando
#       la sesión termina, avisa 30 segundos y vuelve a bloquearse.
#    3. Apaga el equipo tras un rato sin uso, avisando antes.
#
#  No se ejecuta a mano: lo deja instalado instalar-mac.sh y macOS lo arranca en
#  cada inicio de sesión (un "LaunchAgent", el equivalente de la tarea programada
#  de Windows). Si alguien lo cierra, macOS lo vuelve a lanzar a los 10 segundos.
#  La configuración de cada equipo la escribe el instalador en:
#
#      /Library/Application Support/LabGate/config
#
#  -----------------------------------------------------------------------------
#  EN QUÉ SE DIFERENCIA DE WINDOWS
#  -----------------------------------------------------------------------------
#
#  En Windows el bloqueo anula teclas (Alt+Tab, la tecla Windows...). En Mac eso
#  exige conceder a mano permisos de Accesibilidad en cada equipo, así que aquí
#  se hace de otra forma que no necesita ningún permiso: mientras el equipo está
#  bloqueado, si el alumno pasa a otro programa (Cmd+Tab, el Dock, Spotlight),
#  ese programa se cierra y el sistema vuelve al frente en menos de un segundo.
#  La Terminal, el Monitor de Actividad y la Configuración del Sistema se cierran
#  en cuanto aparecen, igual que el Administrador de tareas en Windows.
#
#  Apagar el equipo requiere permiso de administrador. El instalador deja
#  permitido a los usuarios únicamente eso, apagar, en /etc/sudoers.d/labgate-apagado.
#
#  Está escrito para el bash 3.2 que trae macOS: nada de bash moderno.
# ==============================================================================

export PATH="/usr/bin:/bin:/usr/sbin:/sbin"
export LANG="en_US.UTF-8"

# Carpeta de este script: junto a él está aviso.applescript.
CARPETA_SCRIPT="$(cd "$(dirname "$0")" 2>/dev/null && pwd)"
ARCHIVO_CONFIG="/Library/Application Support/LabGate/config"
ARCHIVO_BITACORA="/Library/Logs/LabGate/centro-computo.log"

# ==============================================================================
#  CONFIGURACIÓN
# ==============================================================================

# Valores por omisión, los mismos que en Windows. El archivo de configuración
# que escribe el instalador los reemplaza.
URL="http://labgate.example"
LABORATORIO=0
MAQUINA=0
BLOQUEAR_ADMINISTRADORES=0
MINUTOS_INACTIVIDAD=30
SEGUNDOS_AVISO=60
NO_APAGAR_ENTRE=""
SIN_APAGADO=0

if [ -r "$ARCHIVO_CONFIG" ]; then
    . "$ARCHIVO_CONFIG"
fi

# Opciones de la línea de comandos (las usa probar-mac.sh). Ganan a la configuración.
PROBAR=0
while [ $# -gt 0 ]; do
    case "$1" in
        --probar)                   PROBAR=1 ;;
        --minutos)                  MINUTOS_INACTIVIDAD="${2:-}"; shift ;;
        --aviso)                    SEGUNDOS_AVISO="${2:-}"; shift ;;
        --laboratorio)              LABORATORIO="${2:-}"; shift ;;
        --maquina)                  MAQUINA="${2:-}"; shift ;;
        --bloquear-administradores) BLOQUEAR_ADMINISTRADORES=1 ;;
        --sin-apagado)              SIN_APAGADO=1 ;;
    esac
    shift
done

es_numero() {
    case "$1" in
        '' | *[!0-9]*) return 1 ;;
    esac
    return 0
}

# Se normalizan los números: un "07" se volvería octal en las cuentas y el
# servidor rechaza "centro=01" (no lo toma como entero).
es_numero "$LABORATORIO"         || LABORATORIO=0
es_numero "$MAQUINA"             || MAQUINA=0
es_numero "$MINUTOS_INACTIVIDAD" || MINUTOS_INACTIVIDAD=30
es_numero "$SEGUNDOS_AVISO"      || SEGUNDOS_AVISO=60
LABORATORIO=$(( 10#$LABORATORIO ))
MAQUINA=$(( 10#$MAQUINA ))
MINUTOS_INACTIVIDAD=$(( 10#$MINUTOS_INACTIVIDAD ))
SEGUNDOS_AVISO=$(( 10#$SEGUNDOS_AVISO ))

# Mismos límites que en Windows.
[ "$MINUTOS_INACTIVIDAD" -lt 2 ]   && MINUTOS_INACTIVIDAD=2
[ "$MINUTOS_INACTIVIDAD" -gt 480 ] && MINUTOS_INACTIVIDAD=480
[ "$SEGUNDOS_AVISO" -lt 15 ]       && SEGUNDOS_AVISO=15
[ "$SEGUNDOS_AVISO" -gt 600 ]      && SEGUNDOS_AVISO=600

URL="${URL%/}"

# --- Datos de este usuario -------------------------------------------------------
[ -n "$HOME" ] || HOME="$(cd ~ 2>/dev/null && pwd)"
USUARIO="$(id -un)"
DATOS_USUARIO="$HOME/.labgate"

# Perfil del navegador exclusivo del sistema en este usuario.
#
# Lo usan la pantalla de bloqueo Y la ventana que queda abierta al desbloquear:
# así la sesión que el alumno abrió para registrarse sigue viva, en vez de pedirle
# que vuelva a entrar. Se borra al empezar cada sesión de macOS y cada vez que el
# equipo se vuelve a bloquear, para que nadie herede la sesión del anterior.
PERFIL="$DATOS_USUARIO/perfil-sistema"

# Página local que se muestra mientras la red todavía no conecta.
PAGINA_ESPERA="$DATOS_USUARIO/espera.html"

# Marca de "ya hay un vigilante en esta sesión" y firma de la sesión de macOS.
CERROJO="$DATOS_USUARIO/vigilante.lock"
MARCA_SESION="$DATOS_USUARIO/sesion"

mkdir -p "$DATOS_USUARIO" 2>/dev/null

# Programas que dan salida del bloqueo: se cierran en cuanto aparecen, como el
# Administrador de tareas en Windows. En modo prueba la Terminal se respeta,
# porque desde ahí se detiene la prueba.
ESCAPES='Activity Monitor|System Settings|System Preferences|Script Editor|Automator|Console|Shortcuts'
[ "$PROBAR" = 1 ] || ESCAPES="Terminal|iTerm|$ESCAPES"
PATRON_ESCAPES="/($ESCAPES)\\.app/Contents/MacOS/"

# ==============================================================================
#  BITÁCORA
# ==============================================================================

escribir_bitacora() {
    local linea
    linea="$(date '+%Y-%m-%d %H:%M:%S')  [${2:-INFO}]  $1  (pid $$, $USUARIO)"

    [ "$PROBAR" = 1 ] && echo "  $linea"

    # Si la bitácora no se puede escribir, el trabajo principal debe continuar.
    {
        # Al pasar de 1 MB se conservan sólo las últimas líneas. Se recorta en el
        # sitio (y no renombrando) porque el archivo lo comparten todas las
        # cuentas y sólo el administrador podría renombrarlo.
        if [ -f "$ARCHIVO_BITACORA" ] && [ "$(stat -f %z "$ARCHIVO_BITACORA")" -gt 1048576 ]; then
            tail -n 4000 "$ARCHIVO_BITACORA" > "$DATOS_USUARIO/bitacora.tmp" &&
                cat "$DATOS_USUARIO/bitacora.tmp" > "$ARCHIVO_BITACORA"
            rm -f "$DATOS_USUARIO/bitacora.tmp"
        fi
        echo "$linea" >> "$ARCHIVO_BITACORA"
    } 2>/dev/null
}

# ==============================================================================
#  UNA SOLA COPIA POR SESIÓN
# ==============================================================================

tomar_cerrojo() {
    if mkdir "$CERROJO" 2>/dev/null; then
        echo $$ > "$CERROJO/pid"
        return 0
    fi

    # Ya existe: ¿la copia que lo tomó sigue viva?
    local otro
    otro="$(cat "$CERROJO/pid" 2>/dev/null)"
    if [ -n "$otro" ] && [ "$otro" != "$$" ] && kill -0 "$otro" 2>/dev/null &&
        ps -ww -o command= -p "$otro" 2>/dev/null | grep -q 'equipo-centro-computo'; then
        return 1
    fi

    # Quedó de una copia que ya no existe (se apagó el equipo, por ejemplo).
    echo $$ > "$CERROJO/pid"
    return 0
}

soltar_cerrojo() {
    if [ "$(cat "$CERROJO/pid" 2>/dev/null)" = "$$" ]; then
        rm -rf "$CERROJO"
    fi
}

# ==============================================================================
#  INACTIVIDAD
# ==============================================================================

# Segundos sin teclado ni ratón, o -1 si no se pudo medir. Quien llama debe
# tratar el -1 como "no apagar".
segundos_inactivo() {
    local nanosegundos
    nanosegundos="$(ioreg -c IOHIDSystem 2>/dev/null | awk '/HIDIdleTime/ {print $NF; exit}')"
    if ! es_numero "$nanosegundos"; then
        echo -1
        return
    fi
    echo $(( nanosegundos / 1000000000 ))
}

# "07:30" -> 450
a_minutos() {
    local h="${1%%:*}" m="${1##*:}"
    es_numero "$h" && es_numero "$m" || return 1
    echo $(( 10#$h * 60 + 10#$m ))
}

# Franja "HH:MM-HH:MM" en la que el equipo nunca se apaga solo. Admite que cruce
# la medianoche ("22:00-06:00").
dentro_de_franja_protegida() {
    [ -n "$NO_APAGAR_ENTRE" ] || return 1

    local inicio fin ahora
    inicio="$(a_minutos "${NO_APAGAR_ENTRE%%-*}")" || return 1
    fin="$(a_minutos "${NO_APAGAR_ENTRE##*-}")" || return 1
    ahora="$(a_minutos "$(date +%H:%M)")"

    if [ "$inicio" -le "$fin" ]; then
        [ "$ahora" -ge "$inicio" ] && [ "$ahora" -lt "$fin" ]
    else
        [ "$ahora" -ge "$inicio" ] || [ "$ahora" -lt "$fin" ]
    fi
}

# Con el cambio rápido de usuario puede haber varias sesiones a la vez. Como la
# inactividad que se mide es la de toda la máquina y el aviso sólo se ve en esta
# sesión, ante la duda no se apaga.
hay_otra_sesion_abierta() {
    local sesiones
    sesiones=$(( $(who 2>/dev/null | awk '$2 == "console" {print $1}' | sort -u | wc -l) ))
    [ "$sesiones" -gt 1 ]
}

# Apagar a mitad de una instalación puede dejar el equipo inservible.
macos_esta_actualizando() {
    pgrep -x softwareupdate >/dev/null 2>&1 ||
        pgrep -x Installer >/dev/null 2>&1 ||
        pgrep -x installer >/dev/null 2>&1
}

# ==============================================================================
#  AVISOS EN PANTALLA
# ==============================================================================

# Muestra una ventana de aviso sin esperar a que se cierre. Deja en AVISO_PID el
# proceso de la ventana, para poder quitarla.
abrir_aviso() {
    local mensaje="$1" boton="$2" segundos="$3"
    osascript "$CARPETA_SCRIPT/aviso.applescript" \
        "Centro de Cómputo - LabGate" "$mensaje" "$boton" "$segundos" >/dev/null 2>&1 &
    AVISO_PID=$!
}

cerrar_aviso() {
    [ -n "$AVISO_PID" ] && kill "$AVISO_PID" 2>/dev/null
    AVISO_PID=""
}

# Avisa de que la sesión del alumno terminó y el equipo se va a bloquear.
#
# No se puede cancelar: el botón sólo cierra la ventana, la cuenta sigue. La
# forma de seguir usando el equipo es registrar una entrada en el sistema, y la
# ventana del sistema queda detrás, accesible, mientras corre la cuenta.
mostrar_aviso_de_bloqueo() {
    local segundos="${1:-30}" hora
    hora="$(date -v+"${segundos}"S '+%H:%M:%S')"

    abrir_aviso "Tu sesión en este equipo terminó.

El equipo se bloqueará en $segundos segundos (a las $hora).

Guarda tu trabajo. Si quieres seguir usándolo, registra tu entrada de Uso Libre en el sistema antes de que termine la cuenta." \
        "Entendido" "$segundos"

    sleep "$segundos"
    cerrar_aviso
}

# Muestra el aviso de apagado y devuelve 0 sólo si se puede apagar con seguridad.
#
# Criterio deliberado, el mismo de Windows: si alguien vuelve a usar el equipo
# (mueve el ratón, pulsa una tecla o el botón del aviso), NO se apaga. Un equipo
# encendido de más es un problema menor que un alumno perdiendo su trabajo.
mostrar_aviso_de_apagado() {
    local segundos="$1" hora referencia ahora restantes
    hora="$(date -v+"${segundos}"S '+%H:%M:%S')"

    abrir_aviso "El equipo se apagará por inactividad en $segundos segundos (a las $hora).

Se cerrará todo sin guardar. Si sigues aquí, mueve el ratón o pulsa el botón." \
        "Seguir usando el equipo" "$segundos"

    referencia="$(segundos_inactivo)"
    restantes="$segundos"

    while [ "$restantes" -gt 0 ]; do
        sleep 1
        restantes=$(( restantes - 1 ))
        ahora="$(segundos_inactivo)"

        # Una medición fallida no se toma como "volvió el usuario": se ignora.
        if [ "$ahora" -ge 0 ] && [ "$referencia" -ge 0 ]; then
            if [ "$ahora" -lt $(( referencia - 2 )) ]; then
                cerrar_aviso
                return 1
            fi
            referencia="$ahora"
        fi
    done

    cerrar_aviso
    return 0
}

# ==============================================================================
#  APAGADO
# ==============================================================================

# "shutdown -h now" es un apagado ordenado: detiene los servicios y vacía el
# disco. Lo único que no hace es esperar a que cada programa pregunte si se
# guarda, igual que el /f de Windows; eso es lo que pidió el centro de cómputo.
apagar_equipo() {
    if sudo -n /sbin/shutdown -h now >/dev/null 2>&1; then
        escribir_bitacora 'Apagado aceptado por macOS.'
        return 0
    fi
    escribir_bitacora 'No se pudo apagar: falta el permiso que deja el instalador (/etc/sudoers.d/labgate-apagado).' ERROR
    return 1
}

# Devuelve 0 si el equipo se va a apagar. En lugar de dormir cuando algo impide
# apagar, deja apuntado cuándo volver a mirar: el mismo bucle mantiene la
# pantalla de bloqueo y no puede quedarse parado.
revisar_inactividad() {
    [ "$SECONDS" -lt "$INACTIVIDAD_EN_PAUSA_HASTA" ] && return 1

    local inactivo
    inactivo="$(segundos_inactivo)"

    # Sin medición fiable no se toma ninguna decisión.
    [ "$inactivo" -lt 0 ] && return 1
    [ "$inactivo" -lt $(( MINUTOS_INACTIVIDAD * 60 )) ] && return 1

    # --- Comprobaciones de seguridad antes de molestar a nadie ---
    dentro_de_franja_protegida && return 1

    if hay_otra_sesion_abierta; then
        escribir_bitacora 'Hay otra sesión abierta en el equipo; no se apaga.' AVISO
        INACTIVIDAD_EN_PAUSA_HASTA=$(( SECONDS + 300 ))
        return 1
    fi

    if macos_esta_actualizando; then
        escribir_bitacora 'macOS está instalando actualizaciones; no se apaga.' AVISO
        INACTIVIDAD_EN_PAUSA_HASTA=$(( SECONDS + 300 ))
        return 1
    fi

    escribir_bitacora "Sin actividad desde hace $(( inactivo / 60 )) minutos. Mostrando aviso." AVISO

    if ! mostrar_aviso_de_apagado "$SEGUNDOS_AVISO"; then
        # Alguien volvió a usar el equipo: la cuenta de inactividad ya se
        # reinició sola, así que no hace falta nada más.
        escribir_bitacora 'No se apaga: el aviso se canceló.'
        return 1
    fi

    if [ "$PROBAR" = 1 ]; then
        escribir_bitacora 'SIMULACIÓN: aquí se habría apagado el equipo. Se continúa vigilando.' AVISO
        INACTIVIDAD_EN_PAUSA_HASTA=$(( SECONDS + 60 ))
        return 1
    fi

    apagar_equipo && return 0

    # Si el apagado falló, se reintenta más tarde.
    INACTIVIDAD_EN_PAUSA_HASTA=$(( SECONDS + 600 ))
    return 1
}

# ==============================================================================
#  NAVEGADOR
# ==============================================================================

# Ruta de Google Chrome. Es el único navegador de Mac que admite la pantalla
# completa de bloqueo (--kiosk) con un perfil propio.
buscar_chrome() {
    local app
    for app in "/Applications/Google Chrome.app" "$HOME/Applications/Google Chrome.app"; do
        if [ -x "$app/Contents/MacOS/Google Chrome" ]; then
            echo "$app"
            return
        fi
    done

    # Por si lo instalaron en otra carpeta.
    app="$(mdfind "kMDItemCFBundleIdentifier == 'com.google.Chrome'" 2>/dev/null | head -n 1)"
    if [ -n "$app" ] && [ -x "$app/Contents/MacOS/Google Chrome" ]; then
        echo "$app"
    fi
}

# "?equipo=1-7": le dice al sistema qué computadora es. El alumno no lo nota;
# sirve para detectar la máquina al registrarse y para que, cuando quien inicia
# sesión es del personal, el sistema sepa qué computadora liberar.
sufijo_equipo() {
    if [ "$LABORATORIO" -gt 0 ] && [ "$MAQUINA" -gt 0 ]; then
        echo "?equipo=$LABORATORIO-$MAQUINA"
    fi
}

# Escribe la página local que se abre al principio y devuelve su dirección.
#
# Al encender, la red tarda en conectarse; abrir el sistema directamente dejaba el
# navegador en "sin conexión" hasta que alguien recargara a mano. Esta página no
# depende de la red: reintenta sola cada dos segundos y entra al sistema en cuanto
# el servidor contesta. Entra por /redirect, que lleva a cada quien a su panel y a
# quien no tiene sesión lo manda al login.
preparar_pagina_espera() {
    local sufijo
    sufijo="$(sufijo_equipo)"

    if cat > "$PAGINA_ESPERA" 2>/dev/null <<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Centro de Cómputo LabGate</title>
<style>
  html, body { height: 100%; margin: 0; }
  body { display: flex; align-items: center; justify-content: center; background: #1a1a1a;
         font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; color: #ffffff; }
  .caja { text-align: center; max-width: 540px; padding: 40px; }
  .marca { color: #FFE900; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
  h1 { font-size: 32px; margin: 8px 0 30px; }
  .barra { height: 6px; background: #333333; border-radius: 3px; overflow: hidden; }
  .barra div { width: 35%; height: 100%; background: #009B4D; border-radius: 3px; animation: avanzar 1.4s ease-in-out infinite; }
  @keyframes avanzar { 0% { margin-left: -35%; } 100% { margin-left: 100%; } }
  p { color: #bdbdbd; margin-top: 24px; font-size: 15px; line-height: 1.6; }
</style>
</head>
<body>
<div class="caja">
  <div class="marca">Instituto Tecnológico Demo</div>
  <h1>Centro de Cómputo</h1>
  <div class="barra"><div></div></div>
  <p id="estado">Conectando con el sistema…</p>
</div>
<script>
  var destino = "$URL";
  var equipo = "$sufijo";
  var intentos = 0;
  var reloj = null;

  // En la pantalla de bloqueo al encender: hora (en milisegundos) a la que el
  // equipo se libera solo si no hay conexión. Llega así: espera.html#liberar=...
  var liberar = parseInt((location.hash.match(/liberar=(\d+)/) || [])[1] || "0", 10);

  function avisarSinConexion() {
    var texto = "Esperando la red del instituto. En cuanto haya conexión se abrirá el sistema.";
    if (liberar > 0) {
      var faltan = Math.ceil((liberar - Date.now()) / 1000);
      texto = faltan > 0
        ? "Sin conexión con el sistema. Si no se conecta, el equipo se liberará en " +
          Math.floor(faltan / 60) + ":" + ("0" + (faltan % 60)).slice(-2) + "."
        : "Sin conexión con el sistema. Liberando el equipo…";
    }
    document.getElementById("estado").textContent = texto;
  }

  function probar() {
    intentos++;
    fetch(destino + "/favicon.ico?t=" + Date.now(), { mode: "no-cors", cache: "no-store" })
      .then(function () { location.replace(destino + "/redirect" + equipo); })
      .catch(function () {
        if (intentos > 5 && !reloj) {
          avisarSinConexion();
          reloj = setInterval(avisarSinConexion, 1000);
        }
        setTimeout(probar, 2000);
      });
  }

  probar();
</script>
</body>
</html>
HTML
    then
        echo "file://${PAGINA_ESPERA// /%20}"
    else
        # Sin página de espera se va directo al sistema.
        echo "$URL/redirect$sufijo"
    fi
}

# Todos los procesos de Chrome que usan el perfil del sistema, y sólo esos: las
# ventanas que el alumno abra con su propio Chrome no se tocan.
procesos_del_perfil() {
    pgrep -u "$UID" -f -- "--user-data-dir=$PERFIL" 2>/dev/null
}

# El proceso principal de ese Chrome (el que tiene las ventanas); los demás son
# sus ayudantes (--type=renderer, gpu, etc.).
proceso_principal_del_perfil() {
    local pid comando
    for pid in $(procesos_del_perfil); do
        comando="$(ps -ww -o command= -p "$pid" 2>/dev/null)"
        case "$comando" in
            *--type=* | *Helper* | *crashpad*) ;;
            *'Contents/MacOS/Google Chrome'*)
                echo "$pid"
                return
                ;;
        esac
    done
}

# Abre el sistema en una ventana normal del navegador.
#   --con-perfil  usa el perfil del sistema, el mismo de la pantalla de bloqueo,
#                 para que el alumno siga con la sesión que abrió al registrarse.
abrir_sistema() {
    local inicio
    inicio="$(preparar_pagina_espera)"

    if [ -z "$CHROME_APP" ]; then
        # Sin Chrome, el navegador predeterminado (Safari) directo al sistema.
        open "$URL/redirect$(sufijo_equipo)" >/dev/null 2>&1
        escribir_bitacora "Sin Google Chrome: abierto con el navegador predeterminado -> $URL" AVISO
        return
    fi

    if [ "$1" = "--con-perfil" ]; then
        ULTIMA_APERTURA=$SECONDS
        open -na "$CHROME_APP" --args \
            --no-first-run --no-default-browser-check --hide-crash-restore-bubble \
            --use-mock-keychain --remote-debugging-port=0 \
            "--user-data-dir=$PERFIL" "$inicio" >/dev/null 2>&1
    else
        open -a "$CHROME_APP" "$inicio" --args --no-first-run --no-default-browser-check >/dev/null 2>&1
    fi
    escribir_bitacora "Navegador abierto: Google Chrome -> $URL"
}

# Abre el navegador a pantalla completa, sin barra de direcciones, sin pestañas y
# sin botones: sólo el sistema.
#
# --use-mock-keychain evita que Chrome le pida al llavero de macOS su contraseña;
# ese diálogo del sistema quedaría encima de la pantalla de bloqueo.
#
# --remote-debugging-port=0 deja preguntarle a Chrome qué pestañas tiene abiertas
# (ver revisar_pestanas_del_sistema). Escucha sólo dentro de esta computadora
# (127.0.0.1) y en un puerto al azar que Chrome anota en su perfil. La ventana
# normal del sistema se abre con lo mismo.
abrir_kiosco() {
    local inicio
    inicio="$(preparar_pagina_espera)"
    ULTIMA_APERTURA=$SECONDS
    KIOSCO=""

    # Al encender sin red, la página enseña cuánto falta para que el equipo se
    # libere solo; así nadie lo reinicia creyendo que se trabó.
    if [ -n "$LIBERAR_SIN_RED_EN" ] && [ "$(date +%s)" -lt "$LIBERAR_SIN_RED_EN" ]; then
        inicio="$inicio#liberar=${LIBERAR_SIN_RED_EN}000"
    fi

    if ! open -na "$CHROME_APP" --args \
        --kiosk --no-first-run --no-default-browser-check \
        --disable-session-crashed-bubble --hide-crash-restore-bubble --disable-infobars \
        --disable-features=Translate --noerrdialogs --use-mock-keychain \
        --remote-debugging-port=0 \
        "--user-data-dir=$PERFIL" "$inicio" >/dev/null 2>&1; then
        escribir_bitacora 'No se pudo abrir la pantalla de bloqueo.' ERROR
        return 1
    fi
    return 0
}

# Cierra las ventanas del perfil del sistema.
#
# Primero por las buenas (SIGTERM): Chrome se cierra ordenadamente y guarda las
# cookies en disco. Si se matara a la fuerza nada más registrarse, la cookie de
# la sesión podría no haberse escrito y el alumno tendría que volver a entrar. Lo
# que no cierre a tiempo, se cierra a la fuerza.
cerrar_navegador_del_sistema() {
    local segundos="${1:-6}" pid limite

    pid="$(proceso_principal_del_perfil)"
    [ -n "$pid" ] && kill -TERM "$pid" 2>/dev/null

    limite=$(( SECONDS + segundos ))
    while [ "$SECONDS" -lt "$limite" ]; do
        [ -z "$(procesos_del_perfil)" ] && return 0
        sleep 0.3
    done

    for pid in $(procesos_del_perfil); do
        kill -KILL "$pid" 2>/dev/null
    done
    sleep 0.5
}

# Deja el perfil del sistema en blanco: sin sesión abierta, sin historial.
limpiar_perfil_del_sistema() {
    cerrar_navegador_del_sistema 2
    [ -d "$PERFIL" ] || return 0

    if ! rm -rf "$PERFIL" 2>/dev/null; then
        # Si algo quedó tomado, al menos se borran las cookies, que son las que
        # guardan la sesión del alumno anterior.
        find "$PERFIL" -name 'Cookies*' -delete 2>/dev/null
    fi
}

# Cierra los Chrome del alumno (los que NO son del sistema). Sólo se usa con el
# equipo bloqueado: así la pantalla de bloqueo es el único Chrome abierto y nadie
# ve las pestañas del alumno anterior.
cerrar_otros_chrome() {
    local pid comando
    for pid in $(pgrep -u "$UID" -x "Google Chrome" 2>/dev/null); do
        comando="$(ps -ww -o command= -p "$pid" 2>/dev/null)"
        case "$comando" in
            *"--user-data-dir=$PERFIL"*) ;;
            *) kill -TERM "$pid" 2>/dev/null ;;
        esac
    done
}

# ==============================================================================
#  PANTALLA DE BLOQUEO
# ==============================================================================

# Proceso del programa que está al frente (el que recibe el teclado).
pid_al_frente() {
    local asn
    asn="$(lsappinfo front 2>/dev/null)"
    case "$asn" in
        ASN:*) ;;
        *) return ;;
    esac
    lsappinfo info -only pid "$asn" 2>/dev/null |
        awk -F= '/pid/ { gsub(/[^0-9]/, "", $2); print $2; exit }'
}

# Deja en PESTANAS cuántas pestañas del Chrome del sistema muestran algo (el
# sistema, la página de espera u otro sitio), o -1 si no se pudo saber.
#
# Hace falta porque en Mac, a diferencia de Windows, cerrar la ventana (la X roja
# o Cmd+W) NO cierra Chrome: se queda abierto sin ninguna. Y si después se pulsa
# su icono en el Dock, abre una pestaña nueva en blanco, no el sistema. Por eso
# las pestañas nuevas en blanco no cuentan: para el sistema es como no tener nada.
#
# Se le pregunta al propio Chrome por su puerto de depuración (ver abrir_kiosco).
revisar_pestanas_del_sistema() {
    local puerto lista
    PESTANAS=-1

    puerto="$(head -n 1 "$PERFIL/DevToolsActivePort" 2>/dev/null | tr -d '\r')"
    if es_numero "$puerto"; then
        lista="$(curl -sf --connect-timeout 2 -m 3 "http://127.0.0.1:$puerto/json/list" 2>/dev/null)"
    fi

    # Chrome contesta con una lista entre corchetes; cualquier otra cosa es que
    # no se pudo preguntar.
    case "$lista" in
        '['*) ;;
        *)
            # Se anota una sola vez: sin esta consulta no se puede reabrir una
            # ventana cerrada, y la bitácora es donde se busca el motivo.
            if [ "$FALLO_PESTANAS_ANOTADO" != 1 ]; then
                FALLO_PESTANAS_ANOTADO=1
                escribir_bitacora "No se pudo preguntar a Chrome qué pestañas tiene abiertas (puerto: '$puerto')." AVISO
            fi
            return
            ;;
    esac

    # Una entrada por cosa abierta en Chrome. Sólo cuentan las pestañas
    # ("type": "page") que no sean una pestaña nueva en blanco.
    PESTANAS="$(printf '%s\n' "$lista" | tr -d '\r' | awk -F'"' '
        /"type":/ { tipo = $4 }
        /"url":/  { url = $4 }
        /^ *}/ {
            if (tipo == "page" && url !~ /^(chrome:\/\/(newtab|new-tab-page)|chrome-search:\/\/|about:blank)/) n++
            tipo = ""
            url = ""
        }
        END { print n + 0 }')"
}

# Cierra y vuelve a abrir la pantalla de bloqueo cuando se quedó sin ventana.
# Pedirle a Chrome que pase al frente sin ventana le haría abrir una pestaña
# nueva con el buscador, en vez del sistema.
reabrir_kiosco() {
    # Recién abierta todavía no tiene ventana: se le da tiempo.
    [ $(( SECONDS - ULTIMA_APERTURA )) -lt 10 ] && return 1
    escribir_bitacora 'La pantalla de bloqueo se quedó sin ventana; se vuelve a abrir.' AVISO
    cerrar_navegador_del_sistema 2
    abrir_kiosco
    return 0
}

# Trae la pantalla de bloqueo al frente, como mucho una vez por segundo.
activar_kiosco() {
    [ "$SECONDS" -eq "$ULTIMA_ACTIVACION" ] && return
    ULTIMA_ACTIVACION=$SECONDS
    open -a "$CHROME_APP" >/dev/null 2>&1
}

# Lo que se hace en cada vuelta del bucle mientras el equipo está bloqueado.
mantener_kiosco() {
    local frente comando nombre

    # Los programas que dan salida del bloqueo se cierran en cuanto aparecen.
    pkill -KILL -u "$UID" -f "$PATRON_ESCAPES" 2>/dev/null

    # ¿Sigue abierta la pantalla? Se reutiliza el proceso conocido mientras viva.
    if [ -z "$KIOSCO" ] || ! kill -0 "$KIOSCO" 2>/dev/null; then
        KIOSCO="$(proceso_principal_del_perfil)"
    fi

    if [ -z "$KIOSCO" ]; then
        # Recién lanzada puede no aparecer todavía. Y para no lanzar el navegador
        # en ráfaga si algo falla, un intento cada 6 segundos.
        [ $(( SECONDS - ULTIMA_APERTURA )) -lt 6 ] && return
        escribir_bitacora 'La pantalla de bloqueo se cerró; se vuelve a abrir.' AVISO
        abrir_kiosco
        return
    fi

    frente="$(pid_al_frente)"
    [ -n "$frente" ] || return

    if [ "$frente" = "$KIOSCO" ]; then
        # Está al frente; cada 3 segundos se comprueba que siga mostrando algo y
        # no se haya quedado sin ventana o con una pestaña nueva en blanco.
        if [ $(( SECONDS - ULTIMA_REVISION_VENTANAS )) -ge 3 ]; then
            ULTIMA_REVISION_VENTANAS=$SECONDS
            revisar_pestanas_del_sistema
            [ "$PESTANAS" = 0 ] && reabrir_kiosco
        fi
        return
    fi

    # --- Otro programa pasó al frente ---
    comando="$(ps -ww -o command= -p "$frente" 2>/dev/null)"

    # En la prueba se deja usar la Terminal: desde ahí se detiene con Control+C.
    if [ "$PROBAR" = 1 ]; then
        case "$comando" in
            *Terminal.app/* | *iTerm.app/*) return ;;
        esac
    fi

    # Los programas del alumno se cierran. Los del sistema (Finder, Dock, la
    # ventana de Forzar salida...) no: basta con volver a poner la pantalla encima.
    case "$comando" in
        *"--user-data-dir=$PERFIL"*)
            ;;
        *'Google Chrome.app/Contents/MacOS/Google Chrome'*)
            kill -TERM "$frente" 2>/dev/null
            ;;
        /Applications/* | /System/Applications/* | "$HOME"/Applications/*)
            nombre="${comando%%.app/*}"
            nombre="${nombre##*/}"
            escribir_bitacora "Se abrió \"$nombre\" con el equipo bloqueado; se cerró." AVISO
            kill -KILL "$frente" 2>/dev/null
            ;;
    esac

    # Recién abierta, la pantalla pasa al frente sola en cuanto crea su ventana.
    # Pedírselo antes haría que Chrome abriera otra ventana con el buscador.
    [ $(( SECONDS - ULTIMA_APERTURA )) -lt 8 ] && return

    revisar_pestanas_del_sistema
    if [ "$PESTANAS" = 0 ] && reabrir_kiosco; then
        return
    fi

    cerrar_otros_chrome
    activar_kiosco
}

# Con la sesión del alumno abierta: si cerró la ventana del sistema, se vuelve a
# abrir con su sesión puesta, como en Windows.
#
# En Windows basta con ver que el navegador ya no existe. En Mac no: al cerrar la
# ventana Chrome sigue abierto, sin ninguna, y su icono del Dock sólo abre una
# pestaña nueva en blanco. Por eso se le pregunta a Chrome qué pestañas tiene.
# Para reabrirla se cierra ese Chrome por las buenas (así guarda la cookie de la
# sesión) y se abre de nuevo con el mismo perfil.
revisar_ventana_del_sistema() {
    local pid

    # Recién abierta todavía no tiene ventana: se le da tiempo.
    [ $(( SECONDS - ULTIMA_APERTURA )) -lt 10 ] && return

    # Si Chrome ya ni siquiera está abierto, lo reabre la consulta de cada 15 s.
    pid="$(proceso_principal_del_perfil)"
    if [ -z "$pid" ]; then
        SIN_VENTANA_SEGUIDAS=0
        return
    fi

    revisar_pestanas_del_sistema
    if [ "$PESTANAS" = 0 ]; then
        SIN_VENTANA_SEGUIDAS=$(( SIN_VENTANA_SEGUIDAS + 1 ))
    else
        SIN_VENTANA_SEGUIDAS=0
    fi

    # Dos revisiones seguidas sin ventana, para no reabrir por un parpadeo.
    if [ "$SIN_VENTANA_SEGUIDAS" -ge 2 ]; then
        SIN_VENTANA_SEGUIDAS=0
        escribir_bitacora 'La ventana del sistema se cerró; se vuelve a abrir con la sesión en curso.'
        cerrar_navegador_del_sistema 6
        abrir_sistema --con-perfil
    fi
}

entrar_bloqueo() {
    cerrar_otros_chrome
    abrir_kiosco
}

salir_bloqueo() {
    # Cierra la pantalla de bloqueo SIN borrar el perfil: la sesión que el alumno
    # abrió para registrarse se conserva para la ventana normal que viene después.
    cerrar_navegador_del_sistema 6
    KIOSCO=""
}

# ==============================================================================
#  CONSULTA AL SISTEMA
# ==============================================================================

# Pregunta al sistema si esta máquina tiene una sesión activa. Imprime:
#   1      ocupada: hay que desbloquear (o seguir desbloqueado)
#   0      libre: hay que bloquear (o seguir bloqueado)
#   nada   no se pudo consultar (servidor caído o sin red)
#
# Con --reiniciar cancela además el permiso que el personal hubiera dejado abierto
# en esta máquina. Se usa sólo al iniciar sesión en macOS.
consultar_si_esta_registrado() {
    local consulta respuesta
    consulta="$URL/api/equipo/estado?centro=$LABORATORIO&maquina=$MAQUINA"
    [ "$1" = "--reiniciar" ] && consulta="$consulta&reiniciar=1"

    respuesta="$(curl -sf --connect-timeout 4 -m 6 "$consulta" 2>/dev/null)" || return 0
    case "$respuesta" in
        *'"ocupada":true'*)  echo 1 ;;
        *'"ocupada":false'*) echo 0 ;;
    esac
}

# Igual, pero deja la respuesta en REGISTRADO. Si al iniciar sesión todavía no
# había red para avisar de la sesión nueva (reiniciar=1), se avisa en la primera
# consulta que sí conteste: sin ese aviso, el Uso Libre que el alumno anterior
# dejó abierto al apagar seguiría liberando el equipo para el siguiente.
consultar() {
    if [ "$REINICIO_PENDIENTE" = 1 ]; then
        REGISTRADO="$(consultar_si_esta_registrado --reiniciar)"
        [ -n "$REGISTRADO" ] && REINICIO_PENDIENTE=0
    else
        REGISTRADO="$(consultar_si_esta_registrado)"
    fi

    # El servidor contestó: la cuenta regresiva de "sin red" ya no aplica.
    [ -n "$REGISTRADO" ] && LIBERAR_SIN_RED_EN=''
}

# ==============================================================================
#  SESIÓN DE MACOS
# ==============================================================================

es_administrador() {
    id -Gn 2>/dev/null | tr ' ' '\n' | grep -qx admin
}

# ¿El vigilante arranca junto con la sesión de macOS, o es un reinicio con el
# equipo ya en marcha (alguien lo cerró y macOS lo relanzó)? En ese caso puede
# haber un alumno trabajando y no hay que tocarle nada; al iniciar sesión, en
# cambio, el equipo debe partir bloqueado y en blanco.
#
# Cada sesión tiene su propio proceso loginwindow: si es otro que el de la última
# vez, la sesión es nueva. Si no se puede saber, se responde que sí: bloquear de
# más es preferible a dejar el equipo abierto con la sesión del alumno anterior.
es_arranque_de_sesion() {
    local pid firma
    pid="$(pgrep -u "$UID" -x loginwindow 2>/dev/null | head -n 1)"
    # Respaldo: el Dock también arranca con cada sesión.
    [ -n "$pid" ] || pid="$(pgrep -u "$UID" -x Dock 2>/dev/null | head -n 1)"
    [ -n "$pid" ] || return 0

    firma="$pid $(ps -o lstart= -p "$pid" 2>/dev/null)"
    if [ -f "$MARCA_SESION" ] && [ "$(cat "$MARCA_SESION" 2>/dev/null)" = "$firma" ]; then
        return 1
    fi
    echo "$firma" > "$MARCA_SESION" 2>/dev/null
    return 0
}

# ==============================================================================
#  VIGILANTE
# ==============================================================================

# Un solo bucle que se encarga de todo lo que pasa en el equipo:
#
#   - BLOQUEADO: mantiene la pantalla del sistema al frente, cierra lo que el
#     alumno abra, y pregunta al servidor cada 5 s si ya se registró. Si sí,
#     desbloquea.
#   - LIBRE: pregunta cada 15 s si la sesión sigue abierta. Si terminó, avisa y
#     vuelve a bloquear.
#   - En los dos estados, apaga el equipo tras el tiempo sin uso. Así un equipo
#     bloqueado que nadie usa tampoco se queda encendido toda la noche.
iniciar_vigilante() {
    # Tiempos del bucle (los mismos de Windows).
    local cada_consulta_bloq=5        # s entre consultas al servidor estando bloqueado
    local cada_consulta_libre=15      # s entre consultas estando libre
    local cada_inactividad=20         # s entre revisiones de inactividad
    local espera_red_al_inicio=90     # s que se espera a la red al encender antes de liberar
    local espera_red_despues=60       # s sin respuesta, ya con contacto previo, antes de liberar
    local corte_largo=15              # s sin red a partir de los cuales se recarga la pantalla
    local cada_revision_ventana=5     # s entre revisiones de la ventana del sistema (sólo Mac)

    local ahora referencia tope en_marcha

    # --- Arranque ---
    ESTADO=libre

    # Desde aquí cuenta la espera a la red al encender.
    local inicio=$SECONDS

    if [ "$CON_BLOQUEO" = 1 ]; then
        en_marcha=0

        # Hora (segundos desde 1970) a la que se libera el equipo si no hay
        # red; la pantalla de bloqueo la enseña en cuenta regresiva.
        LIBERAR_SIN_RED_EN=$(( $(date +%s) + espera_red_al_inicio ))

        if [ "$ARRANQUE_DE_SESION" = 1 ]; then
            # Sesión nueva: el servidor cancela el permiso que el personal hubiera
            # dejado abierto sin cerrar sesión en el sistema, y cierra el Uso
            # Libre que el alumno anterior dejó abierto al apagar.
            REINICIO_PENDIENTE=1
            consultar
        else
            consultar
            [ "$REGISTRADO" = 1 ] && en_marcha=1
        fi

        if [ "$en_marcha" = 1 ]; then
            escribir_bitacora 'El vigilante arrancó con una sesión en curso: se continúa sin interrumpir.'
            [ -n "$(proceso_principal_del_perfil)" ] || abrir_sistema --con-perfil
        else
            # Cada sesión empieza con el perfil del sistema en blanco.
            limpiar_perfil_del_sistema
            if entrar_bloqueo; then
                ESTADO=bloqueado
                escribir_bitacora "Bloqueo activo. Esperando registro en laboratorio $LABORATORIO, máquina $MAQUINA."
            else
                escribir_bitacora 'No se pudo abrir la pantalla de bloqueo; el equipo queda libre.' ERROR
                abrir_sistema
            fi
        fi
    elif [ "$ARRANQUE_DE_SESION" = 1 ]; then
        # Sin bloqueo sólo se abre el sistema, y sólo al iniciar sesión: si macOS
        # relanza el vigilante a media sesión, no se abre otra ventana.
        abrir_sistema
    fi

    if [ "$CON_APAGADO" = 1 ]; then
        local modo=''
        [ "$PROBAR" = 1 ] && modo=' [SIMULACIÓN: no apagará]'
        escribir_bitacora "Vigilante iniciado. Apagará tras $MINUTOS_INACTIVIDAD min sin actividad, con aviso de $SEGUNDOS_AVISO s.$modo"
    fi

    local hubo_contacto=0        # el servidor ya contestó alguna vez
    local sin_red_desde=''       # desde cuándo no contesta
    local libres_seguidas=0      # consultas seguidas que dicen "libre" estando desbloqueado
    local proxima_consulta=$SECONDS
    local proxima_inactiva=$(( SECONDS + cada_inactividad ))
    local proxima_ventana=$SECONDS

    while true; do
        # Bloqueado se revisa cada medio segundo; libre basta con cada segundo.
        if [ "$ESTADO" = bloqueado ]; then sleep 0.5; else sleep 1; fi
        ahora=$SECONDS

        # ================================================================
        #  BLOQUEADO
        # ================================================================
        if [ "$ESTADO" = bloqueado ]; then

            mantener_kiosco

            if [ "$ahora" -ge "$proxima_consulta" ]; then
                proxima_consulta=$(( ahora + cada_consulta_bloq ))
                consultar

                if [ "$REGISTRADO" = 1 ]; then
                    escribir_bitacora 'Registro detectado: se libera el equipo.'
                    salir_bloqueo
                    abrir_sistema --con-perfil
                    ESTADO=libre
                    hubo_contacto=1
                    sin_red_desde=''
                    libres_seguidas=0
                    proxima_consulta=$(( SECONDS + cada_consulta_libre ))

                elif [ "$REGISTRADO" = 0 ]; then
                    # Volvió la red tras un corte largo: la pantalla pudo quedarse
                    # en "sin conexión", así que se vuelve a abrir desde la espera.
                    if [ -n "$sin_red_desde" ] && [ $(( ahora - sin_red_desde )) -ge "$corte_largo" ]; then
                        escribir_bitacora 'Volvió la conexión; se recarga la pantalla de bloqueo.'
                        cerrar_navegador_del_sistema 2
                        abrir_kiosco
                    fi
                    hubo_contacto=1
                    sin_red_desde=''

                else
                    # Sin respuesta. Al encender se espera más, porque la red
                    # tarda en conectarse; ya con contacto previo se espera menos.
                    [ -n "$sin_red_desde" ] || sin_red_desde=$ahora
                    if [ "$hubo_contacto" = 1 ]; then
                        referencia=$sin_red_desde
                        tope=$espera_red_despues
                    else
                        referencia=$inicio
                        tope=$espera_red_al_inicio
                    fi

                    if [ $(( ahora - referencia )) -ge "$tope" ]; then
                        escribir_bitacora "El sistema no responde desde hace $tope s: se levanta el bloqueo para no dejar el equipo inservible." AVISO
                        salir_bloqueo
                        abrir_sistema --con-perfil
                        ESTADO=libre
                        libres_seguidas=0
                        proxima_consulta=$(( SECONDS + cada_consulta_libre ))
                    fi
                fi
            fi

        # ================================================================
        #  LIBRE (con bloqueo configurado): vigilar que la sesión siga abierta
        # ================================================================
        elif [ "$CON_BLOQUEO" = 1 ] && [ "$ahora" -ge "$proxima_consulta" ]; then
            proxima_consulta=$(( ahora + cada_consulta_libre ))
            consultar

            if [ "$REGISTRADO" = 1 ]; then
                libres_seguidas=0
                hubo_contacto=1

                # Mientras su sesión siga abierta, el sistema se queda a la mano:
                # si cerró la ventana, se vuelve a abrir con su sesión puesta.
                if [ -z "$(procesos_del_perfil)" ]; then
                    escribir_bitacora 'La ventana del sistema se cerró; se vuelve a abrir con la sesión en curso.'
                    abrir_sistema --con-perfil
                fi

            elif [ "$REGISTRADO" = 0 ]; then
                hubo_contacto=1
                libres_seguidas=$(( libres_seguidas + 1 ))

                # Dos respuestas seguidas, para no bloquear por un parpadeo.
                if [ "$libres_seguidas" -ge 2 ]; then
                    escribir_bitacora 'La sesión en este equipo terminó; se avisa y se vuelve a bloquear.' AVISO
                    mostrar_aviso_de_bloqueo 30

                    # Durante el aviso pudo registrar una entrada de uso libre.
                    consultar
                    if [ "$REGISTRADO" = 1 ]; then
                        escribir_bitacora 'Se registró una nueva entrada durante el aviso; el equipo sigue libre.'
                        libres_seguidas=0
                    else
                        # Perfil en blanco: el siguiente alumno no hereda la sesión.
                        limpiar_perfil_del_sistema
                        if entrar_bloqueo; then
                            ESTADO=bloqueado
                            sin_red_desde=''
                            proxima_consulta=$(( SECONDS + cada_consulta_bloq ))
                            escribir_bitacora 'Equipo bloqueado de nuevo. Esperando registro.'
                        else
                            abrir_sistema
                            libres_seguidas=0
                        fi
                    fi
                fi
            fi
            # Sin respuesta (sin red): se deja el equipo como está.
        fi

        # ================================================================
        #  LIBRE con la sesión abierta: ¿cerró la ventana del sistema?
        # ================================================================
        if [ "$ESTADO" = libre ] && [ "$CON_BLOQUEO" = 1 ] && [ "$REGISTRADO" = 1 ] &&
            [ "$ahora" -ge "$proxima_ventana" ]; then
            proxima_ventana=$(( ahora + cada_revision_ventana ))
            revisar_ventana_del_sistema
        fi

        # ================================================================
        #  APAGADO POR INACTIVIDAD (en los dos estados)
        # ================================================================
        if [ "$CON_APAGADO" = 1 ] && [ "$ahora" -ge "$proxima_inactiva" ]; then
            proxima_inactiva=$(( ahora + cada_inactividad ))
            if revisar_inactividad; then
                exit 0
            fi
        fi
    done
}

# ==============================================================================
#  ARRANQUE
# ==============================================================================

if [ "$(uname)" != "Darwin" ]; then
    echo "  Este script es sólo para Mac."
    exit 0
fi

if [ "$UID" -eq 0 ]; then
    echo "  No se ejecuta como administrador (sudo): macOS lo arranca solo con la cuenta de cada usuario."
    exit 0
fi

# Estado compartido entre las funciones.
ESTADO=libre
KIOSCO=''
AVISO_PID=''
REGISTRADO=''
REINICIO_PENDIENTE=0
LIBERAR_SIN_RED_EN=''
PESTANAS=-1
FALLO_PESTANAS_ANOTADO=0
SIN_VENTANA_SEGUIDAS=0
ULTIMA_APERTURA=-100
ULTIMA_ACTIVACION=-1
ULTIMA_REVISION_VENTANAS=0
INACTIVIDAD_EN_PAUSA_HASTA=0

# Un vigilante por sesión. macOS ya no lanza dos copias del instalado; esto evita
# además que la prueba corra a la vez que él.
if ! tomar_cerrojo; then
    if [ "$PROBAR" = 1 ]; then
        echo ""
        echo "  Ya hay un vigilante funcionando en esta sesión (el que está instalado)."
        echo "  Para ver el bloqueo, cierra sesión y entra con la cuenta de los alumnos."
        echo ""
    fi
    exit 0
fi

al_terminar() {
    cerrar_aviso
    # Al detener la prueba no se deja la pantalla de bloqueo abierta.
    if [ "$PROBAR" = 1 ] && [ "$ESTADO" = bloqueado ]; then
        echo ""
        echo "  Prueba detenida: se cierra la pantalla de bloqueo."
        cerrar_navegador_del_sistema 2
    fi
    soltar_cerrojo
}
trap al_terminar EXIT
# Si alguien lo cierra, sale con error y macOS lo vuelve a lanzar.
trap 'exit 1' TERM HUP
trap 'exit 130' INT

CHROME_APP="$(buscar_chrome)"

# --- Qué hace este equipo --------------------------------------------------------
BLOQUEO_CONFIGURADO=0
if [ "$LABORATORIO" -gt 0 ] && [ "$MAQUINA" -gt 0 ]; then
    BLOQUEO_CONFIGURADO=1
fi
CON_BLOQUEO=$BLOQUEO_CONFIGURADO

# El personal técnico entra con su cuenta de administrador y trabaja sin bloqueo,
# salvo en los equipos donde los alumnos también son administradores.
if [ "$BLOQUEO_CONFIGURADO" = 1 ] && [ "$BLOQUEAR_ADMINISTRADORES" != 1 ] && es_administrador; then
    escribir_bitacora 'Cuenta de administrador: no se aplica el bloqueo.'
    CON_BLOQUEO=0
fi

if [ "$CON_BLOQUEO" = 1 ] && [ -z "$CHROME_APP" ]; then
    escribir_bitacora 'Google Chrome no está instalado y sin él no hay pantalla de bloqueo: el equipo queda libre.' ERROR
    CON_BLOQUEO=0
fi

CON_APAGADO=1
[ "$SIN_APAGADO" = 1 ] && CON_APAGADO=0

ARRANQUE_DE_SESION=0
es_arranque_de_sesion && ARRANQUE_DE_SESION=1

if [ "$PROBAR" = 1 ]; then
    # La prueba siempre parte como un inicio de sesión: abre el navegador y, si
    # hay bloqueo, lo muestra.
    ARRANQUE_DE_SESION=1
    echo ""
    echo "  ============================================================"
    echo "   MODO PRUEBA - el equipo NO se apagará"
    echo "  ============================================================"
    if [ "$CON_BLOQUEO" = 1 ]; then
        echo "  Bloqueo: laboratorio $LABORATORIO, máquina #$MAQUINA"
        echo "  La pantalla quedará bloqueada hasta registrar la asistencia."
    elif [ "$BLOQUEO_CONFIGURADO" = 1 ] && [ -z "$CHROME_APP" ]; then
        echo "  Bloqueo configurado, pero falta Google Chrome: no se aplica."
    elif [ "$BLOQUEO_CONFIGURADO" = 1 ]; then
        echo "  Bloqueo configurado, pero esta cuenta es de administrador: no se aplica."
    fi
    if [ "$CON_APAGADO" = 1 ]; then
        echo "  Deja de tocar el equipo $MINUTOS_INACTIVIDAD minuto(s) para ver el aviso de apagado."
    fi
    echo ""
    echo "  Para detenerla: vuelve a esta ventana (Cmd+Tab) y pulsa Control+C."
    echo ""
else
    # Un respiro para que el escritorio termine de cargar.
    sleep 3
fi

# Sin bloqueo ni apagado sólo queda abrir el sistema.
if [ "$CON_BLOQUEO" = 0 ] && [ "$CON_APAGADO" = 0 ]; then
    [ "$ARRANQUE_DE_SESION" = 1 ] && abrir_sistema
    escribir_bitacora 'Apagado automático desactivado por configuración.'
    exit 0
fi

iniciar_vigilante
