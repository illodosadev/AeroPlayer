---
id: T-19
title: Historial de escucha y estadísticas
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: 6.25
language: es
---

# T-19 — Historial de escucha y estadísticas

## Goal

Guardar un historial propio de lo que se escucha y mostrar estadísticas (canciones y artistas más escuchados por periodo), para tener en un solo sitio lo que ninguno de los dos servicios ofrece combinado.

El historial es **independiente del servicio**: cada escucha recuerda de qué servicio viene y los datos de la canción tal como estaban al escucharla. Así sirve igual para Spotify, con el que se entrega, y para YouTube Music cuando exista T-10.

## Context

- Extra deseable del discovery ("Historial de escucha y estadísticas propias"), para después de la v1.
- **División propuesta (revisión del 2026-11-09).** Se mantiene en un solo fichero, como T-05, T-13 y T-25, pero se propone en dos cambios:
  - **T-19a — Registrar escuchas (3,75 puntos):** el contador de escuchas en el cliente, el endpoint para registrar escuchas y las escuchas pendientes en el navegador. Secciones "T-19a" de los criterios.
  - **T-19b — Historial y estadísticas (2,5 puntos):** la página `/history` con "Load more" y el borrado, la página `/stats` y los enlaces de la navegación. Secciones "T-19b" de los criterios. **Depende de T-19a.**
- **Depende de:**
  - **T-06:** el composable del reproductor, que vive en un layout persistente de Inertia, y su `player_state_changed` con la pista, la posición, `paused` y `timestamp`. El contador de escuchas se engancha a ese composable, así que sigue contando al navegar por la app. T-19a añade al composable la marca de los seeks que vienen de la barra de progreso, que T-06 no define (ver "Reglas propuestas"). (T-19a)
  - **T-07:** el formato común de canción (`source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image`). La copia que guarda cada escucha usa ese formato. (T-19a)
  - **T-23:** el estado `null` del SDK cuando la reproducción pasa a otro dispositivo o a otra pestaña. T-19 lo usa para dejar de contar. (T-19a)
  - **T-02:** solo el usuario con sesión iniciada ve y registra escuchas.
  - **T-03 / T-21:** el estilo Aero, los tests de navegador de Pest 4 y la carátula genérica `AeroCover` (`data-aero="cover-fallback"`).
  - **T-25 (en concreto T-25a, si T-25 se divide):** la navegación Aero de la app (`data-aero="app-nav"`), donde T-19b añade los enlaces "History" y "Stats". (T-19b)
- **Ya no depende de T-10** (decisión D5, 2026-11-09: primero Spotify, con un diseño independiente del servicio). T-10 está bloqueada por la pregunta abierta 2. El historial funciona con YouTube Music cuando T-10 exista (ver "Fuera de alcance").
- **Relación con T-13 (cola):**
  - La repetición de una canción ("repeat one") cuenta una escucha nueva en cada vuelta, y "Previous" que vuelve a empezar la canción también. T-19 lo detecta por el estado del reproductor, así que no necesita que T-13 esté hecha: se prueba con el SDK simulado.
  - Una canción que falla (D4 de T-13) antes de sumar 30 s de reproducción no cuenta. Si falla después de 30 s, ya contó.
- **Relación con T-15 (plan B móvil con Spotify Connect):** si cuenta la música que suena en la app oficial de Spotify mientras la web solo la controla es una decisión pendiente (ver "Pendiente").

## Acceptance criteria

```gherkin
# ==============================================================================
# T-19a — Registrar escuchas
# ==============================================================================

# --- Cuándo cuenta una escucha (cliente, con el SDK simulado de T-06) ----------
# "Pasan N s de reproducción" = avanza el reloj simulado del contador N segundos
# con el último estado del SDK en paused = false.

Scenario: Una canción cuenta como escuchada a los 30 segundos de reproducción
  Given el SDK simulado listo
  And el SDK emite "Track A" (uri "spotify:track:aaa", 180000 ms) en la posición 0 con paused = false
  When pasan 29 s de reproducción
  Then no se ha enviado ninguna petición "POST /history/listens"
  When pasa 1 s más de reproducción
  Then se envía una sola petición "POST /history/listens"
  And su cuerpo lleva track.source "spotify", track.uri "spotify:track:aaa", played_ms mayor o igual que 30000 y un play_id con formato UUID

Scenario: Una canción saltada antes de los 30 segundos no cuenta
  Given "Track A" (180000 ms) lleva 20 s de reproducción
  When el usuario pulsa "Next" y el SDK emite "Track B" en la posición 0
  And pasan 5 s de reproducción de "Track B"
  Then no se ha enviado ninguna petición "POST /history/listens" con track.uri de "Track A"

Scenario: El tiempo en pausa no cuenta
  Given "Track A" (180000 ms) lleva 20 s de reproducción
  When el SDK emite un estado de "Track A" con paused = true
  And pasan 60 s con la canción en pausa
  And el SDK emite un estado de "Track A" con paused = false
  And pasan 9 s de reproducción
  Then no se ha enviado ninguna petición "POST /history/listens"
  When pasa 1 s más de reproducción
  Then se envía una sola petición "POST /history/listens" para "Track A"

Scenario: Pausar al principio y reanudar no crea una reproducción nueva
  Given "Track A" (180000 ms) lleva 2 s de reproducción desde la posición 0
  When el SDK emite un estado de "Track A" en la posición 2000 ms con paused = true
  And pasan 10 s con la canción en pausa
  And el SDK emite un estado de "Track A" en la posición 2000 ms con paused = false
  And pasan 28 s de reproducción
  Then se envía una sola petición "POST /history/listens" para "Track A"
  And su played_ms es mayor o igual que 30000

Scenario: Avanzar con la barra de progreso no suma tiempo escuchado
  Given "Track A" (180000 ms) lleva 5 s de reproducción
  When el usuario mueve la barra de progreso a 2:50
  And pasan 10 s de reproducción hasta el final
  And el SDK emite "Track B" en la posición 0
  Then no se ha enviado ninguna petición "POST /history/listens" con track.uri de "Track A"

Scenario: Volver atrás con la barra de progreso no crea una escucha nueva
  Given "Track A" (180000 ms) lleva 40 s de reproducción y ya se envió su escucha
  When el usuario mueve la barra de progreso a 0:00
  And pasan 40 s de reproducción
  Then solo se ha enviado una petición "POST /history/listens" para "Track A"

Scenario: Cada vuelta de "repeat one" cuenta otra vez
  Given "Track A" (180000 ms) sonando en la posición 2:59
  And su escucha ya enviada con el play_id "3f8a2c9e-5b71-4d0a-8e6f-1a2b3c4d5e6f"
  When el SDK emite "Track A" en la posición 0 sin que el usuario haya movido la barra de progreso
  And pasan 30 s de reproducción
  Then se envía una segunda petición "POST /history/listens" para "Track A"
  And su play_id es distinto de "3f8a2c9e-5b71-4d0a-8e6f-1a2b3c4d5e6f"

Scenario: Volver a empezar una canción con "Previous" cuenta otra vez
  Given "Track A" (180000 ms) lleva 45 s de reproducción y ya se envió su escucha
  When el usuario pulsa "Previous" y el SDK emite "Track A" en la posición 0
  And pasan 30 s de reproducción
  Then se envía una segunda petición "POST /history/listens" para "Track A", con otro play_id

Scenario: Una canción de menos de 30 segundos cuenta si se escucha hasta el final
  Given el SDK emite "Short Track" (20000 ms) en la posición 0 con paused = false
  When pasan 19 s de reproducción
  Then se envía una sola petición "POST /history/listens" para "Short Track", con played_ms mayor o igual que 19000

Scenario: Una canción de menos de 30 segundos que se salta no cuenta
  Given "Short Track" (20000 ms) lleva 10 s de reproducción
  When el usuario pulsa "Next" y el SDK emite "Track B" en la posición 0
  Then no se ha enviado ninguna petición "POST /history/listens" para "Short Track"

Scenario: La reproducción pasa a otro dispositivo o a otra pestaña
  Given "Track A" (180000 ms) lleva 20 s de reproducción en esta pestaña
  When el SDK emite "player_state_changed" con estado null (T-23)
  And pasan 60 s
  Then no se ha enviado ninguna petición "POST /history/listens"

Scenario: Navegar por la app no reinicia el contador
  Given "Track A" (180000 ms) lleva 20 s de reproducción
  When el usuario navega a otra página de la app con Inertia
  And pasan 10 s de reproducción
  Then se envía una sola petición "POST /history/listens" para "Track A"

# --- Endpoint para registrar escuchas (backend) --------------------------------

Scenario: Registrar una escucha
  Given el usuario con sesión iniciada
  When envía "POST /history/listens" con play_id "9b2f6c1e-0d4a-4f7e-9a51-3c2d8e7f1a10", played_at "2026-11-09T18:05:00+01:00", played_ms 30500 y la canción de source "spotify", id "0DiWol3AO6WpXZgp0goxAV", uri "spotify:track:0DiWol3AO6WpXZgp0goxAV", título "One More Time", artistas ["Daft Punk"], álbum "Discovery", duración 320000 ms e image "https://i.scdn.co/image/abc"
  Then la respuesta tiene estado 201
  And queda guardada una sola escucha con ese play_id
  And esa escucha tiene source "spotify", track.id "0DiWol3AO6WpXZgp0goxAV", uri "spotify:track:0DiWol3AO6WpXZgp0goxAV", título "One More Time", artistas ["Daft Punk"], álbum "Discovery", duración 320000 ms e imagen "https://i.scdn.co/image/abc"
  And su played_at es "2026-11-09 17:05:00" en UTC
  And no se envía ninguna petición a Spotify

Scenario: La misma escucha enviada dos veces se guarda una vez
  Given ya existe una escucha con play_id "9b2f6c1e-0d4a-4f7e-9a51-3c2d8e7f1a10"
  When se vuelve a enviar "POST /history/listens" con ese play_id
  Then la respuesta tiene estado 200
  And sigue habiendo una sola escucha guardada con ese play_id

Scenario Outline: Una escucha no válida se rechaza
  Given el usuario con sesión iniciada
  When envía "POST /history/listens" con una escucha válida salvo <campo>
  Then la respuesta tiene estado 422
  And no se guarda ninguna escucha nueva

  Examples:
    | campo                                                                 |
    | played_ms 29999 con duration_ms 180000                                |
    | played_ms 18999 con duration_ms 20000                                 |
    | track.source "tidal"                                                  |
    | play_id "not-a-uuid"                                                  |
    | sin track.title                                                       |
    | track.artists como texto "Daft Punk" en lugar de una lista            |
    | played_at 10 minutos en el futuro según el reloj del servidor         |
    | played_at "ayer"                                                      |

Scenario: Registrar una escucha sin sesión
  Given un visitante sin sesión iniciada
  When envía "POST /history/listens" con una escucha válida y "Accept: application/json"
  Then la respuesta tiene estado 401
  And no se guarda ninguna escucha nueva

# --- Fallos al registrar (cliente) ---------------------------------------------

Scenario Outline: La escucha se guarda en el navegador si no se puede enviar
  Given "Track A" acaba de llegar a 30 s de reproducción
  And "POST /history/listens" <fallo>
  When el contador envía la escucha
  Then la escucha queda pendiente en el almacenamiento local del navegador
  When "POST /history/listens" vuelve a responder bien y pasan 60 s
  Then se reenvía la escucha con el mismo play_id
  And queda guardada una sola escucha de "Track A"
  And no queda ninguna escucha pendiente en el navegador

  Examples:
    | fallo                                      |
    | responde 503                               |
    | responde 429                               |
    | falla por un error de red                  |
    | responde 401 porque la sesión caducó       |
    | responde 419 por un token CSRF caducado    |

Scenario: Una escucha pendiente se envía al recargar la página
  Given una escucha pendiente de "Track A" con play_id "7c1d9e2a-4b3f-4a8e-9d0c-2e5f6a7b8c9d" en el navegador
  When el usuario recarga la página y "POST /history/listens" responde bien
  Then se envía "POST /history/listens" con play_id "7c1d9e2a-4b3f-4a8e-9d0c-2e5f6a7b8c9d"
  And no queda ninguna escucha pendiente en el navegador

Scenario: Como máximo 500 escuchas pendientes
  Given 500 escuchas pendientes en el navegador, la más antigua con play_id "1e0f2a3b-4c5d-4e6f-8a7b-9c0d1e2f3a4b"
  And "POST /history/listens" responde 503
  When "Track A" llega a 30 s de reproducción y su envío falla
  Then sigue habiendo 500 escuchas pendientes en el navegador
  And entre ellas está la de "Track A"
  And ya no está la de play_id "1e0f2a3b-4c5d-4e6f-8a7b-9c0d1e2f3a4b"

Scenario: Una escucha rechazada por el servidor no se reintenta
  Given "Track A" acaba de llegar a 30 s de reproducción
  And "POST /history/listens" responde 422
  When el contador envía la escucha
  Then no queda ninguna escucha pendiente en el navegador
  And pasados 120 s no se ha vuelto a enviar

# ==============================================================================
# T-19b — Historial y estadísticas (depende de T-19a)
# ==============================================================================

# --- Historial -----------------------------------------------------------------

Scenario: Una canción escuchada aparece la primera en el historial
  Given el navegador con la zona horaria "Europe/Madrid"
  And una escucha de "Old Song" con played_at "2026-11-09 10:00:00" UTC
  And una escucha de "One More Time" de "Daft Punk", del álbum "Discovery", con source "spotify" y played_at "2026-11-09 17:05:00" UTC
  When el usuario abre "/history"
  Then la primera fila muestra "One More Time", "Daft Punk", "Discovery", "Nov 9, 2026, 6:05 PM" y la etiqueta "Spotify"
  And la segunda fila muestra "Old Song"

Scenario: El historial no depende de Spotify
  Given escuchas guardadas de "One More Time"
  And Spotify sin conectar
  When el usuario abre "/history"
  Then la página muestra "One More Time" con los datos guardados
  And no se envía ninguna petición a Spotify

Scenario: Escucha sin carátula
  Given una escucha guardada con image null
  When el usuario abre "/history"
  Then su fila muestra el elemento con data-aero="cover-fallback"

Scenario: Cargar más escuchas
  Given 120 escuchas guardadas
  When el usuario abre "/history"
  Then la página muestra 50 filas, de la más reciente a la más antigua
  When pulsa "Load more"
  Then la página muestra 100 filas, sin repetidas
  When vuelve a pulsar "Load more"
  Then la página muestra 120 filas
  And ya no muestra "Load more"

Scenario: Falla "Load more" en el historial
  Given 120 escuchas guardadas y "/history" mostrando 50 filas
  And la petición de la página siguiente responde 503
  When el usuario pulsa "Load more"
  Then la página sigue mostrando las 50 filas
  And muestra "Couldn't load more listens. Try again."
  And "Load more" sigue visible y activo
  When la petición vuelve a responder bien y el usuario pulsa otra vez "Load more"
  Then la página muestra 100 filas, sin repetidas

Scenario: Historial vacío
  Given no hay ninguna escucha guardada
  When el usuario abre "/history"
  Then ve "No listens yet."
  And no ve el botón "Clear history"

Scenario Outline: Las páginas del historial sin sesión
  Given un visitante sin sesión iniciada
  When pide "<ruta>"
  Then se le redirige a "/login"

  Examples:
    | ruta     |
    | /history |
    | /stats   |

Scenario: Enlaces en la navegación de la app [test de navegador]
  Given el usuario con sesión iniciada
  When mira la navegación de la app (data-aero="app-nav", de T-25)
  Then ve los enlaces "History" y "Stats"
  When pulsa "History"
  Then está en "/history"
  When pulsa "Stats"
  Then está en "/stats"

# --- Estadísticas --------------------------------------------------------------
# Los escenarios que nombran un periodo dependen de la regla propuesta
# "Periodos de las estadísticas" (a confirmar en /opsx-propose).

Scenario: Canciones y artistas más escuchados
  Given el reloj del servidor en "2026-11-09 12:00:00" UTC
  And en los últimos 7 días: 3 escuchas de "Song A" de "Artist X", 2 de "Song B" de "Artist X" y "Artist Y", y 1 de "Song C" de "Artist Z"
  When el usuario abre "/stats" y elige "Last 7 days"
  Then "Top songs" muestra, en este orden, "Song A" (3 listens), "Song B" (2 listens) y "Song C" (1 listen)
  And "Top artists" muestra, en este orden, "Artist X" (5 listens), "Artist Y" (2 listens) y "Artist Z" (1 listen)
  And la página muestra "6 listens" como total del periodo

# Depende de la regla propuesta "Periodos de las estadísticas".
Scenario Outline: Periodos de las estadísticas
  Given el reloj del servidor en "2026-11-09 12:00:00" UTC y el navegador con la zona horaria "UTC"
  And una escucha de "Song A" hace 3 días, otra hace 20 días, otra el "2026-02-01" y otra el "2025-06-01"
  When el usuario abre "/stats" y elige "<periodo>"
  Then la página muestra "<total>" como total del periodo

  Examples:
    | periodo      | total      |
    | Last 7 days  | 1 listen   |
    | Last 30 days | 2 listens  |
    | This year    | 3 listens  |
    | All time     | 4 listens  |

# Depende de la regla propuesta "Periodos de las estadísticas".
Scenario Outline: "This year" empieza el 1 de enero en la zona horaria del navegador
  Given el reloj del servidor en "2025-12-31 23:30:00" UTC (ya 1 de enero de 2026 en Madrid)
  And una escucha con played_at "2025-12-31 22:50:00" UTC (31 de diciembre a las 23:50 en Madrid)
  When se pide "/stats" con el periodo "This year" y <zona>
  Then la página muestra "<resultado>"

  Examples:
    | zona                       | resultado                  |
    | tz "Europe/Madrid"         | No listens in this period. |
    | tz "UTC"                   | 1 listen                   |
    | sin tz                     | 1 listen                   |
    | tz "Mars/Olympus"          | 1 listen                   |

# Depende de la regla propuesta "Periodos de las estadísticas".
Scenario Outline: Periodo por defecto y periodo desconocido
  Given escuchas guardadas
  When el usuario pide "<ruta>"
  Then la respuesta tiene estado 200
  And el periodo seleccionado es "Last 30 days"

  Examples:
    | ruta                 |
    | /stats               |
    | /stats?period=decade |

Scenario: Empate en número de escuchas
  Given en el periodo 2 escuchas de "Song A", la última hace 2 días, y 2 de "Song B", la última hace 1 día
  When el usuario abre "/stats"
  Then "Top songs" muestra "Song B" antes que "Song A"

Scenario: Como máximo 10 canciones y 10 artistas
  Given en el periodo escuchas de 12 canciones distintas de 12 artistas distintos
  When el usuario abre "/stats"
  Then "Top songs" muestra 10 filas
  And "Top artists" muestra 10 filas

Scenario: Una canción cuyo título cambió cuenta como una sola
  Given 1 escucha de la canción de source "spotify" e id "4uLU6hMCjMI75M1A2tKUQC" con título "Song A" hace 5 días
  And 1 escucha de la misma canción con título "Song A - Remastered" hace 1 día
  When el usuario abre "/stats"
  Then "Top songs" muestra una sola fila para esa canción, "Song A - Remastered" (2 listens)

# Depende de la regla propuesta "Periodos de las estadísticas".
Scenario: Periodo sin escuchas
  Given solo hay escuchas de hace 60 días
  When el usuario abre "/stats" y elige "Last 7 days"
  Then ve "No listens in this period."
  And no ve las listas "Top songs" ni "Top artists"

# --- Borrar el historial -------------------------------------------------------
# Todos los escenarios de esta sección dependen de la regla propuesta
# "Borrar el historial" (a confirmar en /opsx-propose).

Scenario: Borrar el historial
  Given 5 escuchas guardadas y 1 escucha pendiente en el navegador
  When el usuario abre "/history" y pulsa "Clear history"
  Then ve el diálogo "Clear your entire listening history? This can't be undone." con los botones "Clear history" y "Cancel"
  When confirma con "Clear history"
  Then se envía "DELETE /history"
  And no queda ninguna escucha guardada
  And no queda ninguna escucha pendiente en el navegador
  And la página muestra "No listens yet."
  When abre "/stats" con el periodo "All time"
  Then ve "No listens in this period."

Scenario: Cancelar el borrado
  Given 5 escuchas guardadas
  When el usuario pulsa "Clear history" y después "Cancel"
  Then no se envía "DELETE /history"
  And la página sigue mostrando las 5 escuchas

Scenario: Falla el borrado
  Given 5 escuchas guardadas y "DELETE /history" responde 500
  When el usuario pulsa "Clear history" y confirma
  Then la página muestra "Couldn't clear your history. Try again."
  And sigue mostrando las 5 escuchas

Scenario: Borrar el historial sin sesión
  Given un visitante sin sesión iniciada y 5 escuchas guardadas
  When envía "DELETE /history" con "Accept: application/json"
  Then la respuesta tiene estado 401
  And siguen guardadas las 5 escuchas
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D13 — Umbral de escucha:** una canción cuenta como escuchada **tras 30 segundos de reproducción**, el mismo umbral que usa Spotify. Las reglas exactas (pausa, seek, repeticiones y canciones cortas) están en "Reglas propuestas".
- **D5 — Primero Spotify, independiente del servicio:** la escucha guarda `source` y T-19 se entrega con Spotify. T-10 deja de ser requisito: el historial funciona con YouTube Music cuando exista T-10.
- Interfaz en inglés (T-01): los textos de pantalla de los escenarios están en inglés.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

*Las dos primeras están pendientes de que el propietario las confirme (ver "Pendiente"); los escenarios que dependen de ellas lo indican con un comentario.*

- **Periodos de las estadísticas (propuesta, a confirmar):**
  - "Last 7 days" (`7d`), "Last 30 days" (`30d`), "This year" (`year`) y "All time" (`all`), en el parámetro `period`. Por defecto, y con un valor desconocido, "Last 30 days" (respuesta 200, sin error).
  - "Last 7 days" y "Last 30 days" son ventanas móviles desde ahora (7 × 24 h y 30 × 24 h).
  - "This year" empieza el 1 de enero a las 00:00 en la zona horaria del navegador. El frontend **siempre** envía esa zona en el parámetro `tz` (IANA, de `Intl.DateTimeFormat().resolvedOptions().timeZone`). Si falta o no es una zona válida, se usa UTC.
- **Borrar el historial (propuesta, a confirmar):**
  - Botón "Clear history" en `/history`, con un diálogo de confirmación: "Clear your entire listening history? This can't be undone.", botones "Clear history" y "Cancel".
  - `DELETE /history` borra todas las escuchas. Es irreversible: no hay papelera ni deshacer. Responde 204, o 401 sin sesión.
  - En el navegador también se descartan las escuchas pendientes de T-19a, para que no reaparezcan después del borrado.
  - Si falla: "Couldn't clear your history. Try again." y la lista sigue igual.
- **Tiempo escuchado (T-19a):**
  - Es el tiempo real que pasa con el reproductor de esta pestaña sonando (último estado no nulo con `paused = false`), medido con un reloj monótono del navegador (`performance.now()`), no con la posición de la canción.
  - No cuenta el tiempo en pausa, ni el tiempo con el estado `null` (otro dispositivo u otra pestaña, T-23), ni el tiempo con la pestaña cerrada.
  - **Seek con la barra de progreso:** no suma ni resta tiempo escuchado y no crea una reproducción nueva. Saltar al final no hace que cuente; volver atrás y escuchar otra vez la misma parte sigue sumando a la misma reproducción, que cuenta una sola vez.
- **Una escucha por reproducción (T-19a).** Una reproducción nueva empieza cuando:
  - el estado del reproductor muestra una canción distinta (otro `uri`) de la anterior, o
  - la misma canción **salta hacia atrás**: la posición anterior, interpolada como en T-06, era de más de 3 s y la nueva es de menos de 3 s, y el salto **no lo provocó el usuario moviendo la barra de progreso**.

  Así cuentan otra vez la vuelta de "repeat one" y "Previous" que reinicia la canción, se hagan como se hagan: hoy T-13 propone volver a pedir el `uri` con `play` para "repeat one" y `seek(0)` para "Previous" después de 3 s, y T-06 usa `previousTrack()`. Pausar y reanudar no es un salto hacia atrás. Para distinguir los seeks de la barra, el composable del reproductor de T-06 tiene que marcar los seeks que vienen de la barra de progreso. Hoy T-06 no lo define, así que **esa marca entra en el alcance de T-19a**: es un añadido pequeño al composable de T-06 (por ejemplo, un indicador `lastSeekFromBar` que la barra pone al hacer seek) y se hace en el cambio de T-19a, sin bloquearlo.

  Cada reproducción tiene un `play_id` (UUID v4) creado en el navegador. Cuando su tiempo escuchado llega al umbral, se envía la escucha **en ese momento** (no al acabar la canción) y esa reproducción ya no se vuelve a enviar.
- **Umbral (T-19a):** `min(30 000 ms, max(duration_ms − 1 000 ms, 1 000 ms))`.
  - Canciones de 31 s o más: 30 s.
  - Canciones de menos de 31 s: la duración menos 1 s, es decir, cuentan **si se escuchan hasta el final**, con 1 s de margen por la granularidad de los eventos del SDK. Una de 20 s cuenta a los 19 s; una de 30,5 s, a los 29,5 s.
  - Canciones de 1 s o menos: nunca cuentan (el umbral de 1 s no se alcanza). Se acepta.
  - El servidor aplica la misma fórmula al validar `played_ms` (422 si no llega).
- **Endpoint `POST /history/listens` (T-19a; el contrato exacto se fija en el diseño):**
  - Cuerpo: `{play_id, played_at, played_ms, track: {source, id, uri, title, artists, album, duration_ms, image}}`. `track` es el formato común de T-07, sin `playable`.
  - `played_at` es la hora en que **empezó** la reproducción, en ISO 8601 con zona horaria. Se guarda en UTC.
  - Validación: `play_id` UUID; `played_at` fecha válida y no más de 5 minutos en el futuro según el servidor; `played_ms` entero ≥ umbral; `track.source` en la lista de servicios que conoce la app (hoy solo `spotify`); `track.id`, `track.uri` y `track.title` obligatorios (máximo 255, 255 y 500 caracteres); `track.artists` lista de textos (puede estar vacía); `track.album` texto o null; `track.image` URL o null; `track.duration_ms` entero > 0.
  - Respuestas: 201 si se guarda; 200 si ya existía ese `play_id` (no se duplica: índice único en `play_id`); 422 si no es válida; 401 sin sesión. Siempre JSON.
  - No llama a Spotify: la copia de los datos viene del cliente.
- **Copia de la canción:** cada escucha guarda título, artistas, álbum, imagen y duración tal como estaban al escucharla. El historial y las estadísticas nunca piden nada a Spotify, así que funcionan con Spotify desconectado o caído.
- **De dónde salen los datos en el cliente:** de `track_window.current_track` del último estado del SDK (`id`, `uri`, `name`, `artists[].name`, `album.name`, la primera imagen de `album.images`, `duration_ms`), traducidos al formato común de T-07.
- **Escuchas pendientes en el navegador (T-19a):**
  - Si el envío falla por red, 5xx, 401, 419 o 429, la escucha se guarda en `localStorage` (clave propuesta `aeroplayer.pendingListens`) con su `play_id`.
  - Se reintenta: al cargar cualquier página de la app, con el evento `online`, cada 60 s mientras haya pendientes y antes de enviar una escucha nueva. Siempre con el mismo `play_id`, así que el servidor no duplica.
  - Un 422 descarta la escucha sin reintentar (y deja un aviso en la consola).
  - Como máximo 500 pendientes; al añadir la 501, se descarta la más antigua.
  - Si `localStorage` no está disponible (por ejemplo, navegación privada estricta), se intenta una vez y, si falla, la escucha se pierde.
  - Tras un 401 la escucha espera hasta que el usuario vuelva a iniciar sesión en ese navegador.
- **Página `/history` (T-19b):**
  - De la más reciente a la más antigua por `played_at` (y por id en caso de empate).
  - 50 escuchas por página, con "Load more" (mismo texto que T-07 y T-08) y paginación por cursor (`cursorPaginate`), para que no se repitan filas aunque entren escuchas nuevas mientras se pagina. Si falla la página siguiente: "Couldn't load more listens. Try again.", las filas cargadas se quedan y "Load more" sigue activo, como en T-07.
  - Cada fila: `AeroCover`, título, artistas separados por comas, álbum, fecha y hora, y la etiqueta del servicio ("Spotify").
  - **Fecha y hora:** se guardan en UTC y se muestran en la zona horaria del navegador con `Intl.DateTimeFormat('en-US', {dateStyle: 'medium', timeStyle: 'short'})`, por ejemplo "Nov 9, 2026, 6:05 PM".
  - Vacía: "No listens yet." y sin botón "Clear history".
- **Página `/stats` (T-19b):**
  - **Muestra:** el total de escuchas del periodo ("1 listen" / "N listens"), "Top songs" y "Top artists", como máximo 10 filas cada uno, con su número de escuchas.
  - **Canciones:** se agrupan por `source` + `track.id`. La fila muestra los datos de la escucha más reciente de esa canción.
  - **Artistas:** cada artista de una canción con varios cuenta una escucha (una escucha de "Song B" de "Artist X" y "Artist Y" suma 1 a cada uno). Se agrupan por nombre exacto, así que el mismo nombre en los dos servicios suma junto cuando exista T-10.
  - **Orden:** más escuchas primero; en empate, la escucha más reciente primero; después, el título (o el nombre) en orden alfabético.
  - Periodo sin escuchas: "No listens in this period." y sin las listas.
- **Navegación (T-19b):** los enlaces "History" y "Stats" se añaden a la navegación Aero de T-25 (`data-aero="app-nav"`). T-25 es dueña del componente; T-19b solo añade sus dos enlaces.
- **Acceso a datos:** todas las lecturas y escrituras, incluidas las consultas de agregados de las estadísticas, van por un repositorio concreto en `app/Repositories/` (por ejemplo, `ListenRepository`), sin interfaz (`openspec/config.yaml`).
- **Modelo de datos (se concreta en el diseño):** una tabla de escuchas con índice único en `play_id` e índice en `played_at`. Para contar artistas de forma portable entre SQLite (tests) y MySQL (Hostinger), se propone una tabla aparte con un artista por fila (escucha, nombre, posición) en lugar de agregar sobre una columna JSON. Al ser una app de un solo usuario, si hace falta o no `user_id` se decide en el diseño.
- **Textos de la interfaz** (en inglés): "History", "Stats", "No listens yet.", "Load more", "Couldn't load more listens. Try again.", "Clear history", "Cancel", "Clear your entire listening history? This can't be undone.", "Couldn't clear your history. Try again.", "Top songs", "Top artists", "Last 7 days", "Last 30 days", "This year", "All time", "No listens in this period.", "1 listen" / "N listens", "Spotify".

**Casos límite**

- **Dos pestañas reproduciendo:** Spotify solo suena en un dispositivo a la vez por cuenta. La pestaña que pierde el audio recibe el estado `null` (T-23) y deja de contar, así que solo envía la escucha la pestaña que suena de verdad. Si las dos llegaran a enviar la misma canción, serían dos reproducciones con `play_id` distinto. Se comprueba a mano junto con el escenario manual de dos pestañas de T-23.
- **"Play here" (T-23):** al traer la música de vuelta a la web empieza a contar el tiempo en esta pestaña. Si la canción ya iba por la mitad en otro dispositivo, cuenta si suena 30 s más aquí. El tiempo en el otro dispositivo no cuenta.
- **Recarga con música sonando:** una recarga corta el audio (T-06), así que la reproducción en curso que no llegó al umbral se pierde. Una que ya llegó y no se pudo enviar sigue pendiente en el navegador y se envía con su mismo `play_id`.
- **Cerrar la pestaña justo después del umbral:** la escucha puede quedar pendiente y se envía la próxima vez que se abra la app en ese navegador. Si se cierra antes de los 30 s, no cuenta.
- **Pestaña en segundo plano:** el navegador ralentiza los temporizadores (hasta uno por minuto). Como el tiempo se calcula con diferencias de `performance.now()`, el cómputo es correcto, pero el envío puede retrasarse hasta un minuto. `played_at` no cambia.
- **Estados repetidos o fuera de orden del SDK:** se usa siempre el último estado por `timestamp`, como T-06. Un estado repetido de la misma canción en una posición cercana no es una reproducción nueva.
- **Pausa larga:** pausar una hora y reanudar sigue siendo la misma reproducción.
- **Canción que falla o no está disponible:** si falla antes de sumar 30 s de reproducción, no cuenta; si falla después, la escucha ya se registró.
- **Canción "relinked" por Spotify:** el SDK puede informar de un `id` distinto del pedido. Se guarda el que informa el SDK, así que la misma canción podría aparecer con dos ids en las estadísticas. Se acepta.
- **Reloj del cliente desajustado:** `played_at` viene del navegador. Más de 5 minutos en el futuro da 422 (la escucha se descarta). Un reloj atrasado se acepta tal cual.
- **Cambio de hora (horario de verano):** como se guarda en UTC y se muestra con la zona del navegador, las horas se ven correctas a ambos lados del cambio.
- **Borrar el historial con música sonando:** la reproducción en curso que llegue al umbral después del borrado sí se guarda.
- **Historial grande:** unas 100 escuchas al día son unas 36 000 al año. La lista se pagina de 50 en 50, y las estadísticas usan el índice en `played_at` y agregados en la base de datos, nunca cargando todas las filas en PHP.
- **Sin red (offline):** la escucha queda pendiente y se envía con el evento `online` o en el siguiente reintento.

**Pistas para los tests**

- **Pest (feature):**
  - `POST /history/listens`: el 201, el 200 idempotente, la tabla de 422 como dataset, el 401, la conversión a UTC y `Http::preventStrayRequests()` para comprobar que no se llama a Spotify. Lo guardado se comprueba leyendo la escucha (por el repositorio o con aserciones de modelo, que los tests pueden usar).
  - Las estadísticas con escuchas creadas por factory y `$this->travelTo(...)`; "This year" con `tz=Europe/Madrid`, `tz=UTC`, sin `tz` y con una `tz` no válida; el periodo desconocido; el empate; el límite de 10; el agrupado por `source` + `id`.
  - `DELETE /history` y la página `/history` (orden, cursor, 50 por página) comprobando las props de Inertia.
- **Tests de navegador de Pest 4 con el SDK simulado de T-06:**
  - El contador recibe un reloj inyectable (por ejemplo, una función `now()` configurable o el reloj simulado de Playwright) para avanzar 29 s / 30 s sin esperar de verdad.
  - Los eventos del SDK se emiten desde el test, como en T-06 y T-23. Las peticiones `POST /history/listens` se cuentan en el servidor (escuchas guardadas) o interceptando la red.
  - Las escuchas pendientes se comprueban leyendo y sembrando `localStorage` desde el test (el tope de 500 se siembra así). Los fallos del endpoint se simulan con una ruta que responde 503/429/401/419 o cortando la red.
  - La zona horaria del navegador se fija con la opción `timezoneId` de Playwright ("Europe/Madrid").
  - **Fechas formateadas:** las versiones recientes de ICU ponen un espacio estrecho sin salto (U+202F) antes de "PM". Al comparar "Nov 9, 2026, 6:05 PM" hay que normalizar los espacios (por ejemplo, sustituir `\s` y U+202F por un espacio normal) en el test.
  - El diálogo de borrado, "Load more" y su fallo, la carátula genérica (`data-aero="cover-fallback"`) y los enlaces de la navegación.
- **@manual (Premium real):** que "repeat one" y "Previous" emiten un estado de la misma canción cerca de la posición 0, que pasar la reproducción a otro dispositivo emite el estado `null`, y el caso de las dos pestañas. Van como lista de comprobación en el `tasks.md` del cambio.

**Fuera de alcance**

- **Escuchas de YouTube Music:** llegan con T-10, que registra sus escuchas con su `source` (T-13 propone `youtube_music`) y las mismas reglas de tiempo escuchado y reproducción. T-19 no toca el reproductor de YouTube.
- Importar el historial de Spotify ("recently played") o de YouTube Music, o sincronizar con servicios externos (por ejemplo, Last.fm).
- Borrar escuchas sueltas, exportar el historial y deshacer el borrado.
- Gráficas, tiempo total de escucha, álbumes o géneros más escuchados, y periodos personalizados.
- Mezclar como una sola la misma canción de los dos servicios en las estadísticas: depende del criterio de "misma canción" de T-22.
- Reproducir desde el historial o desde las estadísticas (las filas no son reproducibles en esta tarea).

**Pendiente (no decidido)**

- **Regla propuesta "Periodos de las estadísticas"** (7 días, 30 días, este año y siempre; por defecto 30 días; "This year" en la zona del navegador): propuesta, a confirmar en /opsx-propose.
- **Regla propuesta "Borrar el historial"** (borrar todo, con confirmación, irreversible, descartando también las pendientes del navegador): propuesta, a confirmar en /opsx-propose.
- **Reproducción por Spotify Connect (T-15):** si la música que suena en la app oficial del móvil y controla la web cuenta en el historial. Con esta tarea no cuenta, porque solo se mide la reproducción en la pestaña.
- **Verificar con el SDK real que el estado `null` llega** cuando la reproducción pasa a otro dispositivo o a otra pestaña. Sigue sin verificar (pendiente de T-23). Si no llega, el contador seguiría sumando tiempo que no se escucha en esta pestaña, y hay que usar la detección alternativa que elija T-23.
- **Verificar con el SDK real** que la vuelta de "repeat one" y "Previous" emiten un `player_state_changed` de la misma canción cerca de la posición 0. Si no, hay que cambiar la detección de una reproducción nueva.
- Si T-13 cambia cómo hace "repeat one" o "Previous", revisar que la regla del salto hacia atrás sigue sirviendo.

**Estimación: 6,25 puntos (T-19a 3,75 + T-19b 2,5).**

*T-19a — Registrar escuchas (3,75 puntos):*
- **Endpoint de escuchas, validación con el umbral, idempotencia, migración y repositorio (0,75 puntos):** contrato pequeño pero con muchos casos de 422.
- **Contador de escuchas en el cliente (1,5 puntos):** tiempo real sin pausas, seek de la barra, salto hacia atrás por "repeat one" y "Previous", canciones cortas y estado `null`. Es la lógica más delicada.
- **Escuchas pendientes en `localStorage` y reintentos (0,75 puntos):** subida desde 0,5 al revisar: cinco disparadores de reintento, cinco tipos de fallo, el tope de 500 y `localStorage` no disponible.
- **Tests de navegador con el SDK simulado, reloj inyectable y fallos de red, más la comprobación manual (0,75 puntos):** subida desde 0,5 al revisar: el SDK simulado existe (T-06), pero el reloj inyectable, sembrar `localStorage` y simular los fallos son nuevos.

*T-19b — Historial y estadísticas (2,5 puntos):*
- **Página de historial con cursor, zona horaria, fallo de "Load more", borrado con confirmación y enlaces de navegación (1 punto).**
- **Página de estadísticas: periodos con zona horaria, agregados por canción y por artista, empates y límite (1 punto).**
- **Tests (0,5 puntos):** datasets de Pest con `travelTo` y tests de navegador para la zona horaria, el diálogo y "Load more"; reutilizan la configuración de T-19a.

## Definition of Done

- [ ] Criterios automatizables cubiertos por tests; los @manual en el checklist del cambio
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
