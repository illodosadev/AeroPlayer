---
id: T-07
title: Buscar y reproducir en Spotify
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 3.5
language: es
---

# T-07 — Buscar y reproducir en Spotify

## Goal

Buscar canciones, álbumes y artistas del catálogo de Spotify desde el reproductor y reproducir canciones desde los resultados. Así el usuario encuentra cualquier música sin abrir la app oficial, que es la condición para cumplir el objetivo de "uso diario" del discovery.

La búsqueda se construye desde el principio como **una sola lista de resultados, independiente del servicio**. Hoy solo la alimenta Spotify, pero YouTube Music (T-11) se añadirá como un segundo origen sin rehacer la interfaz ni el contrato del endpoint.

## Context

- Necesidad v1 del discovery: "buscar canciones, álbumes y artistas y reproducirlos".
- **Depende de:**
  - **T-05**, que aporta la cuenta conectada y el cliente HTTP de Spotify compartido. Ese cliente tiene un timeout de 10 s, una única espera tras un `Retry-After` compartida por todas las funciones (5 s si falta la cabecera) y renueva el token y reintenta tras un 401.
  - **T-06**, que aporta la función para reproducir un URI de Spotify en la pestaña.
  - **T-02**, porque solo el usuario con sesión iniciada puede buscar.
  - **T-03**, que aporta el estilo y el runner de tests de navegador. T-03 no aporta un componente de lista: cada tarea construye sus listas. T-07 construye la lista única de búsqueda, y T-08 (biblioteca) reutiliza el mismo formato de elementos.
  - **T-21**, que aporta la carátula genérica `AeroCover` (`data-aero="cover-fallback"`). T-07 ya depende de ella a través de T-06, que depende de T-21.
- **T-24** (páginas de álbum y de artista) depende de esta tarea. T-07 muestra los álbumes y artistas en los resultados, y T-24 los convierte en enlaces a sus páginas.
- La pregunta abierta 6 quedó resuelta el 2026-11-09: los resultados de búsqueda y las bibliotecas de los dos servicios se muestran en una sola lista mezclada y sin duplicados. La mezcla y la eliminación de duplicados entre servicios van en **T-22**, porque no se pueden probar hasta que exista la búsqueda de YouTube Music (T-11).
- Puntos de entrada para otras tareas: cada fila de canción es donde T-13 pondrá "Add to queue" y T-14 "Add to playlist". T-07 no implementa esas acciones.
- Las condiciones del "development mode" de Spotify cambiaron en febrero de 2026 (pregunta abierta 10). Para esta tarea, el cambio importante es que la búsqueda devuelve como máximo 10 resultados por petición (ver "Restricciones descubiertas").

## Acceptance criteria

```gherkin
# --- Buscar ---------------------------------------------------------------------

Scenario: Buscar en Spotify
  Given el usuario con sesión iniciada y Spotify conectado
  And la API de Spotify (falseada) devuelve para "Daft Punk" 3 canciones, 2 álbumes y 1 artista
  When el usuario escribe "Daft Punk" en el campo "Search songs, albums and artists"
  Then se envía a Spotify una sola petición GET "/v1/search" con q "Daft Punk", type "track,album,artist", limit 10, offset 0 y market "from_token"
  And la página muestra las secciones "Songs" con 3 filas, "Albums" con 2 y "Artists" con 1, en el orden en que las devuelve Spotify

Scenario: Enter busca de inmediato
  Given el usuario en la página de búsqueda con Spotify conectado
  When escribe "Daft Punk" y pulsa Enter antes de que pasen 300 ms
  Then se envía la búsqueda de "Daft Punk" sin esperar los 300 ms
  And no se envía una segunda búsqueda de "Daft Punk" cuando pasan los 300 ms

Scenario: Cada canción muestra sus datos y su servicio de origen
  Given resultados de búsqueda con la canción "One More Time" de "Daft Punk", del álbum "Discovery" y con una duración de 320000 ms
  When se muestran los resultados
  Then su fila muestra "One More Time", "Daft Punk", "Discovery", "5:20", la carátula del álbum y la etiqueta "Spotify"

# --- Formato del endpoint de resultados -----------------------------------------

Scenario: Las canciones tienen el formato común de la app
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide al endpoint de resultados la sección "songs" de "Daft Punk" con "Accept: application/json"
  Then cada elemento de "items" tiene exactamente las claves source, id, uri, title, artists, album, duration_ms, image y playable
  And "source" vale "spotify", "artists" es una lista de nombres, "album" es el título del álbum e "image" es una URL o null
  And ningún elemento tiene las claves de Spotify name, images, href, external_urls, is_playable ni album.images

Scenario: Los álbumes tienen el formato común de la app
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide al endpoint de resultados la sección "albums" de "Daft Punk"
  Then cada elemento de "items" tiene exactamente las claves source, id, uri, title, artists, year e image
  And ningún elemento tiene las claves de Spotify name, images, href, release_date ni total_tracks

Scenario: Los artistas tienen el formato común de la app
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide al endpoint de resultados la sección "artists" de "Daft Punk"
  Then cada elemento de "items" tiene exactamente las claves source, id, uri, title e image
  And ningún elemento tiene las claves de Spotify name, images, href, genres ni followers

# --- Consulta: espacios, caracteres especiales, vacía y demasiado larga --------

Scenario: Los espacios del principio y del final no cuentan
  Given el usuario con sesión iniciada y Spotify conectado
  When el usuario busca "   Daft Punk   "
  Then la petición a Spotify lleva q "Daft Punk"

Scenario: Caracteres especiales
  Given el usuario con sesión iniciada y Spotify conectado
  When el usuario busca 'AC/DC & "Back in Black" #1 Beyoncé'
  Then la petición a Spotify lleva, una vez decodificada, q exactamente 'AC/DC & "Back in Black" #1 Beyoncé'
  And la página muestra los resultados sin error

Scenario: Campo de búsqueda vacío
  Given el usuario en la página de búsqueda
  When el campo está vacío o solo contiene espacios
  Then no se envía ninguna petición a Spotify
  And la página muestra "Type to search your music."

Scenario: El endpoint rechaza una consulta vacía
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide el endpoint de resultados con q "   "
  Then la respuesta tiene estado 422
  And no se envía ninguna petición a Spotify

Scenario: Consulta demasiado larga
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide el endpoint de resultados con un q de 201 caracteres
  Then la respuesta tiene estado 422
  And no se envía ninguna petición a Spotify
  And el campo de búsqueda de la página no admite más de 200 caracteres

# --- Escribir rápido ----------------------------------------------------------

Scenario: Solo se busca cuando el usuario deja de escribir
  Given el usuario en la página de búsqueda con Spotify conectado
  When escribe "Daft Punk" letra a letra, con menos de 300 ms entre pulsaciones
  Then no se envía ninguna búsqueda mientras escribe
  And 300 ms después de la última pulsación se envía una sola búsqueda, con q "Daft Punk"

Scenario: Una respuesta antigua no sustituye a la nueva
  Given el usuario busca "Daft" y su respuesta tarda más que la siguiente
  When después busca "Daft Punk" y la respuesta de "Daft Punk" llega antes que la de "Daft"
  Then la página muestra los resultados de "Daft Punk"
  And cuando llega la respuesta de "Daft", la página sigue mostrando los resultados de "Daft Punk"

# --- Cargar más (cursor opaco) --------------------------------------------------

Scenario: Cargar más canciones
  Given la búsqueda "Daft Punk" con 25 canciones en total según Spotify y las 10 primeras en pantalla
  When el usuario pulsa "Load more" en la sección "Songs"
  Then la app pide al endpoint de resultados la sección "songs" con el "next_cursor" de la respuesta anterior
  And se envía a Spotify una petición con type "track", limit 10 y offset 10
  And la sección "Songs" muestra 20 canciones
  When vuelve a pulsar "Load more" en la sección "Songs"
  Then la sección "Songs" muestra 25 canciones
  And la sección "Songs" ya no muestra "Load more"

Scenario: El endpoint devuelve un cursor opaco
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide al endpoint de resultados la sección "songs" de "Daft Punk", que tiene 25 canciones en Spotify
  Then la respuesta tiene "next_cursor" como texto no vacío y no tiene las claves "offset" ni "next" de Spotify
  When se pide la tercera página y Spotify devuelve "next" null
  Then la respuesta tiene "next_cursor" null

Scenario Outline: Un cursor no válido se rechaza
  Given el usuario con sesión iniciada y Spotify conectado
  When se pide al endpoint de resultados la sección "songs" con <cursor>
  Then la respuesta tiene estado 422
  And no se envía ninguna petición a Spotify

  Examples:
    | cursor                                                                  |
    | el texto "no-es-un-cursor"                                              |
    | un cursor bien formado al que se le ha cambiado un carácter de la firma |
    | un cursor firmado por la app con offset 1010 (por encima de 1000)       |

Scenario: Un doble clic en "Load more" envía una sola petición
  Given la sección "Songs" con 10 canciones y más resultados en Spotify
  When el usuario pulsa dos veces seguidas "Load more" en la sección "Songs"
  Then se envía a Spotify una sola petición con offset 10
  And la sección "Songs" muestra 20 canciones, sin repetidas

Scenario: Una canción repetida entre páginas sale una sola vez
  Given la sección "Songs" con 10 canciones, entre ellas la de id "4uLU6hMCjMI75M1A2tKUQC"
  When el usuario pulsa "Load more" en la sección "Songs" y la segunda página de Spotify vuelve a traer "4uLU6hMCjMI75M1A2tKUQC"
  Then esa canción aparece una sola vez en la sección "Songs"

Scenario: Falla "Load more"
  Given la sección "Songs" con 10 canciones y más resultados en Spotify
  And la API de Spotify responde 503 a la siguiente petición
  When el usuario pulsa "Load more" en la sección "Songs"
  Then el endpoint de resultados responde 503 con el código de error "spotify_unavailable"
  And la sección "Songs" sigue mostrando las 10 canciones
  And la sección muestra "Spotify search isn't available right now. Try again in a moment."
  And "Load more" sigue visible y activo
  When la API de Spotify vuelve a responder bien y el usuario pulsa otra vez "Load more"
  Then la sección "Songs" muestra 20 canciones

Scenario: Sin más resultados que cargar
  Given la búsqueda "Daft Punk" con 2 álbumes en total según Spotify
  When se muestran los resultados
  Then la sección "Albums" no muestra "Load more"

# --- Sin resultados, sin conexión y sin sesión ---------------------------------

Scenario: Búsqueda sin resultados
  Given el usuario con sesión iniciada y Spotify conectado
  And la API de Spotify devuelve 0 canciones, 0 álbumes y 0 artistas para "zzqxjkwvvy"
  When el usuario busca "zzqxjkwvvy"
  Then la página muestra 'No results for "zzqxjkwvvy"'
  And no muestra las secciones "Songs", "Albums" ni "Artists"

Scenario: Buscar sin Spotify conectado
  Given el usuario con sesión iniciada y Spotify sin conectar
  When abre la página de búsqueda
  Then la respuesta de "/search" tiene estado 200 y la prop de error "spotify_not_connected"
  And ve "Connect Spotify to search its catalog." y el botón "Connect Spotify" de T-05
  When se pide el endpoint de resultados de "Daft Punk"
  Then la respuesta tiene estado 409 con el código de error "spotify_not_connected"
  And no se envía ninguna petición a Spotify

Scenario: Un visitante sin sesión que abre la página de búsqueda
  Given un visitante sin sesión iniciada
  When pide "/search"
  Then se le redirige a "/login"
  And no se envía ninguna petición a Spotify

Scenario: Un visitante sin sesión que pide el endpoint de resultados
  Given un visitante sin sesión iniciada
  When pide el endpoint de resultados de "Daft Punk" con "Accept: application/json"
  Then la respuesta tiene estado 401
  And no se envía ninguna petición a Spotify

# --- Reproducir desde los resultados ------------------------------------------

Scenario: Reproducir una canción desde los resultados
  Given resultados de búsqueda con la canción de URI "spotify:track:0DiWol3AO6WpXZgp0goxAV"
  When el usuario pulsa esa canción
  Then el espía sobre la función de front-end de T-06 `play({contextUri?, uris?, offset})` registra una sola llamada, con uris ["spotify:track:0DiWol3AO6WpXZgp0goxAV"] y sin contextUri

Scenario: Canción no disponible
  Given resultados de búsqueda con la canción de id "6DCZcSspjsKoFjzjrWoCdn", que Spotify marca con "is_playable" false
  When se muestran los resultados
  Then su fila aparece atenuada con el texto "Not available"
  And su elemento en el endpoint tiene "playable" false
  And al pulsarla, el espía sobre `play` de T-06 no registra ninguna llamada

# --- Errores de Spotify (a través del cliente compartido de T-05) ---------------

Scenario: El token caducado no interrumpe la búsqueda
  Given Spotify conectado y el access token guardado caducó hace 1 minuto
  When el usuario busca "Daft Punk"
  Then la página muestra los resultados de "Daft Punk"
  And no muestra "Reconnect Spotify"

Scenario: Acceso a Spotify revocado
  Given Spotify conectado y el cliente de T-05 indica que el acceso fue revocado (la renovación responde "invalid_grant")
  When el usuario busca "Daft Punk"
  Then la página muestra "Spotify access was revoked." y el botón "Reconnect Spotify" de T-05
  And la app no muestra una página de error

Scenario: Spotify limita las peticiones
  Given Spotify conectado y la API de Spotify responde 429 con la cabecera "Retry-After: 30"
  When el usuario busca "Daft Punk"
  Then el endpoint de resultados responde 503 con el código de error "spotify_unavailable"
  And la página muestra "Spotify search isn't available right now. Try again in a moment."
  And la app no muestra una página de error
  When el usuario busca "Justice" 10 segundos después
  Then no se envía ninguna petición a Spotify y la página muestra el mismo mensaje
  When el usuario busca "Justice" 31 segundos después del 429
  Then se envía la petición a Spotify

Scenario: Error del servidor de Spotify
  Given Spotify conectado y la API de Spotify responde 503
  When el usuario busca "Daft Punk"
  Then el endpoint de resultados responde 503 con el código de error "spotify_unavailable"
  And la página muestra "Spotify search isn't available right now. Try again in a moment."
  And la app no muestra una página de error

Scenario: Spotify no responde a tiempo
  Given Spotify conectado y la API de Spotify no responde antes del timeout del cliente de T-05
  When el usuario busca "Daft Punk"
  Then el endpoint de resultados responde 503 con el código de error "spotify_unavailable"
  And la página muestra "Spotify search isn't available right now. Try again in a moment."
  And la app no muestra una página de error
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **Una sola lista, mezclada y sin duplicados** (resuelve la pregunta abierta 6). Los resultados de búsqueda y las bibliotecas de Spotify y YouTube Music se muestran juntos. Si la misma canción está en los dos servicios, sale una vez.
- **Consecuencia para T-07:** esta tarea solo cubre Spotify, porque YouTube Music son T-09 a T-12. T-07 construye la interfaz de búsqueda como esa lista única, hoy alimentada solo por Spotify, para que los resultados de YouTube Music se sumen después sin rehacerla. La mezcla y la eliminación de duplicados entre servicios van en T-22.
- Interfaz en inglés (T-01): los textos de pantalla de los escenarios están en inglés.

**Decisiones del lote de enriquecimiento (compartidas con otras tareas)**

- **Cliente HTTP de Spotify compartido (T-05):**
  - Timeout de 10 s.
  - Una única espera tras un `Retry-After`, compartida por todas las funciones (5 s si falta la cabecera).
  - Renueva el token y reintenta tras un 401.
  - T-07 no define su propio timeout ni su propia espera. Que un 429 sin `Retry-After` espere 5 s se prueba en T-05.
- **Runner de tests de navegador (T-03):** tests de navegador de Pest 4 (Playwright con Chromium).
- **Botón de paginación:** "Load more" en cada sección, el mismo texto que usa T-08. La lista de búsqueda la construye T-07, y T-08 reutiliza el formato de sus elementos.
- **Errores en páginas y endpoints:**
  - Las páginas Inertia (como `/search`) muestran los errores de Spotify con estado 200 y una prop de error (`spotify_unavailable`, `spotify_not_connected` o `spotify_reconnect_required`), como T-08. Inertia trata el 409 como un estado de su protocolo.
  - Los endpoints JSON mantienen 409 (`spotify_not_connected`), 503 (`spotify_unavailable`) y 422.
  - El error `not_connected` del cliente de T-05 se traduce a `spotify_not_connected`.
- **Reproducir:** la función de front-end de T-06 es `play({contextUri?, uris?, offset})`. Una canción suelta se reproduce con `uris: [trackUri]`.
- **Mezcla y duplicados entre servicios:** van en T-22.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Claves extra de T-08 sobre este formato** (añadido al aplicar los cambios del lote el 2026-11-09):
  - Las canciones de una playlist añaden `position` y `local`.
  - Las canciones guardadas añaden `added_at`.
  - Las playlists tienen su propia forma: `source, id, uri, title, image, owner, collaborative, can_list_tracks, total`.

  Las claves comunes de este formato no cambian.

- **Formato común de resultados.** Los nombres de los campos son de la app, no de Spotify.
  - Canción: `source`, `id`, `uri`, `title`, `artists` (lista de nombres), `album` (título), `duration_ms`, `image` (URL o null) y `playable`.
  - Álbum: `source`, `id`, `uri`, `title`, `artists`, `year` e `image`.
  - Artista: `source`, `id`, `uri`, `title` e `image`.
  - Si T-24 o T-14 necesitan más datos (por ejemplo, el id del álbum de una canción), se añaden como claves nuevas en un cambio posterior.
- **La búsqueda pasa por un punto que consulta cada servicio conectado** y junta sus resultados. En T-07 solo hay un servicio, Spotify. T-11 añade el segundo sin tocar los componentes de la lista ni el contrato.
- **Rutas:**
  - Una página Inertia `/search`.
  - Un endpoint JSON de resultados (por ejemplo, `GET /search/results?q=&section=songs|albums|artists&cursor=`) que responde `{ items, next_cursor }` por sección.
  - La primera carga sin `section` devuelve las tres secciones.
  - El endpoint llama a Spotify desde el servidor con el cliente de T-05, así que el token nunca llega al navegador.
- **Cursor opaco por sección:**
  - El endpoint no expone el `offset` de Spotify, sino un `cursor` opaco (por ejemplo, un JSON firmado o cifrado con el offset de cada servicio). Así podrá servir una lista mezclada en T-22.
  - Da 422 un cursor que no se puede leer, uno cuya firma no coincide o uno con un offset por encima de 1000.
  - `next_cursor` es null cuando Spotify devuelve `next: null` o el offset llegaría a 1000, que es el máximo de la API.
  - T-08 usa el mismo tipo de cursor.
- **10 resultados por sección y página**, que es el máximo actual de Spotify. Al añadir una página, se descartan los ids que ya están en pantalla. "Load more" se desactiva mientras carga.
- **Escritura:** debounce de 300 ms. Enter busca de inmediato y cancela el debounce pendiente. Se cancela la petición anterior con AbortController y, por si acaso, se descarta cualquier respuesta que no corresponda a la última consulta.
- **Consulta:**
  - Se quitan los espacios del principio y del final, y una consulta vacía no se envía.
  - El máximo es de 200 caracteres: el campo tiene `maxlength` y el servidor valida `required|string|max:200` después de quitar los espacios.
  - La consulta se envía tal cual, sin escapar, así que los filtros de Spotify (`artist:`, `year:`…) funcionan si el usuario los escribe.
- **Disponibilidad:**
  - Se envía `market=from_token`, así que Spotify usa el país de la cuenta.
  - Una canción con `is_playable: false` se muestra atenuada con el texto genérico **"Not available"**, sea cual sea `restrictions.reason` (`market`, `product` o `explicit`). Se elige el texto genérico porque las razones no se pueden probar de forma fiable y el usuario solo necesita saber que no suena.
  - Si `is_playable` falta, la canción se considera reproducible.
- **Reproducir una canción desde los resultados** reproduce solo esa canción. Qué suena después lo decide T-13 (la cola).
- **Errores:**
  - **429, 5xx, timeout o error de conexión:** el endpoint responde 503 con `spotify_unavailable`. En una búsqueda nueva se vacían los resultados anteriores, para no mezclarlos con la consulta nueva. Si lo que falla es "Load more", las filas ya cargadas se quedan y el botón sigue activo.
  - **Acceso revocado:** se muestra el estado de reconexión de T-05.
  - **No hay reintento automático:** la siguiente búsqueda del usuario ya es el reintento.
- **Sin caché de resultados.** Cada búsqueda va a Spotify. Se revisará si el límite de peticiones se convierte en un problema.

**Restricciones descubiertas**

- **Endpoint de búsqueda:**
  - `GET /v1/search` acepta `type` = `album`, `artist`, `playlist`, `track`, `show`, `episode`, `audiobook`.
  - `limit` va de 0 a 10 (por defecto 5) y `offset` de 0 a 1000.
  - Si la petición lleva un token de usuario, manda el país de su cuenta.
  - Cada tipo devuelve un objeto paginado con `total`, `next` e `items`.
  - `is_playable` aparece cuando se pasa `market`, y `restrictions.reason` puede ser `market`, `product` o `explicit`.
  - La documentación no indica una longitud máxima para `q`.
  - Fuente: [Spotify Web API — Search](https://developer.spotify.com/documentation/web-api/reference/search).
- **Cambios del development mode (febrero de 2026):**
  - El `limit` máximo de la búsqueda baja de 50 a 10 y el valor por defecto de 20 a 5, así que hay que paginar.
  - Se quita `popularity` de canciones, álbumes y artistas, y `available_markets` y `linked_from` de las canciones.
  - Se eliminan las peticiones en lote y `GET /artists/{id}/top-tracks` (afecta a T-24).
  - Las apps de desarrollo admiten como máximo 5 usuarios y el propietario necesita Premium.
  - Según la guía, `external_ids` (que incluye el ISRC) se volvió a añadir en marzo de 2026.
  - Los cambios se aplicaron a las apps nuevas el 11 de febrero de 2026 y a las existentes el 9 de marzo de 2026.
  - Fuentes: [February 2026 Migration Guide](https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide), [Update on Developer Access and Platform Security](https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security).
- **Límite de peticiones:** Spotify cuenta las peticiones en una ventana móvil de 30 segundos y devuelve 429, normalmente con `Retry-After` en segundos. En development mode el límite es más bajo. Fuente: [Rate limits](https://developer.spotify.com/documentation/web-api/concepts/rate-limits).

**Casos límite**

- Si el campo se vacía, la pantalla vuelve al estado inicial ("Type to search your music.") y se cancela la petición en curso. Si llega una respuesta después, se descarta.
- Una canción con varios artistas los muestra separados por comas, en el orden de Spotify.
- Si una canción, un álbum o un artista no tienen imagen (`images` vacío), `image` es null y se muestra la carátula genérica `AeroCover` de T-21 (`data-aero="cover-fallback"`).
- Si una sección tiene 0 resultados pero otras no, esa sección no se muestra. Solo cuando las tres están vacías aparece 'No results for "…"'.
- Si "Load more" falla (429 o 5xx), las filas ya cargadas se quedan, la sección muestra el mensaje de "no disponible" y "Load more" sigue disponible.
- Un usuario que no está en la lista de usuarios de la app en development mode recibe 403 de Spotify. Por ahora se trata como `spotify_unavailable` (ver "Pendiente").
- Mientras dura la espera del `Retry-After` de T-05, la búsqueda tampoco llama a Spotify, aunque el 429 lo haya provocado otra función (por ejemplo, la biblioteca).

**Pistas para los tests**

- **Falsear Spotify en Pest:**
  - `Http::fake()` con patrones por URL (`api.spotify.com/v1/search*`).
  - Las respuestas JSON se guardan en `tests/Fixtures/spotify/` copiadas del formato de la documentación, con ids base62 de 22 caracteres.
  - `Http::preventStrayRequests()` garantiza que ningún test llama a la API real.
  - Los parámetros enviados se comprueban con `Http::assertSent`. Que no se llamó a Spotify se comprueba con `Http::assertSentCount(0)`.
- **Credencial de Spotify:** se crea con la factory de T-05. Para el token caducado, con `expires_at` en el pasado y el fake del endpoint de token de T-05 respondiendo bien. Este test solo comprueba el resultado de la búsqueda, no la renovación (eso es de T-05).
- **429:** `Http::response([...], 429, ['Retry-After' => 30])`, y `$this->travel(10)->seconds()` y `$this->travel(31)->seconds()` para la espera. **Timeout:** un callback de `Http::fake` que lanza `ConnectionException`.
- **Formato común:** `assertExactJsonStructure` (o comparar `array_keys` de cada elemento) y `assertJsonMissingPath` para las claves de Spotify.
- **Cursor:** se pide la primera página, se reenvía el `next_cursor` recibido y se comprueba con `Http::assertSent` que Spotify recibió `offset=10`.
- **Navegador (Pest 4 browser tests, T-03):**
  - Debounce y Enter: `typeSlowly` y contar las peticiones con `Http::assertSentCount`.
  - Respuestas fuera de orden: un callback de `Http::fake` que tarda más para q "Daft".
  - El doble clic en "Load more".
  - `maxlength`.
  - Las filas atenuadas.
  - Se pone un espía sobre la función de front-end de T-06 `play({contextUri?, uris?, offset})` y se comprueban sus argumentos (`uris: [trackUri]` para una canción).
- **Manual:** que el audio suena de verdad y que la barra de reproductor muestra la canción se comprueba a mano en el navegador. Es responsabilidad de T-06.

**Fuera de alcance**

- **Mezclar y quitar duplicados entre Spotify y YouTube Music**, el criterio de "misma canción" y qué servicio la reproduce: T-22.
- **Páginas de álbum y de artista:** T-24.
- Buscar playlists, podcasts, episodios o audiolibros. Solo canciones, álbumes y artistas, como pide el discovery.
- Las acciones "Add to queue" (T-13) y "Add to playlist" (T-14).
- La biblioteca de Spotify (T-08), conectar la cuenta y el cliente HTTP compartido (T-05).
- Historial de búsquedas, sugerencias mientras se escribe y filtros visuales por año o género.

**Pendiente (no decidido)**

- **Criterio de "misma canción"** y qué servicio reproduce una canción que está en los dos. Se decide en T-22.
- **Mensaje para el 403** de development mode (usuario no registrado en la app de Spotify). ¿Basta `spotify_unavailable` o hace falta un mensaje propio?
- Cuando haya dos servicios, ¿qué etiqueta lleva una canción que está en los dos? Se decide en T-22.

**Estimación: 3,5 puntos.**
- **Endpoint de resultados, formato común, cursor opaco y validación (1,25 puntos):** el contrato tiene que servir para la lista mezclada de T-22.
- **Página de búsqueda (1,5 puntos):** debounce, Enter, respuestas fuera de orden, "Load more" sin repetidos y sin doble petición. Es la lógica de frontend más delicada.
- **Sin conexión, sin sesión y errores (0,25 puntos):** todos pasan por el cliente compartido de T-05.
- **Fixtures, tests de Pest y de navegador (0,5 puntos).**

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
