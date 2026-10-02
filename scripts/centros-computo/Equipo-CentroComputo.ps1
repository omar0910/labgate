#Requires -Version 5.1
<#
================================================================================
 SISTEMA DE SERVICIOS INFORMÁTICOS - LabGate
 Preparación de los equipos de los centros de cómputo
================================================================================

 Hace dos cosas en cada computadora del laboratorio:

   1. Al iniciar sesión, abre el navegador con el sistema.
   2. Apaga el equipo cuando lleva un rato sin que nadie lo use,
      avisando antes para que quien esté ahí pueda evitarlo.

 -----------------------------------------------------------------------------
 CÓMO SE USA
 -----------------------------------------------------------------------------

 En cada computadora, abrir PowerShell COMO ADMINISTRADOR y ejecutar:

     .\Equipo-CentroComputo.ps1 -Instalar

 Para cambiar los tiempos o la dirección:

     .\Equipo-CentroComputo.ps1 -Instalar -MinutosInactividad 30
     .\Equipo-CentroComputo.ps1 -Instalar -NoApagarEntre "07:00-14:00"

 Para quitarlo de un equipo:

     .\Equipo-CentroComputo.ps1 -Desinstalar

 Para probarlo sin que llegue a apagar nada:

     .\Equipo-CentroComputo.ps1 -Probar -MinutosInactividad 2

 -----------------------------------------------------------------------------
 QUÉ DIRECCIÓN USAR
 -----------------------------------------------------------------------------

 La dirección por la que los equipos llegan al servidor sin avisos de
 certificado (se indica con -Url al instalar). Si en la red interna el dominio
 muestra una advertencia, porque un equipo intermedio sustituye el certificado
 del servidor, es mejor la IP: no conviene enseñar a los alumnos a saltarse
 esos avisos.

 -----------------------------------------------------------------------------
 LO QUE ESTE SCRIPT NO PUEDE HACER
 -----------------------------------------------------------------------------

 La inactividad se mide con teclado y ratón (GetLastInputInfo de Windows). Eso
 significa que NO detecta como actividad ver un video, una descarga larga ni una
 práctica que corra sola. Para esos casos está el aviso previo en pantalla: quien
 esté ahí lo ve y lo cancela.

 Al apagar se cierran las aplicaciones sin preguntar, así que SE PIERDE lo que no
 estuviera guardado. Es lo pedido por el centro de cómputo. El apagado sigue siendo
 ordenado para Windows (servicios detenidos, disco sincronizado): no daña el equipo.

 Aun así, el equipo NO se apaga si:
   - hay otra sesión de usuario abierta en la misma computadora
     (el aviso solo se ve en esta sesión, y el otro alumno no tendría forma de evitarlo);
   - Windows está instalando actualizaciones
     (apagar ahí sí puede dejar el equipo inservible).

================================================================================
#>

[CmdletBinding(DefaultParameterSetName = 'Monitor')]
param(
    # Deja el equipo configurado: tarea al iniciar sesión y página de inicio del navegador.
    [Parameter(ParameterSetName = 'Instalar')]
    [switch]$Instalar,

    # Deshace todo lo que hizo -Instalar y detiene el vigilante en marcha.
    [Parameter(ParameterSetName = 'Desinstalar')]
    [switch]$Desinstalar,

    # Ejecuta el vigilante en primer plano. NUNCA apaga: solo avisa de lo que haría.
    [Parameter(ParameterSetName = 'Probar')]
    [switch]$Probar,

    # Uso interno: lo lanza la segunda tarea programada en los equipos instalados
    # con -BloquearAdministradores. No hace falta ejecutarlo a mano.
    [Parameter(ParameterSetName = 'Guardia')]
    [switch]$Guardia,

    # Uso interno: marca la copia que ya corre sin consola. El script se relanza
    # a sí mismo con esto para que no quede ninguna ventana que el alumno pueda
    # cerrar. No hace falta escribirlo a mano.
    [switch]$SinVentana,

    # Dirección del sistema. Se indica al instalar con -Url; ésta es de ejemplo.
    [ValidateNotNullOrEmpty()]
    [ValidatePattern('^https?://')]
    [string]$Url = 'http://labgate.example',

    # Minutos sin teclado ni ratón antes de empezar la cuenta regresiva.
    [ValidateRange(2, 480)]
    [int]$MinutosInactividad = 30,

    # Segundos que dura el aviso en pantalla antes de apagar.
    [ValidateRange(15, 600)]
    [int]$SegundosAviso = 60,

    # Franja horaria en la que el equipo NUNCA se apaga solo, por ejemplo "07:00-14:00".
    # Admite que cruce la medianoche ("22:00-06:00"). Vacío = sin franja protegida.
    [ValidatePattern('^$|^\d{1,2}:\d{2}-\d{1,2}:\d{2}$')]
    [string]$NoApagarEntre = '',

    # Instala solo la apertura del navegador, sin apagado automático.
    [switch]$SinApagado,

    # --- Bloqueo hasta registrarse ---------------------------------------
    # Número del laboratorio en el sistema (el id del centro de cómputo) y
    # número de esta máquina dentro de él. Si se indican los dos, el equipo
    # queda bloqueado con el sistema en pantalla completa hasta que el alumno
    # registre su asistencia o su entrada de uso libre en ESTA máquina.
    # Sin ellos, el script se comporta como antes y no bloquea nada.
    [ValidateRange(0, 9999)]
    [int]$Laboratorio = 0,

    [ValidateRange(0, 9999)]
    [int]$Maquina = 0,

    # Aplica el bloqueo también a las cuentas de administrador. Hace falta en los
    # equipos donde los alumnos entran con una cuenta de administrador; sin esto
    # el bloqueo nunca se activaría para ellos.
    [switch]$BloquearAdministradores
)

$ErrorActionPreference = 'Stop'

# Nombres y rutas fijas, para que instalar y desinstalar coincidan siempre.
$NombreTarea     = 'LabGate - Equipo de centro de computo'
$CarpetaDatos    = Join-Path $env:ProgramData 'LabGate'
$ArchivoBitacora = Join-Path $CarpetaDatos 'centro-computo.log'

# El vigilante es UNO POR SESIÓN, no por equipo: la inactividad que mide Windows
# es la de la sesión que pregunta. Por eso el mutex es 'Local\' (ámbito de sesión)
# y no 'Global\' (ámbito de máquina), que dejaría a las demás sesiones sin vigilante.
$NombreMutex = 'Local\LabGate-VigilanteCentroComputo'

# En las cuentas de administrador, el Administrador de tareas se abre con permisos
# elevados y el vigilante, que corre sin ellos, no puede cerrarlo. Para eso está
# la guardia: una segunda tarea, elevada, que sólo cierra el Administrador de
# tareas mientras exista esta señal, y la señal sólo existe con el equipo bloqueado.
$NombreTareaGuardia = 'LabGate - Guardia del bloqueo'
$NombreSenal        = 'Local\LabGate-EquipoBloqueado'
$NombreMutexGuardia = 'Local\LabGate-GuardiaCentroComputo'

# Valor que devuelve la medición de inactividad cuando no se pudo consultar.
# Se distingue de 0 ("se acaba de usar") a propósito: confundirlos provocaba
# cancelaciones y apagados falsos.
$INACTIVIDAD_DESCONOCIDA = -1

function Ocultar-Consola {
    <#
        Esconde la ventana negra de PowerShell.

        Aunque la tarea se lanza con -WindowStyle Hidden, la ventana existe y en
        algunos equipos llega a verse. Si el alumno la cierra, se lleva por
        delante al vigilante y el equipo se queda sin bloqueo. Sin ventana no hay
        nada que cerrar; para eso está también el reinicio automático de la tarea.

        En modo -Probar no se esconde: ahí la ventana es justo lo que se quiere ver.
    #>

    try {
        if (-not ('LabGate.Consola' -as [type])) {
            Add-Type -Namespace LabGate -Name Consola -MemberDefinition @'
                [DllImport("kernel32.dll")] public static extern IntPtr GetConsoleWindow();
                [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr ventana, int modo);
'@ -ErrorAction Stop
        }

        $ventana = [LabGate.Consola]::GetConsoleWindow()
        if ($ventana -ne [IntPtr]::Zero) {
            [void][LabGate.Consola]::ShowWindow($ventana, 0)   # SW_HIDE
        }
    } catch {
        # Si no se pudo esconder, el vigilante sigue trabajando igual.
    }
}

# Se esconde aquí, antes de cualquier otra cosa. Más abajo el script prepara la
# anulación de teclas, que tarda unos segundos en compilar; si se esperara a
# terminar eso, la ventana alcanzaría a verse en la barra de tareas y el alumno
# podría cerrarla. Al instalar o desinstalar NO se esconde: ahí la ventana es la
# del instalador y hay que poder leerla.
if (-not ($Instalar -or $Desinstalar -or $Probar)) { Ocultar-Consola }

# ==============================================================================
#  BITÁCORA
# ==============================================================================

function Escribir-Bitacora {
    param(
        [Parameter(Mandatory)][string]$Mensaje,
        [ValidateSet('INFO', 'AVISO', 'ERROR')][string]$Nivel = 'INFO'
    )

    $linea = '{0}  [{1}]  {2}  (sesion {3}, {4})' -f `
        (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Nivel, $Mensaje, $PID, $env:USERNAME

    if ($Probar) {
        switch ($Nivel) {
            'ERROR' { Write-Host $linea -ForegroundColor Red }
            'AVISO' { Write-Host $linea -ForegroundColor Yellow }
            default { Write-Host $linea -ForegroundColor Gray }
        }
    }

    try {
        if (-not (Test-Path $CarpetaDatos)) {
            New-Item -ItemType Directory -Path $CarpetaDatos -Force | Out-Null
        }

        if (Test-Path $ArchivoBitacora) {
            if ((Get-Item $ArchivoBitacora).Length -gt 1MB) {
                Move-Item $ArchivoBitacora "$ArchivoBitacora.anterior" -Force
            }
        }

        Add-Content -Path $ArchivoBitacora -Value $linea -Encoding UTF8 -ErrorAction Stop
    } catch {
        # Si la bitácora no se puede escribir, el trabajo principal debe continuar.
        # Es lo que pasaría en una cuenta de alumno si la instalación no le hubiera
        # dado permiso sobre el archivo; Instalar-EnEsteEquipo se encarga de eso.
    }
}

# ==============================================================================
#  ARRANQUE SIN VENTANA
# ==============================================================================

function Hay-OtraCopiaEnMarcha {
    param([Parameter(Mandatory)][string]$Nombre)

    # El vigilante y la guardia se apuntan con un mutex mientras trabajan.
    try {
        $mutex = $null
        if ([System.Threading.Mutex]::TryOpenExisting($Nombre, [ref]$mutex)) {
            $mutex.Dispose()
            return $true
        }
    } catch { }

    return $false
}

function Relanzar-SinVentana {
    <#
        Vuelve a lanzarse a sí mismo en un proceso SIN consola y se retira.
        Devuelve $true si lo consiguió (y entonces esta copia debe terminar).

        Esconder la ventana no basta: en los equipos donde la consola la dibuja
        el Terminal de Windows, la ventana se queda en la barra de tareas y el
        alumno puede cerrarla. Creando el proceso sin consola no hay ventana que
        esconder ni que cerrar.

        La tarea programada relanza esto cada dos minutos por si alguien mató al
        vigilante; si ya hay uno trabajando, esta copia se retira en silencio.
    #>

    $nombreMutex = if ($Guardia) { $NombreMutexGuardia } else { $NombreMutex }
    if (Hay-OtraCopiaEnMarcha -Nombre $nombreMutex) { return $true }

    $argumentos = @(
        '-NoProfile'
        '-ExecutionPolicy Bypass'
        "-File `"$PSCommandPath`""
        '-SinVentana'
    )

    if ($Guardia) {
        $argumentos += '-Guardia'
    } else {
        $argumentos += "-Url `"$Url`""
        $argumentos += "-MinutosInactividad $MinutosInactividad"
        $argumentos += "-SegundosAviso $SegundosAviso"
        if ($NoApagarEntre) { $argumentos += "-NoApagarEntre `"$NoApagarEntre`"" }
        if ($SinApagado)    { $argumentos += '-SinApagado' }
        if ($Laboratorio -gt 0 -and $Maquina -gt 0) {
            $argumentos += "-Laboratorio $Laboratorio"
            $argumentos += "-Maquina $Maquina"
            if ($BloquearAdministradores) { $argumentos += '-BloquearAdministradores' }
        }
    }

    try {
        $arranque = New-Object System.Diagnostics.ProcessStartInfo
        $arranque.FileName        = Join-Path $PSHOME 'powershell.exe'
        $arranque.Arguments       = $argumentos -join ' '
        $arranque.UseShellExecute = $false   # hace falta para que valga CreateNoWindow
        $arranque.CreateNoWindow  = $true    # el proceso nuevo no tiene consola
        $arranque.WindowStyle     = [System.Diagnostics.ProcessWindowStyle]::Hidden

        [void][System.Diagnostics.Process]::Start($arranque)
        return $true
    } catch {
        # Si no se pudo, este mismo proceso sigue adelante: más vale un vigilante
        # con ventana que ningún vigilante.
        Escribir-Bitacora "No se pudo arrancar sin ventana ($($_.Exception.Message)); se continua en esta copia." 'AVISO'
        return $false
    }
}

# Al instalar o desinstalar hay que ver la ventana; al probar, también.
if (-not ($Instalar -or $Desinstalar -or $Probar -or $SinVentana)) {
    if (Relanzar-SinVentana) { return }
}

function Permitir-EscrituraDeAlumnos {
    <#
        Da permiso de escritura al grupo Usuarios sobre la carpeta de datos.

        Hace falta porque la instalación corre como administrador: sin esto, el
        archivo pertenece al administrador y las sesiones de los alumnos no pueden
        añadir líneas, de modo que justo las que interesan quedan sin registrar.
    #>
    try {
        if (-not (Test-Path $CarpetaDatos)) {
            New-Item -ItemType Directory -Path $CarpetaDatos -Force | Out-Null
        }

        $acl = Get-Acl $CarpetaDatos
        $usuarios = New-Object System.Security.Principal.SecurityIdentifier('S-1-5-32-545')
        $regla = New-Object System.Security.AccessControl.FileSystemAccessRule(
            $usuarios,
            'Modify',
            'ContainerInherit, ObjectInherit',
            'None',
            'Allow'
        )
        $acl.AddAccessRule($regla)
        Set-Acl -Path $CarpetaDatos -AclObject $acl

        # El archivo puede existir ya de una instalación anterior.
        if (Test-Path $ArchivoBitacora) {
            $aclArchivo = Get-Acl $ArchivoBitacora
            $reglaArchivo = New-Object System.Security.AccessControl.FileSystemAccessRule(
                $usuarios, 'Modify', 'Allow'
            )
            $aclArchivo.AddAccessRule($reglaArchivo)
            Set-Acl -Path $ArchivoBitacora -AclObject $aclArchivo
        }

        return $true
    } catch {
        Write-Host "  Aviso: no se pudieron ajustar los permisos de la bitacora." -ForegroundColor Yellow
        return $false
    }
}

# ==============================================================================
#  TIEMPO SIN ACTIVIDAD
# ==============================================================================

# Windows lleva la cuenta de cuándo se movió el ratón o se tocó el teclado por
# última vez. Se consulta con GetLastInputInfo, de user32.dll.
#
# Importante: el dato es POR SESIÓN, no por equipo. Por eso la tarea se registra
# para ejecutarse al iniciar sesión, dentro de la sesión del usuario, y no como
# servicio del sistema.
#
# Nota: no se le pasa -UsingNamespace System.Runtime.InteropServices. Add-Type ya
# incluye ese espacio de nombres y repetirlo aborta la compilación con
# "Warning as Error: the using directive appeared previously".
try {
    # Si el script se vuelve a ejecutar en la misma ventana (al probarlo), el tipo
    # ya existe y cargarlo otra vez daría error: se reutiliza.
    if (-not ('LabGate.Inactividad' -as [type])) {
    Add-Type -Namespace LabGate -Name Inactividad -MemberDefinition @'
    [StructLayout(LayoutKind.Sequential)]
    private struct LASTINPUTINFO {
        public uint cbSize;
        public uint dwTime;
    }

    [DllImport("user32.dll")]
    private static extern bool GetLastInputInfo(ref LASTINPUTINFO plii);

    /// <summary>Segundos desde la última tecla o movimiento de ratón. -1 si no se pudo medir.</summary>
    public static double Segundos() {
        LASTINPUTINFO info = new LASTINPUTINFO();
        info.cbSize = (uint)Marshal.SizeOf(info);

        if (!GetLastInputInfo(ref info)) {
            return -1;
        }

        // La resta va en enteros sin signo de 32 bits a propósito: así sigue siendo
        // correcta cuando el contador del sistema da la vuelta (~49 días).
        unchecked {
            uint ahora = (uint)Environment.TickCount;
            uint transcurrido = ahora - info.dwTime;
            return transcurrido / 1000.0;
        }
    }
'@ -ErrorAction Stop
    }
    $MedicionDisponible = $true
} catch {
    # Sin medición no hay apagado posible, pero el navegador sí debe abrirse.
    $MedicionDisponible = $false
}

function Obtener-SegundosInactivo {
    <#
        Devuelve los segundos sin actividad, o $INACTIVIDAD_DESCONOCIDA si no se
        pudo medir. Quien llama debe tratar ese caso como "no apagar".
    #>
    if (-not $MedicionDisponible) { return $INACTIVIDAD_DESCONOCIDA }

    try {
        $valor = [LabGate.Inactividad]::Segundos()
        if ($valor -lt 0) { return $INACTIVIDAD_DESCONOCIDA }
        return $valor
    } catch {
        return $INACTIVIDAD_DESCONOCIDA
    }
}

# ==============================================================================
#  CONDICIONES QUE IMPIDEN APAGAR
# ==============================================================================

function Hay-OtraSesionAbierta {
    <#
        Con el cambio rápido de usuario puede haber varias sesiones a la vez. Como
        la inactividad que se mide es solo la de ESTA sesión, apagar dejaría fuera
        al alumno que sí está trabajando en la otra. Ante la duda, no se apaga.

        Se cuentan los procesos explorer.exe por usuario, que es lo que hay en toda
        sesión de escritorio. No se usa quser.exe porque no existe en las ediciones
        Home de Windows, y ahí la comprobación quedaría siempre en blanco.
    #>
    try {
        $explorers = Get-CimInstance Win32_Process -Filter "Name = 'explorer.exe'" -ErrorAction Stop
        if (-not $explorers) { return $false }

        $duenos = @{}
        foreach ($p in $explorers) {
            try {
                $info = Invoke-CimMethod -InputObject $p -MethodName GetOwner -ErrorAction Stop
                if ($info.User) { $duenos["$($info.Domain)\$($info.User)"] = $true }
            } catch { }
        }

        return ($duenos.Count -gt 1)
    } catch {
        # Si no se puede averiguar, se asume que no hay otras sesiones: bloquear
        # siempre dejaría los equipos encendidos para siempre.
        return $false
    }
}

function Windows-EstaActualizando {
    <#
        Apagar en medio de una actualización puede dejar el equipo inservible.
    #>
    try {
        $procesos = @('TiWorker', 'TrustedInstaller', 'WindowsUpdateBox', 'wuauclt')
        foreach ($p in $procesos) {
            $encontrado = Get-Process -Name $p -ErrorAction SilentlyContinue
            if ($encontrado) {
                # TiWorker aparece a menudo en reposo; solo cuenta si consume CPU.
                if ($p -eq 'TiWorker') {
                    $cpu1 = ($encontrado | Measure-Object -Property CPU -Sum).Sum
                    Start-Sleep -Seconds 3
                    $otra = Get-Process -Name $p -ErrorAction SilentlyContinue
                    if (-not $otra) { continue }
                    $cpu2 = ($otra | Measure-Object -Property CPU -Sum).Sum
                    if (($cpu2 - $cpu1) -lt 0.5) { continue }
                }
                return $true
            }
        }
        return $false
    } catch {
        return $false
    }
}

function Dentro-DeFranjaProtegida {
    param([string]$Franja)

    <#
        $Franja tiene el formato "HH:MM-HH:MM". Devuelve $true si la hora actual
        cae dentro, contemplando que la franja cruce la medianoche
        (por ejemplo "22:00-06:00").
    #>

    if ([string]::IsNullOrWhiteSpace($Franja)) { return $false }

    try {
        $partes = $Franja.Split('-')
        if ($partes.Count -ne 2) { return $false }

        $ahora  = (Get-Date).TimeOfDay
        $inicio = [TimeSpan]::Parse($partes[0].Trim())
        $fin    = [TimeSpan]::Parse($partes[1].Trim())

        if ($inicio -le $fin) {
            # Franja normal dentro del mismo día: 07:00-14:00
            return ($ahora -ge $inicio -and $ahora -lt $fin)
        }

        # Franja que cruza la medianoche: 22:00-06:00
        return ($ahora -ge $inicio -or $ahora -lt $fin)
    } catch {
        return $false
    }
}

# ==============================================================================
#  NAVEGADOR
# ==============================================================================

function Buscar-Navegador {
    <#
        Ruta del navegador a usar. Se prefiere Chrome, que es lo habitual en los
        laboratorios, y se cae a Edge, que viene con Windows.

        Además de las rutas típicas se consulta el registro (App Paths), que es
        donde los instaladores dejan la ubicación real.
    #>

    $candidatos = @(
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe"
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
        "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe"
    )

    foreach ($ruta in $candidatos) {
        if ($ruta -and (Test-Path -LiteralPath $ruta)) { return $ruta }
    }

    # Segundo intento: donde los propios instaladores registran el ejecutable.
    foreach ($exe in 'chrome.exe', 'msedge.exe', 'firefox.exe') {
        foreach ($raiz in 'HKLM:', 'HKCU:') {
            $clave = "$raiz\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\$exe"
            try {
                if (Test-Path $clave) {
                    $ruta = (Get-ItemProperty -Path $clave -Name '(default)' -ErrorAction Stop).'(default)'
                    if ($ruta -and (Test-Path -LiteralPath $ruta)) { return $ruta }
                }
            } catch { }
        }
    }

    return $null
}

function Abrir-Sistema {
    param(
        [Parameter(Mandatory)][string]$Direccion,

        # Abre la ventana con el perfil del sistema, el mismo de la pantalla de
        # bloqueo, para que el alumno siga con la sesión que abrió al registrarse
        # en lugar de tener que entrar otra vez.
        [switch]$ConPerfilDelSistema
    )

    $navegador = Buscar-Navegador

    # No se abre el sistema directamente sino la página de espera: al encender, la
    # red tarda en conectarse y el navegador se quedaba en "sin conexión" hasta
    # que alguien recargaba a mano. La espera reintenta sola y entra en cuanto
    # hay red; si ya la hay, pasa al sistema al instante.
    $inicio = Preparar-PaginaEspera -Direccion $Direccion

    try {
        if ($navegador) {
            $hoja = Split-Path $navegador -Leaf
            if ($hoja -eq 'chrome.exe' -or $hoja -eq 'msedge.exe') {
                # --no-first-run y --no-default-browser-check evitan que el asistente
                # de bienvenida tape el sistema en los perfiles nuevos.
                $argumentos = @('--no-first-run', '--no-default-browser-check')
                if ($ConPerfilDelSistema) {
                    $argumentos += "--user-data-dir=`"$PerfilSistema`""
                }
                $argumentos += "`"$inicio`""
                Start-Process -FilePath $navegador -ArgumentList $argumentos
            } else {
                Start-Process -FilePath $navegador -ArgumentList "`"$inicio`""
            }
            Escribir-Bitacora "Navegador abierto: $hoja -> $Direccion"
        } else {
            Start-Process $inicio
            Escribir-Bitacora "Abierto con el navegador predeterminado -> $Direccion" 'AVISO'
        }
    } catch {
        Escribir-Bitacora "No se pudo abrir el navegador: $($_.Exception.Message)" 'ERROR'
    }
}

function Configurar-PaginaDeInicio {
    param([Parameter(Mandatory)][string]$Direccion)

    <#
        Deja el sistema como página de arranque de Chrome y Edge.

        Complementa a la apertura automática: si el alumno cierra el navegador y lo
        vuelve a abrir, o pulsa el botón de inicio, regresa al sistema.

        Se escribe en HKLM (política del equipo), así que aplica a todos los usuarios
        y requiere permisos de administrador. Firefox no lee estas claves; para él
        basta con la apertura automática del script.
    #>

    $politicas = @(
        'HKLM:\SOFTWARE\Policies\Google\Chrome'
        'HKLM:\SOFTWARE\Policies\Microsoft\Edge'
    )

    foreach ($clave in $politicas) {
        try {
            if (-not (Test-Path $clave)) {
                New-Item -Path $clave -Force | Out-Null
            }

            # Al arrancar, abrir la lista de páginas (4 = "abrir una lista de URL").
            New-ItemProperty -Path $clave -Name 'RestoreOnStartup' -Value 4 -PropertyType DWord -Force | Out-Null

            # Botón de inicio apuntando al sistema.
            #
            # HomepageIsNewTabPage = 0 no es opcional: sin ella, Chrome y Edge dejan
            # que decida el perfil del usuario, y de fábrica ese valor es "usar la
            # página de Nueva pestaña". Entonces HomepageLocation se ignora y el botón
            # de inicio abre una pestaña en blanco en vez del sistema.
            New-ItemProperty -Path $clave -Name 'HomepageLocation' -Value $Direccion -PropertyType String -Force | Out-Null
            New-ItemProperty -Path $clave -Name 'HomepageIsNewTabPage' -Value 0 -PropertyType DWord -Force | Out-Null
            New-ItemProperty -Path $clave -Name 'ShowHomeButton' -Value 1 -PropertyType DWord -Force | Out-Null

            # La lista de páginas de arranque va en una subclave con valores
            # numerados: '1', '2', ... Así esperan estas políticas las listas.
            $listaInicio = Join-Path $clave 'RestoreOnStartupURLs'
            if (-not (Test-Path $listaInicio)) {
                New-Item -Path $listaInicio -Force | Out-Null
            }
            New-ItemProperty -Path $listaInicio -Name '1' -Value $Direccion -PropertyType String -Force | Out-Null

            Escribir-Bitacora "Pagina de inicio configurada en $clave"
        } catch {
            Escribir-Bitacora "No se pudo configurar $clave : $($_.Exception.Message)" 'AVISO'
        }
    }
}

function Quitar-PaginaDeInicio {
    $politicas = @(
        'HKLM:\SOFTWARE\Policies\Google\Chrome'
        'HKLM:\SOFTWARE\Policies\Microsoft\Edge'
    )

    foreach ($clave in $politicas) {
        try {
            if (Test-Path $clave) {
                $valores = 'RestoreOnStartup', 'HomepageLocation', 'HomepageIsNewTabPage', 'ShowHomeButton'
                foreach ($valor in $valores) {
                    Remove-ItemProperty -Path $clave -Name $valor -ErrorAction SilentlyContinue
                }
                $listaInicio = Join-Path $clave 'RestoreOnStartupURLs'
                if (Test-Path $listaInicio) {
                    Remove-Item -Path $listaInicio -Recurse -Force -ErrorAction SilentlyContinue
                }
                Escribir-Bitacora "Pagina de inicio retirada de $clave"
            }
        } catch {
            Escribir-Bitacora "No se pudo limpiar $clave : $($_.Exception.Message)" 'AVISO'
        }
    }
}

# ==============================================================================
#  BLOQUEO HASTA REGISTRARSE
# ==============================================================================
#
#  Mientras el alumno no registre su asistencia o su entrada de uso libre en
#  esta máquina, el equipo muestra el sistema a pantalla completa y no deja
#  hacer nada más. En cuanto el registro aparece, se libera. Y cuando la sesión
#  termina (cierra su uso libre, acaba la clase o el encargado la cierra desde el
#  Monitor), avisa y vuelve a bloquearse.
#
#  Mientras está bloqueado:
#    - Se anulan la tecla Windows, Alt+Tab, Alt+F4, Alt+Espacio, Ctrl+Esc,
#      Ctrl+Shift+Esc, el clic derecho y los atajos del navegador que abren
#      ventanas del sistema (abrir archivo, guardar, imprimir, descargas...).
#    - Si se abre el Administrador de tareas desde Ctrl+Alt+Supr, se cierra al
#      instante.
#    - Si la pantalla del sistema se minimiza o pierde el primer plano, vuelve.
#    - Se apagan los atajos de accesibilidad (Shift cinco veces y similares),
#      que son una salida clásica hacia el Panel de control.
#
#  Cómo sale el personal del Centro de Cómputo:
#    1. Con una cuenta de ADMINISTRADOR de Windows. Si en ese equipo los alumnos
#       también entran como administradores, se instala con
#       -BloquearAdministradores y esta vía deja de existir.
#    2. Asignando la máquina desde el Monitor de Laboratorios del sistema.
#
#  Lo único que no se puede evitar es Ctrl+Alt+Supr: Windows se reserva esa
#  combinación. Desde ahí se puede cerrar sesión o bloquear la pantalla, pero no
#  usar el equipo.

# Perfil del navegador exclusivo del sistema en este usuario de Windows.
#
# Lo usan la pantalla de bloqueo Y la ventana que queda abierta al desbloquear:
# así la sesión que el alumno abrió para registrarse sigue viva, en vez de pedirle
# que vuelva a entrar. Se borra al empezar cada sesión de Windows y cada vez que
# el equipo se vuelve a bloquear, para que nadie herede la sesión del anterior.
$PerfilSistema = Join-Path $env:LOCALAPPDATA 'LabGate\PerfilSistema'

# Página local que se muestra mientras la red todavía no conecta.
$PaginaEspera = Join-Path $env:LOCALAPPDATA 'LabGate\espera.html'

# Al encender sin red: a qué hora se libera el equipo solo. La pantalla de
# bloqueo lo enseña en cuenta regresiva. Se borra en cuanto el servidor contesta.
$script:liberarSinRedEn = $null

# Al iniciar sesión se avisa al servidor (reiniciar=1) para que cierre lo que dejó
# abierto la sesión anterior. Si en ese momento aún no había red, el aviso queda
# pendiente y va en la primera consulta que sí conteste.
$script:reinicioPendiente = $false

# Anulación de teclas, clic derecho y ventana al frente. Va en C# porque necesita
# un gancho de teclado de bajo nivel con su propio bucle de mensajes, cosa que
# PowerShell por sí solo no puede sostener mientras hace otras cosas.
try {
    if (-not ('LabGate.Candado' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
using System.Threading;

namespace LabGate
{
    public static class Candado
    {
        private const int WH_KEYBOARD_LL = 13;
        private const int WH_MOUSE_LL = 14;
        private const int WM_RBUTTONDOWN = 0x0204;
        private const int WM_RBUTTONUP = 0x0205;
        private const uint WM_QUIT = 0x0012;
        private const uint LLKHF_ALTDOWN = 0x20;

        [StructLayout(LayoutKind.Sequential)]
        private struct KBDLLHOOKSTRUCT { public uint vkCode; public uint scanCode; public uint flags; public uint time; public IntPtr dwExtraInfo; }

        [StructLayout(LayoutKind.Sequential)]
        private struct MSG { public IntPtr hwnd; public uint message; public IntPtr wParam; public IntPtr lParam; public uint time; public int x; public int y; }

        [StructLayout(LayoutKind.Sequential)]
        private struct ACCESO { public uint cbSize; public uint dwFlags; }

        [StructLayout(LayoutKind.Sequential)]
        private struct FILTRO { public uint cbSize; public uint dwFlags; public uint iWaitMSec; public uint iDelayMSec; public uint iRepeatMSec; public uint iBounceMSec; }

        private delegate IntPtr ProcGancho(int nCode, IntPtr wParam, IntPtr lParam);

        [DllImport("user32.dll", SetLastError = true)] private static extern IntPtr SetWindowsHookEx(int idHook, ProcGancho lpfn, IntPtr hMod, uint dwThreadId);
        [DllImport("user32.dll")] private static extern bool UnhookWindowsHookEx(IntPtr hhk);
        [DllImport("user32.dll")] private static extern IntPtr CallNextHookEx(IntPtr hhk, int nCode, IntPtr wParam, IntPtr lParam);
        [DllImport("user32.dll")] private static extern int GetMessage(out MSG msg, IntPtr hWnd, uint min, uint max);
        [DllImport("user32.dll")] private static extern bool PostThreadMessage(uint idThread, uint msg, IntPtr wParam, IntPtr lParam);
        [DllImport("user32.dll")] private static extern short GetAsyncKeyState(int vKey);
        [DllImport("kernel32.dll")] private static extern uint GetCurrentThreadId();
        [DllImport("kernel32.dll", CharSet = CharSet.Unicode)] private static extern IntPtr GetModuleHandle(string nombre);

        [DllImport("user32.dll")] private static extern bool IsIconic(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);
        [DllImport("user32.dll")] private static extern bool SetForegroundWindow(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern IntPtr GetForegroundWindow();
        [DllImport("user32.dll")] private static extern bool BringWindowToTop(IntPtr hWnd);
        [DllImport("user32.dll")] private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint pid);
        [DllImport("user32.dll")] private static extern bool AttachThreadInput(uint idAttach, uint idAttachTo, bool fAttach);
        [DllImport("user32.dll")] private static extern bool SetWindowPos(IntPtr hWnd, IntPtr despuesDe, int x, int y, int cx, int cy, uint flags);

        [DllImport("user32.dll", EntryPoint = "SystemParametersInfo")] private static extern bool SpiAcceso(uint accion, uint param, ref ACCESO valor, uint winIni);
        [DllImport("user32.dll", EntryPoint = "SystemParametersInfo")] private static extern bool SpiFiltro(uint accion, uint param, ref FILTRO valor, uint winIni);

        // Referencias vivas: si el recolector de basura se llevara el delegado,
        // Windows llamaría a una dirección que ya no existe.
        private static readonly ProcGancho procTeclado = ProcesarTeclado;
        private static readonly ProcGancho procRaton = ProcesarRaton;

        private static Thread hilo;
        private static uint idHilo;
        private static volatile bool activo;

        private static ACCESO pegajosasAntes;
        private static ACCESO alternanciaAntes;
        private static FILTRO filtroAntes;
        private static bool accesibilidadGuardada;

        public static bool Activo { get { return activo; } }

        public static bool Activar()
        {
            if (hilo != null) { activo = true; return true; }

            bool instalado = false;
            ManualResetEvent listo = new ManualResetEvent(false);

            hilo = new Thread(delegate ()
            {
                idHilo = GetCurrentThreadId();
                IntPtr modulo = GetModuleHandle(null);
                IntPtr gTeclado = SetWindowsHookEx(WH_KEYBOARD_LL, procTeclado, modulo, 0);
                IntPtr gRaton = SetWindowsHookEx(WH_MOUSE_LL, procRaton, modulo, 0);
                instalado = gTeclado != IntPtr.Zero;
                listo.Set();

                // Un gancho de bajo nivel sólo funciona si su hilo atiende mensajes.
                MSG m;
                while (GetMessage(out m, IntPtr.Zero, 0, 0) > 0) { }

                if (gTeclado != IntPtr.Zero) UnhookWindowsHookEx(gTeclado);
                if (gRaton != IntPtr.Zero) UnhookWindowsHookEx(gRaton);
            });
            hilo.IsBackground = true;
            hilo.Start();
            listo.WaitOne(3000);

            if (!instalado) { Desactivar(); return false; }

            activo = true;
            ApagarAtajosAccesibilidad();
            return true;
        }

        public static void Desactivar()
        {
            activo = false;
            RestaurarAtajosAccesibilidad();

            if (hilo != null)
            {
                PostThreadMessage(idHilo, WM_QUIT, IntPtr.Zero, IntPtr.Zero);
                hilo.Join(2000);
                hilo = null;
            }
        }

        /// <summary>
        /// Decide si una tecla se anula. Es pública y sin efectos para poder
        /// probarla sin instalar el gancho.
        /// </summary>
        public static bool EsAtajoProhibido(int vk, bool alt, bool ctrl, bool shift)
        {
            // Tecla Windows (izquierda y derecha) y tecla de menú contextual.
            if (vk == 0x5B || vk == 0x5C || vk == 0x5D) return true;

            // Alt + Tab / Esc / F4 / Espacio (este último abre el menú de la ventana).
            if (alt && (vk == 0x09 || vk == 0x1B || vk == 0x73 || vk == 0x20)) return true;

            // Ctrl + Esc (menú Inicio) y Ctrl + Shift + Esc (Administrador de tareas).
            if (ctrl && vk == 0x1B) return true;

            // Shift + F10 abre el menú contextual; F12, las herramientas de desarrollo.
            if (shift && vk == 0x79) return true;
            if (vk == 0x7B) return true;

            // Atajos del navegador que abren ventanas o cuadros del sistema. Con Alt
            // también pulsado no se tocan: en los teclados en español Ctrl+Alt es
            // AltGr, que hace falta para escribir @, #, €...
            if (ctrl && !alt)
            {
                switch (vk)
                {
                    case 0x42: // B
                    case 0x44: // D  marcador
                    case 0x48: // H  historial
                    case 0x4A: // J  descargas
                    case 0x4E: // N  ventana nueva / incógnito
                    case 0x4F: // O  abrir archivo
                    case 0x50: // P  imprimir
                    case 0x53: // S  guardar como
                    case 0x54: // T  pestaña nueva
                    case 0x55: // U  código fuente
                    case 0x57: // W  cerrar pestaña
                        return true;
                }
                // Ctrl + Shift + I / C (herramientas de desarrollo) y Supr (borrar datos).
                if (shift && (vk == 0x49 || vk == 0x43 || vk == 0x2E)) return true;
            }

            return false;
        }

        /// <summary>Restaura, pone encima de todo y trae al frente la ventana.</summary>
        public static void FijarAlFrente(IntPtr ventana)
        {
            if (ventana == IntPtr.Zero) return;

            if (IsIconic(ventana)) ShowWindow(ventana, 9);                       // SW_RESTORE
            SetWindowPos(ventana, new IntPtr(-1), 0, 0, 0, 0, 0x0001 | 0x0002);  // HWND_TOPMOST

            IntPtr actual = GetForegroundWindow();
            if (actual == ventana) return;

            // Windows no deja que un programa en segundo plano robe el frente; unir
            // momentáneamente la entrada con la del programa activo lo permite.
            uint pid;
            uint hiloActual = GetWindowThreadProcessId(actual, out pid);
            uint hiloPropio = GetCurrentThreadId();
            bool unido = hiloActual != 0 && hiloActual != hiloPropio && AttachThreadInput(hiloPropio, hiloActual, true);

            BringWindowToTop(ventana);
            SetForegroundWindow(ventana);

            if (unido) AttachThreadInput(hiloPropio, hiloActual, false);
        }

        private static bool Pulsada(int vk) { return (GetAsyncKeyState(vk) & 0x8000) != 0; }

        private static IntPtr ProcesarTeclado(int nCode, IntPtr wParam, IntPtr lParam)
        {
            if (nCode >= 0 && activo)
            {
                KBDLLHOOKSTRUCT k = (KBDLLHOOKSTRUCT)Marshal.PtrToStructure(lParam, typeof(KBDLLHOOKSTRUCT));
                bool alt = (k.flags & LLKHF_ALTDOWN) != 0;
                // Se anulan tanto el pulsar como el soltar: si pasara sólo el soltar
                // de la tecla Windows, el menú Inicio se abriría igual.
                if (EsAtajoProhibido((int)k.vkCode, alt, Pulsada(0x11), Pulsada(0x10)))
                    return (IntPtr)1;
            }
            return CallNextHookEx(IntPtr.Zero, nCode, wParam, lParam);
        }

        private static IntPtr ProcesarRaton(int nCode, IntPtr wParam, IntPtr lParam)
        {
            if (nCode >= 0 && activo)
            {
                int mensaje = wParam.ToInt32();
                // El menú del clic derecho ofrece "Guardar como", "Inspeccionar"...
                if (mensaje == WM_RBUTTONDOWN || mensaje == WM_RBUTTONUP) return (IntPtr)1;
            }
            return CallNextHookEx(IntPtr.Zero, nCode, wParam, lParam);
        }

        // Shift cinco veces (teclas especiales), Shift derecho ocho segundos (filtro)
        // y Bloq Num cinco segundos (alternancia) abren cuadros con enlaces al Panel
        // de control. Se apagan sólo sus atajos, y sólo en esta sesión (sin guardar
        // en el perfil); al desbloquear se deja todo como estaba.
        private static void ApagarAtajosAccesibilidad()
        {
            try
            {
                pegajosasAntes.cbSize = (uint)Marshal.SizeOf(typeof(ACCESO));
                alternanciaAntes.cbSize = pegajosasAntes.cbSize;
                filtroAntes.cbSize = (uint)Marshal.SizeOf(typeof(FILTRO));

                SpiAcceso(0x003A, pegajosasAntes.cbSize, ref pegajosasAntes, 0);
                SpiAcceso(0x0034, alternanciaAntes.cbSize, ref alternanciaAntes, 0);
                SpiFiltro(0x0032, filtroAntes.cbSize, ref filtroAntes, 0);
                accesibilidadGuardada = true;

                ACCESO p = pegajosasAntes;
                if ((p.dwFlags & 1u) == 0) { p.dwFlags &= ~4u; SpiAcceso(0x003B, p.cbSize, ref p, 0); }

                ACCESO a = alternanciaAntes;
                if ((a.dwFlags & 1u) == 0) { a.dwFlags &= ~4u; SpiAcceso(0x0035, a.cbSize, ref a, 0); }

                FILTRO f = filtroAntes;
                if ((f.dwFlags & 1u) == 0) { f.dwFlags &= ~4u; SpiFiltro(0x0033, f.cbSize, ref f, 0); }
            }
            catch { }
        }

        private static void RestaurarAtajosAccesibilidad()
        {
            if (!accesibilidadGuardada) return;
            try
            {
                SpiAcceso(0x003B, pegajosasAntes.cbSize, ref pegajosasAntes, 0);
                SpiAcceso(0x0035, alternanciaAntes.cbSize, ref alternanciaAntes, 0);
                SpiFiltro(0x0033, filtroAntes.cbSize, ref filtroAntes, 0);
            }
            catch { }
            accesibilidadGuardada = false;
        }
    }
}
'@ -ErrorAction Stop
    }
    $CandadoDisponible = $true
} catch {
    $CandadoDisponible = $false
}

function Es-Administrador {
    <#
        ¿La cuenta de Windows con la que se entró es administradora?

        Ojo: con el Control de cuentas de usuario activo (lo normal), un
        administrador trabaja con un permiso recortado e IsInRole('Administrator')
        responde que NO aunque la cuenta sí lo sea. Por eso se mira la pertenencia
        al grupo local de Administradores, que no depende de eso.
    #>
    try {
        $identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = New-Object Security.Principal.WindowsPrincipal($identidad)
        if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
            return $true
        }

        try {
            $miembros = Get-LocalGroupMember -SID 'S-1-5-32-544' -ErrorAction Stop
            return [bool]($miembros | Where-Object { $_.SID -and $_.SID.Value -eq $identidad.User.Value })
        } catch {
            # Respaldo para equipos donde Get-LocalGroupMember falla (por ejemplo,
            # cuando el grupo guarda cuentas borradas): se pregunta a net localgroup.
            $grupo = (New-Object Security.Principal.SecurityIdentifier('S-1-5-32-544')).Translate(
                [Security.Principal.NTAccount]).Value.Split('\')[-1]
            $salida = & net localgroup $grupo 2>$null
            $usuario = $env:USERNAME
            return [bool]($salida | Where-Object {
                $linea = $_.Trim()
                $linea -ieq $usuario -or $linea -ilike "*\$usuario"
            })
        }
    } catch {
        return $false
    }
}

function Es-ArranqueDeSesion {
    <#
        ¿El vigilante arranca junto con la sesión de Windows, o es un reinicio con
        el equipo ya en marcha?

        La tarea lo vuelve a lanzar cada dos minutos por si alguien lo cerró. En
        ese caso puede haber un alumno trabajando y no hay que tocarle nada; al
        iniciar sesión, en cambio, el equipo debe partir bloqueado y en blanco.

        Se mira cuándo arrancó el escritorio (explorer.exe) de esta sesión. Si no
        se puede saber, se responde que sí: bloquear de más es preferible a dejar
        el equipo abierto con la sesión del alumno anterior.
    #>

    try {
        $sesion = (Get-Process -Id $PID).SessionId
        $escritorio = @(Get-Process -Name 'explorer' -ErrorAction Stop |
            Where-Object { $_.SessionId -eq $sesion } |
            Sort-Object StartTime) | Select-Object -First 1

        if (-not $escritorio) { return $true }

        return ((Get-Date) - $escritorio.StartTime).TotalMinutes -lt 3
    } catch {
        return $true
    }
}

function Consultar-SiEstaRegistrado {
    param(
        [Parameter(Mandatory)][string]$Direccion,
        [Parameter(Mandatory)][int]$Centro,
        [Parameter(Mandatory)][int]$NumeroMaquina,

        # Cancela el permiso que el personal hubiera dejado abierto en esta
        # máquina. Se usa sólo al iniciar sesión en Windows: si alguien del
        # personal liberó el equipo y se fue sin cerrar sesión en el sistema, el
        # siguiente que encienda la computadora no hereda esa libertad.
        [switch]$Reiniciar
    )

    <#
        Pregunta al sistema si esta máquina tiene una sesión activa.

        Devuelve:
          $true   ocupada: hay que desbloquear (o seguir desbloqueado)
          $false  libre: hay que bloquear (o seguir bloqueado)
          $null   no se pudo consultar (servidor caído o sin red)
    #>

    $consulta = "$Direccion/api/equipo/estado?centro=$Centro&maquina=$NumeroMaquina"
    if ($Reiniciar) { $consulta += '&reiniciar=1' }

    try {
        $respuesta = Invoke-RestMethod -Uri $consulta -TimeoutSec 6 -ErrorAction Stop
        return [bool]$respuesta.ocupada
    } catch {
        return $null
    }
}

function Consultar-Estado {
    param(
        [Parameter(Mandatory)][string]$Direccion,
        [Parameter(Mandatory)][int]$Centro,
        [Parameter(Mandatory)][int]$NumeroMaquina
    )

    <#
        Igual que Consultar-SiEstaRegistrado, pero si el aviso de sesión nueva
        (reiniciar=1) quedó pendiente porque al iniciar sesión no había red, lo
        manda en esta consulta. Sin ese aviso, el Uso Libre que el alumno anterior
        dejó abierto al apagar seguiría liberando el equipo para el siguiente.
    #>

    if ($script:reinicioPendiente) {
        $respuesta = Consultar-SiEstaRegistrado -Direccion $Direccion -Centro $Centro `
                        -NumeroMaquina $NumeroMaquina -Reiniciar
        if ($null -ne $respuesta) { $script:reinicioPendiente = $false }
        return $respuesta
    }

    return (Consultar-SiEstaRegistrado -Direccion $Direccion -Centro $Centro -NumeroMaquina $NumeroMaquina)
}

function Obtener-ProcesosDelSistema {
    # Procesos del navegador que usan el perfil del sistema, y sólo esos: las
    # ventanas que el alumno abra por su cuenta con su propio perfil no se tocan.
    try {
        $patron = [regex]::Escape($PerfilSistema)
        return @(Get-CimInstance Win32_Process -Filter "Name = 'chrome.exe' OR Name = 'msedge.exe'" -ErrorAction Stop |
                 Where-Object { $_.CommandLine -and $_.CommandLine -match $patron })
    } catch {
        return @()
    }
}

function Cerrar-NavegadorDelSistema {
    param([int]$Segundos = 6)

    <#
        Cierra las ventanas del perfil del sistema.

        Primero por las buenas: al cerrar la ventana, el navegador guarda las
        cookies en disco. Si se matara a la fuerza nada más iniciar sesión, la
        cookie de la sesión podría no haberse escrito todavía y el alumno tendría
        que volver a entrar. Lo que no cierre a tiempo, se cierra a la fuerza.
    #>

    foreach ($info in Obtener-ProcesosDelSistema) {
        $p = Get-Process -Id $info.ProcessId -ErrorAction SilentlyContinue
        if ($p -and $p.MainWindowHandle -ne [IntPtr]::Zero) {
            try { [void]$p.CloseMainWindow() } catch { }
        }
    }

    $limite = (Get-Date).AddSeconds($Segundos)
    while ((Get-Date) -lt $limite) {
        if ((Obtener-ProcesosDelSistema).Count -eq 0) { return }
        Start-Sleep -Milliseconds 300
    }

    foreach ($info in Obtener-ProcesosDelSistema) {
        try { Stop-Process -Id $info.ProcessId -Force -ErrorAction Stop } catch { }
    }
    for ($i = 0; $i -lt 10 -and (Obtener-ProcesosDelSistema).Count -gt 0; $i++) {
        Start-Sleep -Milliseconds 300
    }
}

function Limpiar-PerfilDelSistema {
    # Deja el perfil del sistema en blanco: sin sesión abierta, sin historial.
    Cerrar-NavegadorDelSistema -Segundos 2

    if (-not (Test-Path -LiteralPath $PerfilSistema)) { return }

    try {
        Remove-Item -LiteralPath $PerfilSistema -Recurse -Force -ErrorAction Stop
    } catch {
        # Si algún archivo quedó tomado, al menos se borran las cookies, que son
        # las que guardan la sesión del alumno anterior.
        Get-ChildItem -LiteralPath $PerfilSistema -Recurse -Filter 'Cookies*' -ErrorAction SilentlyContinue |
            Remove-Item -Force -ErrorAction SilentlyContinue
    }
}

function Preparar-PaginaEspera {
    param([Parameter(Mandatory)][string]$Direccion)

    <#
        Escribe la página local que se abre al principio y devuelve su dirección.

        Al encender, la red tarda en conectarse; abrir el sistema directamente
        dejaba el navegador en "sin conexión" hasta que alguien recargara a mano.
        Esta página no depende de la red: reintenta sola cada dos segundos y entra
        al sistema en cuanto el servidor contesta.

        Entra por /redirect y no por /login a propósito: /redirect lleva a cada
        quien a su panel, y a quien no tenga sesión lo manda al login. Abriendo
        /login, el alumno que acababa de registrarse veía otra vez el formulario
        aunque su sesión siguiera abierta.

        La dirección lleva además qué máquina es (?equipo=1-33). El alumno no lo
        nota; sirve para que, cuando quien inicia sesión es del personal, el
        sistema sepa qué computadora liberar.
    #>

    $sufijo = ''
    if ($Laboratorio -gt 0 -and $Maquina -gt 0) {
        $sufijo = "?equipo=$Laboratorio-$Maquina"
    }

    $html = @'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Centro de Cómputo LabGate</title>
<style>
  html, body { height: 100%; margin: 0; }
  body { display: flex; align-items: center; justify-content: center; background: #1a1a1a;
         font-family: "Segoe UI", Arial, sans-serif; color: #ffffff; }
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
  var destino = "__DESTINO__";
  var equipo = "__EQUIPO__";
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
'@

    $html = $html.Replace('__DESTINO__', $Direccion.TrimEnd('/')).Replace('__EQUIPO__', $sufijo)

    try {
        $carpeta = Split-Path $PaginaEspera -Parent
        if (-not (Test-Path -LiteralPath $carpeta)) {
            New-Item -ItemType Directory -Path $carpeta -Force | Out-Null
        }
        [System.IO.File]::WriteAllText($PaginaEspera, $html, (New-Object System.Text.UTF8Encoding($false)))
        return ([System.Uri]$PaginaEspera).AbsoluteUri
    } catch {
        # Sin página de espera se va directo al sistema, como antes.
        return "$($Direccion.TrimEnd('/'))/redirect$sufijo"
    }
}

function Abrir-Kiosco {
    param([Parameter(Mandatory)][string]$Direccion)

    <#
        Abre el navegador a pantalla completa, sin barra de direcciones, sin
        pestañas y sin botones: sólo el sistema. Devuelve el proceso.
    #>

    $navegador = Buscar-Navegador

    if (-not $navegador) {
        Escribir-Bitacora 'No hay navegador para la pantalla de bloqueo.' 'ERROR'
        return $null
    }

    $hoja = Split-Path $navegador -Leaf
    if ($hoja -ne 'chrome.exe' -and $hoja -ne 'msedge.exe') {
        Escribir-Bitacora "El navegador $hoja no admite pantalla completa de bloqueo." 'ERROR'
        return $null
    }

    $inicio = Preparar-PaginaEspera -Direccion $Direccion

    # Al encender sin red, la página enseña cuánto falta para que el equipo se
    # libere solo; así nadie lo reinicia creyendo que se trabó.
    if ($script:liberarSinRedEn -and (Get-Date) -lt $script:liberarSinRedEn) {
        try {
            $inicio += '#liberar=' + ([DateTimeOffset]$script:liberarSinRedEn).ToUnixTimeMilliseconds()
        } catch { }
    }

    $argumentos = @(
        '--kiosk'
        '--no-first-run'
        '--no-default-browser-check'
        '--disable-session-crashed-bubble'
        '--disable-infobars'
        '--disable-features=Translate'
        "--user-data-dir=`"$PerfilSistema`""
        "`"$inicio`""
    )

    try {
        return Start-Process -FilePath $navegador -ArgumentList $argumentos -PassThru -ErrorAction Stop
    } catch {
        Escribir-Bitacora "No se pudo abrir la pantalla de bloqueo: $($_.Exception.Message)" 'ERROR'
        return $null
    }
}

function Mantener-Kiosco {
    param($Kiosco, [Parameter(Mandatory)][string]$Direccion)

    # Sigue abierto y con su ventana: no hay nada que hacer.
    if ($Kiosco -and -not $Kiosco.HasExited) {
        try { $Kiosco.Refresh() } catch { }
        if ($Kiosco.MainWindowHandle -ne [IntPtr]::Zero) { return $Kiosco }
    }

    # Chrome a veces pasa su ventana a otro de sus procesos. Se busca antes que
    # nada y sin esperas: mientras el vigilante siga apuntando a un proceso sin
    # ventana, no puede traer la pantalla de bloqueo al frente.
    foreach ($info in Obtener-ProcesosDelSistema) {
        $p = Get-Process -Id $info.ProcessId -ErrorAction SilentlyContinue
        if ($p -and $p.MainWindowHandle -ne [IntPtr]::Zero) { return $p }
    }

    # Vivo pero todavía sin ventana: acaba de arrancar, hay que darle tiempo.
    if ($Kiosco -and -not $Kiosco.HasExited) { return $Kiosco }

    # Para no lanzar el navegador en ráfaga si algo falla, un intento cada 3 s.
    $ahora = Get-Date
    if ($script:ultimaReapertura -and ($ahora - $script:ultimaReapertura).TotalSeconds -lt 3) {
        return $null
    }
    $script:ultimaReapertura = $ahora

    Escribir-Bitacora 'La pantalla de bloqueo se cerro; se vuelve a abrir.' 'AVISO'
    return (Abrir-Kiosco -Direccion $Direccion)
}

function Entrar-Bloqueo {
    param([Parameter(Mandatory)][string]$Direccion)

    # Devuelve el proceso de la pantalla de bloqueo, o $null si no se pudo
    # abrir: en ese caso no tiene sentido bloquear nada y el equipo queda libre.

    $kiosco = Abrir-Kiosco -Direccion $Direccion
    if (-not $kiosco) { return $null }

    if ($CandadoDisponible) {
        if (-not [LabGate.Candado]::Activar()) {
            Escribir-Bitacora 'No se pudo activar la anulacion de teclas; el bloqueo queda mas debil.' 'AVISO'
        }
    }

    Encender-SenalDeBloqueo
    return $kiosco
}

function Encender-SenalDeBloqueo {
    # Mientras exista, la guardia (si está instalada) cierra el Administrador de
    # tareas. Desaparece sola si el vigilante termina de cualquier forma.
    if ($script:senalBloqueo) { return }
    try {
        $script:senalBloqueo = New-Object System.Threading.EventWaitHandle(
            $true, [System.Threading.EventResetMode]::ManualReset, $NombreSenal)
    } catch {
        $script:senalBloqueo = $null
    }
}

function Apagar-SenalDeBloqueo {
    if ($script:senalBloqueo) {
        try { $script:senalBloqueo.Dispose() } catch { }
        $script:senalBloqueo = $null
    }
}

function Salir-Bloqueo {
    if ($CandadoDisponible) { [LabGate.Candado]::Desactivar() }
    Apagar-SenalDeBloqueo

    # Cierra la pantalla de bloqueo SIN borrar el perfil: la sesión que el alumno
    # abrió para registrarse se conserva para la ventana normal que viene después.
    Cerrar-NavegadorDelSistema
}

function Mostrar-AvisoDeBloqueo {
    param([int]$Segundos = 30)

    <#
        Avisa de que la sesión del alumno terminó y el equipo se va a bloquear.

        No se puede cancelar con el ratón, a diferencia del aviso de apagado: la
        forma de seguir usando el equipo es registrar una entrada en el sistema, y
        la ventana del sistema queda detrás, accesible, mientras corre la cuenta.
    #>

    try {
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction Stop
        Add-Type -AssemblyName System.Drawing -ErrorAction Stop
    } catch {
        return
    }

    $script:bloqueoRestantes = $Segundos

    try {
        $ventana = New-Object System.Windows.Forms.Form
        $ventana.Text            = 'Centro de Computo - LabGate'
        $ventana.Size            = New-Object System.Drawing.Size(540, 250)
        $ventana.FormBorderStyle = 'FixedDialog'
        $ventana.MaximizeBox     = $false
        $ventana.MinimizeBox     = $false
        $ventana.ControlBox      = $false
        $ventana.TopMost         = $true
        $ventana.BackColor       = [System.Drawing.Color]::White
        $ventana.StartPosition   = 'CenterScreen'

        $franja = New-Object System.Windows.Forms.Panel
        $franja.Size      = New-Object System.Drawing.Size(540, 6)
        $franja.Location  = New-Object System.Drawing.Point(0, 0)
        $franja.BackColor = [System.Drawing.Color]::FromArgb(255, 233, 0)
        $ventana.Controls.Add($franja)

        $titulo = New-Object System.Windows.Forms.Label
        $titulo.Text      = 'Tu sesión en este equipo terminó'
        $titulo.Font      = New-Object System.Drawing.Font('Segoe UI', 14, [System.Drawing.FontStyle]::Bold)
        $titulo.ForeColor = [System.Drawing.Color]::FromArgb(26, 26, 26)
        $titulo.Location  = New-Object System.Drawing.Point(30, 30)
        $titulo.Size      = New-Object System.Drawing.Size(480, 34)
        $ventana.Controls.Add($titulo)

        $cuenta = New-Object System.Windows.Forms.Label
        $cuenta.Text      = "El equipo se bloqueará en $Segundos segundos."
        $cuenta.Font      = New-Object System.Drawing.Font('Segoe UI', 11)
        $cuenta.ForeColor = [System.Drawing.Color]::FromArgb(90, 90, 90)
        $cuenta.Location  = New-Object System.Drawing.Point(30, 72)
        $cuenta.Size      = New-Object System.Drawing.Size(480, 26)
        $ventana.Controls.Add($cuenta)

        $nota = New-Object System.Windows.Forms.Label
        $nota.Text      = 'Guarda tu trabajo. Si quieres seguir usándolo, registra tu entrada de Uso Libre en el sistema antes de que termine la cuenta.'
        $nota.Font      = New-Object System.Drawing.Font('Segoe UI', 9)
        $nota.ForeColor = [System.Drawing.Color]::FromArgb(110, 110, 110)
        $nota.Location  = New-Object System.Drawing.Point(30, 110)
        $nota.Size      = New-Object System.Drawing.Size(480, 60)
        $ventana.Controls.Add($nota)

        $reloj = New-Object System.Windows.Forms.Timer
        $reloj.Interval = 1000
        $reloj.Add_Tick({
            $script:bloqueoRestantes--
            if ($script:bloqueoRestantes -le 0) {
                $reloj.Stop()
                $ventana.Close()
                return
            }
            $cuenta.Text = "El equipo se bloqueará en $($script:bloqueoRestantes) segundos."
            if ($script:bloqueoRestantes -le 10) {
                $cuenta.ForeColor = [System.Drawing.Color]::FromArgb(200, 30, 30)
            }
        })

        $ventana.Add_Shown({ try { [System.Media.SystemSounds]::Exclamation.Play() } catch { } })

        $reloj.Start()
        [void]$ventana.ShowDialog()
        $reloj.Stop()
        $reloj.Dispose()
        $ventana.Dispose()
    } catch {
        # Si no se puede mostrar, se bloquea igual: el aviso es una cortesía.
    }
}

# ==============================================================================
#  AVISO EN PANTALLA ANTES DE APAGAR
# ==============================================================================

function Mostrar-AvisoDeApagado {
    param([Parameter(Mandatory)][int]$Segundos)

    <#
        Muestra una ventana con cuenta regresiva y devuelve $true solo si se puede
        apagar con seguridad.

        Criterio deliberado: ante cualquier duda se devuelve $false. Si la ventana
        no se puede mostrar, si falla al dibujarse o si alguien vuelve a usar el
        equipo, NO se apaga. Un equipo encendido de más es un problema menor que un
        alumno perdiendo su trabajo sin haber visto ningún aviso.
    #>

    try {
        Add-Type -AssemblyName System.Windows.Forms -ErrorAction Stop
        Add-Type -AssemblyName System.Drawing -ErrorAction Stop
    } catch {
        # Si no hay interfaz gráfica es que no hay nadie delante de la pantalla,
        # así que el apagado sigue adelante. Queda registrado para poder revisarlo.
        Escribir-Bitacora 'Sin interfaz grafica: no hay escritorio activo, se apaga sin aviso.' 'AVISO'
        return $true
    }

    # Estado compartido con los manejadores de eventos de la ventana.
    $script:avisoRestantes  = $Segundos
    $script:avisoApagar     = $false   # solo pasa a $true si la cuenta llega a cero
    $script:avisoReferencia = Obtener-SegundosInactivo
    $script:avisoVisible    = $false

    try {
        $ventana = New-Object System.Windows.Forms.Form
        $ventana.Text            = 'Centro de Computo - LabGate'
        $ventana.Size            = New-Object System.Drawing.Size(520, 265)
        $ventana.FormBorderStyle = 'FixedDialog'
        $ventana.MaximizeBox     = $false
        $ventana.MinimizeBox     = $false
        $ventana.TopMost         = $true
        $ventana.BackColor       = [System.Drawing.Color]::White
        $ventana.ControlBox      = $false
        $ventana.ShowInTaskbar   = $true

        # Centrado en la pantalla donde está el cursor, que es la que mira el alumno,
        # y no siempre en el monitor principal.
        $ventana.StartPosition = 'Manual'
        try {
            $pantalla = [System.Windows.Forms.Screen]::FromPoint([System.Windows.Forms.Cursor]::Position)
            $area = $pantalla.WorkingArea
            $ventana.Location = New-Object System.Drawing.Point(
                ($area.X + [int](($area.Width - 520) / 2)),
                ($area.Y + [int](($area.Height - 265) / 2))
            )
        } catch {
            $ventana.StartPosition = 'CenterScreen'
        }

        $franja = New-Object System.Windows.Forms.Panel
        $franja.Size      = New-Object System.Drawing.Size(520, 6)
        $franja.Location  = New-Object System.Drawing.Point(0, 0)
        $franja.BackColor = [System.Drawing.Color]::FromArgb(0, 155, 77)
        $ventana.Controls.Add($franja)

        $titulo = New-Object System.Windows.Forms.Label
        $titulo.Text      = 'El equipo se apagará por inactividad'
        $titulo.Font      = New-Object System.Drawing.Font('Segoe UI', 14, [System.Drawing.FontStyle]::Bold)
        $titulo.ForeColor = [System.Drawing.Color]::FromArgb(26, 26, 26)
        $titulo.Location  = New-Object System.Drawing.Point(30, 32)
        $titulo.Size      = New-Object System.Drawing.Size(460, 34)
        $ventana.Controls.Add($titulo)

        $cuenta = New-Object System.Windows.Forms.Label
        $cuenta.Text      = "Se apagará en $Segundos segundos."
        $cuenta.Font      = New-Object System.Drawing.Font('Segoe UI', 11)
        $cuenta.ForeColor = [System.Drawing.Color]::FromArgb(90, 90, 90)
        $cuenta.Location  = New-Object System.Drawing.Point(30, 74)
        $cuenta.Size      = New-Object System.Drawing.Size(460, 26)
        $ventana.Controls.Add($cuenta)

        $nota = New-Object System.Windows.Forms.Label
        $nota.Text      = 'Se cerrará todo sin guardar. Si sigues aquí, mueve el ratón o pulsa el botón.'
        $nota.Font      = New-Object System.Drawing.Font('Segoe UI', 9)
        $nota.ForeColor = [System.Drawing.Color]::FromArgb(120, 120, 120)
        $nota.Location  = New-Object System.Drawing.Point(30, 106)
        $nota.Size      = New-Object System.Drawing.Size(460, 40)
        $ventana.Controls.Add($nota)

        $boton = New-Object System.Windows.Forms.Button
        $boton.Text      = 'Seguir usando el equipo'
        $boton.Font      = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Bold)
        $boton.Size      = New-Object System.Drawing.Size(240, 42)
        $boton.Location  = New-Object System.Drawing.Point(140, 152)
        $boton.BackColor = [System.Drawing.Color]::FromArgb(0, 155, 77)
        $boton.ForeColor = [System.Drawing.Color]::White
        $boton.FlatStyle = 'Flat'
        $boton.FlatAppearance.BorderSize = 0
        $boton.Add_Click({
            $script:avisoApagar = $false
            $ventana.Close()
        })
        $ventana.Controls.Add($boton)

        $reloj = New-Object System.Windows.Forms.Timer
        $reloj.Interval = 1000
        $reloj.Add_Tick({
            $script:avisoRestantes--

            $ahora = Obtener-SegundosInactivo

            # Una medición fallida no debe interpretarse como "volvió el usuario":
            # simplemente se ignora ese segundo.
            if ($ahora -ge 0 -and $script:avisoReferencia -ge 0) {
                if ($ahora -lt ($script:avisoReferencia - 2)) {
                    $script:avisoApagar = $false
                    $reloj.Stop()
                    $ventana.Close()
                    return
                }
                $script:avisoReferencia = $ahora
            }

            if ($script:avisoRestantes -le 0) {
                $script:avisoApagar = $true
                $reloj.Stop()
                $ventana.Close()
                return
            }

            $cuenta.Text = "Se apagará en $($script:avisoRestantes) segundos."
            if ($script:avisoRestantes -le 10) {
                $cuenta.ForeColor = [System.Drawing.Color]::FromArgb(200, 30, 30)
            }
        })

        $ventana.Add_Shown({
            $script:avisoVisible = $true
            $ventana.Activate()
            try { [System.Media.SystemSounds]::Exclamation.Play() } catch { }
        })

        $reloj.Start()
        [void]$ventana.ShowDialog()
        $reloj.Stop()
        $reloj.Dispose()
        $ventana.Dispose()

    } catch {
        # Un fallo al dibujar la ventana indica una sesión sin escritorio
        # interactivo: nadie puede estar trabajando ahí, así que se apaga.
        Escribir-Bitacora "El aviso no se pudo mostrar ($($_.Exception.Message)); se apaga igual." 'AVISO'
        return $true
    }

    if (-not $script:avisoVisible) {
        Escribir-Bitacora 'La ventana de aviso nunca llego a verse; se apaga igual.' 'AVISO'
        return $true
    }

    return $script:avisoApagar
}

# ==============================================================================
#  VIGILANTE
# ==============================================================================

function Apagar-Equipo {
    <#
        Lanza el apagado del equipo.

        Sobre /f (forzar el cierre de las aplicaciones):
        Es lo que pidió el centro de cómputo, y NO daña el equipo. Windows sigue
        haciendo un apagado ordenado del sistema: detiene los servicios, vacía la
        caché de escritura del disco y desmonta los volúmenes. Lo único que cambia
        es que no espera a que cada programa pregunte si se guarda, así que se
        pierde el trabajo que nadie guardó. Eso es muy distinto de cortar la
        corriente, que sí puede corromper el sistema de archivos.

        Sin /f, un solo diálogo de "¿desea guardar?" deja el equipo encendido toda
        la noche esperando una respuesta que no va a llegar.

        El mensaje va entrecomillado dentro del propio argumento porque
        Start-Process separa por espacios: sin las comillas, shutdown.exe recibe
        "Apagado", "por", "inactividad"... y rechaza el comando entero.
    #>
    $mensaje = '"Apagado por inactividad - Centro de Computo LabGate"'

    try {
        $proceso = Start-Process -FilePath "$env:SystemRoot\System32\shutdown.exe" `
            -ArgumentList '/s', '/f', '/t', '20', '/c', $mensaje `
            -WindowStyle Hidden -PassThru -Wait -ErrorAction Stop

        if ($proceso.ExitCode -ne 0) {
            Escribir-Bitacora "shutdown.exe devolvio el codigo $($proceso.ExitCode); el equipo NO se apagara." 'ERROR'
            return $false
        }

        Escribir-Bitacora 'Apagado aceptado por Windows: 20 s de margen, cerrando aplicaciones.'
        return $true
    } catch {
        Escribir-Bitacora "No se pudo apagar: $($_.Exception.Message)" 'ERROR'
        return $false
    }
}

function Revisar-Inactividad {
    param(
        [Parameter(Mandatory)][int]$Limite,
        [Parameter(Mandatory)][int]$Aviso,
        [string]$Franja = '',
        [switch]$Simulacion
    )

    <#
        Comprueba si toca apagar y, si toca, avisa y apaga.

        Devuelve 'apagado' si el equipo va a apagarse, o 'nada'. En lugar de
        dormir cuando algo impide apagar, deja apuntado cuándo volver a mirar:
        el mismo bucle mantiene la pantalla de bloqueo y no puede quedarse parado.
    #>

    $ahora = Get-Date
    if ($script:inactividadEnPausaHasta -and $ahora -lt $script:inactividadEnPausaHasta) {
        return 'nada'
    }

    $inactivo = Obtener-SegundosInactivo

    # Sin medición fiable no se toma ninguna decisión.
    if ($inactivo -lt 0) { return 'nada' }
    if ($inactivo -lt $Limite) { return 'nada' }

    # --- Comprobaciones de seguridad antes de molestar a nadie ---

    if (Dentro-DeFranjaProtegida -Franja $Franja) {
        return 'nada'
    }

    if (Hay-OtraSesionAbierta) {
        Escribir-Bitacora 'Hay otra sesion abierta en el equipo; no se apaga.' 'AVISO'
        $script:inactividadEnPausaHasta = $ahora.AddMinutes(5)
        return 'nada'
    }

    if (Windows-EstaActualizando) {
        Escribir-Bitacora 'Windows esta instalando actualizaciones; no se apaga.' 'AVISO'
        $script:inactividadEnPausaHasta = $ahora.AddMinutes(5)
        return 'nada'
    }

    Escribir-Bitacora ('Sin actividad desde hace {0:N0} minutos. Mostrando aviso.' -f ($inactivo / 60)) 'AVISO'

    if (-not (Mostrar-AvisoDeApagado -Segundos $Aviso)) {
        # Alguien volvió a usar el equipo: la cuenta de inactividad ya se reinició
        # sola, así que no hace falta nada más.
        Escribir-Bitacora 'No se apaga: el aviso se cancelo.'
        return 'nada'
    }

    if ($Simulacion) {
        Escribir-Bitacora 'SIMULACION: aqui se habria apagado el equipo. Se continua vigilando.' 'AVISO'
        $script:inactividadEnPausaHasta = (Get-Date).AddSeconds(60)
        return 'nada'
    }

    if (Apagar-Equipo) {
        return 'apagado'
    }

    # Si el apagado falló, se reintenta más tarde en lugar de dar por hecho que
    # el equipo ya se apagó.
    Escribir-Bitacora 'El apagado no se pudo completar; se reintentara mas tarde.' 'ERROR'
    $script:inactividadEnPausaHasta = (Get-Date).AddMinutes(10)
    return 'nada'
}

function Iniciar-Vigilante {
    param(
        [Parameter(Mandatory)][string]$Direccion,
        [Parameter(Mandatory)][int]$Minutos,
        [Parameter(Mandatory)][int]$Aviso,
        [string]$Franja = '',
        [int]$Centro = 0,
        [int]$NumeroMaquina = 0,
        [switch]$ConBloqueo,
        [switch]$ConApagado,
        [switch]$Simulacion
    )

    <#
        Un solo bucle que se encarga de todo lo que pasa en el equipo:

          - BLOQUEADO: mantiene la pantalla del sistema al frente, cierra el
            Administrador de tareas, y pregunta al servidor cada 5 s si el alumno
            ya se registró. Si sí, desbloquea.
          - LIBRE: pregunta cada 15 s si la sesión sigue abierta. Si terminó,
            avisa y vuelve a bloquear.
          - En los dos estados, apaga el equipo tras el tiempo sin uso. Así un
            equipo bloqueado que nadie usa tampoco se queda encendido toda la noche.
    #>

    if ($ConApagado -and -not $MedicionDisponible) {
        Escribir-Bitacora 'No se pudo preparar la medicion de inactividad; el apagado queda desactivado.' 'ERROR'
        $ConApagado = $false
    }

    # Un vigilante por sesión. El mutex evita que la tarea, si se dispara dos veces
    # en la misma sesión, deje dos copias trabajando en paralelo.
    $mutex = $null
    try {
        $mutex = New-Object System.Threading.Mutex($false, $NombreMutex)
        if (-not $mutex.WaitOne(0)) {
            Escribir-Bitacora 'Ya hay un vigilante en esta sesion; esta copia termina.'
            return
        }
    } catch {
        Escribir-Bitacora "No se pudo crear el mutex ($($_.Exception.Message)); se sigue sin el." 'AVISO'
        $mutex = $null
    }

    # Tiempos del bucle.
    $pausaMs            = 500    # cada cuánto se revisa la pantalla de bloqueo
    $cadaConsultaBloq   = 5      # s entre consultas al servidor estando bloqueado
    $cadaConsultaLibre  = 15     # s entre consultas estando libre
    $cadaInactividad    = 20     # s entre revisiones de inactividad
    $esperaRedAlInicio  = 90     # s que se espera a la red al encender antes de liberar
    $esperaRedDespues   = 60     # s sin respuesta, ya con contacto previo, antes de liberar
    $corteLargo         = 15     # s sin red a partir de los cuales se recarga la pantalla

    try {
        $limite = $Minutos * 60
        $modo = if ($Simulacion) { ' [SIMULACION: no apagara]' } else { '' }

        # --- Arranque ---
        $estado = 'libre'
        $kiosco = $null

        # Desde aquí cuenta la espera a la red al encender.
        $inicio = Get-Date

        if ($ConBloqueo) {
            # Hora a la que se libera el equipo si no hay red; la pantalla de
            # bloqueo la enseña en cuenta regresiva.
            $script:liberarSinRedEn = $inicio.AddSeconds($esperaRedAlInicio)

            # Si esto no es el inicio de sesión de Windows sino un reinicio del
            # vigilante (la tarea lo relanza cada dos minutos por si alguien lo
            # mató) y la máquina está registrada, hay alguien trabajando: no se
            # le cierra el navegador ni se le bloquea la pantalla.
            $enMarcha = $false

            if (Es-ArranqueDeSesion) {
                # Sesión nueva de Windows: el servidor cancela el permiso que el
                # personal hubiera dejado abierto sin cerrar sesión en el sistema,
                # y cierra el Uso Libre que el alumno anterior dejó abierto al
                # apagar. Si todavía no hay red, se avisa en cuanto la haya.
                $respuesta = Consultar-SiEstaRegistrado -Direccion $Direccion -Centro $Centro `
                                -NumeroMaquina $NumeroMaquina -Reiniciar
                if ($null -eq $respuesta) { $script:reinicioPendiente = $true }
            } else {
                $enMarcha = (Consultar-SiEstaRegistrado -Direccion $Direccion -Centro $Centro `
                                -NumeroMaquina $NumeroMaquina) -eq $true
            }

            if ($enMarcha) {
                Escribir-Bitacora 'El vigilante arranco con una sesion en curso: se continua sin interrumpir.'
                if ((Obtener-ProcesosDelSistema).Count -eq 0) {
                    Abrir-Sistema -Direccion $Direccion -ConPerfilDelSistema
                }
            } else {
                # Cada sesión de Windows empieza con el perfil del sistema en blanco.
                Limpiar-PerfilDelSistema
                $kiosco = Entrar-Bloqueo -Direccion $Direccion
                if ($kiosco) {
                    $estado = 'bloqueado'
                    Escribir-Bitacora "Bloqueo activo. Esperando registro en laboratorio $Centro, maquina $NumeroMaquina."
                } else {
                    Escribir-Bitacora 'No se pudo abrir la pantalla de bloqueo; el equipo queda libre.' 'ERROR'
                    Abrir-Sistema -Direccion $Direccion
                }
            }
        } else {
            Abrir-Sistema -Direccion $Direccion
        }

        if ($ConApagado) {
            Escribir-Bitacora "Vigilante iniciado. Apagara tras $Minutos min sin actividad, con aviso de $Aviso s.$modo"
        }

        $huboContacto      = $false      # el servidor ya contestó alguna vez
        $sinRedDesde       = $null       # desde cuándo no contesta
        $libresSeguidas    = 0           # consultas seguidas que dicen "libre" estando desbloqueado
        $proximaConsulta   = Get-Date
        $proximaInactiva   = (Get-Date).AddSeconds($cadaInactividad)

        while ($true) {
            Start-Sleep -Milliseconds $pausaMs
            $ahora = Get-Date

            # ================================================================
            #  BLOQUEADO
            # ================================================================
            if ($estado -eq 'bloqueado') {

                # El Administrador de tareas es la salida desde Ctrl+Alt+Supr.
                Get-Process -Name 'Taskmgr' -ErrorAction SilentlyContinue |
                    Stop-Process -Force -ErrorAction SilentlyContinue

                # La pantalla del sistema tiene que seguir abierta y al frente.
                $kiosco = Mantener-Kiosco -Kiosco $kiosco -Direccion $Direccion
                if ($kiosco -and $CandadoDisponible) {
                    try {
                        $kiosco.Refresh()
                        [LabGate.Candado]::FijarAlFrente($kiosco.MainWindowHandle)
                    } catch { }
                }

                if ($ahora -ge $proximaConsulta) {
                    $proximaConsulta = $ahora.AddSeconds($cadaConsultaBloq)
                    $registrado = Consultar-Estado -Direccion $Direccion -Centro $Centro -NumeroMaquina $NumeroMaquina

                    # El servidor contestó: la cuenta regresiva de "sin red" ya no aplica.
                    if ($null -ne $registrado) { $script:liberarSinRedEn = $null }

                    if ($registrado -eq $true) {
                        Escribir-Bitacora 'Registro detectado: se libera el equipo.'
                        Salir-Bloqueo
                        Abrir-Sistema -Direccion $Direccion -ConPerfilDelSistema
                        $kiosco = $null
                        $estado = 'libre'
                        $huboContacto = $true
                        $sinRedDesde = $null
                        $libresSeguidas = 0
                        $proximaConsulta = $ahora.AddSeconds($cadaConsultaLibre)
                    }
                    elseif ($registrado -eq $false) {
                        # Volvió la red tras un corte largo: la pantalla pudo quedarse
                        # en "sin conexión", así que se vuelve a abrir desde la espera.
                        if ($sinRedDesde -and ($ahora - $sinRedDesde).TotalSeconds -ge $corteLargo) {
                            Escribir-Bitacora 'Volvio la conexion; se recarga la pantalla de bloqueo.'
                            Cerrar-NavegadorDelSistema -Segundos 2
                            $kiosco = Abrir-Kiosco -Direccion $Direccion
                        }
                        $huboContacto = $true
                        $sinRedDesde = $null
                    }
                    else {
                        # Sin respuesta. Al encender se espera más, porque la red
                        # tarda en conectarse; ya con contacto previo se espera menos.
                        if (-not $sinRedDesde) { $sinRedDesde = $ahora }
                        $referencia = if ($huboContacto) { $sinRedDesde } else { $inicio }
                        $tope = if ($huboContacto) { $esperaRedDespues } else { $esperaRedAlInicio }

                        if (($ahora - $referencia).TotalSeconds -ge $tope) {
                            Escribir-Bitacora "El sistema no responde desde hace $tope s: se levanta el bloqueo para no dejar el equipo inservible." 'AVISO'
                            Salir-Bloqueo
                            Abrir-Sistema -Direccion $Direccion -ConPerfilDelSistema
                            $kiosco = $null
                            $estado = 'libre'
                            $libresSeguidas = 0
                            $proximaConsulta = $ahora.AddSeconds($cadaConsultaLibre)
                        }
                    }
                }
            }

            # ================================================================
            #  LIBRE (con bloqueo configurado): vigilar que la sesión siga abierta
            # ================================================================
            elseif ($ConBloqueo -and $ahora -ge $proximaConsulta) {
                $proximaConsulta = $ahora.AddSeconds($cadaConsultaLibre)
                $registrado = Consultar-Estado -Direccion $Direccion -Centro $Centro -NumeroMaquina $NumeroMaquina
                if ($null -ne $registrado) { $script:liberarSinRedEn = $null }

                if ($registrado -eq $true) {
                    $libresSeguidas = 0
                    $huboContacto = $true

                    # Mientras su sesión siga abierta, el sistema se queda a la
                    # mano: si cerró la ventana, se vuelve a abrir con su sesión
                    # puesta. Sin esto tendría que entrar otra vez con su
                    # contraseña, porque la sesión vive en el perfil del sistema
                    # y no en el navegador que abre desde el escritorio.
                    if ((Obtener-ProcesosDelSistema).Count -eq 0) {
                        Escribir-Bitacora 'La ventana del sistema se cerro; se vuelve a abrir con la sesion en curso.'
                        Abrir-Sistema -Direccion $Direccion -ConPerfilDelSistema
                    }
                }
                elseif ($registrado -eq $false) {
                    $huboContacto = $true
                    $libresSeguidas++

                    # Dos respuestas seguidas, para no bloquear por un parpadeo.
                    if ($libresSeguidas -ge 2) {
                        Escribir-Bitacora 'La sesion en este equipo termino; se avisa y se vuelve a bloquear.' 'AVISO'
                        Mostrar-AvisoDeBloqueo -Segundos 30

                        # Durante el aviso pudo registrar una entrada de uso libre.
                        if ((Consultar-Estado -Direccion $Direccion -Centro $Centro -NumeroMaquina $NumeroMaquina) -eq $true) {
                            Escribir-Bitacora 'Se registro una nueva entrada durante el aviso; el equipo sigue libre.'
                            $libresSeguidas = 0
                        } else {
                            # Perfil en blanco: el siguiente alumno no hereda la sesión.
                            Limpiar-PerfilDelSistema
                            $kiosco = Entrar-Bloqueo -Direccion $Direccion
                            if ($kiosco) {
                                $estado = 'bloqueado'
                                $sinRedDesde = $null
                                $proximaConsulta = (Get-Date).AddSeconds($cadaConsultaBloq)
                                Escribir-Bitacora 'Equipo bloqueado de nuevo. Esperando registro.'
                            } else {
                                Abrir-Sistema -Direccion $Direccion
                                $libresSeguidas = 0
                            }
                        }
                    }
                }
                # $null: sin red. Se deja el equipo como está.
            }

            # ================================================================
            #  APAGADO POR INACTIVIDAD (en los dos estados)
            # ================================================================
            if ($ConApagado -and $ahora -ge $proximaInactiva) {
                $proximaInactiva = $ahora.AddSeconds($cadaInactividad)
                $resultado = Revisar-Inactividad -Limite $limite -Aviso $Aviso -Franja $Franja -Simulacion:$Simulacion
                if ($resultado -eq 'apagado') { return }
            }
        }
    } finally {
        # Pase lo que pase, el teclado no se queda capado.
        if ($CandadoDisponible) {
            try { [LabGate.Candado]::Desactivar() } catch { }
        }
        Apagar-SenalDeBloqueo
        if ($mutex) {
            try { $mutex.ReleaseMutex() } catch { }
            try { $mutex.Dispose() } catch { }
        }
    }
}

function Iniciar-Guardia {
    <#
        Corre elevada en las cuentas de administrador con bloqueo. Ahí el
        Administrador de tareas se abre con permisos elevados y el vigilante no
        puede cerrarlo; la guardia sí. No hace nada más, y sólo actúa mientras el
        vigilante tiene el equipo bloqueado (mientras existe su señal).
    #>

    $mutex = $null
    try {
        $mutex = New-Object System.Threading.Mutex($false, $NombreMutexGuardia)
        if (-not $mutex.WaitOne(0)) { return }
    } catch {
        $mutex = $null
    }

    Escribir-Bitacora 'Guardia del bloqueo iniciada.'

    try {
        while ($true) {
            Start-Sleep -Milliseconds 300

            $senal = $null
            if (-not [System.Threading.EventWaitHandle]::TryOpenExisting($NombreSenal, [ref]$senal)) {
                continue
            }
            # Se suelta en el acto: si se conservara, la señal seguiría existiendo
            # aunque el vigilante terminara, y el equipo quedaría sin Administrador
            # de tareas para siempre.
            $senal.Dispose()

            Get-Process -Name 'Taskmgr' -ErrorAction SilentlyContinue |
                Stop-Process -Force -ErrorAction SilentlyContinue
        }
    } finally {
        if ($mutex) {
            try { $mutex.ReleaseMutex() } catch { }
            try { $mutex.Dispose() } catch { }
        }
    }
}

# ==============================================================================
#  INSTALACIÓN
# ==============================================================================

function Confirmar-Administrador {
    $identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identidad)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Admite-ArranqueSinVentana {
    <#
        ¿Este equipo puede arrancar PowerShell a través de conhost sin ventana
        (conhost.exe --headless)?

        powershell.exe es un programa de consola: lanzado directamente, Windows le
        abre su ventana negra (o la Terminal de Windows) antes de que arranque, y
        -WindowStyle Hidden sólo la esconde una vez que PowerShell ya cargó. Eso
        se veía como un parpadeo cada dos minutos, en cada relanzamiento de la
        tarea. A través de conhost en modo --headless no llega a crearse ninguna
        ventana.

        Se comprueba de verdad: se lanza un PowerShell mínimo por ese camino y se
        mira que haya escrito su archivo. Si no (Windows 10 anterior a 2018, o algo
        lo impide), la tarea se registra como antes, con parpadeo pero funcionando.
    #>
    param([Parameter(Mandatory)][string]$PowerShell)

    $conhost = Join-Path $env:SystemRoot 'System32\conhost.exe'
    if (-not (Test-Path $conhost)) { return $false }

    $marca = Join-Path $CarpetaDatos ('prueba-arranque-{0}.txt' -f [guid]::NewGuid().ToString('N'))
    try {
        $proceso = Start-Process -FilePath $conhost -PassThru -ArgumentList (
            "--headless `"$PowerShell`" -NoProfile -ExecutionPolicy Bypass -Command `"Set-Content -LiteralPath '$marca' -Value 'ok'`"")
        [void]$proceso.WaitForExit(20000)

        # Por si conhost terminó antes de que el archivo quedara escrito.
        for ($i = 0; $i -lt 10 -and -not (Test-Path -LiteralPath $marca); $i++) { Start-Sleep -Milliseconds 300 }

        return (Test-Path -LiteralPath $marca) -and ((Get-Content -LiteralPath $marca -ErrorAction Stop) -eq 'ok')
    } catch {
        return $false
    } finally {
        Remove-Item -LiteralPath $marca -Force -ErrorAction SilentlyContinue
    }
}

function Instalar-EnEsteEquipo {
    if (-not (Confirmar-Administrador)) {
        Write-Host ''
        Write-Host '  Hace falta abrir PowerShell como Administrador para instalar.' -ForegroundColor Red
        Write-Host '  Clic derecho en PowerShell -> "Ejecutar como administrador".' -ForegroundColor Gray
        Write-Host ''
        return
    }

    $rutaScript = $PSCommandPath
    if (-not $rutaScript) {
        Write-Host '  No se pudo determinar la ruta del script.' -ForegroundColor Red
        return
    }

    # El script se copia a una carpeta fija del equipo para que la tarea no se rompa
    # si se retira la memoria USB desde la que se instaló.
    $destino = Join-Path $CarpetaDatos 'Equipo-CentroComputo.ps1'
    if (-not (Test-Path $CarpetaDatos)) {
        New-Item -ItemType Directory -Path $CarpetaDatos -Force | Out-Null
    }
    if ($rutaScript -ne $destino) {
        Copy-Item -LiteralPath $rutaScript -Destination $destino -Force
    }

    # Sin esto, las sesiones de los alumnos no podrían escribir la bitácora, porque
    # el archivo lo crea el administrador durante la instalación.
    Permitir-EscrituraDeAlumnos | Out-Null

    $argumentos = @(
        '-NoProfile'
        '-WindowStyle Hidden'
        '-ExecutionPolicy Bypass'
        "-File `"$destino`""
        "-Url `"$Url`""
        "-MinutosInactividad $MinutosInactividad"
        "-SegundosAviso $SegundosAviso"
    )
    if ($NoApagarEntre) { $argumentos += "-NoApagarEntre `"$NoApagarEntre`"" }
    if ($SinApagado)    { $argumentos += '-SinApagado' }

    # El bloqueo sólo se activa si el equipo sabe qué máquina es.
    if ($Laboratorio -gt 0 -and $Maquina -gt 0) {
        $argumentos += "-Laboratorio $Laboratorio"
        $argumentos += "-Maquina $Maquina"
        if ($BloquearAdministradores) { $argumentos += '-BloquearAdministradores' }
    }

    # Sin parpadeo: PowerShell se arranca a través de conhost sin ventana, si este
    # equipo lo admite (ver Admite-ArranqueSinVentana). Si no, como antes.
    $powershell = Join-Path $PSHOME 'powershell.exe'
    $sinParpadeo = Admite-ArranqueSinVentana -PowerShell $powershell
    if ($sinParpadeo) {
        $ejecutable = Join-Path $env:SystemRoot 'System32\conhost.exe'
        $prefijo    = "--headless `"$powershell`" "
    } else {
        $ejecutable = 'powershell.exe'
        $prefijo    = ''
    }

    $accion = New-ScheduledTaskAction -Execute $ejecutable -Argument ($prefijo + ($argumentos -join ' '))

    # Al iniciar sesión cualquier usuario, con un respiro para que el escritorio y
    # la red terminen de levantar.
    $disparador = New-ScheduledTaskTrigger -AtLogOn
    $disparador.Delay = 'PT20S'

    # Además, cada dos minutos se vuelve a lanzar. Si el vigilante sigue vivo, la
    # copia nueva se descarta sola (MultipleInstances IgnoreNew, y el mutex por si
    # acaso); si alguien lo cerró —la ventana de PowerShell, el Administrador de
    # tareas—, vuelve en menos de dos minutos en lugar de dejar el equipo sin
    # bloqueo hasta el siguiente inicio de sesión.
    $repeticion = (New-ScheduledTaskTrigger -Once -At (Get-Date) `
        -RepetitionInterval (New-TimeSpan -Minutes 2)).Repetition
    $repeticion.StopAtDurationEnd = $false
    $disparador.Repetition = $repeticion

    $ajustes = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -ExecutionTimeLimit ([TimeSpan]::Zero) `
        -MultipleInstances IgnoreNew `
        -RestartCount 5 `
        -RestartInterval (New-TimeSpan -Minutes 1)

    # En la sesión del usuario que inicia: es la única forma de que la detección de
    # teclado y ratón mida a la persona correcta.
    $principal = New-ScheduledTaskPrincipal -GroupId 'S-1-5-32-545' -RunLevel Limited

    Register-ScheduledTask -TaskName $NombreTarea `
        -Action $accion -Trigger $disparador -Settings $ajustes -Principal $principal `
        -Description 'Abre el sistema del Centro de Computo al iniciar sesion y apaga el equipo por inactividad.' `
        -Force | Out-Null

    # La guardia sólo hace falta donde los alumnos entran como administradores.
    # Si se reinstala sin -BloquearAdministradores, se quita la que hubiera.
    if ($BloquearAdministradores -and $Laboratorio -gt 0 -and $Maquina -gt 0) {
        $accionGuardia = New-ScheduledTaskAction -Execute $ejecutable -Argument ($prefijo + (@(
            '-NoProfile'
            '-WindowStyle Hidden'
            '-ExecutionPolicy Bypass'
            "-File `"$destino`""
            '-Guardia'
        ) -join ' '))

        # "Con los privilegios más altos": para un administrador, elevada sin
        # preguntar; para una cuenta normal, igual que cualquier otro programa.
        $principalGuardia = New-ScheduledTaskPrincipal -GroupId 'S-1-5-32-545' -RunLevel Highest

        Register-ScheduledTask -TaskName $NombreTareaGuardia `
            -Action $accionGuardia -Trigger $disparador -Settings $ajustes -Principal $principalGuardia `
            -Description 'Cierra el Administrador de tareas mientras el equipo esta bloqueado esperando registro.' `
            -Force | Out-Null
    } else {
        try { Unregister-ScheduledTask -TaskName $NombreTareaGuardia -Confirm:$false -ErrorAction Stop } catch { }
    }

    Configurar-PaginaDeInicio -Direccion $Url

    Escribir-Bitacora "Instalado. Url=$Url  Inactividad=$MinutosInactividad min  Aviso=$SegundosAviso s  Franja='$NoApagarEntre'  SinParpadeo=$sinParpadeo"

    Write-Host ''
    Write-Host '  Listo. El equipo quedo configurado.' -ForegroundColor Green
    Write-Host ''
    Write-Host "    Sistema:        $Url"
    if ($SinApagado) {
        Write-Host '    Apagado:        desactivado'
    } else {
        Write-Host "    Se apaga tras:  $MinutosInactividad minutos sin uso"
        Write-Host "    Aviso previo:   $SegundosAviso segundos"
        if ($NoApagarEntre) {
            Write-Host "    Nunca entre:    $NoApagarEntre"
        }
    }
    if ($Laboratorio -gt 0 -and $Maquina -gt 0) {
        Write-Host "    Bloqueo:        laboratorio $Laboratorio, maquina #$Maquina"
        if ($BloquearAdministradores) {
            Write-Host '                    (tambien para cuentas de administrador, con guardia)'
        }
    } else {
        Write-Host '    Bloqueo:        desactivado (sin -Laboratorio y -Maquina)'
    }
    if ($sinParpadeo) {
        Write-Host '    Arranque:       sin ventana (sin parpadeo)'
    } else {
        Write-Host '    Arranque:       normal (puede verse un parpadeo cada 2 minutos)'
    }
    Write-Host "    Bitacora:       $ArchivoBitacora"
    Write-Host ''
    Write-Host '  Empieza a funcionar en el proximo inicio de sesion.' -ForegroundColor Gray
    Write-Host '  Para comprobarlo ahora sin esperar, cierra sesion y vuelve a entrar.' -ForegroundColor Gray
    Write-Host ''
}

function Desinstalar-DeEsteEquipo {
    if (-not (Confirmar-Administrador)) {
        Write-Host '  Hace falta abrir PowerShell como Administrador.' -ForegroundColor Red
        return
    }

    try {
        Unregister-ScheduledTask -TaskName $NombreTarea -Confirm:$false -ErrorAction Stop
        Write-Host '  Tarea programada eliminada.' -ForegroundColor Green
    } catch {
        Write-Host '  No habia ninguna tarea registrada.' -ForegroundColor Yellow
    }

    try {
        Unregister-ScheduledTask -TaskName $NombreTareaGuardia -Confirm:$false -ErrorAction Stop
        Write-Host '  Guardia del bloqueo eliminada.' -ForegroundColor Green
    } catch { }

    # Quitar la tarea no detiene los vigilantes que ya están corriendo. Si no se
    # cierran, el equipo seguiría apagándose solo durante el mantenimiento.
    $detenidos = 0
    try {
        $propios = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" -ErrorAction Stop |
                   Where-Object { $_.CommandLine -and $_.CommandLine -like '*Equipo-CentroComputo.ps1*' -and $_.ProcessId -ne $PID }
        foreach ($p in $propios) {
            try {
                Stop-Process -Id $p.ProcessId -Force -ErrorAction Stop
                $detenidos++
            } catch { }
        }
    } catch { }

    if ($detenidos -gt 0) {
        Write-Host "  Vigilantes detenidos: $detenidos" -ForegroundColor Green
    }

    Quitar-PaginaDeInicio
    Escribir-Bitacora "Desinstalado del equipo. Vigilantes detenidos: $detenidos"

    Write-Host '  Pagina de inicio restablecida.' -ForegroundColor Green
    Write-Host "  La bitacora se conserva en $ArchivoBitacora" -ForegroundColor Gray
}

# ==============================================================================
#  ARRANQUE
# ==============================================================================

if ($Instalar) {
    Instalar-EnEsteEquipo
    return
}

if ($Desinstalar) {
    Desinstalar-DeEsteEquipo
    return
}

if ($Guardia) {
    Iniciar-Guardia
    return
}

# --- Qué hace este equipo ---------------------------------------------------
$bloqueoConfigurado = ($Laboratorio -gt 0 -and $Maquina -gt 0)
$conBloqueo = $bloqueoConfigurado

# El personal técnico entra con su cuenta de administrador y trabaja sin
# bloqueo, salvo en los equipos donde los alumnos también son administradores.
if ($bloqueoConfigurado -and -not $BloquearAdministradores -and (Es-Administrador)) {
    Escribir-Bitacora 'Cuenta de administrador: no se aplica el bloqueo.'
    $conBloqueo = $false
}

if ($Probar) {
    Write-Host ''
    if ($conBloqueo) {
        Write-Host "  MODO BLOQUEO: laboratorio $Laboratorio, maquina #$Maquina" -ForegroundColor Cyan
        Write-Host '  El equipo quedara bloqueado hasta registrar asistencia.' -ForegroundColor Gray
    } elseif ($bloqueoConfigurado) {
        Write-Host '  Bloqueo configurado, pero esta cuenta es de administrador: no se aplica.' -ForegroundColor Yellow
        Write-Host '  Para probarlo en esta cuenta, agrega -BloquearAdministradores.' -ForegroundColor Gray
    }
    if (-not $SinApagado) {
        Write-Host '  MODO PRUEBA: el equipo NO se apagara, solo se avisara de lo que haria.' -ForegroundColor Cyan
        Write-Host "  Deja de tocar el equipo $MinutosInactividad minuto(s) para ver el aviso." -ForegroundColor Gray
    }
    Write-Host '  Se detiene con Ctrl+C.' -ForegroundColor Gray
    Write-Host ''
}

# Sin bloqueo ni apagado sólo queda abrir el sistema.
if ($SinApagado -and -not $conBloqueo) {
    Abrir-Sistema -Direccion $Url
    Escribir-Bitacora 'Apagado automatico desactivado por configuracion.'
    return
}

Iniciar-Vigilante -Direccion $Url -Minutos $MinutosInactividad -Aviso $SegundosAviso `
    -Franja $NoApagarEntre -Centro $Laboratorio -NumeroMaquina $Maquina `
    -ConBloqueo:$conBloqueo -ConApagado:(-not $SinApagado) -Simulacion:$Probar
