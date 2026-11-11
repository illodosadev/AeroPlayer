---
id: T-13
title: Cola de reproducción con shuffle y repeat
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 8
language: es
---

# T-13 — Cola de reproducción con shuffle y repeat

## Goal

Ver y editar la cola de reproducción (añadir, quitar, reordenar) y usar aleatorio y repetición, para controlar qué suena a continuación. La cola se guarda en el servidor, así que es la misma en todos los dispositivos (D3). Cuando una canción falla, la cola salta sola a la siguiente (D4).

La cola se construye **independiente del servicio**: cada elemento lleva su `source` (formato común de T-07). Esta tarea se entrega y se prueba **solo con Spotify** (D5). YouTube Music se suma en T-10 sin rehacer el modelo ni la API.

## Context

- **Necesidad v1 del discovery:** "ver y editar la cola de reproducción, con shuffle y repeat".
- **Dependencias:**
  - **T-06:** la función `play({contextUri?, uris?, offset})`, el SDK simulado para los tests y los controles de la barra. Aquí "siguiente" y "anterior" pasan a mover la cola de la app, no el contexto de Spotify.
  - **T-07, T-08 y T-24:** son los puntos de entrada a la cola. Aportan las filas de canción donde va "Add to queue" (resultados de búsqueda, Liked Songs, playlists propias y pistas de un álbum) y el formato común de canción.
  - **T-21:** la barra y `AeroGlassPanel`. Esta tarea añade a la barra sus propios botones "Shuffle", "Repeat" y "Queue".
  - **T-23:** los avisos de error de Spotify y el caso de la reproducción en otro dispositivo.
  - **T-05:** el cliente HTTP de Spotify y el scope `user-modify-playback-state`, que ya está pedido.
  - **T-02:** la sesión.
- **Ya no depende de T-10** (D5). La cola mixta entre servicios y su criterio de 2 s pasan a T-10.
- **Cambia el comportamiento de otras tareas.** Al entregarse, las listas que se pueden leer (playlists propias, Liked Songs, álbumes) dejan de reproducirse como contexto de Spotify o como página de `uris`: ahora cargan la cola de la app. El cambio de OpenSpec que lo entregue (el cambio 2, ver "Tamaño y división") incluye deltas MODIFIED sobre los requisitos de T-06, T-08 y T-24 (ver Seguimiento).
- **T-14:** cada canción que T-14 carga en la cola lleva un `origin` opaco, que T-13 guarda y devuelve sin interpretarlo. T-14 lo usa para marcar como no disponible una canción de una playlist propia que falla.
- **Pregunta abierta 5:** resuelta por D2 para el cambio de servicio. La aplica T-10.
- **Se entrega en tres cambios** (ver "Tamaño y división" en Notes). Cada sección de los criterios indica a qué cambio pertenece.

## Acceptance criteria

Los escenarios con `@provisional` dependen de la heurística de fin de canción, que hay que verificar en el spike con el SDK real (ver Notes, "Escenarios provisionales"). Las rutas que nombran los escenarios son el contrato de la API (ver Notes, "API").

```gherkin
# ==============================================================================
# Cambio 1 — Cola en el servidor, panel y sincronización
# ==============================================================================

# --- API de la cola (backend) --------------------------------------------------

Scenario: Leer la cola guardada
  Given el usuario con sesión iniciada y una cola guardada con "Song A" (actual), "Song B" y "Song C"
  When el frontend pide "GET /queue"
  Then la respuesta tiene estado 200 con "version", "current_item_id", "shuffle" = false, "repeat" = "off", "mode" = "queue", "context" = null, "has_more" e "items"
  And "items" tiene las 3 canciones en orden, cada una con exactamente las claves item_id, source, id, uri, title, artists, album, duration_ms, image y origin
  And "current_item_id" es el item_id de "Song A"

Scenario: Cola que no existe todavía
  Given el usuario con sesión iniciada que nunca ha usado la cola
  When el frontend pide "GET /queue"
  Then la respuesta tiene estado 200 con "items" vacío, "current_item_id" null y "version" = 0

Scenario Outline: Un visitante sin sesión no accede a la cola
  Given un visitante sin sesión iniciada
  When pide "<petición>" como petición JSON
  Then la respuesta tiene estado 401 y el JSON tiene "error" = "unauthenticated"
  And no se guarda ningún cambio en la cola
  And no se envía ninguna petición a Spotify

  # Las tres últimas filas (POST /queue/continue, PUT /queue/context y PUT /spotify/player/modes) entran con el cambio 2.
  Examples:
    | petición                    |
    | GET /queue                  |
    | POST /queue/items           |
    | PUT /queue                  |
    | PATCH /queue                |
    | PATCH /queue/items/{item}   |
    | DELETE /queue/items/{item}  |
    | POST /queue/continue        |
    | PUT /queue/context          |
    | PUT /spotify/player/modes   |

Scenario: Añadir una canción guarda el cambio y sube la versión
  Given una cola con "version" = 4 y 2 canciones
  When el frontend envía "POST /queue/items" con version 4 y la canción "spotify:track:0DiWol3AO6WpXZgp0goxAV" en el formato común
  Then la respuesta tiene estado 200 con "version" = 5 y 3 canciones
  And la nueva canción es la última de "items", con "origin" = null

Scenario: El origen de una canción se guarda tal cual
  Given el usuario con sesión iniciada
  When el frontend envía "PUT /queue" con una canción cuyo origin es {"type": "own_playlist", "playlist_id": 12, "item_id": 345}
  Then "GET /queue" devuelve esa canción con el mismo origin, sin cambios

Scenario Outline: Canción no válida
  Given una cola con "version" = 4
  When el frontend envía "POST /queue/items" con version 4 y <canción>
  Then la respuesta tiene estado 422 con "error" = "invalid_track"
  And la cola sigue con "version" = 4

  Examples:
    | canción                                                   |
    | uri "spotify:album:2noRn2Aes5aoNVsU6iWThc"                |
    | uri "spotify:track:abc"                                   |
    | source "deezer"                                           |
    | "playable" = false                                        |
    | sin "title"                                               |
    | origin "own_playlist" (un texto, no un objeto)            |

Scenario Outline: Peticiones a la API de la cola que no se pueden aplicar
  Given una cola con "version" = 6, "Song A" sonada, "Song B" actual y "Song C", "Song D" y "Song E" pendientes
  When el frontend envía <petición>
  Then la respuesta tiene estado <estado> con "error" = "<código>"
  And la cola sigue con "version" = 6 y sin cambios

  Examples:
    | petición                                                                  | estado | código                     |
    | "PUT /queue" con "tracks" vacío                                           | 422    | tracks_required            |
    | "PUT /queue" con 3 canciones y current_index 3                            | 422    | current_index_out_of_range |
    | "PUT /queue" con 501 canciones                                            | 422    | queue_full                 |
    | "PATCH /queue/items/{item}" con version 6 y un item que no existe         | 404    | queue_item_not_found       |
    | "DELETE /queue/items/{item}" con version 6 y un item que no existe        | 404    | queue_item_not_found       |
    | "PATCH /queue/items/{item}" con version 6 para "Song B", la actual        | 422    | item_not_pending           |
    | "PATCH /queue/items/{item}" con version 6 para "Song A", ya sonada        | 422    | item_not_pending           |
    | "PATCH /queue/items/{item}" con version 6, "Song E" y position 3          | 422    | position_out_of_range      |

Scenario: La cola está llena
  Given una cola con 500 canciones
  When el usuario elige "Add to queue" en otra canción
  Then "POST /queue/items" responde 422 con "error" = "queue_full"
  And la página muestra "The queue is full (500 songs). Remove some songs first."
  And la cola sigue con 500 canciones

Scenario: Versión obsoleta por un cambio desde otro dispositivo
  Given el portátil y el móvil con la cola en "version" = 7
  And el móvil quita una canción y la cola pasa a "version" = 8
  When el portátil envía "POST /queue/items" con version 7
  Then la respuesta tiene estado 409 con "error" = "queue_version_conflict" y la cola actual con "version" = 8
  And el portátil muestra "The queue changed on another device."
  And el panel del portátil muestra la cola de la versión 8, sin la canción que intentó añadir
  And el portátil no vuelve a enviar "POST /queue/items"

Scenario: Sustituir la cola funciona aunque la versión sea antigua
  Given una cola en "version" = 9 y un dispositivo que la tiene cargada en "version" = 7
  When ese dispositivo envía "PUT /queue" con 3 canciones y current_index 1
  Then la respuesta tiene estado 200 con "version" = 10, las 3 canciones y "current_item_id" de la segunda

# --- Misma cola en todos los dispositivos (D3) ---------------------------------

Scenario: La misma cola en otro dispositivo
  Given en el portátil una cola con "Song C" (sonada), "Song A" (actual) y "Song D"
  When el usuario abre el reproductor en el móvil
  Then el panel "Queue" del móvil muestra "Song A" en "Now playing" y "Song D" en "Next up"

Scenario: Un dispositivo ve los cambios de otro al volver a la pestaña
  Given el reproductor abierto en el portátil con la cola en "version" = 3 y la pestaña oculta
  And desde el móvil se añade "Song E" y la cola pasa a "version" = 4
  When la pestaña del portátil vuelve a estar visible
  Then el portátil pide "GET /queue"
  And su panel "Queue" muestra "Song E" al final

Scenario: Un dispositivo ve los cambios de otro cada 30 segundos
  Given el reproductor abierto y visible en el portátil, con la cola en "version" = 3
  And desde el móvil se añade "Song E" y la cola pasa a "version" = 4
  When pasan 30 segundos con la pestaña del portátil visible
  Then el portátil ha pedido "GET /queue" una vez en ese tiempo
  And su panel "Queue" muestra "Song E" al final

Scenario: Una pestaña oculta no consulta la cola
  Given el reproductor abierto en el portátil con la pestaña oculta
  When pasan 90 segundos
  Then el portátil no pide "GET /queue"

# Entra con el cambio 2: necesita que la cola mande lo que muestra la barra.
Scenario: La posición dentro de la canción no se guarda
  Given "Song A" sonando en el minuto 2:10 como canción actual de la cola
  When el usuario recarga la página
  Then la barra muestra "Song A" en "0:00" con el botón "Play"
  And no se envía ninguna petición para empezar a reproducir hasta que el usuario pulsa "Play"

# --- Añadir y ver la cola ------------------------------------------------------

Scenario Outline: Añadir una canción a la cola desde una fila
  Given una canción sonando y una cola con 2 canciones pendientes
  When el usuario elige "Add to queue" en la canción "Song X" de <origen>
  Then "Song X" aparece al final de "Next up"
  And la página muestra "Added to queue"
  And la canción que suena no se interrumpe

  Examples:
    | origen                               |
    | los resultados de búsqueda (T-07)    |
    | la lista "Liked Songs" (T-08)        |
    | una playlist propia (T-08)           |
    | las pistas de un álbum (T-24)        |

Scenario: Las canciones no reproducibles no se pueden añadir
  Given resultados de búsqueda con 3 canciones, de las que la segunda está marcada "Not available"
  When se muestran sus filas
  Then la acción "Add to queue" de la segunda fila está desactivada
  And la de la primera y la de la tercera están activas

# Entra con el cambio 2: necesita que la cola mande lo que muestra la barra.
Scenario: Añadir a una cola vacía no empieza a sonar
  Given la cola vacía y nada sonando
  When el usuario elige "Add to queue" en "Song X"
  Then "Song X" es la canción actual de la cola y la barra la muestra con el botón "Play"
  And no se envía "PUT /spotify/player/play"

Scenario: Ver la cola
  Given una cola con la canción actual "Song A" y 3 canciones pendientes
  When el usuario pulsa "Queue" en la barra
  Then el panel muestra "Song A" en "Now playing" y las 3 pendientes en "Next up", en orden
  And cada fila muestra título, artistas, duración y la etiqueta "Spotify"
  And el botón "Queue" tiene aria-expanded = "true"

Scenario: Cola vacía
  Given la cola vacía
  When el usuario abre el panel "Queue"
  Then ve el mensaje "Your queue is empty."

# --- Editar la cola ------------------------------------------------------------

Scenario: Reordenar y quitar canciones
  Given una cola con las canciones A, B y C pendientes
  When el usuario mueve C al principio de "Next up" y quita B
  Then "Next up" queda C, A
  And al recargar la página "Next up" sigue siendo C, A

Scenario: Reordenar con el teclado
  Given una cola con las canciones A, B y C pendientes
  When el usuario enfoca la fila de C y pulsa su botón "Move up" dos veces
  Then "Next up" queda C, A, B

Scenario: Reordenar mientras suena no corta la canción
  Given "Song A" sonando y las canciones B y C pendientes
  When el usuario mueve C antes de B
  Then no se llama a "pause()" ni se envía "PUT /spotify/player/play"
  And la barra sigue mostrando "Song A" con el botón "Pause"

Scenario: Fallo del servidor al editar la cola
  Given una cola con 2 canciones pendientes
  And "POST /queue/items" responde 503 o la red está cortada
  When el usuario elige "Add to queue" en "Song X"
  Then la página muestra "Couldn't update the queue. Try again."
  And "Next up" sigue con las 2 canciones, sin "Song X"
  And la canción que suena no se interrumpe

Scenario: Spotify sin conectar
  Given el usuario con sesión iniciada, Spotify sin conectar y una cola guardada con 3 canciones
  When abre el panel "Queue"
  Then ve las 3 canciones
  And la barra muestra "Connect Spotify to play your music." y el botón "Play" está desactivado
  And puede quitar y reordenar canciones

# ==============================================================================
# Cambio 2 — Reproducción guiada por la cola (después del spike)
# ==============================================================================

# --- API de continuación, modo contexto y modos de Spotify (backend) -----------

Scenario Outline: Fallos al continuar una lista
  Given una cola en "version" = 5 con continuación de "Liked Songs" y <situación>
  When el frontend envía "POST /queue/continue" con version 5
  Then la respuesta tiene estado <estado> con "error" = "<código>"<extra>
  And la cola sigue con "version" = 5, las mismas canciones y "has_more" = true

  Examples:
    | situación                                                       | estado | código                     | extra               |
    | Spotify responde 429 con "Retry-After: 7"                       | 429    | rate_limited               | y "retry_after" = 7 |
    | Spotify responde 503                                            | 502    | spotify_unavailable        |                     |
    | el cliente de T-05 corta por timeout                            | 502    | spotify_unavailable        |                     |
    | Spotify sin conectar                                            | 409    | spotify_not_connected      |                     |
    | la conexión de T-05 en estado reconnect_required                | 409    | spotify_reconnect_required |                     |

Scenario: Continuar sin nada que continuar
  Given una cola en "version" = 5 sin continuación ("has_more" = false)
  When el frontend envía "POST /queue/continue" con version 5
  Then la respuesta tiene estado 422 con "error" = "no_continuation"
  And no se envía ninguna petición a Spotify

Scenario Outline: Contexto no válido
  Given el usuario con sesión iniciada y una cola en "version" = 5
  When el frontend envía "PUT /queue/context" con context_uri "<uri>" y title "Indie Picks"
  Then la respuesta tiene estado 422 con "error" = "invalid_context_uri"
  And la cola sigue con "version" = 5 y "mode" = "queue"

  Examples:
    | uri                                       |
    | spotify:album:2noRn2Aes5aoNVsU6iWThc      |
    | https://open.spotify.com/playlist/indie   |
    |                                           |

Scenario: Neutralizar los modos de Spotify en el dispositivo web
  Given Spotify conectado y Http::fake para "api.spotify.com" que responde 204
  When el frontend envía "PUT /spotify/player/modes" con device_id "web-1"
  Then Spotify recibe "PUT /v1/me/player/shuffle?state=false&device_id=web-1" y "PUT /v1/me/player/repeat?state=off&device_id=web-1"
  And la respuesta al frontend tiene estado 204

Scenario Outline: Fallos al neutralizar los modos de Spotify
  Given <situación>
  When el frontend envía "PUT /spotify/player/modes" con device_id "web-1"
  Then la respuesta tiene estado <estado> y el JSON tiene "error" = "<código>"
  And la respuesta no es una página de error HTML

  Examples:
    | situación                                               | estado | código                     |
    | Spotify responde 403 con reason "PREMIUM_REQUIRED"      | 403    | premium_required           |
    | Spotify responde 404 "Device not found"                 | 404    | device_not_found           |
    | Spotify responde 429 con "Retry-After: 7"               | 429    | rate_limited               |
    | Spotify responde 503                                    | 502    | spotify_unavailable        |
    | Spotify sin conectar                                    | 409    | spotify_not_connected      |

# --- Controles con la cola -----------------------------------------------------

Scenario: Con la cola vacía no hay siguiente ni anterior
  Given la cola vacía
  When se carga la barra
  Then los botones "Next" y "Previous" están desactivados

Scenario: Siguiente y anterior mueven la cola
  Given "Song B" sonando en "0:01", con "Song A" ya sonada y "Song C" pendiente
  When el usuario pulsa "Next"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song C>"] y no se llama a "nextTrack()"
  When el SDK emite un estado de "Song C" en "0:01" y el usuario pulsa "Previous"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"] y no se llama a "previousTrack()"

Scenario Outline: "Previous" reinicia la canción a partir de 3 segundos
  Given "Song B" sonando en la posición <posición> ms, con "Song A" ya sonada
  When el usuario pulsa "Previous"
  Then <resultado>

  Examples:
    | posición | resultado                                                        |
    | 2000     | se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"] |
    | 3000     | se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"] |
    | 3001     | se llama a "seek(0)" y sigue sonando "Song B"                    |
    | 5000     | se llama a "seek(0)" y sigue sonando "Song B"                    |

Scenario: "Previous" en la primera canción
  Given "Song A", la primera de la cola, sonando en "0:01"
  When el usuario pulsa "Previous"
  Then se llama a "seek(0)"

Scenario: Quitar la canción que suena
  Given "Song A" sonando y las canciones B y C pendientes
  When el usuario quita "Song A" de la cola
  Then se envía "PUT /spotify/player/play" con uris ["<uri de B>"]
  And "Now playing" muestra "Song B" y "Next up" muestra solo "Song C"

Scenario: Quitar la última canción, que es la que suena
  Given "Song A" sonando y ninguna canción pendiente
  When el usuario quita "Song A" de la cola
  Then se llama a "pause()"
  And el panel muestra "Your queue is empty."

Scenario: Saltar a una canción del panel
  Given "Song A" sonando y las canciones B, C y D pendientes
  When el usuario pulsa "Song C" en "Next up"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de C>"]
  And "Next up" muestra solo "Song D"

# --- Reproducir listas ---------------------------------------------------------

Scenario: Reproducir una canción dentro de una lista sustituye la cola
  Given "Liked Songs" abierta con 50 canciones cargadas, de las que 49 son reproducibles (la no reproducible está en el puesto 40), y "Song Y" en el puesto 2
  And una cola anterior con 5 canciones
  When el usuario pulsa "Song Y"
  Then se envía "PUT /queue" con las 49 canciones reproducibles en orden y "Song Y" como actual
  And se envía "PUT /spotify/player/play" con uris ["<uri de Song Y>"]
  And "Now playing" muestra "Song Y" y la primera de "Next up" es la canción del puesto 3

Scenario: Reproducir una canción desde la búsqueda
  Given resultados de búsqueda con "Song X" y una cola anterior con 5 canciones
  When el usuario pulsa "Song X"
  Then la cola queda solo con "Song X" como actual
  And se envía "PUT /spotify/player/play" con uris ["<uri de Song X>"]

Scenario: Reproducir un álbum carga sus pistas en la cola
  Given la página de un álbum de 14 pistas, todas reproducibles
  When el usuario pulsa "Play"
  Then la cola tiene las 14 pistas en orden con la primera como actual
  And se envía "PUT /spotify/player/play" con uris de la primera pista y sin context_uri

Scenario: Una lista más larga que el límite de la cola
  Given "Liked Songs" con 600 canciones reproducibles cargadas en pantalla
  When el usuario pulsa la primera
  Then se envía "PUT /queue" con 500 canciones, de la primera a la 500
  And la página muestra "Only the first 500 songs were added to the queue."

Scenario: La cola sigue más allá de la página cargada
  Given "Liked Songs" con 3200 canciones, de las que se cargaron 50 en la cola al pulsar la primera
  When el usuario pulsa "Next" hasta que empieza a sonar la canción 47, de modo que quedan 3 pendientes
  Then el frontend envía "POST /queue/continue"
  And Spotify recibe "GET /v1/me/tracks" con limit=50 y offset=50
  And la cola pasa a tener 100 canciones, con las nuevas al final

Scenario: Si falla la continuación, se reintenta con la canción siguiente
  Given una cola con continuación de "Liked Songs", la canción 47 de 50 sonando y "POST /queue/continue" que respondió 502 spotify_unavailable
  When el usuario pulsa "Next" y empieza a sonar la canción 48
  Then el frontend vuelve a enviar "POST /queue/continue" una sola vez
  And la página no muestra ningún aviso mientras queden canciones pendientes

@provisional
Scenario: La cola termina sin poder continuar
  Given una cola con continuación de "Liked Songs", la última canción cargada sonando y "POST /queue/continue" que responde 502 spotify_unavailable
  When termina la canción
  Then la reproducción se detiene y no se envía "PUT /spotify/player/play"
  And la cola muestra "Couldn't load more songs from Spotify."

# --- Avance automático ---------------------------------------------------------

@provisional
Scenario: Al terminar una canción empieza la siguiente
  Given "Song A" sonando y "Song B" pendiente
  When el SDK simulado emite el fin de "Song A"
  Then en menos de 500 ms se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"]
  And se envía "PATCH /queue" con "current_item_id" de "Song B"

@provisional
Scenario: El avance no espera al servidor
  Given "Song A" sonando, "Song B" pendiente y "PATCH /queue" responde 503
  When el SDK simulado emite el fin de "Song A"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"] sin esperar la respuesta de "PATCH /queue"
  And 5 segundos después se vuelve a enviar "PATCH /queue" una sola vez
  And cuando ese reintento también responde 503, la página muestra "Couldn't save the queue. Check your connection."

@provisional
Scenario: Un conflicto de versión durante el avance automático
  Given en el portátil "Song A" sonando, "Song B" y "Song C" pendientes y la cola en "version" = 7
  And desde el móvil se quita "Song B" y la cola pasa a "version" = 8
  When termina "Song A" en el portátil y "PATCH /queue" con version 7 responde 409 con la cola de la versión 8
  Then el portátil envía "PUT /spotify/player/play" con uris ["<uri de Song C>"]
  And envía "PATCH /queue" con version 8 y "current_item_id" de "Song C" una sola vez
  And "Now playing" muestra "Song C"

@provisional
Scenario: El reintento tras un conflicto también falla
  Given un avance automático cuyo "PATCH /queue" respondió 409 y cuyo reintento con la versión nueva también responde 409
  When llega la segunda respuesta 409
  Then el portátil adopta la cola de esa respuesta y no envía más "PATCH /queue"
  And la página muestra "The queue changed on another device."

@provisional
Scenario: Fin de la cola
  Given la repetición en "Repeat: off" y la última canción de la cola sonando
  When termina la canción
  Then la reproducción se detiene y no se envía "PUT /spotify/player/play"
  And la cola muestra "No more songs in the queue."
  And el botón "Next" está desactivado

@provisional
Scenario: Spotify pone otra canción por su cuenta al terminar
  Given "Song A" sonando y "Song B" pendiente
  When el SDK emite un estado con una canción "Radio Track" que no está en la cola y paused = false
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"]

@provisional
Scenario Outline: Spotify pone otra canción después del fin de la cola
  Given la cola terminada hace <tiempo> con la repetición en "Repeat: off"
  When el SDK emite un estado con "Radio Track", que no está en la cola, y paused = false
  Then <resultado>

  Examples:
    | tiempo      | resultado                                                        |
    | 2 segundos  | se llama a "pause()"                                             |
    | 6 segundos  | no se llama a "pause()" y la barra muestra "Radio Track" sonando |

Scenario: Añadir después del fin de la cola
  Given la cola terminada con "Song C" como última canción
  When el usuario elige "Add to queue" en "Song X"
  Then "Song X" aparece en "Next up" y el botón "Next" se activa
  And no empieza a sonar hasta que el usuario pulsa "Next" o la pulsa en el panel

# --- Aleatorio y repetición de Spotify -----------------------------------------

Scenario: Se neutralizan el aleatorio y la repetición propios de Spotify
  Given la cola sonando en el dispositivo web "web-1"
  When el SDK emite un estado con shuffle = true y repeat_mode = 2
  Then el frontend envía "PUT /spotify/player/modes" con device_id "web-1" una sola vez

Scenario: Falla la neutralización por límite de peticiones
  Given la cola sonando con "Song A" y "PUT /spotify/player/modes" responde 429 rate_limited
  When el SDK emite varios estados de "Song A" con repeat_mode = 2
  Then el frontend envía "PUT /spotify/player/modes" una sola vez mientras suena "Song A"
  And la barra no muestra ningún aviso

Scenario: Falla la neutralización porque la cuenta no tiene Premium
  Given la cola sonando y "PUT /spotify/player/modes" responde 403 premium_required
  When el SDK emite un estado con repeat_mode = 2
  Then la barra muestra "Spotify Premium is required to play music here." (T-23)

@provisional
Scenario: La repetición de Spotify sigue activa y la canción vuelve a empezar
  Given "Song A" sonando, "Song B" pendiente y la neutralización de los modos falló
  When el SDK emite "Song A" en la posición 179000 ms de 180000 y después "Song A" en la posición 500 ms con paused = false, sin que la app haya llamado a "seek()"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"]

# --- Canciones que fallan (D4) -------------------------------------------------

Scenario: Una canción que falla se salta
  Given "Song A" sonando y "Song B" y "Song C" pendientes
  When empieza "Song B" y el SDK emite "playback_error"
  Then la barra muestra "This song can't be played."
  And se envía "PUT /spotify/player/play" con uris ["<uri de Song C>"]

Scenario Outline: El fallo de una canción se informa con su origen
  Given "Song B" en la cola con origin {"type": "own_playlist", "playlist_id": 12, "item_id": 345}
  When "Song B" falla por <motivo>
  Then el frontend emite el evento de fallo de canción con el item_id de "Song B", ese origin y reason "<reason>"

  Examples:
    | motivo                                                   | reason          |
    | un "playback_error" del SDK                              | playback_error  |
    | 10 segundos sin un estado del SDK con "Song B" sonando   | start_timeout   |

Scenario: La canción no empieza en 10 segundos
  Given "Song B" pedida con "PUT /spotify/player/play" y "Song C" pendiente
  When pasan 10 segundos sin un estado del SDK con "Song B" y paused = false
  Then la barra muestra "This song can't be played."
  And se envía "PUT /spotify/player/play" con uris ["<uri de Song C>"]

Scenario: Tres fallos seguidos detienen la reproducción
  Given una cola con B, C, D y E pendientes
  When fallan B, C y D, una detrás de otra
  Then la barra muestra "Couldn't play 3 songs in a row. Playback stopped."
  And no se envía "PUT /spotify/player/play" para "Song E"
  And "Song D" queda como actual y el botón muestra "Play"

Scenario: Una canción que suena reinicia la cuenta de fallos
  Given una cola con B, C, D, E y F pendientes
  When fallan B y C, suena D y después fallan E y F
  Then no aparece "Couldn't play 3 songs in a row. Playback stopped."

Scenario Outline: Los errores que no son de la canción no la saltan
  Given "Song A" sonando y "Song B" y "Song C" pendientes
  When al pedir "Song B" ocurre <error>
  Then la barra muestra el aviso de T-23 para ese error
  And no se envía "PUT /spotify/player/play" para "Song C"
  And no aparece "This song can't be played."

  Examples:
    | error                                                       |
    | "PUT /spotify/player/play" responde 429 rate_limited        |
    | "PUT /spotify/player/play" responde 403 premium_required    |
    | "PUT /spotify/player/play" responde 502 spotify_unavailable |
    | el SDK emite "not_ready"                                    |

# --- Otro dispositivo y playlists que solo se siguen ---------------------------

Scenario: La reproducción pasa a otro dispositivo
  Given "Song A" sonando en la web y "Song B" pendiente
  When la barra muestra "Playing on Kitchen Speaker" (T-23)
  Then la web no envía "PUT /spotify/player/play" mientras dure ese aviso
  And la cola guardada sigue con "Song A" como actual

Scenario: Reproducir una playlist que solo se sigue
  Given una cola con "Song A" (actual) y "Song B" pendiente
  When el usuario pulsa "Play" en "Indie Picks", que solo sigue
  Then se envía "PUT /queue/context" con context_uri "spotify:playlist:indie" y title "Indie Picks"
  And se envía "PUT /spotify/player/play" con context_uri "spotify:playlist:indie"
  And el panel "Queue" muestra 'Playing "Indie Picks" from Spotify. Its songs can't be shown in the queue.' y el botón "Back to my queue"
  And los botones "Shuffle" y de repetición están desactivados
  And "Next" llama a "nextTrack()" del SDK
  And la cola guardada sigue con "Song A" y "Song B"

Scenario: Volver a la cola desde una playlist que solo se sigue
  Given "Indie Picks" sonando como contexto de Spotify y la cola guardada con "Song A" como actual
  When el usuario pulsa "Back to my queue"
  Then se envía "PATCH /queue" con "mode" = "queue"
  And se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"]
  And el panel vuelve a mostrar "Now playing" y "Next up"

# --- Comprobación manual con Spotify real (cambio 2) ---------------------------

@manual @provisional
Scenario: La cola suena de verdad
  Given Spotify Premium real y una cola de 4 canciones en el reproductor de escritorio
  When se deja sonar la cola entera con "Repeat: off", se prueba "Previous" antes y después de 3 s y una canción no disponible en el país de la cuenta
  Then cada canción empieza menos de 2 s después de terminar la anterior
  And al final de la cola no suena ninguna canción de la radio de Spotify
  And se anota cómo emite el SDK real el fin de una canción

# ==============================================================================
# Cambio 3 — Aleatorio y repetición de la app
# ==============================================================================

# --- Aleatorio -----------------------------------------------------------------

Scenario: Aleatorio
  Given una cola con 10 canciones pendientes y la semilla fija de los tests
  When el usuario activa "Shuffle"
  Then "Next up" muestra las mismas 10 canciones, en un orden distinto
  And el orden de "Next up" contiene cada una de las 10 exactamente una vez, así que ninguna se repite antes de que suenen todas

Scenario: Desactivar el aleatorio recupera el orden
  Given una cola con A, B, C, D y E pendientes y "Shuffle" activado, con "Next up" en otro orden
  And ya ha sonado C
  When el usuario desactiva "Shuffle"
  Then "Next up" queda A, B, D, E
  And la canción que suena no cambia

Scenario: Lo añadido con el aleatorio activado va al final
  Given "Shuffle" activado y 3 canciones pendientes
  When el usuario elige "Add to queue" en "Song X"
  Then "Song X" es la última de "Next up"

Scenario: Los modos se ven igual en otro dispositivo
  Given en el portátil "Shuffle" activado y el botón de repetición en "Repeat: one"
  When el usuario abre el reproductor en el móvil
  Then en el móvil el botón "Shuffle" tiene aria-pressed = "true" y el botón de repetición se llama "Repeat: one"

# --- Repetición ----------------------------------------------------------------

Scenario: El botón de repetición pasa por los tres modos
  Given el botón de repetición llamado "Repeat: off"
  When el usuario lo pulsa tres veces
  Then su nombre pasa a "Repeat: all", después a "Repeat: one" y vuelve a "Repeat: off"
  And cada cambio se guarda con "PATCH /queue"

@provisional
Scenario: Repetición de la cola
  Given la repetición en "Repeat: all", "Shuffle" desactivado y la cola A, B y C con "Song C" sonando
  When termina "Song C"
  Then en menos de 500 ms se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"]
  And se envía "PATCH /queue" con "current_item_id" de "Song A"
  And "Now playing" muestra "Song A" y "Next up" muestra B, C

@provisional
Scenario: Repetición de la cola con el aleatorio activado
  Given la repetición en "Repeat: all", "Shuffle" activado, la semilla fija de los tests y la cola A, B, C y D con "Song D" sonando como última
  When termina "Song D"
  Then se envía "PATCH /queue" con restart = true
  And su respuesta tiene las mismas 4 canciones en un orden nuevo cuya primera no es "Song D"
  And se envía "PUT /spotify/player/play" con uris de la primera canción de esa respuesta
  And "Next up" muestra las otras 3 en el orden de la respuesta

@provisional
Scenario: Falla el barajado al volver a empezar
  Given la repetición en "Repeat: all", "Shuffle" activado y la cola A, B, C y D con "Song D" sonando como última
  And "PATCH /queue" con restart = true responde 500
  When termina "Song D"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"], la primera del orden actual, sin barajar

@provisional
Scenario: Repetir una canción
  Given la repetición en "Repeat: one" y "Song A" sonando con "Song B" pendiente
  When termina "Song A"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song A>"] y no se llama a "seek()"
  When el usuario pulsa "Next"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song B>"]

@manual @provisional
Scenario: Aleatorio y repetición reales
  Given Spotify Premium real y una cola de 4 canciones en el reproductor de escritorio
  When se prueban "Shuffle", "Repeat: all" y "Repeat: one" dejando terminar las canciones
  Then cada canción empieza menos de 2 s después de terminar la anterior
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D2 (resuelve la pregunta abierta 5):** se acepta una pausa breve al cambiar de servicio: la canción siguiente tiene que empezar menos de 2 s después de que termine la anterior. Por D5, ese criterio entre servicios se aplica en T-10. **D2 no cubre el paso entre dos canciones de Spotify.** Que ahí también sean menos de 2 s (en los escenarios `@manual` de esta tarea) es una propuesta de T-13, por coherencia con D2.
- **D3:** la cola **se guarda en el servidor**. Es la misma en todos los dispositivos: orden, canción actual y estado de aleatorio y repetición. Implica tablas propias, repositorio, una API con la que se sincroniza el cliente y una regla para las ediciones simultáneas (ver Reglas propuestas).
- **D4 (resuelve el pendiente de T-10 y T-23):** cuando una canción de la cola falla, se muestra "This song can't be played." y se salta a la siguiente. Tras 3 fallos seguidos, la reproducción se detiene. El texto de ese aviso es una propuesta (ver Reglas propuestas).
- **D5:** primero Spotify, sin atarse a ningún servicio. El modelo ya lleva `source`, pero esta tarea se entrega y se prueba solo con Spotify. El escenario "Cola mixta entre servicios" sale de T-13 y pasa a T-10. T-13 deja de depender de T-10.
- La interfaz está en inglés: los textos de pantalla de los escenarios están en inglés.

**Decisiones del orquestador (2026-11-09, coordinadas con T-14)**

- **Campo `origin`:** cada elemento de la cola tiene un `origin` opcional y opaco, por ejemplo `{type: "own_playlist", playlist_id, item_id}`, o null. Se envía en `PUT /queue` (y se admite en `POST /queue/items`) y se devuelve en `GET /queue`. T-13 nunca lo interpreta. T-14 es dueña del endpoint que marca una canción como no disponible: el cliente lo llama cuando T-13 informa de un `playback_error` en un elemento cuyo origen es una playlist propia.
- **Límite de 500 canciones** en la cola. T-14 baja su límite de playlist a 500 para que cuadren.
- **Tres cambios** al proponer (ver "Tamaño y división").

**Tamaño y división**

Con 8 puntos es la tarea más grande del backlog. Se mantiene en un solo fichero, pero se propone en **tres cambios** de OpenSpec al hacer `/opsx-propose`. El orquestador puede convertirlos más adelante en tareas del backlog.

1. **Cola en el servidor, panel, añadir, quitar, reordenar y sincronización (unos 3 puntos).** Las tablas, los repositorios, la API con versión y 409, la validación y el límite, el panel "Queue" con "Add to queue" en las filas de T-07, T-08 y T-24, y la consulta al volver a la pestaña y cada 30 s. En este cambio la reproducción sigue como hoy (T-06, T-08 y T-24): la cola se ve y se edita, pero todavía no manda lo que suena.
   - Mientras solo exista el cambio 1, la reproducción sigue siendo la de T-06: "Now playing" y "canción actual" significan el `current_item_id` de la cola, no necesariamente lo que suena en la barra. Los escenarios en los que la barra muestra la canción actual de la cola entran con el cambio 2.
2. **Reproducción guiada por la cola (unos 3,5 puntos). Empieza después del spike del fin de canción.** El avance automático, "Next" y "Previous" sobre la cola, las listas que sustituyen la cola, la continuación, los fallos de D4 con el evento para T-14, el modo contexto, el otro dispositivo y la neutralización del aleatorio y la repetición de Spotify, que hace falta para detectar el fin de una canción. Lleva los deltas MODIFIED sobre T-06, T-08 y T-24. Depende del cambio 1.
3. **Aleatorio y repetición de la app (unos 1,5 puntos).** El barajado visible, recuperar el orden, los tres modos de repetición y el barajado al volver a empezar. Depende del cambio 1 y, para la repetición, del cambio 2.

**Escenarios provisionales**

- Los escenarios marcados `@provisional` dependen de la heurística de fin de canción, porque el SDK no tiene un evento propio (ver Restricciones descubiertas). Se dan por buenos solo después del spike con el SDK real. Si el spike cambia la heurística, se revisan antes de implementar el cambio 2 o el 3.
- Los de "2 s" en `@manual` también dependen de la pregunta abierta 3 en el móvil: con la pantalla bloqueada puede que la cola no avance. Los tests automáticos no lo cubren. Se comprueba en el spike del móvil.
- Los demás escenarios no dependen de la heurística: usan "Next", el panel o eventos explícitos del SDK (`playback_error`, `not_ready`).

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Textos de la interfaz** (en inglés):
  - *Barra:* "Shuffle" (con `aria-pressed`), "Repeat: off" / "Repeat: all" / "Repeat: one" (nombre accesible del botón de repetición), "Queue" (con `aria-expanded`).
  - *Filas:* "Add to queue", y la confirmación "Added to queue".
  - *Panel:* "Now playing", "Next up", "Remove", "Move up", "Move down", "Your queue is empty.", "No more songs in the queue.", "Back to my queue".
  - *Avisos:*
    - "The queue is full (500 songs). Remove some songs first."
    - "Only the first 500 songs were added to the queue."
    - "The queue changed on another device."
    - "Couldn't update the queue. Try again."
    - "Couldn't save the queue. Check your connection."
    - "Couldn't load more songs from Spotify."
    - "Couldn't play 3 songs in a row. Playback stopped." (D4)
    - 'Playing "<playlist>" from Spotify. Its songs can't be shown in the queue.'
  - Se reutilizan "This song can't be played." y "Spotify Premium is required to play music here." (T-23) y "Connect Spotify to play your music." (T-06).
- **Modelo de datos y acceso:**
  - Tabla `queues`: una por usuario, con `current_item_id`, `shuffle`, `repeat` (`off`, `all`, `one`), `mode` (`queue` o `spotify_context`), `context_uri`, `context_title`, `continuation` (JSON con la lista de origen y su cursor opaco de T-08, o null) y `version` (entero).
  - Tabla `queue_items`: `queue_id`, `position` (orden actual), `original_position` (orden sin aleatorio), los campos del formato común de canción de T-07 (`source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image`) y `origin` (JSON o null).
  - Todo el acceso pasa por clases concretas `QueueRepository` y `QueueItemRepository` en `app/Repositories`, inyectadas por el contenedor, sin interfaces (convención del proyecto). Ni el controlador ni las reglas de validación consultan los modelos directamente.
  - Las canciones ya sonadas se quedan en la tabla, antes de la actual, para que funcionen "Previous" y "Repeat: all". El panel solo muestra la actual y las pendientes.
- **API.** Las rutas son **el contrato que usan los escenarios**, como en T-06. Si `/opsx-propose` las cambia, se cambian también los escenarios. Todas piden sesión y responden en JSON, con 401 `unauthenticated` sin sesión.
  - `GET /queue`: el estado completo. Cada elemento tiene exactamente `item_id`, `source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image` y `origin`. `has_more` indica si hay `continuation`, que no se expone.
  - `PUT /queue {tracks, current_index, continuation?}`: sustituye la cola. **No pide versión**: reproducir una lista es una orden explícita y siempre gana. Errores: 422 `tracks_required` (lista vacía), 422 `current_index_out_of_range` y 422 `queue_full` (más de 500). El cliente recorta a 500 antes de enviar, así que el 422 solo protege el servidor.
  - `POST /queue/items {version, tracks}`: añade al final. Errores: 422 `invalid_track` y 422 `queue_full`.
  - `PATCH /queue/items/{item} {version, position}`: mueve una canción pendiente. `position` es el índice dentro de "Next up", empezando en 0. Errores: 404 `queue_item_not_found`, 422 `item_not_pending` (la actual o una ya sonada) y 422 `position_out_of_range`.
  - `DELETE /queue/items/{item}?version=`: quita una canción. Error: 404 `queue_item_not_found`.
  - `PATCH /queue {version, current_item_id?, shuffle?, repeat?, mode?, restart?}`: avance, modos, salida del modo contexto y vuelta al principio (`restart`, que baraja de nuevo si el aleatorio está activado).
  - `POST /queue/continue {version}`: carga la página siguiente de la lista de origen. Errores: 422 `no_continuation`, y los de Spotify con la tabla de T-06 (429 `rate_limited` con `retry_after`, 502 `spotify_unavailable`, 409 `spotify_not_connected` y 409 `spotify_reconnect_required`). Si falla, la cola no cambia y conserva la continuación.
  - `PUT /queue/context {context_uri, title}`: entra en el modo contexto, sin versión, como `PUT /queue`. `context_uri` cumple `^spotify:playlist:[A-Za-z0-9]+$`. Si no, 422 `invalid_context_uri`.
  - `PUT /spotify/player/modes {device_id}`: envía a Spotify `PUT /v1/me/player/shuffle?state=false` y `PUT /v1/me/player/repeat?state=off` para ese dispositivo con el cliente de T-05, y responde 204. Usa la tabla de errores de T-06.
  - Toda escritura de la cola sube `version` en 1 y responde con el estado completo. Toda escritura con versión obsoleta responde 409 `queue_version_conflict` con el estado actual.
- **Ediciones simultáneas desde dos dispositivos (control optimista):**
  - Toda escritura, salvo `PUT /queue` y `PUT /queue/context`, lleva la `version` que el cliente tiene. Si no coincide con la del servidor, responde **409 `queue_version_conflict`** con el estado actual.
  - Si el 409 llega por una **acción del usuario**, el cliente adopta el estado del servidor, muestra "The queue changed on another device." y no reintenta: el usuario repite la acción si quiere.
  - Si llega por un **avance automático** (fin de canción, salto por fallo, continuación), el cliente adopta el estado del servidor y recalcula sobre él la canción que toca después de la que terminó. Si es la que ya está sonando, solo reintenta guardar. Si es otra, la pide con `play` y reintenta guardar. Reintenta una sola vez: si vuelve a recibir 409, adopta el estado, muestra "The queue changed on another device." y no envía más.
  - La escritura se hace en una transacción con bloqueo de la fila de `queues` (`lockForUpdate`), así que dos peticiones a la vez nunca dejan la misma versión.
- **Sincronización entre dispositivos:** sin websockets (dependen de la pregunta abierta 1). El cliente pide `GET /queue` al cargar la página, al volver a estar visible la pestaña (`visibilitychange`), cada 30 s mientras está visible y tras cada 409. Con la pestaña oculta no consulta.
- **Posición dentro de la canción: no se guarda.** Guardarla obligaría a escribir cada pocos segundos. Al abrir otro dispositivo o recargar, la canción actual se muestra en 0:00 y en pausa, y empieza desde el principio al pulsar "Play". Si el usuario quiere conservar la posición entre dispositivos, ya está "Play here" de T-23 para la parte de Spotify.
- **Validación de canciones** (422 `invalid_track`):
  - `source` debe estar en la lista de servicios habilitados, que en T-13 solo tiene `spotify`. T-10 añade `youtube_music`.
  - Para `spotify`, `uri` cumple `^spotify:track:[A-Za-z0-9]{22}$`.
  - `title` es obligatorio y `playable` debe ser true.
  - `origin` es null o un objeto JSON con una clave `type` de texto, de como mucho 1 KB. No se valida nada más: T-13 no lo interpreta.
  - El servidor no consulta Spotify para comprobar la canción: se fía de los datos del formato común, porque la app es de un solo usuario y así no gasta peticiones.
- **Límite de 500 canciones** en total (sonadas, actual y pendientes). Si se llena, "Add to queue" responde 422 `queue_full`. Al reproducir una lista más larga, el cliente envía la elegida y las siguientes hasta 500 y muestra el aviso. La continuación se detiene al llegar al límite.
- **Reproducir frente a añadir:**
  - **"Add to queue"** añade al final, también con el aleatorio activado. Nunca interrumpe lo que suena. Si la cola está vacía, la canción pasa a ser la actual, en pausa.
  - **Pulsar una canción dentro de una lista que se puede leer** (playlist propia o colaborativa, Liked Songs, álbum) sustituye la cola por las canciones reproducibles de esa lista **ya cargadas en pantalla**, en su orden, con la elegida como actual. "Play" en la cabecera de la lista equivale a pulsar la primera.
  - **Pulsar una canción en la búsqueda** sustituye toda la cola por esa sola canción. Se mantiene la regla de T-07 de reproducir solo esa canción. Es una propuesta sin confirmar (ver Pendiente).
  - **Continuación:**
    - Si la lista de origen tiene más páginas (Liked Songs o una playlist propia con `next_cursor`), la cola guarda la referencia a la lista y el cursor.
    - Cuando empieza a sonar una canción y quedan 3 pendientes o menos, el cliente pide `POST /queue/continue`. El servidor trae la página siguiente de 50 con el servicio de biblioteca de T-08 y la añade al final. Con el aleatorio activado, las nuevas se reparten al azar entre las pendientes.
    - **Si falla** (429, 502, Spotify sin conectar o con reconexión pendiente), no se muestra nada mientras queden pendientes. Se vuelve a intentar una vez cada vez que empieza a sonar una canción. Si la cola llega al final con la continuación pendiente, se para y muestra "Couldn't load more songs from Spotify.". Durante un 429 se respeta `retry_after` y no se pide antes. Los avisos de conexión de Spotify son los de T-06 y T-23.
    - Los álbumes no continúan, porque T-24 ya los carga enteros hasta 200 pistas.
- **Cómo la cola controla Spotify:**
  - La cola de la app es la fuente de verdad. Cada canción se empieza con `play({uris: [uri]})` de T-06, de una en una.
  - "Next" y "Previous" de la barra, en el modo cola, mueven la cola de la app y no llaman a `nextTrack()` ni a `previousTrack()`. "Next" se desactiva si no hay pendientes y la repetición está en "off". Con la cola vacía, "Next" y "Previous" están desactivados.
  - **Fin de canción** (heurística, a verificar en el spike). La canción actual X se da por terminada cuando, después de un estado con X sonando, llega alguno de estos estados:
    - X en pausa en la posición 0, con la última posición interpolada a menos de 2 s del final;
    - una canción que no es X (por ejemplo, la radio de Spotify);
    - X otra vez en menos de 2 s desde el principio, con la última posición interpolada a menos de 2 s del final y sin que la app haya llamado a `seek()`. Es el caso de la repetición de Spotify si no se pudo desactivar.
  - Tras el fin, la siguiente se pide en menos de 500 ms (el test automático). La comprobación manual pide que suene en menos de 2 s (propuesta, ver D2).
  - **Aleatorio y repetición de Spotify:** si en el modo cola un estado del SDK llega con `shuffle = true` o `repeat_mode ≠ 0`, el cliente llama una vez a `PUT /spotify/player/modes`. Hace falta porque con `repeat` en `track` o `context` una canción suelta se repetiría y nunca "terminaría".
    - Como mucho una petición por canción, falle o no.
    - Con 429 o 502 no se muestra aviso. Si la repetición de Spotify sigue activa, el fin se detecta por el tercer caso de la heurística.
    - Con 403 `premium_required` se muestra el aviso de Premium de T-23.
  - **Radio de Spotify:** si Spotify pone una canción que no está en la cola, cuenta como fin de la actual. Si la cola ya ha terminado y la repetición está en "off", una canción ajena que empiece en los 5 s siguientes se pausa con `pause()`. Pasados 5 s no se pausa, porque puede haberla puesto el usuario desde la app oficial.
  - **Otro dispositivo (T-23):** mientras se muestra "Playing on …", la web no avanza la cola. Tras "Play here", si suena la canción actual de la cola, la cola sigue. Si suena otra, se trata como ajena: al terminar, suena la siguiente pendiente de la cola.
- **"Previous":** con la posición por encima de 3000 ms, `seek(0)`. Con 3000 ms o menos, la canción anterior de la cola. En la primera canción, siempre `seek(0)`. La posición es la interpolada de T-06.
- **Saltar a una canción del panel:** las pendientes que quedan antes se dan por sonadas.
- **Quitar y mover canciones:**
  - Quitar la actual mientras suena equivale a "Next" y después quitarla. Si no hay pendientes, se llama a `pause()` y la cola queda vacía.
  - Solo se pueden mover las canciones pendientes, y siempre detrás de la actual (422 `item_not_pending` para la actual o las ya sonadas). Mover no corta lo que suena.
  - El panel tiene arrastrar y soltar y, para el teclado y el lector de pantalla, los botones "Move up" y "Move down" en cada fila.
- **Aleatorio (reordenación visible):**
  - Al activarlo, el servidor baraja las pendientes (Fisher–Yates) y guarda el orden nuevo en `position`. El panel muestra ese orden. Como es una permutación, ninguna canción se repite antes de que suenen todas.
  - Al desactivarlo, las pendientes vuelven a su `original_position`. Se pierden los cambios de orden manuales hechos con el aleatorio activado.
  - Con "Repeat: all" y el aleatorio activado, al terminar la última el cliente envía `PATCH /queue` con `restart = true`. El servidor baraja toda la cola, sin que la primera sea la que acaba de sonar (si hay más de una), y el cliente pide la primera de la respuesta. **Es la única vez que el avance espera al servidor**, porque el orden nuevo lo decide el servidor. Si falla, suena la primera del orden actual sin barajar y se guarda como en cualquier avance.
  - Sin el aleatorio, "Repeat: all" vuelve a la primera sin esperar al servidor.
  - La aleatoriedad usa `Random\Randomizer` inyectado por el contenedor, para que los tests fijen la semilla.
- **Repetición:** el botón pasa por "off" → "all" → "one" → "off". "one" solo actúa al terminar la canción de forma natural: vuelve a pedir la misma canción con `play({uris: [uri]})`, sin `seek()`. "Next" y el salto por fallo pasan a la siguiente.
- **Fallos de canción (D4):**
  - Cuentan como fallo de la canción el `playback_error` del SDK y que pasen 10 s desde la petición sin un estado del SDK con esa canción sonando.
  - Cada fallo muestra "This song can't be played." y salta a la siguiente, respetando la repetición. Con "Repeat: one", también salta.
  - Cada fallo emite en el cliente un evento de fallo de canción con `{item_id, origin, reason}`, donde `reason` es `playback_error` o `start_timeout`. T-13 no hace nada más con él. T-14 lo escucha y solo marca la canción cuando el motivo es `playback_error` y el origen es una playlist propia.
  - Tras 3 fallos seguidos, se detiene: la última que falló queda como actual y se muestra "Couldn't play 3 songs in a row. Playback stopped.". La cuenta vuelve a 0 cuando una canción empieza a sonar.
  - **No** cuentan como fallo ni saltan los errores que no son de la canción: 429, 403 `premium_required`, 404 `device_not_found`, 502 `spotify_unavailable`, `not_ready`, `authentication_error` y `account_error`. Se muestra el aviso de T-23 y la cola no avanza.
  - Las canciones con `playable: false` no se pueden añadir. Una que deja de estar disponible después de añadirla entra por `playback_error`.
- **Playlists que solo se siguen (modo contexto de Spotify):**
  - Como no se pueden listar (T-08), "Play" las reproduce como contexto de Spotify con `PUT /queue/context` y `play({contextUri})`. **La cola de la app no se borra**: queda guardada en espera.
  - El panel muestra el aviso y "Back to my queue". "Shuffle" y el botón de repetición se desactivan, y "Next" y "Previous" llaman a `nextTrack()` y `previousTrack()`, como en T-06. En este modo no se tocan el aleatorio ni la repetición de Spotify.
  - "Add to queue" sigue añadiendo a la cola guardada.
  - "Back to my queue" envía `PATCH /queue` con `mode = queue` y empieza la canción actual desde 0:00.
  - El fin del contexto no se detecta: lo que pase después lo decide Spotify (parar o su radio).
- **Errores de la API de la cola:**
  - En las ediciones del usuario no hay cambios optimistas. Si el servidor responde 5xx o se corta la red, se muestra "Couldn't update the queue. Try again." y la cola no cambia.
  - En los avances automáticos, la reproducción no espera al servidor (salvo `restart` con aleatorio): suena la siguiente y se envía `PATCH /queue`. Si falla por 5xx o por red, se reintenta una sola vez a los 5 s. Si vuelve a fallar, se muestra "Couldn't save the queue. Check your connection.".

**Alternativas descartadas** (para el `design.md` del cambio)

- **Usar la cola de Spotify** (`POST /v1/me/player/queue`): solo permite añadir. No hay forma de quitar ni reordenar, no admite YouTube Music, y Spotify no garantiza el orden de ejecución entre llamadas del Player API.
- **Enviar toda la lista como `uris` a Spotify y dejar que avance solo:** las transiciones serían más limpias, pero cada edición de la cola obligaría a reiniciar la reproducción, y el aleatorio, la repetición y los saltos por fallo dejarían de ser de la app. Se recupera como plan si el avance de una en una no funciona con la pantalla bloqueada (ver Pendiente).
- **Guardar la posición dentro de la canción:** demasiadas escrituras para poco beneficio.
- **"La última escritura gana" sin versión:** dos dispositivos podrían deshacerse cambios sin saberlo.

**Restricciones descubiertas**

- El SDK no tiene un evento de fin de canción. Los eventos documentados son `ready`, `not_ready`, `player_state_changed` y `autoplay_failed`, y los errores `initialization_error`, `authentication_error`, `account_error` y `playback_error`. `player_state_changed` "puede ejecutarse también a intervalos aleatorios". El estado trae `paused`, `position`, `repeat_mode` (0 sin repetición, 1 contexto, 2 canción), `shuffle`, `disallows` y `track_window` (`current_track`, `previous_tracks`, `next_tracks`). Fuente: https://developer.spotify.com/documentation/web-playback-sdk/reference
- `PUT /v1/me/player/repeat?state=track|context|off&device_id=` y `PUT /v1/me/player/shuffle?state=true|false&device_id=` requieren `user-modify-playback-state` y Premium, responden 204, y Spotify no garantiza su orden respecto a otras llamadas del Player API. Fuentes: https://developer.spotify.com/documentation/web-api/reference/set-repeat-mode-on-users-playback y https://developer.spotify.com/documentation/web-api/reference/toggle-shuffle-for-users-playback
- `POST /v1/me/player/queue` solo añade un `uri` a la cola de Spotify. No hay endpoints para quitar ni reordenar. Fuente: https://developer.spotify.com/documentation/web-api/reference/add-to-queue

**Casos límite**

- **Pestaña en segundo plano:** los navegadores retrasan los temporizadores de las pestañas ocultas. El avance depende de los eventos del SDK, no de temporizadores, pero el margen de 10 s de "no empieza" puede alargarse. Se acepta.
- **Móvil con la pantalla bloqueada:** si el navegador suspende el JavaScript, la cola no puede pedir la siguiente canción (pregunta abierta 3).
- **La misma canción dos veces en la cola:** se permite. Cada aparición tiene su `item_id`, y quitar o mover actúa sobre ese elemento.
- **Doble clic en "Add to queue":** el botón se desactiva mientras espera la respuesta, así que se envía una sola petición.
- **Dos dispositivos reproduciendo a la vez:** Spotify solo deja uno activo. El otro ve "Playing on …" (T-23) y no avanza la cola, así que no hay dos avances compitiendo.
- **Conflicto durante el avance:** si otro dispositivo quitó la canción que acaba de empezar, puede oírse un instante antes de que el reintento pida la correcta. Se acepta.
- **El usuario elige el dispositivo web desde la app oficial de Spotify y pone otra música:** la web la trata como canción ajena y, cuando termina, sigue con la cola. Es raro y se acepta.
- **Cola vacía con "Shuffle" o la repetición activados:** los modos se guardan y se aplican a lo que se añada.
- **Lista con canciones no reproducibles:** no entran en la cola, igual que en T-08.

**Pistas para los tests**

- **Pest (feature):** la API de la cola con la base de datos de test.
  - Las claves exactas (incluido `origin`), la versión y el 409.
  - La tabla de 422 y 404 con sus códigos, `queue_full` con una factory de 500 elementos, y el 401 sin sesión en todas las rutas.
  - El bloqueo en dos peticiones seguidas con la misma versión.
  - La continuación y sus fallos con `Http::fake` para `api.spotify.com/v1/me/tracks*`, y `Http::assertSent` para `limit` y `offset`.
  - `PUT /spotify/player/modes` con `Http::assertSent` para las dos peticiones a Spotify y la tabla de errores.
  - El aleatorio y `restart` con un `Random\Randomizer` con semilla fija ligado en el contenedor.
- **Repositorios:** tests de `QueueRepository` y `QueueItemRepository` para el reordenado, el barajado y la recuperación del orden original.
- **Tests de navegador de Pest 4 con el SDK simulado de T-06:**
  - El fin de canción se simula emitiendo los estados de la heurística (`@provisional`).
  - Los márgenes de 500 ms, 5 s, 10 s y 30 s, con temporizadores falsos o el reloj del navegador.
  - La pestaña oculta se simula como en T-16: `document.visibilityState` y el evento `visibilitychange`.
  - Las peticiones a `PUT /spotify/player/play`, `/queue*` y `/spotify/player/modes` se comprueban del lado servidor.
  - El evento de fallo de canción, con un espía en la página.
  - Dos dispositivos: dos páginas del navegador con la misma sesión.
  - Sin red: se bloquean las rutas `/queue*` desde el test.
- **`@manual`:** con Spotify Premium real, se copia como lista de comprobación en el `tasks.md` del cambio y se anota cómo emite el SDK el fin de una canción.

**Fuera de alcance**

- **Cola mixta entre servicios** y el criterio de 2 s al pasar de Spotify a YouTube Music (D2, D5): van en T-10.
- Reproducir YouTube Music (T-10). El plan B móvil con Spotify Connect (T-15).
- Marcar canciones como no disponibles en una playlist propia: es de T-14. T-13 solo emite el evento de fallo con el `origin`.
- "Play next" (insertar justo después de la actual), "Clear queue", guardar la cola como playlist (T-14) y el historial de escucha (T-19).
- Recomendaciones o radio propias al terminar la cola.
- La cola de Spotify del dispositivo (`POST /v1/me/player/queue`).

**Pendiente**

- **Verificar con el SDK real** la heurística de fin de canción y qué emite Spotify cuando termina una canción suelta (pausa en 0, canción en `previous_tracks` o radio). Hay que hacerlo en un spike antes del cambio 2. Si no es fiable, se usa la alternativa del temporizador por posición interpolada, y se revisan los escenarios `@provisional`.
- **Pregunta abierta 3:** si la cola avanza con la pantalla del móvil bloqueada. Si no, se vuelve a la alternativa de enviar varias `uris` a Spotify.
- **Pregunta abierta 1:** si el hosting permite websockets para sincronizar al momento. Mientras tanto, la sincronización es por consulta cada 30 s y al volver a la pestaña.
- **Pulsar una canción en la búsqueda sustituye toda la cola por esa sola canción.** El usuario no lo ha confirmado. La alternativa es insertarla como actual y conservar las pendientes.
- **El objetivo de 2 s entre dos canciones de Spotify** es una propuesta de T-13. D2 solo lo fija para el cambio de servicio.
- El umbral de 3 pendientes para la continuación, el margen de 10 s para "no empieza", los 5 s de la radio tras el fin y la consulta cada 30 s son propuestas. El límite de 500 lo fijó el orquestador al coordinar con T-14.
- Si "Play" en una playlist que solo se sigue debe conservar la cola en espera (propuesta) o borrarla.
- Si al desactivar el aleatorio deben conservarse los cambios de orden manuales.
- Si conviene "Clear queue" y "Play next" en la v1.

**Seguimiento (cambios sugeridos en otros ficheros, no aplicados)**

- **T-10:** recibir el escenario "Cola mixta entre servicios" con el criterio de D2 (la siguiente empieza menos de 2 s después). D4 resuelve su pendiente de saltar canciones. Añadir `youtube_music` a los servicios admitidos por la cola. Depende de T-13, y no al revés. Lo aplica el orquestador.
- **T-23:** D4 resuelve su pendiente sobre saltar canciones. Su regla de que `playback_error` no salta sigue valiendo fuera de la cola, y en la cola salta T-13.
- **T-06, T-08 y T-24:** el cambio 2 de T-13 lleva deltas MODIFIED sobre la reproducción. "Next" y "Previous" mueven la cola. Las playlists propias, Liked Songs y los álbumes cargan la cola y suenan con `uris` de una en una, en lugar de contexto u `offset`. T-08 debe dejar su servicio de biblioteca y su cursor opaco reutilizables para la continuación.
- **T-07 y T-24:** colocar "Add to queue" en las filas de canción y de pista.
- **T-21:** sitio en la barra para "Shuffle", el botón de repetición y "Queue".
- **T-14:**
  - Reproducir una playlist propia usa `PUT /queue` con el `origin` de cada canción (`{type: "own_playlist", playlist_id, item_id}`).
  - Escuchar el evento de fallo de canción de T-13 (`{item_id, origin, reason}`) y llamar a su endpoint para marcar la canción solo con `reason = playback_error` y origen de playlist propia.
  - Su límite de playlist baja a 500, que es el de la cola. Lo aplica el orquestador.
- **T-19:** cada vuelta de "Repeat: one" llega como la misma canción otra vez cerca de la posición 0, sin un `seek()` de la app, porque T-13 vuelve a enviar `play({uris: [uri]})`. La regla del salto hacia atrás de T-19 lo cuenta como reproducción nueva. "Previous" después de 3 s sí usa `seek(0)` de la app (no de la barra de progreso), y T-19 también lo cuenta. Conviene anotarlo en su relación con T-13.
- **T-16:** el visualizador trabaja con el estado del SDK, no con la cola.
  - Entre dos canciones de la cola puede llegar un estado en pausa en la posición 0 durante menos de 2 s. El visualizador pasará un momento a `paused`. Se acepta.
  - "Repeat: one" repite el mismo id, así que no reinicia la escena.
  - Tras recargar, la barra muestra la canción actual de la cola sin estado del SDK. T-16 debe decidir si eso es `idle` ("Nothing playing", propuesta coherente con su regla actual) o `paused`.
- **Discovery:** marcar resuelta la pregunta abierta 5 con D2 y anotar D3, D4 y D5.

**Estimación: 8 puntos** (cambio 1: 3 + cambio 2: 3,5 + cambio 3: 1,5).

*Cambio 1 — Cola en el servidor, panel y sincronización (3 puntos):*
- **Modelo, repositorios y API con versión, 409, códigos de error, límite y `origin` (1,5 puntos):** dos tablas, bloqueo de fila y las seis rutas de la cola del cambio 1 (de las nueve de la tarea) con su tabla de errores.
- **Panel y "Add to queue" en las filas de T-07, T-08 y T-24, con reordenado por ratón y teclado (1 punto).**
- **Sincronización por visibilidad y cada 30 s, y sus tests (0,5 puntos).**

*Cambio 2 — Reproducción guiada por la cola (3,5 puntos):*
- **Motor de la cola (1,5 puntos):** controlar Spotify de una en una, la heurística de fin de canción, la radio de Spotify, "Previous", el 409 durante el avance y `PUT /spotify/player/modes`. Es la parte más incierta.
- **Listas que sustituyen la cola y continuación con sus fallos (0,75 puntos).**
- **D4 con el evento para T-14, el modo contexto y el otro dispositivo (0,75 puntos).**
- **Tests de navegador con el SDK simulado y comprobación manual (0,5 puntos).**

*Cambio 3 — Aleatorio y repetición (1,5 puntos):*
- **Barajado en el servidor, recuperar el orden y `restart` (0,75 puntos).**
- **Botón de tres modos y "Repeat: one" (0,25 puntos).**
- **Tests con semilla fija y comprobación manual (0,5 puntos).**

**Seguimiento de T-04 (despliegue, 2026-11-09)**

Pregunta abierta 1 resuelta: hosting compartido Premium de Hostinger, app en `music.illodosadev.io` (subdominio de `illodosadev.io`), despliegue con GitHub Actions + SSH lanzado a mano (D14–D16).

- **Websockets:** el plan compartido no permite procesos permanentes, así que no hay websockets. El sondeo cada 30 s es la solución final, no provisional; la parte de la pregunta abierta 1 de su Pendiente queda resuelta.

**Seguimiento de aero-player-controls (T-21, 2026-11-11)**

- Los botones van en la ranura `extra` de `AeroPlayerBar`, como `AeroButton` con `aria-pressed` (modo o panel) o `aria-expanded`. Heredan el foco visible y el área táctil de 44x44 px. `/styleguide` tiene un ejemplo ("Extra").
- Los iconos nuevos van en `resources/aero/icons/` (24x24, `fill="currentColor"`), con su fila en `resources/aero/CREDITS.md` y su nombre en el tipo `AeroIconName` de `AeroIcon.vue`.

## Definition of Done

- [ ] Criterios automatizables cubiertos por tests; los @manual en el checklist del cambio
- [ ] Spike hecho antes del cambio 2: cómo emite el SDK real el fin de una canción suelta, y escenarios `@provisional` confirmados o revisados
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
