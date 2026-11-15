---
id: T-27
title: Spike de YouTube Music
status: done
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: 2
language: es
---

# T-27 — Spike de YouTube Music

## Goal

Responder, con la cuenta real del usuario y su servidor, lo que bloquea T-09 a T-12: qué ruta técnica usa la app para hablar con YouTube Music, con qué permisos, y cómo se reproduce. Es una investigación con tiempo limitado: no produce un cambio de OpenSpec ni código para `main`, solo respuestas anotadas en esta tarea.

## Context

- Resuelve la parte pendiente de la pregunta abierta 2 del discovery. La autenticación ya está decidida (2026-11-14): OAuth con el flujo para TV y dispositivos de entrada limitada de Google y un cliente OAuth propio del usuario.
- **Bloquea:** T-09 (conectar), T-10 (reproducir), T-11 (buscar) y T-12 (biblioteca).
- **Tiempo máximo:** un día de trabajo. Si se agota sin respuesta, se anota lo que se sabe y se decide con el usuario.

## Acceptance criteria

```gherkin
Scenario: La ruta técnica queda decidida
  Given el spike terminado
  When se lee esta tarea
  Then indica la ruta elegida: un cliente PHP propio contra la API interna de YouTube Music, o la librería Python "ytmusicapi"
  And explica por qué, con el resultado de las comprobaciones 1 y 2
  And si la ruta es "ytmusicapi", indica si funciona con un access token que le pasa PHP en cada llamada, sin el refresh token ni el client secret

Scenario: Los permisos quedan decididos
  Given el spike terminado
  When se lee esta tarea
  Then indica si el scope "https://www.googleapis.com/auth/youtube.readonly" basta para leer la biblioteca y buscar con la API interna
  And si hace falta el scope completo "https://www.googleapis.com/auth/youtube", explica por qué y lo anota frente a la decisión D6 (sin permisos de escritura)

Scenario: Cómo se habla con la API interna queda anotado
  Given el spike terminado
  When se lee esta tarea
  Then anota el contexto de cliente y las cabeceras con los que una petición autenticada con un token OAuth responde 200
  And anota si ese mismo token sirve para la reproducción de T-10

Scenario: La reproducción queda decidida
  Given el spike terminado
  When se lee esta tarea
  Then indica cómo reproduce la app una canción de YouTube Music (por ejemplo, el reproductor embebido de YouTube)
  And indica si, con la cuenta Premium del usuario, ese método reproduce sin anuncios

Scenario: Un resultado negativo para el trabajo
  Given ninguna ruta técnica consigue una petición autenticada que responda 200
  When termina el spike
  Then esta tarea lo anota con lo que se probó
  And T-09 a T-12 se revisan con el usuario antes de empezar ninguna
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Comprobaciones**

1. **Servidor:** si el hosting permite ejecutar Python desde PHP, y si `python3` y `pip --user` existen. Se comprueba por SSH. En esta tarea se anota solo la conclusión ("se puede" o "no se puede"), sin detalles de configuración del servidor: el repositorio es público.
2. **OAuth:**
   - crear el cliente "TV and Limited Input devices" en Google Cloud, con la pantalla de consentimiento en "In production", porque en "Testing" las autorizaciones caducan a los 7 días;
   - completar el flujo para dispositivos con `youtube.readonly` y obtener un refresh token;
   - hacer una petición autenticada a la API interna de YouTube Music (por ejemplo, leer las playlists de la biblioteca) desde PHP, y desde `ytmusicapi` si el punto 1 lo permite;
   - con `ytmusicapi`, probar que funciona pasándole solo un access token válido obtenido por PHP, sin el refresh token ni el client secret, porque T-09 deja la renovación en PHP;
   - repetirla con el scope completo solo si `youtube.readonly` no basta.
3. **Cuenta del propietario:** anotar que su cuenta de Google tiene un canal de YouTube, porque T-09 la identifica por el id del canal. El id va al `.env`, no a esta tarea.
4. **Reproducción:** probar el método elegido con una canción, con la cuenta Premium, y anotar si hay anuncios.

**Reglas**

- Ni el client id, ni el client secret, ni ningún token se escriben en esta tarea ni en el repositorio. Van en el `.env` del servidor o en el gestor de contraseñas del usuario.
- El código de prueba del spike no se sube a `main`.
- **Cierre:** como no tiene cambio de OpenSpec, `/ship` no la cierra. Se pasa a `status: done` a mano cuando "Resultados" está completo y T-09 a T-12 están actualizadas.

**Fuentes de partida**

- [ytmusicapi — OAuth authentication](https://ytmusicapi.readthedocs.io/en/stable/setup/oauth.html): desde noviembre de 2024 exige un client id y un client secret propios (tipo "TV and Limited Input").
- [ytmusicapi #682](https://github.com/sigma67/ytmusicapi/discussions/682): informes de 400 con OAuth, resueltos con un cliente propio válido.
- [Google Cloud Help](https://support.google.com/cloud/answer/15549945): en "Testing" las autorizaciones caducan a los 7 días.
- [OAuth 2.0 for TV and Limited-Input Device Applications](https://developers.google.com/youtube/data/guides/auth/devices).

**Estimación: 2 puntos.** Un día con la cuenta real: el cliente de Google Cloud, el flujo, las peticiones desde las dos rutas y la prueba de reproducción.

**Resultados**

<!-- Se rellena al hacer el spike: fecha, ruta elegida, scope, contexto de cliente y cabeceras, si el token sirve para reproducir, método de reproducción y anuncios. -->

- **Comprobación 1, servidor (2026-11-14):**
  - PHP puede hacer peticiones HTTP: la ruta del cliente PHP propio es posible;
  - el servidor tiene Python 3 con pip, y PHP puede lanzarlo: la ruta `ytmusicapi` también es posible;
  - pero ese Python es una versión ya fuera de soporte: `pip` solo instala `ytmusicapi` 1.10.3, mientras que la versión estable actual es la 1.12.x. La ruta Python quedaría congelada en una versión antigua de la librería, sin los arreglos futuros, y con una API no oficial que cambia a menudo eso es un riesgo alto;
  - tras subir el sitio a PHP 8.4 (2026-11-14, T-04), la configuración de PHP volvió a la de fábrica: la ruta Python necesitaría cambiar de nuevo la configuración de PHP del servidor;
  - **conclusión provisional:** se prefiere el cliente PHP propio, si la comprobación 2 confirma que una petición autenticada desde PHP funciona.
- **Comprobación 2, OAuth y API interna (2026-11-15):**
  - **Cliente OAuth:** el tipo "TV and Limited Input devices" funciona con el flujo para dispositivos desde PHP y Google devuelve refresh token, tanto con `youtube.readonly` como con el scope completo.
  - **Modo "Testing":** la pantalla de consentimiento no se puede pasar a "In production" sin verificar la app con Google, porque `youtube.readonly` es un scope sensible. En "Testing" solo entran las cuentas añadidas como usuarios de prueba, y su autorización caduca a los 7 días. Decisión del usuario: verificar la app en una tarea aparte; hasta entonces, la app funciona en "Testing" y se reconecta cuando caduca.
  - **API interna con OAuth:**

    | Cliente (`context.client.clientName`) | `youtube.readonly` | Scope completo |
    |---|---|---|
    | `WEB_REMIX` (el de la web de YouTube Music) | 403 `PERMISSION_DENIED` | 400 `INVALID_ARGUMENT` |
    | `ANDROID_MUSIC` | no probado | 400 `INVALID_ARGUMENT` |
    | `TVHTML5` | 403 `ACCESS_TOKEN_SCOPE_INSUFFICIENT` | 200: la biblioteca completa, también las playlists guardadas de otros usuarios |

    El 400 se mantiene con todas las cabeceras que manda `ytmusicapi` (`X-Goog-Request-Time`, `X-Goog-Visitor-Id`, `?alt=json`) y sin ellas. Sin token, la biblioteca responde 401. Con `TVHTML5`, la respuesta usa el formato de la app de TV (`tileRenderer`), no el de la web.
  - **`ytmusicapi` 1.12.3** (la última) falla con 400 en la biblioteca y en la búsqueda con un access token válido pasado desde fuera, porque siempre manda el contexto `WEB_REMIX`. **Ruta descartada.**
  - **Búsqueda sin token:** `POST https://music.youtube.com/youtubei/v1/search?alt=json` con el contexto `{"client": {"clientName": "WEB_REMIX", "clientVersion": "1.<AAAAMMDD>.01.00", "hl": "en"}}`, las cabeceras `Content-Type: application/json` y `Origin: https://music.youtube.com`, y el parámetro del filtro de canciones, responde 200 con canciones en formato `musicResponsiveListItemRenderer`: título, artista, álbum, duración e id del vídeo. No necesita la cuenta del usuario.
  - **API oficial (YouTube Data API v3) con `youtube.readonly`:**
    - responde 200 en `playlists?mine=true`, que da las playlists propias;
    - responde 200 en `playlistItems` con `playlistId=LM`, que da "Liked Music" de YouTube Music;
    - responde 200 en `playlistItems` de cualquier playlist por su id;
    - responde 200 en `videos?part=status`, cuyo campo `embeddable` dice si un vídeo se puede insertar;
    - no lista las playlists de otros usuarios guardadas en la biblioteca;
    - la cuota por defecto es de 10 000 unidades al día, y cada página de un listado cuesta 1.
- **Comprobación 3, cuenta del propietario (2026-11-15):** la cuenta tiene canal de YouTube (`channels?mine=true` responde 200 con un canal). El id va al `.env`.
- **Comprobación 4, reproducción (2026-11-15):**
  - el reproductor embebido de YouTube (IFrame API), en una página servida por HTTP en `localhost`, reprodujo cuatro vídeos: una canción de la búsqueda, una de "Liked Music", un videoclip oficial y un vídeo que no es música;
  - con la cuenta Premium iniciada en el navegador, no salió ningún anuncio;
  - el primer intento dio error en las dos canciones, sin que llegara a anotarse el código, y tras recargar la página todas sonaron. T-10 debe tratar el error del reproductor igualmente;
  - el reproductor no usa el token de la app: Premium depende de la sesión de YouTube del navegador.
- **Decisiones (2026-11-15):**
  - **Ruta:** cliente PHP propio, sin Python. La biblioteca va por la **API oficial** con el token del usuario; la búsqueda, por la **API interna sin token**; la reproducción, con el **reproductor embebido de YouTube**.
  - **Scope:** `https://www.googleapis.com/auth/youtube.readonly`. Se mantiene D6. Por eso la biblioteca no muestra las playlists de otros usuarios guardadas; mostrarlas pediría el scope completo y la API interna con `TVHTML5`, y el usuario lo descarta.
  - **El token** sirve para la API oficial, no para la interna ni para reproducir.
  - **Verificar la app con Google** va en una tarea aparte, por crear.

## Definition of Done

- [x] Every acceptance criterion answered in "Resultados", with the date
- [x] T-09 to T-12 updated with the decisions
- [x] No secret, token or server configuration detail written in the repository
