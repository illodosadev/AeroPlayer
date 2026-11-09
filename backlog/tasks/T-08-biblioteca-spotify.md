---
id: T-08
title: Biblioteca de Spotify
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 5
language: es
---

# T-08 — Biblioteca de Spotify

## Goal

Ver y reproducir desde el reproductor las playlists y las canciones guardadas ("Liked Songs") del usuario en Spotify, para acceder a su música habitual sin abrir la app oficial. La biblioteca se construye desde el principio como **una sola vista unificada**, que hoy solo alimenta Spotify. Así, cuando llegue YouTube Music (T-12), sus elementos se suman a la misma lista sin rehacer la interfaz. Esto acerca la meta "biblioteca unificada" del discovery.

## Context

Necesidad v1 del discovery: "ver y reproducir mis playlists y canciones guardadas de cada servicio". Es la parte de Spotify de la biblioteca; la de YouTube Music es T-12.

- **Dependencias:**
  - **T-05:** la cuenta de Spotify conectada, los scopes concedidos y el **cliente compartido de la Web API de Spotify**. Ese cliente se encarga del timeout de 10 s, de la espera común tras un 429 (`Retry-After`, o 5 s si falta la cabecera) y de renovar el token ante un 401 (con bloqueo y un solo reintento). T-08 lo reutiliza: solo pone sus mensajes y su caché.
  - **T-06:** la reproducción con el Web Playback SDK. Su función de frontend es `play({contextUri?, uris?, offset})`, donde `offset` es `{position}` o `{uri}`. También ofrece un SDK falso para los tests.
  - **T-21 (separada de T-03):** la barra de reproductor y la carátula genérica. Se usa el estilo de T-03.
  - **T-03:** el runner de tests de navegador (Pest 4).
- **Formato de datos común:** las canciones usan el formato que define T-07. Las playlists usan una forma equivalente. Las claves exactas están en Reglas propuestas.
- **Otras tareas relacionadas:**
  - T-13 (cola editable, shuffle y repeat) y T-14 (playlists propias mixtas de la app) son cosas distintas. Las playlists de esta tarea son las del usuario en Spotify.
  - La mezcla y la deduplicación entre servicios van en **T-22** (ver Seguimiento).
- **Condiciones actuales de la API de Spotify (pregunta abierta 10):** desde el 9 de marzo de 2026, las apps en development mode solo pueden leer las canciones de las playlists que el usuario posee o en las que colabora. De las playlists que solo sigue, la API devuelve los datos de la playlist pero no sus canciones (ver Restricciones descubiertas).
- **Prerrequisito, un spike con la cuenta real antes de implementar.** Hay que confirmar dos cosas:
  - que una playlist que el usuario solo sigue se reproduce como contexto (`PUT /me/player/play` con `context_uri`);
  - que las canciones guardadas se reproducen pasando sus `uris`.

  Si alguna falla, se para y se revisa esta tarea antes de seguir.

Rutas propuestas (se pueden revisar en /opsx-propose):
- `/library`: la vista unificada;
- `/library/playlists/spotify/{id}`: el detalle de una playlist;
- `/library/liked`: las canciones guardadas.

El servicio va en la ruta de la playlist para que T-12 pueda añadir `/library/playlists/youtube-music/{id}` sin chocar.

## Acceptance criteria

```gherkin
# --- Acceso -------------------------------------------------------------------

Scenario: Un visitante sin sesión no ve la biblioteca
  Given un visitante sin sesión iniciada
  When pide "/library", "/library/liked" o "/library/playlists/spotify/road"
  Then cada petición se redirige a "/login"
  And no se envía ninguna petición a la API de Spotify

# --- Vista unificada de la biblioteca -----------------------------------------

Scenario: Ver mis playlists de Spotify en la biblioteca unificada
  Given Spotify conectado
  And la API de Spotify (falsa) devuelve 3 playlists: "Road Trip", "Chill" y "Gym", cada una con carátula
  And 12 canciones guardadas
  When el usuario abre "/library"
  Then la respuesta Inertia indica el componente "Library"
  And ve las 3 playlists en el orden en que las devuelve Spotify, cada una con su título, su carátula y la etiqueta "Spotify"
  And ve una única lista "Playlists" y una entrada "Liked Songs" con el texto "12 songs", sin pestañas por servicio

Scenario: Las playlists usan el formato común de la app
  Given Spotify conectado y la API de Spotify (falsa) con las playlists "Road Trip" y "Chill"
  When se pide "/library"
  Then las claves de cada playlist en las props de Inertia son exactamente: source, id, uri, title, image, owner, collaborative, can_list_tracks, total
  And "source" vale "spotify" en las dos

Scenario: Se ven todas las playlists aunque haya muchas
  Given Spotify conectado y la API de Spotify (falsa) con 120 playlists repartidas en páginas de 50, 50 y 20
  When el usuario abre "/library"
  Then ve las 120 playlists
  And la API falsa ha recibido 3 peticiones a "/me/playlists" con limit=50 y offset 0, 50 y 100
  And 1 petición a "/me/tracks" con limit=1

Scenario: Playlist sin carátula
  Given Spotify conectado y una playlist "Untitled Mix" cuyo campo "images" está vacío
  When el usuario abre "/library"
  Then la playlist "Untitled Mix" tiene "image" a null en las props de Inertia
  And en la página se muestra con la carátula genérica de T-21

Scenario: Playlist que sigue el usuario pero es de otra persona
  Given Spotify conectado y una playlist "Indie Picks" cuyo propietario es "Alice", no colaborativa y sin el campo "items"
  When el usuario abre "/library"
  Then "Indie Picks" aparece en la lista con el texto "by Alice", sin número de canciones
  And en las props tiene "can_list_tracks" a false y "total" a null

Scenario: Playlist colaborativa
  Given Spotify conectado y una playlist colaborativa "Office Jams" de otro usuario en la que el usuario colabora
  When el usuario abre "/library"
  Then "Office Jams" aparece con la marca "Collaborative"
  And en las props tiene "can_list_tracks" a true

# --- Contenido de una playlist ------------------------------------------------

Scenario: Ver las canciones de una playlist propia
  Given Spotify conectado y la playlist propia "Road Trip" con las canciones "Song A" (3:45), "Song B" (4:02) y "Song C" (2:58)
  When el usuario abre "Road Trip"
  Then ve las 3 canciones en ese orden, cada una con título, artistas, álbum y duración en formato "m:ss"
  And ve el total "3 songs"
  And las claves de cada canción en las props de Inertia son exactamente: source, id, uri, title, artists, album, duration_ms, image, playable, position, local

Scenario: Playlist con muchas canciones
  Given Spotify conectado y una playlist propia con 230 canciones
  When el usuario abre la playlist
  Then ve las primeras 50 canciones, el total "230 songs" y el botón "Load more"
  And al pulsar "Load more" ve 100 canciones
  And la API falsa ha recibido peticiones a "/playlists/{id}/items" con limit=50 y offset 0 y 50

Scenario: No se pueden ver las canciones de una playlist que solo se sigue
  Given Spotify conectado y la playlist "Indie Picks" de "Alice", que el usuario solo sigue
  When el usuario abre "Indie Picks"
  Then ve el mensaje "Spotify doesn't let this app list the songs of playlists you don't own."
  And ve el botón "Play" activado
  And no se envía ninguna petición a "/playlists/{id}/items"
  And la respuesta tiene estado 200, sin página de error

Scenario: Playlist vacía
  Given Spotify conectado y la playlist propia "New Ideas" sin canciones
  When el usuario abre "New Ideas"
  Then ve el mensaje "This playlist is empty."
  And el botón "Play" está desactivado

Scenario: Archivo local dentro de una playlist
  Given Spotify conectado y la playlist propia "Road Trip" con un elemento marcado como archivo local ("is_local": true) titulado "My Demo"
  When el usuario abre "Road Trip"
  Then "My Demo" aparece atenuado con el texto "Local file, can't play here"
  And en las props tiene "local" a true y "playable" a false
  And al pulsar "My Demo" Spotify no recibe ninguna petición PUT /v1/me/player/play

Scenario: Canción no disponible
  Given Spotify conectado y la playlist propia "Road Trip" con la canción "Gone Song" marcada como no reproducible ("is_playable": false)
  When el usuario abre "Road Trip"
  Then "Gone Song" aparece atenuada con el texto "Not available"
  And al pulsar "Gone Song" Spotify no recibe ninguna petición PUT /v1/me/player/play

Scenario: Canción eliminada de Spotify
  Given Spotify conectado y la playlist propia "Road Trip", cuya API (falsa) devuelve "total": 3 y 3 elementos, uno de ellos con "item": null
  When el usuario abre "Road Trip"
  Then ve solo las 2 canciones con datos
  And ve el total "3 songs", que es el total de Spotify e incluye el elemento oculto
  And la página no muestra ningún error

Scenario: La playlist ya no existe
  Given Spotify conectado y una playlist que la API de Spotify (falsa) responde con 404 en "GET /v1/playlists/{id}"
  When el usuario abre "/library/playlists/spotify/{id}" de esa playlist
  Then ve el mensaje "This playlist is no longer available."
  And ve un enlace para volver a "/library"

Scenario: Ya no hay acceso a la playlist
  Given Spotify conectado y una playlist que la API de Spotify (falsa) responde con 403 en "GET /v1/playlists/{id}"
  When el usuario abre "/library/playlists/spotify/{id}" de esa playlist
  Then ve el mensaje "This playlist is no longer available."
  And la respuesta tiene estado 200, sin página de error

# --- Canciones guardadas (Liked Songs) ----------------------------------------

Scenario: Ver mis canciones guardadas
  Given Spotify conectado y la API de Spotify (falsa) con 3 canciones guardadas, devueltas de la más reciente a la más antigua
  When el usuario abre "Liked Songs"
  Then ve las 3 canciones en ese orden, cada una con título, artistas, álbum, duración y la etiqueta "Spotify"
  And ve el total "3 songs"
  And las claves de cada canción en las props de Inertia son exactamente: source, id, uri, title, artists, album, duration_ms, image, playable, added_at

Scenario: Miles de canciones guardadas
  Given Spotify conectado y la API de Spotify (falsa) con 3200 canciones guardadas
  When el usuario abre "Liked Songs"
  Then ve las primeras 50 canciones, el total "3,200 songs" y el botón "Load more"
  And la API falsa ha recibido una sola petición a "/me/tracks", con limit=50 y offset=0
  And al pulsar "Load more" ve 100 canciones y la API falsa recibe una petición con offset=50

Scenario: Sin canciones guardadas
  Given Spotify conectado, con playlists y sin canciones guardadas
  When el usuario abre "Liked Songs"
  Then ve el mensaje "No liked songs yet."

# --- Reproducción (comprobación automática, con el SDK falso de T-06) ---------

Scenario: Reproducir una playlist desde el principio
  Given la playlist propia "Road Trip" (uri "spotify:playlist:road") abierta, con "Song A", "Song B" y "Song C"
  When el usuario pulsa "Play"
  Then Spotify recibe PUT /v1/me/player/play con context_uri "spotify:playlist:road" y offset.position 0

Scenario: Reproducir una playlist desde una canción concreta
  Given la playlist propia "Road Trip" abierta, con "Song A" (position 0), "Song B" (position 1) y "Song C" (position 2)
  When el usuario pulsa "Song B"
  Then Spotify recibe PUT /v1/me/player/play con context_uri "spotify:playlist:road" y offset.position 1

Scenario: La posición enviada es la de Spotify aunque haya elementos ocultos
  Given la playlist propia "Road Trip" con un elemento "item": null en la posición 0 y "Song B" en la posición 1
  When el usuario pulsa "Song B", que en la página es la primera canción visible
  Then Spotify recibe PUT /v1/me/player/play con context_uri "spotify:playlist:road" y offset.position 1

Scenario: Reproducir una playlist que solo se sigue
  Given la playlist "Indie Picks" (uri "spotify:playlist:indie") de "Alice", que el usuario solo sigue
  When el usuario pulsa "Play" en "Indie Picks"
  Then Spotify recibe PUT /v1/me/player/play con context_uri "spotify:playlist:indie" y sin offset

Scenario: Reproducir una canción guardada
  Given "Liked Songs" abierta, con "Song X", "Song Y" y "Song Z" en ese orden y todas reproducibles
  When el usuario pulsa "Song Y"
  Then Spotify recibe PUT /v1/me/player/play con uris de "Song X", "Song Y" y "Song Z", en ese orden, y offset.position 1

Scenario: Las canciones no reproducibles no se envían
  Given "Liked Songs" abierta, con "Song X", "Gone Song" (no reproducible) y "Song Z"
  When el usuario pulsa "Song Z"
  Then Spotify recibe PUT /v1/me/player/play con uris de "Song X" y "Song Z" y offset.position 1

# --- Reproducción (comprobación manual en el navegador) -----------------------

@manual
Scenario: La playlist suena y avanza
  Given la cuenta real conectada y una playlist propia con al menos 3 canciones, abierta en un navegador de escritorio
  When el usuario pulsa "Play" y después "siguiente"
  Then la barra de reproductor muestra la primera canción y después la segunda

@manual
Scenario: Una playlist que solo se sigue suena entera
  Given la cuenta real conectada y una playlist que el usuario solo sigue
  When el usuario pulsa "Play" y después "siguiente"
  Then la barra de reproductor muestra una canción de esa playlist y después otra distinta

@manual
Scenario: Una canción guardada suena y avanza
  Given la cuenta real conectada y "Liked Songs" abierta
  When el usuario pulsa la segunda canción y después "siguiente"
  Then la barra de reproductor muestra la segunda canción y después la tercera

# --- Estados sin conexión, vacíos y errores -----------------------------------

Scenario: Spotify sin conectar
  Given el usuario con sesión iniciada y Spotify sin conectar
  When abre "/library"
  Then ve el mensaje "Connect Spotify to see your library." y el botón "Connect Spotify"
  And no se envía ninguna petición a la API de Spotify

Scenario: Biblioteca vacía
  Given Spotify conectado, la API de Spotify (falsa) sin playlists y "/me/tracks?limit=1" con "total": 0
  When el usuario abre "/library"
  Then ve el mensaje "Your library is empty."
  And no ve la lista "Playlists" ni la entrada "Liked Songs"

Scenario: Sin playlists pero con canciones guardadas
  Given Spotify conectado, la API de Spotify (falsa) sin playlists y "/me/tracks?limit=1" con "total": 12
  When el usuario abre "/library"
  Then ve la entrada "Liked Songs" con el texto "12 songs"
  And en la lista "Playlists" ve el mensaje "No playlists yet."
  And no ve el mensaje "Your library is empty."

Scenario: Error de la API de Spotify
  Given Spotify conectado y la API de Spotify (falsa) responde a "/me/playlists" con estado 503
  When el usuario abre "/library"
  Then la respuesta tiene estado 200, sin página de error
  And ve el mensaje "Couldn't load your Spotify library." y el botón "Try again"
  And la página no contiene el cuerpo de la respuesta de Spotify ni el access token

Scenario: Spotify limita las peticiones y no hay caché
  Given Spotify conectado, sin la biblioteca en caché, y el cliente compartido de T-05 en espera por un 429
  When el usuario abre "/library"
  Then la respuesta tiene estado 200, sin página de error
  And ve el mensaje "Spotify is busy right now. Try again in a moment."

Scenario: Acceso revocado desde Spotify
  Given Spotify conectado y la renovación del token responde con "invalid_grant", así que la conexión de T-05 pasa al estado "reconnect_required"
  When el usuario abre "/library"
  Then la respuesta tiene estado 200, sin página de error
  And ve el aviso de T-05 "Your Spotify access expired or was revoked. Please reconnect Spotify."
  And no ve el mensaje "Connect Spotify to see your library."
  And no ve ninguna playlist ni la entrada "Liked Songs"

Scenario: Falta el permiso de canciones guardadas
  Given Spotify conectado y los scopes concedidos guardados por T-05 sin "user-library-read"
  When el usuario abre "/library" y después "Liked Songs"
  Then en "/library" la entrada "Liked Songs" no muestra número y muestra "Reconnect Spotify to grant access to your library."
  And en "Liked Songs" ve el mensaje "Reconnect Spotify to grant access to your library." y el botón "Reconnect Spotify"
  And no se envía ninguna petición a "/me/tracks"

Scenario: Falta el permiso de playlists privadas
  Given Spotify conectado y los scopes concedidos guardados por T-05 sin "playlist-read-private"
  When el usuario abre "/library"
  Then en lugar de la lista "Playlists" ve el mensaje "Reconnect Spotify to grant access to your library." y el botón "Reconnect Spotify"
  And no se envía ninguna petición a "/me/playlists"

# --- Caché ---------------------------------------------------------------------

Scenario: La biblioteca se sirve desde la caché
  Given Spotify conectado y "/library" abierta hace menos de 10 minutos
  When el usuario vuelve a abrir "/library"
  Then ve las mismas playlists
  And no se envía ninguna petición nueva a "/me/playlists" ni a "/me/tracks"

Scenario: La caché caduca a los 10 minutos
  Given Spotify conectado y "/library" abierta hace 11 minutos
  When el usuario vuelve a abrir "/library"
  Then se envía una petición nueva a "/me/playlists"

Scenario: Refrescar la biblioteca a mano
  Given Spotify conectado y "/library" abierta hace menos de 10 minutos
  And el usuario ha creado en Spotify una playlist "Fresh" después
  When pulsa "Refresh"
  Then se envía una petición nueva a "/me/playlists"
  And "Fresh" aparece en la lista

Scenario: Tras desconectar y volver a conectar no se usa la caché anterior
  Given Spotify conectado y "/library" abierta hace menos de 10 minutos
  When el usuario desconecta Spotify, vuelve a conectar la misma cuenta y abre "/library"
  Then se envía una petición nueva a "/me/playlists"

Scenario: Al desconectar Spotify no se ven datos de su biblioteca
  Given Spotify conectado y la biblioteca guardada en caché
  When el usuario desconecta Spotify y abre "/library"
  Then ve el mensaje "Connect Spotify to see your library."
  And no ve ninguna playlist de Spotify
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **Una sola lista mezclada y sin duplicados** (resuelve la pregunta abierta 6). La biblioteca y los resultados de búsqueda de los dos servicios se muestran juntos en una sola lista. Si la misma canción o playlist está en Spotify y en YouTube Music, sale una vez.
- **Consecuencia para T-08:** esta tarea solo cubre Spotify, porque la biblioteca de YouTube Music es T-12. La interfaz se hace ya como esa vista unificada: sin pestañas por servicio, con la etiqueta del servicio en cada elemento y con el formato común de la app. Así YouTube Music se suma después sin rehacerla. La mezcla y la deduplicación entre servicios no se pueden probar hasta que exista T-12, y van en **T-22**.
- **Playlists que el usuario solo sigue:** se muestran en la biblioteca y se pueden reproducir. Al abrir una se ve el aviso de que no se pueden listar sus canciones.
- Siguen **sin decidir**: el criterio para saber que dos canciones son la misma y qué servicio reproduce una canción deduplicada (ver Pendiente).
- La interfaz está en inglés (T-01), así que los textos de los escenarios están en inglés.

**Decisiones del lote (coordinadas entre tareas el 2026-11-09)**

- **Cliente de Spotify compartido (T-05):** timeout, espera tras un 429 y renovación del token son del cliente de T-05. T-08 no los reimplementa. Solo traduce sus resultados a los mensajes de la biblioteca.
- **Formato común** con T-07: `source`, `title`, `image`. Las etiquetas compartidas son "Load more" (paginación) y "Not available" (canción no reproducible).

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Claves exactas del formato común.** El backend traduce las respuestas de Spotify y la interfaz solo conoce estos tipos. Ninguna prop contiene claves propias de la API de Spotify.
  - *Canción* (formato de T-07, con los tipos que fije T-07): `source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image`, `playable`.
  - *Canción dentro de una playlist:* las de la canción más:
    - `position`: el índice original en la playlist de Spotify, que se usa como `offset.position`;
    - `local`: booleano.
  - *Canción guardada:* las de la canción más `added_at` (ISO 8601). Servirá a T-22 para ordenar la lista mezclada.
  - *Playlist:*
    - `source`, `id`, `uri`, `title`, `image` (null si no hay carátula);
    - `owner`: nombre visible del propietario, o null;
    - `collaborative`;
    - `can_list_tracks`: true si el usuario es propietario o colaborador;
    - `total`: el total de Spotify, o null si la API no lo da.
- **Acceso a datos:** el acceso a la API pasa por una clase de servicio de la biblioteca (por ejemplo, `SpotifyLibrary`) que usa el cliente compartido de T-05, no por el controlador.
- **Paginación de las playlists:** se leen todas en el servidor, de 50 en 50 (el máximo de la API), siguiendo el campo `next`, con un tope de seguridad de 1000 playlists (20 peticiones). Un usuario normal tiene pocos cientos, y tenerlas todas facilita la mezcla futura.
- **Total de Liked Songs en `/library`:** se pide `GET /me/tracks?limit=1` y se usa su `total`. Se guarda en caché junto con las playlists. La biblioteca está vacía solo si no hay playlists y ese total es 0.
- **Paginación de las canciones (de una playlist y de Liked Songs):**
  - de 50 en 50 con el botón "Load more"; nunca se descarga una biblioteca de miles de canciones de golpe;
  - el cursor que usa la interfaz es opaco (no un offset de Spotify), para que T-22 pueda combinar servicios.
- **Número de canciones ("{n} songs"):** es siempre el `total` que da Spotify. Incluye los elementos que la app oculta (`item: null` y episodios de podcast), así que puede ser algo mayor que las canciones visibles. Si `total` es null, no se muestra número.
- **Orden:** el que devuelve Spotify. Las playlists van en el orden de la biblioteca del usuario y Liked Songs de la más reciente a la más antigua. No se añade ordenación ni filtro propios.
- **Detalle de una playlist:** al abrir `/library/playlists/spotify/{id}` (también directamente, sin pasar por `/library`) se llama a `GET /v1/playlists/{id}` para sus datos. `can_list_tracks` se calcula comparando el propietario y el campo `collaborative` con el id de Spotify del usuario. Solo si es true se pide `/playlists/{id}/items`. Un 404 o un 403 en cualquiera de las dos llamadas muestra "This playlist is no longer available.".
- **Reproducción**, siempre con la función de T-06 `play({contextUri?, uris?, offset})`, que envía `PUT /v1/me/player/play`:
  - *Playlist propia o colaborativa:* `play({contextUri, offset: {position}})`, con la `position` de la canción elegida (0 con "Play").
  - *Playlist que solo se sigue:* `play({contextUri})`, sin offset.
  - *Canciones guardadas:* `play({uris, offset: {position}})`. `uris` son las canciones reproducibles de la página de 50 que contiene la elegida, en orden, y `position` es su índice dentro de esa lista.

  La cola editable de la app es T-13, que decide si estas listas se cargan en su cola.
- **Canciones no reproducibles:**
  - Las peticiones llevan `market=from_token`, para que Spotify indique `is_playable` y aplique el track relinking.
  - Los archivos locales (`is_local: true`) tienen `playable: false` y `local: true`, y muestran "Local file, can't play here".
  - Las canciones con `is_playable: false` muestran "Not available".
  - Los elementos con `item: null` (canción eliminada) y los episodios de podcast no se muestran.
- **Scopes:** antes de llamar a Spotify se comprueban los scopes concedidos que guarda T-05.
  - Sin `user-library-read`: Liked Songs muestra "Reconnect Spotify to grant access to your library." sin enviar ninguna petición. En `/library` no se pide el total de canciones guardadas, y la entrada "Liked Songs" sale sin número y con ese mismo texto.
  - Sin `playlist-read-private`: `/library` muestra el mismo mensaje en lugar de las playlists y no llama a `/me/playlists`.
- **Caché:**
  - *Qué se guarda:* la lista de playlists con el total de Liked Songs, y cada página de canciones. Van en la caché de Laravel durante **10 minutos**.
  - *Clave:* incluye el id de la cuenta de Spotify y el identificador de la conexión de T-05, que cambia en cada conexión. Así, tras desconectar y volver a conectar nunca se lee la caché anterior.
  - *"Refresh":* se salta la caché y la sobrescribe.
  - *Sin datos caducados:* no se reutiliza contenido por `snapshot_id` ni se sirven datos caducados. Durante la espera por un 429 solo se sirve la caché que sigue vigente; si no la hay, se muestra "Spotify is busy right now. Try again in a moment.".
- **Errores:** cualquier error de Spotify que el cliente de T-05 no resuelva se muestra como un mensaje dentro de la página, con estado 200 y sin datos internos (cuerpo de la respuesta de Spotify o tokens). Cada error tipado del cliente se traduce así:
  - `not_connected`: "Connect Spotify to see your library.".
  - Conexión en estado `reconnect_required` (token revocado): el aviso de reconexión de T-05, nunca el de conectar, porque la conexión sigue existiendo.
  - Espera por un 429: "Spotify is busy right now. Try again in a moment.".
  - 5xx o timeout: "Couldn't load your Spotify library.".
  - 404 o 403 en una playlist: "This playlist is no longer available.".
- **Textos propuestos de la interfaz:**
  - *Navegación:* "Library", "Playlists", "Liked Songs".
  - *Botones:* "Play", "Load more", "Refresh", "Try again", "Connect Spotify", "Reconnect Spotify".
  - *Etiquetas:* "by {owner}", "Collaborative", "{n} songs", "Local file, can't play here", "Not available".
  - *Estados vacíos:* "This playlist is empty.", "No liked songs yet.", "No playlists yet.", "Your library is empty.".
  - *Avisos:*
    - "Connect Spotify to see your library."
    - "Couldn't load your Spotify library."
    - "Spotify is busy right now. Try again in a moment."
    - "Spotify doesn't let this app list the songs of playlists you don't own."
    - "This playlist is no longer available."
    - "Reconnect Spotify to grant access to your library."

  Además se usa el aviso de T-05 "Your Spotify access expired or was revoked. Please reconnect Spotify.".

**Restricciones descubiertas**

- **Playlists del usuario:** `GET /v1/me/playlists`.
  - Scope `playlist-read-private`, más `playlist-read-collaborative` para incluir las colaborativas.
  - `limit` máximo 50 (por defecto 20) y `offset` máximo 100 000.
  - El campo `tracks` de cada playlist está obsoleto y se sustituye por `items` (`href` y `total`).
  - Las `images` pueden venir vacías y sus URLs caducan en menos de un día.
  - Fuente: https://developer.spotify.com/documentation/web-api/reference/get-a-list-of-current-users-playlists
- **Canciones de una playlist:** `GET /v1/playlists/{id}/items`, que sustituye a `/tracks` (obsoleto).
  - `limit` máximo 50.
  - Cada elemento trae `is_local` e `item`, que puede ser `null`.
  - Solo está disponible para playlists que el usuario posee o en las que colabora. Con cualquier otra devuelve **403 Forbidden**.
  - Fuentes: https://developer.spotify.com/documentation/web-api/reference/get-playlists-items y https://developer.spotify.com/documentation/web-api/reference/get-playlists-tracks
- **Canciones guardadas:** `GET /v1/me/tracks`.
  - Scope `user-library-read`, `limit` máximo 50 (por defecto 20).
  - Cada elemento trae `added_at` y `track`.
  - Con `market` se reciben `is_playable` y `restrictions.reason` (`market`, `product` o `explicit`; pueden aparecer más).
  - Fuente: https://developer.spotify.com/documentation/web-api/reference/get-users-saved-tracks
- **Cambios de febrero de 2026 en development mode.** Afectan a las apps nuevas desde el 11 de febrero de 2026, y las existentes se migraron el 9 de marzo de 2026.
  - El contenido de las playlists (`items`) solo se devuelve para las que el usuario posee o en las que colabora. En las que solo sigue se omite el campo `items`.
  - Guardar, quitar y comprobar canciones pasan a `/me/library`.
  - La búsqueda baja a un máximo de 10 resultados por petición.
  - Desaparecen `GET /artists/{id}/top-tracks`, las peticiones de varios elementos a la vez (`GET /tracks`, `/albums`…) y `GET /users/{id}/playlists`.
  - El propietario de la app necesita Premium, y hay un máximo de 5 usuarios por app.
  - Fuente: https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide
- **Playlists algorítmicas y editoriales de Spotify** (por ejemplo, Discover Weekly): las apps nuevas o en development mode no pueden acceder a ellas desde noviembre de 2024. Fuente: https://developer.spotify.com/blog/2024-11-27-changes-to-the-web-api
- **Límite de peticiones:** se cuenta en una ventana móvil de 30 segundos. Al pasarse, la API responde con **429** y normalmente con la cabecera `Retry-After` en segundos. Lo gestiona el cliente compartido de T-05. Fuente: https://developer.spotify.com/documentation/web-api/concepts/rate-limits
- **Track relinking:** con `market`, si una canción no está disponible en el país del usuario, Spotify puede devolver otra versión equivalente con `linked_from`. Con `is_playable: false` no hay versión alternativa. Fuente: https://developer.spotify.com/documentation/web-api/concepts/track-relinking

**Casos límite**

- **Bibliotecas muy grandes:** con 3200 canciones guardadas, leerlas todas son 64 peticiones. Por eso se pagina con "Load more" y nunca se descarga todo al abrir la página.
- **Playlists muy grandes:** una playlist puede tener hasta 10 000 canciones. Se aplica la misma paginación de 50 en 50. Con el contexto de Spotify se reproduce entera, aunque solo se hayan cargado 50.
- **Liked Songs se reproduce por páginas:** al pulsar una canción guardada solo se envían las de su página de 50, así que la reproducción se para al final de esa página. Que siga sonando depende de T-13.
- **Playlist que el usuario dejó de seguir, o colaboración retirada, entre que se cargó la lista y se abre:** la API responde 403 o 404 y se muestra el mensaje correspondiente.
- **Playlists algorítmicas o editoriales de Spotify** que aparezcan en la lista: se tratan como las playlists que solo se siguen (ver Pendiente).
- **Nombres con emojis, caracteres no latinos o muy largos:** se muestran tal cual, recortados con puntos suspensivos en la vista.
- **Varios artistas:** se muestran separados por comas.
- **Playlist cambiada en Spotify mientras se pagina:** con "Load more" puede repetirse o saltarse alguna canción. Se acepta. Con "Refresh" se vuelve a cargar desde el principio.
- **Spotify Premium caducado:** la lectura de la biblioteca sigue funcionando, pero no la reproducción en el navegador (T-06). Si caduca el Premium del propietario, la app de development mode deja de funcionar entera.

**Pistas para los tests**

- **API de Spotify falsa:**
  - `Http::fake()` con patrones por endpoint: `api.spotify.com/v1/me/playlists*`, `api.spotify.com/v1/playlists/*/items*`, `api.spotify.com/v1/me/tracks*` y `accounts.spotify.com/api/token`.
  - Para la paginación se usa `Http::sequence()` o un callback que lea `limit` y `offset` de la petición.
  - Las respuestas salen de fixtures JSON en `tests/Fixtures/spotify/`, copiadas de los ejemplos de la documentación oficial.
  - Se activa `Http::preventStrayRequests()` para que ningún test llame de verdad a Spotify.
- **Contar peticiones:** `Http::assertSent()`, `Http::assertNotSent()` y `Http::assertSentCount()` comprueban `limit`, `offset` y `market=from_token`. También comprueban que no se envía nada a Spotify en estos casos: visitante sin sesión, Spotify sin conectar, falta de scope, caché vigente y playlist que solo se sigue.
- **Claves exactas:** `assertInertia(fn ($page) => $page->component('Library')->has('playlists', 120)->where('playlists.0.source', 'spotify'))`, y para las claves exactas `array_keys($page['props']['playlists'][0])` comparado con la lista esperada con `toEqualCanonicalizing`.
- **Espera por 429 y renovación del token:** se reutilizan las fakes y ayudantes de T-05, que fijan el cliente en espera o hacen que la renovación responda `{"error": "invalid_grant"}`. T-08 no prueba el mecanismo, solo el mensaje de la biblioteca.
- **Caché:** caché `array` en los tests y `$this->travel(11)->minutes()` para la caducidad. Para la reconexión, se usa el ayudante de T-05 que crea una conexión nueva de la misma cuenta.
- **Reproducción:**
  - Tests de navegador de Pest 4 (T-03) con el SDK simulado de T-06: se pulsa "Play" o una fila y después se usa `Http::assertSent` sobre `PUT /v1/me/player/play` de Spotify para comprobar `context_uri`/`offset.position` o `uris`/`offset.position`.
  - Para las filas no reproducibles se usa `Http::assertNotSent`.
  - Los escenarios `@manual` se comprueban a mano en el navegador con la cuenta real.
- **Archivos locales y canciones no disponibles:** fixtures con `is_local: true`, `is_playable: false` e `item: null`.

**Fuera de alcance**

- Biblioteca de YouTube Music (T-12). Mezcla y deduplicación entre servicios (T-22).
- Timeout, espera tras un 429 y renovación del token, que son del cliente compartido de T-05.
- Búsqueda (T-07). Cola editable, shuffle y repeat (T-13). Playlists propias mixtas de la app (T-14). Reproducción en el móvil (T-15).
- Modificar la biblioteca de Spotify: crear, renombrar o borrar playlists, añadir o quitar canciones, seguir o dejar de seguir playlists, y guardar o quitar "me gusta". Además, en development mode hay informes de 403 en los endpoints de escritura.
- Álbumes guardados, artistas seguidos, podcasts, episodios y audiolibros.
- Ordenar o filtrar la biblioteca dentro de la app.

**Pendiente**

- **Criterio de "misma canción"** (por ISRC, por título, artista y duración, o una mezcla) y **qué servicio reproduce una canción deduplicada**. Se decide en T-22.
- **Playlists en la vista mezclada:** las canciones guardadas tiene sentido deduplicarlas, pero las playlists son listas personales. Falta decidir si dos playlists con el mismo nombre en los dos servicios se fusionan en una o se listan juntas, cada una con su servicio. Se decide en T-22.
- **Orden de la lista mezclada** cuando haya dos servicios (por fecha de guardado, alfabético, primero un servicio…). Spotify da `added_at` en las canciones guardadas, pero no da fecha en las playlists. Se decide en T-22.
- **Dudas del spike que no bloquean:** si `/me/playlists` devuelve las playlists algorítmicas o editoriales que el usuario sigue (por ejemplo, Discover Weekly) y si se pueden reproducir por contexto, y si las playlists que solo se siguen traen `items.total`. Si no lo traen, `total` es null y no se muestra número.
- **Duración de la caché:** 10 minutos es una propuesta. El usuario puede preferir más tiempo, a cambio de ver los cambios de Spotify con más retraso.

**Seguimiento (pendiente en otros ficheros)**

- **T-22 — mezcla y deduplicación entre servicios** (id reservado; lo crea el coordinador). Junta la biblioteca (T-08 y T-12) y la búsqueda (T-07 y T-11). Decide el criterio de "misma canción", qué servicio reproduce una canción deduplicada, qué se hace con las playlists, el orden de la lista mezclada y la paginación entre servicios sobre el cursor opaco de T-08.
- **T-05:**
  - Guardar los scopes concedidos (campo `scope` de la respuesta del token).
  - Incluir `playlist-read-private`, `playlist-read-collaborative` y `user-library-read`.
  - Dar a cada conexión un identificador que cambie al reconectar, para la clave de caché.
  - Ofrecer ayudantes de test para la espera por 429, la conexión nueva y "invalid_grant".
- **T-06:** `play({contextUri?, uris?, offset})` debe aceptar `uris` con `offset: {position}`, además de `contextUri` con o sin offset. Debe poder simularse con el SDK falso de T-06.
- **T-07:** el formato de canción gana dos extensiones en T-08 (`position` y `local` para canciones de playlist, `added_at` para canciones guardadas) y una forma de playlist (`source`, `id`, `uri`, `title`, `image`, `owner`, `collaborative`, `can_list_tracks`, `total`). Conviene que T-07 fije los tipos de `artists` y `album`.
- **T-21:** debe ofrecer la carátula genérica que usan las playlists y canciones sin `image`.
- **T-12:** debe alimentar la misma vista `/library` con `source: "youtube_music"` y la ruta `/library/playlists/youtube-music/{id}`, no una biblioteca aparte.
- **T-13:** T-08 reproduce por contexto de Spotify o por páginas de `uris`. T-13 decide si esas listas pasan a la cola de la app. Las canciones de playlists que solo se siguen no se pueden leer, así que no pueden cargarse en esa cola.
- **T-14:** no se pueden añadir a playlists propias canciones de playlists que solo se siguen, porque no se listan.
- **Discovery:** añadir a Constraints los cambios de febrero de 2026 en development mode, y marcar como parcialmente respondida la pregunta abierta 10.

**Estimación: 5 puntos.** Se mantiene como una sola tarea: tras pasar el timeout, el 429 y la renovación del token al cliente de T-05, la caché que queda es pequeña y separarla daría una tarea de medio punto.
- Servicio de biblioteca sobre el cliente de T-05, con paginación, `market`, traducción al formato común con claves exactas, comprobación de scopes y mensajes de error: 1,5 puntos.
- Vista unificada, detalle de playlist y Liked Songs con "Load more", estados vacíos y elementos no reproducibles: 2 puntos.
- Órdenes de reproducción por contexto, offset y `uris` con tests de navegador de Pest 4, más la comprobación manual: 1 punto.
- Caché ligada a la conexión, con caducidad y "Refresh": 0,5 puntos.

## Definition of Done

- [ ] Acceptance criteria covered by tests, except the `@manual` scenarios, which are checked by hand in a desktop browser with the real Spotify account and recorded in the change
- [ ] Spike done before implementing: a followed playlist plays as a context and Liked Songs plays with `uris`
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
