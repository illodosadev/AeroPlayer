---
id: T-21
title: Controles del reproductor Aero
status: done
discovery: reproductor-musica-privado
change: aero-player-controls
priority: must
estimate: 3
language: es
---

# T-21 — Controles del reproductor Aero

## Goal

Añadir al sistema de diseño Aero los controles propios del reproductor: un slider para el volumen y el progreso, la barra de reproductor y una carátula de reserva. Se muestran en la página de muestra y la skill de diseño se amplía con ellos. Es necesario porque la barra de reproductor es la pieza más visible de la app y recoge el rasgo "reproductores clásicos" del discovery (WMP 11, iTunes 7–9, Zune, Winamp). T-06 la conecta a la reproducción real, y T-07 y T-08 necesitan una carátula genérica cuando una canción no tiene imagen.

## Context

Sale de T-03 al revisarla, para que cada cambio sea más pequeño. Depende de T-03, que deja los tokens, `AeroButton` (incluido el orbe), `AeroGlassPanel`, `AeroBackground`, la página `/styleguide` con sesión, la skill `frutiger-aero` y los tests de navegador con Pest 4 (Playwright + Chromium). Por T-03, también depende de T-01 y T-02. T-06 depende de esta tarea.

Componentes de esta tarea y por qué cada uno:
- **Slider (`AeroSlider`)** para el volumen y el progreso/seek. Los dos son controles básicos del discovery.
- **Carátula con reserva (`AeroCover`)**: muestra la imagen de la canción o, si no hay o no carga, una carátula genérica Aero (`data-aero="cover-fallback"`). T-07 y T-08 esperan una carátula genérica.
- **Barra de reproductor (`AeroPlayerBar`)**, compuesta de `AeroCover`, título y artista, los botones anterior / play-pausa (orbe) / siguiente, y los sliders de progreso y volumen. En esta tarea solo es visual: T-06 la conecta a la reproducción.

Se aplican las decisiones y reglas de T-03: componentes Vue propios con CSS propio, recursos sin verificar licencias pero con su procedencia anotada, tokens `--aero-*`, ganchos `data-aero`, foco visible, movimiento reducido, contraste de 4,5:1 y área táctil de 44x44 px.

## Acceptance criteria

```gherkin
# --- Página de muestra --------------------------------------------------------

Scenario: La página de muestra incluye los controles del reproductor
  Given el usuario con sesión iniciada
  When pide "/styleguide"
  Then la página muestra, además de las secciones de T-03, las secciones "Sliders", "Cover" y "Player bar"

# --- Slider -------------------------------------------------------------------

Scenario: Los sliders se manejan con el teclado
  Given el slider "Volume" de la sección "Sliders" con un valor intermedio
  When recibe el foco y se pulsa la flecha derecha
  Then su valor aumenta un paso
  And el valor accesible del slider (aria-valuenow o el valor del input) refleja el nuevo valor

Scenario: Los sliders se manejan con el puntero
  Given el slider "Seek" de la sección "Sliders" con un valor intermedio
  When se hace clic cerca del final de la pista
  Then su valor pasa a ser mayor que el anterior

Scenario: Los sliders no salen de sus límites
  Given el slider "Volume" en su valor máximo
  When se pulsa la flecha derecha
  Then su valor sigue siendo el máximo

# --- Carátula -----------------------------------------------------------------

Scenario: Canción sin carátula
  Given la sección "Cover" con un ejemplo sin URL de imagen
  When se muestra
  Then aparece el elemento con data-aero="cover-fallback"
  And no hay ningún elemento <img> en ese ejemplo
  And el elemento de reserva tiene aria-hidden="true", porque el título de la canción ya está al lado

Scenario: La imagen de la carátula no carga
  Given la sección "Cover" con un ejemplo cuya URL de imagen devuelve 404
  When se muestra
  Then aparece el elemento con data-aero="cover-fallback"
  And no queda visible ningún <img> con naturalWidth igual a 0

Scenario: Carátula con imagen
  Given la sección "Cover" con un ejemplo con una imagen válida
  When se muestra
  Then se ve el <img> con un texto alternativo no vacío
  And no aparece el elemento con data-aero="cover-fallback"

# --- Barra de reproductor -----------------------------------------------------

Scenario: La barra de reproductor tiene los controles básicos
  Given el usuario con sesión iniciada en "/styleguide"
  When mira la sección "Player bar"
  Then ve una carátula, un título, un artista y los controles con nombre accesible "Previous", "Play", "Next", "Seek" y "Volume"
  And la barra está dentro de un AeroGlassPanel y el control "Play" es un AeroButton con forma de orbe

Scenario: Play y pausa cambian el control sin sonido
  Given la barra de reproductor de la página de muestra
  When se pulsa el control "Play"
  Then el control pasa a llamarse "Pause" y muestra el icono de pausa
  And no se crea ningún elemento <audio> o <video> en la página

Scenario: Título y artista vacíos no descolocan la barra
  Given la barra de reproductor de la página de muestra con título "Song A" y artista "Artist A"
  When el ejemplo pasa a tener título y artista vacíos
  Then la altura de la barra (getBoundingClientRect().height) no cambia
  And no aparece ningún texto de relleno en el lugar del título ni del artista

# --- Accesibilidad y adaptación -----------------------------------------------

Scenario: El foco del teclado se ve en los controles del reproductor
  Given el usuario en "/styleguide"
  When recorre con Tab los controles de las secciones "Sliders" y "Player bar"
  Then cada control enfocado muestra un indicador de foco (outline o anillo de al menos 2 px) que no tiene sin el foco
  And ningún control habilitado se salta en el recorrido

Scenario: Movimiento reducido en los controles del reproductor
  Given el navegador con prefers-reduced-motion: reduce
  When se abre "/styleguide", se pulsa "Play" y se mueven los sliders
  Then ningún elemento de las secciones "Sliders", "Cover" y "Player bar" tiene animation-name distinto de "none"
  And las transiciones duran 0,01 s o menos

Scenario: La barra funciona en el móvil con un título largo
  Given un viewport de 375x667 px
  And la barra de reproductor con un título de 120 caracteres
  When se abre "/styleguide"
  Then document.documentElement.scrollWidth es menor o igual que 375
  And todos los controles de la barra son visibles
  And el área táctil de cada control interactivo de la barra mide al menos 44x44 px
  And el título se muestra en una línea cortada con puntos suspensivos, y su texto completo está en el atributo "title"

# --- Skill y validación -------------------------------------------------------

Scenario: La skill incluye los controles del reproductor
  Given la skill "frutiger-aero"
  When se lee su contenido
  Then nombra AeroSlider, AeroCover y AeroPlayerBar con la ruta de su fichero
  And su sección de reproductores clásicos nombra AeroPlayerBar y AeroSlider y enlaza su referencia
  And el test de tokens frente a skill de T-03 sigue pasando con los tokens nuevos que añada esta tarea

@manual
Scenario: El propietario aprueba los controles del reproductor
  Given "/styleguide" en marcha en local
  When el propietario revisa las secciones "Sliders", "Cover" y "Player bar" frente a los cuatro rasgos del discovery
  Then el "design.md" del cambio tiene una sección "Validación visual" con la fecha
  And incluye capturas a 1280 px y a 375 px de esas secciones de "/styleguide"
  And tiene un veredicto para cada uno de los cuatro rasgos (glossy / cristal, naturaleza y cielo, reproductores clásicos, skeuomorfismo), todos "aprobado"
  And cada corrección que pidió el propietario está marcada con [x]
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- Se aplican las de T-03: componentes Vue Aero propios con CSS propio (shadcn-vue se retira poco a poco), recursos visuales sin verificar licencias pero con su procedencia anotada, y página de muestra con sesión.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Lo que necesitan T-06 y T-23 de la barra** (añadido al aplicar los cambios del lote el 2026-11-09):
  - "Play"/"Pause", "Previous" y "Next" con estado desactivado.
  - Una zona de aviso dentro de la barra con un mensaje, un botón opcional (por ejemplo "Play here") y un enlace opcional (por ejemplo "Reconnect Spotify").
  - La barra vive en un layout persistente de Inertia, que monta T-06.

- **Slider nativo:** `AeroSlider` envuelve un `<input type="range">` nativo. Así tiene gratis el teclado (flechas, Inicio, Fin), el rol `slider` y el valor accesible. El aspecto Aero va en la pista y el tirador, con los pseudo-elementos de cada navegador.
- **Rango del slider:** en la página de muestra, de 0 a 100 con paso 1. T-06 fija el rango real del seek (en segundos) y del volumen.
- **Estética de la barra:** pista con relieve y tirador brillante al estilo WMP 11, y orbe de play central más grande que anterior y siguiente. El detalle lo fija la revisión del propietario.
- **Carátula de reserva:** generada con CSS o con un SVG propio (por ejemplo, una nota musical sobre un degradado de cielo), sin pedir red. Si es un fichero, se anota en el registro de procedencia de T-03.
- **Play/pausa de la muestra:** en `/styleguide` solo cambia el estado visual y el nombre accesible del control. No crea audio.
- **Iconos de los controles** (anterior, play, pausa, siguiente, volumen): si son ficheros, se anotan en el registro de procedencia.

**Restricciones descubiertas**

- Cada motor de navegador pinta el `<input type="range">` con sus propios pseudo-elementos (`::-webkit-slider-thumb`, `::-moz-range-thumb`, etc.). Hay que estilarlos por separado y probarlos en Chromium y, a mano, en el navegador del móvil del propietario.
- En iOS no se puede fijar el volumen del audio desde la web. El slider de volumen se ve y se mueve, pero su efecto real lo decide T-06 (pregunta abierta 3).

**Casos límite**

- **Título o artista vacíos:** la barra no se descoloca. Se deja el hueco, sin texto de relleno inventado.
- **Carátula que tarda en cargar:** se ve la reserva hasta que llega la imagen, sin saltos de tamaño (la carátula tiene un tamaño fijo).
- **Arrastrar el tirador fuera de la barra:** el valor se queda en el límite y el arrastre no se corta.
- **Toque en el móvil:** el tirador tiene un área táctil de 44x44 px aunque se dibuje más pequeño.

**Pistas para los tests**

- Tests de navegador con Pest 4, el runner que deja T-03. Localizan los componentes por `data-aero`.
- **Carátula que no carga:** el ejemplo de 404 apunta a una ruta inexistente de la propia app, o se bloquea la petición en el test.
- **Sliders:** se lee `input.value` o `aria-valuenow` antes y después de cada acción.
- **Título largo:** se comprueba con `getComputedStyle` (`text-overflow: ellipsis`, `white-space: nowrap`) y que el ancho del título no supera el de la barra.
- **Escenario `@manual`:** la validación del propietario. Se verifica a mano y se anota en el `design.md` del cambio.

**Fuera de alcance**

- La reproducción real, los datos de la barra y el seek en segundos (T-06).
- El visualizador (T-16) y los temas (T-18).
- La cola, aleatorio y repetición (T-13). Si sus botones van en la barra, los añade esa tarea.

**Pendiente**

- **Iconos concretos de los controles:** los elige el propietario, como el resto de recursos de T-03.

**Estimación: 3 puntos.**
- **`AeroSlider` con los pseudo-elementos de cada navegador y su teclado (1 punto).**
- **`AeroPlayerBar` y `AeroCover` con su reserva y el título largo (1 punto).**
- **Secciones de `/styleguide`, ampliación de la skill y tests de navegador (0,5 puntos).**
- **Ronda de revisión del propietario y correcciones (0,5 puntos).**

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Botones extra en `AeroPlayerBar`:** dejar sitio (una ranura) para los botones que añaden otras tareas: "Visualizer" (T-16), "Lyrics" (T-17), "Shuffle", "Repeat: off/all/one" y "Queue" (T-13). Los de panel usan `aria-pressed` y tienen área táctil de 44x44 px.
- **Colores solo de tokens `--aero-*`** en la barra y en la carátula de reserva, sin colores literales, para que sigan los temas de T-18 (también Graphite). Los tokens temables nuevos van en los ficheros de temas si T-18 ya existe (regla de T-18).

**Seguimiento de aero-design-system (T-03, 2026-11-11)**

- Ampliar la skill `.claude/skills/frutiger-aero` (componentes y tokens) y `/styleguide` con los controles nuevos; el test de la skill falla si un token del CSS no está en ella.
- Los iconos van en `resources/aero/icons/` con su fila en `resources/aero/CREDITS.md` (el test de procedencia lo exige).

**Cerrada con `aero-player-controls` (2026-11-11)**

El cambio refinó algunos criterios al ver el código real. La referencia es ahora `openspec/specs/aero-design/spec.md`:

- El plugin de Pest solo hace clic en el centro de un elemento, así que el escenario de puntero arrastra el tirador más allá del final de la pista y comprueba que el valor se queda en el máximo.
- La barra solo emite eventos: `playing`, `position` y `volume` los lleva el padre. Por T-06 y T-23 se añadieron controles desactivables uno a uno, una zona de aviso en dos ranuras (`notice` dentro de `role="status"`, `notice-actions` fuera) y la ranura `extra`.
- Iconos (`resources/aero/icons/`) y carátula de muestra (`resources/aero/samples/`) hechos a mano, con su fila de procedencia. La nota musical se corrigió dos veces a petición del propietario (centrado por peso visual).
- La regla global de movimiento reducido no alcanza los pseudo-elementos del `<input type="range">`; un test del código fuente impide ponerles transiciones.
- Validación visual aprobada el 2026-11-11: los cuatro rasgos, los iconos, la carátula, el slider en el móvil del propietario y el reparto de filas de la barra.

## Definition of Done

- [x] Acceptance criteria covered by tests
- [x] `@manual` scenarios verified and recorded in the change's `design.md`
- [x] Spec deltas archived in openspec/specs/
- [x] Work committed on main
