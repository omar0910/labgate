/**
 * Capturas de pantalla de las vistas del sistema.
 *
 * Sirve para revisar cambios de diseño sin ir pantalla por pantalla a mano:
 * abre Chrome, inicia sesión y guarda una imagen de cada vista, además de
 * avisar si alguna página tiene errores de JavaScript o se desborda a lo ancho.
 *
 * Uso:
 *   1. Levanta el servidor:  php artisan serve --port=8123
 *   2. Ejecuta:              LOGIN=tu_usuario PASSWORD=tu_clave npm run capturas
 *
 * Variables de entorno:
 *   LOGIN      usuario para iniciar sesión (obligatoria)
 *   PASSWORD   contraseña de ese usuario   (obligatoria)
 *   BASE_URL   dirección del servidor      (por defecto http://127.0.0.1:8123)
 *   SALIDA     carpeta destino             (por defecto storage/app/capturas)
 *   RUTAS      lista separada por comas    (por defecto las de abajo)
 *
 * Nota para Git Bash: antepón MSYS_NO_PATHCONV=1 al usar RUTAS, porque si no
 * convierte "/admin/reportes" en una ruta de Windows y la navegación falla.
 *
 * Requiere Google Chrome instalado; no descarga navegadores aparte.
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8123';
const USUARIO = process.env.LOGIN;
const CLAVE = process.env.PASSWORD;
const SALIDA = process.env.SALIDA || path.join(RAIZ, 'storage', 'app', 'capturas');

// A propósito no se guarda ninguna credencial en el repositorio.
if (!USUARIO || !CLAVE) {
    console.error(
        'Faltan credenciales.\n' +
        'Ejecuta:  LOGIN=tu_usuario PASSWORD=tu_clave npm run capturas\n' +
        '(el usuario debe tener rol Administrador para ver todas las vistas)'
    );
    process.exit(1);
}

// Vistas a capturar. Cada entrada: [nombre de archivo, ruta].
const RUTAS_POR_DEFECTO = [
    ['reportes', '/admin/reportes'],
    ['horarios', '/admin/horarios'],
    ['dashboard-admin', '/admin/dashboard'],
    ['alumnos', '/admin/alumnos'],
    ['materias', '/admin/materias'],
    ['monitor', '/monitor/vivo'],
];

const paginas = process.env.RUTAS
    ? process.env.RUTAS.split(',').map(r => [r.trim().replace(/\W+/g, '-').replace(/^-|-$/g, '') || 'pagina', r.trim()])
    : RUTAS_POR_DEFECTO;

fs.mkdirSync(SALIDA, { recursive: true });

const incidencias = [];

const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({
    viewport: { width: 1440, height: 900 },
});
const pagina = await contexto.newPage();

// Si algo truena en el navegador (por ejemplo la gráfica), que quede registrado
pagina.on('console', m => {
    if (m.type() === 'error') incidencias.push(`[consola] ${m.text()}`);
});
pagina.on('pageerror', e => incidencias.push(`[js] ${e.message}`));

// --- Iniciar sesión ---
await pagina.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await pagina.fill('input[name="login"]', USUARIO);
await pagina.fill('input[name="password"]', CLAVE);
await Promise.all([
    pagina.waitForNavigation({ waitUntil: 'networkidle' }),
    // El del formulario de usuario y contraseña: en modo demostración la pantalla
    // tiene además los botones para entrar con un clic.
    pagina.click('form:has(input[name="login"]) button[type="submit"]'),
]);

if (pagina.url().includes('/login')) {
    console.error(`No se pudo iniciar sesión como "${USUARIO}". Revisa LOGIN y PASSWORD.`);
    await navegador.close();
    process.exit(1);
}

console.log(`Sesión iniciada como ${USUARIO}\n`);
console.log('VISTA'.padEnd(20), 'ALTO'.padStart(8), '  DESBORDE');
console.log('-'.repeat(44));

for (const [nombre, ruta] of paginas) {
    try {
        const respuesta = await pagina.goto(`${BASE}${ruta}`, { waitUntil: 'networkidle' });
        if (respuesta && respuesta.status() >= 400) {
            incidencias.push(`[${nombre}] HTTP ${respuesta.status()} en ${ruta}`);
        }
        // Margen para que terminen de dibujarse las gráficas
        await pagina.waitForTimeout(1200);

        await pagina.screenshot({ path: path.join(SALIDA, `${nombre}-completa.png`), fullPage: true });
        await pagina.screenshot({ path: path.join(SALIDA, `${nombre}-visible.png`) });

        const alto = await pagina.evaluate(() => document.body.scrollHeight);
        const desborda = await pagina.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth
        );
        if (desborda) incidencias.push(`[${nombre}] la página se desborda horizontalmente`);

        console.log(nombre.padEnd(20), `${alto}px`.padStart(8), `  ${desborda ? 'SÍ' : 'no'}`);
    } catch (e) {
        incidencias.push(`[${nombre}] ${e.message}`);
        console.log(nombre.padEnd(20), 'ERROR'.padStart(8));
    }
}

await navegador.close();

console.log('\n--- Incidencias ---');
console.log(incidencias.length ? incidencias.join('\n') : 'ninguna');
console.log(`\nImágenes guardadas en: ${SALIDA}`);
