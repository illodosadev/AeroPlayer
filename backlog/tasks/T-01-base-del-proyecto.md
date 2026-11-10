---
id: T-01
title: Base del proyecto Laravel + Inertia + Vue
status: proposed
discovery: reproductor-musica-privado
change: project-foundation
priority: must
estimate: 2
language: es
---

# T-01 — Base del proyecto Laravel + Inertia + Vue

## Goal

Crear un repositorio nuevo con el starter kit oficial de Laravel con Vue (Laravel + Inertia + Vue + TypeScript) que compile, pase sus tests y muestre una página Vue. Las páginas de error no deben exponer detalles internos. Así todas las tareas siguientes, y el despliegue en Hostinger (T-04), parten de una base conocida, probada y segura por defecto, en lugar de montar cada una su propio andamiaje.

## Context

Es un proyecto nuevo e independiente de LaravelSellerInventory. Usa la misma plantilla de spec-driven development (OpenSpec + backlog). El stack se decidió en el discovery: monolito Laravel + Inertia + Vue. No depende de ninguna otra tarea. T-02, T-03, T-04 y T-20 dependen de esta.

Decisiones tomadas con el usuario el 2026-11-09:
- **Base:** starter kit oficial de Laravel con Vue (Inertia, Vue 3, TypeScript, Tailwind, shadcn-vue). Trae login, registro, verificación de email y ajustes de perfil, y T-01 los deja tal cual. Quitar el registro y lo que sobre es trabajo de T-02. T-03 crea componentes Aero propios y retira shadcn-vue poco a poco, sin reestilarlo.
- **Tests:** Pest.
- **Idioma de la interfaz:** inglés (resuelve la pregunta abierta 8). El starter kit ya viene en inglés, así que la locale de la app es `en`.
- **Entorno local:** stack Docker propio. Se separa en **T-20** para que cada tarea sea un solo cambio, igual que `dev-environment` en el inventario. Hasta que exista T-20, esta tarea se trabaja con contenedores puntuales (imágenes oficiales de PHP/Composer y Node) o con PHP y Node instalados en la máquina.

## Acceptance criteria

```gherkin
# --- Aplicación base ----------------------------------------------------------

Scenario: La página de inicio es un componente Vue servido por Inertia
  Given la aplicación en el entorno de tests
  When se pide "/"
  Then la respuesta tiene estado 200
  And la respuesta Inertia indica el componente "Welcome"

Scenario: La interfaz está en inglés
  Given la aplicación recién instalada
  When se consulta la locale de la aplicación
  Then es "en"

# --- Build y tests ------------------------------------------------------------

Scenario: El frontend compila sin errores
  Given el proyecto con sus dependencias instaladas
  When se ejecuta el build de producción del frontend
  Then el comando termina con código 0
  And se genera el manifiesto de Vite en "public/build/manifest.json"

Scenario: La comprobación de tipos pasa
  Given el proyecto con sus dependencias instaladas
  When se ejecuta la comprobación de tipos de TypeScript del frontend
  Then el comando termina con código 0

Scenario: La batería de tests Pest pasa
  Given el proyecto con sus dependencias de desarrollo instaladas
  And ningún servidor MySQL en marcha
  When se ejecuta "php artisan test"
  Then el comando termina con código 0

# --- Errores sin detalles internos --------------------------------------------

Scenario: Una ruta que no existe devuelve 404 sin detalles internos
  Given la aplicación con el modo debug desactivado
  When se pide "/ruta-que-no-existe"
  Then la respuesta tiene estado 404
  And la respuesta no contiene trazas de error ni rutas de ficheros del servidor

Scenario: Error interno sin detalles con debug desactivado
  Given la aplicación con el modo debug desactivado
  And una ruta, registrada solo dentro del test, que lanza una excepción con el mensaje "boom-secret-1234"
  When se pide esa ruta
  Then la respuesta tiene estado 500
  And la respuesta no contiene "boom-secret-1234", trazas de error ni rutas de ficheros del servidor

Scenario: Base de datos no accesible
  Given la aplicación con el modo debug desactivado y el driver de sesión "database" (el de producción)
  And la conexión de base de datos apunta a un host inaccesible con la contraseña "db-secret-5678"
  When se pide "/"
  Then la respuesta tiene estado 500
  And la respuesta no contiene "db-secret-5678", el host de la base de datos ni trazas de error

# --- Documentación ------------------------------------------------------------

Scenario: El stack queda documentado para los siguientes cambios
  Given el repositorio tras completar la tarea
  When se lee el bloque "context" de "openspec/config.yaml"
  Then indica Laravel, Inertia, Vue 3 con TypeScript, Tailwind, Pest y la locale "en"
  And el README explica cómo instalar las dependencias, compilar el frontend y ejecutar los tests
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- Starter kit oficial de Laravel con Vue, sin tocar su auth. T-02 quita el registro (la ruta `/register` existe entre T-01 y T-02) y deja el login de un solo usuario. T-03 crea componentes Aero propios y retira shadcn-vue poco a poco, sin reestilarlo.
- Pest, con la interfaz en inglés. El entorno Docker se hace aparte, en T-20.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Versiones:** la última versión estable de Laravel y del starter kit en el momento de crear el repo, y la versión de PHP más alta que Hostinger soporte. Mientras no se resuelva la pregunta abierta 1, PHP 8.4 es una apuesta prudente; se revisa en T-04.
- **Tests:** SQLite en memoria (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), que es lo que trae el starter kit.

**Restricciones descubiertas**

- Desde Laravel 11 las sesiones y la caché se guardan por defecto en la base de datos, así que "/" necesita la BD en producción aunque la página de inicio no la use. En los tests el driver de sesión es `array`, así que el escenario "Base de datos no accesible" tiene que forzar el driver `database` en ese test para reproducir el caso real.
- En hosting compartido no suele haber Node. El build de Vite tendrá que hacerse fuera del servidor. Lo decide T-04; T-01 solo garantiza que el build funciona.

**Pistas para los tests**

- Para el 500, la ruta que lanza la excepción se registra con `Route::get` dentro del propio test de Pest. No hace falta ninguna ruta de test en la aplicación.
- Para la BD inaccesible, se apunta la conexión a un host que no resuelve (por ejemplo, `db.invalid`) con un timeout bajo, para que el test no tarde.

**Fuera de alcance**

- El stack Docker (T-20), quitar el registro y adaptar la auth del starter kit (T-02), la estética Aero (T-03) y el despliegue en Hostinger (T-04).

**Seguimiento (hecho al enriquecer)**

- Discovery: la pregunta abierta 8 queda resuelta (interfaz en inglés).
- T-02: anotado que el starter kit trae registro, verificación de email y recuperación de contraseña.
- T-03: anotado que crea componentes Aero propios y retira shadcn-vue poco a poco, y que también depende de T-02.
- T-04: anotados la revisión de la versión de PHP, dónde se hace el build de Vite y que su escenario de 500 se refiere al entorno desplegado.
- Nueva tarea **T-20 — Stack Docker local**, separada de esta.

**Estimación: 2 puntos.** El starter kit trae hecho casi todo el andamiaje de Laravel, Inertia y Vue. El trabajo es instalarlo y fijar versiones (1 punto), y añadir los tres tests de errores sin detalles, `openspec/config.yaml` y el README (1 punto).

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **D11 (2026-11-09):** "/" deja de ser una página: sin sesión redirige a `/login` y el componente `Welcome` se borra (lo implementa T-02). El escenario "La página de inicio es un componente Vue servido por Inertia" vale solo hasta T-02.
- En "Base de datos no accesible", pedir "/login" en lugar de "/", para que el escenario no dependa de D11.
- Las páginas de error Aero (404, 419, 429, 500, 503) son T-26, que reutiliza la ruta de prueba del 500 y la configuración `db.invalid` de esta tarea.

**Seguimiento de T-04 (despliegue, 2026-11-09)**

Pregunta abierta 1 resuelta: hosting compartido Premium de Hostinger, app en `music.illodosadev.io` (subdominio de `illodosadev.io`), despliegue con GitHub Actions + SSH lanzado a mano (D14–D16).

- PHP 8.4 era provisional. El sitio `illodosadev.io` tiene PHP 8.3, y el subdominio puede compartirla: la versión final se decide en el Pendiente de T-04 (fijar 8.3 en `config.platform.php` o subir el sitio a 8.4). La versión de Laravel elegida aquí tiene que admitir la que salga.
- El escenario de 500 en el entorno desplegado no se repite en T-04: lo cubren esta tarea, T-20 y T-26.
- T-04 crea el workflow de CI (`ci.yml`): tests en cada push y gitleaks.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
