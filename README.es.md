<div align="center">

<img src="public/img/logo.png" width="96" alt="Logotipo de LabGate">

# LabGate

**Control de acceso y asistencia para laboratorios de cómputo universitarios**

El alumno se registra en la computadora donde se sienta, la computadora se desbloquea y el personal ve cada laboratorio en vivo.

[Demostración en línea](DEMO_URL) · [English](README.md)

[![Pruebas](https://github.com/omar0910/labgate/actions/workflows/pruebas.yml/badge.svg)](https://github.com/omar0910/labgate/actions/workflows/pruebas.yml)
![Laravel](https://img.shields.io/badge/Laravel-10-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![PowerShell](https://img.shields.io/badge/PowerShell-agente%20de%20laboratorio-5391FE?logo=powershell&logoColor=white)

</div>

![Monitor de laboratorios en vivo](docs/capturas/03-monitor-en-vivo.png)

## Acerca del proyecto

LabGate es el sistema que diseñé y desarrollé como mi proyecto de residencia
profesional de Ingeniería en Sistemas Computacionales. Sustituyó las listas en
papel de los laboratorios de cómputo de una universidad pública de México, donde
está en producción con 4 laboratorios y más de 1,000 cuentas de usuario.

Este repositorio es la **edición de demostración**: el mismo código, con una
institución ficticia y datos generados, para que cualquiera pueda probarlo. No
incluye nombres reales, registros de alumnos ni detalles de la infraestructura.

**El problema que resuelve.** La asistencia a los laboratorios se llevaba en
papel, nadie sabía qué computadoras estaban en uso o descompuestas, y no había
forma de saber quién había usado un equipo. LabGate liga cada sesión a una
persona, una computadora y una hora, y con eso ofrece monitoreo en vivo y reportes.

## Pruébalo

Abre la [demostración en línea](DEMO_URL) y elige un rol. Un clic, sin registrarse:

| Rol | Cuenta de demostración | Qué ver |
|---|---|---|
| Administrador | `admin.demo` | Panel, monitor en vivo, horarios, reportes |
| Encargado | `encargado.demo` | Clases del día, reportes de fallas, mantenimiento |
| Profesor | `profesor.demo` | Clases de hoy, pase de lista, estadísticas de clase |
| Alumno | `alumno.demo` | Registro de asistencia, uso libre, progreso |

La contraseña de todas es `demo1234`. Los datos son ficticios y se reinician cada
noche. Mientras la demostración está encendida, una tarea simula el día: los
alumnos se registran al empezar cada clase y las sesiones de uso libre abren y
cierran, así que el monitor en vivo siempre tiene algo que mostrar (hay clases de
lunes a viernes, de 7:00 a 21:00, hora UTC−7).

## Funciones

**Alumnos**
- Registran su asistencia desde la computadora del laboratorio, o con su propia laptop.
- Abren y cierran sesiones de uso libre fuera del horario de clase.
- Reportan una computadora con falla en dos clics.
- Ven su asistencia por materia, clase por clase.

**Profesores**
- Ven sus clases de hoy y quién ya se registró.
- Pasan lista o la corrigen, con faltas justificadas.
- Calendario semanal, historial y estadísticas por clase, exportables a PDF y Excel.

**Encargados y administradores**
- Monitor en vivo: cada computadora de cada laboratorio, quién la usa y para qué.
- Horarios con detección de choques (laboratorio y profesor) y reservas especiales.
- Importadores inteligentes de Excel para alumnos, profesores, grupos y el
  horario completo, con una vista previa de lo que va a cambiar antes de guardar.
- Mesa de ayuda para fallas y mantenimiento preventivo, con contador de usos por computadora.
- Reportes por docente, materia, alumno, laboratorio y carrera, en PDF y Excel.
- Resumen semanal por correo para profesores y alumnos.

**Agente de las computadoras del laboratorio (Windows y macOS)**
- Mantiene la computadora bloqueada en la pantalla de acceso hasta que el alumno se registra en *esa* máquina.
- La vuelve a bloquear al terminar la sesión y la apaga tras un tiempo sin uso.
- Se recupera solo de cortes de red, apagones e intentos de cerrarlo.

## Capturas

| | |
|---|---|
| ![Acceso](docs/capturas/01-acceso.png) Acceso, con entrada de un clic a la demostración | ![Panel del administrador](docs/capturas/02-panel-administrador.png) Panel del administrador |
| ![Horarios](docs/capturas/04-horarios.png) Horario semanal por laboratorio | ![Reportes](docs/capturas/05-reportes.png) Reportes |
| ![Profesor](docs/capturas/06-profesor-clases-de-hoy.png) Profesor: clases de hoy | ![Alumno](docs/capturas/07-alumno-inicio.png) Alumno: registro y uso libre |
| ![Progreso del alumno](docs/capturas/08-alumno-progreso.png) Alumno: progreso de asistencia | ![Encargado](docs/capturas/09-encargado-inicio.png) Encargado: el día de hoy |

## Lo más interesante del código

Las partes que le señalaría a quien revise el proyecto:

- **El agente de laboratorio** ([`scripts/centros-computo`](scripts/centros-computo)).
  Un script de PowerShell (unas 2,400 líneas) con un gancho de teclado de bajo
  nivel escrito en C#. Abre el navegador en modo quiosco, consulta al servidor si
  su computadora tiene una sesión activa y resuelve los casos difíciles: sin red
  al encender, un apagón a media clase, un alumno que mata el proceso. Hay una
  versión en Bash para macOS.
- **Las reglas de sesión en el servidor**
  ([`EquipoEstadoController`](app/Http/Controllers/Api/EquipoEstadoController.php),
  [`ReportesDeEquipos`](app/Support/ReportesDeEquipos.php)).
  El registro de una clase desbloquea la computadora sólo mientras dura la clase
  (más un margen); un uso libre que se quedó abierto se cierra cuando la
  computadora deja de reportarse, y se reabre si resulta que fue un corte de red
  y no un apagado.
- **El importador de horarios** ([`PlanDeHorarios`](app/Support/PlanDeHorarios.php)).
  Compara el horario de un Excel con lo que ya está guardado, empareja
  laboratorios, materias y profesores por nombre aproximado, recuerda las
  correcciones del usuario, y edita las clases que ya existen en lugar de
  crearlas de nuevo, para conservar sus asistencias.
- **Las faltas sin registro** ([`FaltasSinRegistro`](app/Support/FaltasSinRegistro.php)).
  Un alumno que nunca se registró no tiene una fila que contar. El sistema
  deduce la falta sólo cuando la clase de verdad se impartió y el alumno ya
  estaba inscrito.
- **El modo demostración** ([`app/Demo`](app/Demo), [`ModoDemo`](app/Http/Middleware/ModoDemo.php)).
  Un sembrador arma un semestre alrededor de la fecha de hoy (unos 290 alumnos,
  52 clases por semana sin choques, más de 10,000 registros de asistencia), un
  simulador mantiene vivo el día en curso, y un filtro bloquea las pocas
  acciones que dejarían la demostración inservible para el siguiente visitante.

## Tecnologías

- **Servidor:** PHP 8.1+, Laravel 10, MySQL 8, colas y tareas programadas
- **Interfaz:** Blade, Bootstrap 5.3, Vite, SweetAlert2
- **Documentos:** DomPDF (PDF), Laravel Excel (importar y exportar)
- **Agente de laboratorio:** PowerShell 5.1 con C#, Bash para macOS

## Instalación en local

Requisitos: PHP 8.1+, Composer, Node 18+, MySQL 8.

```bash
git clone https://github.com/omar0910/labgate.git
cd labgate
composer install
npm install && npm run build

cp .env.example .env        # configura DB_DATABASE, DB_USERNAME, DB_PASSWORD y DEMO=true
php artisan key:generate

php artisan migrate:fresh --seed --seeder=DemoSeeder
php artisan serve
```

Abre http://127.0.0.1:8000 y entra con cualquiera de las cuentas de arriba.

Para que la demostración tenga "vida" en local, deja corriendo las tareas
programadas en otra terminal:

```bash
php artisan schedule:work
```

Comandos útiles:

| Comando | Qué hace |
|---|---|
| `php artisan demo:reiniciar` | Borra la base y vuelve a sembrar la demostración (sólo con `DEMO=true`) |
| `php artisan demo:simular` | Registra lo que "está pasando" en este momento |
| `php artisan admin:crear` | Crea un administrador en una instalación real |

## Pruebas

99 pruebas automáticas cubren las reglas que más importan: quién entra y a
dónde, el registro de asistencia, el uso libre, lo que el servidor le contesta a
cada computadora del laboratorio (incluidos apagones y cortes de red), los
choques de horario, el mantenimiento, el buscador y el modo demostración. Corren
contra una base MySQL de verdad y con el reloj fijo, para que el resultado no
dependa de cuándo se ejecuten.

```bash
# una sola vez: crear la base de pruebas (su nombre debe terminar en _test)
mysql -u root -e "CREATE DATABASE labgate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan test
```

También se ejecutan en cada cambio con GitHub Actions
([flujo](.github/workflows/pruebas.yml)).

## Estructura del proyecto

```
app/
  Demo/            Generador de datos de demostración y simulador del día
  Http/            Controladores y filtros (roles, modo demostración, detección de la PC)
  Imports/         Importadores de Excel     Exports/   Exportaciones a Excel
  Support/         Lógica del dominio: reglas de asistencia, búsqueda, plan de horarios
config/marca.php   Nombre, institución y logotipos de la instalación
scripts/           Agente de las PC para Windows y macOS, con sus guías de instalación
tests/             Pruebas, un archivo por cada área del sistema
```

## Autor

**César Omar Ramos Martínez** — estudiante de Ingeniería en Sistemas Computacionales, México.
[GitHub](https://github.com/omar0910)

© 2026 César Omar Ramos Martínez. Publicado con fines de portafolio y revisión.
