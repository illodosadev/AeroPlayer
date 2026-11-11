---
id: T-17
title: Letra de la canción actual
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: 3.5
language: es
---

# T-17 — Letra de la canción actual

## Goal

Mostrar la letra de la canción que suena para seguirla sin salir del reproductor. Si LRCLIB tiene la letra **sincronizada**, la línea que se canta se resalta y el panel la sigue mientras avanza la canción. Si solo tiene la **letra sin sincronizar**, se muestra el texto completo.

La letra se pide con los datos de la canción que da el reproductor (título, artistas, álbum y duración), sin depender del servicio. Hoy la canción viene de Spotify (T-06). Cuando exista YouTube Music (T-10), funcionará igual si su reproductor da los mismos datos.

## Context

- Es un extra deseable del discovery ("Letra de la canción que suena"), para después de la v1.
- **Fuente decidida el 2026-11-09 (D9):** [LRCLIB](https://lrclib.net). Es gratuita y no pide API key. Los detalles de su API están en "Restricciones descubiertas".
- **Depende de:**
  - **T-06**, que aporta el composable del reproductor y el layout persistente de Inertia donde viven la barra y los paneles.
  - **T-21**, que aporta la barra de reproductor `AeroPlayerBar`, donde va el botón "Lyrics".
  - **T-03**, que aporta `AeroGlassPanel`, `AeroButton`, la regla global de movimiento reducido y los tests de navegador de Pest 4 (Playwright y Chromium).
  - **T-02**, porque solo el usuario con sesión iniciada puede pedir letras.
- **Lo que T-17 exige al composable del reproductor de T-06.** T-06 hoy no lo promete de forma explícita: lo muestra en la barra, pero no lo expone como API del composable. Debe exponer, de forma reactiva:
  - la canción actual, con `uri` (o id), `title`, `artists[]` (lista de nombres), `album` y `duration_ms`, sacados de `track_window.current_track` de `player_state_changed`;
  - la posición interpolada en milisegundos, a partir de `position`, `timestamp` y `paused` del último estado;
  - la posición nueva **justo después de un seek**, sin esperar a que el SDK emita otro estado;
  - `paused`.

  Si T-06 no lo incluye al implementarse, T-17 lo añade al composable en su propio cambio, dentro de la parte "Panel y resaltado" de la estimación. T-06 tiene anotada esta necesidad en sus Notas (orquestador del lote, 2026-11-09).
- **Comparte zona con T-16:** el panel "Now playing" del visualizador de T-16 ocupa la misma zona encima de la barra, en el mismo layout persistente. Los dos paneles son excluyentes (ver "Reglas propuestas").
- **Ya no depende de T-10** (D5, 2026-11-09: primero Spotify, sin depender del servicio). T-17 usa la canción que da el composable del reproductor. Con YouTube Music funcionará cuando exista T-10, si este da la canción con el mismo formato (ver "Pendiente").
- **No usa el cliente de Spotify de T-05:** las letras no salen de Spotify. El cliente de LRCLIB de esta tarea copia los patrones de T-05: timeout, errores tipados y una espera compartida tras un `Retry-After`, guardada en la caché compartida entre procesos.
- **Interfaz en inglés** (T-01): los textos de pantalla de los escenarios están en inglés.

## Acceptance criteria

```gherkin
# --- Endpoint de letras (backend; LRCLIB falseado con Http::fake) ---------------

Scenario: Letra sincronizada encontrada
  Given el usuario con sesión iniciada
  And LRCLIB (falseado) responde a "GET https://lrclib.net/api/get" con un registro con instrumental false, plainLyrics y syncedLyrics "[00:05.00] Line one\n[00:12.00] Line two\n[00:20.00] Line three"
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"], album "Discovery" y duration_ms 320357, con "Accept: application/json"
  Then se envía a LRCLIB una sola petición "GET /api/get" con track_name "One More Time", artist_name "Daft Punk", album_name "Discovery" y duration 320
  And no se envía ninguna petición a "/api/search"
  And la petición lleva la cabecera "User-Agent" configurada en "services.lrclib.user_agent"
  And la respuesta tiene estado 200 con type "synced"
  And "lines" vale [{"time_ms": 5000, "text": "Line one"}, {"time_ms": 12000, "text": "Line two"}, {"time_ms": 20000, "text": "Line three"}]

Scenario: Solo hay letra sin sincronizar
  Given el usuario con sesión iniciada
  And LRCLIB responde a "/api/get" con instrumental false, syncedLyrics null y plainLyrics "Line one\nLine two"
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la respuesta tiene estado 200, type "plain", "lines" null y "plain" "Line one\nLine two"

Scenario: Canción instrumental
  Given el usuario con sesión iniciada
  And LRCLIB responde a "/api/get" con instrumental true, plainLyrics null y syncedLyrics null
  When se pide "GET /lyrics" con title "Aerodynamic", artists ["Daft Punk"] y duration_ms 212000
  Then la respuesta tiene estado 200 y type "instrumental"

Scenario: Con varios artistas se envía solo el primero
  Given el usuario con sesión iniciada
  When se pide "GET /lyrics" con title "Get Lucky", artists ["Daft Punk", "Pharrell Williams"] y duration_ms 369000
  Then la petición "GET /api/get" lleva artist_name "Daft Punk"

Scenario: Si "/api/get" no encuentra la canción, se busca con "/api/search"
  Given el usuario con sesión iniciada
  And LRCLIB responde 404 "TrackNotFound" a "/api/get"
  And LRCLIB responde a "/api/search" con dos registros con letra sincronizada: uno de 335 segundos y otro de 321 segundos con syncedLyrics "[00:01.00] Fallback line"
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"], album "Discovery" y duration_ms 320357
  Then se envía a LRCLIB una sola petición "GET /api/search", con track_name "One More Time" y artist_name "Daft Punk" y sin album_name
  And la respuesta tiene type "synced" y su única línea es "Fallback line"

Scenario: En la búsqueda de respaldo se prefiere la letra sincronizada
  Given el usuario con sesión iniciada
  And LRCLIB responde 404 a "/api/get"
  And LRCLIB responde a "/api/search", en este orden, con un registro de 320 segundos solo con plainLyrics "Plain only" y otro de 321 segundos con syncedLyrics "[00:01.00] Synced line"
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la respuesta tiene type "synced" y su única línea es "Synced line"

Scenario: La búsqueda de respaldo descarta las canciones con otra duración
  Given el usuario con sesión iniciada
  And LRCLIB responde 404 a "/api/get"
  And LRCLIB responde a "/api/search" con un único registro de 335 segundos con letra
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la respuesta tiene estado 200 y type "not_found"

Scenario: Letra no encontrada
  Given el usuario con sesión iniciada
  And LRCLIB responde 404 a "/api/get" y un array vacío a "/api/search"
  When se pide "GET /lyrics" con title "zzqxjkwvvy", artists ["Nobody"] y duration_ms 200000
  Then la respuesta tiene estado 200, type "not_found", "lines" null y "plain" null

Scenario Outline: Duración fuera del rango de LRCLIB
  Given el usuario con sesión iniciada
  When se pide "GET /lyrics" con title "Long Mix", artists ["DJ"] y duration_ms <duración>
  Then la respuesta tiene estado 200 y type "not_found"
  And no se envía ninguna petición a LRCLIB

  Examples:
    | duración |
    | 0        |
    | 400      |
    | 3700000  |

# --- Caché ----------------------------------------------------------------------

Scenario: Una letra encontrada se guarda en caché 7 días
  Given el usuario con sesión iniciada y la letra de "One More Time" ya pedida a LRCLIB
  When se vuelve a pedir "GET /lyrics" con los mismos datos 6 días después
  Then la respuesta es la misma que la primera
  And no se envía ninguna petición nueva a LRCLIB
  When se vuelve a pedir 8 días después de la primera vez
  Then se envía una petición nueva a LRCLIB

Scenario: Un "no encontrada" se guarda en caché 24 horas
  Given el usuario con sesión iniciada y la letra de "zzqxjkwvvy" pedida y no encontrada
  When se vuelve a pedir 23 horas después
  Then la respuesta tiene type "not_found" y no se envía ninguna petición a LRCLIB
  When se vuelve a pedir 25 horas después de la primera vez
  Then se envía una petición nueva a LRCLIB

Scenario: La clave de caché no distingue mayúsculas ni espacios de los extremos
  Given la letra de "One More Time" de "Daft Punk" ya está en caché
  When se pide "GET /lyrics" con title "  one more time " y artists ["DAFT PUNK"], con el mismo álbum y la misma duración
  Then no se envía ninguna petición a LRCLIB y se devuelve la letra de la caché

# --- Errores de LRCLIB ------------------------------------------------------------

Scenario Outline: LRCLIB falla y la respuesta no se guarda en caché
  Given el usuario con sesión iniciada
  And LRCLIB <fallo> a <endpoint>
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la respuesta tiene estado 503 y el JSON tiene "error" = "lyrics_unavailable"
  And la respuesta no es una página de error HTML
  And no se guarda en caché ningún "not_found" para esa canción
  And al pedirla otra vez, una vez pasada la espera si la hay, se envía una petición nueva a LRCLIB

  Examples:
    | endpoint                                    | fallo                                                     |
    | "/api/get"                                  | responde 500 "UnknownError"                               |
    | "/api/get"                                  | responde 503 "ServerOverloaded" con "Retry-After: 1"      |
    | "/api/get"                                  | no responde antes del timeout (ConnectionException)       |
    | "/api/get"                                  | responde 502 con un cuerpo HTML que no es JSON            |
    | "/api/get"                                  | responde 200 con un cuerpo que no es JSON                 |
    | "/api/get"                                  | responde 400 "ValidationError"                            |
    | "/api/search", tras un 404 de "/api/get"    | responde 500 "UnknownError"                               |
    | "/api/search", tras un 404 de "/api/get"    | no responde antes del timeout (ConnectionException)       |

Scenario Outline: LRCLIB limita las peticiones (429) y la espera se respeta
  Given el usuario con sesión iniciada
  And LRCLIB responde a <endpoint> con estado 429 y <cabecera>
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la respuesta tiene estado 503 con "error" = "lyrics_unavailable"
  And no se guarda en caché ningún "not_found" para esa canción
  When <antes> después del 429 se pide "GET /lyrics" con title "Justice", artists ["Genesis"] y duration_ms 200000
  Then no se envía ninguna petición a LRCLIB y la respuesta tiene estado 503 con "lyrics_unavailable"
  When <después> después del 429 se pide otra vez
  Then se envía la petición a LRCLIB

  Examples:
    | endpoint                                  | cabecera                  | antes          | después         |
    | "/api/get"                                | la cabecera "Retry-After: 30" | 29 segundos | 40 segundos     |
    | "/api/get"                                | sin cabecera "Retry-After" | 4 segundos    | 7 segundos      |
    | "/api/search", tras un 404 de "/api/get"  | la cabecera "Retry-After: 30" | 29 segundos | 40 segundos     |

Scenario: Durante la espera por un 429 se sirven las letras de la caché
  Given la letra de "One More Time" en caché
  And LRCLIB respondió 429 con "Retry-After: 30" hace 5 segundos
  When se pide "GET /lyrics" para "One More Time" con los mismos datos
  Then la respuesta tiene estado 200 con la letra de la caché
  And no se envía ninguna petición a LRCLIB

Scenario: User-Agent por defecto
  Given el usuario con sesión iniciada
  And LRCLIB_USER_AGENT vacía, APP_NAME "AeroPlayer" y APP_URL "https://music.example.test"
  And LRCLIB simulado responde 404 a "/api/get" y una lista vacía a "/api/search"
  When se pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357
  Then la petición a LRCLIB lleva "User-Agent: AeroPlayer (https://music.example.test)"

# --- Validación y sesión ----------------------------------------------------------

Scenario Outline: Petición mal formada
  Given el usuario con sesión iniciada
  When se pide "GET /lyrics" con <entrada>
  Then la respuesta tiene estado 422
  And no se envía ninguna petición a LRCLIB

  Examples:
    | entrada                                                     |
    | title vacío o solo con espacios                             |
    | artists ausente, vacío o con solo nombres en blanco         |
    | sin duration_ms                                             |
    | duration_ms "abc"                                           |
    | duration_ms -1                                              |
    | un title de 256 caracteres                                  |

Scenario: Un visitante sin sesión pide una letra
  Given un visitante sin sesión iniciada
  When pide "GET /lyrics" con title "One More Time", artists ["Daft Punk"] y duration_ms 320357, con "Accept: application/json"
  Then la respuesta tiene estado 401 con "error" = "unauthenticated"
  And no se envía ninguna petición a LRCLIB

# --- Lectura del formato LRC --------------------------------------------------------

Scenario Outline: Variantes de marca de tiempo
  Given LRCLIB devuelve syncedLyrics "<lrc>"
  When se pide la letra a "GET /lyrics"
  Then "lines" vale [{"time_ms": <time_ms>, "text": "<texto>"}]

  Examples:
    | lrc                                            | time_ms | texto           |
    | [01:02] Line                                   | 62000   | Line            |
    | [01:02.50] Line                                | 62500   | Line            |
    | [01:02.505] Line                               | 62505   | Line            |
    | [00:10.00] <00:10.00>Word <00:10.50>by <00:11.00>word | 10000 | Word by word |

Scenario: Las líneas se ordenan y una línea con varias marcas de tiempo se repite
  Given LRCLIB devuelve syncedLyrics "[00:30.00] Chorus\n[00:10.00][00:50.00] Verse"
  When se pide la letra a "GET /lyrics"
  Then "lines" vale [{"time_ms": 10000, "text": "Verse"}, {"time_ms": 30000, "text": "Chorus"}, {"time_ms": 50000, "text": "Verse"}]

Scenario: Se aplica la etiqueta offset
  Given LRCLIB devuelve syncedLyrics "[offset:+500]\n[ar:Daft Punk]\n[00:10.00] Line"
  When se pide la letra a "GET /lyrics"
  Then "lines" vale [{"time_ms": 9500, "text": "Line"}]

Scenario: Las líneas vacías se conservan como pausas
  Given LRCLIB devuelve syncedLyrics "[00:10.00] Line\n[00:15.00] \n[00:40.00] Next"
  When se pide la letra a "GET /lyrics"
  Then "lines" tiene 3 elementos y el de 15000 ms tiene text ""

Scenario: Letra sincronizada ilegible
  Given LRCLIB devuelve syncedLyrics sin ninguna marca de tiempo válida y plainLyrics "Line one"
  When se pide la letra a "GET /lyrics"
  Then la respuesta tiene type "plain" y "plain" "Line one"

# --- Panel de letra (navegador, con el SDK simulado de T-06) --------------------------

Scenario: Abrir y cerrar el panel
  Given el usuario con sesión iniciada y el SDK simulado con "One More Time" sonando
  When pulsa el botón "Lyrics" de la barra de reproductor
  Then se abre el panel de letra, el botón tiene aria-pressed "true" y el foco está dentro del panel
  When vuelve a pulsar "Lyrics"
  Then el panel se cierra y el botón tiene aria-pressed "false"

Scenario: Escape cierra el panel
  Given el panel de letra abierto con el foco dentro
  When el usuario pulsa Escape
  Then el panel se cierra
  And el botón "Lyrics" tiene aria-pressed "false" y tiene el foco

Scenario: El panel de letra y el visualizador se excluyen
  # Solo cuando exista T-16. Si T-17 llega antes, este escenario lo implementa T-16.
  Given el panel de letra abierto
  When el usuario pulsa "Visualizer" (T-16)
  Then el panel de letra se cierra y "Lyrics" tiene aria-pressed "false"
  And el panel "Now playing" está abierto, "Visualizer" tiene aria-pressed "true" y el foco está dentro de ese panel
  When el usuario pulsa "Lyrics"
  Then el panel "Now playing" se cierra y "Visualizer" tiene aria-pressed "false"
  And el panel de letra está abierto y el foco está dentro de él

Scenario: La línea actual se resalta
  Given el panel de letra abierto
  And "/lyrics" devuelve type "synced" con líneas en 00:05.00, 00:12.00 y 00:20.00
  When el SDK simulado emite un estado con posición 12500 ms y paused false
  Then la línea de 00:12.00 tiene aria-current "true"
  And ninguna otra línea tiene aria-current

Scenario Outline: Atribución a LRCLIB
  Given el panel de letra abierto y "/lyrics" devuelve type "<tipo>"
  When se carga el panel
  Then el panel muestra "Lyrics from LRCLIB" con un enlace a "https://lrclib.net"

  Examples:
    | tipo         |
    | synced       |
    | plain        |
    | instrumental |

Scenario: El resaltado avanza con la canción
  Given la línea de 00:12.00 resaltada y la canción sonando desde la posición 12500 ms
  When la posición interpolada alcanza 20000 ms sin nuevos eventos del SDK
  Then la línea de 00:20.00 tiene aria-current "true" como mucho 1 segundo después
  And esa línea está dentro de la zona visible del panel

Scenario: En pausa se mantiene la línea
  Given la línea de 00:12.00 resaltada
  When el SDK emite un estado con posición 12500 ms y paused true
  And pasan 10 segundos
  Then la línea de 00:12.00 sigue teniendo aria-current "true"

Scenario: Al saltar a otro punto, el resaltado salta con él
  Given la línea de 00:05.00 resaltada en una canción de 180000 ms
  When el usuario mueve la barra de progreso a 0:21
  Then la línea de 00:20.00 tiene aria-current "true" antes de que el SDK emita un nuevo estado

Scenario: Antes de la primera línea no se resalta ninguna
  Given el panel abierto con líneas en 00:05.00, 00:12.00 y 00:20.00
  When el SDK emite un estado con posición 2000 ms
  Then ninguna línea tiene aria-current

Scenario: Letra sin sincronizar
  Given el panel de letra abierto y "/lyrics" devuelve type "plain" con "Line one\nLine two"
  When se carga el panel
  Then el panel muestra "Line one" y "Line two"
  And ninguna línea tiene aria-current

Scenario: Canción instrumental en el panel
  Given el panel de letra abierto y "/lyrics" devuelve type "instrumental"
  When se carga el panel
  Then el panel muestra "This track is instrumental."

Scenario: Letra no disponible
  Given el panel de letra abierto y "/lyrics" devuelve type "not_found"
  When se carga el panel
  Then el panel muestra "Lyrics not available."
  And el panel no muestra "Lyrics from LRCLIB"

Scenario: No suena nada
  Given el usuario con sesión iniciada y el reproductor sin ninguna canción
  When abre el panel de letra
  Then el panel muestra "Play a song to see its lyrics."
  And no se envía ninguna petición a "/lyrics"

Scenario: Canción sin título o sin artista
  Given el SDK simulado emite una canción con título vacío o sin artistas
  When el usuario abre el panel de letra
  Then el panel muestra "Lyrics not available."
  And no se envía ninguna petición a "/lyrics"

Scenario: Mientras carga
  Given "/lyrics" tarda en responder
  When el usuario abre el panel de letra
  Then el panel muestra "Loading lyrics…"

# --- Cambio de canción y peticiones solapadas -----------------------------------------

Scenario: La letra cambia con la canción
  Given el panel de letra abierto con la letra de "Track A"
  When el SDK emite un estado con "Track B"
  Then se envía una petición a "/lyrics" con los datos de "Track B"
  And el panel muestra la letra de "Track B"

Scenario: Una respuesta antigua no sustituye a la de la canción actual
  Given el panel abierto con "Track A" sonando y la respuesta de "/lyrics" para "Track A" retrasada
  When empieza "Track B" y su respuesta llega antes que la de "Track A"
  Then el panel muestra la letra de "Track B"
  And cuando llega la respuesta de "Track A", el panel sigue mostrando la letra de "Track B"

Scenario: La misma canción no se vuelve a pedir
  Given el panel abierto con la letra de "Track A" ya cargada
  When el SDK emite otros estados de "Track A" (pausa, seek o la misma canción en bucle)
  Then no se envía ninguna petición nueva a "/lyrics"

Scenario: Con el panel cerrado no se piden letras
  Given el panel de letra cerrado
  When suenan "Track A" y después "Track B"
  Then no se envía ninguna petición a "/lyrics"
  When el usuario abre el panel
  Then se envía una sola petición a "/lyrics", con los datos de "Track B"

Scenario: El panel sigue abierto al navegar por la app
  Given el panel de letra abierto con la letra de "Track A"
  When el usuario navega a otra página de la app con Inertia
  Then el panel sigue abierto con la misma letra
  And no se envía ninguna petición nueva a "/lyrics"

# --- Fallos, móvil y movimiento reducido ----------------------------------------------

Scenario Outline: Un fallo de las letras no afecta al reproductor
  Given el panel de letra abierto con "Track A" sonando
  And <fallo>
  When se carga el panel
  Then el panel muestra "Lyrics aren't available right now. Try again in a moment."
  And la página no navega ni redirige
  And la barra sigue mostrando "Track A"
  When el usuario pulsa "Pause"
  Then se llama a "pause()" del SDK simulado
  When el usuario cierra el panel y lo vuelve a abrir
  Then se envía una nueva petición a "/lyrics"

  Examples:
    | fallo                                                              |
    | "/lyrics" responde 503 con "lyrics_unavailable"                    |
    | "/lyrics" responde 401 con "unauthenticated" (sesión caducada)     |
    | la petición a "/lyrics" falla sin respuesta (navegador sin red)    |

Scenario: El panel en el móvil
  Given un viewport de 375x667 px y "One More Time" sonando
  When el usuario abre el panel de letra
  Then el panel ocupa todo el ancho de la zona por encima de la barra
  And document.documentElement.scrollWidth es menor o igual que 375
  And los controles de la barra de reproductor siguen visibles
  And el botón "Lyrics" tiene un área táctil de al menos 44x44 px

Scenario: Movimiento reducido
  Given el navegador con prefers-reduced-motion: reduce y el panel abierto con letra sincronizada
  When la línea resaltada pasa a la siguiente
  Then el panel se desplaza hasta ella sin animación (scrollIntoView con behavior distinto de "smooth")
  And ningún elemento del panel tiene animation-name distinto de "none"
  And las transiciones duran 0,01 s o menos

@manual
Scenario: La letra va a tiempo con el audio real
  Given Spotify Premium real conectado y el reproductor abierto en Chrome de escritorio
  When se escuchan 3 canciones conocidas con letra sincronizada en LRCLIB, con el panel abierto, incluyendo un seek y una pausa en cada una
  Then el resaltado coincide con lo que se canta, y se apunta el desfase observado de cada canción en el checklist del cambio
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D9: las letras salen de LRCLIB** (https://lrclib.net), que es gratis y no pide API key. Si LRCLIB tiene la letra sincronizada, se muestra con la línea actual resaltada y el panel la sigue. Si no, se muestra la letra sin sincronizar.
- **D5: primero Spotify, sin depender del servicio.** La letra se pide con los datos de la canción que da el reproductor. T-10 deja de ser requisito: con YouTube Music funcionará cuando exista T-10.
- Interfaz en inglés (T-01): el texto "Letra no disponible" del borrador pasa a "Lyrics not available.". Los escenarios siguen en castellano.

**Decisión del orquestador del lote (2026-11-09), compartida con T-16**

- **El panel de letra y el panel "Now playing" de T-16 se excluyen:**
  - abrir uno cierra el otro, y el foco pasa al panel que se acaba de abrir;
  - los dos botones ("Lyrics" y "Visualizer") usan `aria-pressed`.

  Se implementa con un único estado de "panel activo" en el layout persistente de T-06 (por ejemplo, `null`, `'lyrics'` o `'visualizer'`). La tarea que se haga en segundo lugar añade el escenario de exclusión.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **LRCLIB se llama desde el servidor de Laravel, no desde el navegador.** El endpoint `GET /lyrics` (JSON, middleware `auth`) llama a LRCLIB. Motivos:
  - el navegador no puede cambiar el `User-Agent`, y LRCLIB da más margen a un cliente identificado (unas 90 peticiones/min frente a 45);
  - la caché se comparte entre pestañas y dispositivos;
  - la espera tras un 429 se respeta en un solo sitio;
  - se prueba con `Http::fake`, como el resto de clientes de la app.
  - **Alternativa descartada:** llamar desde el navegador. LRCLIB lo permite (CORS `*`), pero no hay caché común ni `User-Agent`, y la espera habría que llevarla en cada pestaña.
- **Contrato de `GET /lyrics`:**
  - **Entrada:**
    - `title`: obligatorio.
    - `artists[]`: lista de nombres; hace falta al menos uno que no quede vacío. Es la misma forma que `artists` del formato común de T-07, y es el único nombre de parámetro.
    - `album`: opcional.
    - `duration_ms`: obligatorio.
  - **Validación:**
    - `title` y los nombres de artista se recortan, y no pueden quedar vacíos.
    - Cada texto tiene como máximo 255 caracteres.
    - `duration_ms` es un entero mayor o igual que 0.
    - Si algo no se cumple, la respuesta es 422.
  - **Salida:** `{ type: "synced" | "plain" | "instrumental" | "not_found", lines: [{ time_ms, text }] | null, plain: string | null }`.
  - **Errores:** 401 `unauthenticated` y 503 `lyrics_unavailable`. Siempre en JSON, nunca una página HTML ni un 500.
- **Cómo se busca en LRCLIB:**
  1. Primero `GET /api/get` con `track_name`, `artist_name`, `album_name` (si hay) y `duration` en segundos (`round(duration_ms / 1000)`).
  2. `artist_name` es **el primer artista** de la lista. LRCLIB espera un solo nombre, y el resto de artistas suele hacer que no encuentre la canción.
  3. Si responde 404, se hace **una sola** búsqueda de respaldo con `GET /api/search?track_name=&artist_name=`, sin `album_name`.
     - Solo valen los resultados que se diferencian de la duración de la canción en ±2 s como mucho (la misma tolerancia que usa `/api/get`).
     - Entre esos se elige, por este orden y aunque esté más abajo en la lista: el primero con letra sincronizada; si no hay, el primero con letra sin sincronizar; si tampoco, el primero instrumental.
     - Si no queda ninguno, `not_found`.
  4. Si la duración redondeada queda fuera de 1–3600 s, que es el rango que acepta LRCLIB (por ejemplo, `duration_ms` 0 o 400), no se llama a LRCLIB y se devuelve `not_found`.
- **Tipo de la respuesta:**
  - `instrumental: true` da `instrumental`.
  - Si hay `syncedLyrics` legible, `synced` (y `plain` también se rellena si viene).
  - Si no, pero hay `plainLyrics`, `plain`.
  - Si no hay ninguna de las dos, `not_found`.
- **Lectura del formato LRC en el servidor**, para devolver `lines` ya listas:
  - Se aceptan marcas `[mm:ss]`, `[mm:ss.xx]` (centésimas) y `[mm:ss.xxx]` (milésimas).
  - Una línea con varias marcas se repite en cada una.
  - Las líneas se ordenan por tiempo.
  - `[offset:±ms]` se aplica a todas las líneas (`+` adelanta la letra). Las demás etiquetas (`ar`, `ti`, `al`, `length`, `by`…) se ignoran.
  - El texto se recorta, y se quitan las marcas de palabra `<mm:ss.xx>` del formato ampliado, dejando un solo espacio entre palabras.
  - Las líneas vacías se conservan con `text: ""` (son pausas instrumentales).
  - Si no queda ninguna línea con marca válida, se trata como si no hubiera `syncedLyrics`.
- **Cliente de LRCLIB** (una clase, por ejemplo `LrclibClient`):
  - **Configuración:**
    - Base URL configurable (`services.lrclib.base_url`, por defecto `https://lrclib.net`).
    - Timeout de **5 s** por petición: la letra no es crítica, así que como mucho se tarda 10 s en el peor caso, con la búsqueda de respaldo.
    - Cabecera `User-Agent` desde `services.lrclib.user_agent` (variable `LRCLIB_USER_AGENT`). Si está vacía, se usa `"<APP_NAME> (<APP_URL>)"`: el nombre de la app y un enlace a su página, que es lo mínimo que pide LRCLIB. El valor definitivo está en "Pendiente".
  - **Errores:**
    - Se convierten en errores tipados (`unavailable`, `rate_limited`): 5xx, timeout, error de conexión, respuestas que no son JSON y 400 `ValidationError`. Un 400 indica un fallo de la app (la validación propia debería evitarlo), así que se registra en el log y no se guarda como `not_found`.
    - Un 404 `TrackNotFound` no es un error: es "no encontrada".
    - No se reintenta solo.
  - **Espera tras un 429:**
    - Con un 429, o un 503 con `Retry-After`, en `/api/get` o en `/api/search`, se guarda "bloqueado hasta" = ahora + `Retry-After` + un margen aleatorio de hasta el 30 % (lo pide LRCLIB), o 5 s (más el margen) si falta la cabecera. Mientras dure no se envía nada a LRCLIB.
    - Ese bloqueo va en la caché compartida entre procesos (database o redis, como en T-05).
- **Caché de letras** (Laravel Cache, sin tabla nueva):
  - La clave sale del primer artista, el título, el álbum y la duración en segundos, normalizados: recortados y en minúsculas.
  - TTL: **7 días** para `synced`, `plain` e `instrumental`. **24 horas** para `not_found`, porque el servicio de LRCLIB que busca letras en segundo plano puede añadirla más tarde.
  - Los errores no se guardan. Si `/api/get` da 404 y la búsqueda de respaldo falla, tampoco se guarda `not_found`.
  - La caché se consulta antes que el bloqueo por 429, así que las letras ya guardadas se sirven siempre.
- **Acceso a datos:** esta tarea no tiene modelos ni tablas, así que no hace falta repositorio. Si más adelante la caché pasa a una tabla, irá a través de un repositorio concreto en `app/Repositories/`, sin interfaz (`openspec/config.yaml`).
- **Panel de letra:**
  - Un botón "Lyrics" (`AeroButton` con icono y `aria-pressed`) en la barra de reproductor de T-21 abre y cierra un panel (`AeroGlassPanel`).
  - El panel ocupa la misma zona que el panel "Now playing" de T-16: cubre la zona de contenido, por encima de la barra, que sigue visible y usable. Tiene `role="region"` y `aria-label="Lyrics"`.
  - Excluye al panel de T-16 (ver la decisión del orquestador). Al abrirlo, el foco pasa al panel.
  - Escape, con el foco dentro, lo cierra y devuelve el foco al botón "Lyrics".
  - Vive en el layout persistente de T-06, así que sigue abierto al navegar con Inertia.
  - En el móvil (menos de 640 px de ancho, como T-16) ocupa todo el ancho de la zona por encima de la barra.
  - Con `synced`, `plain` e `instrumental`, el pie muestra la atribución "Lyrics from LRCLIB" con un enlace a `https://lrclib.net`. Con `not_found` y con errores no se muestra.
- **Cuándo se pide la letra:**
  - Solo con el panel abierto: al abrirlo y cuando cambia la canción. Con el panel cerrado no se pide nada.
  - Qué canción suena se reconoce por su URI o id (no por los eventos del SDK), así que una pausa, un seek o un bucle de la misma canción no vuelven a pedirla.
  - Cerrar y volver a abrir el panel tras un error vuelve a pedirla.
- **Fallos vistos desde el panel:** un 503, un 401 (sesión caducada) y una petición sin respuesta (sin red) muestran el mismo mensaje, "Lyrics aren't available right now. Try again in a moment.". El panel no redirige al login: qué hace la app con una sesión caducada es cosa de T-02, y el siguiente clic en la app lo resolverá.
- **Peticiones solapadas:**
  - Cada petición lleva el URI de su canción.
  - Al cambiar de canción se cancela la anterior (AbortController).
  - Por si acaso, se descarta cualquier respuesta cuyo URI no sea el de la canción actual.
- **Resaltado sincronizado:**
  - La línea actual es la última cuyo `time_ms` es menor o igual que la posición. La posición es la interpolada del composable de T-06, y tras un seek, la nueva posición, sin esperar al SDK.
  - Se marca con `aria-current="true"`. Cada línea lleva `data-lyrics-line` y `data-time-ms`, para los tests.
  - Antes de la primera línea no se resalta ninguna.
  - Una pausa (`text: ""`) se muestra como "♪" y también puede ser la línea actual.
  - En pausa, el resaltado no cambia.
  - El cálculo es una función pura (`currentLineIndex(lines, positionMs)`).
- **Desplazamiento y movimiento reducido:**
  - La línea actual se centra en el panel con `scrollIntoView`: `behavior: "smooth"` normalmente, y `"auto"` con `prefers-reduced-motion: reduce`.
  - Con movimiento reducido, las transiciones del panel duran 0,01 s o menos y `animation-name` es `none`. Es la regla global de T-03 y T-21.
- **Textos de la interfaz** (en inglés):
  - "Lyrics", "Lyrics from LRCLIB";
  - "Loading lyrics…";
  - "Lyrics not available.", "This track is instrumental.";
  - "Play a song to see its lyrics.";
  - "Lyrics aren't available right now. Try again in a moment.".

**Restricciones descubiertas** (documentación de LRCLIB consultada el 2026-11-09: https://lrclib.net/docs, versión en Markdown en https://lrclib.net/docs.md)

- **Acceso:**
  - "No API key is required, and anonymous API access remains available."
  - La base es `https://lrclib.net`.
  - La API tiene CORS abierto (`Access-Control-Allow-Origin: *`), así que se podría llamar desde el navegador.
- **`GET /api/get`:**
  - `track_name` y `artist_name` son obligatorios. `album_name` es opcional ("recommended"). `duration` va en segundos, entre 1 y 3600.
  - Sobre la duración: "LRCLIB will attempt to provide the lyrics only when the duration matches the record in LRCLIB's database, or at least with a difference of ±2 seconds in duration."
  - Si no encuentra la canción, responde `404` con `{"message": "Failed to find specified track", "name": "TrackNotFound", "statusCode": 404}`. Las canciones que faltan "can be picked up by the background lyrics fetching service and may become available in a later request".
  - También existe `GET /api/get/{id}`.
- **`GET /api/search`:**
  - Hace falta `q` o `track_name`; si no hay ninguno, devuelve un array vacío. `artist_name` y `album_name` son opcionales, y `q` tiene prioridad sobre los demás.
  - Devuelve como máximo 20 resultados y no tiene paginación.
  - Hay un `GET /api/v2/search` en beta que no trae el texto de la letra.
- **Campos de cada registro:**
  - `id`, `trackName`, `artistName`, `albumName`, `duration` (en segundos), `instrumental`, `hasWordSync`, `plainLyrics` (string o null), `syncedLyrics` (LRC con marcas por línea, string o null) y `lyricsfile` (YAML).
  - `plainLyrics` y `syncedLyrics` son `null` en los registros instrumentales.
  - En el ejemplo de la documentación, `syncedLyrics` termina con una marca sin texto (`[03:25.72] `).
- **Identificar el cliente:**
  - "We require you to identify your client in requests." Se pide un `User-Agent` con el nombre de la app, la versión y un enlace a su página o un email. Ejemplo: `LRCGET v0.2.0 (https://github.com/tranxuanthang/lrcget)`.
  - Desde el navegador, que no puede cambiar el `User-Agent`, se pueden usar `X-User-Agent` o `Lrclib-Client`.
  - Registrar la app es opcional y da más margen. Una app de servidor usa la cabecera secreta `Lrclib-Api-Key`, que nunca debe salir del servidor.
- **Límite de peticiones** (aproximado; "may change at any time without notice"):
  - Unas 45 peticiones/min por IP para clientes genéricos (sin `User-Agent`, navegador o librería HTTP común).
  - Unas 90/min por IP para un cliente identificado.
  - Más para las apps registradas.
  - Al superarlo, LRCLIB responde 429 `RateLimitExceeded` con `Retry-After` en segundos. El cliente "**must** honor `Retry-After`", con "a small random jitter (for example, up to 30% of the delay)". Ignorarlo "may result in a temporary ban".
  - Con picos muy fuertes, el borde de la red puede responder sin JSON: "Always rely on the status code and the `Retry-After` header rather than parsing the body."
- **Errores:** 400 `ValidationError` (por ejemplo, un `track_name` vacío o un `duration` fuera de rango), 404 `TrackNotFound`, 429 `RateLimitExceeded`, 500 `UnknownError` y 503 `ServerOverloaded` ("retry after the `Retry-After` header (usually 1 second)"). Los parámetros mal formados se rechazan con texto plano, no JSON: hay que comprobar el estado y el `Content-Type` antes de leer el cuerpo.
- **Condiciones de uso:** la documentación no tiene una sección de términos de uso. Solo pide un uso responsable porque "LRCLIB is a free service".
- **Formato LRC** (https://en.wikipedia.org/wiki/LRC_(file_format)):
  - Marcas `[mm:ss.xx]`.
  - Etiquetas `[ar:]`, `[ti:]`, `[al:]`, `[length:]`, `[offset:]`… El offset va en milisegundos y "+" adelanta la letra.
  - Varias marcas pueden preceder a una misma línea (estribillos).
  - El formato ampliado marca palabras con `<mm:ss.xx>`.
  - La documentación de LRCLIB no dice qué variantes usa, así que el lector acepta además `[mm:ss]` y `[mm:ss.xxx]`.

**Casos límite**

- **Título con sufijos de Spotify** ("One More Time - Radio Edit", "- 2011 Remaster"): pueden hacer que `/api/get` no encuentre la canción. En esta tarea no se limpian (ver "Pendiente").
- **Álbum distinto en Spotify y en LRCLIB** (recopilatorios, reediciones): si `/api/get` falla por eso, lo cubre la búsqueda de respaldo, que no envía el álbum.
- **Varios artistas:** solo se envía el primero. Si LRCLIB guarda "Daft Punk feat. Pharrell Williams", la búsqueda por palabras de `/api/search` lo encuentra igualmente.
- **La posición interpolada de T-06** puede desviarse hasta 1 s respecto al audio. El resaltado tiene ese mismo margen, y los tests lo usan.
- **La última línea vacía** de LRCLIB (`[03:25.72] `) se conserva como pausa: al final de la canción ya no queda resaltada la última línea cantada.
- **Una canción que no es una canción** (un episodio o un anuncio, si el estado del SDK lo indica) no tiene título y artista útiles. Se trata como "Canción sin título o sin artista": no se pide nada y se muestra "Lyrics not available.".
- **Cambios de canción muy rápidos** (pulsar "Next" varias veces): solo cuenta la petición de la última canción. Las demás se cancelan o se descartan, y en la caché solo entra lo que LRCLIB llegó a responder.
- **Dos pestañas abiertas:** comparten la caché del servidor, así que la segunda pestaña no vuelve a pedir la canción a LRCLIB.
- **Letras muy largas** (más de 200 líneas): el panel tiene su propio scroll, y solo se desplaza hasta la línea actual, no la página entera.
- **Seek hacia atrás:** el resaltado vuelve a la línea correspondiente. La regla es la misma ("última línea con `time_ms` ≤ posición").
- **El plan B móvil (T-15)** da la posición por Spotify Connect. Si su composable expone la misma posición, el panel funciona igual. Si no, el panel muestra la letra sin resaltar (ver "Pendiente").
- **Registrar la app en LRCLIB** no hace falta para un solo usuario. Si alguna vez se registra, `LRCLIB_API_KEY` va en el `.env` y nunca en el repositorio, que es público.
- **Visualizador abierto al cargar la app** (T-16 puede recordar su panel; dónde se guarda está pendiente en T-16): si el usuario abre "Lyrics", se aplica la exclusión de siempre y el visualizador se cierra.

**Pistas para los tests**

- **Falsear LRCLIB en Pest:**
  - `Http::fake()` con `lrclib.net/api/get*` y `lrclib.net/api/search*`, junto con `Http::preventStrayRequests()`.
  - Las respuestas se guardan en `tests/Fixtures/lrclib/` con la forma del ejemplo de la documentación (sincronizada, sin sincronizar, instrumental, 404 `TrackNotFound`, 429 `RateLimitExceeded`, 400 `ValidationError`).
  - Parámetros (y la ausencia de `album_name` en `/api/search`) y `User-Agent` se comprueban con `Http::assertSent` y `Http::assertNotSent`. Las peticiones a cada endpoint se cuentan con `Http::recorded()`. Que no se llamó a LRCLIB, con `Http::assertSentCount(0)`.
- **Errores y tiempo:**
  - Timeout: un callback de `Http::fake` que lanza `ConnectionException`.
  - Cuerpo que no es JSON: `Http::response('<html>…</html>', 502, ['Content-Type' => 'text/html'])`.
  - Que un fallo no deja `not_found` en caché: tras el error, `Cache::has` sobre la clave da falso.
  - `$this->travel(...)` para las esperas tras un 429 y para las caducidades de la caché (6 y 8 días, 23 y 25 horas).
  - El margen aleatorio se prueba en sus extremos: con `Retry-After: 30`, a los 29 s no se envía nada y a los 40 s (más que 30 × 1,3) sí; sin cabecera, a los 4 s no y a los 7 s (más que 5 × 1,3) sí. Si el diseño lo prefiere, se fija con una semilla o un valor inyectado.
- **Lector LRC:** test unitario de Pest sobre la clase lectora, con las variantes de marca, varias marcas por línea, offset, etiquetas, pausas, marcas de palabra y entrada ilegible.
- **Navegador (Pest 4, T-03, con el SDK simulado de T-06):**
  - `/lyrics` responde desde el servidor real con LRCLIB falseado, o se intercepta la ruta en Playwright si el diseño lo prefiere (necesario para el 401 y para la petición sin respuesta).
  - El resaltado se comprueba con el selector `[data-lyrics-line][aria-current="true"]` y su `data-time-ms`.
  - El desplazamiento se comprueba con un espía sobre `Element.prototype.scrollIntoView` que guarda su `behavior`, con y sin `prefers-reduced-motion: reduce` (emulación de medios de Playwright, como en T-03).
  - Las duraciones de transición y `animation-name` se leen con `getComputedStyle`, como en T-03 y T-21.
  - El foco se comprueba con `document.activeElement` (dentro del panel tras abrirlo, en "Lyrics" tras Escape).
  - Respuestas fuera de orden: retrasar la de "Track A".
  - Móvil: viewport de 375x667 px, como en T-16 y T-21.
- **Manual:** el escenario `@manual` se copia como lista de comprobación en el `tasks.md` del cambio.

**Fuera de alcance**

- Resaltado palabra a palabra (`hasWordSync`, `lyricsfile`, formato LRC ampliado).
- Publicar o marcar letras en LRCLIB (`/api/publish`, `/api/flag`).
- Elegir a mano entre varios resultados de LRCLIB, o corregir la letra si es de otra canción.
- Traducir letras o mostrar la letra en otros sitios (pantalla completa, karaoke, notificaciones).
- Letras de Spotify u otras fuentes.
- Registrar la app en el portal de desarrolladores de LRCLIB.
- La reproducción de YouTube Music (T-10) y su formato de canción.
- El visualizador y su panel "Now playing" (T-16). T-17 solo comparte con él la regla de exclusión.

**Pendiente (no decidido)**

- **Valor definitivo del `User-Agent`** de LRCLIB: LRCLIB pide nombre, versión y un enlace o un email (por ejemplo, la URL del repositorio público o un email de contacto). Mientras tanto se usa el valor por defecto `"<APP_NAME> (<APP_URL>)"`.
- **Limpiar el título antes de buscar** (quitar "- Remastered 2011", "- Radio Edit", "(feat. …)"): no se ha decidido si hace falta ni con qué reglas. Se revisará con el uso real.
- **Formato de canción de YouTube Music:** sus títulos y artistas pueden venir con otra forma ("Artist - Song (Official Video)"). Qué se envía a LRCLIB para esas canciones se decide en T-10 o en una tarea posterior.
- **Qué hace el desplazamiento automático si el usuario se mueve a mano por el panel** (pausar el seguimiento, botón "Follow lyrics"…).
- **Pulsar una línea para saltar a ese punto de la canción.**
- **Recordar si el panel estaba abierto** al recargar la pestaña (T-16 puede recordarlo para el suyo, pendiente; aquí tampoco se ha decidido).
- **Resaltado con el plan B móvil (T-15)**, según cómo exponga la posición.

**Tamaño y posible división**

Se mantiene como una sola tarea porque el contrato de `/lyrics` y el panel se prueban juntos, y el panel no sirve sin el endpoint. Si en `/opsx-propose` resulta demasiado grande para un cambio, se puede dividir en:
- **T-17a — Endpoint de letras:** endpoint, cliente de LRCLIB, caché y lector LRC. Unos 1,75 puntos.
- **T-17b — Panel de letra y resaltado:** panel, exclusión con T-16, resaltado y desplazamiento. Unos 1,75 puntos. Depende de T-17a.

**Estimación: 3,5 puntos.**
- **Endpoint `/lyrics`, cliente de LRCLIB y caché (1,25 puntos):** `/api/get` con búsqueda de respaldo, tolerancia de ±2 s, espera con margen aleatorio tras un `Retry-After` en los dos endpoints, errores tipados y TTL distintos. Copia patrones de T-05, pero es un cliente nuevo.
- **Lector LRC (0,5 puntos):** es una función pura y acotada, pero tiene varias variantes de formato.
- **Panel y resaltado (1,25 puntos):**
  - botón en la barra, Escape y foco, y exclusión con T-16;
  - estados vacíos y peticiones solo con el panel abierto;
  - respuestas fuera de orden;
  - resaltado con la posición interpolada y el seek;
  - desplazamiento con movimiento reducido, y móvil.

  Es la parte más delicada de frontend.
- **Fixtures, tests de Pest y de navegador y comprobación manual (0,5 puntos).**

**Seguimiento de aero-player-controls (T-21, 2026-11-11)**

- Los botones van en la ranura `extra` de `AeroPlayerBar`, como `AeroButton` con `aria-pressed` (modo o panel) o `aria-expanded`. Heredan el foco visible y el área táctil de 44x44 px. `/styleguide` tiene un ejemplo ("Extra").
- Los iconos nuevos van en `resources/aero/icons/` (24x24, `fill="currentColor"`), con su fila en `resources/aero/CREDITS.md` y su nombre en el tipo `AeroIconName` de `AeroIcon.vue`.

## Definition of Done

- [ ] Criterios automatizables cubiertos por tests; el escenario @manual, en el checklist del cambio
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
