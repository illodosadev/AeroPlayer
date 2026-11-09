---
id: T-24
title: "Spotify: páginas de álbum y artista"
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 2
language: es
---

# T-24 — Spotify: páginas de álbum y artista

## Goal

Abrir un álbum o un artista de Spotify desde los resultados de búsqueda, ver sus pistas o su discografía y reproducir el álbum desde el principio o desde cualquier pista. Así se completa la necesidad del discovery de "buscar canciones, álbumes y artistas y reproducirlos": no basta con encontrar un álbum, hay que poder escucharlo entero.

## Context

- Sale de T-07 al revisarla, para que cada tarea sea un solo cambio. T-07 queda con la búsqueda y esta tarea con las páginas de detalle.
- **Depende de:**
  - **T-07**, que aporta los resultados de álbumes y artistas que enlazan aquí, el formato común de álbum y el componente de lista con "Load more".
  - **T-06**, que aporta la función de front-end `play({contextUri?, uris?, offset})` para reproducir un contexto de Spotify desde un índice de inicio.
  - **T-05** (indirectamente), que aporta el cliente HTTP de Spotify compartido. Ese cliente tiene un timeout de 10 s, una única espera tras un `Retry-After` compartida (5 s si falta la cabecera) y renueva el token tras un 401.
- Spotify eliminó en febrero de 2026 el endpoint de canciones más populares de un artista. Por eso la página de artista solo muestra su discografía (ver "Restricciones descubiertas" y "Pendiente").
- Puntos de entrada para otras tareas: cada fila de pista del álbum es donde T-13 pondrá "Add to queue" y T-14 "Add to playlist". T-24 no implementa esas acciones.

## Acceptance criteria

```gherkin
# --- Llegar a las páginas -------------------------------------------------------

Scenario: Abrir un álbum desde los resultados de búsqueda
  Given resultados de búsqueda con el álbum "Discovery" de id "2noRn2Aes5aoNVsU6iWThc"
  When el usuario pulsa el álbum
  Then llega a "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"

Scenario: Abrir un artista desde los resultados de búsqueda
  Given resultados de búsqueda con el artista "Daft Punk" de id "4tZwfgrHOc3mvqYlEYSvVi"
  When el usuario pulsa el artista
  Then llega a "/spotify/artists/4tZwfgrHOc3mvqYlEYSvVi"

# --- Álbum --------------------------------------------------------------------

Scenario: Ver un álbum
  Given la API de Spotify (falseada) con el álbum "Discovery" de "Daft Punk", de 2001 y con 14 pistas
  When el usuario abre "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"
  Then ve el título "Discovery", el artista "Daft Punk", el año "2001" y la carátula
  And ve las 14 pistas en orden, cada una con su número, su título y su duración en formato "m:ss"

Scenario: El artista del álbum enlaza a su página
  Given la página del álbum "Discovery"
  When el usuario pulsa "Daft Punk"
  Then llega a "/spotify/artists/4tZwfgrHOc3mvqYlEYSvVi"

Scenario: Reproducir el álbum desde el principio
  Given la página del álbum "Discovery" con URI "spotify:album:2noRn2Aes5aoNVsU6iWThc"
  When el usuario pulsa "Play"
  Then el espía sobre la función de front-end de T-06 `play({contextUri?, uris?, offset})` registra una sola llamada, con contextUri "spotify:album:2noRn2Aes5aoNVsU6iWThc" y offset 0

Scenario: Reproducir el álbum desde una pista
  Given la página del álbum "Discovery"
  When el usuario pulsa la pista número 3
  Then el espía sobre `play` registra una llamada con contextUri "spotify:album:2noRn2Aes5aoNVsU6iWThc" y offset 2

Scenario: Reproducir desde una pista de un álbum de varios discos
  Given la página del álbum de id "7dK54iZuOxXFarGhXwEXfF", con 10 pistas en el disco 1 y 5 en el disco 2
  When el usuario pulsa la pista 1 del disco 2
  Then el espía sobre `play` registra una llamada con contextUri "spotify:album:7dK54iZuOxXFarGhXwEXfF" y offset 10

Scenario: Álbum con más pistas que una página de Spotify
  Given el álbum de id "3Ks0eeH0GWpY4AU20D5HPD" con 60 pistas en la API de Spotify
  When el usuario abre su página
  Then se envía a Spotify una petición GET "/v1/albums/3Ks0eeH0GWpY4AU20D5HPD", que trae las 50 primeras pistas
  And una sola petición de pistas, con limit 50 y offset 50
  And la página muestra las 60 pistas, sin "Load more"

Scenario: Álbum que supera el máximo de pistas
  Given el álbum de id "5Lq2TzK8wVnB3xYcRmD7aE" con 250 pistas en la API de Spotify
  When el usuario abre su página
  Then se envía a Spotify una petición del álbum, que trae las 50 primeras pistas
  And 3 peticiones de pistas, con limit 50 y offset 50, 100 y 150
  And la página muestra las 200 primeras pistas y el texto "Showing the first 200 of 250 tracks."

Scenario: Pista no disponible en el álbum
  Given la página de un álbum en el que Spotify marca la pista número 5 con "is_playable" false
  When se muestra la página
  Then la pista 5 aparece atenuada con el texto "Not available"
  And al pulsarla, el espía sobre `play` no registra ninguna llamada

Scenario: Ninguna pista disponible
  Given la página de un álbum en el que Spotify marca todas las pistas con "is_playable" false
  When se muestra la página
  Then el botón "Play" está desactivado

# --- Artista ------------------------------------------------------------------

Scenario: Ver un artista
  Given la API de Spotify con el artista "Daft Punk", que tiene 4 álbumes y 12 singles
  When el usuario abre "/spotify/artists/4tZwfgrHOc3mvqYlEYSvVi"
  Then ve el nombre "Daft Punk" y su imagen
  And se piden a Spotify los álbumes con include_groups "album" y los singles con include_groups "single", cada uno con limit 10 y offset 0
  And ve la sección "Albums" con 4 álbumes y sin "Load more"
  And ve la sección "Singles and EPs" con 10 singles y "Load more"

Scenario: Cargar más singles
  Given la página de "Daft Punk" con 10 de sus 12 singles en pantalla
  When el usuario pulsa "Load more" en la sección "Singles and EPs"
  Then la app pide al endpoint de discografía el grupo "singles" con el "next_cursor" de la respuesta anterior
  And se envía a Spotify una petición con include_groups "single", limit 10 y offset 10
  And la sección "Singles and EPs" muestra 12 singles y ya no muestra "Load more"

Scenario: Un doble clic en "Load more" envía una sola petición
  Given la página de "Daft Punk" con 10 de sus 12 singles en pantalla
  When el usuario pulsa dos veces seguidas "Load more" en la sección "Singles and EPs"
  Then se envía a Spotify una sola petición con offset 10

Scenario: Un álbum de la discografía enlaza a su página
  Given la página de "Daft Punk" con el álbum "Discovery" en la sección "Albums"
  When el usuario pulsa "Discovery"
  Then llega a "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"

Scenario: Artista sin singles
  Given un artista con 3 álbumes y 0 singles en Spotify
  When el usuario abre su página
  Then ve la sección "Albums" con 3 álbumes
  And no ve la sección "Singles and EPs"

Scenario: Artista sin discografía
  Given un artista con 0 álbumes y 0 singles en Spotify
  When el usuario abre su página
  Then ve su nombre y el texto "No releases on Spotify."

# --- Ids que no existen o mal formados ------------------------------------------

Scenario: Álbum que no existe
  Given la API de Spotify responde 404 para el álbum "1A2b3C4d5E6f7G8h9I0jKl"
  When el usuario abre "/spotify/albums/1A2b3C4d5E6f7G8h9I0jKl"
  Then la respuesta tiene estado 404 con la página de error de la app

Scenario: Artista que no existe
  Given la API de Spotify responde 404 para el artista "9Zy8Xw7Vu6Ts5Rq4Po3Nm2"
  When el usuario abre "/spotify/artists/9Zy8Xw7Vu6Ts5Rq4Po3Nm2"
  Then la respuesta tiene estado 404 con la página de error de la app

Scenario Outline: Id mal formado
  Given el usuario con sesión iniciada y Spotify conectado
  When abre "<ruta>"
  Then la respuesta tiene estado 404 con la página de error de la app
  And no se envía ninguna petición a Spotify

  Examples:
    | ruta                                     |
    | /spotify/albums/notAnId123               |
    | /spotify/albums/2noRn2Aes5aoNVsU6iWTh-c  |
    | /spotify/artists/4tZwfgrHOc3mvqYlEYSvV   |

Scenario: Spotify rechaza un id con 400
  Given la API de Spotify responde 400 "invalid id" para el álbum "zzzzzzzzzzzzzzzzzzzzzz"
  When el usuario abre "/spotify/albums/zzzzzzzzzzzzzzzzzzzzzz"
  Then la respuesta tiene estado 404 con la página de error de la app
  And la respuesta no contiene el mensaje de error de Spotify

# --- Errores de Spotify (a través del cliente compartido de T-05) ---------------

Scenario: Spotify limita las peticiones al abrir un álbum
  Given Spotify conectado y la API de Spotify responde 429 con la cabecera "Retry-After: 30"
  When el usuario abre "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"
  Then la respuesta tiene estado 200 con el componente Inertia de la página de álbum y la prop de error "spotify_unavailable"
  And la página muestra "Spotify isn't available right now. Try again in a moment." y el botón "Try again"
  And la app no muestra una página de error

Scenario: Error del servidor de Spotify al abrir un artista
  Given Spotify conectado y la API de Spotify responde 503
  When el usuario abre "/spotify/artists/4tZwfgrHOc3mvqYlEYSvVi"
  Then la respuesta tiene estado 200 con el componente Inertia de la página de artista y la prop de error "spotify_unavailable"
  And la página muestra "Spotify isn't available right now. Try again in a moment." y el botón "Try again"
  And la app no muestra una página de error

Scenario: Falla "Load more" en la discografía
  Given la página de "Daft Punk" con 10 singles en pantalla y la API de Spotify responde 503
  When el usuario pulsa "Load more" en la sección "Singles and EPs"
  Then el endpoint de discografía responde 503 con el código de error "spotify_unavailable"
  And la sección sigue mostrando los 10 singles, con el mensaje "Spotify isn't available right now. Try again in a moment."
  And "Load more" sigue disponible

Scenario: Falla una petición de pistas a mitad de la carga del álbum
  Given Spotify conectado y un álbum de 120 pistas cuyo "GET /albums/{id}" devuelve las 50 primeras
  And la petición de pistas con offset 50 responde 200 y la de offset 100 responde 503
  When el usuario abre la página de ese álbum
  Then la respuesta tiene estado 200 con la prop de error "spotify_unavailable"
  And la página no muestra ninguna fila de pista, ni siquiera las ya recibidas
  And muestra el botón "Try again"

# --- Sin conexión y sin sesión ----------------------------------------------------

Scenario: Acceso a Spotify revocado al abrir un álbum
  Given el usuario con sesión iniciada y la conexión de Spotify en estado "reconnect_required" (T-05)
  When abre "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"
  Then la respuesta tiene estado 200 con la prop de error "spotify_reconnect_required"
  And la página muestra el botón "Reconnect Spotify" de T-05
  And no se envía ninguna petición a Spotify

Scenario: Endpoint de discografía con el acceso revocado
  Given el usuario con sesión iniciada y la conexión de Spotify en estado "reconnect_required"
  When se pide el endpoint de discografía de "4tZwfgrHOc3mvqYlEYSvVi" con "Accept: application/json"
  Then la respuesta tiene estado 409 con el código de error "spotify_reconnect_required"
  And no se envía ninguna petición a Spotify

Scenario: Abrir un álbum sin Spotify conectado
  Given el usuario con sesión iniciada y Spotify sin conectar
  When abre "/spotify/albums/2noRn2Aes5aoNVsU6iWThc"
  Then la respuesta tiene estado 200 con la prop de error "spotify_not_connected"
  And la página muestra "Connect Spotify to see this page." y el botón "Connect Spotify" de T-05
  And no se envía ninguna petición a Spotify

Scenario: Endpoint de discografía sin Spotify conectado
  Given el usuario con sesión iniciada y Spotify sin conectar
  When se pide el endpoint de discografía de "4tZwfgrHOc3mvqYlEYSvVi" con "Accept: application/json"
  Then la respuesta tiene estado 409 con el código de error "spotify_not_connected"
  And no se envía ninguna petición a Spotify

Scenario Outline: Un visitante sin sesión que abre una página de álbum o artista
  Given un visitante sin sesión iniciada
  When pide "<ruta>"
  Then se le redirige a "/login"
  And no se envía ninguna petición a Spotify

  Examples:
    | ruta                                      |
    | /spotify/albums/2noRn2Aes5aoNVsU6iWThc    |
    | /spotify/artists/4tZwfgrHOc3mvqYlEYSvVi   |

Scenario: Un visitante sin sesión que pide el endpoint de discografía
  Given un visitante sin sesión iniciada
  When pide el endpoint de discografía de "4tZwfgrHOc3mvqYlEYSvVi" con "Accept: application/json"
  Then la respuesta tiene estado 401
  And no se envía ninguna petición a Spotify
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- Una sola lista mezclada y sin duplicados para los dos servicios (pregunta abierta 6). Afecta poco a esta tarea, porque un álbum o un artista de Spotify son páginas de Spotify. La mezcla entre servicios va en T-22.
- Interfaz en inglés: los textos de pantalla de los escenarios están en inglés.

**Decisiones del lote de enriquecimiento (compartidas con otras tareas)**

- **Cliente HTTP de Spotify compartido (T-05):** timeout de 10 s, una única espera tras un `Retry-After` (5 s si falta la cabecera) y renovación del token con reintento tras un 401. T-24 no define su propio timeout ni su propia espera.
- **Tests de navegador:** Pest 4 browser tests (T-03).
- **Paginación:** botón "Load more" y un cursor opaco por sección, como en T-07 y T-08. T-03 no aporta un componente de lista: T-24 reutiliza la lista que construye T-07 y el formato común de álbum.
- **Errores en páginas y endpoints:**
  - Las páginas Inertia de álbum y artista muestran los errores de Spotify con estado 200 y una prop de error (`spotify_unavailable`, `spotify_not_connected` o `spotify_reconnect_required`), como T-08. Inertia trata el 409 como un estado de su protocolo.
  - Solo el endpoint JSON de discografía usa 409 (`spotify_not_connected` o `spotify_reconnect_required`), 503 (`spotify_unavailable`) y 422.
  - El error `not_connected` del cliente de T-05 se traduce a `spotify_not_connected`.
- **Reproducir:** la función de front-end de T-06 es `play({contextUri?, uris?, offset})`.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Rutas:**
  - Páginas Inertia `/spotify/albums/{id}` y `/spotify/artists/{id}`. El parámetro `{id}` está restringido a `[A-Za-z0-9]{22}`, así que un id mal formado da la página 404 sin llamar a Spotify.
  - Un endpoint JSON de discografía, por ejemplo `GET /spotify/artists/{id}/releases?group=albums|singles&cursor=`, que responde `{ items, next_cursor }` con el formato común de álbum de T-07.
  - Un cursor ilegible da 422.
- **Álbum entero hasta un máximo de 200 pistas.**
  - Los datos del álbum y sus 50 primeras pistas salen de `GET /albums/{id}`, que ya incluye la primera página de pistas. Las demás salen de `GET /albums/{id}/tracks` con `limit` 50 desde el offset 50, hasta 3 peticiones más.
  - El álbum no tiene "Load more": se ve entero o hasta el máximo.
  - Si hay más de 200 pistas, se muestra "Showing the first 200 of N tracks.". 200 cubre casi cualquier álbum o recopilatorio y limita a 4 las peticiones a Spotify por página.
- **Discografía del artista paginada:**
  - "Albums" (`include_groups=album`) y "Singles and EPs" (`include_groups=single`), de 10 en 10, que es el máximo de Spotify.
  - Cada sección tiene su "Load more", que se desactiva mientras carga.
  - Una sección vacía no se muestra. Si las dos están vacías, se muestra "No releases on Spotify.".
  - "Appears on" y los recopilatorios quedan fuera.
- **Reproducir:** "Play" reproduce el contexto `spotify:album:<id>` desde la posición 0. Pulsar una pista reproduce el mismo contexto con `offset` igual al índice global de la pista en el álbum, empezando en 0 y contando todos los discos en orden. Es lo que Spotify espera en `offset.position`. Por ejemplo, la pista 1 del disco 2 de un álbum con 10 pistas en el disco 1 tiene offset 10.
- **Disponibilidad:** se envía `market=from_token`. Una pista con `is_playable: false` se muestra con el texto genérico "Not available" (el mismo criterio que T-07). Si no hay ninguna pista reproducible, "Play" se desactiva.
- **Errores:**
  - **404 de Spotify:** página 404 de la app.
  - **400 de Spotify:** si un id pasa la restricción de la ruta pero Spotify lo rechaza, también se muestra la página 404 de la app, sin el mensaje de Spotify.
  - **429, 5xx, timeout o error de conexión en una página:** estado 200 con la prop `spotify_unavailable`, el mensaje y el botón "Try again", que recarga la página.
  - **Los mismos errores en el endpoint de discografía:** 503 `spotify_unavailable`.

**Restricciones descubiertas**

- **Sin canciones más populares:** `GET /artists/{id}/top-tracks` se eliminó en el development mode en febrero de 2026, sin sustituto. La página de artista no puede mostrar "sus canciones más populares", como decía el borrador de T-07. Además, `popularity` ya no viene en canciones, álbumes ni artistas. Fuente: [February 2026 Migration Guide](https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide).
- **Discografía:** `GET /artists/{id}/albums` sigue disponible, con `include_groups` = `album`, `single`, `appears_on`, `compilation` y `limit` de 1 a 10. Fuente: [Get Artist's Albums](https://developer.spotify.com/documentation/web-api/reference/get-an-artists-albums).
- **Pistas de un álbum:** `GET /albums/{id}/tracks` admite `limit` de 1 a 50. Las pistas pueden traer `restrictions.reason` (`market`, `product` o `explicit`). Fuente: [Get Album Tracks](https://developer.spotify.com/documentation/web-api/reference/get-an-albums-tracks).
- **Sin peticiones en lote:** las peticiones en lote (`GET /albums`, `GET /artists`) se eliminaron, así que cada álbum o artista se pide por separado (misma guía de migración).

**Casos límite**

- Álbumes de varios discos: la numeración se muestra por disco (`disc_number` y `track_number`) y el `offset` para reproducir es el índice global en el álbum, empezando en 0.
- Un álbum o artista sin imagen muestra la carátula genérica `AeroCover` de T-21 (`data-aero="cover-fallback"`). T-24 depende de T-21 a través de T-06.
- Si falla una de las peticiones de pistas intermedias (por ejemplo, la segunda de tres), la página entera se muestra con la prop `spotify_unavailable`. No se muestra un álbum a medias.
- Si la espera del `Retry-After` de T-05 está activa por otra función, la página se muestra con la prop `spotify_unavailable` sin llamar a Spotify.
- Un artista con cientos de singles se pagina de 10 en 10, sin cargar todo de golpe.

**Pistas para los tests**

- **Falsear Spotify en Pest:**
  - `Http::fake()` con patrones `api.spotify.com/v1/albums/*`, `api.spotify.com/v1/albums/*/tracks*`, `api.spotify.com/v1/artists/*` y `api.spotify.com/v1/artists/*/albums*`.
  - Los fixtures van en `tests/Fixtures/spotify/` con ids base62 de 22 caracteres.
  - Los álbumes de 60 y 250 pistas se generan con un helper que crea N pistas. El fixture de `GET /albums/{id}` incluye las 50 primeras.
  - `Http::preventStrayRequests()`, y `Http::assertSent` para comprobar `limit`, `offset`, `include_groups` y `market`.
  - `Http::assertSentCount(0)` para comprobar que no se llamó a Spotify, también en los ids mal formados.
- **Respuestas Inertia:** `assertOk()` y `assertInertia` para el componente y sus props, incluida la prop de error. `assertStatus(404)` y `assertDontSee` para el mensaje de Spotify.
- **Navegador (Pest 4 browser tests, T-03):** el doble clic en "Load more" y un espía sobre la función de front-end de T-06 `play({contextUri?, uris?, offset})`, comprobando `contextUri` y `offset`. Que el audio suena de verdad se comprueba a mano.

**Fuera de alcance**

- La búsqueda (T-07), la cola (T-13), las playlists propias (T-14) y la biblioteca (T-08).
- Páginas de álbum y artista de YouTube Music: quedan aplazadas y ninguna tarea las tiene todavía (ver "Pendiente").
- Las secciones "Appears on", recopilatorios y artistas relacionados.
- Seguir a un artista o guardar un álbum en la biblioteca de Spotify.

**Pendiente (no decidido)**

- **Sección de canciones en la página de artista.** Una opción es buscar `artist:"<nombre>"` con `type=track` y quedarse con las canciones de ese id de artista. El orden sería por relevancia, no por popularidad. Hay que confirmarlo con el usuario. Mientras tanto, la página solo muestra la discografía.
- **El máximo de 200 pistas por álbum** es una propuesta. El usuario puede preferir otro número.
- **Páginas de álbum y artista de YouTube Music:** aplazadas, sin tarea asignada. Hay que decidir si van en T-11 o en una tarea nueva.

**Estimación: 2 puntos.**
- **Página de álbum (0,75 puntos):** reutilizar la primera página de pistas, cargar el resto hasta el máximo y reproducir con contexto y offset global.
- **Página de artista (0,75 puntos):** dos secciones con "Load more" y cursor opaco, reutilizando la lista de T-07.
- **Errores, accesos sin sesión o sin conexión y tests (0,5 puntos):** casi todo pasa por el cliente compartido de T-05.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
