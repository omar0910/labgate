# Equipos de los centros de cómputo

Script para preparar las computadoras de los laboratorios del LabGate. Hace dos cosas:

1. **Al iniciar sesión**, abre el navegador con el sistema.
2. **Tras 30 minutos sin usarse**, avisa en pantalla y apaga el equipo, cerrando
   las aplicaciones abiertas.

Y, si se instala con `INSTALAR-CON-BLOQUEO.bat`, deja el equipo bloqueado hasta que
el alumno se registre (ver [Bloqueo hasta registrarse](#bloqueo-hasta-registrarse-opcional)).

---

## Instalación en un equipo

**Copia los archivos a una memoria USB.** Da igual en qué carpeta queden, mientras
estén los cinco juntos.

**En cada computadora: doble clic en `INSTALAR.bat`.**

Eso es todo. El instalador se encarga solo de pedir permisos de administrador
(acepta la ventana azul que aparece), encontrar el script aunque la memoria cambie
de letra, y saltar el bloqueo de scripts de Windows.

Debe terminar diciendo **"Listo. El equipo quedó configurado."**

Después puedes retirar la memoria: el script ya se copió a `C:\ProgramData\LabGate\`.
Empieza a actuar en el siguiente inicio de sesión.

### Los archivos que se usan con doble clic

| Archivo | Para qué |
|---|---|
| `INSTALAR.bat` | Deja el equipo configurado |
| `INSTALAR-CON-BLOQUEO.bat` | Igual, más el bloqueo hasta registrarse |
| `PROBAR.bat` | Prueba segura: **nunca apaga**, solo enseña el aviso |
| `DESINSTALAR.bat` | Lo quita todo de ese equipo |

### Si prefieres hacerlo a mano desde PowerShell

PowerShell **como administrador**, y ojo con la ruta: si copiaste la carpeta
`scripts` completa, el script está un nivel más adentro.

```powershell
cd D:\scripts\centros-computo
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
.\Equipo-CentroComputo.ps1 -Instalar
```

¿No sabes en qué letra quedó la memoria? Esto te lo dice:

```powershell
Get-ChildItem D:\, E:\, F:\, G:\ -Filter 'Equipo-CentroComputo.ps1' -Recurse -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName
```

## Ajustes

Todos opcionales. Sin ellos se usan los valores de arriba.

```powershell
# Apagar tras 45 minutos en vez de 30
.\Equipo-CentroComputo.ps1 -Instalar -MinutosInactividad 45

# Dar 2 minutos de aviso antes de apagar
.\Equipo-CentroComputo.ps1 -Instalar -SegundosAviso 120

# Nunca apagar durante el horario de clases de la mañana
.\Equipo-CentroComputo.ps1 -Instalar -NoApagarEntre "07:00-14:00"

# Solo abrir el navegador, sin apagado automático
.\Equipo-CentroComputo.ps1 -Instalar -SinApagado

# Apuntar a otra dirección
.\Equipo-CentroComputo.ps1 -Instalar -Url "http://192.168.1.10"
```

Se pueden combinar:

```powershell
.\Equipo-CentroComputo.ps1 -Instalar -MinutosInactividad 45 -NoApagarEntre "07:00-14:00"
```

La franja de `-NoApagarEntre` admite cruzar la medianoche, por ejemplo `"22:00-06:00"`.

---

## Probarlo sin riesgo

Doble clic en **`PROBAR.bat`**, o desde PowerShell:

```powershell
.\Equipo-CentroComputo.ps1 -Probar -MinutosInactividad 2
```

Abre el navegador y queda vigilando en primer plano. **En modo prueba el equipo
nunca se apaga**: cuando tocaría hacerlo, lo anuncia y sigue funcionando, para que
puedas ver el aviso sin perder nada.

Con `-MinutosInactividad 2` no hay que esperar 20 minutos. Se detiene con **Ctrl+C**.

---

## Quitarlo de un equipo

Doble clic en **`DESINSTALAR.bat`**, o desde PowerShell:

```powershell
.\Equipo-CentroComputo.ps1 -Desinstalar
```

Elimina la tarea programada, **detiene los vigilantes que estén corriendo** y
restablece la página de inicio del navegador.

---

## Qué pasa al apagar

**Se cierran todas las aplicaciones sin preguntar, y se pierde lo que no estuviera
guardado.** Es lo que pidió el centro de cómputo, y la ventana de aviso lo advierte
con todas sus letras antes de que ocurra.

**Esto no daña el equipo.** Windows sigue haciendo un apagado ordenado: detiene los
servicios, vacía la caché de escritura del disco y desmonta los volúmenes
correctamente. Lo único que se salta es la pregunta de "¿desea guardar?". Es muy
distinto de cortar la corriente, que sí puede corromper el sistema de archivos.

El alumno tiene **80 segundos** para reaccionar: 60 del aviso en pantalla, más 20
del contador propio de Windows.

## Cuándo NO se apaga

Aun con el apagado forzado, hay tres casos en que se cancela:

- **Alguien mueve el ratón o toca el teclado** durante la cuenta regresiva.
- **Hay otra sesión de usuario abierta** en la misma computadora. El aviso solo se
  ve en la sesión inactiva, así que el otro alumno no tendría forma de evitarlo.
- **Windows está instalando actualizaciones.** Apagar ahí sí puede dejar el equipo
  inservible, que es justo el daño que hay que evitar.

También se respeta la franja de `-NoApagarEntre`, si se configuró.

### Lo que el script no puede detectar

La inactividad se mide con teclado y ratón, que es lo que ofrece Windows. **Ver un
video, una descarga larga o una práctica que corre sola no cuentan como actividad.**
Para esos casos está el aviso: quien esté ahí lo ve, lo cancela y sigue trabajando.

Si en algún laboratorio resulta molesto, sube el tiempo:

```powershell
.\Equipo-CentroComputo.ps1 -Instalar -MinutosInactividad 45
```

---

## Si algo no funciona

El script registra todo en:

```
C:\ProgramData\LabGate\centro-computo.log
```

Para verlo:

```powershell
Get-Content C:\ProgramData\LabGate\centro-computo.log -Tail 30
```

Para comprobar que la tarea quedó registrada:

```powershell
Get-ScheduledTask -TaskName 'LabGate - Equipo de centro de computo' | Format-List TaskName, State
Get-ScheduledTaskInfo -TaskName 'LabGate - Equipo de centro de computo'
```

---

## Qué dirección usar

La dirección por la que los equipos del laboratorio llegan al servidor **sin avisos
de certificado**. Se indica al instalar, con `-Url`.

Un caso real: dentro de una red institucional, el dominio público mostraba una
advertencia de certificado porque un equipo intermedio de la red sustituía el
certificado del servidor; en Safari el acceso quedaba bloqueado del todo. Ahí lo
correcto fue apuntar los equipos a la IP interna del servidor: no conviene que los
alumnos se acostumbren a saltarse avisos de seguridad.

Para cambiarla después, basta con reinstalar apuntando a la nueva:

```powershell
.\Equipo-CentroComputo.ps1 -Instalar -Url "https://labgate.example"
```

---

## Sobre la página de inicio del navegador

Además de abrir el navegador al iniciar sesión, la instalación deja el sistema como
página de arranque de Chrome y Edge. Así, si el alumno cierra el navegador y lo
vuelve a abrir, o pulsa el botón de Inicio, regresa al sistema.

Se aplica como política del equipo, de modo que vale para todos los usuarios. Como
efecto secundario, en el menú del navegador aparecerá *"Administrado por tu
organización"*, y el alumno no podrá cambiar esa página. En un laboratorio suele ser
lo deseable.

Firefox no lee esas políticas; ahí solo funciona la apertura automática.

---

## Bloqueo hasta registrarse (opcional)

Deja la computadora con el sistema a pantalla completa, sin poder hacer nada más,
hasta que el alumno registre su asistencia o su entrada de uso libre **en esa
máquina**.

### Cómo se comporta

1. **Al iniciar sesión en Windows** aparece la pantalla del sistema. Si la red
   todavía no conecta (pasa mucho al encender), se muestra *"Conectando con el
   sistema…"* y entra sola en cuanto hay conexión: ya no hay que recargar a mano.
2. **El alumno inicia sesión y se registra.** En unos 5 segundos el equipo se
   libera y el navegador se abre normal **con su sesión ya iniciada**: no tiene
   que volver a entrar. Si cierra esa ventana, **se vuelve a abrir sola** en
   menos de quince segundos, otra vez con su sesión puesta; así no tiene que
   escribir su contraseña de nuevo para terminar su uso libre. Las ventanas que
   abra por su cuenta no se tocan.
3. **Cuando su sesión termina** —pulsa *Terminar sesión* de uso libre, **cierra
   sesión en la página**, acaba la hora de su clase (con 15 minutos de margen) o
   el encargado lo saca desde el Monitor—, aparece un aviso de 30 segundos: *"Tu
   sesión en este equipo terminó"*. Si en ese tiempo no registra una entrada
   nueva, **el equipo se vuelve a bloquear** y el navegador empieza en blanco,
   sin la sesión del alumno anterior.

Cerrar sesión en la página **también registra la salida de uso libre**, así que la
máquina queda libre para el siguiente alumno. El sistema lo avisa antes de
cerrarla.

Mientras está bloqueado:

- La tecla Windows, Alt+Tab, Alt+F4, Ctrl+Esc, Ctrl+Shift+Esc, el clic derecho y
  los atajos del navegador para abrir, guardar, imprimir o ver descargas no hacen
  nada. Escribir, copiar, pegar y la arroba (AltGr) funcionan normal.
- Si alguien minimiza el navegador, vuelve al frente al instante; si lo cierra, se
  vuelve a abrir en unos segundos.
- **El Administrador de tareas se cierra en cuanto se abre**, también si llegan a él
  desde Ctrl+Alt+Supr.

Y en cualquier momento, bloqueado o libre:

- **El vigilante corre sin consola.** La tarea lanza un proceso que enseguida se
  relanza a sí mismo sin ventana y termina, así que no queda ninguna ventana de
  PowerShell en la barra de tareas. Antes sí la había, y quien la cerraba dejaba
  el equipo sin bloqueo hasta el siguiente inicio de sesión.
- **Si aun así alguien lo mata** (por ejemplo desde el Administrador de tareas con
  el equipo desbloqueado), la tarea lo vuelve a lanzar cada dos minutos, así que
  el equipo se recupera solo. Al volver comprueba si hay una sesión registrada en
  esa máquina: si la hay, no interrumpe a quien esté trabajando; si no, bloquea.
- **Ese relanzamiento no parpadea.** La tarea arranca PowerShell a través de
  `conhost.exe --headless`, que no crea ninguna ventana. Antes, cada dos minutos
  se veía un flashazo negro de menos de un segundo: era la ventana de la consola,
  que Windows abre antes de que PowerShell alcance a esconderla. El instalador
  comprueba que el equipo lo admita y, si no (Windows 10 anterior a 2018), la
  registra como antes. Al terminar dice cuál quedó: *"Arranque: sin ventana"*.
  Los equipos instalados antes de este cambio siguen con el parpadeo hasta que
  se reinstalen.

### Antes de instalarlo: revisa el laboratorio

El bloqueo solo se levanta si el alumno **puede** registrarse en ese laboratorio:
tiene que permitir uso libre o tener clases programadas. Por ejemplo:

| Laboratorio | Uso libre | Clases | ¿Listo para bloquear? |
|---|---|---|---|
| A | Sí | 17 horarios | **Sí** |
| B | No | 9 horarios | **Sí**, pero sólo se desbloquea en horas de clase |
| C | No | ninguna | **No todavía** |

En un laboratorio sin uso libre ni clases, **las PCs bloqueadas quedarían
inservibles**: nadie tendría cómo desbloquearlas salvo que el encargado las asigne
una por una desde el Monitor. Para habilitarlo, primero activa *"permite uso
libre"* en Laboratorios o cárgale sus horarios.

El número de cada laboratorio aparece en el sistema, en *Centros de Cómputo*,
debajo de su nombre.

### Instalación

En cada computadora, **doble clic en `INSTALAR-CON-BLOQUEO.bat`**. Pregunta tres
cosas:

1. El número de laboratorio (el de la tabla de arriba, o el que muestra el sistema
   para un laboratorio nuevo).
2. El número de **esa** computadora dentro del laboratorio.
3. Si la cuenta con la que entran los alumnos **es de administrador**. Para
   ayudarte, antes de preguntar enseña las cuentas del equipo y cuáles son de
   administrador.

El número de la computadora tiene que coincidir con el que el alumno elige en el
sistema al registrarse. Si las máquinas ya tienen etiqueta física, usa ese mismo
número; si no, conviene numerarlas y pegarles una etiqueta antes de empezar.

**Anota qué número le diste a cada equipo** para no repetirlos. Dos máquinas con el
mismo número harían que al registrarse una se desbloqueara también la otra.

Los equipos que ya tenían una versión anterior del bloqueo **hay que reinstalarlos**
con el `.bat` para que tomen los cambios. No hace falta tocar el servidor.

### Cómo entra el personal a un equipo bloqueado

En la pantalla de bloqueo, **si quien inicia sesión es administrador, encargado o
profesor, el equipo se libera solo**. No hay que registrar nada ni pedirle la
máquina a nadie: sirve para instalar programas dentro del perfil de alumnos,
revisar una computadora o preparar una clase.

El permiso dura **mientras esa persona tenga su sesión abierta en el sistema**. Al
cerrar sesión, el equipo vuelve a bloquearse con el aviso de siempre. Y si alguien
se va sin cerrarla, el permiso se cancela en el siguiente inicio de sesión de
Windows, para que no quede un equipo abierto al día siguiente.

Para que esto funcione, la computadora se identifica al abrir el sistema
(`/redirect?equipo=1-33`, laboratorio 1 y máquina 33). Entrar al sistema desde
otra computadora cualquiera no libera nada.

La otra vía sigue disponible: asignar la máquina desde el **Monitor de
Laboratorios**, que funciona incluso en los laboratorios sin uso libre.

### Cuentas de administrador de Windows

- **Si los alumnos usan una cuenta normal** (respuesta *N*, lo recomendable): el
  bloqueo no se aplica a las cuentas de administrador, así que el técnico entra
  con la suya y el equipo queda libre para mantenimiento.
- **Si los alumnos usan una cuenta de administrador** (respuesta *S*): el bloqueo
  se aplica a **todas** las cuentas, incluida la del técnico. Además se instala
  una segunda tarea, la *guardia*, que corre con permisos elevados: en estas
  cuentas el Administrador de tareas se abre elevado y sin ella no se podría
  cerrar.

  Para hacer mantenimiento en ese caso basta con iniciar sesión en el sistema con
  tu cuenta desde esa pantalla, como se explica arriba.

### Qué pasa si se cae la red

- **Al encender**, se espera a la red hasta **90 segundos** con la pantalla de
  *"Conectando…"*. Si no hay conexión, la pantalla lo dice con una cuenta
  regresiva (*"el equipo se liberará en 1:15"*), para que nadie reinicie la PC
  creyendo que se trabó. Al llegar a cero, el bloqueo se levanta.
- **Si la red se cae con el equipo ya bloqueado**, se levanta al minuto sin
  respuesta.
- **Si la red se cae mientras alguien trabaja**, no pasa nada: sigue desbloqueado.

Es deliberado: más vale que alguien trabaje sin registrar a que un corte de red
deje el laboratorio entero parado. En cuanto el sistema vuelve a responder, si esa
máquina no tiene a nadie registrado, avisa y se vuelve a bloquear.

### Uso Libre que se queda abierto al apagar

Si un alumno se va sin terminar su Uso Libre y la PC se apaga (sola por
inactividad, a mano o por un corte de luz), **el sistema lo cierra solo**:

- **Si la PC deja de reportarse 10 minutos**, se da por apagada.
- **Si alguien la vuelve a encender antes**, se cierra al iniciar sesión.

La hora de salida es la del último reporte de la PC, es decir, cuando se apagó. Si
la PC se apagó sola por inactividad, esa hora es unos 30 minutos después de que
el alumno se fue.

Si lo que falló fue **la red** y no la PC, al volver la red el Uso Libre se reabre
solo y el alumno sigue trabajando sin enterarse.

Sólo se aplica a las PC con este script instalado. En las demás, el Uso Libre se
sigue cerrando como siempre: el alumno con *Terminar sesión*, o el encargado desde
el Monitor de Laboratorios.

Tras un apagón, el alumno de Uso Libre **se vuelve a registrar** y la PC se
desbloquea como siempre.

### Si la PC se apaga a media clase

**La asistencia de clase no se cierra al apagar.** Al volver a encender, la PC se
desbloquea sola en unos segundos, sin que el alumno haga nada, mientras dure su
clase (más los 15 minutos de margen). No tiene que volver a registrarse: si lo
intenta, el sistema le dice que ya registró su asistencia.

Si esa PC ya no enciende y el alumno se pasa a otra, la nueva sigue bloqueada,
porque su asistencia quedó con el número de la anterior. El encargado se la libera
desde el Monitor asignándosela a su matrícula.

### Lo que este bloqueo no puede hacer

- **No puede impedir Ctrl+Alt+Supr.** Windows se reserva esa combinación. Desde ahí
  se puede cerrar sesión o cambiar de usuario, pero no usar el equipo: el
  Administrador de tareas se cierra solo.
- **No cierra los programas del alumno anterior.** Al volver a bloquear, lo que
  dejó abierto queda detrás de la pantalla del sistema, fuera de alcance; el
  siguiente alumno lo verá al registrarse. El apagado por inactividad sí lo cierra
  todo.

Es un recordatorio firme, no una barrera infranqueable. Frena a la gran mayoría; a
quien de verdad quiera saltárselo, no. Para algo más estricto haría falta una
directiva de grupo aplicada por el área de redes.

### Comprobar que quedó bien

```powershell
Get-ScheduledTask -TaskName 'LabGate*' | Format-Table TaskName, State
```

Debe aparecer `LabGate - Equipo de centro de computo` y, si respondiste *S* a lo de
administrador, también `LabGate - Guardia del bloqueo`.

### Quitarlo

`DESINSTALAR.bat` lo quita todo, bloqueo y guardia incluidos. Y si solo quieres el
apagado por inactividad sin bloqueo, reinstala con `INSTALAR.bat`.

---

## Instalar en muchos equipos

Si el instituto tiene dominio de Active Directory, en lugar de ir máquina por máquina
se puede desplegar por directiva de grupo: copiar el script a una carpeta compartida
y crear una tarea que lo ejecute con `-Instalar`. Eso lo puede armar el administrador
de la red con este mismo script.

**Nota importante si hay dominio:** la configuración de la página de inicio se escribe
en `HKLM\SOFTWARE\Policies`, que es el mismo sitio que administra la directiva de
grupo. Si existe alguna GPO que gestione Chrome o Edge, puede sobrescribir estos
valores en el siguiente refresco. En ese caso conviene configurar la página de inicio
directamente por GPO y ejecutar el script con `-SinApagado` desactivado pero sin
depender de esa parte.

Sin dominio, la instalación es manual: unos treinta segundos por equipo.
