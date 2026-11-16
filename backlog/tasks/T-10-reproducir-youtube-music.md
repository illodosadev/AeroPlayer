---
id: T-10
title: Reproducir canciones de YouTube Music
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 5
language: es
---

# T-10 — Reproducir canciones de YouTube Music

## Goal

Reproducir canciones de YouTube Music en el reproductor de la app con los mismos controles que Spotify (play, pausa, siguiente, anterior, volumen, seek), para que los dos servicios se usen igual. Una sola barra y un solo estado de reproducción sirven para los dos servicios, y la cola de T-13 pasa de una canción de un servicio a una del otro sola.

## Context

- **Necesidad v1 del discovery.** Es la mitad de YouTube del reproductor; la de Spotify es T-06.
- **Depende de:**
  - **T-13**, la cola. T-13 se entrega antes y solo con Spotify (D5). T-10 añade `youtube_music` a sus fuentes, y su "Next", "Previous", avance automático, repetición y fallos de D4 pasan a valer también para YouTube Music. Decidido con el usuario el 2026-11-15;
  - **T-27**, el spike, que fijó el método: el reproductor embebido de YouTube (IFrame API) con el id del vídeo, sin anuncios con la cuenta Premium iniciada en el navegador;
  - **T-06 y T-21**, el reproductor del navegador y la barra Aero;
  - **T-02**, la sesión.
- **Escenarios que dependen de otras tareas.** Se cumplen cuando esas tareas estén entregadas; si T-10 llega antes, se quedan fuera del cambio y se añaden con ellas:
  - **T-14** (playlists propias): "Reproducir una playlist mixta" y "El fallo llega a T-14". Además, el endpoint de añadir de T-14 solo acepta `youtube_music` cuando lo añaden T-11 o T-12;
  - **T-19** (historial): "Las escuchas se registran".
- **No depende de T-09.** El reproductor embebido no usa el token de la app, así que las canciones de YouTube Music suenan aunque YouTube Music no esté conectado o la conexión haya caducado. La conexión solo hace falta para leer la biblioteca (T-12). Decidido con el usuario el 2026-11-15.
- **Tampoco necesita Spotify conectado.** Hoy la barra (spec `spotify-playback`) muestra "Connect Spotify to play your music." y desactiva "Play" sin Spotify, y T-13 hace lo mismo con la cola. Con T-10 eso solo vale cuando la canción actual es de Spotify. El cambio de OpenSpec lleva deltas MODIFIED sobre estos requisitos de `spotify-playback`:
  - "The browser player is created only with Spotify connected";
  - "Controls wait for the device";
  - "The bar shows what is playing";
  - "Leaving stops the player", en el caso de desconectar Spotify.
- **Puntos de entrada:** las filas de canción de YouTube Music llegan con T-11 (búsqueda) y T-12 (biblioteca). T-10 se prueba con colas cargadas por la API de T-13.
- **La interfaz va en inglés** (T-01).
- **Preguntas abiertas del discovery:**
  - la 2 está resuelta por T-27;
  - la 3, la pantalla bloqueada en el móvil, sigue abierta y no se trata aquí;
  - la 11, la política de Spotify sobre mezclar servicios, afecta a la cola mixta y sigue sin decidir (ver "Pendiente").

## Acceptance criteria

Las rutas y nombres de estado son los de T-06 y T-13. Los escenarios `@provisional` dependen de la heurística de fin de canción de Spotify de T-13. Los `@manual` se comprueban a mano con el reproductor real.

```gherkin
# --- Reproducir ------------------------------------------------------------------

Scenario: Reproducir una canción de YouTube Music
  Given una cola con la canción de YouTube Music "Song Y" (id de vídeo "vid-y-00001", título "Song Y", artista "Artist Y", carátula y duración 3:30)
  When el usuario pulsa "Play", y el test dispara el estado "playing" del reproductor simulado
  Then el reproductor de YouTube simulado ha recibido loadVideoById("vid-y-00001")
  And la barra muestra "Song Y", "Artist Y", la carátula de la cola, "Pause" y la duración 3:30
  And el recuadro de vídeo de YouTube está junto a la barra

Scenario: El recuadro de vídeo cumple el mínimo de YouTube
  Given una canción de YouTube Music sonando, a 1280 px y a 375 px de ancho
  When se mide el recuadro de vídeo
  Then mide al menos 200x200 px y se ve entero dentro de la pantalla
  And a 375 px no hay desplazamiento horizontal y los controles de la barra conservan su área táctil de 44x44 px

Scenario: Ningún panel tapa el recuadro de vídeo
  Given una canción de YouTube Music sonando
  When el usuario abre el panel "Queue" (T-13)
  Then el recuadro de vídeo sigue midiendo al menos 200x200 px
  And ningún elemento queda encima de él (document.elementFromPoint en sus cuatro esquinas y su centro devuelve el recuadro)

Scenario: El recuadro de vídeo solo se ve con YouTube Music
  Given una canción de Spotify sonando
  When el usuario mira la barra
  Then no hay recuadro de vídeo y la barra muestra la carátula de Spotify como hasta ahora

Scenario: Pausar
  Given una canción de YouTube Music sonando
  When el usuario pulsa "Pause"
  Then el reproductor simulado recibe pauseVideo()
  And la barra muestra "Play" y el progreso se detiene

Scenario: Reanudar, volumen y posición
  Given una canción de YouTube Music en pausa en 0:40
  When el usuario pulsa "Play", pone el volumen al 30 % y lleva la posición a 1:30
  Then el reproductor simulado recibe playVideo(), setVolume(30) y seekTo(90, true), en ese orden
  And la barra muestra "Pause" y el progreso sigue desde 1:30

Scenario: El volumen es el mismo para los dos servicios
  Given el volumen al 30 % con una canción de Spotify
  When empieza una canción de YouTube Music
  Then el reproductor de YouTube simulado recibe setVolume(30) y el control de volumen sigue en 30 %
  And al recargar la página el volumen sigue en 30 %, como en T-06

Scenario: Reproducción automática bloqueada
  Given el reproductor simulado se queda en el estado "unstarted" tras loadVideoById, sin pasar a "buffering" ni a "playing"
  When pasan 3 segundos
  Then la barra muestra "Press play to start listening", como T-06
  And al pulsar "Play" el reproductor simulado recibe playVideo() y la canción empieza
  And esos segundos no cuentan para el límite de 10 s de "La canción no empieza"

# --- Sin Spotify ni YouTube Music conectados -------------------------------------

Scenario: Una cola solo de YouTube Music sin Spotify conectado
  Given Spotify sin conectar y una cola con "Song Y1" y "Song Y2" de YouTube Music
  When el usuario pulsa "Play"
  Then "Song Y1" suena en el reproductor de YouTube
  And la barra no muestra "Connect Spotify to play your music."

Scenario: La cola llega a una canción de Spotify sin Spotify conectado
  Given Spotify sin conectar y una cola con "Song Y" de YouTube Music seguida de "Song S" de Spotify
  When termina "Song Y"
  Then la barra muestra "Connect Spotify to play your music."
  And "Song S" queda como actual, sin sonar y sin contar como fallo
  And no aparece "This song can't be played."

Scenario: Desconectar Spotify mientras suena YouTube Music
  Given "Song Y" de YouTube Music sonando
  When el usuario desconecta Spotify en "/settings/spotify"
  Then "Song Y" sigue sonando y la barra sigue mostrándola

Scenario: YouTube Music desconectado no impide reproducir (sustituye al de T-14)
  Given YouTube Music sin conectar, o en el estado "reconnect_required"
  And una cola con "Song Y" de YouTube Music
  When el usuario pulsa "Play"
  Then "Song Y" suena igual
  And no se envía ninguna petición a "/youtube-music" ni a Google desde el servidor

# --- Cola (T-13) -----------------------------------------------------------------

Scenario: "Next" y "Previous" con canciones de YouTube Music
  Given una cola con "Song Y1", "Song Y2" y "Song Y3" de YouTube Music, sonando "Song Y2" en 0:01
  When el usuario pulsa "Next" y, con "Song Y3" en 0:01, pulsa "Previous"
  Then el reproductor simulado recibe loadVideoById con el id de "Song Y3" y después con el de "Song Y2"
  And se envía "PATCH /queue" con el "current_item_id" de "Song Y3" y después con el de "Song Y2"

Scenario: "Previous" reinicia la canción de YouTube Music a partir de 3 segundos
  Given "Song Y2" sonando en 0:05, con "Song Y1" ya sonada
  When el usuario pulsa "Previous"
  Then el reproductor simulado recibe seekTo(0, true) y sigue sonando "Song Y2", como la regla de 3 s de T-13

Scenario: Una canción de YouTube Music termina y empieza la siguiente
  Given una cola con "Song Y1" y "Song Y2" de YouTube Music, sonando "Song Y1"
  When el reproductor simulado emite el estado "ended"
  Then en menos de 500 ms recibe loadVideoById con el id de "Song Y2"
  And se envía "PATCH /queue" con el "current_item_id" de "Song Y2"

@provisional
Scenario: De Spotify a YouTube Music
  Given una cola con "Song S" de Spotify seguida de "Song Y" de YouTube Music
  When el SDK de Spotify simulado indica que "Song S" terminó (heurística de T-13)
  Then en menos de 500 ms el reproductor de YouTube simulado recibe loadVideoById con el id de "Song Y"
  And la barra pasa a mostrar "Song Y" con el recuadro de vídeo

Scenario: De YouTube Music a Spotify
  Given una cola con "Song Y" de YouTube Music seguida de "Song S" de Spotify
  When el reproductor de YouTube simulado emite el estado "ended"
  Then en menos de 500 ms se envía "PUT /spotify/player/play" con uris ["<uri de Song S>"]
  And el recuadro de vídeo desaparece y la barra muestra "Song S"

Scenario: "Next" entre servicios
  Given "Song S" de Spotify sonando y "Song Y" de YouTube Music a continuación
  When el usuario pulsa "Next"
  Then el SDK de Spotify simulado recibe pause() antes de que el reproductor de YouTube reciba loadVideoById
  And nunca hay a la vez un estado "playing" de YouTube y un estado de Spotify con paused = false

Scenario: "Repeat: one" con una canción de YouTube Music
  Given "Song Y" de YouTube Music sonando con "Repeat: one"
  When el reproductor simulado emite el estado "ended"
  Then recibe loadVideoById con el id de "Song Y" otra vez

Scenario: Reproducir una playlist mixta (de T-14; necesita T-14)
  Given la playlist propia "Verano 2007" con "Song S" de Spotify y "Song Y" de YouTube Music, en ese orden, creada en el test con factories (no hace falta T-11 ni T-12)
  When el usuario la reproduce
  Then se envía "PUT /queue" con las dos canciones en el orden de la playlist
  And empieza "Song S", y al terminar empieza "Song Y"

@manual
Scenario: El paso real entre servicios
  Given la app desplegada por HTTPS, Spotify Premium y la sesión de YouTube Premium en el navegador
  When una cola real pasa de una canción de Spotify a una de YouTube Music, y al revés
  Then la siguiente se oye menos de 2 s después del final de la anterior (D2)
  And no sale ningún anuncio en el recuadro de vídeo

# --- Fallos (D4) -----------------------------------------------------------------

Scenario Outline: Una canción de YouTube Music no se puede reproducir
  Given una cola con "Song Y1", "Song Y2" y "Song Y3" de YouTube Music
  When empieza "Song Y2" y el reproductor simulado emite el error <código>
  Then la barra muestra "This song can't be played."
  And el reproductor simulado recibe loadVideoById con el id de "Song Y3"
  And el frontend emite el evento de fallo de T-13 con el item_id de "Song Y2" y reason "<reason>"

  Examples:
    | código | causa                                | reason          |
    | 2      | id de vídeo no válido                | playback_error  |
    | 100    | vídeo eliminado o privado            | playback_error  |
    | 101    | el propietario no permite insertarlo | playback_error  |
    | 150    | el propietario no permite insertarlo | playback_error  |
    | 5      | error del reproductor HTML5          | player_error    |

Scenario: La canción de YouTube Music no empieza en 10 segundos
  Given "Song Y2" pedida con loadVideoById y "Song Y3" pendiente
  When pasan 10 segundos sin el estado "playing" de "Song Y2"
  Then la barra muestra "This song can't be played."
  And el reproductor simulado recibe loadVideoById con el id de "Song Y3"
  And el evento de fallo lleva reason "start_timeout"

Scenario: El fallo llega a T-14 (necesita T-14)
  Given "Song Y" en la cola con origin {"type": "own_playlist", "playlist_id": 12, "item_id": 345}
  When el reproductor simulado emite el error 100 al empezar "Song Y"
  Then el frontend emite el evento de fallo con el item_id de "Song Y", ese origin y reason "playback_error"
  And T-14 la marca como no disponible, y no lo hace con reason "player_error" ni "start_timeout"

Scenario: Tres fallos seguidos paran la reproducción
  Given una cola con tres canciones de YouTube Music seguidas que no se pueden reproducir y una cuarta pendiente
  When fallan las tres, una detrás de otra
  Then la barra muestra "Couldn't play 3 songs in a row. Playback stopped."
  And el reproductor simulado no recibe loadVideoById para la cuarta

Scenario: El reproductor de YouTube no se puede cargar
  Given el script de la IFrame API de YouTube no carga (da error o no responde en 10 s)
  And una cola con "Song Y" de YouTube Music seguida de "Song S" de Spotify
  When toca reproducir "Song Y"
  Then la barra muestra "YouTube Music playback isn't available right now."
  And "Song Y" queda como actual, sin saltar a "Song S" y sin contar como fallo
  And no se emite el evento de fallo de canción

Scenario: Salir del fallo de carga del reproductor
  Given "Song Y" como actual con "YouTube Music playback isn't available right now." y "Song S" de Spotify a continuación
  When el usuario pulsa "Play"
  Then la app vuelve a cargar el script de la IFrame API una vez y, si carga, "Song Y" empieza
  When en su lugar el usuario pulsa "Next"
  Then se envía "PUT /spotify/player/play" con uris ["<uri de Song S>"] y el aviso desaparece

# --- Pestañas, navegación y estado -----------------------------------------------

Scenario: Solo suena una pestaña
  Given dos pestañas de la app abiertas, y "Song Y" de YouTube Music sonando en la primera
  When el usuario pulsa "Play" en la segunda
  Then el reproductor de la primera pestaña recibe pauseVideo()
  When la canción termina en la segunda pestaña
  Then se envía exactamente un "PATCH /queue", desde la segunda pestaña, y la primera no envía ninguno

Scenario: La reproducción sigue al navegar
  Given una canción de YouTube Music sonando
  When el usuario va de "/library" a "/search" dentro de la app
  Then el reproductor simulado no recibe destroy() ni un loadVideoById nuevo
  And el recuadro de vídeo sigue visible y la barra conserva su estado

Scenario: Salir para el reproductor
  Given una canción de YouTube Music sonando
  When el usuario cierra la sesión
  Then el reproductor simulado recibe destroy() y el recuadro de vídeo desaparece de la página, como el de Spotify en T-06

Scenario: Un solo estado de reproducción para los dos servicios
  Given una canción de YouTube Music sonando
  When el test lee el estado del reproductor común (el mismo que usa la barra)
  Then "track" tiene source "youtube_music", id "vid-y-00001", uri, title, artists, album, image y durationMs
  And el estado tiene paused, positionMs y volume, con los mismos nombres que con una canción de Spotify

Scenario: Las escuchas se registran (T-19; necesita T-19)
  Given una canción de YouTube Music que suena 30 s seguidos de reproducción real
  When se cumplen los 30 s
  Then se envía "POST /history/listens" con source "youtube_music" y el id del vídeo, con las mismas reglas que Spotify (D13)
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-15)**

- **Orden:** T-13 va antes. T-10 suma YouTube Music a la cola ya hecha, sin rehacer su modelo ni su API.
- **Recuadro de vídeo visible:** mientras suena una canción de YouTube Music, su vídeo se ve en un recuadro de al menos 200x200 px junto a la barra, como su carátula. No se oculta.
- **Una barra para los dos servicios:** un único reproductor de la app que manda a Spotify o a YouTube según la canción actual de la cola, con los mismos controles y el mismo estado. T-16, T-17 y T-19 leen ese estado sin saber de qué servicio es la canción.
- **Sin conexión obligatoria:** las canciones de YouTube Music suenan aunque YouTube Music no esté conectado. Esto **sustituye** el escenario "YouTube Music desconectado" que T-14 le pasaba a T-10, en el que salían "Unavailable" (ver Seguimiento).

**Pregunta abierta 2, resuelta por T-27 (2026-11-15)**

- **Método:** reproductor embebido de YouTube (IFrame API) con el id del vídeo.
- **Anuncios:** ninguno con la cuenta Premium iniciada en el navegador. Premium depende de la sesión de YouTube del navegador, no del token de la app.
- **Errores:** el reproductor avisa por `onError`. En la prueba, un primer intento dio error sin que se anotara el código, y tras recargar sonó todo. Por eso el código 5 no marca la canción en T-14 (ver Reglas).
- **Origen:** la página se sirvió por HTTP, no como fichero local, y con `origin` en `playerVars`.
- **Comprobación previa opcional:** la Data API (`videos?part=status`, campo `embeddable`) dice si un vídeo se puede insertar. T-10 no la usa: el error del reproductor basta, y así no hace falta la conexión.

**Reglas de YouTube para el reproductor embebido** ([Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality), [Player parameters](https://developers.google.com/youtube/player_parameters))

- El reproductor debe tener un área visible de **al menos 200x200 px**. No se oculta, no se tapa con otros elementos y no se cambia su aspecto.
- La reproducción automática, o la que se lanza por programa, como la siguiente canción de la cola, **solo puede empezar si más de la mitad del reproductor se ve** en la pantalla. Por eso el recuadro va fijo junto a la barra, que siempre está a la vista, y los paneles de T-13, T-16 y T-17 no lo tapan.
- El reproductor se identifica por la cabecera `Referer`: no se usa `noreferrer`.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Un reproductor de la app con dos motores:**
  - un composable común, por ejemplo `usePlayer`, expone el estado y los controles a la barra y al resto de la app;
  - por debajo usa el motor de Spotify (`useSpotifyPlayer`) o uno nuevo de YouTube (`useYouTubePlayer`), según el `source` de la canción actual;
  - al cambiar de servicio, pausa el motor anterior antes de empezar el siguiente;
  - el estado común amplía el de T-06: `track` con `source`, `id`, `uri`, `title`, `artists[]`, `album`, `image` y `durationMs`, más `paused`, `positionMs`, `timestamp`, `volume` y `autoplayBlocked`.
- **Motor de YouTube:** carga la IFrame API una sola vez, crea un único reproductor por pestaña dentro del recuadro y lo mantiene vivo al navegar, como el SDK de Spotify (T-06). Usa `playerVars` con `origin` igual al de la página y `playsinline: 1`.
- **Fin de canción:** el estado `0` ("ended") de `onStateChange` hace avanzar la cola. A diferencia de Spotify, no hace falta heurística.
- **Reproducción automática bloqueada:** la IFrame API no tiene un evento propio. Cuenta como bloqueada si el reproductor sigue en "unstarted" (`-1`) 3 s después de `loadVideoById`, sin pasar por "buffering" (`3`) ni "playing" (`1`). Se verifica con el reproductor real al proponer.
- **Fallos de la canción (D4):**
  - errores 2, 100, 101, 150 y cualquier código desconocido: `playback_error`;
  - error 5: `player_error`. Salta y cuenta para los 3 fallos, pero T-14 no marca la canción, porque puede ser un fallo pasajero;
  - 10 s desde `loadVideoById` sin el estado "playing": `start_timeout`, como en T-13.
- **Errores que no son de la canción** (T-13: no saltan, no cuentan y no emiten el evento): la IFrame API que no carga ("YouTube Music playback isn't available right now.") y una canción de Spotify sin Spotify conectado ("Connect Spotify to play your music.").
- **Datos de la barra:** el título, los artistas, el álbum, la carátula y la duración salen de la canción de la cola (formato común de T-07), no de `getVideoData()` del reproductor, que da otros textos.
- **Formato en la cola:** `source: "youtube_music"`, `id` = el id del vídeo (11 caracteres de `[A-Za-z0-9_-]`) y `uri` = `youtube_music:video:<id>`. T-10 lo define y lo valida en la cola; T-11 y T-12 lo usan al crear filas de canción.
- **Una pestaña suena:** al empezar a sonar, una pestaña avisa a las demás por `BroadcastChannel`, y las que estaban sonando se pausan. Solo la pestaña que suena avanza la cola. Para Spotify ya lo garantiza el dispositivo único (T-13).
- **Volumen:** un único volumen guardado en el navegador para los dos motores (T-06). La IFrame API usa 0-100, igual que la barra.
- **Configuración:** la dirección del script de la IFrame API va en la configuración, como `sdk_url` en T-06, para que los tests carguen un reproductor simulado y nunca el real.

**Casos límite**

- **El usuario pausa el vídeo desde el propio recuadro:** `onStateChange` lo refleja y la barra pasa a "Play".
- **Sin sesión de YouTube Premium en el navegador:** pueden salir anuncios dentro del recuadro. La app no lo detecta ni lo evita; es una limitación aceptada (T-27).
- **Un vídeo que ya no existe en una playlist propia:** falla con el error 100 y T-14 lo marca como no disponible, por el evento de fallo.
- **Cambio rápido de servicio** (pulsar "Next" varias veces entre canciones de los dos servicios): solo suena la última pedida, y el motor que pierde queda en pausa.
- **Pestaña en segundo plano:** el avance depende de `onStateChange`, pero el margen de 10 s puede alargarse, como en T-13. Se acepta.
- **Aleatorio:** lo resuelve el servidor (T-13) sin mirar el servicio de cada canción, así que no necesita nada propio en T-10.

**Pistas para los tests**

- Un reproductor de YouTube simulado en `tests/Browser/fixtures`, servido por la dirección de configuración. Expone `YT.Player` con `loadVideoById`, `playVideo`, `pauseVideo`, `seekTo`, `setVolume` y `destroy`. Registra cada llamada y deja al test disparar `onStateChange` y `onError`. Ningún test carga el script real de YouTube.
- El "menos de 500 ms" se mide en el test de navegador, desde el evento de fin hasta `loadVideoById` o hasta `PUT /spotify/player/play`. Los 2 s audibles de D2 quedan en el escenario `@manual`.
- El estado común se lee en el test de navegador con un gancho solo para tests, que se decide al proponer, igual que se hizo con el SDK simulado de T-06.
- El recuadro se mide con `getBoundingClientRect()` a 1280 px y a 375 px, y se comprueba que no está tapado con `document.elementFromPoint()`.

**Fuera de alcance**

- Las filas y puntos de entrada de YouTube Music en la búsqueda (T-11) y en la biblioteca (T-12).
- La reproducción con la pantalla bloqueada en el móvil (pregunta abierta 3).
- Detectar si el navegador tiene sesión de YouTube Premium.
- Comprobar con la Data API si un vídeo se puede insertar antes de reproducirlo.

**Pendiente (no decidido)**

- **Pregunta abierta 11 del discovery:** la Developer Policy de Spotify (III.5) prohíbe integrar sus contenidos con los de otro servicio, y la cola mixta lo hace. Falta que el usuario decida si acepta el riesgo, como con las librerías no oficiales de YouTube Music, o si limita la mezcla. Hay que decidirlo antes de proponer el cambio 2 (la cola mixta). El cambio 1 no mezcla servicios.

**Seguimiento**

- **T-14 (`backlog/tasks/T-14-playlists-mixtas.md`):**
  - el escenario "YouTube Music desconectado", en el que las canciones de YouTube Music salían "Unavailable" y no iban a la cola, queda **sustituido**: suenan igual (decisión del 2026-11-15);
  - T-14 marca la canción solo con reason `playback_error`, no con `player_error` ni `start_timeout`.
- **T-11 y T-12:** usan el formato `youtube_music:video:<id>`. Sus filas de canción de YouTube Music no dependen de la conexión para reproducir.
- **T-13:** sin cambios en su alcance. Su modelo ya guarda el `source` de cada canción. Como T-13 se entrega antes, el cambio de T-10 lleva también deltas MODIFIED sobre la spec que deje T-13:
  - "Spotify sin conectar": "Play" solo se desactiva si la canción actual es de Spotify;
  - el evento de fallo de canción: añade la reason `player_error`;
  - la validación de `source`: acepta `youtube_music` con el formato `youtube_music:video:<id>`;
  - una regla nueva: al empezar a sonar en una pestaña, las demás se pausan, y solo la que suena avanza la cola.
- **T-16 y T-17:** sus paneles no deben tapar el recuadro de vídeo.

**Tamaño y división**

Se puede proponer en **dos cambios**:

1. **Motor de YouTube y barra común (unos 3 puntos):**
   - el composable común, que exige rehacer como "un motor más" la parte de T-13 y T-06 pensada solo para Spotify;
   - el motor de YouTube con el recuadro de vídeo, los controles y la reproducción automática bloqueada;
   - la cola solo de YouTube Music: siguiente, anterior, fin de canción y repetición;
   - los fallos, el script que no carga y una pestaña que suena;
   - sin Spotify conectado, la navegación y el cierre de sesión.
2. **Cola mixta (unos 2 puntos):** el paso entre servicios en los dos sentidos, "Next" entre servicios, el volumen compartido, la canción de Spotify sin Spotify conectado, la playlist mixta de T-14, el evento para T-14 y las escuchas de T-19. Necesita la decisión de la pregunta abierta 11.

**Estimación: 5 puntos**, como T-06: un segundo motor de reproducción con su simulador y el composable común que une los dos. **El riesgo principal es el composable común:** obliga a separar del SDK de Spotify la parte de la cola de T-13, y si T-13 queda muy atada a Spotify puede costar más.

## Definition of Done

- [ ] Acceptance criteria covered by tests, and the `@manual` scenario checked
- [ ] Spec deltas archived in openspec/specs/, including the MODIFIED deltas on `spotify-playback`
- [ ] Work committed on main
- [ ] T-14 updated: "YouTube Music desconectado" replaced, and marking only on reason `playback_error`
