---
id: T-23
title: Spotify en otro dispositivo y errores de reproducción
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 4
language: es
---

# T-23 — Spotify en otro dispositivo y errores de reproducción

## Goal

Cuando la reproducción de Spotify sale del reproductor web o falla, el reproductor debe decirlo con un mensaje claro y dejar el resto de la app funcionando. Pasa cuando:
- el usuario empieza a reproducir en otro dispositivo o en otra pestaña;
- el navegador no es compatible;
- el token no sirve;
- la cuenta pierde Premium;
- una canción no se puede reproducir;
- se corta la red;
- Spotify no responde.

Si el problema es que la música sonaba en otro dispositivo, el usuario puede traerla de vuelta con un clic. Sin esto, un fallo deja la barra congelada sin explicación, y eso choca con el objetivo de usar el reproductor cada día en lugar de la app oficial.

## Context

- **Sale de T-06** al revisarla: T-06 deja la reproducción "feliz" (token, SDK, controles) y esta tarea, los caminos alternativos. Depende de T-06 y usa:
  - el SDK simulado de T-06;
  - su tabla de errores de endpoints (401 `unauthenticated`, 403 `premium_required`, 404 `device_not_found`, 429 `rate_limited`);
  - su función de arranque.
- **Otras dependencias:**
  - **T-05:** el cliente HTTP compartido de Spotify, con timeout de 10 s y enfriamiento compartido por `Retry-After`, y los scopes `user-read-playback-state` y `user-modify-playback-state`.
  - **T-21:** la zona de avisos de la barra.
  - **T-03:** los tests de navegador de Pest 4.
- **Decisión del usuario del 2026-11-09:** qué hace el reproductor cuando la reproducción pasa a otro dispositivo (ver Notes).
- **Móvil:** el aviso de navegador no compatible es el punto de partida del plan B (T-15). Su alcance depende de la pregunta abierta 3.

## Acceptance criteria

```gherkin
# --- Endpoints de transferencia y dispositivo activo (backend) -----------------

Scenario: Transferir la reproducción al dispositivo web
  Given Spotify conectado y Http::fake para "api.spotify.com" que responde 204
  When el frontend envía "PUT /spotify/player/transfer" con device_id "web-1"
  Then se envía a Spotify "PUT /v1/me/player" con el cuerpo {"device_ids": ["web-1"], "play": true}
  And la respuesta al frontend tiene estado 204

Scenario: Leer el dispositivo activo
  Given Spotify conectado y "GET /v1/me/player" responde 200 con device.name "Kitchen Speaker" e is_playing true
  When el frontend pide "GET /spotify/player/current"
  Then la respuesta tiene estado 200 y el JSON {"device_name": "Kitchen Speaker", "is_playing": true}

Scenario: No hay reproducción activa en ningún dispositivo
  Given Spotify conectado y "GET /v1/me/player" responde 204
  When el frontend pide "GET /spotify/player/current"
  Then la respuesta tiene estado 204

Scenario Outline: Fallos de los endpoints de transferencia y dispositivo activo
  Given <situación>
  When el frontend llama a <endpoint>
  Then la respuesta tiene estado <estado> y el JSON tiene "error" = "<código>"<extra>
  And la respuesta no es una página de error HTML

  Examples:
    | endpoint                       | situación                                                                  | estado | código                     | extra               |
    | "PUT /spotify/player/transfer" | un visitante sin sesión iniciada                                           | 401    | unauthenticated            |                     |
    | "PUT /spotify/player/transfer" | Spotify sin conectar                                                       | 409    | spotify_not_connected      |                     |
    | "PUT /spotify/player/transfer" | el cliente de T-05 devuelve el error reconnect_required                    | 409    | spotify_reconnect_required |                     |
    | "PUT /spotify/player/transfer" | Spotify responde 403 con reason "PREMIUM_REQUIRED"                         | 403    | premium_required           |                     |
    | "PUT /spotify/player/transfer" | Spotify responde 404 "Device not found"                                    | 404    | device_not_found           |                     |
    | "PUT /spotify/player/transfer" | Spotify responde 429 con "Retry-After: 7"                                  | 429    | rate_limited               | y "retry_after" = 7 |
    | "PUT /spotify/player/transfer" | el enfriamiento compartido de T-05 está activo, con 5 s restantes, sin llamar a Spotify | 429 | rate_limited     | y "retry_after" = 5 |
    | "PUT /spotify/player/transfer" | Spotify responde 503                                                       | 502    | spotify_unavailable        |                     |
    | "PUT /spotify/player/transfer" | el cliente de T-05 devuelve el error unavailable (timeout de 10 s)         | 502    | spotify_unavailable        |                     |
    | "GET /spotify/player/current"  | un visitante sin sesión iniciada                                           | 401    | unauthenticated            |                     |
    | "GET /spotify/player/current"  | Spotify sin conectar                                                       | 409    | spotify_not_connected      |                     |
    | "GET /spotify/player/current"  | el cliente de T-05 devuelve el error reconnect_required                    | 409    | spotify_reconnect_required |                     |
    | "GET /spotify/player/current"  | Spotify responde 403 con reason "PREMIUM_REQUIRED"                         | 403    | premium_required           |                     |
    | "GET /spotify/player/current"  | Spotify responde 429 con "Retry-After: 7"                                  | 429    | rate_limited               | y "retry_after" = 7 |
    | "GET /spotify/player/current"  | el enfriamiento compartido de T-05 está activo, con 5 s restantes, sin llamar a Spotify | 429 | rate_limited     | y "retry_after" = 5 |
    | "GET /spotify/player/current"  | Spotify responde 503                                                       | 502    | spotify_unavailable        |                     |
    | "GET /spotify/player/current"  | el cliente de T-05 devuelve el error unavailable (timeout de 10 s)         | 502    | spotify_unavailable        |                     |

# --- Otro dispositivo (decisión del 2026-11-09) --------------------------------

Scenario: La reproducción pasa a otro dispositivo con nombre
  Given el SDK simulado con "Track A" sonando en el dispositivo web
  And "GET /spotify/player/current" devuelve device_name "Kitchen Speaker"
  When el SDK emite "player_state_changed" con estado null
  Then se pide "GET /spotify/player/current" una sola vez
  And el botón de la barra muestra "Play" y el progreso deja de avanzar
  And la barra muestra "Playing on Kitchen Speaker" y el botón "Play here"

Scenario Outline: No se puede saber el nombre del otro dispositivo
  Given el SDK simulado con "Track A" sonando en el dispositivo web
  And "GET /spotify/player/current" <respuesta>
  When el SDK emite "player_state_changed" con estado null
  Then la barra muestra "Playing on another device" y el botón "Play here"

  Examples:
    | respuesta                                  |
    | devuelve 200 con device_name vacío         |
    | responde 429 rate_limited                  |
    | responde 502 spotify_unavailable           |

Scenario: La reproducción se detiene en todas partes
  Given el SDK simulado con "Track A" sonando en el dispositivo web
  And "GET /spotify/player/current" responde 204
  When el SDK emite "player_state_changed" con estado null
  Then la barra no muestra ningún aviso "Playing on"
  And sigue mostrando "Track A" con el botón "Play"

Scenario: Recuperar la reproducción en la web
  Given la barra muestra "Playing on Kitchen Speaker" y el SDK simulado tiene device_id "web-1"
  When el usuario pulsa "Play here"
  Then se llama a "activateElement()"
  And se envía "PUT /spotify/player/transfer" con device_id "web-1"
  When el SDK emite "player_state_changed" con un estado de "Track A" y paused = false
  Then desaparece el aviso "Playing on Kitchen Speaker"
  And el botón muestra "Pause"

Scenario Outline: Falla la transferencia de vuelta a la web
  Given la barra muestra "Playing on Kitchen Speaker"
  And "PUT /spotify/player/transfer" responde <respuesta>
  When el usuario pulsa "Play here"
  Then la barra muestra "Couldn't move playback here. Try again."
  And el botón "Play here" sigue disponible

  Examples:
    | respuesta            |
    | 404 device_not_found    |
    | 429 rate_limited        |
    | 502 spotify_unavailable |

Scenario: La transferencia falla porque la cuenta no tiene Premium
  Given la barra muestra "Playing on Kitchen Speaker"
  And "PUT /spotify/player/transfer" responde 403 premium_required
  When el usuario pulsa "Play here"
  Then la barra muestra "Spotify Premium is required to play music here."
  And la barra no muestra "Couldn't move playback here. Try again."
  And los botones de Spotify están desactivados

@manual
Scenario: Otro dispositivo real y vuelta a la web
  Given Spotify Premium real con un álbum sonando en el reproductor web
  When se empieza a reproducir con la misma cuenta en el móvil o en otro equipo
  Then el reproductor web muestra "Playing on" seguido del nombre real de ese dispositivo
  When se pulsa "Play here"
  Then la música vuelve a oírse en la pestaña y el otro dispositivo se calla

@manual
Scenario: Dos pestañas del reproductor
  Given el reproductor abierto en dos pestañas y un álbum sonando en la primera
  When se empieza a reproducir desde la segunda pestaña
  Then solo suena la segunda
  And la primera muestra "Playing on" seguido del nombre del dispositivo y el botón "Play here"

# --- Errores del SDK -----------------------------------------------------------

Scenario: Navegador donde el SDK no puede iniciarse
  Given Spotify conectado y el SDK simulado configurado para emitir "initialization_error" al conectar
  When se abre cualquier página con sesión iniciada
  Then nada más cargar, la barra muestra "Spotify can't play in this browser."
  And los botones de Spotify están desactivados
  And se puede navegar entre páginas de la app sin errores de JavaScript sin capturar

Scenario: La cuenta no tiene Premium
  Given el SDK simulado listo
  When el SDK emite "account_error"
  Then la barra muestra "Spotify Premium is required to play music here."
  And los botones de Spotify están desactivados
  And no se vuelve a llamar a "connect()"
  And no se envía ninguna petición para desconectar Spotify y los tokens siguen guardados

Scenario Outline: El token no sirve
  Given el SDK simulado listo
  And "GET /spotify/token" responde <respuesta>
  When el SDK emite "authentication_error"
  Then <resultado>

  Examples:
    | respuesta                          | resultado                                                                                              |
    | 200 con un token nuevo             | se llama a "disconnect()" y a "connect()" una sola vez y no se muestra ningún aviso                    |
    | 409 spotify_reconnect_required     | la barra muestra "Reconnect Spotify to keep listening." con un enlace a la página de conexión de T-05 |

Scenario: El token sigue sin servir tras reconectar
  Given el SDK simulado ya reconectó una vez por "authentication_error"
  When el SDK vuelve a emitir "authentication_error"
  Then la barra muestra "Reconnect Spotify to keep listening."
  And no se vuelve a llamar a "connect()"

Scenario: Canción que no se puede reproducir
  Given el SDK simulado listo
  When el SDK emite "playback_error" con el mensaje "Cannot perform operation; no list was loaded."
  Then la barra muestra "This song can't be played."
  And el botón "Play" sigue activo y la función de arranque de T-06 puede empezar otro contexto

Scenario: Se corta la red y vuelve
  Given el SDK simulado con una pista sonando y device_id "web-1"
  When el SDK emite "not_ready" con device_id "web-1"
  Then la barra muestra "Connection to Spotify lost. Reconnecting…"
  And los botones de Spotify están desactivados
  When el SDK emite "ready" con device_id "web-2"
  Then el aviso desaparece y los botones vuelven a estar activos
  And la siguiente llamada a la función de arranque usa device_id "web-2"

# --- Spotify no disponible -----------------------------------------------------

Scenario Outline: Spotify no está disponible
  Given Spotify conectado y <situación>
  When <acción>
  Then la barra muestra "Spotify is unavailable right now. Try again later."
  And se puede navegar entre páginas de la app sin errores de JavaScript sin capturar

  Examples:
    | situación                                                                     | acción                                                  |
    | el script del SDK no se puede descargar                                       | se abre cualquier página con sesión iniciada            |
    | el SDK simulado listo y "PUT /spotify/player/play" responde 502 spotify_unavailable | se llama a la función de arranque para empezar un álbum |
    | el SDK simulado listo y "PUT /spotify/player/play" responde 429 rate_limited  | se llama a la función de arranque para empezar un álbum |

Scenario Outline: Otros fallos al empezar un contexto
  Given el SDK simulado listo
  And "PUT /spotify/player/play" responde <respuesta>
  When se llama a la función de arranque para empezar un álbum
  Then la barra muestra "<aviso>"

  Examples:
    | respuesta            | aviso                                              |
    | 403 premium_required | Spotify Premium is required to play music here.    |
    | 404 device_not_found | Couldn't reach the web player. Reload the page.    |

@manual
Scenario: Fallos reales
  Given Spotify Premium real y el reproductor abierto en Chrome de escritorio
  When se corta la red con DevTools a mitad de canción y se vuelve a conectar, se reproduce una canción no disponible en el país de la cuenta y se abre el reproductor en un navegador sin EME
  Then cada caso muestra el aviso de su escenario automatizado
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **La reproducción pasa a otro dispositivo:** cuando el usuario empieza a reproducir con la misma cuenta en otro sitio, el reproductor web deja su estado en pausa, muestra "Playing on <nombre del dispositivo>" y ofrece un botón para traer la reproducción de vuelta a la web. Al pulsarlo, la reproducción se transfiere al reproductor web.

**Decisiones del lote (las fijó el orquestador del enriquecimiento)**

- El cliente HTTP de Spotify es de T-05, con su enfriamiento por `Retry-After` y sus errores tipados (`unavailable`, `rate_limited`, `reconnect_required`). Los tests de navegador son los de Pest 4 (T-03). La zona de avisos de la barra es de T-21.
- Solo puede conectarse la cuenta del propietario (`SPOTIFY_OWNER_ID`, en T-05), así que Premium está garantizado al conectar. `account_error` **no borra los tokens**: es un error más de Spotify que esta tarea muestra.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Textos de la interfaz** (en inglés):
  - "Play here", "Playing on <device>", "Playing on another device", "Couldn't move playback here. Try again."
  - "Spotify can't play in this browser.", "Spotify Premium is required to play music here.", "Reconnect Spotify to keep listening."
  - "This song can't be played.", "Connection to Spotify lost. Reconnecting…", "Spotify is unavailable right now. Try again later.", "Couldn't reach the web player. Reload the page."
  - La decisión del usuario fija el comportamiento, pero no el texto del botón: "Play here" es una propuesta.
- **Endpoints nuevos:**
  - `PUT /spotify/player/transfer {device_id}` llama a `PUT /v1/me/player` con `{device_ids: [id], play: true}`.
  - `GET /spotify/player/current` llama a `GET /v1/me/player` y devuelve `{device_name, is_playing}`, o 204 si Spotify responde 204.
  - Los dos usan la tabla de errores de T-06:
    - 401 `unauthenticated`, 409 `spotify_not_connected` y 409 `spotify_reconnect_required`;
    - 403 `premium_required`, 404 `device_not_found`;
    - 429 `rate_limited` con `retry_after`;
    - 502 `spotify_unavailable` si Spotify responde 5xx o si el cliente de T-05 corta por timeout.
- **"Play here" con un 403 `premium_required`:** muestra el aviso de Premium, no "Couldn't move playback here. Try again.".
- **Detección de otro dispositivo:**
  - Se dispara cuando el estado del SDK pasa a `null` después de haber tenido un estado. Entonces se pide una sola vez `GET /spotify/player/current`.
  - Si hay nombre, se muestra "Playing on <name>". Si el nombre llega vacío o la petición falla, se muestra "Playing on another device". Si responde 204, no se muestra aviso porque no suena nada en ningún sitio.
  - No se consulta Spotify periódicamente.
- **"Play here"** llama a `activateElement()` en el mismo clic y luego a la transferencia. El aviso desaparece cuando el SDK vuelve a emitir un estado no nulo.
- **Cuándo aparece cada aviso:**
  - `initialization_error` se muestra nada más cargar la página, sin esperar a que el usuario pulse Play.
  - `account_error` desactiva los controles, no reintenta y no toca los tokens.
  - `authentication_error` reconecta una sola vez (`disconnect()` + `connect()`, que vuelve a pedir el token). A la segunda, o si el token responde 409, se muestra el aviso de volver a conectar.
- **`playback_error`** no desactiva los controles y no salta a la siguiente pista (ver Pendiente).
- **`not_ready` → `ready`:** se guarda el `device_id` nuevo, porque el SDK puede cambiarlo al reconectar. La música no se reanuda sola (ver Pendiente).
- **429:** al empezar un contexto, un 429 muestra el aviso de Spotify no disponible. No se reintenta en bucle, porque el enfriamiento compartido de T-05 ya evita repetir peticiones.

**Restricciones descubiertas**

- El SDK emite `ready`, `not_ready` (normalmente por falta de conexión), `player_state_changed`, `autoplay_failed`, `initialization_error` (lo más probable es que el navegador no soporte EME), `authentication_error` (el token de `getOAuthToken` no sirve), `account_error` (la cuenta no tiene Premium válido) y `playback_error` (falla la carga o la reproducción de una pista). Fuente: https://developer.spotify.com/documentation/web-playback-sdk/reference
- La referencia no dice qué emite `player_state_changed` cuando la reproducción pasa a otro dispositivo. Solo documenta que `getCurrentState` resuelve `null` cuando no se reproduce en el SDK. La detección por estado `null` hay que verificarla con el SDK real. Fuente: la misma referencia.
- Sin `activateElement()` llamado desde un clic, la reproducción transferida empieza en pausa. Fuente: la misma referencia.
- Transferir la reproducción y leer el dispositivo activo requieren `user-modify-playback-state` y `user-read-playback-state`. Fuente: https://developer.spotify.com/documentation/web-api/concepts/scopes
- EME solo funciona en contextos seguros: si producción se sirve por HTTP, el SDK emite `initialization_error`. Fuente: https://developer.mozilla.org/en-US/docs/Web/API/Navigator/requestMediaKeySystemAccess

**Casos límite**

- **Dos pestañas:** las dos crean un dispositivo con el mismo nombre (`APP_NAME`), así que la pestaña que pierde el audio muestra "Playing on <APP_NAME>", lo que puede confundir.
- **Recarga con música sonando:** el dispositivo anterior desaparece y "Play here" puede fallar con 404. Se muestra "Couldn't move playback here. Try again.".
- **Pistas no disponibles en el mercado:** T-07 y T-08 pueden marcarlas antes con `is_playable` (pidiendo `market=from_token`). Aquí solo se trata el `playback_error`.
- **Premium perdido con una canción sonando:** el `account_error` puede llegar en cualquier momento. Los controles se desactivan sin reintentos.
- **Varios eventos de error seguidos:** la barra muestra el último aviso, no una pila de avisos.

**Pistas para los tests**

- **Pest (feature):** los endpoints de transferencia y de dispositivo activo con `Http::fake`, más la tabla de errores (`Http::assertSent` para el cuerpo `{"device_ids": ["web-1"], "play": true}`).
- **Tests de navegador de Pest 4 con el SDK simulado de T-06:**
  - todos los escenarios de otro dispositivo y de errores;
  - las respuestas del backend, simuladas en el servidor;
  - la falta de errores de JavaScript sin capturar, comprobada con la aserción de errores de JS del plugin de navegador de Pest 4;
  - el script del SDK que no se descarga, simulado apuntando su URL configurable a una ruta que responde 404.
- **`@manual` (requieren Premium real):** el otro dispositivo real, las dos pestañas y los fallos reales. Van como lista de comprobación en el `tasks.md` del cambio.

**Fuera de alcance**

- Controlar otros dispositivos con Spotify Connect como función general. Solo se lee el nombre del activo y se trae la reproducción a la web.
- El plan B móvil (T-15): aquí solo se avisa de que el navegador no es compatible.
- La reproducción feliz, los controles y la desconexión (T-06).

**Pendiente**

- **Verificar con el SDK real** que, al pasar la reproducción a otro dispositivo, `player_state_changed` llega con estado `null`. Si no es así, hay que cambiar la detección, por ejemplo comparando el `device_id` de `GET /v1/me/player`.
- Si, al fallar una canción dentro de una lista, se pasa sola a la siguiente. Es la misma decisión pendiente en T-10.
- Si, al volver la conexión tras un `not_ready`, la música se reanuda sola.
- Cómo distinguir las dos pestañas, ya que ambos dispositivos se llaman igual (por ejemplo, que una pestaña nueva cierre el reproductor de la anterior).
- Si un 404 `device_not_found` al empezar un contexto debe reconectar el SDK y reintentar una vez en lugar de pedir recargar.
- **Pregunta abierta 3:** si el aviso de navegador no compatible lleva al plan B de T-15 cuando exista.

**Estimación: 4 puntos.**
- Dos endpoints con su tabla de 17 filas de errores en Pest (0,5 puntos): reutilizan el cliente de T-05 y el mapeo de T-06.
- Detección de otro dispositivo, "Play here" y sus fallos, incluido el 403 (1 punto): estado `null`, una consulta y una transferencia.
- Seis tipos de error del SDK y de Spotify, con sus avisos y estados de los controles (1,5 puntos).
- Unas 30 filas de Examples en tests de navegador con el SDK simulado de T-06, más la comprobación manual (1 punto): el simulador ya existe, pero cada fila necesita su respuesta simulada en el servidor y su evento del SDK.

## Definition of Done

- [ ] Criterios automatizables cubiertos por tests; los @manual en el checklist del cambio
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
