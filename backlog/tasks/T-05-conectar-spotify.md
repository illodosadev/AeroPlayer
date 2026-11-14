---
id: T-05
title: Conectar la cuenta de Spotify
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 6
language: es
---

# T-05 — Conectar la cuenta de Spotify

## Goal

Conectar la cuenta de Spotify Premium del propietario, y solo esa, guardando sus tokens cifrados en el servidor. T-05 entrega además **el cliente compartido de la Web API de Spotify** que usan T-06, T-07, T-08, T-15, T-23 y T-24. Ese cliente da siempre un token válido, renueva el access token solo, respeta el límite de peticiones de Spotify y convierte los fallos en errores tipados (`not_connected`, `reconnect_required`, `unavailable`, `rate_limited`) que cada tarea muestra a su manera. Así las credenciales personales no quedan expuestas, y ninguna tarea tiene que reimplementar la autenticación ni el manejo de errores de Spotify.

## Context

El discovery pide conectar la cuenta de Spotify Premium y guardar los tokens de forma segura en el servidor (necesidad no funcional). Solo hay un usuario, así que hay como mucho una conexión de Spotify. La app de Spotify se registra en **development mode** con el usuario como propietario. Las condiciones actuales de ese modo se han verificado al enriquecer (ver "Restricciones descubiertas", que responde a la pregunta abierta 10).

- **Depende de:** T-02 (login). Todas las rutas de esta tarea exigen sesión.
- **Dependen de esta:** T-06, T-07, T-08, T-15, T-23 y T-24, que usan el cliente compartido y los scopes que se piden aquí.
- **Interfaz en inglés** (decisión de T-01): los textos de la UI de los escenarios están en inglés.

Decisiones tomadas con el usuario el 2026-11-09 (detalle en Notes):
- Una cuenta sin Premium se rechaza.
- **"Solo mi cuenta":** solo se acepta la cuenta de Spotify del propietario, cuyo id se configura en el `.env`.

## Acceptance criteria

```gherkin
# --- Conectar -----------------------------------------------------------------

Scenario: Iniciar la conexión redirige a Spotify con los scopes y un state
  Given el usuario con sesión iniciada y Spotify sin conectar
  When pulsa "Connect Spotify" (GET "/spotify/connect")
  Then la respuesta redirige a "https://accounts.spotify.com/authorize"
  And la URL lleva response_type=code, el client_id configurado, el redirect_uri configurado y show_dialog=true
  And el parámetro scope contiene exactamente los scopes de la lista de "Reglas propuestas"
  And el parámetro state es una cadena aleatoria de al menos 40 caracteres, igual a la guardada en la sesión

Scenario: Conectar la cuenta del propietario
  Given SPOTIFY_OWNER_ID="owner-123"
  And el usuario con sesión iniciada, Spotify sin conectar y un state "state-abc" guardado en la sesión
  And Spotify (simulado) devuelve para el code "code-123" el access token "BQ-access-123", el refresh token "AQ-refresh-456" y expires_in 3600
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me" con id "owner-123" y display_name "Alice"
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify"
  And la página muestra "Spotify connected" junto a "Alice"
  And la tabla de conexiones tiene exactamente una fila, con la cuenta "owner-123", los scopes concedidos y la caducidad del token dentro de 3600 segundos
  And el state ya no está en la sesión

Scenario: Los tokens se guardan cifrados
  Given Spotify conectado con el access token "BQ-access-123" y el refresh token "AQ-refresh-456"
  When se leen las columnas de la conexión directamente en la base de datos, sin pasar por el modelo
  Then ningún valor contiene "BQ-access-123" ni "AQ-refresh-456"
  And al leer la conexión a través de la app se obtienen "BQ-access-123" y "AQ-refresh-456"

Scenario: Los tokens no llegan al navegador en la página de ajustes
  Given Spotify conectado con el access token "BQ-access-123" y el refresh token "AQ-refresh-456"
  When el usuario abre "/settings/spotify"
  Then las props de Inertia de la página no contienen "BQ-access-123" ni "AQ-refresh-456"

Scenario: Reconectar la misma cuenta renueva los tokens y mantiene una sola conexión
  Given Spotify conectado con la cuenta "owner-123" y el refresh token "AQ-refresh-456"
  When el usuario completa otra vez la conexión con la cuenta "owner-123" y Spotify devuelve el refresh token "AQ-nuevo-777"
  Then la tabla de conexiones tiene exactamente una fila, con la cuenta "owner-123"
  And al leer la conexión a través de la app se obtiene el refresh token "AQ-nuevo-777"

# --- Cuenta rechazada ---------------------------------------------------------

Scenario: Otra cuenta de Spotify se rechaza
  Given SPOTIFY_OWNER_ID="owner-123"
  And el usuario con sesión iniciada y un state "state-abc" en la sesión
  And el canje del code "code-123" devuelve tokens válidos
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me" con id "otra-cuenta-999"
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Only the owner's Spotify account can be connected."
  And no se guarda ninguna conexión ni ningún token

Scenario: Otra cuenta de Spotify no toca la conexión existente del propietario
  Given SPOTIFY_OWNER_ID="owner-123"
  And Spotify conectado con la cuenta "owner-123" y el refresh token "AQ-refresh-456"
  And un state "state-abc" en la sesión
  And el canje del code "code-123" devuelve tokens válidos
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me" con id "otra-cuenta-999"
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Only the owner's Spotify account can be connected."
  And la tabla de conexiones tiene exactamente una fila, con la cuenta "owner-123"
  And al leer la conexión a través de la app se obtiene el refresh token "AQ-refresh-456"

Scenario: Cuenta del propietario sin Premium según el campo "product"
  Given SPOTIFY_OWNER_ID="owner-123"
  And el usuario con sesión iniciada y un state "state-abc" en la sesión
  And el canje del code "code-123" devuelve tokens válidos
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me" con id "owner-123" y product "free"
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Spotify Premium is required."
  And no se guarda ninguna conexión ni ningún token

Scenario: Cuenta que no está en la lista de usuarios de la app de Spotify
  Given el usuario con sesión iniciada y un state "state-abc" en la sesión
  And el canje del code "code-123" devuelve tokens válidos
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me" con estado 403
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "This Spotify account is not allowed to use this app."
  And no se guarda ninguna conexión ni ningún token

# --- Callback inválido o rechazado --------------------------------------------

Scenario: El usuario cancela en la pantalla de Spotify
  Given el usuario con sesión iniciada, Spotify sin conectar y un state "state-abc" en la sesión
  When llega el callback "/spotify/callback?error=access_denied&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Spotify connection was cancelled."
  And no se envía ninguna petición al endpoint de tokens de Spotify
  And Spotify sigue sin conectar

Scenario: State manipulado
  Given el usuario con sesión iniciada y un state "state-abc" en la sesión
  When llega el callback "/spotify/callback?code=code-123&state=state-otro"
  Then se redirige a "/settings/spotify" con el mensaje "Could not connect Spotify. Please try again."
  And no se envía ninguna petición al endpoint de tokens de Spotify
  And no se guarda ninguna conexión

Scenario: Falta el state
  Given el usuario con sesión iniciada y un state "state-abc" en la sesión
  When llega el callback "/spotify/callback?code=code-123" sin state
  Then se redirige a "/settings/spotify" con el mensaje "Could not connect Spotify. Please try again."
  And no se envía ninguna petición al endpoint de tokens de Spotify

Scenario: Callback repetido (el state es de un solo uso)
  Given un callback con code "code-123" y state "state-abc" ya procesado con éxito
  When llega otra vez el mismo callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Could not connect Spotify. Please try again."
  And no se envía ninguna petición nueva al endpoint de tokens de Spotify
  And la conexión existente no cambia

Scenario: Code caducado o ya usado
  Given el usuario con sesión iniciada y un state "state-abc" en la sesión
  And Spotify (simulado) responde al canje del code "code-viejo" con estado 400 y error "invalid_grant"
  When llega el callback "/spotify/callback?code=code-viejo&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Could not connect Spotify. Please try again."
  And no se guarda ninguna conexión

Scenario: Callback sin sesión iniciada
  Given un visitante sin sesión
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/login"
  And no se envía ninguna petición a Spotify

Scenario: Spotify no responde durante la conexión
  Given el usuario con sesión iniciada y un state "state-abc" en la sesión
  And Spotify (simulado) responde al canje del code "code-123" con estado 503
  When llega el callback "/spotify/callback?code=code-123&state=state-abc"
  Then se redirige a "/settings/spotify" con el mensaje "Spotify is not responding right now. Try again in a moment."
  And no se guarda ninguna conexión

Scenario Outline: Spotify no está configurado
  Given la app con todas las variables de Spotify configuradas salvo <variable>, que está vacía
  When el usuario pide "GET /spotify/connect"
  Then se redirige a "/settings/spotify", que muestra "Spotify is not configured."
  And "/settings/spotify" no muestra el botón "Connect Spotify"
  And no se envía ninguna petición a Spotify

  Examples:
    | variable              |
    | SPOTIFY_CLIENT_ID     |
    | SPOTIFY_CLIENT_SECRET |
    | SPOTIFY_OWNER_ID      |

# --- Cliente compartido: sin conexión utilizable ------------------------------

Scenario: El cliente sin Spotify conectado
  Given Spotify sin conectar (no hay ninguna fila de conexión)
  When el cliente hace una petición "GET https://api.spotify.com/v1/me"
  Then no se envía ninguna petición a Spotify
  And el cliente lanza el error "not_connected"

Scenario: El cliente con la conexión pendiente de reconectar
  Given una conexión de "owner-123" con el estado "reconnect_required" y sin tokens
  When el cliente hace una petición "GET https://api.spotify.com/v1/me"
  Then no se envía ninguna petición a Spotify
  And el cliente lanza el error "reconnect_required"

# --- Cliente compartido: renovación del token ---------------------------------

Scenario: El token caducado se renueva solo
  Given Spotify conectado con el access token caducado hace 10 segundos y el refresh token "AQ-refresh-456"
  And Spotify (simulado) responde al refresh con el access token "BQ-nuevo-789" y expires_in 3600, sin refresh token nuevo
  When el cliente hace una petición "GET https://api.spotify.com/v1/me"
  Then se envía una única petición grant_type=refresh_token con el refresh token "AQ-refresh-456"
  And la petición a "/v1/me" lleva "Authorization: Bearer BQ-nuevo-789"
  And al leer la conexión a través de la app se obtienen "BQ-nuevo-789", con caducidad dentro de 3600 segundos, y el refresh token "AQ-refresh-456"

Scenario: Spotify rota el refresh token
  Given Spotify conectado con el access token caducado
  And Spotify (simulado) responde al refresh con el access token "BQ-nuevo-789" y el refresh token "AQ-rotado-999"
  When el cliente hace una petición a la Web API
  Then al leer la conexión a través de la app se obtiene el refresh token "AQ-rotado-999"

Scenario: Un token que caduca en menos de 60 segundos se renueva antes de usarse
  Given Spotify conectado con un access token que caduca dentro de 30 segundos
  When el cliente hace una petición a la Web API
  Then se envía la petición de refresh antes de la petición a la Web API

Scenario: Un token vigente no se renueva
  Given Spotify conectado con un access token que caduca dentro de 30 minutos
  When el cliente hace una petición a la Web API
  Then no se envía ninguna petición al endpoint de tokens de Spotify

Scenario: Un 401 renueva el token y repite la petición una vez
  Given Spotify conectado con un access token vigente "BQ-access-123"
  And Spotify (simulado) responde a la primera petición "GET https://api.spotify.com/v1/me" con 401, y a la segunda con 200
  And Spotify (simulado) responde al refresh con el access token "BQ-nuevo-789"
  When el cliente hace la petición "GET https://api.spotify.com/v1/me"
  Then se envía un refresh y la petición se repite una sola vez, con "Authorization: Bearer BQ-nuevo-789"
  And el cliente devuelve la respuesta 200

Scenario: Un 401 que persiste tras la renovación pide reconectar
  Given Spotify conectado
  And Spotify (simulado) responde con 401 a todas las peticiones "GET https://api.spotify.com/v1/me" y con éxito al refresh
  When el cliente hace la petición "GET https://api.spotify.com/v1/me"
  Then se envían exactamente 2 peticiones a "/v1/me" y 1 refresh
  And el cliente lanza el error "reconnect_required"
  And la conexión queda con el estado "reconnect_required" y sin tokens

# --- Cliente compartido: acceso revocado o caducado ---------------------------

Scenario: Acceso revocado desde Spotify o refresh token caducado
  Given Spotify conectado con el access token caducado
  And Spotify (simulado) responde al refresh con estado 400 y error "invalid_grant"
  When el cliente hace una petición a la Web API
  Then el cliente lanza el error "reconnect_required"
  And la conexión queda con el estado "reconnect_required" y sin tokens
  And "GET /settings/spotify" responde 200 y muestra "Your Spotify access expired or was revoked. Please reconnect Spotify." y el botón "Connect Spotify"

Scenario: Faltan scopes tras ampliar los permisos de la app
  Given Spotify conectado con scopes concedidos que no incluyen "user-read-playback-state"
  When el usuario abre "/settings/spotify"
  Then la respuesta tiene estado 200 y muestra "Reconnect Spotify to grant the new permissions." y el botón "Connect Spotify"

# --- Cliente compartido: Spotify caído o límite de peticiones -----------------

Scenario: Spotify caído durante el refresh
  Given Spotify conectado con el access token caducado
  And Spotify (simulado) responde al refresh con estado 503
  When el cliente hace una petición a la Web API
  Then el cliente lanza el error "unavailable"
  And al leer la conexión a través de la app se obtienen los mismos tokens que antes

Scenario: Spotify no responde en 10 segundos
  Given Spotify conectado
  And Spotify (simulado) no responde a "GET https://api.spotify.com/v1/me" antes del timeout de 10 segundos
  When el cliente hace esa petición
  Then el cliente lanza el error "unavailable"
  And los tokens guardados no cambian

Scenario: Límite de peticiones (429) con Retry-After
  Given Spotify conectado
  And Spotify (simulado) responde a "GET https://api.spotify.com/v1/me/playlists" con estado 429 y la cabecera "Retry-After: 5"
  When el cliente hace esa petición
  Then el cliente lanza el error "rate_limited" con una espera de 5 segundos

Scenario: El bloqueo por 429 es compartido y no envía nada a Spotify
  Given el cliente recibió un 429 con "Retry-After: 5" hace 2 segundos en "/v1/me/playlists"
  When el cliente hace una petición "GET https://api.spotify.com/v1/search?q=daft+punk&type=track"
  Then no se envía ninguna petición a Spotify
  And el cliente lanza el error "rate_limited" con una espera de 3 segundos
  And 6 segundos después del 429, la misma petición sí se envía

Scenario: 429 sin cabecera Retry-After
  Given Spotify conectado
  And Spotify (simulado) responde con estado 429 sin la cabecera "Retry-After"
  When el cliente hace la petición
  Then el cliente lanza el error "rate_limited" con una espera de 5 segundos
  And durante esos 5 segundos no se envía ninguna petición a Spotify

Scenario: Un 429 del endpoint de tokens también activa el bloqueo compartido
  Given Spotify conectado con el access token caducado
  And Spotify (simulado) responde al refresh en "https://accounts.spotify.com/api/token" con estado 429 y "Retry-After: 5"
  When el cliente hace una petición "GET https://api.spotify.com/v1/me"
  Then el cliente lanza el error "rate_limited" con una espera de 5 segundos
  And no se envía ninguna petición a "/v1/me"
  And los tokens guardados no cambian

Scenario: Durante el bloqueo no se intenta renovar el token
  Given Spotify conectado con el access token caducado
  And el cliente recibió un 429 con "Retry-After: 5" hace 2 segundos
  When el cliente hace una petición a la Web API
  Then no se envía ninguna petición al endpoint de tokens ni a la Web API
  And el cliente lanza el error "rate_limited" con una espera de 3 segundos

# --- Desconectar --------------------------------------------------------------

Scenario: Desconectar Spotify
  Given Spotify conectado
  When el usuario pulsa "Disconnect Spotify" (DELETE "/spotify/connection")
  Then la tabla de conexiones queda vacía
  And se redirige a "/settings/spotify", que muestra "Spotify disconnected." y el botón "Connect Spotify"
  And la página indica que el acceso también se puede quitar en la cuenta de Spotify (spotify.com/account/apps)

Scenario: Desconectar sin conexión
  Given Spotify sin conectar
  When se envía DELETE "/spotify/connection"
  Then se redirige a "/settings/spotify" y la página responde 200
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **Una cuenta sin Premium se rechaza:** no se guardan sus tokens.
- **"Solo mi cuenta":** la app solo acepta la cuenta de Spotify del propietario, cuyo id se configura en el `.env` (`SPOTIFY_OWNER_ID`). Si en el callback el id de `GET /me` no coincide, se rechaza con "Only the owner's Spotify account can be connected." y no se guarda ningún token. Development mode ya exige que el propietario de la app tenga Premium (ver "Restricciones descubiertas"), así que esta regla cumple también la decisión anterior.
- **Nota residual:** el propietario debe tener Premium completo. Los planes solo móviles (Premium Mini, Lite) no sirven para el Web Playback SDK.

**Decisiones del orquestador, compartidas por el lote**

- T-05 es dueña de **un único cliente de la Web API de Spotify**, que usan T-06, T-07, T-08, T-15, T-23 y T-24:
  - timeout de 10 s;
  - un único bloqueo por `Retry-After` compartido por todas las funciones, de 5 s si falta la cabecera;
  - un 401 renueva el token y repite la petición una vez; si el 401 persiste tras renovar, `reconnect_required`;
  - la renovación se hace bajo `Cache::lock` y guarda el refresh token rotado.
- El cliente lanza cuatro errores tipados: `not_connected`, `reconnect_required`, `unavailable` y `rate_limited` (con la espera restante). Cada tarea que lo usa muestra sus propios mensajes. Las tareas que lo consumen convierten `not_connected` en una respuesta 409 `spotify_not_connected` y `reconnect_required` en una respuesta 409 `spotify_reconnect_required`.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Añadido al aplicar los cambios del lote el 2026-11-09:**
  - **Identificador de conexión:** cada conexión tiene un identificador que cambia al volver a conectar. T-08 lo usa en la clave de caché de la biblioteca, para que no se sirvan datos de una conexión anterior.
  - **Prop compartida `spotify.connected`:** las props compartidas de Inertia exponen un booleano `spotify.connected`. Así el reproductor de T-06 llama a `disconnect()` del SDK cuando se desconecta Spotify.

- **Flujo OAuth: Authorization Code con client secret**, que es el que Spotify recomienda para apps con servidor que guardan el secreto. PKCE se puede añadir encima; no es obligatorio. Implicit grant está deprecado y no se usa.
- **Rutas:** `GET /spotify/connect` (redirige a Spotify), `GET /spotify/callback` (redirect URI), `DELETE /spotify/connection` (desconectar) y la página `/settings/spotify`, dentro de los ajustes del starter kit. Todas con el middleware `auth`.
- **Configuración:** `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI` y `SPOTIFY_OWNER_ID` en el `.env`, leídas en `config/services.php`. Si falta `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET` o `SPOTIFY_OWNER_ID`, Spotify se considera "no configurado".
- **State:** aleatorio, de al menos 40 caracteres, guardado en la sesión y de un solo uso (se saca con `pull` al llegar el callback). Si no coincide o falta, se aborta sin canjear el code.
- **`show_dialog=true`** al conectar, para que Spotify muestre siempre la pantalla de permisos y deje ver o cambiar la cuenta con la que se conecta (si es otra, se rechaza).
- **Comprobación de Premium por `product`:** se implementa siempre. Si `GET /me` devuelve `product` y no es `premium`, se rechaza con "Spotify Premium is required." En development mode `GET /me` ya no devuelve ese campo, así que en la práctica rara vez se activa: lo que garantiza Premium es la regla del propietario. Si falta el campo, la conexión sigue adelante.
- **Una sola conexión:** como mucho una fila de conexión, siempre de la cuenta del propietario. Reconectar la misma cuenta sustituye los tokens en esa fila.
- **Almacenamiento:** access token y refresh token con el cast `encrypted` de Laravel. También se guardan el id y el nombre de la cuenta de Spotify, los scopes concedidos (campo `scope` de la respuesta de tokens), la fecha de caducidad del access token y un estado (`connected` o `reconnect_required`). El refresh token nunca sale del servidor. Dar el access token al Web Playback SDK es trabajo de T-06.
- **Cliente compartido** (errores tipados: `not_connected`, `reconnect_required`, `unavailable`, `rate_limited`):
  - Sin fila de conexión → `not_connected`. Si la conexión está en `reconnect_required` o no tiene tokens → `reconnect_required`. En los dos casos no se envía nada a Spotify.
  - Renueva si el access token caduca en menos de 60 segundos. Si la respuesta trae un refresh token nuevo, lo guarda; si no, mantiene el anterior.
  - `invalid_grant` en el refresh, o un 401 tras renovar → borra los tokens, marca la conexión como `reconnect_required` y lanza `reconnect_required`. `/settings/spotify` lee ese estado para pedir que se reconecte.
  - 5xx, error de conexión o timeout de 10 s → `unavailable`, sin tocar los tokens.
  - 429, venga de la Web API o de `accounts.spotify.com/api/token`, → guarda en caché "bloqueado hasta" (ahora + `Retry-After`, o 5 s si falta) y lanza `rate_limited` con la espera restante. Mientras dure el bloqueo no se envía ninguna petición de ninguna función a Spotify, **tampoco el refresh del token**: el cliente lanza `rate_limited` directamente.
  - Ningún error del cliente debe acabar en un 500: cada tarea lo captura y muestra su mensaje.
- **Scopes que se piden** (se piden todos al conectar, para no tener que reconectar en cada tarea):

  | Scope | Lo necesita | Para qué |
  |---|---|---|
  | `streaming` | T-06 | Reproducir con el Web Playback SDK |
  | `user-read-email`, `user-read-private` | T-06, T-07 | Los que pide habitualmente el SDK (su referencia actual no lista scopes: a confirmar en T-06). `user-read-private` también cubre la búsqueda y `GET /me` |
  | `user-modify-playback-state` | T-06, T-15 | Reproducir una URI en el dispositivo del SDK, transferir la reproducción, pausa, seek, volumen, siguiente y anterior por Web API |
  | `user-read-playback-state` | T-06, T-15 | Ver los dispositivos y el estado de reproducción (detectar que suena en otro dispositivo, elegir el dispositivo Connect) |
  | `user-read-currently-playing` | T-15 | La canción que suena y la cola en el dispositivo Connect |
  | `playlist-read-private`, `playlist-read-collaborative` | T-08 | Leer las playlists privadas y colaborativas del usuario |
  | `user-library-read` | T-08 | Leer las canciones guardadas ("me gusta") |

  No se piden permisos de escritura (`playlist-modify-*`, `user-library-modify`): ninguna tarea v1 los necesita. Las playlists mixtas (T-14) se guardan en la app según la pregunta abierta 4. Si T-23 o T-24 necesitan otro scope, se añade aquí y el escenario "Faltan scopes" obliga a reconectar.
- **Textos de la UI de T-05** (inglés): "Connect Spotify", "Disconnect Spotify", "Spotify connected", "Spotify disconnected.", "Spotify connection was cancelled.", "Could not connect Spotify. Please try again.", "Only the owner's Spotify account can be connected.", "Spotify Premium is required.", "This Spotify account is not allowed to use this app.", "Spotify is not responding right now. Try again in a moment.", "Your Spotify access expired or was revoked. Please reconnect Spotify.", "Reconnect Spotify to grant the new permissions.", "Spotify is not configured." Son una propuesta; T-03 puede ajustar la presentación. Los mensajes de `unavailable` y `rate_limited` en las pantallas de reproducción, búsqueda y biblioteca los deciden T-06, T-07, T-08, T-15, T-23 y T-24.

**Restricciones descubiertas** (investigadas el 2026-11-09 en la documentación pública de Spotify; responden a la pregunta abierta 10)

- **Development mode, cambios de febrero de 2026.** Los Client IDs creados desde el 11 de febrero de 2026 requieren Premium, permiten como mucho 5 usuarios autorizados y hay un único Client ID de development mode por desarrollador. Desde el 9 de marzo de 2026 se aplica también a las integraciones existentes (salvo los cambios de endpoints, aplazados para ellas). Fuente: https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security. La guía de migración dice que el límite de Client IDs subió a 25 en julio de 2026.
- **El propietario de la app necesita Premium.** "The app owner must have a Spotify Premium account for apps in development mode to function" (https://developer.spotify.com/documentation/web-api/concepts/quota-modes). Según la guía de migración, si el Premium del propietario caduca, la app deja de funcionar hasta que se renueva (https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide). El usuario debe registrar la app con su propia cuenta Premium. Es la base de la decisión "Solo mi cuenta".
- **Extended quota no es una opción.** Desde el 15 de mayo de 2025 solo se aceptan solicitudes de organizaciones (no de particulares), con al menos 250k usuarios activos al mes (quota-modes). La app se queda en development mode; para un solo usuario es suficiente.
- **`GET /me` ya no devuelve `product`** (ni `email`, `country`, `explicit_content` ni `followers`) a las apps en development mode (guía de migración, campos eliminados del usuario). Por eso Premium se garantiza por la regla del propietario y la comprobación de `product` queda como opcional.
- **Otros cambios para apps nuevas en development mode** (guía de migración), que afectan a otras tareas: la búsqueda devuelve como mucho 10 resultados por tipo (antes 50; por defecto 5); `GET /artists/{id}/top-tracks`, `browse/new-releases` y `browse/categories` se han eliminado; `/playlists/{id}/tracks` pasa a `/playlists/{id}/items` y su contenido solo se devuelve para playlists propias o colaborativas; los endpoints de guardar y quitar de la biblioteca se sustituyen por `PUT/DELETE /me/library`.
- **Redirect URI:** HTTPS obligatorio salvo en direcciones loopback, que pueden usar HTTP con IP literal (`http://127.0.0.1:PORT` o `http://[::1]:PORT`). **`localhost` no está permitido.** La URI debe coincidir exactamente con la registrada. Se aplica a apps nuevas desde el 9 de abril de 2025 y a todas desde noviembre de 2025 (https://developer.spotify.com/documentation/web-api/concepts/redirect_uri). En local hay que usar `http://127.0.0.1:8081/spotify/callback`; en producción, `https://music.<dominio>/spotify/callback`. Como hay un solo Client ID, las dos URIs se registran en la misma app de Spotify.
- **Flujos de autorización:** Authorization Code (recomendado para apps con servidor que guardan el secreto), Authorization Code con PKCE (para apps que no pueden guardar el secreto) y Client Credentials. Implicit grant está deprecado (https://developer.spotify.com/documentation/web-api/concepts/authorization).
- **State:** "Optional, but strongly recommended" contra CSRF. Si no coincide, "your app should reject the request". Si el usuario cancela, el callback llega con `error=access_denied` (https://developer.spotify.com/documentation/web-api/tutorials/code-flow).
- **Tokens:** el access token dura 1 hora. La respuesta del refresh puede no traer un refresh token nuevo; entonces se sigue usando el anterior. **Los refresh tokens duran 6 meses desde que el usuario autoriza, y renovar el access token no amplía ese plazo.** Cuando el refresh token caduca o se revoca, el endpoint de tokens devuelve `invalid_grant` (https://developer.spotify.com/documentation/web-api/tutorials/refreshing-tokens). Consecuencia: el usuario tendrá que reconectar Spotify al menos cada 6 meses.
- **Límite de peticiones:** se calcula en una ventana móvil de 30 segundos. Al superarlo, Spotify responde 429, normalmente con `Retry-After` en segundos. En development mode el límite es más bajo que en extended quota; Spotify no publica cifras (https://developer.spotify.com/documentation/web-api/concepts/rate-limits). Superar la cuota da 429 con el motivo `QUOTA_EXCEEDED` (quota-modes).
- **Web Playback SDK:** requiere Premium (los planes solo móviles, como Premium Mini o Lite, no valen según una respuesta del equipo de Spotify en su foro). Emite `account_error` cuando la cuenta no tiene un Premium válido. `getOAuthToken` se llama en cada `connect` y cuando el token caduca (como mucho a los 60 minutos) (https://developer.spotify.com/documentation/web-playback-sdk/reference). La página del SDK dice ahora que funciona en "most web browsers (Chrome, Firefox, Safari and Microsoft Edge) in both mobile (Android and iOS) and Desktop" (https://developer.spotify.com/documentation/web-playback-sdk). Esto contradice la suposición del discovery de que no está soportado en móviles: lo confirma el spike de la pregunta abierta 3.
- **Scopes:** `streaming` es para el Web Playback SDK y exige Premium; `user-modify-playback-state` cubre play, pausa, seek, volumen, siguiente, anterior, shuffle, repeat, transferir y añadir a la cola; `user-read-playback-state`, los dispositivos y el estado de reproducción; `user-read-currently-playing`, la canción actual y la cola (https://developer.spotify.com/documentation/web-api/concepts/scopes). "Start/Resume Playback" solo funciona con Premium (https://developer.spotify.com/documentation/web-api/reference/start-a-users-playback).

**Casos límite**

- **Cookie de sesión en local:** si el usuario abre la app en `http://localhost:8081` y el callback llega a `http://127.0.0.1:8081`, son hosts distintos. La sesión no viaja, el usuario aparece sin sesión y el state no coincide. En local hay que abrir siempre la app en `127.0.0.1` (y poner así `APP_URL`).
- **SameSite:** el callback es una navegación GET de nivel superior desde Spotify. La cookie de sesión con `SameSite=Lax` (lo que trae Laravel) viaja. Con `Strict` se perdería la sesión.
- **Sesión caducada durante la autorización:** el callback cae en el escenario "Callback sin sesión". Tras el login, el usuario vuelve a pulsar "Connect Spotify"; no se reanuda el callback antiguo.
- **`SPOTIFY_OWNER_ID` mal configurado:** la cuenta del propietario se rechazaría como "otra cuenta". El README explica cómo obtener el id (es el `id` de `GET /me`, que también aparece en la URL del perfil de Spotify).
- **Dos renovaciones a la vez** (por ejemplo, peticiones paralelas de T-06 y T-08): la renovación va bajo `Cache::lock`, y la segunda petición, al obtener el lock, relee el token ya renovado y no envía otro refresh. Así no se pierde un refresh token rotado.
- **El driver de caché del bloqueo y del cooldown** tiene que ser compartido entre procesos (database o redis; no `array` en producción). En los tests vale `array`.
- **Cambio de `APP_KEY`:** los tokens cifrados dejan de poder descifrarse. Se trata como `reconnect_required`, no como un 500. `APP_PREVIOUS_KEYS` permite rotar la clave sin perderlos.
- **Logs:** ni los tokens, ni el code, ni el client secret se escriben en los logs. Las excepciones del cliente HTTP no deben incluir el cuerpo de la respuesta de tokens.
- **Desconectar no revoca en Spotify:** no se ha encontrado en la documentación un endpoint para revocar tokens. Desconectar borra los tokens de la app, y para quitar el acceso en Spotify el usuario va a spotify.com/account/apps.
- **Premium caducado después de conectar:** en development mode, si el propietario pierde Premium, la app de Spotify deja de funcionar entera. Las llamadas fallarán (403 o `account_error` en el SDK). Para el cliente es un error de Spotify; qué mensaje se muestra lo decide T-06.
- **Usuario de la app borrado:** la conexión se borra con él (clave foránea con borrado en cascada).

**Pistas para los tests**

- Ningún test automático llama a Spotify. Se usa `Http::preventStrayRequests()` y `Http::fake()` para `https://accounts.spotify.com/api/token` y `https://api.spotify.com/v1/*`. El canje del code, el refresh, `invalid_grant`, 401, 403, 503 y 429 con o sin `Retry-After` se simulan con `Http::response(...)`; varias respuestas seguidas para la misma URL (401 y luego 200) con `Http::sequence()`. El timeout se simula con `Http::fake(fn () => throw new ConnectionException(...))`.
- "No se envía ninguna petición" se comprueba con `Http::assertNothingSent()` o `Http::assertSentCount()`. Que el refresh lleva `grant_type=refresh_token` y el refresh token correcto, y que la repetición lleva el Bearer nuevo, se comprueba con `Http::assertSent(fn ($request) => ...)`.
- **El valor del timeout (10 s)** no se puede comprobar de forma fiable con `Http::fake()`, porque la petición falsa no expone las opciones de Guzzle. El escenario del timeout solo comprueba que un error de conexión da `unavailable`. Que el cliente usa `timeout(10)` (o `config('services.spotify.timeout')` = 10) se revisa en la revisión del diseño y del código.
- Los errores tipados se comprueban con `expect(fn () => ...)->toThrow(...)` y leyendo el tipo y la espera restante de la excepción.
- El state se prepara con `$this->withSession(['spotify_oauth_state' => 'state-abc'])` (el nombre de la clave lo fija el diseño). `SPOTIFY_OWNER_ID` se fija con `config(['services.spotify.owner_id' => 'owner-123'])`.
- La redirección a Spotify se comprueba parseando la cabecera `Location`: host, `response_type`, `client_id`, `redirect_uri`, `show_dialog`, la lista de `scope` y que `state` coincide con el de la sesión.
- El cifrado se comprueba con `DB::table(...)->first()` (lectura en bruto) frente a la lectura con el modelo. "Una sola conexión" se comprueba con `assertDatabaseCount(..., 1)`.
- Las caducidades y el cooldown del 429 se prueban con `$this->travel()` / `Carbon::setTestNow()`, sin esperas reales.
- La renovación concurrente se prueba tomando el lock a mano en el test y guardando antes un token nuevo, y comprobando que el cliente no envía otro refresh.
- Las props de Inertia se comprueban con `assertInertia`, buscando que no aparezcan los tokens.
- **Prueba manual una vez**, con la app real de Spotify: conectar, desconectar y quitar el acceso en spotify.com/account/apps para ver el aviso de reconectar. Se apunta como checklist en el README, no como test automático.

**Fuera de alcance**

- Dar el access token al Web Playback SDK y reproducir (T-06), buscar (T-07), la biblioteca (T-08), Spotify Connect en el móvil (T-15), T-23 y T-24. Esas tareas usan el cliente y muestran sus propios mensajes.
- Reintentos automáticos tras un 429 o un 5xx: el cliente solo informa; reintentar o no lo decide cada tarea.
- Avisar antes de que el refresh token cumpla 6 meses. Ver "Pendiente".
- Conectar YouTube Music (T-09).
- Registrar la app en el Spotify Developer Dashboard: es un paso manual del usuario, que se documenta en el README (Client ID, secret, las dos redirect URIs y `SPOTIFY_OWNER_ID`).

**Pendiente**

- **Aviso antes de los 6 meses del refresh token:** decidir si `/settings/spotify` muestra cuándo hay que reconectar (por ejemplo, "Reconnect before 2027-05-09").
- **Librería:** Laravel Socialite con el proveedor de Spotify de SocialiteProviders, o el cliente HTTP de Laravel directamente. Se decide en el diseño; los escenarios valen para las dos.
- **Ubicación de la página:** `/settings/spotify` dentro de los ajustes del starter kit es una propuesta. T-02 decide qué ajustes se quedan y T-03, cómo se ven.

**Tamaño y posible división**

Se mantiene como una sola tarea porque la conexión y el cliente comparten el modelo de la conexión, los tokens y el estado `reconnect_required`, y ninguna tarea que depende de T-05 puede empezar sin las dos partes. Si en `/opsx-propose` resulta demasiado grande para un cambio, se puede dividir en **T-05a — Conectar Spotify** (OAuth, callback, cuenta del propietario, almacenamiento, ajustes y desconexión; unos 3,5 puntos) y **T-05b — Cliente de la Web API de Spotify** (renovación, 401, timeout, 429 y errores tipados; unos 2,5 puntos).

**Estimación: 6 puntos** (antes 5; sube por el cliente compartido).
- El flujo OAuth con state, el callback y sus errores (cancelar, state, code inválido, sin sesión, 403, 503), 2 puntos.
- El modelo de la conexión con tokens cifrados y estado, la reconexión, la desconexión y la página de ajustes, 1 punto.
- El cliente compartido (renovación bajo lock con rotación, 401 con un reintento, timeout, cooldown del 429 compartido, errores tipados), 2,5 puntos.
- La comprobación del propietario, la de `product` y la de scopes, 0,5 puntos.

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **`GET /settings`** redirige a `/settings/spotify` (con `auth`).
- **Navegación de ajustes:** T-05 no crea layout ni subnavegación de ajustes. La subnavegación (Spotify | Appearance) la construye T-18 (o T-09 si llega antes). El enlace principal "Settings" va en la navegación Aero de T-25; hasta T-25 vuelve al menú de usuario del starter kit (regla de T-02). Resolver así el Pendiente "Ubicación de la página".
- **Construir `/settings/spotify` con componentes Aero** (T-02 borra el layout de ajustes del starter kit) y añadir un escenario "la página no contiene elementos con el atributo data-slot" (T-25).
- **Mensajes tras redirigir** ("Spotify disconnected.", etc.): decidir si van dentro de la página o como aviso breve compartido. T-25 sustituye los toasts `vue-sonner` por `AeroFlash`.
- **Prop compartida `spotify.status`** (`not_connected` / `connected` / `reconnect_required`), además o en lugar del booleano `spotify.connected`. La necesita T-14 para elegir entre el aviso de conectar y el de reconectar.

**Seguimiento de T-04 (despliegue, 2026-11-09)**

Pregunta abierta 1 resuelta: hosting compartido Premium de Hostinger, app en `music.illodosadev.io` (subdominio de `illodosadev.io`), despliegue con GitHub Actions + SSH lanzado a mano (D14–D16).

- Añadir el escenario `@manual` "Spotify acepta la redirección de producción": con `https://music.illodosadev.io/spotify/callback` registrada en la app de Spotify y las variables SPOTIFY_* en el `.env` del servidor, al conectar Spotify desde producción vuelve a `https://music.illodosadev.io/settings/spotify` con Spotify conectado. Dependencia blanda de T-04.
- En producción la caché es `database` (no hay Redis en el plan), que cumple el requisito de caché común para el bloqueo de renovación y la pausa tras un 429.

**Parte 1 hecha con `spotify-connection` (2026-11-14)**

T-05 se partió en dos cambios. El primero, `spotify-connection`, está archivado. La referencia es ahora `openspec/specs/spotify-connection/spec.md`, y `openspec/specs/owner-auth/spec.md` para `/settings`.

- **Hecho:**
  - el flujo OAuth con un state de un solo uso;
  - el callback y sus errores: cancelar, state incorrecto, code rechazado, sin sesión, otra cuenta, sin Premium, 403 y Spotify sin responder;
  - una sola conexión, con los tokens cifrados;
  - la página `/settings/spotify` con la fecha de reconexión (6 meses) y el aviso de scopes que faltan;
  - la desconexión;
  - las props compartidas `spotify.status` y `spotify.connected`;
  - el elemento "Settings" del menú de usuario.
- **Resuelto del lote del 2026-11-09:**
  - `/settings` redirige a `/settings/spotify`;
  - la página es Aero, sin `data-slot`;
  - los mensajes van dentro de la página, en una región `role="status"`;
  - se comparte `spotify.status`.
- **Queda para el segundo cambio, `spotify-api-client`:**
  - el cliente compartido: renovación bajo lock con rotación, 401 con un reintento, timeout, pausa compartida tras un 429 y errores tipados;
  - la rotación de `APP_KEY`.

  Al proponerlo, poner aquí `change: spotify-api-client`. T-05 se cierra con ese cambio.
- El escenario `@manual` "Spotify acepta la redirección de producción" pasa a T-04.
- Comprobado a mano en local con la app real de Spotify el 2026-11-14. En Windows, el PHP local necesitó un paquete de certificados raíz (`curl.cainfo` y `openssl.cafile`) para hablar con Spotify por HTTPS. Sin él, la página muestra "Spotify is not responding right now."

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
