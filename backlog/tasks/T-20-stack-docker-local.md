---
id: T-20
title: Stack Docker local
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 3
language: es
---

# T-20 — Stack Docker local

## Goal

Levantar el reproductor en local con un stack Docker propio que tenga dos modos: uno parecido a producción y otro de desarrollo con recarga en caliente. Así se desarrolla y se prueba en un entorno reproducible, y los fallos de configuración aparecen antes del despliegue en Hostinger (T-04).

## Context

Sale de T-01 al enriquecerla, para que cada tarea sea un solo cambio. El inventario hizo lo mismo con su cambio `dev-environment`. El usuario eligió un "Docker propio" el 2026-11-09. Se toma como modelo el stack de LaravelSellerInventory: `Dockerfile` multi-stage (PHP-FPM + nginx), `docker/entrypoint.sh` que exige `APP_KEY` y migra al arrancar, healthchecks y `docker/smoke.sh` con curl. Depende de T-01.

Hay dos modos, y cada escenario dice en cuál se ejecuta:
- **Modo producción (por defecto):** `docker compose up -d --build`. Usa `APP_ENV=production`, `APP_DEBUG=false`, el build de Vite compilado dentro de la imagen y una imagen sin dependencias de desarrollo.
- **Modo desarrollo:** el compose base más un fichero de override de desarrollo. Usa `APP_ENV=local`, `APP_DEBUG=true`, el código montado desde el host y un servicio de Vite con recarga en caliente.
- Los tests se ejecutan en un **servicio `test`** construido desde un target de la imagen con dependencias de desarrollo.

## Acceptance criteria

```gherkin
# --- Modo producción ----------------------------------------------------------

Scenario: Primer arranque sin pasos manuales más allá de la clave
  Given un clon limpio del repositorio
  And "docker/app.env" copiado del ejemplo, con APP_KEY rellenada y sin migrar a mano
  When se levanta el stack en modo producción
  Then cuando el servicio app está healthy, "http://localhost:8081/" responde con estado 200
  And el HTML contiene el atributo "data-page" de Inertia con el componente "Welcome"

Scenario: El puerto se elige con APP_PORT
  Given el fichero ".env" de la raíz del repo con APP_PORT=9090
  When se levanta el stack en modo producción
  Then "http://localhost:9090/" responde con estado 200
  And "docker compose port web 80" indica el puerto 9090 y no el 8081

Scenario: Los assets compilados se sirven
  Given el stack en modo producción
  When se pide la hoja de estilos bajo "/build/" enlazada desde "/"
  Then la respuesta tiene estado 200

Scenario: Falta APP_KEY
  Given "docker/app.env" sin APP_KEY
  When se levanta el stack en modo producción
  Then "docker compose ps" muestra el servicio app como reiniciándose o terminado con un código distinto de 0
  And "docker compose logs app" contiene "APP_KEY is not set" y el comando para generarla
  And "http://localhost:8081/" no responde con estado 200

Scenario: La imagen de producción no lleva dependencias de desarrollo
  Given la imagen del servicio app construida
  When se comprueba si existe "vendor/pestphp" dentro del contenedor app
  Then no existe

Scenario: Los datos sobreviven a un reinicio
  Given el stack en modo producción
  And un usuario con email "persist@example.com" creado con un comando artisan dentro del contenedor app
  When se ejecuta "docker compose down" (sin -v) y se vuelve a levantar el stack
  Then la tabla "users" sigue conteniendo "persist@example.com"

Scenario: Base de datos caída
  Given el stack en modo producción con la contraseña de MySQL "db-secret-5678"
  When se para el servicio mysql y se pide "/"
  Then la respuesta tiene estado 500 con la página de error genérica
  And la respuesta no contiene "db-secret-5678" ni trazas de error

Scenario: Recuperación tras la caída de la base de datos
  Given el stack en modo producción con el servicio mysql parado
  When se vuelve a arrancar mysql y espera a estar healthy
  Then "/" responde con estado 200
  And el contenedor app no se ha reiniciado (su hora de inicio en "docker compose ps" no cambia)

Scenario: Smoke test del stack
  Given el stack en modo producción
  When se ejecuta "docker/smoke.sh http://localhost:8081"
  Then termina con código 0
  And comprueba "/" → 200, la hoja de estilos → 200, una ruta inexistente → 404 y "/login" → 200

# --- Tests y modo desarrollo --------------------------------------------------

Scenario: La batería Pest pasa en el servicio de tests
  Given el repositorio con el stack construido
  When se ejecuta "docker compose run --rm test"
  Then termina con código 0

Scenario: Recarga en caliente en modo desarrollo
  Given el stack en modo desarrollo
  When se pide "/"
  Then el HTML carga los scripts desde el servidor de Vite en "http://localhost:5173"
  And "http://localhost:5173/@vite/client" responde con estado 200

Scenario: Cambios de PHP sin reconstruir en modo desarrollo
  Given el stack en modo desarrollo
  And una ruta "/dev-check" que devuelve el texto "v1"
  When se cambia en el host esa ruta para que devuelva "v2", sin reconstruir ninguna imagen
  Then la siguiente petición a "/dev-check" devuelve "v2"

# --- Documentación ------------------------------------------------------------

Scenario: El stack queda documentado
  Given el repositorio tras completar la tarea
  When se leen el README y el bloque "context" de "openspec/config.yaml"
  Then el README explica la preparación inicial, los dos modos, cómo ejecutar los tests y el smoke test, y que APP_PORT va en el ".env" de la raíz (Compose no lo lee de "docker/app.env")
  And "openspec/config.yaml" menciona el stack Docker y MySQL
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Base de datos: MySQL.** Es una suposición que depende de la pregunta abierta 1: Hostinger suele ofrecer MySQL o MariaDB, pero el motor exacto y su versión hay que confirmarlos con el plan real. Si es MariaDB, se cambia la imagen.
- **Puerto por defecto 8081**, para no chocar con el stack del inventario (8080). APP_PORT se define en el `.env` de la raíz, porque Compose sustituye `${APP_PORT}` en `ports:` desde ahí o desde la shell, nunca desde `env_file`.
- **Ruta `/dev-check`:** solo existe para comprobar a mano el escenario de recarga en modo desarrollo. No se commitea.
- **Orden respecto a T-02:** ningún escenario depende de `/register`, así que T-20 puede hacerse antes o después de T-02.
- **Correo:** no hace falta, porque T-02 quita todas las funciones que envían email. Se deja el mailer `log` por defecto y no se añade Mailpit.
- **Cuenta del propietario:** se crea con `docker compose exec app php artisan app:create-owner` (T-02). El comando es interactivo.
- **Tests de navegador:** el servicio `test` necesita Playwright y Chromium, porque T-03 elige Pest 4 browser tests como runner común.
- **URL local para Spotify:** Spotify no acepta `localhost` como URI de redirección, solo direcciones loopback como `http://127.0.0.1:8081`. Por eso `APP_URL` y la URL con la que se abre la app en local son `http://127.0.0.1:8081`: si se usan hosts distintos, la cookie de sesión no sobrevive a la vuelta de OAuth. Los escenarios de este stack pueden seguir usando `localhost`.
- **Variables de Spotify:** el ejemplo `docker/app.env` y el README incluyen `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI` y `SPOTIFY_OWNER_ID` (T-05).
- **Caché compartida:** el driver de caché es `database` o `redis`, porque el bloqueo y la pausa tras un 429 de T-05 deben compartirse entre procesos.
- **Versión de PHP de la imagen:** la misma que fije T-01.

**Casos límite**

- Si el puerto está ocupado, `docker compose up` falla con un error claro. Se arregla cambiando APP_PORT.
- El primer arranque espera a que MySQL esté healthy antes de migrar (`depends_on` con `condition: service_healthy`, como en el inventario).
- Los volúmenes de Docker en Windows pueden ir lentos con `vendor/` y `node_modules/` montados. En modo desarrollo conviene dejarlos en volúmenes con nombre.
- `docker compose down -v` borra los datos. Es intencionado y el README lo avisa.

**Fuera de alcance**

- El despliegue en Hostinger (T-04). Este stack no se usa en producción salvo que el plan resulte ser un VPS (pregunta abierta 1).

**Estimación: 3 puntos.** Se puede reutilizar casi todo el stack del inventario: Dockerfile, entrypoint, nginx y smoke test (1 punto). Lo nuevo es el modo desarrollo con Vite y el código montado, el target y servicio de tests y la variable de puerto (1,5 puntos). El resto es la documentación y las comprobaciones de los escenarios (0,5 puntos).

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Escenarios independientes de D11** ("/" deja de ser una página):
  - "Primer arranque…": pedir `http://localhost:8081/login` y comprobar que `data-page` indica el componente de la página de login, no "Welcome".
  - "Base de datos caída", "Recuperación", "Recarga en caliente" y "APP_PORT": pedir "/login".
  - Prueba rápida: "/" → 302 a `/login`, `/login` → 200, la hoja de estilos → 200 y una ruta inexistente → 404.

**Seguimiento de T-04 (despliegue, 2026-11-09)**

Pregunta abierta 1 resuelta: hosting compartido Premium de Hostinger, app en `music.illodosadev.io` (subdominio de `illodosadev.io`), despliegue con GitHub Actions + SSH lanzado a mano (D14–D16).

- La cláusula "salvo que el plan resulte ser un VPS" ya no aplica: el plan es compartido y este stack no se usa en producción.
- La suposición de MySQL depende del Pendiente de T-04 "Motor y versión de la base de datos" (se anota al crearla en Hostinger).
- T-04 amplía `docker/smoke.sh` con una opción `--production` (HTTPS, redirección, cookies, `X-App-Version`, sitio principal) en lugar de crear otro script.

**Seguimiento de project-foundation (2026-11-10)**

- La imagen PHP del stack Docker sigue la versión 8.4 fijada en `config.platform.php` (cambio `project-foundation`).

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
