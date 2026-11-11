---
id: T-06
title: Reproducir canciones de Spotify en el navegador
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 5
language: es
---

# T-06 — Reproducir canciones de Spotify en el navegador

## Goal

Que las canciones de Spotify suenen en la pestaña del reproductor con el Web Playback SDK, con los controles básicos del discovery: play, pausa, siguiente, anterior, volumen y seek. La barra de reproductor debe mostrar siempre lo que suena. Es el núcleo del reproductor: sin esto no se cumple la métrica de "uso diario", y T-07, T-08, T-13 y T-14 reproducen a través de la función de arranque que deja esta tarea.

## Context

- **Discovery:** Spotify suena en la pestaña del navegador del reproductor. No se controlan otros dispositivos con Spotify Connect como función general, salvo el plan B móvil (T-15). El Web Playback SDK requiere Premium.
- **Dependencias:**
  - **T-05:** conexión, tokens cifrados y el cliente HTTP compartido de Spotify. Ese cliente tiene timeout de 10 s, un único enfriamiento por `Retry-After` común a todas las funciones y renovación del token bajo `Cache::lock` con rotación del refresh token.
  - **T-21 "Controles del reproductor Aero":** la barra de reproductor y sus controles.
  - **T-03:** el runner de tests de navegador (tests de navegador de Pest 4, Playwright/Chromium).
- **Scopes que T-06 exige a T-05:** `streaming`, `user-read-email`, `user-read-private`, `user-read-playback-state` y `user-modify-playback-state`.
- **Dividida en dos al revisarla:** esta tarea cubre token, SDK, reproducción, controles y desconexión. **T-23** cubre qué pasa cuando la reproducción pasa a otro dispositivo y los errores del SDK y de Spotify, y depende de esta. T-07, T-08, T-13 y T-14 dependen solo de T-06.
- **Límite con T-13:** aquí "siguiente" y "anterior" se mueven **dentro del contexto de Spotify que suena** (un álbum o una playlist). La cola editable, el aleatorio y la repetición son de T-13.
- **Límite con T-07 / T-08:** la búsqueda y la biblioteca, desde donde el usuario elige qué escuchar, llegan después. Por eso T-06 expone la **función del reproductor `play({contextUri?, uris?, offset})`**, que es la que llamarán esas tareas. En los tests se llama directamente.
  - `contextUri` sirve para un álbum, una playlist o un artista.
  - `uris` sirve para pistas sueltas: T-07 reproduce canciones individuales, y `spotify:track:…` no es un `context_uri` válido. También sirve para listas sin contexto en Spotify, como las canciones guardadas ("me gusta") de T-08.
  - Hace falta exactamente uno de los dos.
  - `offset` es `{position}` o `{uri}`.
- **Móvil:** el alcance en el móvil depende de la pregunta abierta 3. El aviso de navegador no compatible es de T-23. El plan B es T-15.

## Acceptance criteria

```gherkin
# --- Endpoint de token para el SDK (backend) -----------------------------------
# La renovación, el cifrado y el bloqueo son de T-05. Aquí solo se prueba cómo responde el endpoint.

Scenario: El endpoint entrega el token actual
  Given Spotify conectado y el servicio de tokens de T-05 devuelve "tok-123" con 3000 segundos de vida
  When el frontend pide "GET /spotify/token" con la sesión iniciada
  Then la respuesta tiene estado 200 y el JSON {"access_token": "tok-123", "expires_in": 3000}
  And la cabecera "Cache-Control" contiene "no-store"
  And el cuerpo de la respuesta no contiene el refresh token guardado

Scenario Outline: El endpoint de token cuando no puede entregar token
  Given <situación>
  When se pide "GET /spotify/token" como petición JSON
  Then la respuesta tiene estado <estado>
  And el JSON tiene "error" = "<código>" y no contiene ningún token

  Examples:
    | situación                                                       | estado | código                     |
    | un visitante sin sesión iniciada                                | 401    | unauthenticated            |
    | el usuario con sesión iniciada y Spotify sin conectar           | 409    | spotify_not_connected      |
    | Spotify conectado y la renovación de T-05 falla con invalid_grant | 409  | spotify_reconnect_required |

# --- Endpoint para empezar un contexto (backend) -------------------------------

Scenario Outline: Empezar a reproducir en el dispositivo web
  Given Spotify conectado y Http::fake para "api.spotify.com" que responde 204
  When el frontend envía "PUT /spotify/player/play" con device_id "web-1" y <entrada>
  Then se envía a Spotify "PUT /v1/me/player/play?device_id=web-1" con el cuerpo <cuerpo>
  And la petición a Spotify lleva el access token como "Authorization: Bearer"
  And la respuesta al frontend tiene estado 204

  Examples:
    | entrada                                                                  | cuerpo                                                                              |
    | context_uri "spotify:album:abc" y offset {"position": 0}                 | {"context_uri": "spotify:album:abc", "offset": {"position": 0}}                     |
    | context_uri "spotify:album:abc" y offset {"uri": "spotify:track:t2"}     | {"context_uri": "spotify:album:abc", "offset": {"uri": "spotify:track:t2"}}         |
    | uris ["spotify:track:t1"] sin offset                                     | {"uris": ["spotify:track:t1"], "offset": {"position": 0}}                           |
    | uris ["spotify:track:t1", "spotify:track:t2"] y offset {"position": 1}   | {"uris": ["spotify:track:t1", "spotify:track:t2"], "offset": {"position": 1}}       |

Scenario Outline: Petición mal formada al endpoint de empezar
  Given Spotify conectado
  When el frontend envía "PUT /spotify/player/play" con device_id "web-1" y <entrada>
  Then la respuesta tiene estado 422
  And no se envía ninguna petición a Spotify

  Examples:
    | entrada                                                       |
    | ni context_uri ni uris                                        |
    | context_uri "spotify:album:abc" y uris ["spotify:track:t1"]   |

Scenario Outline: Fallos del endpoint de empezar
  Given <situación>
  When el frontend envía "PUT /spotify/player/play" con device_id "web-1" y context_uri "spotify:album:abc"
  Then la respuesta tiene estado <estado> y el JSON tiene "error" = "<código>"<extra>
  And la respuesta no es una página de error HTML

  Examples:
    | situación                                                               | estado | código                     | extra                              |
    | un visitante sin sesión iniciada                                        | 401    | unauthenticated            |                                    |
    | Spotify sin conectar                                                    | 409    | spotify_not_connected      |                                    |
    | el cliente de T-05 devuelve el error reconnect_required                 | 409    | spotify_reconnect_required |                                    |
    | Spotify responde 403 con reason "PREMIUM_REQUIRED"                      | 403    | premium_required           |                                    |
    | Spotify responde 404 "Device not found"                                 | 404    | device_not_found           |                                    |
    | Spotify responde 429 con "Retry-After: 7"                               | 429    | rate_limited               | y "retry_after" = 7                |
    | el enfriamiento compartido de T-05 está activo, con 5 segundos restantes, sin llamar a Spotify | 429 | rate_limited | y "retry_after" = 5        |
    | Spotify responde 503                                                    | 502    | spotify_unavailable        |                                    |
    | el cliente de T-05 devuelve el error unavailable (timeout de 10 s)      | 502    | spotify_unavailable        |                                    |

# --- Carga del SDK y dispositivo -----------------------------------------------

Scenario: Se crea el reproductor del SDK con Spotify conectado
  Given Spotify conectado, APP_NAME = "Music" y el SDK simulado
  When se abre cualquier página con sesión iniciada
  Then se crea un único "Spotify.Player" con name = "Music" y volume = 0.5
  And se llama a "connect()"

Scenario: El SDK pide token al backend
  Given el reproductor creado con el SDK simulado y "GET /spotify/token" devuelve "tok-456"
  When el SDK llama al callback "getOAuthToken"
  Then el frontend pide "GET /spotify/token"
  And el callback recibe "tok-456"

Scenario: Controles desactivados hasta que el dispositivo está listo
  Given el reproductor creado y el SDK simulado aún sin emitir "ready"
  When se carga la barra
  Then los botones "Play", "Previous" y "Next" están desactivados
  When el SDK emite "ready" con device_id "web-1"
  Then el botón "Play" está activo

Scenario: Spotify sin conectar
  Given el usuario con sesión iniciada y Spotify sin conectar
  When abre cualquier página
  Then no se pide el script del SDK de Spotify ni se crea ningún "Spotify.Player"
  And la barra muestra "Connect Spotify to play your music." con un enlace a la página de conexión de T-05

@manual
Scenario: El dispositivo aparece en Spotify
  Given Spotify Premium real conectado y el reproductor abierto en Chrome de escritorio
  When se abre la lista de dispositivos en la app oficial de Spotify
  Then aparece un dispositivo llamado como APP_NAME

# --- Reproducción y barra ------------------------------------------------------

Scenario Outline: Empezar a reproducir desde la función del reproductor
  Given el SDK simulado listo con device_id "web-1"
  When se llama a la función del reproductor play(<argumentos>)
  Then se llama a "activateElement()" antes de la petición
  And se envía "PUT /spotify/player/play" con device_id "web-1" y <cuerpo>

  Examples:
    | argumentos                                                             | cuerpo                                                                 |
    | {contextUri: "spotify:album:abc", offset: {position: 0}}               | context_uri "spotify:album:abc" y offset {"position": 0}               |
    | {contextUri: "spotify:album:abc", offset: {uri: "spotify:track:t2"}}   | context_uri "spotify:album:abc" y offset {"uri": "spotify:track:t2"}   |
    | {uris: ["spotify:track:t1"]}                                           | uris ["spotify:track:t1"] y offset {"position": 0}                     |

Scenario: La función del reproductor rechaza argumentos inválidos
  Given el SDK simulado listo con device_id "web-1"
  When se llama a play({}) o a play con contextUri y uris a la vez
  Then la promesa devuelta se rechaza con un error que indica que hace falta exactamente uno de contextUri o uris
  And no se envía ninguna petición a "PUT /spotify/player/play"

Scenario: La barra muestra lo que suena
  Given el SDK simulado listo
  When el SDK emite "player_state_changed" con la pista "Track A" de los artistas "Artist 1" y "Artist 2", duración 180000 ms, posición 10000 ms y paused = false
  Then la barra muestra "Track A", "Artist 1, Artist 2", la carátula del estado, "0:10" y "3:00"
  And el botón muestra "Pause"

Scenario: El progreso avanza solo mientras suena
  Given el SDK simulado emitió un estado con posición 10000 ms y paused = false
  When pasan 2 segundos sin nuevos eventos del SDK
  Then la barra muestra "0:12" (con un margen de 1 segundo)

Scenario: El progreso se para en pausa
  Given el SDK simulado emitió un estado con posición 10000 ms y paused = true
  When pasan 2 segundos sin nuevos eventos del SDK
  Then la barra sigue mostrando "0:10"
  And el botón muestra "Play"

Scenario: El navegador bloquea la reproducción automática
  Given el SDK simulado listo
  When el SDK emite "autoplay_failed"
  Then la barra muestra "Press play to start listening"
  When el usuario pulsa "Play"
  Then se llama a "activateElement()" y después a "resume()"

Scenario: La música sigue al navegar por la app
  Given el SDK simulado listo y un estado de reproducción con "Track A"
  When el usuario navega a otra página de la app con Inertia
  Then "Spotify.Player" se ha creado una sola vez y no se ha llamado a "disconnect()"
  And la barra sigue mostrando "Track A"

@manual
Scenario: El audio suena de verdad en la pestaña
  Given Spotify Premium real conectado y el reproductor abierto en Chrome de escritorio
  When se empieza un álbum real en la posición 0
  Then se oye la primera pista en esa pestaña y la barra coincide con lo que suena

# --- Controles -----------------------------------------------------------------

Scenario: Pausar y reanudar
  Given el SDK simulado con una pista sonando
  When el usuario pulsa "Pause"
  Then se llama a "pause()"
  When el SDK emite un estado con paused = true y el usuario pulsa "Play"
  Then se llama a "resume()"

Scenario: Cambiar el volumen
  Given el SDK simulado con una pista sonando
  When el usuario pone el control de volumen al 30 %
  Then se llama a "setVolume(0.3)"
  And el control muestra "30%"

Scenario: Avanzar a un punto de la canción
  Given el SDK simulado con una pista de 180000 ms sonando
  When el usuario mueve la barra de progreso a 1:30
  Then se llama a "seek(90000)"
  And la barra muestra "1:30"

Scenario: Siguiente y anterior dentro del contexto
  Given el SDK simulado con un estado cuyo track_window tiene pistas anteriores y siguientes
  When el usuario pulsa "Next"
  Then se llama a "nextTrack()"
  When el usuario pulsa "Previous"
  Then se llama a "previousTrack()"

Scenario: No hay pista siguiente
  Given el SDK simulado con "Track C" sonando, última del álbum, y track_window.next_tracks vacío
  When se carga la barra
  Then el botón "Next" está desactivado
  When el usuario pulsa "Next"
  Then no se llama a "nextTrack()"

@manual
Scenario: Controles reales
  Given Spotify Premium real y un álbum real sonando en la pestaña
  When se usan Pause, Play, el volumen al 30 %, el seek a 1:30, Next y Previous (antes y después de los 3 primeros segundos de la pista)
  Then cada acción se oye como se espera y se anota qué hace Previous en cada caso

# --- Desconexión ---------------------------------------------------------------

Scenario: Cerrar sesión para la música
  Given el SDK simulado con una pista sonando
  When el usuario cierra sesión
  Then se llama a "disconnect()" antes de salir de la página

Scenario: Desconectar Spotify con música sonando
  Given el SDK simulado con una pista sonando
  When el usuario pulsa "Disconnect Spotify" (T-05)
  Then se llama a "disconnect()"
  And la barra muestra "Connect Spotify to play your music."
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones del lote (las fijó el orquestador del enriquecimiento)**

- T-05 tiene el único cliente HTTP de Spotify (timeout de 10 s, enfriamiento compartido por `Retry-After`, renovación bajo `Cache::lock` con rotación del refresh token) y sus errores tipados (`unavailable`, `rate_limited`, `reconnect_required`). T-06 solo los usa.
- Solo puede conectarse la cuenta del propietario (`SPOTIFY_OWNER_ID`, en T-05), así que Premium está garantizado al conectar. `account_error` no borra los tokens: es un error más de Spotify y lo muestra T-23.
- Los tests de navegador son los de Pest 4 (Playwright/Chromium) que monta T-03.
- La barra y los controles vienen de T-21.
- La decisión del usuario del 2026-11-09 (qué pasa cuando la reproducción pasa a otro dispositivo) se aplica en T-23.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Textos de la interfaz** (en inglés): "Play", "Pause", "Next", "Previous", "Press play to start listening", "Connect Spotify to play your music.". Los avisos de error son de T-23.
- **`GET /spotify/token`:**
  - Solo con sesión iniciada.
  - Devuelve `{access_token, expires_in}` con `Cache-Control: no-store` y nunca el refresh token.
  - Errores: 401 `unauthenticated`, 409 `spotify_not_connected` y 409 `spotify_reconnect_required`.
  - Cuándo renovar lo decide T-05.
- **`PUT /spotify/player/play`:**
  - Recibe `{device_id, context_uri?, uris?, offset?}` con exactamente uno de `context_uri` o `uris`. Si no se cumple, o falta `device_id`, responde 422.
  - `offset` es `{position}` o `{uri}`, y por defecto `{position: 0}`.
  - Llama a `PUT /v1/me/player/play?device_id=…` con `{context_uri, offset}` o `{uris, offset}`, a través del cliente de T-05.
  - **Errores, que T-23 reutiliza para sus endpoints.** Salen de los errores tipados del cliente de T-05 (`unavailable`, `rate_limited`, `reconnect_required`) y de las respuestas de Spotify:
    - 401 `unauthenticated`;
    - 409 `spotify_not_connected`;
    - 409 `spotify_reconnect_required`;
    - 403 `premium_required` cuando Spotify responde `PREMIUM_REQUIRED`;
    - 404 `device_not_found`;
    - 429 `rate_limited` con `retry_after` en segundos, tanto si responde Spotify como si está activo el enfriamiento compartido;
    - 502 `spotify_unavailable` si Spotify responde 5xx o si el cliente de T-05 corta por timeout.
  - Siempre en JSON, nunca una página HTML.
- **SDK vs Web API:** pausa, reanudar, seek, volumen, siguiente y anterior usan los métodos del SDK. Empezar a reproducir pasa por el backend.
- **Función de arranque:** `play({contextUri?, uris?, offset})` del composable del reproductor.
  - `offset` es `{position}` o `{uri}`, y por defecto `{position: 0}`.
  - Si faltan `contextUri` y `uris`, o vienen los dos, rechaza la promesa sin llamar al backend.
  - Llama primero a `activateElement()`, porque la invocan clics del usuario (T-07, T-08), y luego al endpoint del backend.
- **Dispositivo:** se llama como `APP_NAME`. Hay un único `Spotify.Player` por pestaña, creado en un layout persistente de Inertia. Una recarga completa de la pestaña corta el audio.
- **Volumen:** el control va de 0 a 100 % y se pasa al SDK como 0–1. El volumen inicial es 50 %.
- **Next / Previous:**
  - "Next" se desactiva si `track_window.next_tracks` está vacío.
  - "Previous" siempre llama a `previousTrack()`, y lo que haga Spotify según la posición se anota en la comprobación manual.
- **Progreso:** se interpola en el cliente a partir de `position`, `timestamp` y `paused` del último estado.
- **SDK simulado para los tests:**
  - La URL del script del SDK es configurable. En los tests de navegador apunta a un script falso que crea `window.Spotify.Player`, registra las llamadas a sus métodos y permite emitir eventos desde el test evaluando JavaScript en la página.
  - Las peticiones al backend se comprueban del lado servidor con `Http::fake`.

**Restricciones descubiertas**

- El SDK llama a `getOAuthToken` en cada `connect()` y cada vez que caduca el token, que dura como máximo 60 minutos. Fuente: https://developer.spotify.com/documentation/web-playback-sdk/reference
- `setVolume` acepta valores entre 0 y 1. En iOS no tiene efecto y `getVolume` siempre devuelve 1. `seek` va en milisegundos. Fuente: la misma referencia.
- Algunos navegadores bloquean el audio sin interacción del usuario. `activateElement()`, llamado desde un clic, lo evita, y la referencia avisa del evento `autoplay_failed`. Fuente: la misma referencia.
- `player_state_changed` "puede ejecutarse también a intervalos aleatorios" y no emite el progreso de forma continua. Fuente: la misma referencia.
- **Contradice el discovery:** la página general del SDK dice que es compatible con navegadores de móvil (Android e iOS) y de escritorio, con limitaciones en iOS. Los planes Premium "solo móvil" no sirven. Fuente: https://developer.spotify.com/documentation/web-playback-sdk
- `streaming` es el scope del SDK y requiere Premium. Leer el estado de reproducción y empezar o transferir la reproducción requieren `user-read-playback-state` y `user-modify-playback-state`. Fuente: https://developer.spotify.com/documentation/web-api/concepts/scopes
- EME, el DRM que usa el SDK, solo funciona en contextos seguros: HTTPS, o `localhost` en desarrollo. Fuente: https://developer.mozilla.org/en-US/docs/Web/API/Navigator/requestMediaKeySystemAccess

**Casos límite**

- **Autoplay o radio de Spotify:** al terminar un álbum, Spotify puede rellenar `next_tracks` con recomendaciones. En ese caso "Next" queda activo aunque se haya llegado al final del álbum.
- **`player_state_changed` repetido o fuera de orden:** la barra usa siempre el último estado por `timestamp`.
- **Token a punto de caducar con música sonando:** el SDK vuelve a llamar a `getOAuthToken`. Lo cubre el escenario "El SDK pide token al backend".
- **Cerrar la pestaña sin cerrar sesión:** el SDK desaparece con la pestaña y Spotify da de baja el dispositivo cuando quiere. No hace falta hacer nada.

**Pistas para los tests**

- **Pest (feature, sin Spotify real):** los dos bloques de endpoints, con el servicio de tokens de T-05 simulado y `Http::fake` para `api.spotify.com`. Se usa `Http::assertSent` para comprobar la URL, el cuerpo y la cabecera `Authorization`.
- **Tests de navegador de Pest 4 con el SDK simulado (T-03):** la carga del SDK, el dispositivo, la barra, el progreso, `autoplay_failed`, los controles, "Next" desactivado, la navegación con Inertia, el cierre de sesión y la desconexión de Spotify.
- **`@manual` (requieren Premium real y audio):** se copian como lista de comprobación en el `tasks.md` del cambio.

**Fuera de alcance**

- Lo que pasa cuando la reproducción pasa a otro dispositivo, las dos pestañas y los errores del SDK y de Spotify. Todo eso es T-23.
- La cola editable, el aleatorio y la repetición (T-13). Las pantallas de búsqueda y biblioteca (T-07, T-08).
- El plan B móvil (T-15). Atajos de teclado y Media Session API.

**Pendiente**

- **Pregunta abierta 3 (móvil):** la documentación actual de Spotify dice que el SDK funciona en navegadores móviles, en contra de lo que asume el discovery. Hay que confirmarlo en el spike en el móvil real.
- **Pregunta abierta 10:** las condiciones actuales del development mode de Spotify para el SDK.
- Qué hace exactamente "Previous" según la posición de la canción. Se anota en la comprobación manual.
- Si el volumen se recuerda entre recargas (por ejemplo, en `localStorage`).

**Estimación: 5 puntos.**
- Endpoint de token y endpoint para empezar (contexto o `uris`, el 422 y una tabla de 9 errores), todo en Pest (1 punto): la lógica de tokens, del cliente HTTP y de sus errores tipados es de T-05, así que aquí solo se traduce.
- Carga del SDK, composable en un layout persistente, dispositivo y función de arranque (1,5 puntos): es la parte nueva más delicada.
- Mapeo del estado a la barra con el progreso interpolado y `autoplay_failed` (1 punto).
- Controles y desconexión (0,5 puntos): llamadas directas a métodos del SDK.
- SDK simulado para los tests de navegador y comprobación manual con Premium real (1 punto).

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Estado que expone el composable del reproductor** (lo consumen T-13, T-16, T-17 y T-19), reactivo e independiente del servicio:
  - la canción actual con `source`, `id`, `uri`, `title`, `artists[]`, `album`, `duration_ms` e `image`, sacados de `track_window.current_track`;
  - la posición interpolada en ms y la posición nueva **justo después de un seek**, sin esperar otro estado del SDK;
  - `paused`, `timestamp` y si hay reproducción local (`hasLocalPlayback`).
- **Seeks de la barra:** T-19a añade al composable una marca de los seeks que vienen de la barra de progreso, para distinguirlos de un reinicio de la canción.
- **Panel activo:** el layout persistente guarda un único estado de panel (`null` | `'lyrics'` | `'visualizer'`), compartido por T-16 y T-17, que son excluyentes.
- **Layout persistente:** T-25 lo convierte en `AeroAppLayout` (con la navegación Aero). El reproductor vive en un solo componente de layout y `Spotify.Player` no se vuelve a crear al navegar.
- **T-13 (cambio 2, cola):** modifica "Siguiente y anterior dentro del contexto" y "No hay pista siguiente": en modo cola, "Next" y "Previous" mueven la cola de la app y "Next" se desactiva si no queda nada pendiente. T-13 añade además `PUT /spotify/player/modes` para desactivar el aleatorio y la repetición propios de Spotify.

**Seguimiento de aero-design-system (T-03, 2026-11-11)**

- Pest browser no puede inyectar un script antes de cargar la página desde `visit()` ni bloquear peticiones. Para falsear el SDK de Spotify: leer la URL del SDK de la configuración y, en los tests, apuntarla a una ruta solo de pruebas que sirva un SDK falso.
- Las pruebas con sesión usan `signedInOwnerVisit()` de `tests/Pest.php` (no hay `actingAs`). Usar selectores explícitos (`#id`, `.clase`, `[atributo]`): una etiqueta suelta como `h1` se busca como texto y espera hasta el timeout.

**Seguimiento de aero-player-controls (T-21, 2026-11-11)**

- La barra es `AeroPlayerBar` (`resources/js/components/aero/AeroPlayerBar.vue`) y solo emite eventos: el estado lo lleva el padre. Props: `title`, `artist`, `coverSrc`, `playing`, `position` y `duration` en segundos, `volume` (0–100) y `previousDisabled`, `playDisabled`, `nextDisabled`, `seekDisabled`. Eventos: `v-model:position`, `v-model:volume`, `toggle-play`, `previous` y `next`.
- `formatTime` (`resources/js/lib/formatTime.ts`) da `m:ss`, y `h:mm:ss` a partir de una hora. El seek ya expone `aria-valuetext` "<transcurrido> of <total>".
- Siguen en T-06 el layout persistente que monta la barra y el efecto real del volumen (también en iOS).
- En `/styleguide` la barra va dentro de un panel y es más estrecha que en el layout real. Al montarla, revisar el reparto de filas a 375 px.
- Tests: un recorrido con Tab que pasa del último control cuelga Chrome sin cabeza ("Target crashed"). Acotar los recorridos a los controles habilitados.

## Definition of Done

- [ ] Criterios automatizables cubiertos por tests; los @manual en el checklist del cambio
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
