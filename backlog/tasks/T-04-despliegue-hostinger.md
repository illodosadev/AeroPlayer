---
id: T-04
title: Despliegue en Hostinger
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 6
language: es
---

# T-04 — Despliegue en Hostinger

## Goal

Publicar la app en `https://music.illodosadev.io`, en el hosting de Hostinger del usuario, para poder usarla desde fuera de casa y desde el móvil sin afectar al sitio `illodosadev.io` de la misma cuenta. El despliegue se lanza a mano con un botón de GitHub Actions, que prueba, compila y sube la app por SSH, y los tests corren en cada push. Así cada versión publicada está probada, se puede repetir y no depende del ordenador del usuario. Sin esto no se cumple la necesidad no funcional del discovery "accesible por Internet desde fuera de casa", ni la métrica de "uso diario".

## Context

- Necesidades no funcionales del discovery: acceso por Internet desde escritorio y móvil, y aislamiento de los otros proyectos de la misma cuenta de Hostinger.
- **Pregunta abierta 1, resuelta el 2026-11-09 con la extensión MCP de Hostinger** (datos leídos de la cuenta):
  - **Plan:** "Premium Web Hosting", hosting **compartido** (CloudLinux), activo y pagado hasta 2030. El usuario Unix va en el secreto `DEPLOY_USER`, no en el repo.
  - **Sitio actual:** un solo sitio, `illodosadev.io` (raíz `~/domains/illodosadev.io/public_html`), sin subdominios, sin bases de datos y sin tareas cron.
  - **PHP del sitio:** 8.3 (8.3.36). Hay disponibles 7.3–8.5. Extensiones activas que importan: `intl`, `pdo_mysql` (`nd_pdo_mysql`), `pdo_sqlite`, `mbstring`, `bcmath`, `gd`, `zip`, `opcache`. `sodium` está desactivada.
  - **Funciones desactivadas:** las habituales de ejecución de procesos y enlaces (`exec`, `shell_exec`, `symlink`, entre otras). Qué mecanismo para lanzar procesos queda disponible se comprueba por SSH en la puesta en marcha y no se documenta en este repo público.
  - **SSL:** certificado de Hostinger de por vida activo en `illodosadev.io`, con redirección HTTP → HTTPS activada.
  - **Node.js:** las apps Node.js de Hostinger son de los planes Business y Cloud; el plan Premium no las tiene.
- **Decisiones del usuario (2026-11-09):**
  - **D14 — Mecanismo:** GitHub Actions + SSH. El CI ejecuta los tests, prepara las dependencias de producción, compila con Vite, sube por SSH con rsync y ejecuta las migraciones. El servidor no necesita Node.
  - **D15 — Cuándo:** con un botón manual (`workflow_dispatch`). Los tests corren en cada push, pero el despliegue solo sale cuando el usuario lo lanza (por ejemplo, al cerrar un cambio con `/ship`). Así no se publica trabajo a medias, porque los commits van directos a `main`.
  - **D16 — Dónde:** `music.illodosadev.io` como **subdominio del sitio `illodosadev.io`**, no como sitio aparte.
- **Esta tarea crea los dos workflows** de GitHub Actions: `ci.yml` (tests en cada push) y `deploy.yml` (despliegue manual). Ninguna otra tarea crea CI.
- **Depende de:**
  - **T-01** (base Laravel que compila y pasa sus tests) y **T-02** (login: no se publica nada sin login; el comando `app:create-owner`).
  - **T-20** (stack Docker local): su script de pruebas de humo se amplía aquí, y la base de datos local debe ser del mismo motor que la de producción (ver Pendiente).
- **Afecta a:** T-05 y T-06 (HTTPS obligatorio para la redirección OAuth de Spotify y para el DRM del SDK), T-13 (sin websockets en compartido) y T-09 (Python para YouTube Music, pregunta abierta 2). Ver "Seguimiento".
- Repositorio: `github.com/illodosadev/AeroPlayer`, **público** (`openspec/config.yaml`): nunca se suben secretos.

## Acceptance criteria

Cómo se comprueba cada escenario:
- `[humo]`: el script de pruebas de humo (ver Reglas propuestas), que el workflow ejecuta al final de cada despliegue y que también se puede ejecutar a mano.
- `[workflow]`: una ejecución real de GitHub Actions, enlazada en el `design.md` del cambio. Las pistas para provocar cada caso están en las Notas.
- `@manual`: a mano, anotado en el `design.md` del cambio.

```gherkin
# --- Respuesta y HTTPS ----------------------------------------------------------

# [humo]
Scenario: La app responde en su subdominio con HTTPS
  Given la app desplegada
  When se pide "https://music.illodosadev.io/login"
  Then la respuesta tiene estado 200
  And el HTML contiene el atributo "data-page" de Inertia con el componente de la página de login
  And el certificado TLS es válido para "music.illodosadev.io" (curl sin "--insecure" no da error)

# [humo]
Scenario: HTTP redirige a HTTPS
  Given la app desplegada
  When se pide "http://music.illodosadev.io/login" sin seguir redirecciones
  Then la respuesta es una redirección 301 o 302 a "https://music.illodosadev.io/login"

# [humo]
Scenario: La hoja de estilos compilada se sirve
  Given la app desplegada
  When se pide la hoja de estilos que enlaza el HTML de "/login"
  Then la respuesta tiene estado 200 y el tipo "text/css"

# [humo]
Scenario: La cookie de sesión es segura
  Given la app desplegada
  When se pide "https://music.illodosadev.io/login"
  Then la cookie de sesión tiene los atributos "Secure", "HttpOnly" y "SameSite=Lax"

# [humo]
Scenario: La versión desplegada se puede consultar
  Given la app desplegada desde el commit S
  When se pide "https://music.illodosadev.io/login"
  Then la respuesta tiene la cabecera "X-App-Version" con el SHA corto de S

# --- Aislamiento del sitio principal ---------------------------------------------

# [humo]
Scenario: El sitio illodosadev.io sigue respondiendo
  Given el estado de "https://illodosadev.io/" anotado en la variable del repositorio MAIN_SITE_STATUS
  When termina un despliegue
  Then "https://illodosadev.io/" responde con ese mismo estado

@manual
Scenario: La versión de PHP del sitio principal no cambia sin decidirlo
  Given la versión de PHP de "illodosadev.io" anotada en el runbook antes del primer despliegue (8.3 el 2026-11-09)
  When termina el primer despliegue
  Then "hosting_php_get" (o hPanel) muestra para "illodosadev.io" la versión anotada en el runbook
  # Si el usuario decide cambiarla (ver Pendiente), se actualiza antes el valor anotado en el runbook.

# [workflow]
Scenario Outline: Una ruta de despliegue peligrosa se rechaza antes de tocar nada
  Given el secreto DEPLOY_PATH <valor>
  When el usuario lanza el despliegue
  Then el workflow termina en rojo en el paso "Check deploy path", antes de rsync
  And "https://illodosadev.io/" sigue respondiendo con el estado de MAIN_SITE_STATUS

  Examples:
    | valor                                                                 |
    | vacío                                                                 |
    | igual a "~/domains/illodosadev.io/public_html"         |
    | dentro de "public_html" ("~/domains/illodosadev.io/public_html/music") |
    | apuntando a una carpeta sin el fichero marcador ".aeroplayer-deploy-target" |

# --- Nada interno al descubierto ------------------------------------------------

# [humo]
Scenario Outline: Los ficheros internos no se sirven
  Given la app desplegada
  When se pide "https://music.illodosadev.io<ruta>" con "curl --path-as-is"
  Then la respuesta tiene estado 403 o 404
  And el cuerpo no contiene "APP_KEY", "DB_PASSWORD" ni "SPOTIFY_CLIENT_SECRET"

  Examples:
    | ruta                         |
    | /.env                        |
    | /.git/config                 |
    | /composer.json               |
    | /artisan                     |
    | /vendor/autoload.php         |
    | /storage/logs/laravel.log    |
    | /../.env                     |

# [humo]
Scenario: Los errores no exponen detalles en producción
  Given la app desplegada con APP_DEBUG=false
  When se pide "https://music.illodosadev.io/ruta-que-no-existe"
  Then la respuesta tiene estado 404
  And no contiene trazas de error, rutas de ficheros del servidor ("/home/<usuario>") ni valores de configuración

# --- Los workflows ----------------------------------------------------------------

# [workflow]
Scenario: Un push a main ejecuta los tests y no despliega
  Given los workflows "CI" y "Deploy"
  When se hace push de un commit a "main"
  Then se ejecuta "CI" con los tests de Pest
  And "Deploy" no se ejecuta y ningún paso conecta con el servidor

# [workflow]
Scenario: El despliegue se lanza a mano y publica la versión elegida
  Given el workflow "Deploy" y los secretos de despliegue configurados
  When el usuario lo lanza con el botón "Run workflow" sobre "main"
  Then el workflow ejecuta los tests con las dependencias de desarrollo
  And prepara aparte el paquete de producción ("composer install --no-dev" y el build de Vite)
  And sube el paquete por SSH, ejecuta "php artisan migrate --force" y las pruebas de humo pasan
  And la cabecera "X-App-Version" de producción es el SHA corto de "main"

# [workflow]
Scenario: Primer despliegue en un servidor vacío
  Given la carpeta de despliegue con el ".env" y el fichero marcador, pero sin código de la app
  When el usuario lanza el despliegue
  Then el workflow no ejecuta "php artisan down" (todavía no hay "artisan")
  And crea "storage/framework/cache", "storage/framework/sessions", "storage/framework/views" y "storage/logs"
  And termina en verde con las pruebas de humo pasando

# [workflow]
Scenario: Falta el .env en el servidor
  Given la carpeta de despliegue con el fichero marcador y sin ".env"
  When el usuario lanza el despliegue
  Then el workflow termina en rojo con "Missing .env on the server. Follow the Despliegue runbook."
  And el paso de rsync no se ejecuta

# [workflow]
Scenario: Si los tests fallan, no se toca el servidor
  Given una rama temporal con un test que falla
  When el usuario lanza el despliegue con la entrada "ref" = esa rama
  Then el workflow termina en rojo antes de conectar por SSH
  And la cabecera "X-App-Version" de producción sigue siendo la de antes

# [workflow]
Scenario Outline: Falla la conexión con el servidor
  Given <causa>
  When el usuario lanza el despliegue
  Then el workflow termina en rojo en el paso de conexión, con un mensaje que nombra <mensaje>
  And "/login" de producción sigue respondiendo 200 con la misma cabecera "X-App-Version"

  Examples:
    | causa                                              | mensaje                 |
    | el secreto DEPLOY_SSH_KEY vacío                    | el secreto que falta    |
    | la clave pública de despliegue quitada del servidor | el fallo de SSH        |
    | DEPLOY_KNOWN_HOSTS con una huella que no coincide  | la huella del servidor  |

# [workflow]
Scenario: Falla un paso en el servidor
  Given una rama temporal con una migración que lanza una excepción
  When el usuario la despliega con la entrada "ref"
  Then el workflow termina en rojo en el paso de migraciones
  And "https://music.illodosadev.io/login" responde 503 con la página de mantenimiento, no un 500 con código a medias
  And al volver a desplegar "main" después, "/login" responde 200

# [workflow]
Scenario: Dos despliegues a la vez
  Given un despliegue en marcha
  When el usuario lanza otro
  Then el segundo queda en espera hasta que termina el primero (grupo de concurrencia "deploy-production") y no se ejecutan a la vez
  # GitHub solo guarda una ejecución en espera por grupo: un tercer lanzamiento cancela al segundo, que estaba esperando.

# [workflow]
Scenario: Volver a una versión anterior
  Given dos commits A y B en "main", con B desplegado y una migración nueva en B
  When el usuario lanza el despliegue con la entrada "ref" = el SHA de A
  Then la cabecera "X-App-Version" de producción es el SHA corto de A
  And la tabla que crea la migración de B sigue existiendo en la base de datos ("php artisan db:table <tabla>" en el servidor la muestra), y su fila sigue en la tabla "migrations"

@manual
Scenario: Las pruebas de humo fallan
  Given el stack local de T-20 con la base de datos parada
  When se ejecuta "docker/smoke.sh http://localhost:8081"
  Then sale con un código distinto de 0 y nombra la comprobación que falló
  # El workflow usa el mismo script, así que termina en rojo con ese mismo mensaje.

# --- Secretos en un repositorio público ----------------------------------------

# [workflow]
Scenario: El escaneo de secretos pasa sobre todo el historial
  Given el repositorio público
  When "CI" ejecuta gitleaks sobre todo el historial de git
  Then el paso termina en verde
  And ".env" está en ".gitignore"
  And los workflows solo usan los secretos DEPLOY_* a través de "${{ secrets.* }}"

@manual
Scenario: Los valores reales no aparecen en el registro de la ejecución
  Given el registro completo de la última ejecución de "Deploy"
  When el usuario busca en él los valores reales de APP_KEY, DB_PASSWORD, SPOTIFY_CLIENT_SECRET y la clave privada SSH
  Then no aparece ninguno

# --- Puesta en marcha (una vez) ----------------------------------------------------

@manual
Scenario: Primera puesta en marcha siguiendo el runbook
  Given el runbook "Despliegue" del README y la cuenta de Hostinger sin el subdominio
  When el usuario sigue el runbook: crea el subdominio, instala el SSL, crea la base de datos, activa SSH, sube la clave de despliegue, crea la carpeta de despliegue con el fichero marcador, escribe el ".env" del servidor, lanza el primer despliegue y ejecuta "php artisan app:create-owner" por SSH
  Then puede iniciar sesión en "https://music.illodosadev.io/login" con la cuenta del propietario
  And el "design.md" del cambio anota la fecha, la versión de PHP del subdominio, el motor y la versión de la base de datos, el puerto SSH y que "rsync" existe en el servidor

@manual
Scenario: La app funciona en el móvil fuera de casa
  Given el móvil del usuario con datos móviles, sin Wi-Fi
  When abre "https://music.illodosadev.io" e inicia sesión
  Then ve la página principal sin avisos de certificado
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (2026-11-09)**

- **Pregunta abierta 1 resuelta:** hosting compartido Premium (datos de la cuenta en el Context).
- **D14:** GitHub Actions + SSH. El build de Vite se hace en el runner de CI, porque el plan no tiene Node.js, y los assets compilados se suben con rsync.
- **D15:** el despliegue es manual (`workflow_dispatch`). Los tests corren en cada push.
- **D16:** `music.illodosadev.io` es un subdominio del sitio `illodosadev.io`. Comparten usuario Unix y cuenta. El aislamiento es por carpeta, no por usuario, y por eso existe la guarda de la ruta de despliegue.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Estructura en el servidor:**
  - El código de la app vive **fuera de `public_html`**, por ejemplo en `~/domains/illodosadev.io/aeroplayer`, y el directorio del subdominio apunta a su carpeta `public`. Si el directorio del subdominio no puede apuntar fuera de `public_html` (ver Pendiente), la alternativa es un enlace simbólico creado por SSH (`ln -s`), porque la función `symlink` de PHP está desactivada pero el comando del shell no.
  - En la raíz de la carpeta de despliegue hay un fichero marcador vacío `.aeroplayer-deploy-target`, creado a mano en la puesta en marcha.
  - El `.env` de producción vive solo en el servidor, en la carpeta de la app (fuera del directorio público), con permisos `600`. rsync lo excluye, igual que `storage/` y el marcador, para no borrarlos ni pisarlos.
- **Guarda de la ruta de despliegue (paso "Check deploy path", antes de rsync):** el workflow se para si `DEPLOY_PATH` está vacío, es o contiene `public_html`, o si por SSH no existe `$DEPLOY_PATH/.aeroplayer-deploy-target`. rsync con `--delete` corre con el mismo usuario que es dueño de `illodosadev.io`, así que un error en esa ruta podría borrar el sitio principal.
- **Workflow "CI"** (`.github/workflows/ci.yml`): en cada push y en cada pull request. Instala dependencias con las de desarrollo, ejecuta Pest (SQLite en memoria) y gitleaks sobre todo el historial.
- **Workflow "Deploy"** (`.github/workflows/deploy.yml`), solo `workflow_dispatch`, con la entrada opcional `ref` (por defecto `main`; propuesta para poder volver atrás, no forma parte de D15):
  1. **Tests:** checkout de `ref`, `composer install` con las dependencias de desarrollo y Pest. Si fallan, se para.
  2. **Paquete de producción:** en una copia limpia del mismo `ref`, `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build` y el SHA corto del commit escrito en un fichero `VERSION`. PHP del runner con la misma versión menor que el servidor y sin las extensiones que el servidor no tiene (por ejemplo `sodium`, con `shivammathur/setup-php` y `extensions: :sodium`).
  3. **Conexión SSH** con la clave del secreto `DEPLOY_SSH_KEY` y la huella del servidor en `DEPLOY_KNOWN_HOSTS` (sin aceptar huellas desconocidas). Guarda de la ruta y comprobación de que existe `.env`.
  4. **En el servidor:** si ya hay `artisan`, `php artisan down --retry=15`. Después rsync del paquete con `--delete` (excluye `.env`, `storage/`, `.aeroplayer-deploy-target`, `node_modules/`, `.git/` y `tests/`), `mkdir -p` del árbol de `storage`, la comprobación de la plataforma (con `composer check-platform-reqs --no-dev` si hay Composer en el servidor; si no, el runner compara la salida de `php -v` y `php -m` del servidor con los requisitos del paquete), `php artisan migrate --force`, `php artisan optimize` y `php artisan up`.
  5. **Pruebas de humo.** Si fallan, el workflow termina en rojo; la app no se vuelve a poner en mantenimiento sola.
  - Si falla un paso entre `down` y `up`, la app se queda en mantenimiento (503) hasta que el usuario lo arregle y vuelva a desplegar. Es preferible a servir código a medias.
  - Grupo de concurrencia `deploy-production` sin cancelar el que está en marcha.
- **Versión visible:** un middleware añade la cabecera `X-App-Version` con el contenido de `VERSION` (el SHA corto). No es un secreto: el repositorio es público.
  - `VERSION` está en `.gitignore` y solo se genera en el paquete de despliegue.
  - Si no existe (tests de Pest, stack de T-20, desarrollo local), la cabecera vale `dev`. Las pruebas de humo sin `--production` no comprueban su valor.
- **Pruebas de humo:** se amplía el script de T-20 (`docker/smoke.sh <url-base>`) en lugar de crear otro, para que local y producción compartan comprobaciones. Con `--production` añade las que solo tienen sentido en producción: HTTPS y certificado, redirección HTTP, atributos de la cookie, `X-App-Version` (si se pasa el SHA esperado) y el estado de `illodosadev.io` frente a `MAIN_SITE_STATUS`. Las rutas internas se piden con `curl --path-as-is`, para que `/../.env` no se normalice a `/.env`. Sale con código distinto de 0 y nombra la comprobación que falla.
- **Secretos de GitHub:** `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER` y `DEPLOY_PATH`, más la variable (no secreta) `MAIN_SITE_STATUS`. Los secretos de la app (APP_KEY, DB_*, SPOTIFY_*) **no** pasan por GitHub: solo están en el `.env` del servidor.
- **Configuración de producción (`.env` del servidor):**
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://music.illodosadev.io`, `LOG_LEVEL=warning`.
  - `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`.
  - `CACHE_STORE=database`: el compartido no tiene Redis, y T-05 necesita una caché común a todos los procesos para el bloqueo de renovación de tokens y la pausa tras un 429.
  - `QUEUE_CONNECTION=sync` mientras ninguna tarea necesite colas. Si alguna las necesita, se usa `database` con un cron que ejecuta `php artisan queue:work --stop-when-empty` (no hay procesos permanentes en compartido).
  - `DB_HOST=127.0.0.1`, `DB_PORT=3306` (la base de datos está en el mismo servidor; el host `srvNNNN.hstgr.io` solo sirve para conexiones desde fuera).
  - Sin correo (T-02 quita todo lo que envía email).
- **Cron:** no hace falta mientras no haya tareas programadas. Si se añaden, una sola entrada `* * * * * php artisan schedule:run` creada desde hPanel o el MCP.
- **Puesta en marcha (una vez), documentada en el README como runbook "Despliegue":**
  - Anotar el estado de `https://illodosadev.io/` en `MAIN_SITE_STATUS` y la versión de PHP de `illodosadev.io`.
  - Crear el subdominio `music` del sitio `illodosadev.io` (hPanel o MCP, operación `hosting_websites_add-domain`).
  - Instalar el SSL del subdominio y activar la redirección a HTTPS.
  - Crear la base de datos MySQL y su usuario.
  - Activar el acceso SSH en hPanel, comprobar que existen `rsync` y `composer` y añadir la clave pública de despliegue.
  - Crear la carpeta de despliegue con el fichero marcador.
  - (Primera vez) lanzar el despliegue sin `.env` para comprobar el escenario "Falta el .env".
  - Escribir el `.env` del servidor. `APP_KEY` se genera en local con `php artisan key:generate --show` y se pega en el `.env` del servidor.
  - Primer despliegue y `php artisan app:create-owner` por SSH (el comando es solo interactivo, T-02).
  - Registrar `https://music.illodosadev.io/spotify/callback` en la app de Spotify, junto a la local `http://127.0.0.1:8081/spotify/callback` (en development mode solo hay un Client ID). Configurar `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI` y `SPOTIFY_OWNER_ID` (T-05). La comprobación de que Spotify acepta la redirección de producción es de T-05 (ver Seguimiento).
  - Ninguna operación que cambie la cuenta de Hostinger se hace sin que el usuario la confirme.

**Restricciones descubiertas (cuenta de Hostinger, 2026-11-09)**

- **Sin Node.js en el servidor** (plan Premium): el build de Vite tiene que hacerse fuera.
- **El despliegue Git de Hostinger** para sitios PHP solo copia los ficheros de la rama: no ejecuta `composer`, ni el build, ni las migraciones. Por eso se descartó (D14).
- **Sin procesos permanentes:** ni workers de colas, ni websockets. T-13 sincroniza la cola con sondeo, que es lo único posible aquí.
- **Funciones de PHP desactivadas:** `exec`, `shell_exec`, `system`, `passthru`, `popen`, `symlink`, `link`. Consecuencias:
  - `php artisan storage:link` falla (usa `symlink`). Si alguna tarea necesita `storage` público, el enlace se crea por SSH con `ln -s`. Hoy ninguna lo necesita.
  - **Para la pregunta abierta 2 (YouTube Music, T-09):** PHP no puede lanzar Python con `exec` ni `shell_exec`. Si queda algún mecanismo para lanzar procesos se comprueba por SSH en la puesta en marcha; en cualquier caso no hay procesos permanentes y Hostinger puede cambiar la lista. Falta comprobar por SSH si hay `python3` y si se pueden instalar paquetes con `pip --user`.
- **`sodium` desactivada en el servidor:** el runner la tendría, así que se desactiva también allí y se comprueba la plataforma del servidor en cada despliegue (ver el paso 4 del workflow).
- **`intl` está activa:** resuelve el Pendiente de T-14 sobre `Collator` en PHP, aunque T-14 ya ordena en el navegador.
- **Redis no está disponible** en el plan: caché y sesiones en base de datos.
- **Límites del plan:** `memory_limit` hasta 1536M y `max_execution_time` hasta 360 s, de sobra para la app. El plan limita también los inodos; `vendor/` sin dependencias de desarrollo y sin copias de versiones antiguas.
- **La configuración de PHP es por sitio** (`hosting_php_get`/`hosting_php_update` trabajan con un dominio). Falta comprobar si el subdominio usa la del sitio `illodosadev.io` (ver Pendiente).
- **Las migraciones de MySQL no son transaccionales** para los cambios de esquema: una migración que falla a medias puede dejar el esquema a medias. Por eso las pruebas de un fallo de migración usan una que lanza una excepción **antes** de tocar el esquema.

**Casos límite**

- **El 500 en producción:** no se provoca en producción, porque haría falta una ruta de prueba. El escenario "Los errores no exponen detalles" usa un 404, y el 500 ya está cubierto por T-01 (tests), T-20 (stack local) y T-26 (páginas de error).
- **Migración destructiva:** las migraciones no se deshacen al volver a una versión anterior. Antes de desplegar una migración que borra datos, el usuario hace una copia de la base de datos (se comprueba en el runbook qué copias tiene el plan).
- **Certificado del subdominio que caduca o falla al renovarse:** el escenario de HTTPS de las pruebas de humo lo detecta en el siguiente despliegue. Entre despliegues no hay vigilancia (fuera de alcance).
- **Caché de configuración vieja:** `optimize` se ejecuta en cada despliegue, después del rsync, para que el `.env` nuevo o el código nuevo no usen una caché anterior.
- **Opcache:** está activa en el servidor. Tras el rsync, los ficheros PHP cambiados se recargan porque opcache valida las fechas por defecto. Si no fuera así, se comprueba en el diseño cómo vaciarla.
- **Visitante durante el despliegue:** ve la página de mantenimiento (503, la Aero de T-26 cuando exista) durante unos segundos.
- **El usuario cambia illodosadev.io a propósito** (por ejemplo, pasa a redirigir): actualiza `MAIN_SITE_STATUS` antes del siguiente despliegue. Por eso la comprobación es solo del estado, no del contenido.

**Pistas para los tests**

- Los escenarios `[humo]` son el propio script, que el workflow ejecuta contra producción. Se puede probar en local contra el stack Docker de T-20 sin `--production`.
- Los `[workflow]` se verifican con ejecuciones reales y se enlazan en el `design.md` del cambio:
  - "Si los tests fallan" y "Falla un paso en el servidor": una rama temporal con un test roto, o con una migración cuyo `up()` empieza con `throw`, lanzada con la entrada `ref`. Después se vuelve a desplegar `main` y se borra la rama.
  - "Falla la conexión": se vacía o se cambia el secreto en un entorno de GitHub de prueba, o temporalmente, y se restaura después.
  - "Ruta peligrosa": se cambia `DEPLOY_PATH` temporalmente. La guarda corre antes de rsync, así que no hay riesgo si funciona; se prueba primero con el valor vacío.
  - "Las pruebas de humo fallan" (`@manual`): `docker/smoke.sh` contra el stack de T-20 con MySQL parado, sin bajar producción.
  - "Volver a una versión anterior": se aprovecha el primer cambio real que añada una migración (B = ese despliegue, A = el anterior) y después se vuelve a desplegar `main`. No se suben migraciones de prueba a `main`.
  - "Falta el .env": se provoca en la puesta en marcha, lanzando el despliegue antes de escribir el `.env` (el runbook separa los dos pasos).
- "Ningún secreto en el repositorio": gitleaks en el CI, más la búsqueda manual de los valores reales en el registro de la ejecución.

**Seguimiento (cambios en otros ficheros, no hechos aquí)**

- **T-01:** PHP 8.4 era provisional; el servidor tiene 8.3 en `illodosadev.io` y la decisión queda en el Pendiente de esta tarea. El escenario de 500 en el entorno desplegado no se pide aquí (ver Casos límite).
- **T-02:** su Pendiente "Ejecutar el comando en Hostinger… Lo resuelve T-04" queda resuelto: por SSH, tras el primer despliegue (runbook).
- **T-05:** añadir el escenario manual "Spotify acepta la redirección de producción" (la URI `https://music.illodosadev.io/spotify/callback` registrada; al conectar desde producción vuelve a `/settings/spotify` conectado), con dependencia blanda de T-04.
- **T-09:** Python solo depende ya de la pregunta abierta 2 y de comprobar por SSH `python3` y `pip --user` (ver Restricciones).
- **T-13:** la parte de la pregunta abierta 1 sobre websockets queda resuelta: no hay websockets en el plan. El sondeo es la solución final, no provisional.
- **T-20:** la cláusula "salvo que el plan resulte ser un VPS" ya no aplica. La suposición de MySQL depende del Pendiente "Motor y versión de la base de datos" de esta tarea. Su `docker/smoke.sh` se amplía aquí con `--production`.

**Fuera de alcance**

- Vigilancia continua (uptime, alertas) y copias de seguridad propias más allá de las del plan.
- Un entorno de staging.
- CDN y caché del servidor de Hostinger (desactivadas hoy; no hacen falta para un solo usuario).
- Las páginas de error Aero (T-26).
- Comprobar en producción la conexión con Spotify (T-05).

**Pendiente**

- **Versión de PHP del subdominio:** comprobar con `hosting_php_get` sobre `music.illodosadev.io` cuando exista. Si comparte la del sitio (8.3), hay dos opciones:
  - fijar la app a PHP 8.3 (`config.platform.php` en `composer.json`; la versión de Laravel elegida en T-01 tiene que admitirla);
  - o subir `illodosadev.io` a 8.4 tras comprobar que sigue funcionando. Lo decide el usuario.

  T-01 fijó PHP 8.4 como provisional; se revisa aquí con ese dato.
- **Dónde puede apuntar el directorio del subdominio:** si `hosting_websites_add-domain` acepta una carpeta fuera de `public_html` (como `aeroplayer/public`) o hace falta el enlace simbólico por SSH.
- **Motor y versión de la base de datos** (MySQL o MariaDB): se anota al crearla (`SELECT VERSION()`). T-20 usa MySQL; si es MariaDB, se cambia la imagen local. T-14 pide la collation `utf8mb4_bin`, que existe en los dos.
- **SSH en el plan:** comprobar en hPanel que está activado, el puerto (Hostinger suele usar el 65002) y que existen `rsync` y `composer` en el servidor. Todo D14 depende de ello; si no hubiera `rsync`, se sube un archivo comprimido por `scp` y se descomprime por SSH.
- **Python en el servidor** para la pregunta abierta 2 (ver Restricciones).
- **Copias de seguridad del plan:** frecuencia y retención; se anotan en el runbook.
- **Entrada `ref` del despliegue:** propuesta para volver atrás; la confirma el usuario en /opsx-propose.

**Estimación: 6 puntos.**
- **Puesta en marcha en Hostinger (subdominio, SSL, base de datos, SSH, carpeta y marcador, `.env`, cuenta del propietario) y runbook: 1 punto.**
- **Workflow "Deploy" (tests y paquete de producción por separado, SSH, guarda de la ruta, primer despliegue, mantenimiento, concurrencia, entrada `ref`): 2,5 puntos.** Es la parte nueva y la que más prueba y error necesita.
- **Workflow "CI" con gitleaks: 0,5 puntos.**
- **Configuración de producción (drivers, cookies seguras, `X-App-Version`, ficheros internos no servidos) y comprobación de la versión de PHP: 0,5 puntos.**
- **Ampliar las pruebas de humo y verificar los escenarios `[workflow]` y `@manual`: 1,5 puntos.**

## Definition of Done

- [ ] Escenarios `[humo]` cubiertos por el script de pruebas de humo, en verde contra producción
- [ ] Escenarios `[workflow]` y `@manual` verificados y anotados en el `design.md` del cambio
- [ ] Runbook "Despliegue" en el README
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
