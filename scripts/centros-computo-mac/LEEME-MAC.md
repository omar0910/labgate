# Centro de Cómputo LabGate: instalación en las computadoras Mac

Es la versión para Mac del script de Windows (`scripts/centros-computo`). En cada Mac del laboratorio hace lo mismo:

1. **Al iniciar sesión, abre el sistema** en Google Chrome.
2. **Bloquea el equipo hasta que el alumno se registre.** El sistema queda a pantalla completa hasta que el alumno registra su asistencia o su entrada de Uso Libre en esa máquina. Cuando la sesión termina, avisa 30 segundos y vuelve a bloquearse.
3. **Apaga el equipo si nadie lo usa** durante 30 minutos. Antes muestra un aviso de 60 segundos, y basta con mover el ratón para cancelarlo.

---

## Antes de empezar

Necesitas:

- **La contraseña de administrador de la Mac.** Es la de la cuenta con la que se instalan programas en ese equipo.
- **Google Chrome instalado en la Mac.** Sin Chrome no hay pantalla de bloqueo. Para comprobarlo, abre el Finder y entra a *Aplicaciones*. Si no aparece, descárgalo desde google.com/chrome.
- **La carpeta `centros-computo-mac` completa en una memoria USB.** Copia la carpeta entera, no archivos sueltos.
- **El número que tendrá cada Mac en el laboratorio.** No puede repetir el de ninguna otra computadora del mismo laboratorio, incluidas las Windows.

- **El número del laboratorio.** Aparece en el sistema, en *Centros de Cómputo*, debajo del nombre de cada laboratorio.

### Las teclas de la Mac

| En la Mac | Equivale en Windows a |
|---|---|
| **⌘ Comando** (cmd), junto a la barra espaciadora | Ctrl |
| **⌘ + Espacio** | Botón de Inicio → buscar |
| **⌘ + Tab** | Alt + Tab |
| **Control + C** en la Terminal | Ctrl + C (detener) |
| **Menú Apple** (la manzana, arriba a la izquierda) | Botón de Inicio (apagar, cerrar sesión) |

---

## Instalación, paso a paso

Se hace en cada Mac, una por una.

**1. Entra con la cuenta de administrador** y conecta la memoria USB. Aparece en el escritorio y en la barra lateral del Finder, en *Ubicaciones*.

**2. Abre la Terminal:** pulsa **⌘ + Espacio**, escribe `Terminal` y pulsa **Enter**. Se abre una ventana en la que se escriben órdenes.

**3. Entra a la carpeta de la memoria.** Esta es la forma más fácil:

- En la Terminal escribe `cd` seguido de **un espacio**, y todavía no pulses Enter.
- Abre la memoria en el Finder y **arrastra la carpeta `centros-computo-mac` hasta la ventana de la Terminal**. Se escribe sola la ruta completa.
- Ahora sí, pulsa **Enter**.

**4. Ejecuta el instalador.** Escribe lo siguiente y pulsa **Enter**:

```
sudo bash instalar-mac.sh
```

**5. Escribe la contraseña de administrador** y pulsa **Enter**. Mientras la escribes **no se ve nada, ni puntos ni asteriscos**. Es normal: sí se está escribiendo.

**6. Responde las preguntas**, igual que en `INSTALAR-CON-BLOQUEO.bat` de Windows:

- ¿Bloquear hasta que el alumno se registre? → `S`
- Número de laboratorio → por ejemplo `1`
- Número de esta computadora → por ejemplo `21`
- ¿La cuenta de los alumnos es de administrador? El instalador muestra la lista de cuentas de la Mac para que lo veas. Responde `N` si es una cuenta normal, o `S` si es de administrador.
- ¿Es correcto? → `S`

**7. Lee el resumen.** Si dice **"Listo"**, ya quedó. Anota el número de la Mac. Ya puedes retirar la memoria. Para expulsarla, arrástrala a la papelera del Dock.

**8. Compruébalo:** abre el menú Apple → *Cerrar sesión* y entra con la cuenta de los alumnos. Debe aparecer la pantalla negra "Conectando con el sistema…" y después el login del sistema a pantalla completa.

> macOS puede mostrar una notificación como **"Elemento de fondo añadido"** o **"bash puede ejecutarse en segundo plano"**. Es normal: es el vigilante que se acaba de instalar. No lo desactives.

---

## Qué pasa con el equipo bloqueado

- El alumno solo ve el sistema. Al registrar su asistencia o su Uso Libre **en esa máquina**, el equipo se libera en unos 5 segundos y el sistema queda abierto en una ventana normal, con su sesión ya iniciada.
- **Diferencia con Windows:** en Mac no se pueden desactivar teclas como ⌘+Tab sin conceder permisos especiales a mano en cada equipo. Por eso aquí se hace de otra forma: si el alumno cambia a otro programa (con ⌘+Tab, el Dock o Spotlight), **ese programa se cierra y el sistema vuelve al frente en menos de un segundo.** La Terminal, el Monitor de Actividad y la Configuración del Sistema se cierran en cuanto aparecen.
- Si alguien cierra la pantalla de bloqueo (⌘+Q), se vuelve a abrir sola en unos segundos.
- Con la sesión del alumno abierta, si cierra la ventana del sistema (la X roja, ⌘+W o ⌘+Q), **se vuelve a abrir sola en unos 10 segundos, con su sesión puesta**, igual que en Windows. En Mac, la X roja cierra la ventana pero Chrome sigue abierto, y si después se pulsa su icono en el Dock abre una pestaña nueva en blanco. El vigilante le pregunta a Chrome qué pestañas tiene: si no queda ninguna, o solo pestañas nuevas en blanco, vuelve a abrir el sistema.
- Si el servidor no responde, el equipo **no se queda inservible**. Al encender se libera solo a los **90 segundos**, y mientras tanto la pantalla muestra una cuenta regresiva. Si la red se cae más tarde, se libera a **1 minuto**. Si se cae mientras alguien trabaja, no pasa nada.
- Si el alumno se va sin terminar su Uso Libre y la Mac se apaga, **el sistema lo cierra solo** cuando la Mac lleva 10 minutos sin reportarse, o antes si alguien la vuelve a encender. Si lo que falló fue la red, el Uso Libre se reabre solo al volver. Está explicado en el LEEME de Windows, en *"Uso Libre que se queda abierto al apagar"*.

### Cómo sale el personal del Centro de Cómputo

Igual que en Windows:

1. **Entrando con una cuenta de administrador de la Mac.** Esas cuentas no se bloquean, salvo que al instalar se haya respondido `S` a la pregunta de los administradores.
2. **Iniciando sesión en el sistema desde la pantalla de bloqueo** con una cuenta de administrador, encargado o profesor. El equipo se libera solo mientras esa persona tenga su sesión abierta en el sistema.
3. **Asignando la máquina desde el Monitor de Laboratorios.**

---

## Probar sin riesgo (opcional)

Para ver el aviso de apagado sin que el equipo llegue a apagarse. Se hace en la Terminal, dentro de la carpeta de la memoria (pasos 2 y 3), y **sin `sudo`**:

```
bash probar-mac.sh
```

Deja de tocar la Mac 2 minutos y aparecerá el aviso. Con esta prueba el equipo **nunca** se apaga.

Para probar también la pantalla de bloqueo, añade el laboratorio y el número de la máquina:

```
bash probar-mac.sh 1 21
```

Para detener la prueba, vuelve a la Terminal con **⌘ + Tab** y pulsa **Control + C**.

---

## Cambiar algo o quitarlo

- **Cambiar el número de la máquina u otra respuesta:** vuelve a ejecutar `sudo bash instalar-mac.sh`. Reemplaza la instalación anterior.
- **Cambiar los tiempos** (los mismos valores que en Windows):
  ```
  sudo bash instalar-mac.sh --minutos 45 --aviso 60
  sudo bash instalar-mac.sh --no-apagar-entre 07:00-14:00
  sudo bash instalar-mac.sh --sin-apagado
  ```
- **Quitarlo de la Mac:**
  ```
  sudo bash desinstalar-mac.sh
  ```
  Después cierra sesión y vuelve a entrar.

Los cambios se aplican en el **siguiente inicio de sesión**.

---

## Si algo no funciona

**Ver qué ha hecho el equipo (bitácora).** En la Terminal:

```
tail -n 40 /Library/Logs/LabGate/centro-computo.log
```

Muestra las últimas 40 líneas: cuándo se bloqueó, cuándo se liberó, si no había red, si se apagó, etc.

| Problema | Causa probable y solución |
|---|---|
| `$'\r': command not found` al instalar | Los archivos se copiaron con el formato de línea de Windows. En la carpeta de la memoria ejecuta `sed -i '' 's/\r$//' *.sh` y vuelve a instalar. |
| "Hace falta instalar con permisos de administrador" | Faltó `sudo` al principio: `sudo bash instalar-mac.sh`. |
| "Sorry, try again" al escribir la contraseña | La contraseña no es correcta, o la cuenta no es de administrador. |
| Al entrar como alumno no se bloquea | La cuenta de los alumnos es de administrador y respondiste `N`. Vuelve a instalar y responde `S`. También puede faltar Google Chrome: revisa la bitácora. |
| Se queda en "Esperando la red del instituto" | La Mac no llega al servidor. Revisa que esté en la red del instituto (no en otra Wi-Fi). |
| El aviso de apagado aparece, pero no se apaga | El instalador no pudo dar el permiso para apagar; lo indica al final como AVISO. |

---

## Qué instala y dónde (para el personal técnico)

| Qué | Dónde |
|---|---|
| El vigilante y sus archivos de apoyo | `/Library/Application Support/LabGate/` |
| La configuración de la Mac (laboratorio, número, tiempos) | `/Library/Application Support/LabGate/config` |
| El arranque en cada inicio de sesión (el equivalente de la tarea programada de Windows) | `/Library/LaunchAgents/mx.edu.labgate.centrocomputo.plist` |
| El permiso para apagar (únicamente `shutdown -h now`) | `/etc/sudoers.d/labgate-apagado` |
| La bitácora | `/Library/Logs/LabGate/centro-computo.log` |
| El perfil de Chrome del sistema (se borra en cada sesión) | `~/.labgate/` en la carpeta de cada usuario |

Notas:

- Si alguien cierra el vigilante, macOS lo vuelve a lanzar a los 10 segundos. Si el alumno está trabajando con su sesión abierta, no se le interrumpe.
- El equipo **no se apaga** si hay otra cuenta con la sesión abierta en la misma Mac, ni mientras macOS instala actualizaciones.
- Como en Windows, la inactividad se mide con el teclado y el ratón: un video o una descarga larga no cuentan como uso. Para eso está el aviso previo.
- La dirección del servidor se elige con el mismo criterio que en Windows: la que entra sin avisos de certificado desde la red del laboratorio.
