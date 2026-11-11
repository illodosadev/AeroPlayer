---
id: T-16
title: Visualizador Aero de burbujas y agua
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: 5
language: es
---

# T-16 — Visualizador Aero de burbujas y agua

## Goal

Mostrar un visualizador animado mientras suena la música, como los de Windows Media Player y Winamp, para reforzar la estética años 2000. Tiene el estilo "Aero bubbles & water": burbujas, rayos de luz y olas sobre cielo y agua, a juego con el tema Aero de la app. Es una **animación simulada**: no reacciona al audio real, pero sigue la reproducción. Se mueve cuando suena, se congela en pausa y cambia de aspecto con cada canción.

## Context

Extra deseable del discovery ("Visualizador animado al estilo Windows Media Player / Winamp"), para después de la v1.

- **Dependencias:**
  - **T-03:** tokens `--aero-*`, `AeroGlassPanel`, `AeroButton`, la regla global de movimiento reducido, el registro de procedencia, los ganchos `data-aero`, `/styleguide` y los tests de navegador de Pest 4 (Playwright + Chromium).
  - **T-21:** la barra de reproductor, donde va el botón "Visualizer".
  - **T-06:** el composable del reproductor en el layout persistente de Inertia. Da el estado: la pista actual (`track_window.current_track` en Spotify) y `paused`. También el SDK simulado para los tests y "Disconnect Spotify".
  - **T-23:** qué muestra la barra cuando el SDK emite un estado `null` (reproducción en otro dispositivo o detenida en todas partes). El visualizador se alinea con eso (ver Reglas propuestas).
  - **T-04 (dependencia blanda):** solo para la comprobación manual en el móvil. El SDK necesita un contexto seguro (EME: HTTPS o `localhost`, ver T-06), así que en el móvil hace falta la app desplegada o un túnel HTTPS.
- **No depende de T-10:** el visualizador trabaja con lo que diga el composable del reproductor, sin importar de qué servicio sale (D5: primero Spotify). Cuando exista T-10, funciona igual con YouTube Music si el composable da el mismo estado.
- **Relacionadas:**
  - **T-18 (temas):** el visualizador saca sus colores de los tokens `--aero-*` y redibuja al cambiar el atributo `data-theme` de `<html>`, que pone T-18.
  - **T-17 (letras):** su panel "Lyrics" ocupa la misma zona por encima de la barra, en el mismo layout persistente. Los dos paneles se excluyen entre sí (ver Reglas propuestas).
- **Por qué es simulado:** ni Spotify ni YouTube dejan leer la señal de audio desde la página, y Spotify cerró a las apps nuevas el análisis de audio de la Web API. Las fuentes están en "Restricciones descubiertas".

## Acceptance criteria

`data-frames` es el contador de fotogramas dibujados del visualizador (ver "Gancho de los tests" en Notes). "No se dibuja ningún fotograma durante 1 segundo" significa que `data-frames` no cambia en ese segundo.

```gherkin
# --- Página de muestra --------------------------------------------------------

Scenario: La página de muestra incluye el visualizador
  Given el usuario con sesión iniciada
  When pide "/styleguide"
  Then la página muestra una sección "Visualizer" con un visualizador (data-aero="visualizer")
  And la sección tiene los botones "Demo play", "Demo pause", "Demo next track" y "Demo stop"
  And no hay ningún elemento <audio> o <video> en la página

Scenario: Los controles de demostración cambian el estado del visualizador
  Given la sección "Visualizer" de "/styleguide" con el visualizador en data-state="idle"
  When se pulsa "Demo play"
  Then el visualizador tiene data-state="playing" y un data-seed no vacío S1
  When se pulsa "Demo pause"
  Then el visualizador tiene data-state="paused" y data-seed sigue siendo S1
  When se pulsa "Demo play" y después "Demo next track"
  Then el visualizador tiene data-state="playing" y un data-seed distinto de S1
  When se pulsa "Demo stop"
  Then el visualizador tiene data-state="idle" y no tiene atributo data-seed

# --- Abrir y seguir la reproducción ---------------------------------------------

Scenario: Activar el visualizador mientras suena música
  Given el SDK simulado de T-06 con "Track A" (id "trackA") del artista "Artist 1" sonando y el visualizador cerrado
  When el usuario pulsa el botón "Visualizer" de la barra de reproductor
  Then aparece el panel "Now playing" con el elemento data-aero="visualizer"
  And el panel muestra el texto "Track A" y el texto "Artist 1"
  And el <canvas> del visualizador tiene aria-hidden="true"
  And el botón "Visualizer" tiene aria-pressed="true"
  And el foco está dentro del panel "Now playing"
  And el visualizador tiene data-state="playing" y un atributo data-seed no vacío
  And data-frames aumenta durante 1 segundo

Scenario: El visualizador sigue el estado de reproducción
  Given el visualizador abierto con data-state="playing" y data-seed = S
  When el SDK simulado emite un estado de la misma pista con paused = true
  Then el visualizador tiene data-state="paused"
  And no se dibuja ningún fotograma durante 1 segundo
  When el SDK simulado emite un estado de la misma pista con paused = false
  Then el visualizador tiene data-state="playing" y data-seed sigue siendo S
  And data-frames vuelve a aumentar

Scenario: Cada canción tiene su propia semilla
  Given el visualizador abierto con "Track A" (id "trackA") sonando y data-seed = SA
  When el SDK simulado emite un estado con "Track B" (id "trackB") sonando
  Then data-seed pasa a tener un valor SB distinto de SA
  And data-state sigue siendo "playing"
  When el SDK simulado vuelve a emitir un estado con "Track A"
  Then data-seed vuelve a ser SA

Scenario: Cada canción tiene su propia escena
  Given el navegador con prefers-reduced-motion: reduce y el visualizador abierto con "Track A" sonando
  And la imagen del lienzo (canvas.toDataURL()) guardada como IA1
  When el SDK simulado emite un estado con "Track B" y se guarda la imagen del lienzo como IB
  And el SDK simulado vuelve a emitir un estado con "Track A" y se guarda la imagen del lienzo como IA2
  Then IB es distinta de IA1
  And IA2 es idéntica a IA1

Scenario: Cambio de canción en pausa
  Given el visualizador abierto con "Track A" en pausa (data-state="paused") y data-seed = SA
  When el SDK simulado emite un estado con "Track B" y paused = true
  Then data-seed tiene un valor distinto de SA
  And data-state sigue siendo "paused"
  And data-frames aumenta exactamente 1 y después no cambia durante 1 segundo

Scenario Outline: Semilla de pistas sin id
  Given el visualizador abierto
  When el SDK simulado emite un estado sonando con una pista con <identificadores>
  Then el visualizador tiene data-state="playing" y <semilla>

  Examples:
    | identificadores                                 | semilla                                  |
    | id null y uri "spotify:local:Artist:Album:Song:180" | un data-seed no vacío distinto de "default" |
    | id null y uri null                              | data-seed="default"                      |

# --- Sin música y transiciones a reposo -----------------------------------------

Scenario Outline: Visualizador sin música
  Given <situación>
  When el usuario pulsa "Visualizer"
  Then el visualizador tiene data-state="idle" y no tiene atributo data-seed
  And se ve el texto "Nothing playing" dentro del panel, con un contraste de al menos 4,5:1 con el fondo del panel medido con la reserva sin cristal forzada (clase "aero-no-blur" en <html>, como T-18)
  And data-frames aumenta exactamente 1 y después no cambia durante 1 segundo

  Examples:
    | situación                                                       |
    | el usuario con sesión iniciada y Spotify sin conectar           |
    | el SDK simulado listo con device_id "web-1" sin ningún estado emitido |

Scenario: Desconectar Spotify con el visualizador abierto
  Given el visualizador abierto con "Track A" sonando (data-state="playing")
  When el usuario pulsa "Disconnect Spotify" (T-05, T-06)
  Then el visualizador tiene data-state="idle" y no tiene atributo data-seed
  And se ve el texto "Nothing playing" dentro del panel
  And la imagen del lienzo (canvas.toDataURL()) es distinta de la de antes de pulsar "Disconnect Spotify"
  And 200 ms después de pulsar, data-frames no cambia durante 1 segundo

Scenario Outline: El SDK emite un estado null con el visualizador abierto
  Given el visualizador abierto con "Track A" sonando y data-seed = SA
  And "GET /spotify/player/current" <respuesta>
  When el SDK simulado emite "player_state_changed" con estado null
  Then el visualizador tiene data-state="paused" y data-seed sigue siendo SA
  And el panel sigue mostrando el texto "Track A"
  And no se dibuja ningún fotograma durante 1 segundo

  Examples:
    | respuesta                                  |
    | devuelve device_name "Kitchen Speaker"     |
    | responde 204                               |

# --- Cerrar ---------------------------------------------------------------------

Scenario Outline: Cerrar el visualizador
  Given el visualizador abierto con música sonando
  When el usuario <acción>
  Then no queda ningún elemento data-aero="visualizer" en la página
  And el botón "Visualizer" tiene aria-pressed="false" y tiene el foco
  And la música sigue sonando (no se llama a "pause()" del SDK)

  Examples:
    | acción                                          |
    | pulsa otra vez "Visualizer"                     |
    | pulsa "Close visualizer"                        |
    | pulsa Escape con el foco dentro del panel       |

# La exclusión entre "Now playing" y "Lyrics" tiene un único criterio canónico, en los dos sentidos:
# el escenario de paneles excluyentes de T-17. Lo implementa la tarea de las dos (T-16 o T-17) que se construya después.

# --- Rendimiento y batería ------------------------------------------------------

Scenario: Límite de fotogramas
  Given el visualizador abierto con música sonando
  When pasan 2 segundos
  Then data-frames aumenta al menos 20 y como mucho 62 (30 por segundo, con margen)

Scenario: Pestaña oculta
  Given el visualizador abierto con música sonando
  When la pestaña pasa a oculta (document.visibilityState = "hidden" y evento "visibilitychange")
  Then no se dibuja ningún fotograma durante 1 segundo
  When la pestaña vuelve a estar visible
  Then data-frames vuelve a aumentar y data-seed no ha cambiado

# --- Movimiento reducido --------------------------------------------------------

Scenario: Movimiento reducido
  Given el navegador con prefers-reduced-motion: reduce
  And música sonando
  When el usuario abre el visualizador
  Then el visualizador tiene data-motion="reduced"
  And data-frames aumenta exactamente 1 y después no cambia durante 1 segundo
  And ningún elemento del panel tiene animation-name distinto de "none"
  When el SDK simulado emite paused = true
  Then data-state pasa a "paused", igual que sin movimiento reducido

Scenario: El movimiento reducido se activa con el visualizador abierto
  Given el visualizador abierto con música sonando y sin movimiento reducido
  When el navegador pasa a prefers-reduced-motion: reduce
  Then el visualizador pasa a data-motion="reduced"
  And 200 ms después del cambio, data-frames no cambia durante 1 segundo

# --- Colores y temas ------------------------------------------------------------

Scenario: El visualizador usa los colores de los tokens
  Given el navegador con prefers-reduced-motion: reduce y música sonando
  And todos los tokens --aero-* de cielo que usa el visualizador redefinidos en <html> a #ff0000
  When el usuario abre el visualizador
  Then todos los píxeles de las 4 filas superiores del lienzo (getImageData) tienen el canal rojo mayor que 200 y los canales verde y azul menores que 60

Scenario Outline: Cambio de tema con el visualizador abierto
  Given la página con una regla CSS de prueba [data-theme="test-red"] que pone a #ff0000 todos los tokens --aero-* de cielo que usa el visualizador
  And el visualizador abierto <estado>
  When el atributo data-theme de <html> cambia a "test-red"
  Then en menos de 1 segundo todos los píxeles de las 4 filas superiores del lienzo tienen el canal rojo mayor que 200 y los canales verde y azul menores que 60
  And <dibujo>

  Examples:
    | estado                                                     | dibujo                                                                  |
    | con música sonando                                         | data-frames sigue aumentando                                            |
    | con la música en pausa                                     | data-frames aumenta exactamente 1 y después no cambia durante 1 segundo |
    | con música sonando y prefers-reduced-motion: reduce        | data-frames aumenta exactamente 1 y después no cambia durante 1 segundo |

# --- Preferencia y navegación ---------------------------------------------------

# Se aplica si la preferencia va en localStorage (propuesta, ver Pendiente). Si va en el servidor, se reescribe en /opsx-propose.
Scenario Outline: Estado del visualizador al cargar la página
  Given <historia>
  When el usuario recarga la página
  Then el panel "Now playing" está <estado> y el botón "Visualizer" tiene aria-pressed="<pressed>"

  Examples:
    | historia                                              | estado  | pressed |
    | el almacenamiento local del navegador vacío            | cerrado | false   |
    | el usuario abrió el visualizador                       | abierto | true    |
    | el usuario abrió el visualizador y después lo cerró    | cerrado | false   |

# Se aplica si la preferencia va en localStorage (propuesta, ver Pendiente). Si va en el servidor, se reescribe en /opsx-propose.
Scenario: Sin almacenamiento local
  Given el navegador lanza un error al leer o escribir en localStorage
  When el usuario abre la app y después pulsa "Visualizer"
  Then al cargar el visualizador está cerrado
  And al pulsar "Visualizer" se abre igualmente
  And no hay errores en la consola del navegador ni eventos "pageerror"

Scenario: El visualizador sigue abierto al navegar por la app
  Given el visualizador abierto con "Track A" sonando y data-seed = SA
  When el usuario navega a otra página de la app con Inertia
  Then el panel sigue abierto, data-state es "playing" y data-seed sigue siendo SA

# --- Robustez, recursos y adaptación --------------------------------------------

Scenario: El lienzo no tiene contexto 2D
  Given un script de inicio que hace que HTMLCanvasElement.prototype.getContext devuelva null
  And música sonando
  When el usuario abre el visualizador
  Then el elemento data-aero="visualizer-fallback" dentro de data-aero="visualizer" es visible y tiene un background-image calculado con "linear-gradient"
  And no hay ningún elemento <canvas> visible dentro de data-aero="visualizer"
  And el visualizador tiene data-state="playing"
  When el SDK simulado emite paused = true
  Then el visualizador tiene data-state="paused"
  And no hay ningún evento "pageerror"

Scenario: El visualizador no descarga imágenes
  Given el visualizador cerrado
  When el usuario lo abre y deja sonar la música 2 segundos
  Then la página no hace ninguna petición de red nueva de tipo imagen o fuente

Scenario: El visualizador en el móvil
  Given un viewport de 375x667 px y música sonando
  When el usuario abre el visualizador
  Then document.documentElement.scrollWidth es menor o igual que 375
  And los controles de la barra de reproductor siguen visibles
  And los botones "Visualizer" y "Close visualizer" tienen un área táctil de al menos 44x44 px

# --- Validación manual ----------------------------------------------------------

@manual
Scenario: El propietario aprueba el visualizador
  Given el visualizador en marcha en local con una canción real de Spotify
  When el propietario lo revisa a 1280 px y a 375 px, en reproducción, en pausa, al cambiar de canción y sin música
  Then el "design.md" del cambio tiene una sección "Validación visual" con la fecha y capturas a 1280 px y 375 px
  And tiene el veredicto "aprobado" para el estilo "Aero bubbles & water" y cada corrección pedida marcada con [x]

@manual
Scenario: Sin destellos
  Given el visualizador abierto en "/styleguide" con la pista de prueba "Demo max tempo" (data-bpm="150", el máximo del pseudo-pulso)
  When se observa durante 1 minuto a 1280 px
  Then la sección "Validación visual" anota "sin destellos (WCAG 2.3.1)" con la fecha: ningún cambio de brillo de una zona grande más de 3 veces por segundo

@manual
Scenario: Consumo en el móvil
  Given la app desplegada (T-04) o servida por un túnel HTTPS, y el móvil del propietario con la batería por encima del 50 %
  When suena música de Spotify con el visualizador abierto durante 15 minutos
  Then la sección "Validación visual" incluye el % de batería al empezar y al terminar, y si el móvil se notaba caliente (sí/no)
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D8: animación simulada en el estilo "Aero bubbles & water".** No reacciona al audio real. Sigue el play, la pausa y los cambios de canción, con burbujas, rayos de luz y olas sobre cielo y agua, a juego con el tema Aero. **Un solo estilo:** en esta tarea no hay presets ni estilos a elegir.
- **D5: primero Spotify.** El visualizador no depende de ningún servicio: trabaja con el estado del composable del reproductor. Se quita la dependencia de T-10. Funciona con YouTube Music cuando exista T-10, si el composable da el mismo estado.
- Se aplican las de T-03: componentes Vue Aero propios con CSS propio, recursos sin verificar licencias pero con su procedencia anotada, y `/styleguide` solo con sesión.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Componente:** `AeroVisualizer`, con `data-aero="visualizer"` en su raíz. Expone su estado en atributos:
  - `data-state`: `idle` si no hay pista, `playing` si suena y `paused` si está en pausa;
  - `data-seed`: la semilla de la canción actual (no existe en `idle`);
  - `data-motion`: `reduced` o `full`;
  - `data-frames`: el gancho de los tests (ver más abajo);
  - `data-bpm`: el tempo del pseudo-pulso que sale de la semilla (80–150; no existe en `idle`). La sección "Visualizer" de `/styleguide` incluye una pista de prueba "Demo max tempo" con `data-bpm="150"` para la comprobación manual de destellos.
  - Si el lienzo no tiene contexto 2D, se muestra en su lugar `data-aero="visualizer-fallback"`, un elemento con el degradado de cielo de los tokens.
- **Gancho de los tests:** el componente pone en su raíz `data-frames`, el número de fotogramas que ha dibujado desde que se montó, contando también los fotogramas fijos. Lo sube en 1 tras cada fotograma dibujado. Todos los escenarios miden el dibujo con este atributo, así que no dependen de cómo se dibuje (canvas, métodos concretos ni temporizadores). Escribir un atributo hasta 30 veces por segundo es barato.
- **Dónde se ve:**
  - Un botón **"Visualizer"** en la barra de T-21 (un `AeroButton` con icono, `aria-pressed`) abre y cierra un panel **"Now playing"** (`AeroGlassPanel`).
  - El panel cubre la zona de contenido de la página, por encima del contenido y por debajo de la barra, que sigue visible y usable.
  - Dentro del panel van el visualizador, el título y el artista de lo que suena (texto fijo, sin animar) y un botón "Close visualizer".
  - Al abrir, el foco pasa al panel "Now playing" (igual que en la exclusión con T-17), para que Escape funcione sin un clic previo.
  - Escape cierra el panel y devuelve el foco al botón "Visualizer".
  - El panel vive en el layout persistente de Inertia (T-06), así que sobrevive a la navegación.
  - En el móvil (menos de 640 px de ancho), el panel ocupa toda la zona por encima de la barra.
- **Paneles excluyentes con T-17:** el panel "Now playing" y el panel "Lyrics" de T-17 ocupan la misma zona y no pueden estar abiertos a la vez.
  - Abrir uno cierra el otro, y el foco va al panel que se acaba de abrir.
  - Los dos botones ("Visualizer" y "Lyrics") usan `aria-pressed`.
  - Cerrar el visualizador al abrir "Lyrics" cuenta como cerrarlo: la preferencia guardada pasa a "cerrado" y el bucle se para.
  - Si T-16 se construye antes que T-17, la regla y su escenario entran con T-17.
- **Textos de la interfaz (en inglés):** "Visualizer", "Now playing", "Close visualizer", "Nothing playing". En `/styleguide`: "Demo play", "Demo pause", "Demo next track" y "Demo stop".
- **Preferencia abierto/cerrado (propuesta, sin confirmar con el usuario):**
  - Se propone guardarla en `localStorage` (por ejemplo, la clave `aeroplayer.visualizer.open`), es decir, por navegador. Así el móvil podría tenerlo cerrado aunque el escritorio lo tenga abierto.
  - La alternativa es guardarla por usuario en el servidor, como el tema de T-18. Se decide en /opsx-propose (ver Pendiente).
  - Con `localStorage`, cada lectura y escritura va en `try/catch`. Si falla, el visualizador empieza cerrado.
  - Por defecto está cerrado.
- **Cómo "sigue" a la canción sin audio:**
  - **Semilla:** un hash determinista (por ejemplo, 8 caracteres hex) del id de la pista que da el composable. En Spotify es `track_window.current_track.id`, o el `uri` si el id es `null`. Si no hay ninguno de los dos, se usa una semilla fija y `data-seed="default"`.
  - **Qué decide la semilla:** la paleta dentro de los tokens, la densidad y el tamaño de las burbujas, el ángulo de los rayos y la forma de las olas. La misma canción da siempre el mismo dibujo.
  - **Pseudo-pulso:** un tempo inventado, sacado de la semilla, de entre 80 y 150 "bpm" (entre 1,33 y 2,5 Hz). Modula el ritmo de salida de las burbujas, la amplitud de las olas y el brillo de los rayos. No pretende coincidir con el ritmo real.
  - **Cambio de canción:** si cambia el id, la escena se reinicia (sin burbujas en pantalla, semilla nueva). Si llega un estado con la misma pista (por ejemplo, un seek o un `player_state_changed` repetido), no se reinicia nada.
  - **Pausa:** se congela el último fotograma. Al reanudar, la animación sigue desde ese fotograma, sin reiniciarse.
  - **`idle`:** se dibuja una escena fija de cielo y agua, sin burbujas en movimiento, con el texto "Nothing playing".
- **Escenas fijas** (`idle`, `paused`, movimiento reducido o lienzo recién redimensionado sin bucle activo):
  - Se dibuja **exactamente un fotograma** al entrar en `idle`, al entrar en movimiento reducido, al abrir el panel en `paused`, al cambiar de canción, al cambiar de tema y al cambiar de tamaño el lienzo. Después no se dibuja nada más hasta el siguiente de esos eventos.
  - Por eso un cambio de canción en pausa dibuja exactamente un fotograma fijo de la escena nueva.
  - Pasar de `playing` a `paused` no dibuja ningún fotograma nuevo: se queda el último.
  - **Determinista:** la escena fija solo depende de la semilla, del tamaño del lienzo y de los tokens. No usa la hora ni `Math.random()`, así que la misma canción da la misma imagen (`canvas.toDataURL()` idéntica).
- **Franja de cielo:** las 4 filas de píxeles superiores del lienzo solo pintan el degradado de cielo, sin rayos ni burbujas. Sirve para comprobar los colores en los tests.
- **Estado `null` del SDK (alineado con T-23):** cuando el SDK emite un estado `null`, porque la reproducción pasó a otro dispositivo o se detuvo en todas partes, T-23 mantiene la barra con "Track A" y el botón "Play". El visualizador hace lo mismo: pasa a `paused`, conserva la semilla y la escena, y el panel sigue mostrando el título.
- **Cuándo es `idle`:** solo cuando el composable no tiene ninguna pista. Pasa con Spotify sin conectar, con el SDK listo sin ningún estado emitido, después de "Disconnect Spotify" (T-06, la barra muestra "Connect Spotify to play your music.") y al cerrar sesión.
- **Dibujo:**
  - Un `<canvas>` 2D dibujado por código, sin imágenes ni fuentes descargadas. Así no hace falta añadir nada al registro de procedencia de T-03. Si algún día se añade un fichero, se anota allí.
  - El canvas lleva `aria-hidden="true"`, porque es decorativo: el título y el artista ya están en texto en el panel.
  - El título y el artista del panel no se animan. El visualizador no usa la carátula de la canción (ver la restricción de la política de Spotify).
  - **Sin contexto 2D** (`getContext('2d')` devuelve `null`): el panel muestra un degradado CSS fijo de cielo y agua con los tokens, `data-state` sigue la reproducción y no se lanza ningún error.
- **Bucle de animación:**
  - Un único bucle con `requestAnimationFrame` que dibuja como mucho 30 fotogramas por segundo y salta los que sobran.
  - El bucle **no está activo** (no hay ninguna llamada a `requestAnimationFrame` pendiente) en estos cinco casos: el panel cerrado, `paused`, `idle`, la pestaña oculta (`document.visibilitychange`) y el movimiento reducido.
  - Como máximo 60 burbujas a la vez en el escritorio y 30 en el móvil.
  - El canvas sigue el tamaño del panel con `ResizeObserver`, con un `devicePixelRatio` limitado a 2.
- **Movimiento reducido:**
  - Con `prefers-reduced-motion: reduce`, el visualizador dibuja una sola escena fija según la semilla (cielo, agua, rayos y burbujas quietas) y no arranca el bucle.
  - `data-state` sigue cambiando con el play y la pausa, y la escena fija cambia con la canción.
  - Escucha el cambio de la media query en vivo (`matchMedia(...).addEventListener('change', ...)`).
  - Es coherente con la regla global de T-03, que anula las animaciones CSS pero no un bucle de canvas. Por eso el visualizador lo comprueba él mismo.
- **Sin destellos:** ningún cambio de brillo de más de 3 veces por segundo (WCAG 2.3.1). El pseudo-pulso llega como mucho a 2,5 Hz.
- **Colores y temas:**
  - Solo de tokens `--aero-*`, leídos con `getComputedStyle(document.documentElement)` al abrir el panel y cuando cambia el tema.
  - El cambio de tema se detecta con un `MutationObserver` sobre el atributo `data-theme` de `<html>`. T-18 lo pone en la vista raíz, y un único gancho en `resources/js/app.ts` lo actualiza en cada visita de Inertia (salvo las del historial) y de forma optimista al elegir un tema.
  - Al cambiar el tema, el visualizador vuelve a leer los tokens. Si hay una escena fija, la redibuja una vez; si el bucle está activo, el siguiente fotograma ya sale con los colores nuevos.
  - Si hacen falta tokens nuevos (por ejemplo, `--aero-visualizer-*`), se nombran en la skill `frutiger-aero` y el test de tokens frente a skill de T-03 tiene que seguir pasando. Una propiedad personalizada se resuelve donde se declara (T-18), así que un token de color declarado solo en `:root` no seguiría al tema. Por eso, siguiendo la regla de T-18: si T-18 ya existe cuando se construye esta tarea, **esta tarea** añade los tokens nuevos a los cinco ficheros de temas (`resources/css/aero/themes/*.css`), nunca a `tokens.css`; si no existe, van en el fichero de tokens de T-03 y T-18 los mueve a `themes/sky.css` y los declara en los demás temas.
- **Contraste:** el texto "Nothing playing" y el título del panel cumplen 4,5:1 (regla de T-03). Van sobre el panel de cristal, no directamente sobre el canvas.
- **Página de muestra:** la sección "Visualizer" de `/styleguide` usa un estado simulado propio (los botones "Demo …") para probar el visualizador sin el SDK. Los tests de integración con el reproductor usan el SDK simulado de T-06.
- **Skill:** la skill `frutiger-aero` nombra `AeroVisualizer` con la ruta de su fichero y las reglas de movimiento reducido y sin destellos.

**Restricciones descubiertas (investigadas el 2026-11-09)**

- **Spotify cerró a las apps nuevas el análisis de audio:** el 27 de noviembre de 2024 dejó sin acceso a "Audio Features" y "Audio Analysis" (y a "Recommendations", "Related Artists", entre otros) a las apps registradas desde ese día y a las apps en modo desarrollo sin solicitud de ampliación pendiente. Las apps con modo ampliado que ya los usaban no se ven afectadas. AeroPlayer es una app nueva en modo desarrollo, así que no hay tempo, beats ni secciones que usar. Fuente: <https://developer.spotify.com/blog/2024-11-27-changes-to-the-web-api>
- **El Web Playback SDK no da acceso al audio:** su referencia solo documenta los metadatos y el control de la reproducción (`paused`, `position`, `track_window.current_track` con `id`, que puede ser `null`, `uri`, `name`, `album.images`, `artists`). No hay muestras de audio ni análisis. Fuente: <https://developer.spotify.com/documentation/web-playback-sdk/reference>
- **Audio cifrado (EME) dentro de un iframe:** la página del SDK pide que los iframes permitan `encrypted-media`, es decir, el DRM de EME (<https://developer.spotify.com/documentation/web-playback-sdk>). El mantenedor de un proyecto que lo intentó cuenta que el SDK es "an iframe with the media streaming logic inside" y que la Web Audio API necesita la fuente de audio en la misma página (<https://github.com/Fabrizz/MMM-OnSpotify/issues/52>).
  - **Matiz de la investigación:** que EME bloquee la Web Audio API no es una regla uniforme. La especificación lo deja en manos de cada navegador, y Firefox permite `createMediaElementSource` con contenido EME desde la versión 55 (<https://bugzilla.mozilla.org/show_bug.cgi?id=1331763>).
  - **Lo que de verdad lo impide:** el SDK no expone su elemento de audio, que está dentro de un iframe de otro origen, y la Web Audio API exige que el audio sea del mismo origen para analizarlo (sección "Security with MediaElementAudioSourceNode and Cross-Origin Resources": <https://webaudio.github.io/web-audio-api/#MediaElementAudioSourceOptions-security>). Con eso, la conclusión de D8 se mantiene.
- **YouTube:** el IFrame Player API sustituye el elemento por un `<iframe>` de `youtube.com`. Solo ofrece métodos de reproducción, volumen y estado, y eventos (`onReady`, `onStateChange`, `onError`, `onAutoplayBlocked`…). No da acceso a datos de audio, y la página no puede leer un iframe de otro origen. Fuente: <https://developers.google.com/youtube/iframe_api_reference>
- **Política de desarrolladores de Spotify, III.6:** "Do not synchronize any sound recordings with any visual media, including any advertising, film, television program, slideshow, video, or similar content." Fuente: <https://developer.spotify.com/policy>
  - Un visualizador genérico que no sale del audio y solo sigue el play y la pausa no es un vídeo ni un pase de diapositivas. Aun así, "or similar content" es ambiguo.
  - Por prudencia, la animación no se deriva del audio ni anima la carátula. La app es privada y de un solo usuario.
  - No es un análisis legal: se anota como riesgo conocido.
- **Pestañas ocultas:** la mayoría de los navegadores ya pausan `requestAnimationFrame` en las pestañas en segundo plano (<https://developer.mozilla.org/en-US/docs/Web/API/Window/requestAnimationFrame>). Escuchar `visibilitychange` lo garantiza en todos y deja el estado listo para reanudar.
- La regla global de movimiento reducido de T-03 actúa sobre animaciones y transiciones CSS. Un bucle de canvas no se para con CSS, así que el visualizador consulta la media query él mismo.
- **EME necesita un contexto seguro** (HTTPS o `localhost`, ver T-06). Por eso la medición en el móvil necesita la app desplegada (T-04) o un túnel HTTPS: desde el móvil, la IP local del ordenador por HTTP no sirve.

**Casos límite**

- **`player_state_changed` repetido, fuera de orden o "a intervalos aleatorios" (T-06):** el visualizador usa el último estado del composable. Solo reinicia la escena si cambia el id de la pista.
- **Repetir la misma canción** (repetición de T-13, o "Previous" al principio de la pista): mismo id, misma semilla, sin reinicio.
- **Pistas sin id** (por ejemplo, ficheros locales de Spotify, con `id: null`): se usa el `uri`. Si tampoco hay `uri`, la semilla por defecto (escenario "Semilla de pistas sin id").
- **Episodios de podcast** (`type: "episode"`): se tratan igual que una canción.
- **Cambios rápidos de canción** (pulsar "Next" varias veces seguidas): cada cambio reinicia la escena. No se acumulan burbujas ni bucles: siempre hay como mucho un bucle activo.
- **Panel con tamaño 0** (por ejemplo, durante una transición): no se dibuja ni se sube `data-frames` hasta que `ResizeObserver` dé un tamaño mayor que 0.
- **Varias pestañas:** cada una tiene su visualizador independiente. Si la preferencia va en `localStorage`, se comparte, pero solo se lee al cargar.
- **Sin sesión:** la barra y el visualizador solo existen en el layout con sesión. El login no muestra el botón "Visualizer". No hay endpoints de backend en esta tarea.
- **Rotar el móvil o cambiar el tamaño de la ventana:** el canvas se redimensiona sin reiniciar la semilla ni la escena. Si la escena es fija, se redibuja una vez (por eso los tests no cambian el tamaño durante las mediciones de "exactamente 1").
- **Cambio de tema con el panel cerrado:** no hace nada. Los tokens se leen al abrir.

**Pistas para los tests**

- **Tests de navegador de Pest 4 (T-03), localizando por `data-aero` y con los atributos `data-state`, `data-seed`, `data-motion` y `data-frames`.** Para "no cambia durante 1 s" se lee `data-frames`, se espera 1 s y se vuelve a leer. En las transiciones desde `playing` (desconexión, movimiento reducido en vivo) no se cuenta un número exacto de fotogramas, porque un fotograma del bucle puede caer justo antes de que se procese el evento. En su lugar se espera 200 ms y se comprueba que `data-frames` no cambia durante 1 s; el redibujo fijo se verifica comparando `canvas.toDataURL()` antes y después del evento.
- **Pestaña oculta:** se redefine `document.visibilityState` con `Object.defineProperty` y se lanza `document.dispatchEvent(new Event('visibilitychange'))`.
- **Movimiento reducido:** se emula con la emulación de medios de Playwright. Para el cambio en vivo, se cambia la emulación con la página abierta.
- **Escenas comparables:** con movimiento reducido la escena es fija y determinista, así que `canvas.toDataURL()` se puede comparar entre canciones sin depender del tiempo.
- **Integración con el reproductor:** el SDK simulado de T-06 emite `player_state_changed` con distintas pistas, estados de `paused` y el estado `null`. `GET /spotify/player/current` se simula como en T-23.
- **localStorage que falla:** un script de inicio reemplaza `localStorage.getItem` y `setItem` por funciones que lanzan errores, y el test escucha `console` y `pageerror`.
- **Colores:** se redefinen los tokens en `<html>` con `style.setProperty`, o se inyecta una hoja con `[data-theme="test-red"]`, y se leen las 4 filas superiores con `getImageData`. Así el test no depende de los temas reales de T-18.
- **Sin contexto 2D:** un script de inicio reemplaza `HTMLCanvasElement.prototype.getContext` por una función que devuelve `null`.
- **Sin descargas:** se registran las peticiones de Playwright (`page.on('request')`) entre abrir el panel y 2 s después.
- **Determinismo de la semilla:** además del test de navegador, la función de hash se puede probar aislada si el proyecto tiene tests de JavaScript. Si no, bastan los escenarios de semilla.
- **`@manual`:** la validación del propietario, la comprobación de destellos y la medición en el móvil. Se anotan en el `design.md` del cambio.

**Fuera de alcance**

- Reaccionar al audio real (descartado por D8 y por las restricciones de arriba).
- Varios estilos o presets de visualizador, o elegir uno (D8: un solo estilo). Si llegan, van en otra tarea o en T-18.
- Los temas (T-18). Esta tarea solo usa los tokens y observa `data-theme`.
- El panel de letras (T-17). Esta tarea solo respeta la exclusión entre paneles.
- La reproducción de YouTube Music (T-10). El visualizador funciona con ella cuando exista, sin trabajo extra si el composable da el mismo estado.
- Pantalla completa del navegador (Fullscreen API) y salvapantallas.
- Media Session API y atajos de teclado globales.

**Pendiente**

- **Dónde se guarda la preferencia abierto/cerrado:** por navegador (`localStorage`, propuesto) o por usuario en el servidor, como el tema de T-18. No está confirmado con el usuario.
- **Nombres de los tokens de cielo** que usa el visualizador. Dependen de los nombres que fije T-03 en su fichero de tokens; los escenarios de colores usan "todos los tokens de cielo que usa el visualizador" hasta entonces.
- **Impacto en la batería del móvil:** se mide en la comprobación manual (15 minutos con el panel abierto). Si es alto, se decide en el cambio si se bajan los fps o las burbujas en el móvil.
- **Aspecto concreto** (paleta exacta, cantidad de rayos, forma de las olas): lo fija la revisión del propietario.

**Estimación: 5 puntos.**
- **Escena de canvas con burbujas, rayos y olas, semilla, pseudo-pulso y escena fija determinista (1,5 puntos):** un dibujo convincente con estética Aero lleva prueba y error.
- **Estados (incluidos el estado `null` y la desconexión), bucle a 30 fps, escenas fijas de un fotograma, pestaña oculta, movimiento reducido y cambio de tema (1 punto).**
- **Botón en la barra, panel "Now playing", Escape y foco, exclusión con "Lyrics", preferencia, sección de `/styleguide` y skill (0,5 puntos).**
- **Tests de navegador con `data-frames`, SDK simulado, emulación de medios, píxeles y `toDataURL` (1,5 puntos):** son muchos escenarios y tests poco habituales que cuesta dejar estables.
- **Revisión del propietario, comprobación de destellos y medición en el móvil (0,5 puntos).**

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Cola (T-13):** entre dos canciones de la cola puede llegar un estado en pausa en la posición 0 durante menos de 2 s, así que el visualizador pasa un momento a `paused`; se acepta. "Repeat one" mantiene el mismo id, así que la escena no se reinicia.
- **Pendiente:** tras recargar, la barra muestra la canción actual de la cola sin ningún estado del SDK. Con la regla actual eso es `idle` ("Nothing playing"); falta confirmar si debería ser `paused`.

**Seguimiento de aero-player-controls (T-21, 2026-11-11)**

- Los botones van en la ranura `extra` de `AeroPlayerBar`, como `AeroButton` con `aria-pressed` (modo o panel) o `aria-expanded`. Heredan el foco visible y el área táctil de 44x44 px. `/styleguide` tiene un ejemplo ("Extra").
- Los iconos nuevos van en `resources/aero/icons/` (24x24, `fill="currentColor"`), con su fila en `resources/aero/CREDITS.md` y su nombre en el tipo `AeroIconName` de `AeroIcon.vue`.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] `@manual` scenarios verified and recorded in the change's `design.md`
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
