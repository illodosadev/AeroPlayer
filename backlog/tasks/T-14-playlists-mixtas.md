---
id: T-14
title: Playlists propias mixtas
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 5
language: es
---

# T-14 — Playlists propias mixtas

## Goal

Crear dentro de la app playlists propias (crear, renombrar, borrar, añadir, quitar y reordenar canciones) y reproducirlas con la cola de la app. Así el usuario tiene listas que no dependen de ningún servicio y que, cuando llegue YouTube Music, podrán mezclar canciones de los dos, algo que ninguno de los dos permite por separado.

La tarea se entrega **primero con Spotify, pero sin atarse a él** (D5). YouTube Music se suma después (T-10, T-11 y T-12) sin rehacer el modelo ni la interfaz.

## Context

Necesidad v1 del discovery: "crear playlists propias dentro de la app que mezclen canciones de Spotify y de YouTube Music". La pregunta abierta 4 quedó resuelta el 2026-11-09 (D6 y D7, ver Notas).

- **Depende de:**
  - **T-02:** solo el usuario con sesión iniciada tiene playlists.
  - **T-03:** los componentes Aero (`AeroButton`, `AeroInput`, `AeroGlassPanel`) y el runner de tests de navegador (Pest 4). Los diálogos, menús y listas los construye cada tarea siguiendo la skill de T-03.
  - **T-05:** el estado de la conexión de Spotify y su botón "Connect Spotify". **Dependencia real:** hoy T-05 solo expone el booleano `spotify.connected` en las props compartidas, y T-14 necesita distinguir "sin conectar" de `reconnect_required` para elegir el aviso. Hace falta que T-05 exponga `spotify.status` (`not_connected`, `connected` o `reconnect_required`) o algo equivalente (ver Seguimiento).
  - **T-06:** el SDK simulado para los tests de navegador (emite `playback_error`, `not_ready` y los estados de reproducción).
  - **T-07, T-08 y T-24:** las filas de canción donde va "Add to playlist" y el formato común de canción (`source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image`, `playable`). T-08 aporta también la página `/library`, donde va la sección de playlists propias, y su estilo de rutas (`/library/playlists/{source}/{id}`).
  - **T-13:** la cola de la app. Se guarda en el servidor y es la misma en todos los dispositivos (D3). Una playlist se reproduce sustituyendo la cola con `PUT /queue {tracks, current_index}`. Aporta también el aviso "This song can't be played." con el salto automático (D4) y su lista de errores que no son de la canción.
  - **T-21:** la carátula genérica `AeroCover` (`data-aero="cover-fallback"`).
  - **T-23** (a través de T-13): el evento `playback_error` del SDK, que es la señal de que una canción concreta no se puede reproducir.
  - **T-25:** la navegación Aero y el aviso `AeroFlash`. T-25 recomienda hacerse antes de esta tarea.
- **Esta tarea ya no depende de T-11 ni de T-12** (D5). Los escenarios con YouTube Music pasan a T-10, T-11 y T-12 como seguimiento.
- **Playlists de Spotify que el usuario solo sigue:** desde 2026 no se pueden listar sus canciones (T-08), así que no tienen filas y no se pueden usar como origen de canciones.
- **División propuesta** (ver la Estimación): T-14a (playlists, añadir, quitar y reordenar, unos 3 puntos) y T-14b (disponibilidad, marcar como no disponible y reproducir, unos 2 puntos). No se crean ficheros nuevos: se decide al proponer el cambio.

## Acceptance criteria

```gherkin
# --- Acceso -------------------------------------------------------------------

Scenario Outline: Un visitante sin sesión no ve ni cambia playlists
  Given un visitante sin sesión iniciada
  When pide <petición>
  Then <resultado>
  And no se crea, cambia ni borra ninguna playlist ni canción de playlist

  Examples:
    | petición                                                                         | resultado                                                       |
    | la página "/library/playlists/own/1"                                             | se le redirige a "/login"                                       |
    | POST "/library/playlists/own" con el nombre "Verano 2007"                        | se le redirige a "/login"                                       |
    | PATCH "/library/playlists/own/1" con el nombre "Verano 2008"                     | se le redirige a "/login"                                       |
    | DELETE "/library/playlists/own/1"                                                | se le redirige a "/login"                                       |
    | GET "/library/playlists/own" con "Accept: application/json"                      | la respuesta tiene estado 401 con "error" = "unauthenticated"   |
    | POST "/library/playlists/own/1/items" con "Accept: application/json"             | la respuesta tiene estado 401 con "error" = "unauthenticated"   |
    | DELETE "/library/playlists/own/1/items/1" con "Accept: application/json"          | la respuesta tiene estado 401 con "error" = "unauthenticated"   |
    | PUT "/library/playlists/own/1/order" con "Accept: application/json"              | la respuesta tiene estado 401 con "error" = "unauthenticated"   |
    | POST "/library/playlists/own/1/items/1/unavailable" con "Accept: application/json" | la respuesta tiene estado 401 con "error" = "unauthenticated" |

# --- Crear y ver ------------------------------------------------------------------

Scenario: Crear una playlist
  Given el usuario con sesión iniciada en "/library"
  When pulsa "New playlist", escribe "Verano 2007" en el campo "Name" y pulsa "Create"
  Then llega a la página "/library/playlists/own/{id}" de la playlist nueva
  And la página muestra "Verano 2007", el mensaje "This playlist is empty." y el botón "Play" desactivado
  When vuelve a "/library"
  Then la sección "My playlists" muestra "Verano 2007" con el texto "0 songs" y la carátula genérica de T-21

Scenario Outline: Nombre no válido al crear
  Given el usuario con sesión iniciada y el diálogo "New playlist" abierto
  When escribe <nombre> en el campo "Name" y pulsa "Create"
  Then el diálogo sigue abierto y muestra <error> bajo el campo "Name"
  And no se crea ninguna playlist

  Examples:
    | nombre                                  | error                                         |
    | "" (vacío)                              | "Name is required."                           |
    | "    " (solo espacios)                  | "Name is required."                           |
    | un texto de 101 caracteres              | "Name can't be longer than 100 characters."   |

Scenario: Nombre en el límite, con acentos y emojis
  Given el usuario con sesión iniciada
  When crea una playlist con un nombre de exactamente 100 caracteres que incluye "Canción" y "🌊"
  Then la playlist se crea con ese nombre exacto

Scenario: Se quitan los espacios del principio y del final
  Given el usuario con sesión iniciada
  When crea una playlist con el nombre "   Verano 2007   "
  Then la playlist se guarda con el nombre "Verano 2007"

Scenario: Nombre repetido
  Given el usuario con la playlist propia "Verano 2007"
  When crea otra playlist con el nombre "verano 2007"
  Then el diálogo muestra "You already have a playlist with that name."
  And sigue teniendo una sola playlist

Scenario: Nombres que solo se diferencian en un acento
  Given el usuario con la playlist propia "Canción"
  When crea otra playlist con el nombre "Cancion"
  Then se crea la playlist "Cancion"
  And "My playlists" muestra "Canción" y "Cancion"

Scenario: Dos peticiones de crear con el mismo nombre a la vez
  Given el usuario con sesión iniciada y sin playlists
  When llegan a la vez dos peticiones POST "/library/playlists/own" con el nombre "Gym"
  Then se crea una sola playlist "Gym"
  And la otra petición vuelve con el error "You already have a playlist with that name." y no con una página de error

Scenario: Mis playlists en la biblioteca
  Given el usuario con las playlists propias "Zen", "Ánimo" y "beats", y "beats" con 3 canciones
  When abre "/library"
  Then la sección "My playlists" aparece antes de la lista "Playlists" de los servicios
  And muestra "Ánimo", "beats" y "Zen", en ese orden
  And "beats" muestra "3 songs" y la carátula de su primera canción

Scenario Outline: Mis playlists se ven sea cual sea el estado de Spotify
  Given el usuario con la playlist propia "Verano 2007" y <estado>
  When abre "/library"
  Then la respuesta tiene estado 200, sin página de error
  And la sección "My playlists" muestra "Verano 2007"
  And ve también <aviso> de T-08 en la parte de Spotify

  Examples:
    | estado                                                          | aviso                                                                      |
    | Spotify sin conectar                                            | "Connect Spotify to see your library."                                    |
    | la conexión de T-05 en estado "reconnect_required"              | "Your Spotify access expired or was revoked. Please reconnect Spotify."   |
    | la API de Spotify (falsa) responde 503 a "/me/playlists"        | "Couldn't load your Spotify library."                                     |

Scenario: Sin playlists propias
  Given el usuario con sesión iniciada y sin playlists propias
  When abre "/library"
  Then la sección "My playlists" muestra "No playlists of your own yet." y el botón "New playlist"

Scenario Outline: Abrir una playlist que no existe
  Given el usuario con sesión iniciada
  When abre "<ruta>"
  Then la respuesta tiene estado 404 con la página de error de la app

  Examples:
    | ruta                                     |
    | /library/playlists/own/999 (no existe)   |
    | /library/playlists/own/abc               |

# --- Añadir canciones -------------------------------------------------------------

Scenario Outline: Añadir una canción de Spotify desde cualquier lista de canciones
  Given el usuario con la playlist propia "Verano 2007", vacía, y Spotify conectado
  And <lista> con la canción "One More Time" de "Daft Punk" (id "0DiWol3AO6WpXZgp0goxAV", álbum "Discovery", 320000 ms)
  When el usuario pulsa "Add to playlist" en la fila de "One More Time" y elige "Verano 2007"
  Then el endpoint responde 201 y el usuario ve el aviso 'Added to "Verano 2007".'
  And "Verano 2007" contiene una canción con source "spotify", id "0DiWol3AO6WpXZgp0goxAV", uri "spotify:track:0DiWol3AO6WpXZgp0goxAV" y álbum "Discovery"

  Examples:
    | lista                                                        |
    | los resultados de búsqueda de T-07                           |
    | la página del álbum "Discovery" de T-24                      |
    | "Liked Songs" de T-08                                        |
    | una playlist de Spotify propia del usuario, abierta en T-08  |

Scenario: Se ignoran las claves extra de la canción
  Given el usuario con la playlist propia "Verano 2007", vacía
  When se envía POST "/library/playlists/own/{id}/items" con una canción válida en el formato común y además "position" 4, "local" false y "added_at"
  Then la respuesta tiene estado 201
  And la canción guardada no tiene "position" 4 ni "added_at" del envío: su posición es 0 y su "added_at" es el momento de añadirla

Scenario: La playlist se muestra sin llamar a Spotify
  Given la playlist propia "Verano 2007" con "One More Time" de "Daft Punk", álbum "Discovery", 320000 ms, añadida desde Spotify
  And la API de Spotify (falsa) responde 503 a cualquier petición
  When el usuario abre "Verano 2007"
  Then la fila muestra "One More Time", "Daft Punk", "Discovery", "5:20", la carátula guardada y la etiqueta "Spotify"
  And no se envía ninguna petición a la API de Spotify
  And la página no muestra ningún error

Scenario: Las canciones se añaden al final
  Given la playlist propia "Verano 2007" con las canciones A y B
  When el usuario añade la canción C a "Verano 2007"
  Then la playlist queda A, B, C

Scenario: Canción que ya está en la playlist
  Given la playlist propia "Verano 2007" con "One More Time" (Spotify, id "0DiWol3AO6WpXZgp0goxAV")
  When el usuario vuelve a añadir "One More Time" desde los resultados de búsqueda a "Verano 2007"
  Then el endpoint responde 409 con "error" = "already_in_playlist"
  And ve el aviso 'Already in "Verano 2007".'
  And "Verano 2007" sigue teniendo una sola copia de "One More Time"

Scenario: Dos peticiones de añadir la misma canción a la vez
  Given la playlist propia "Verano 2007", vacía
  When llegan a la vez dos peticiones POST "/library/playlists/own/{id}/items" con "One More Time"
  Then una responde 201 y la otra 409 con "error" = "already_in_playlist"
  And "Verano 2007" tiene una sola copia de "One More Time"

Scenario: Los ids de Spotify distinguen mayúsculas y minúsculas
  Given la playlist propia "Verano 2007" con la canción de Spotify de id "4uLU6hMCjMI75M1A2tKUQC"
  When el usuario añade la canción de Spotify de id "4ULU6HMCJMI75M1A2TKUQC"
  Then el endpoint responde 201
  And "Verano 2007" tiene las dos canciones

Scenario: La misma canción en dos playlists distintas
  Given las playlists propias "Verano 2007" y "Gym", con "One More Time" ya en "Verano 2007"
  When el usuario añade "One More Time" a "Gym"
  Then "Gym" contiene "One More Time"
  And "Verano 2007" no cambia

Scenario: Playlist llena
  Given la playlist propia "Verano 2007" con 500 canciones
  When el usuario añade otra canción a "Verano 2007"
  Then el endpoint responde 422 con "error" = "playlist_full"
  And ve el aviso "This playlist is full (500 songs)."
  And "Verano 2007" sigue teniendo 500 canciones

Scenario: El menú lista mis playlists al abrirlo
  Given el usuario con las playlists propias "Zen" y "Ánimo", y los resultados de búsqueda en pantalla
  And en otra pestaña crea la playlist "Gym"
  When pulsa "Add to playlist" en una fila de canción
  Then el menú muestra "Ánimo", "Gym" y "Zen", en ese orden

Scenario: Añadir sin tener playlists propias
  Given el usuario sin playlists propias y los resultados de búsqueda en pantalla
  When pulsa "Add to playlist" en una fila de canción
  Then el menú muestra "No playlists yet." y un enlace "Go to Library" a "/library"

Scenario: Playlist borrada en otra pestaña mientras se añade
  Given el menú "Add to playlist" abierto con la playlist "Verano 2007"
  And "Verano 2007" se borra desde otra pestaña
  When el usuario elige "Verano 2007"
  Then el endpoint responde 404 con "error" = "playlist_not_found"
  And ve el aviso "This playlist no longer exists."
  And no se crea ninguna canción de playlist

Scenario Outline: Las filas que no se pueden reproducir no ofrecen "Add to playlist"
  Given <fila>
  When se muestra la lista
  Then esa fila no muestra "Add to playlist"

  Examples:
    | fila                                                                          |
    | una fila de resultados de búsqueda con "playable" false ("Not available")      |
    | una fila de archivo local en una playlist de Spotify de T-08 ("local" true)    |

Scenario: Una playlist de Spotify que solo se sigue no sirve de origen
  Given la playlist de Spotify "Indie Picks" de "Alice", que el usuario solo sigue
  When el usuario la abre en T-08
  Then la página no muestra ningún "Add to playlist"

Scenario Outline: El endpoint rechaza canciones no válidas
  Given el usuario con la playlist propia "Verano 2007", vacía
  When se envía POST "/library/playlists/own/{id}/items" con "Accept: application/json" y <canción>
  Then la respuesta tiene estado 422
  And "Verano 2007" sigue vacía
  And no se envía ninguna petición a la API de Spotify

  Examples:
    | canción                                                                    |
    | source "youtube_music" (todavía no admitido)                               |
    | source "spotify" e id "notAnId" (no son 22 caracteres base62)              |
    | id "0DiWol3AO6WpXZgp0goxAV" y uri "spotify:track:4uLU6hMCjMI75M1A2tKUQC"    |
    | "playable" false                                                           |
    | sin "title"                                                                |
    | "image" con la URL "javascript:alert(1)"                                   |

# --- Quitar y reordenar -----------------------------------------------------------

Scenario: Quitar y reordenar canciones
  Given la playlist propia "Verano 2007" con las canciones A, B y C
  When el usuario arrastra C al principio y pulsa "Remove from playlist" en B
  Then la playlist queda C, A
  And el orden se mantiene al recargar la página

Scenario: Reordenar con los botones
  Given la playlist propia "Verano 2007" con las canciones A, B y C
  When el usuario enfoca la fila de C y pulsa su botón "Move up" dos veces
  Then la playlist queda C, A, B
  And el botón "Move up" de C está desactivado y el botón "Move down" de B también
  And el orden se mantiene al recargar la página

Scenario: La playlist cambió en otra pestaña
  Given la playlist propia "Verano 2007" con A, B y C abierta en dos pestañas
  And en la segunda pestaña el usuario quita B
  When en la primera pestaña arrastra C al principio
  Then el endpoint de orden responde 409 con "error" = "playlist_changed"
  And la primera pestaña recarga la playlist, que queda A, C
  And muestra el aviso "This playlist changed in another tab. Showing the latest version."

Scenario: Quitar una canción que ya se quitó en otra pestaña
  Given la playlist propia "Verano 2007" con A, B y C abierta en dos pestañas
  And en la segunda pestaña el usuario quita B
  When en la primera pestaña pulsa "Remove from playlist" en B
  Then el endpoint responde 404 con "error" = "playlist_item_not_found"
  And la primera pestaña recarga la playlist, que queda A, C
  And no se muestra una página de error

Scenario Outline: Falla el servidor o la red al cambiar una playlist
  Given la playlist propia "Verano 2007" con A, B y C abierta
  And <petición> responde 503 o la red está cortada
  When el usuario <acción>
  Then ve el aviso "Couldn't update the playlist. Try again."
  And <resultado>
  And no se muestra una página de error

  Examples:
    | petición                                       | acción                                                        | resultado                                                           |
    | POST "/library/playlists/own/{id}/items"       | añade "One More Time" a "Verano 2007" desde la búsqueda       | "Verano 2007" sigue con A, B y C                                    |
    | DELETE "/library/playlists/own/{id}/items/{B}" | pulsa "Remove from playlist" en B                             | B sigue en la lista, en su sitio                                    |
    | PUT "/library/playlists/own/{id}/order"        | arrastra C al principio                                       | la lista vuelve a mostrar A, B, C                                   |
    | PUT "/library/playlists/own/{id}/order"        | pulsa "Move up" en C                                          | la lista vuelve a mostrar A, B, C                                   |
    | PATCH "/library/playlists/own/{id}"            | renombra la playlist a "Verano 2008"                          | el diálogo sigue abierto y la playlist se llama "Verano 2007"       |

# --- Renombrar y borrar -----------------------------------------------------------

Scenario: Renombrar una playlist
  Given la playlist propia "Verano 2007"
  When el usuario pulsa "Rename", escribe "Verano 2008" y pulsa "Save"
  Then la página muestra "Verano 2008"
  And "/library" y el menú "Add to playlist" muestran "Verano 2008" y no "Verano 2007"

Scenario: Cambiar solo mayúsculas y minúsculas del nombre
  Given la playlist propia "Verano 2007"
  When el usuario la renombra a "VERANO 2007"
  Then la playlist se llama "VERANO 2007"

Scenario Outline: Nombre no válido al renombrar
  Given las playlists propias "Verano 2007" y "Gym"
  When el usuario renombra "Verano 2007" a <nombre>
  Then ve <error>
  And la playlist sigue llamándose "Verano 2007"

  Examples:
    | nombre                        | error                                          |
    | "   " (solo espacios)         | "Name is required."                            |
    | un texto de 101 caracteres    | "Name can't be longer than 100 characters."    |
    | "gym"                         | "You already have a playlist with that name."  |

Scenario: Borrar una playlist
  Given la playlist propia "Verano 2008" con 3 canciones
  When el usuario pulsa "Delete" en su página
  Then ve el diálogo 'Delete "Verano 2008"? This can't be undone.' con los botones "Delete" y "Cancel"
  When pulsa "Delete" en el diálogo
  Then llega a "/library" y ve el aviso "Playlist deleted."
  And "Verano 2008" ya no aparece en "My playlists" ni en el menú "Add to playlist"
  And sus 3 canciones de playlist ya no existen en la base de datos

Scenario: Cancelar el borrado
  Given la playlist propia "Verano 2008" y el diálogo de borrado abierto
  When el usuario pulsa "Cancel"
  Then el diálogo se cierra y la playlist sigue existiendo

Scenario Outline: Cambiar una playlist que ya se borró en otra pestaña
  Given la página de "Verano 2007" abierta y la playlist borrada desde otra pestaña
  When el usuario <acción>
  Then llega a "/library" y ve el aviso "This playlist no longer exists."
  And no se muestra una página de error

  Examples:
    | acción                              |
    | la renombra a "Verano 2008"         |
    | la borra desde el diálogo           |

# --- Reproducir (con la cola de T-13) ----------------------------------------------

Scenario: Reproducir una playlist
  Given la playlist propia "Verano 2007" con las canciones de Spotify A, B y C, y Spotify conectado
  When el usuario pulsa "Play"
  Then se envía "PUT /queue" con tracks A, B y C, en ese orden, y current_index 0
  And cada canción enviada lleva origin {type: "own_playlist", playlist_id: <id de Verano 2007>, item_id: <su item_id>}
  And "GET /queue" devuelve A, B y C con A como actual y con el mismo origin en cada una

Scenario: Reproducir desde una canción concreta
  Given la playlist propia "Verano 2007" con A, B y C, y Spotify conectado
  When el usuario pulsa la fila de B
  Then se envía "PUT /queue" con tracks A, B y C, en ese orden, y current_index 1

Scenario: Las canciones no disponibles se saltan
  Given la playlist propia "Verano 2007" con A, B y C, B marcada como no disponible, y Spotify conectado
  When el usuario abre la playlist
  Then B aparece atenuada con el texto "Unavailable" y conserva su posición
  When pulsa "Play"
  Then se envía "PUT /queue" con tracks A y C, en ese orden, y current_index 0
  When pulsa la fila de C
  Then se envía "PUT /queue" con tracks A y C y current_index 1
  When pulsa la fila de B
  Then no se envía "PUT /queue"

Scenario: Una canción que falla al sonar queda marcada como no disponible
  Given la cola de T-13 cargada desde "Verano 2007" con A, B y C, cada una con su origin, y B pedida
  When el SDK simulado de T-06 emite "playback_error" para B
  Then la barra muestra "This song can't be played." y T-13 pide C (D4)
  And se envía "POST /library/playlists/own/{id}/items/{item de B}/unavailable", que responde 204
  And al recargar "Verano 2007", B aparece atenuada con el texto "Unavailable"

Scenario Outline: Los fallos que no son de la canción no la marcan
  Given la cola de T-13 cargada desde "Verano 2007" con A, B y C, cada una con su origin, y B pedida
  When <fallo>
  Then no se envía ninguna petición a "/library/playlists/own/{id}/items/{item de B}/unavailable"
  And al recargar "Verano 2007", B no aparece como "Unavailable"

  Examples:
    | fallo                                                                                   |
    | pasan 10 segundos sin que B empiece a sonar (T-13 la salta, pero no se marca)           |
    | "PUT /spotify/player/play" responde 429 rate_limited                                    |
    | "PUT /spotify/player/play" responde 403 premium_required                                |
    | "PUT /spotify/player/play" responde 404 device_not_found                                |
    | "PUT /spotify/player/play" responde 502 spotify_unavailable (incluido el timeout HTTP de T-05) |
    | el SDK simulado emite "not_ready"                                                       |
    | el SDK simulado emite "authentication_error"                                            |

Scenario: Una canción de la cola que no viene de una playlist propia no marca nada
  Given la playlist propia "Verano 2007" con B, y una cola de T-13 con B añadida desde la búsqueda, sin origin
  When el SDK simulado emite "playback_error" para B
  Then no se envía ninguna petición a ".../unavailable"
  And B no aparece como "Unavailable" en "Verano 2007"

Scenario Outline: El endpoint de marcar como no disponible
  Given el usuario con la playlist propia "Verano 2007" con A y B
  And <situación>
  When se envía "POST /library/playlists/own/{id}/items/{item de B}/unavailable" con "Accept: application/json"
  Then <resultado>

  Examples:
    | situación                                       | resultado                                                                                           |
    | B disponible                                    | la respuesta tiene estado 204 y B queda marcada                                                     |
    | B ya marcada hace un día                        | la respuesta tiene estado 204 y la fecha de la marca no cambia                                      |
    | B quitada de la playlist en otra pestaña        | la respuesta tiene estado 404 con "error" = "playlist_item_not_found" y la barra no muestra otro aviso |
    | "Verano 2007" borrada                           | la respuesta tiene estado 404 con "error" = "playlist_not_found" y la barra no muestra otro aviso   |

Scenario: Volver a añadir una canción marcada le quita la marca
  Given la playlist propia "Verano 2007" con A, B y C, y B marcada como no disponible
  When el usuario añade B a "Verano 2007" desde los resultados de búsqueda, donde B tiene "playable" true
  Then el endpoint responde 200 (y no 409) y el usuario ve el aviso 'Added to "Verano 2007".'
  And la playlist sigue siendo A, B, C, con B disponible y una sola copia de B

Scenario: Quitar a mano una canción no disponible
  Given la playlist propia "Verano 2007" con A, B y C, y B marcada como no disponible
  When el usuario pulsa "Remove from playlist" en B
  Then la playlist queda A, C

Scenario: Spotify desconectado
  Given la playlist propia "Verano 2007" con las canciones de Spotify A y B, y Spotify sin conectar
  When el usuario abre la playlist
  Then la respuesta tiene estado 200 y no se envía ninguna petición a la API de Spotify
  And A y B aparecen atenuadas con el texto "Unavailable"
  And ve el aviso "Connect Spotify to play its songs." y el botón "Connect Spotify" de T-05
  And el botón "Play" está desactivado
  When el usuario conecta Spotify y vuelve a abrir la playlist
  Then A y B ya no aparecen como "Unavailable" y "Play" está activado

Scenario: Acceso a Spotify revocado
  Given la playlist propia "Verano 2007" con canciones de Spotify y la conexión de T-05 en estado "reconnect_required"
  When el usuario abre la playlist
  Then sus canciones aparecen atenuadas con el texto "Unavailable"
  And ve el aviso de T-05 "Your Spotify access expired or was revoked. Please reconnect Spotify." y el botón "Connect Spotify" de T-05
  And el botón "Play" está desactivado

Scenario: Ninguna canción disponible
  Given la playlist propia "Verano 2007" con A y B, las dos marcadas como no disponibles, y Spotify conectado
  When el usuario abre la playlist
  Then el botón "Play" está desactivado

Scenario: Borrar la playlist que suena no cambia la cola
  Given la cola de T-13 cargada desde "Verano 2007" con A, B y C, y A sonando
  When el usuario borra "Verano 2007"
  Then "GET /queue" sigue devolviendo A, B y C con A como actual
  And A sigue sonando

Scenario: La carátula guardada ya no carga
  Given la playlist propia "Verano 2007" con una canción cuya URL de carátula guardada responde 404
  When el usuario abre la playlist
  Then esa fila muestra la carátula genérica de T-21 (data-aero="cover-fallback")
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D6 — Dónde se guardan** (resuelve la primera parte de la pregunta abierta 4): las playlists propias viven **solo en la base de datos de la app**. No se sincroniza nada con Spotify ni con YouTube Music, y no se piden scopes de escritura (T-05 no los pide).
- **D7 — Canciones que dejan de estar disponibles** (resuelve el resto de la pregunta abierta 4): la canción **se queda en la playlist**, atenuada y con el texto "Unavailable". Se salta al reproducir la playlist y se puede quitar a mano. Nada desaparece sin avisar.
- **D5 — Spotify primero, sin atarse al servicio:** T-14 se entrega y se prueba solo con canciones de Spotify. Los escenarios con YouTube Music ("Añadir canciones de los dos servicios", "Reproducir una playlist mixta" y "Servicio desconectado" con YouTube Music) pasan a T-10, T-11 y T-12 (ver Seguimiento). Las dependencias son T-07, T-08, T-24 y T-13; T-11 y T-12 ya no lo son.
- **D3 (de T-13):** la cola se guarda en el servidor y es la misma en todos los dispositivos. Por eso el `origin` de cada canción viaja con la cola y se devuelve con ella: se puede marcar una canción aunque la cola se haya cargado en otro dispositivo o antes de recargar.
- **D4 (de T-13):** cuando una canción de la cola falla, la barra muestra "This song can't be played." y se salta a la siguiente. Tras 3 fallos seguidos, se para. Reproducir una playlist propia usa esa cola.
- **D12 (de T-25):** `/library` es la página de inicio tras el login. Por eso las playlists propias van como una sección de `/library`, y no hace falta un enlace "Playlists" en la navegación (ver Seguimiento de T-25).
- **Decisiones del coordinador para esta revisión (2026-11-09):**
  - T-14 tiene el endpoint para marcar una canción como no disponible. Lo llama el cliente cuando T-13 recibe un `playback_error` de una canción cuyo `origin` es una playlist propia.
  - El límite baja a **500 canciones por playlist**, el mismo que la cola de T-13 (sigue siendo una regla propuesta).
  - Las canciones de YouTube Music se añaden en T-11 (filas de búsqueda) y en T-12 (filas de biblioteca). La playlist mixta y "YouTube Music desconectado" van en T-10.
- La interfaz está en inglés (T-01): los textos de pantalla de los escenarios están en inglés. "Verano 2007" es un dato del usuario.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Dónde se ven y rutas:**
  - Una sección **"My playlists"** al principio de `/library`, antes de la lista "Playlists" de los servicios (T-08), con el botón "New playlist". Va separada porque estas playlists se editan, existen sin ningún servicio conectado y no dependen de la caché ni de los errores de Spotify de T-08. Se ve igual con Spotify sin conectar, en `reconnect_required` o con la API de Spotify fallando.
  - Página de detalle: `/library/playlists/own/{id}`, con el mismo estilo de rutas que T-08 (`/library/playlists/{source}/{id}`). `own` ocupa el lugar del servicio y no choca con `spotify` ni `youtube-music`. `{id}` se restringe a `[0-9]+`, así que `/library/playlists/own/abc` da 404 sin consultar la base de datos.
  - Acciones de página (formularios Inertia, que redirigen; sin sesión, redirigen a `/login`): `POST /library/playlists/own` (crear, redirige al detalle), `PATCH /library/playlists/own/{id}` (renombrar), `DELETE /library/playlists/own/{id}` (borrar, redirige a `/library` con "Playlist deleted.").
  - Endpoints JSON, porque se llaman desde otras páginas sin navegar (sin sesión, 401 con `"error": "unauthenticated"`, como T-13):
    - `GET /library/playlists/own`: lista para el menú "Add to playlist" (`id`, `title`, `total`), pedida cada vez que se abre el menú.
    - `POST /library/playlists/own/{id}/items`: añadir una canción. Responde 201, 200 (canción marcada que se recupera), 409 `already_in_playlist`, 422 (validación o `playlist_full`) o 404 `playlist_not_found`.
    - `DELETE /library/playlists/own/{id}/items/{item}`: quitar. 204, o 404 `playlist_item_not_found`.
    - `PUT /library/playlists/own/{id}/order` con la lista completa de `item_ids` en el orden nuevo. 204, 409 `playlist_changed` o 404.
    - `POST /library/playlists/own/{id}/items/{item}/unavailable`: marcar como no disponible. 204, también si ya estaba marcada (no cambia la fecha), o 404 `playlist_not_found` / `playlist_item_not_found`.
  - **Formato de los errores JSON:** la clave `error` con el código, como T-13 (por ejemplo, `{"error": "already_in_playlist"}`).
- **Forma de una playlist propia en las props:** la misma forma de playlist que T-08 (`source`, `id`, `uri`, `title`, `image`, `owner`, `collaborative`, `can_list_tracks`, `total`), con `source: "own"`, `uri: null`, `owner: null`, `collaborative: false`, `can_list_tracks: true`. Así el mismo componente de lista pinta las dos, y T-22 podría mezclarlas más adelante.
- **Forma de una canción de playlist propia:** las claves del formato común de T-07 más `item_id`, `position` y `added_at`. `playable` se calcula al servir la página (ver Disponibilidad).
- **Qué se guarda de cada canción (copia de sus datos):**
  - Al añadir, se guardan `source`, el `id` del servicio (columna `track_id`), `uri`, `title`, `artists`, `album`, `duration_ms` e `image`, tal como llegan en el formato común. La página de la playlist se pinta solo desde la base de datos, **sin llamar a Spotify**. Guardar `source` es lo que permite que YouTube Music se sume después sin cambiar el modelo.
  - *A favor:* la página carga rápido, funciona con Spotify caído, limitado (429) o desconectado, no gasta el límite de peticiones y no necesita las peticiones en lote que Spotify quitó en febrero de 2026 (sin ellas, refrescar 500 canciones serían 500 peticiones).
  - *En contra:* los datos pueden quedarse desfasados si el servicio cambia el título o la carátula, y la URL de la carátula puede dejar de funcionar. Si la imagen no carga, se muestra `AeroCover`. No se refrescan los datos (ver Pendiente).
  - El servidor **no consulta el servicio al añadir**: confía en los datos que envía el navegador, después de validarlos, como hace T-13 con la cola. Es una app de un solo usuario, y así añadir funciona igual para YouTube Music.
  - **Claves extra:** se ignoran (no dan 422). Solo se guardan las claves del formato común validadas. `position`, `local`, `added_at` o `item_id` que lleguen de una fila de T-08 no se usan: la posición la pone el servidor y `added_at` es el momento de añadir.
- **Validación de la canción al añadir:**
  - `source` solo admite los servicios integrados. En T-14 solo `spotify`; T-11 y T-12 añaden `youtube_music`.
  - Para Spotify, `id` con el formato `[A-Za-z0-9]{22}` y `uri` exactamente `spotify:track:{id}`.
  - `title` obligatorio (máximo 500 caracteres), `artists` lista de textos, `album` texto o null, `duration_ms` entero mayor o igual que 0, `image` null o URL `https` (máximo 2048 caracteres).
  - `playable` debe ser true: una canción no reproducible no se añade (422), y su fila no muestra "Add to playlist".
- **Nombres:**
  - Se quitan los espacios del principio y del final. Obligatorio y de **100 caracteres como máximo**, contados como caracteres y no como bytes (`max:100` de Laravel usa `mb_strlen`), así que los emojis y los acentos cuentan como uno.
  - **No se permiten dos playlists con el mismo nombre**, sin distinguir mayúsculas y minúsculas, para que el menú "Add to playlist" no muestre dos entradas iguales. Los acentos sí distinguen: "Canción" y "Cancion" pueden convivir. Renombrar una playlist a su propio nombre con otras mayúsculas sí se permite.
  - Se guarda una columna `name_key = mb_strtolower(trim(name))` con índice único (`user_id`, `name_key`). En MySQL, la collation por defecto (`utf8mb4_unicode_ci` o `utf8mb4_0900_ai_ci`) ignora acentos y mayúsculas también en los índices únicos, así que **`name_key` debe usar la collation `utf8mb4_bin`** (`->collation('utf8mb4_bin')` en la migración). En SQLite (tests) la comparación ya es binaria.
  - La comprobación previa de nombre repetido es una regla de validación que usa `PlaylistRepository`, para dar el error junto al campo. El índice único es la última garantía: si dos peticiones llegan a la vez y la segunda choca con él, la violación del índice se convierte en el mismo error de validación ("You already have a playlist with that name."), nunca en un 500.
  - Textos: "Name is required.", "Name can't be longer than 100 characters.", "You already have a playlist with that name.".
- **Canciones repetidas:** **no se permite la misma canción (mismo `source` e `id`) dos veces en una playlist**. Se responde 409 `already_in_playlist` y se avisa con 'Already in "{name}".'.
  - La comprobación se hace **dentro de la transacción con bloqueo** de la playlist (no con una regla de validación), y hay índice único (`playlist_id`, `source`, `track_id`). Si aun así salta la violación del índice, se convierte en 409 `already_in_playlist`.
  - **`track_id` también usa `utf8mb4_bin`**, porque los ids de Spotify distinguen mayúsculas y minúsculas: con una collation `_ci`, dos canciones distintas chocarían.
  - **Excepción:** si la canción ya está pero marcada como no disponible, añadirla otra vez le quita la marca, sin duplicarla ni moverla, y responde **200** (no 409). Como no añade ninguna fila, no pasa por la comprobación del límite: también responde 200 con la playlist llena (500 canciones).
  - La misma canción sí puede estar en varias playlists. La misma canción en Spotify y en YouTube Music cuenta como dos canciones distintas hasta que T-22 decida el criterio de "misma canción".
- **Límite:** como máximo **500 canciones por playlist** (422 `playlist_full`, "This playlist is full (500 songs)."), el mismo que la cola de T-13. Así una playlist propia siempre cabe entera en la cola y nunca aparece el aviso de T-13 "Only the first 500 songs were added to the queue.". También acota la petición de reordenar y la página, que no se pagina. No hay límite de número de playlists.
- **Orden:**
  - Las playlists, en `/library` y en el menú, por nombre de la A a la Z, sin distinguir mayúsculas y minúsculas, y con orden natural de acentos ("Ánimo" antes que "beats"). Se ordenan **en el navegador** con `Intl.Collator` (sensibilidad `base`, `numeric: true`), que tienen todos los navegadores actuales. Así no depende de la extensión `intl` de PHP ni de la collation de la base de datos (ver Pendiente). Desempate: con sensibilidad `base`, "Canción" y "Cancion" son iguales, así que los empates se ordenan de nuevo con sensibilidad `variant` y, si siguen iguales, por `id`, para que el orden no dependa del orden en que llegan del servidor.
  - Las canciones, por `position` (0..n-1, sin huecos). Añadir pone la canción al final.
  - Reordenar envía la lista completa de `item_ids`. Si no coincide exactamente con las canciones actuales (otra pestaña añadió o quitó alguna), responde 409 `playlist_changed` y la página recarga la versión actual con el aviso "This playlist changed in another tab. Showing the latest version.".
  - Añadir, quitar, reordenar y marcar se hacen en una transacción que bloquea la fila de la playlist (`lockForUpdate`), para que el límite de 500, las posiciones y la comprobación de repetidas aguanten dos peticiones a la vez.
- **Interacción:**
  - Reordenar arrastrando la fila por su asa, también en pantallas táctiles (el discovery pide móvil), por ejemplo con SortableJS o `vue-draggable-plus`; se decide en /opsx-propose. Para el teclado y el lector de pantalla, cada fila tiene los botones **"Move up"** y **"Move down"**, como el panel de T-13. "Move up" se desactiva en la primera fila y "Move down" en la última. Cada pulsación envía el orden completo.
  - Quitar con "Remove from playlist", sin confirmación. Borrar la playlist pide confirmación con el diálogo 'Delete "{name}"? This can't be undone.' ("Delete" / "Cancel").
  - El menú "Add to playlist" pide la lista al abrirse, así que ve las playlists creadas en otra pestaña. Si no hay ninguna, muestra "No playlists yet." y el enlace "Go to Library". Crear una playlist desde el menú queda fuera de alcance.
  - Avisos breves (con `AeroFlash` de T-25 o un aviso equivalente): 'Added to "{name}".', 'Already in "{name}".', "This playlist is full (500 songs).", "This playlist no longer exists.", "Playlist deleted.", "Couldn't update the playlist. Try again.".
- **Fallos del servidor o de la red al cambiar una playlist** (5xx o red cortada): se muestra **"Couldn't update the playlist. Try again."** y no queda ningún cambio a medias.
  - Añadir y quitar no son optimistas: la lista solo cambia cuando el servidor responde bien.
  - Reordenar sí se ve al momento (arrastrar o "Move up"/"Move down"), y si el servidor falla **se deshace**: la lista vuelve al orden anterior.
  - Renombrar deja el diálogo abierto con el nombre escrito, y la playlist conserva su nombre.
  - Renombrar y crear son peticiones de formulario de Inertia, y por defecto Inertia enseña un 5xx en un modal de error. La página lo intercepta (por ejemplo, con el evento `invalid` del router de Inertia o el `onError`/`onException` de la versión instalada; se comprueba en el diseño) para mostrar el aviso breve en lugar del modal.
  - No hay reintento automático: el usuario repite la acción.
- **Carátula de una playlist propia:** la carátula guardada de su primera canción, o `AeroCover` si está vacía o la primera no tiene imagen.
- **Disponibilidad ("Unavailable").** Una canción se muestra atenuada con "Unavailable", no se envía a la cola y no reacciona al pulsarla en dos casos:
  - **Marcada como no disponible** (columna `unavailable_at`; es lo que fija D7). El cliente llama a `POST .../items/{item}/unavailable` cuando T-13 recibe un **`playback_error`** del SDK (T-23) para una canción de la cola cuyo `origin` es `own_playlist`. Solo cuenta ese error:
    - **No marcan** (aunque T-13 salte la canción o muestre un aviso): que pasen 10 s sin que empiece (T-13 la salta, pero puede ser una red lenta), 429 `rate_limited`, 403 `premium_required`, 404 `device_not_found`, 502 `spotify_unavailable` (incluye el **timeout HTTP de 10 s del cliente de T-05**, no el margen de 10 s de T-13), `not_ready`, `authentication_error` y `account_error`.
    - Una canción de la cola sin `origin` (añadida con "Add to queue" desde otra lista) no marca nada, aunque también esté en una playlist propia.
    - Si el endpoint responde 404 (la canción o la playlist ya no existen), el cliente no muestra nada más: la cola ya ha saltado.
  - **Su servicio no está conectado o pide reconexión** (T-05). **Es una regla propuesta, no parte de D7:** D7 habla de canciones que dejan de estar disponibles en su servicio, y aquí se extiende la misma presentación a un servicio desconectado. Se calcula al servir la página y no se guarda: al reconectar, las canciones vuelven a estar disponibles. La página muestra "Connect Spotify to play its songs." con el botón "Connect Spotify" de T-05 si no está conectado, o el aviso de T-05 "Your Spotify access expired or was revoked. Please reconnect Spotify." con el mismo botón si está en `reconnect_required`.
  - Al añadir no se guarda nada como no disponible, porque las canciones con `playable: false` no se pueden añadir.
  - **Recuperar una canción marcada:** volver a añadirla desde una lista donde aparece como reproducible le quita la marca (200, ver Canciones repetidas).
  - No se comprueba la disponibilidad al abrir la playlist (ver la copia de datos y Pendiente).
- **Reproducir (cola de T-13):**
  - "Play" envía `PUT /queue {tracks, current_index: 0}` con las canciones **disponibles** de la playlist, en su orden. Pulsar una fila envía lo mismo con `current_index` igual al índice de esa canción **dentro de la lista de disponibles** (en A, B no disponible y C, pulsar C da `current_index` 1).
  - Cada canción enviada lleva `origin: {type: "own_playlist", playlist_id, item_id}`. T-13 lo guarda tal cual, sin interpretarlo, y lo devuelve en `GET /queue` (ver Seguimiento de T-13).
  - "Play" se desactiva si la playlist está vacía o no tiene ninguna canción disponible. Pulsar una fila "Unavailable" no envía nada.
  - Borrar la playlist, quitar canciones o reordenarlas no cambia la cola ya cargada: la cola tiene su propia copia (D3).
- **Acceso a datos:** modelos `Playlist` y `PlaylistItem`, y repositorios concretos `PlaylistRepository` y `PlaylistItemRepository` en `app/Repositories/`, sin interfaces. La regla de validación de nombre repetido usa el repositorio, no `unique` contra el modelo. Las canciones repetidas se comprueban en el repositorio dentro de la transacción. Las playlists tienen `user_id`, aunque haya un solo usuario, y los repositorios siempre filtran por él.
- **Errores:**
  - Página de una playlist que no existe: 404 con la página de error de la app (como T-24 con un álbum que no existe).
  - Renombrar o borrar una que ya no existe: redirige a `/library` con "This playlist no longer exists.".
  - Endpoints JSON sobre una playlist que no existe: 404 `playlist_not_found`. Sobre una canción que ya no está: 404 `playlist_item_not_found`, y la página recarga la versión actual sin mostrar error.

**Restricciones descubiertas**

- **Sin peticiones en lote en Spotify:** desde febrero de 2026, el development mode no permite `GET /tracks` con varios ids. Comprobar la disponibilidad de una playlist entera costaría una petición por canción. Por eso la página se pinta desde la copia guardada. Fuente: [February 2026 Migration Guide](https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide).
- **Señal de canción que no suena:** el Web Playback SDK emite `playback_error` cuando falla la carga o la reproducción de una pista, y `not_ready` cuando se pierde la conexión. El evento no dice si el fallo es permanente. Fuente: [Web Playback SDK reference](https://developer.spotify.com/documentation/web-playback-sdk/reference) (recogido en T-23 y T-13).
- **URLs de imágenes:** Spotify avisa de que las URLs de las carátulas de playlists caducan en menos de un día. Las de las carátulas de álbum, que son las que lleva una canción, no tienen esa advertencia, pero tampoco una garantía. Por eso hay respaldo con `AeroCover`. Fuente: [Get Current User's Playlists](https://developer.spotify.com/documentation/web-api/reference/get-a-list-of-current-users-playlists) (recogido en T-08).
- **Las pistas de un álbum no traen álbum ni carátula:** las pistas de `GET /albums/{id}` y `GET /albums/{id}/tracks` son objetos simplificados, sin `album`. Las filas de T-24 tienen que rellenar `album` e `image` con los datos del álbum para que la copia guardada tenga carátula (ver Seguimiento de T-24). Fuente: [Get Album Tracks](https://developer.spotify.com/documentation/web-api/reference/get-an-albums-tracks).
- **Collation de MySQL:** las collations `_ci`/`_ai` (por defecto `utf8mb4_0900_ai_ci` en MySQL 8 y `utf8mb4_unicode_ci` en la configuración por defecto de Laravel) comparan sin distinguir mayúsculas ni acentos, también en los índices únicos. Por eso `name_key` y `track_id` usan `utf8mb4_bin`. Fuente: [MySQL — Character Sets and Collations](https://dev.mysql.com/doc/refman/8.0/en/charset-general.html).
- **Playlists que solo se siguen:** sus canciones no se pueden listar (T-08), así que no pueden ser origen de canciones.

**Casos límite**

- **Pulsar dos veces seguidas** la misma playlist del menú "Add to playlist": la segunda petición recibe 409 `already_in_playlist`, y queda una sola copia. El menú se cierra tras la primera selección.
- **Dos pestañas añaden canciones a la vez** con 499 canciones: la transacción con bloqueo garantiza que solo una entra y la otra recibe `playlist_full`.
- **Nombre que, tras quitar espacios, coincide con otro:** "  Gym " choca con "Gym".
- **Nombres con caracteres especiales** (`<script>`, comillas, emojis, texto de derecha a izquierda): se guardan tal cual y se muestran escapados, recortados con puntos suspensivos si no caben.
- **Canción con varios artistas:** se muestran separados por comas, como en T-07.
- **Canción sin carátula** (`image: null`) o con una URL que ya no carga: `AeroCover`.
- **Reordenar una playlist con una sola canción:** no se envía nada, y "Move up" y "Move down" están desactivados.
- **Arrastrar y soltar en el mismo sitio:** no se envía nada.
- **Spotify limitado (429) o caído:** la página de la playlist se ve igual, porque no llama a Spotify. Lo que falla, si acaso, es la reproducción, que gestiona T-13/T-23.
- **Desconectar Spotify** no borra ni cambia ninguna playlist propia: solo atenúa sus canciones mientras dura.
- **Tres canciones seguidas que fallan** (D4): T-13 se para. Se marcan solo las que fallaron por `playback_error`.
- **La cola cargada en otro dispositivo:** el `origin` viene en `GET /queue` (D3), así que el móvil también marca la canción si falla allí.
- **Una canción que falla dos veces seguidas** (por ejemplo, con "Repeat: all"): la segunda llamada al endpoint responde 204 y no cambia la fecha de la marca.

**Pistas para los tests**

- **Base de datos:** `RefreshDatabase` y factories `PlaylistFactory` y `PlaylistItemFactory` con datos de canción de Spotify realistas (ids base62 de 22 caracteres) y un estado `unavailable()`.
- **Sin llamadas a Spotify:** `Http::preventStrayRequests()` y `Http::assertSentCount(0)` al servir la página de una playlist propia, al añadir y en todos los casos sin sesión o sin conexión.
- **Respuestas Inertia:** `assertInertia` con el componente, `has('items', 3)` y las claves exactas de cada canción y de cada playlist, comparadas con `toEqualCanonicalizing` (como T-08).
- **Endpoints JSON:** `postJson`/`putJson`/`deleteJson` con `assertStatus(409)` y `assertJsonPath('error', 'already_in_playlist')`, y los estados 200, 201, 204, 401, 404 y 422.
- **Concurrencia e índices únicos:** la "otra pestaña" se simula con una segunda petición o cambiando la base de datos entre dos peticiones. La violación del índice se provoca creando la fila justo antes de que el repositorio inserte (por ejemplo, con un evento `creating` en el test o llamando dos veces al repositorio). Hay que comprobar que da 409 o el error de nombre, no un 500.
- **Collation:** el test de "Canción"/"Cancion" y el de los ids con mayúsculas solo prueban de verdad la collation en MySQL. En SQLite pasan siempre. Conviene un test de la migración que compruebe `utf8mb4_bin` en las dos columnas cuando la conexión es MySQL, o ejecutar la suite contra MySQL en el stack de Docker de T-20.
- **Límite de 500:** la factory crea 500 canciones con `count(500)`. Basta un test de la petición, no de navegador.
- **Nombres:** `str_repeat('a', 100)` y `str_repeat('a', 101)`, y uno de 100 caracteres con emojis para comprobar que se cuentan caracteres y no bytes.
- **Navegador (Pest 4, T-03):** crear con el diálogo, arrastrar y soltar y "Move up"/"Move down", el menú "Add to playlist" desde búsqueda, álbum y Liked Songs, el diálogo de borrado, el orden con `Intl.Collator`, filas atenuadas, la carátula de respaldo (una URL de imagen que responde 404) y los fallos de red (se bloquean las rutas `/library/playlists/own*` desde el test, como hace T-13 con `/queue*`).
- **Reproducir y marcar:** con el SDK simulado de T-06 y la API de la cola de T-13. Se comprueba del lado servidor el cuerpo de `PUT /queue` (`tracks`, `current_index` y `origin`) y la llamada a `.../unavailable`. Los fallos se provocan haciendo que el SDK simulado emita `playback_error`, `not_ready` o `authentication_error`, con las respuestas de error de `PUT /spotify/player/play` y con el reloj del navegador para los 10 s.
- **Manual:** que una playlist propia suena entera y salta las canciones "Unavailable" se comprueba a mano con la cuenta real.

**Fuera de alcance**

- **Canciones de YouTube Music** en playlists propias y **playlists mixtas de verdad** (D5): pasan a T-10, T-11 y T-12 (ver Seguimiento).
- **Sincronizar** con Spotify o YouTube Music, importar una playlist de un servicio o exportar una propia (D6).
- Añadir de una vez un álbum entero, una playlist de un servicio, todos los resultados de búsqueda o la cola de T-13 ("guardar la cola como playlist", que T-13 también deja fuera).
- Crear una playlist desde el menú "Add to playlist", carpetas de playlists, descripción, carátula propia y playlists compartidas.
- "Add to playlist" en las filas de una playlist propia (copiar canciones entre playlists propias) y en el panel de la cola de T-13.
- Un enlace "Playlists" en la navegación: las playlists propias están dentro de `/library` (D12).
- Deshacer después de quitar una canción o borrar una playlist.
- Comprobar o refrescar la disponibilidad y los datos de las canciones contra el servicio (ver Pendiente).
- Mezcla y deduplicación entre servicios: T-22.

**Pendiente**

- **Qué devuelve Spotify con una canción que ya no existe o no está disponible.** Se marca solo con `playback_error`, pero el evento no distingue un fallo permanente de uno puntual. Hay que comprobarlo en el spike de T-06/T-13 con la cuenta real. Si da falsos positivos, la canción se recupera volviéndola a añadir.
- **Refrescar los datos guardados o comprobar la disponibilidad** (por ejemplo, con `GET /tracks/{id}` para las canciones no comprobadas en los últimos N días, con un tope de peticiones). Queda fuera de esta tarea por el coste en peticiones.
- **Texto de las canciones no reproducibles:** D7 fija "Unavailable" para las playlists propias, pero T-07, T-08, T-24 y T-13 usan "Not available" para las canciones con `playable: false`. Conviene unificarlo en /opsx-propose (ver Seguimiento).
- **Ordenar en el servidor:** se propone ordenar las playlists en el navegador. Si se prefiere hacerlo en PHP con `Collator`, hace falta la extensión `intl`, y que esté disponible en Hostinger depende de la pregunta abierta 1.
- **Límite de 100 caracteres por nombre** y la prohibición de nombres repetidos y de canciones repetidas: son propuestas que el usuario puede cambiar.

**Seguimiento (cambios propuestos en otros ficheros)**

- **T-13 (`backlog/tasks/T-13-cola-de-reproduccion.md`):** las canciones de la cola admiten una clave opcional y opaca `origin` (por ejemplo, `{type: "own_playlist", playlist_id, item_id}`). Se recibe en `PUT /queue`, se guarda sin interpretarla y se devuelve con cada canción en `GET /queue` (hay que añadir `origin` a la lista de claves exactas de su escenario "Leer la cola guardada"). Nada más cambia en T-13: el endpoint para marcar es de T-14, y lo llama el cliente al recibir un `playback_error` de una canción con ese `origin`.
- **T-08 (`backlog/tasks/T-08-biblioteca-spotify.md`):**
  - `/library` aloja la sección "My playlists" de T-14 al principio, visible aunque Spotify no esté conectado, esté en `reconnect_required`, falle la API o la caché esté vacía.
  - En el escenario "Acceso revocado desde Spotify" (L273), cambiar "no ve ninguna playlist ni la entrada "Liked Songs"" por "no ve ninguna playlist de Spotify ni la entrada "Liked Songs"". Revisar igual "Al desconectar Spotify no se ven datos de su biblioteca" (ya dice "ninguna playlist de Spotify") y "Biblioteca vacía": "Your library is empty." solo habla del contenido de los servicios (o se reescribe como "Your Spotify library is empty.").
  - Las filas de canción de las playlists propias o colaborativas y de "Liked Songs" llevan "Add to playlist" (no las de archivos locales ni las no reproducibles).
  - El segmento `own` de `/library/playlists/{source}/{id}` queda reservado para T-14.
- **T-24 (`backlog/tasks/T-24-spotify-album-y-artista.md`):** las filas de pista del álbum deben emitir el formato común de canción de T-07 completo, con `album` igual al título del álbum e `image` igual a su carátula, porque las pistas de Spotify de un álbum no traen esos datos. Sin esto, la copia guardada en una playlist propia no tendría carátula ni álbum. El componente de fila no muestra "Add to playlist" cuando `playable` es false.
- **T-07 (`backlog/tasks/T-07-buscar-spotify.md`):** el componente de fila no muestra "Add to playlist" cuando `playable` es false. Unificar el texto "Not available" con el "Unavailable" de D7 (también en T-08, T-24 y T-13).
- **T-05 (`backlog/tasks/T-05-conectar-spotify.md`):** las props compartidas exponen solo `spotify.connected` (booleano). T-14 necesita distinguir "sin conectar" de `reconnect_required` para el aviso. Propuesta: una prop `spotify.status` con los valores `not_connected`, `connected` o `reconnect_required`.
- **T-25 (`backlog/tasks/T-25-retirar-shadcn-y-migrar-pantallas.md`):** quitar el enlace "Playlists" que "añade T-14 cuando exista" de "Destinos de la navegación" (L287) y de "Fuera de alcance" (L371). Las playlists propias viven dentro de `/library` (D12), que ya está en la navegación como "Library" y se marca activa en `/library/playlists/…`.
- **T-11 (`backlog/tasks/T-11-buscar-youtube-music.md`):** "Add to playlist" en las filas de canción de YouTube Music de la búsqueda, y añadir `youtube_music` a los `source` admitidos por `POST /library/playlists/own/{id}/items` (con su formato de `id` y `uri`). Recibe el escenario "Añadir canciones de los dos servicios": una canción de Spotify y otra de YouTube Music en "Verano 2007", cada una con su servicio de origen.
- **T-12 (`backlog/tasks/T-12-biblioteca-youtube-music.md`):** "Add to playlist" en las filas de canción de la biblioteca de YouTube Music. Si T-12 se entrega antes que T-11, es la que añade `youtube_music` a los `source` admitidos.
- **T-10 (`backlog/tasks/T-10-reproducir-youtube-music.md`):** recibe los escenarios "Reproducir una playlist mixta" (`PUT /queue` con las canciones de los dos servicios en el orden de la playlist) y "YouTube Music desconectado" (sus canciones salen "Unavailable", no se envían a la cola y suenan solo las de Spotify). Define qué error del reproductor de YouTube equivale al `playback_error` y llama al endpoint de marcar de T-14.
- **T-22 (`backlog/tasks/T-22-mezcla-y-duplicados-entre-servicios.md`):** decidir si la misma canción en Spotify y en YouTube Music cuenta como repetida dentro de una playlist propia, y si las playlists propias entran en la lista mezclada de la biblioteca.
- **Discovery (`backlog/discovery/reproductor-musica-privado.md`):** marcar la pregunta abierta 4 como resuelta el 2026-11-09: solo en la base de datos de la app, sin sincronizar (D6); las canciones no disponibles se quedan atenuadas como "Unavailable", se saltan y se quitan a mano (D7).

**Estimación: 5 puntos**, que se pueden dividir al proponer el cambio:

- **T-14a — Playlists, añadir, quitar y reordenar (unos 3 puntos):**
  - Modelo, migraciones con `utf8mb4_bin`, repositorios y CRUD de playlists con nombres, índice único y redirecciones ante playlists borradas: 0,75 puntos.
  - "Add to playlist" en búsqueda, álbum y biblioteca, con el endpoint, la validación, las repetidas dentro de la transacción y el límite: 1 punto.
  - Página de la playlist con arrastrar, "Move up"/"Move down", quitar, conflictos entre pestañas y deshacer ante fallos: 0,75 puntos.
  - Tests de Pest y de navegador de esta parte: 0,5 puntos.
- **T-14b — Disponibilidad y reproducción (unos 2 puntos):** depende de T-14a y de T-13.
  - Estado derivado de la conexión de Spotify, avisos y "Play" desactivado: 0,25 puntos.
  - Reproducir con `PUT /queue` y `origin`, saltando las no disponibles: 0,5 puntos.
  - Endpoint de marcar, llamada desde el cliente solo con `playback_error` y recuperar al volver a añadir: 0,5 puntos.
  - Tests con el SDK simulado y la cola de T-13 (siete fallos que no marcan, otro dispositivo) y la comprobación manual: 0,75 puntos.

**Seguimiento de spotify-connection (T-05, 2026-11-14)**

- Las props compartidas son `spotify.status` (`not_connected`, `connected` o `reconnect_required`) y `spotify.connected`. Sirven para elegir entre el aviso de conectar y el de reconectar.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Manual check with the real Spotify account: an own playlist plays in order and skips "Unavailable" songs
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
