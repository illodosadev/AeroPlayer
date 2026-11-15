---
id: T-09
title: Conectar la cuenta de YouTube Music
status: enriched
discovery: reproductor-musica-privado
change: youtube-music-connection
priority: must
estimate: 6
language: es
---

# T-09 — Conectar la cuenta de YouTube Music

## Goal

Conectar la cuenta de YouTube Music Premium del propietario con el flujo OAuth para dispositivos de Google y guardar sus tokens de forma segura, para que T-12 pueda leer su biblioteca con la API oficial (YouTube Data API v3) y el scope `youtube.readonly` (T-27, 2026-11-15). La búsqueda (T-11) usa la API interna de YouTube Music sin token, y la reproducción (T-10), el reproductor embebido de YouTube; ninguna de las dos usa esta conexión.

T-09 entrega también un **servicio compartido de credenciales**, que da un access token válido (renovándolo si hace falta) y convierte cada problema en un error tipado. Es el equivalente, para los tokens, de lo que `SpotifyClient` hace con Spotify (T-05). El cliente de la Data API para la biblioteca no está aquí: va en T-12.

## Context

- **Depende de:**
  - **T-27**, el spike, con resultados del 2026-11-15: cliente PHP propio, scope `youtube.readonly`, sin Python;
  - **T-02**, porque solo el propietario con sesión conecta servicios;
  - **T-03**, el estilo Aero y los tests de navegador.
- **Dependen de esta:** T-10, T-11 y T-12.
- **Modelo:** la conexión de Spotify (T-05; specs `spotify-connection` y `spotify-web-api`): página de ajustes propia, tokens cifrados, un `connection_id` que cambia al reconectar, el estado `reconnect_required`, un único propietario permitido y una prop compartida con el estado.
- **Efecto en las specs actuales:**
  - **una capacidad nueva,** `youtube-music-connection`;
  - **un cambio en `spotify-connection`:** la página de ajustes de Spotify gana una barra de enlaces "Spotify / YouTube Music" para llegar a la nueva página. `GET /settings` sigue redirigiendo a `/settings/spotify`.

## Acceptance criteria

```gherkin
# --- Acceso, configuración y aspecto ---------------------------------------------

Scenario: Un visitante sin sesión no ve los ajustes de YouTube Music
  Given un visitante sin sesión iniciada
  When pide "GET /settings/youtube-music"
  Then se le redirige a "/login"
  And no se envía ninguna petición a Google

Scenario Outline: YouTube Music sin configurar
  Given el usuario con sesión iniciada y <falta> en el ".env" del servidor
  When abre "/settings/youtube-music"
  Then ve "YouTube Music is not configured."
  And no ve el botón "Connect YouTube Music"
  And no se envía ninguna petición a Google

  Examples:
    | falta                                      |
    | sin el client id de Google                 |
    | sin el client secret de Google             |
    | sin el id del canal de YouTube del propietario |

Scenario: Llegar a los ajustes de YouTube Music
  Given el usuario en "/settings/spotify"
  When pulsa "YouTube Music" en la barra de enlaces de ajustes
  Then se abre "/settings/youtube-music", con la misma barra y "Spotify" para volver

Scenario: La página es Aero, accesible con el teclado y en el móvil
  Given el usuario en "/settings/youtube-music", en cada estado (sin conectar, esperando la aprobación, conectado y pendiente de reconectar)
  When recorre la página con el tabulador, y después la ve a 375 px de ancho
  Then ningún elemento tiene el atributo "data-slot"
  And cada control activo muestra un contorno de foco de 2 px
  And a 375 px no hay desplazamiento horizontal y cada control conserva un área táctil de 44x44 px dentro de la pantalla

# --- Conectar con el flujo para dispositivos -------------------------------------

Scenario: Empezar la conexión
  Given el usuario con sesión iniciada, YouTube Music configurado y sin conectar
  When pulsa "Connect YouTube Music" ("POST /youtube-music/connect")
  Then se envía a Google una sola petición "POST https://oauth2.googleapis.com/device/code" con el client id y el scope que fijó T-27
  And se le redirige a "/settings/youtube-music", que responde 200 y muestra el código de usuario de Google (por ejemplo "ABCD-EFGH"), un enlace a la dirección de verificación y "Waiting for you to approve the code…"
  And ni la página ni sus props contienen el device code ni el client secret

Scenario: El servidor respeta el intervalo de Google
  Given la conexión empezada con un intervalo de 5 segundos y Google respondiendo "authorization_pending"
  When la página consulta "GET /youtube-music/connect/status" a los 0, 2, 5 y 7 segundos
  Then el servidor ha consultado el endpoint de token de Google exactamente 2 veces, a los 0 y a los 5 segundos
  And cada consulta de la página responde el estado "pending", sin error

Scenario: Google pide ir más despacio
  Given la conexión empezada con un intervalo de 5 segundos
  And Google ha respondido "slow_down" a la consulta de los 0 segundos
  When la página consulta el estado a los 5, 9 y 10 segundos
  Then el servidor solo vuelve a consultar el endpoint de token a los 10 segundos

Scenario: Conectar YouTube Music
  Given la conexión empezada
  And Google (falseado) responde a "channels" con el id del canal del propietario configurado
  When el propietario aprueba el código y Google responde con el access token "ya29.access-123", el refresh token "1//refresh-456" y su caducidad
  Then el servidor pide el canal a "GET https://www.googleapis.com/youtube/v3/channels" con "part=snippet" y "mine=true"
  And la consulta de estado responde "connected" y la página muestra "YouTube Music connected" y el nombre del canal
  And el servidor deja de consultar el endpoint de token

Scenario: Las credenciales se guardan cifradas
  Given YouTube Music conectado con el access token "ya29.access-123" y el refresh token "1//refresh-456"
  When se lee directamente el registro de la conexión en la base de datos
  Then ni "ya29.access-123" ni "1//refresh-456" aparecen en texto plano
  And leídos a través de la app, los dos tokens se recuperan iguales

Scenario: Los tokens no llegan al navegador
  Given YouTube Music conectado
  When el usuario abre cualquier página con sesión
  Then ninguna prop de Inertia, compartida o de la página, contiene el access token ni el refresh token
  And la prop compartida "youtubeMusic" solo indica el estado de la conexión

Scenario Outline: La prop compartida dice el estado de la conexión
  Given el usuario con sesión iniciada y YouTube Music <estado>
  When abre cualquier página con sesión
  Then la prop compartida "youtubeMusic.status" vale "<status>" y "youtubeMusic.connected" vale <connected>

  Examples:
    | estado                              | status             | connected |
    | sin conectar                        | not_connected      | false     |
    | conectado                           | connected          | true      |
    | en el estado "reconnect_required"   | reconnect_required | false     |

Scenario: La página con YouTube Music conectado
  Given YouTube Music conectado
  When el usuario abre "/settings/youtube-music"
  Then ve "YouTube Music connected", el nombre del canal y el botón "Disconnect YouTube Music"
  And no ve el botón "Connect YouTube Music"

Scenario: Empezar la conexión estando ya conectado
  Given YouTube Music conectado
  When se pide "POST /youtube-music/connect"
  Then la respuesta redirige a "/settings/youtube-music", que sigue mostrando la conexión
  And no se envía ninguna petición a Google

Scenario: Aprobar con una cuenta que no es la del propietario
  Given la conexión empezada
  When se aprueba el código con una cuenta de Google cuyo canal no es el del propietario, o que no tiene canal
  Then no se guarda ninguna conexión y la app intenta revocar los tokens recibidos
  And la página muestra "This YouTube account isn't yours. Nothing was connected." y el botón "Connect YouTube Music"

Scenario: El usuario deniega el acceso
  Given la conexión empezada
  When Google responde "access_denied"
  Then la página muestra "You denied access to YouTube Music." y el botón "Connect YouTube Music"
  And no se guarda ninguna conexión

Scenario: El código caduca
  Given la conexión empezada con el "expires_in" que dio Google
  When Google responde "expired_token", o pasan los "expires_in" segundos sin aprobar
  Then la página muestra "The code expired. Start again." y el botón "Connect YouTube Music"
  And el servidor no vuelve a consultar el endpoint de token con ese device code

Scenario Outline: Google falla al pedir el código
  Given el usuario con sesión iniciada, YouTube Music configurado y sin conectar
  And Google responde <respuesta> a "POST /device/code"
  When pulsa "Connect YouTube Music"
  Then se le redirige a "/settings/youtube-music", que responde 200, sin página de error
  And la página muestra <mensaje> y el botón "Connect YouTube Music"
  And la página no contiene el cuerpo de la respuesta de Google

  Examples:
    | respuesta                         | mensaje                                         |
    | 503                               | "Couldn't reach Google. Try again in a moment." |
    | nada antes de 10 segundos         | "Couldn't reach Google. Try again in a moment." |
    | 401 con el error "invalid_client" | "YouTube Music is not configured correctly."    |

Scenario Outline: El endpoint de token falla mientras se espera
  Given la conexión empezada
  When Google responde <respuesta> a una consulta al endpoint de token
  Then la consulta de estado sigue respondiendo "pending", sin error en la página
  And la siguiente consulta a Google respeta <espera>

  Examples:
    | respuesta                 | espera                      |
    | 503                       | el intervalo de siempre     |
    | nada antes de 10 segundos | el intervalo de siempre     |
    | 429                       | el intervalo más 5 segundos |

Scenario: El canal no se puede comprobar
  Given la conexión empezada y el propietario ha aprobado el código
  When la petición del canal responde 503 o no responde antes de 10 segundos
  Then no se guarda ninguna conexión y la app intenta revocar los tokens recibidos
  And la página muestra "Couldn't check your YouTube account. Try again." y el botón "Connect YouTube Music"

Scenario: Empezar otra vez sustituye el código anterior
  Given la conexión empezada con un device code
  When el usuario vuelve a pulsar "Connect YouTube Music", en la misma pestaña o en otra
  Then se pide a Google un código nuevo y la página muestra solo el nuevo
  And ninguna consulta al endpoint de token lleva ya el device code anterior, y ese código no puede guardar una conexión

Scenario: Dos consultas de estado a la vez
  Given la conexión empezada y Google a punto de dar el token
  When llegan dos consultas de estado a la vez
  Then se envía una sola consulta al endpoint de token y se guarda una sola conexión
  And las dos consultas responden "connected", sin error

Scenario: La sesión de la app caduca mientras se espera
  Given la conexión empezada y la sesión de la app caducada
  When la página consulta "GET /youtube-music/connect/status"
  Then la consulta responde 401 en JSON
  And la página lleva al usuario a "/login"

Scenario Outline: Los registros no guardan secretos
  Given el client secret "secret-xyz", el device code "dev-123", el access token "ya29.access-123" y el refresh token "1//refresh-456"
  And Google respondiendo <fallo>, con un cuerpo que incluye "upstream-body-detail"
  When ocurre ese fallo
  Then el registro de la app no contiene "secret-xyz", "dev-123", "ya29.access-123", "1//refresh-456" ni "upstream-body-detail"

  Examples:
    | fallo                                  |
    | 503 a "POST /device/code"              |
    | 503 al endpoint de token mientras se espera |
    | 503 a la renovación del token          |
    | nada antes de 10 segundos a la revocación |

# --- Usar las credenciales -------------------------------------------------------

Scenario: Un access token caducado se renueva sin avisar
  Given YouTube Music conectado y su access token caducado hace 1 minuto
  When una función de la app pide al servicio de credenciales el access token
  Then se envía a Google una sola renovación con el refresh token
  And el servicio devuelve el access token nuevo y lo guarda cifrado
  And no se muestra ningún aviso de reconexión

Scenario: Dos peticiones a la vez renuevan una sola vez
  Given YouTube Music conectado y su access token caducado
  When dos funciones de la app piden el access token a la vez
  Then se envía una sola renovación a Google y las dos reciben el mismo access token nuevo

Scenario: Las credenciales caducan o se revocan
  Given YouTube Music conectado y la renovación respondiendo "invalid_grant"
  When una función de la app pide el access token
  Then el servicio falla con "reconexión necesaria" y la conexión pasa al estado "reconnect_required"
  And "/settings/youtube-music" responde 200 y muestra "Your YouTube Music access expired or was revoked. Please reconnect YouTube Music." y el botón "Connect YouTube Music"

Scenario Outline: La renovación falla por otra causa
  Given YouTube Music conectado y la renovación respondiendo <respuesta>
  When una función de la app pide el access token
  Then el servicio falla con <error> y la conexión sigue en el estado "connected"
  And "/settings/youtube-music" muestra <página>

  Examples:
    | respuesta                         | error                     | página                                                      |
    | 503                               | "no disponible"           | "YouTube Music connected", sin aviso                        |
    | nada antes de 10 segundos         | "no disponible"           | "YouTube Music connected", sin aviso                        |
    | 401 con el error "invalid_client" | "configuración no válida" | "YouTube Music is not configured correctly."                |

Scenario: El aviso de configuración desaparece al renovar bien
  Given YouTube Music conectado, con el aviso "YouTube Music is not configured correctly." por un "invalid_client" anterior
  And el client secret del ".env" ya corregido
  When una función de la app pide el access token y la renovación responde bien
  Then "/settings/youtube-music" ya no muestra el aviso

# --- Desconectar y reconectar ----------------------------------------------------

Scenario: Desconectar YouTube Music
  Given YouTube Music conectado
  When el usuario pulsa "Disconnect YouTube Music" ("DELETE /youtube-music/connection")
  Then la app intenta revocar el token en "https://oauth2.googleapis.com/revoke"
  And la conexión y sus tokens se borran de la base de datos
  And se le redirige a "/settings/youtube-music", que muestra "YouTube Music disconnected." y el botón "Connect YouTube Music"

Scenario: Desconectar aunque Google no responda
  Given YouTube Music conectado y Google sin responder a la revocación
  When el usuario pulsa "Disconnect YouTube Music"
  Then la conexión y sus tokens se borran igualmente y la página muestra "YouTube Music disconnected."

Scenario: Desconectar sin conexión
  Given YouTube Music sin conectar
  When se pide "DELETE /youtube-music/connection"
  Then la respuesta redirige a "/settings/youtube-music" sin error
  And no se envía ninguna petición a Google

Scenario: Reconectar sustituye la conexión anterior
  Given YouTube Music en el estado "reconnect_required"
  When el propietario conecta de nuevo y aprueba el código
  Then queda una sola conexión, con tokens nuevos, un identificador de conexión nuevo y el estado "connected"

Scenario: Un intento fallido no toca la conexión existente
  Given YouTube Music en el estado "reconnect_required"
  When el propietario empieza a conectar y deniega el acceso, o el código caduca
  Then la conexión existente sigue igual, con el estado "reconnect_required"
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-14)**

- **Autenticación:** OAuth con el flujo para TV y dispositivos de entrada limitada de Google, con un cliente OAuth propio del propietario en Google Cloud (tipo "TV and Limited Input devices"). Se descartan las cookies del navegador.
- **Spike aparte:** la ruta técnica y el scope los decidió **T-27** (2026-11-15): `youtube.readonly`, que mantiene D6; la biblioteca, con la API oficial; sin `ytmusicapi`.
- **Verificación de la app con Google:** publicar la pantalla de consentimiento obliga a verificar la app, porque `youtube.readonly` es un scope sensible. El usuario decide verificarla en una tarea aparte (por crear). Mientras tanto, la app funciona en "Testing" con el propietario como usuario de prueba, y su autorización caduca a los 7 días: la renovación falla con `invalid_grant`, la conexión pasa a `reconnect_required` y el propietario reconecta desde la página de ajustes.
- **Solo la cuenta del propietario:** el `.env` guarda el id de su canal de YouTube, como `SPOTIFY_OWNER_ID` en T-05. Una aprobación con otra cuenta, o con una sin canal, se rechaza sin guardar nada.
- **Interfaz:**
  - una página Aero propia, `/settings/youtube-music`, como `/settings/spotify`;
  - las dos páginas de ajustes llevan una barra de enlaces "Spotify / YouTube Music";
  - `/settings` sigue redirigiendo a `/settings/spotify`;
  - los textos de pantalla van en inglés (T-01).
- **Etiquetas como en Spotify:** en el estado `reconnect_required` el botón es "Connect YouTube Music", como "Connect Spotify" en `spotify-connection`. Para cambiar de cuenta estando conectado, se desconecta primero.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Configuración:** `YOUTUBE_MUSIC_CLIENT_ID`, `YOUTUBE_MUSIC_CLIENT_SECRET` y `YOUTUBE_MUSIC_OWNER_CHANNEL_ID` van en el `.env` del servidor, nunca en el repositorio. Sin cualquiera de los tres, la página dice "YouTube Music is not configured." y no se envía nada a Google.
- **Rutas:**

  | Ruta | Qué hace |
  |---|---|
  | `GET /settings/youtube-music` | la página |
  | `POST /youtube-music/connect` | empieza un intento y redirige a la página |
  | `GET /youtube-music/connect/status` | JSON con `pending`, `connected`, `denied`, `expired`, `not_owner` o `failed`; un 401 en JSON sin sesión |
  | `DELETE /youtube-music/connection` | desconecta y redirige a la página |

- **El flujo para dispositivos:**
  - el código se pide a `https://oauth2.googleapis.com/device/code` con el client id y el scope `https://www.googleapis.com/auth/youtube.readonly` (T-27);
  - la página muestra solo el `user_code` y la `verification_url`. El `device_code`, el intervalo y la caducidad se guardan en el servidor (en la caché, con la clave del intento), nunca en el navegador;
  - el navegador consulta el estado con frecuencia, pero **el servidor solo llama** a `https://oauth2.googleapis.com/token` (`grant_type=urn:ietf:params:oauth:grant-type:device_code`) cuando ha pasado el intervalo desde la última llamada. Así el comportamiento se puede probar sin depender del navegador;
  - las consultas a Google van bajo un bloqueo por intento, para que dos consultas a la vez no guarden dos conexiones;
  - `authorization_pending` sigue esperando, y `slow_down` y un 429 suman 5 s al intervalo. Un 5xx o un timeout siguen esperando sin cambiarlo. `access_denied`, `expired_token` y el fin de `expires_in` terminan el intento.
- **Un solo intento a la vez:** un intento nuevo sustituye al anterior. El `device_code` antiguo se borra del servidor, así que ninguna consulta vuelve a usarlo.
- **Al aprobar:**
  - se pide el canal (`channels?part=snippet&mine=true`, 1 unidad de cuota);
  - si el id coincide con el del propietario, se guardan cifrados el access token, el refresh token, su caducidad y los scopes. También se guardan el nombre del canal, un `connection_id` nuevo y el estado `connected`;
  - si no coincide, si no hay canal o si la petición falla, no se guarda nada y se intenta revocar el token recibido;
  - un intento fallido no toca la conexión que ya hubiera.
- **El servicio compartido de credenciales:**
  - `accessToken()` devuelve un token válido durante al menos 60 s. Lo renueva antes con el refresh token, bajo un bloqueo, de modo que dos peticiones a la vez renuevan una sola vez, como el cliente de Spotify;
  - errores tipados:
    - **no conectado:** no hay conexión;
    - **reconexión necesaria:** `invalid_grant`. Además marca `reconnect_required`;
    - **no disponible:** 5xx o timeout de 10 s;
    - **configuración no válida:** `invalid_client`. No marca `reconnect_required`, porque reconectar no lo arregla. Guarda en la conexión una marca `config_error`, que la página de ajustes lee para mostrar "YouTube Music is not configured correctly." sin llamar a Google; la siguiente renovación correcta la borra.
  - si Google rota el refresh token, se guarda el nuevo.
- **Prop compartida:** `youtubeMusic: {status, connected}` en todas las páginas con sesión, como `spotify`, para que T-10 a T-12 sepan si pueden usar el servicio. Nunca lleva tokens.
- **Desconectar:** se borra la conexión siempre. Antes se intenta revocar en `https://oauth2.googleapis.com/revoke`; si falla, ni se impide el borrado ni se muestra un error. Sin conexión, no hace nada y redirige.
- **Registros:** nunca se registran tokens, el client secret, el device code ni cuerpos de respuesta de Google.

**Restricciones descubiertas**

- **`ytmusicapi` y OAuth:** la versión 1.12.3 responde 400 con un token OAuth válido (T-27). Descartada.
- **Pantalla de consentimiento en "Testing":** solo entran los usuarios de prueba, y su autorización caduca a los 7 días, refresh token incluido. Publicarla ("In production") exige verificar la app (T-27). Fuente: [Google Cloud Help](https://support.google.com/cloud/answer/15549945).
- **Flujo para dispositivos:** el código de usuario, la dirección de verificación, el intervalo, `expires_in` y los errores `authorization_pending`, `slow_down`, `access_denied` y `expired_token` están en la guía de Google. Fuente: [OAuth 2.0 for TV and Limited-Input Device Applications](https://developers.google.com/youtube/data/guides/auth/devices).
- **Hosting compartido:** no hay workers ni procesos permanentes, así que la espera la marcan las consultas del navegador. El servidor limita su ritmo (seguimiento de T-04).
- **Permisos:** el scope `https://www.googleapis.com/auth/youtube` da lectura y escritura, y la decisión D6 pide no solicitar permisos de escritura. Se usa `youtube.readonly`: basta para la API oficial (T-27). La API interna lo rechaza, y por eso la biblioteca va por la oficial.

**Casos límite**

- El usuario cierra la página mientras espera: el intento caduca solo a los `expires_in` segundos, y la siguiente visita muestra "Connect YouTube Music".
- La cuenta del propietario no tiene YouTube Music Premium: la conexión funciona, y la falta de Premium se verá al reproducir (T-10).
- Cambia el client secret en el `.env`: las renovaciones fallan como "configuración no válida" y la página de ajustes dice "YouTube Music is not configured correctly.". La conexión no se marca `reconnect_required`.
- Dos pestañas esperando a la vez: solo cuenta el último intento.

**Pistas para los tests**

- `Http::preventStrayRequests()` y `Http::fake()` sobre `oauth2.googleapis.com/*` y `www.googleapis.com/youtube/v3/*`, con fixtures en `tests/Fixtures/google/`.
- El ritmo, con una secuencia de respuestas del endpoint de token y `$this->freezeSecond()` y `$this->travel()`: se cuentan las llamadas a Google, no las del navegador.
- La concurrencia, como en las pruebas de bloqueo del cliente de Spotify (T-05).
- El cifrado, con `DB::table(...)->value(...)`. Los registros, con `Log::spy()` o leyendo el fichero de log en un test.
- Tests de navegador: el código visible, la espera, conectado, desconectar, la barra de ajustes, el foco y la vista de 375 px.
- Ningún test llama a Google de verdad.

**Fuera de alcance**

- El spike (T-27).
- El cliente de la Data API para la biblioteca (T-12), buscar (T-11) y reproducir (T-10).
- Verificar la app con Google (tarea aparte, por crear).
- Autenticación con cookies del navegador, descartada por el usuario.
- Varias cuentas de YouTube Music, o una que no sea la del propietario.

**Pendiente (no decidido)**

- Nada. El scope y la ruta los fijó T-27 el 2026-11-15.

**Estimación: 6 puntos**, como T-05, sin contar el spike (T-27, 2 puntos).
- Flujo para dispositivos con el ritmo marcado por el servidor, intentos sustituidos, bloqueo y sus errores: 2 puntos.
- Comprobación del propietario, conexión cifrada, servicio de credenciales con renovación bajo bloqueo y errores tipados: 2 puntos.
- Desconexión con revocación, prop compartida y barra de ajustes: 0,5 puntos.
- Página Aero y tests de feature y de navegador: 1,5 puntos.

**Seguimiento de T-04 (despliegue, 2026-11-09)**

- Hosting compartido de Hostinger, app en `music.illodosadev.io`, despliegue con GitHub Actions y SSH lanzado a mano (D14–D16).
- No hay procesos permanentes ni workers. T-27 descarta Python: todo va en PHP.

**Seguimiento de youtube-music-connection (parte 1 de 2, archivada el 2026-11-15)**

- **Hecho:** el flujo para dispositivos, la conexión cifrada solo para el canal del propietario, la página `/settings/youtube-music`, la desconexión con revocación, la prop compartida `youtubeMusic` y la barra de enlaces de ajustes. Spec `youtube-music-connection`.
- **Pendiente, en `youtube-music-credentials`:** el servicio compartido de credenciales (`accessToken()` con renovación bajo bloqueo), los errores tipados, `reconnect_required` con `invalid_grant` y la marca `config_error` con `invalid_client`. Sus escenarios son los de "Usar las credenciales" de esta tarea, más el fallo de la renovación en "Los registros no guardan secretos".
- **Prueba manual (2026-11-15):** conectar con la cuenta real funciona. En la página de permisos de Google no aparece ninguna entrada con el nombre de la app, así que la revocación no se pudo comprobar a ojo.

## Definition of Done

- [ ] T-27 done, and its route and scope reflected in the change
- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
