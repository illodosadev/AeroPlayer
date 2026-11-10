---
id: T-26
title: Páginas de error Aero
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: should
estimate: 2
language: es
---

# T-26 — Páginas de error Aero

## Goal

Sustituir las páginas de error por defecto de Laravel por páginas Aero para 404, 419, 429, 500 y 503. Cada una muestra un mensaje corto en inglés y un enlace "Go to library". Siguen la regla de T-01 de no mostrar detalles internos y funcionan aunque fallen la base de datos, la sesión o el build de Vite. Importa porque estas páginas se ven justo cuando algo falla, y "look años 2000 logrado" es una métrica de éxito del discovery.

## Context

- Creada por el orquestador del lote `/task-enrich-batch` del 2026-11-09. Sale de T-25 al revisarla: las páginas de error por defecto de Laravel no son shadcn-vue, así que no forman parte de retirar shadcn-vue, y solo dependen de T-01, T-02 y T-03. Separadas, no tienen que esperar a que terminen las tareas de Spotify.
- **Depende de:**
  - **T-01:** páginas de error sin detalles internos; se reutilizan su ruta de prueba que lanza un 500 y su configuración de base de datos `db.invalid`.
  - **T-02:** rutas quitadas que devuelven 404, el 419 del logout con CSRF y el límite de 5 intentos de login por minuto.
  - **T-03:** tokens `--aero-*` y el degradado de cielo de reserva.
- **No depende** de T-25 ni de las tareas de Spotify.
- **Relación con T-25:** la regla de T-25 "logout sin sesión" convierte el 419 de un logout caducado en una redirección. El 419 de esta tarea solo se aplica a un usuario con sesión y un token no válido, según T-02.
- **Decisiones D11 y D12 (2026-11-09):** "/" sin sesión lleva a `/login` y `/library` es la página principal. Por eso el enlace es "Go to library": `/library` manda a los invitados a `/login`.

## Acceptance criteria

```gherkin
Scenario Outline: Las páginas de error tienen estilo Aero y no dan detalles internos
  Given la aplicación con el modo debug desactivado
  And <situación>
  When se pide <petición>
  Then la respuesta tiene estado <estado>
  And muestra "<texto>" y un enlace "Go to library" a "/library"
  And no contiene trazas de error, rutas de ficheros del servidor ni el atributo "data-slot"

  Examples:
    | situación                                                     | petición                                   | estado | texto                                            |
    | un visitante sin sesión                                       | "/settings/profile" (ruta quitada en T-02) | 404    | Page not found.                                  |
    | el usuario con sesión iniciada                                | "/ruta-que-no-existe"                      | 404    | Page not found.                                  |
    | una ruta, registrada solo en el test, que lanza una excepción | esa ruta                                   | 500    | Something went wrong.                            |
    | la base de datos inaccesible y el driver de sesión "database" | "/login"                                   | 500    | Something went wrong.                            |
    | la aplicación en modo mantenimiento ("php artisan down")      | "/login"                                   | 503    | AeroPlayer is under maintenance. Try again soon. |
    | el usuario con sesión iniciada y la protección CSRF activa    | un POST a "/logout" sin token CSRF válido  | 419    | Your session expired. Please reload the page.    |
    | 5 intentos de login fallidos en el último minuto              | un sexto POST a "/login"                   | 429    | Too many attempts. Try again in a minute.        |

# [test de navegador]
Scenario: La página 404 tiene el aspecto Aero
  Given la aplicación con el modo debug desactivado
  When se abre "/ruta-que-no-existe" en el navegador
  Then el elemento data-aero="error-page" tiene un background-image calculado con "linear-gradient"
  And el elemento data-aero="error-panel" es visible y contiene "Page not found."
  And a 375 px de ancho, scrollWidth del documento no supera 375

Scenario: La página de error no necesita el build ni la sesión
  Given la aplicación con el modo debug desactivado y sin "public/build/manifest.json"
  When se pide "/ruta-que-no-existe"
  Then la respuesta tiene estado 404 y muestra "Page not found."

@manual
Scenario: El propietario aprueba las páginas de error
  Given la app en marcha en local
  When el propietario revisa las páginas 404 y 500 a 1280 px y a 375 px frente a los cuatro rasgos del discovery
  Then el "design.md" del cambio tiene una sección "Validación visual" con la fecha, las capturas y un veredicto "aprobado" por rasgo
  And cada corrección pedida está marcada con [x]
```

## Notes & edge cases

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- Vistas Blade propias en `resources/views/errors/` (404, 419, 429, 500, 503) que comparten un layout Blade.
- Sin dependencia de Vue, del build de Vite, de la sesión ni de la base de datos. El CSS va en línea o en un fichero estático de `public/`.
- Ganchos para los tests: `data-aero="error-page"` en el contenedor con el fondo y `data-aero="error-panel"` en el panel del mensaje.
- Se reutilizan los valores de los tokens `--aero-*` y el degradado de cielo de reserva de T-03. Si los valores se copian, el test de tokens frente a skill de T-03 no debe romperse; cómo se hace lo decide el diseño.
- Textos de la interfaz en inglés: "Go to library", "Page not found.", "Something went wrong.", "Your session expired. Please reload the page.", "Too many attempts. Try again in a minute.", "AeroPlayer is under maintenance. Try again soon." (o `APP_NAME` en lugar de "AeroPlayer").

**Restricciones descubiertas**

- Las páginas se pintan sin sesión: en un 500 causado por la base de datos, la sesión ni siquiera arranca. Por eso el enlace es siempre "Go to library", y `/library` manda a los invitados a `/login`.
- Cuando una petición de Inertia (el POST del login con 429, el logout con 419) recibe una respuesta HTML que no es de Inertia, Inertia la enseña en un modal, así que la página Aero aparece dentro de ese modal.

**Casos límite**

- Enlaces directos a rutas que T-02 quitó (`/register`, `/settings/profile`) reciben el 404 Aero sin pedir sesión ni revelar nada.
- Las páginas son estáticas y **no siguen los temas de T-18**: queda fuera de alcance.

**Pistas para los tests**

- 500: una ruta registrada dentro del test, como en T-01.
- Base de datos caída: forzar el driver de sesión `database` y usar `db.invalid`, como en T-01.
- 503: `$this->artisan('down')`.
- 419: volver a activar el CSRF en el test, como en T-02.
- 429: cinco POST fallidos, como en T-02.
- Sin manifiesto de Vite: renombrar o simular la ausencia de `manifest.json`.
- Fondo y panel: test de navegador de Pest 4 que lee el estilo calculado (T-03).

**Fuera de alcance**

- Los temas de T-18, una página de error en Vue/Inertia y el 403 (no se puede llegar a él desde la interfaz).

**Pendiente**

- **Fila del 429:** las Restricciones de T-02 (L306) dicen que el Fortify instalado puede redirigir a `/login` con "Too many login attempts. Please try again in N seconds." en lugar de devolver 429. Si es así, la fila del 429 cambia o desaparece, y la página Aero 429 solo se aplica a otros límites.
- **Modal de Inertia:** si una página Aero dentro del modal para 419/429 es aceptable, o pasa a ser un aviso dentro de la página (por ejemplo, junto al formulario de login).
- **Logo:** la misma elección pendiente que en T-03/T-25.
- **Prioridad:** se propone `should`, como T-25; decide el usuario.

**Estimación: 2 puntos.**
- Layout Blade y cinco vistas estáticas con aspecto Aero, sin build ni sesión: 1.
- Tests de feature y de navegador para los siete casos más el manifiesto ausente: 0,5.
- Revisión del propietario: 0,5.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
