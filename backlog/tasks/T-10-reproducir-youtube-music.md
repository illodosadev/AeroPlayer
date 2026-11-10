---
id: T-10
title: Reproducir canciones de YouTube Music
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-10 — Reproducir canciones de YouTube Music

## Goal

Reproducir canciones de YouTube Music en el reproductor con los mismos controles que Spotify (play, pausa, siguiente, anterior, volumen, seek), para que los dos servicios se usen igual.

## Context

Necesidad v1 del discovery. Depende de T-09 y usa la barra de reproductor de T-21 (controles Aero, separada de T-03). La interfaz va en inglés. Es la mitad de YouTube del reproductor; la de Spotify es T-06. Cómo se reproduce (reproductor embebido de YouTube u otro) y si Premium evita anuncios es la pregunta abierta 2. En el móvil, la reproducción con la pantalla bloqueada es la pregunta abierta 3.

## Acceptance criteria

```gherkin
Scenario: Reproducir una canción de YouTube Music
  Given YouTube Music conectado y el reproductor abierto
  When el usuario reproduce una canción de YouTube Music
  Then el audio suena en la pestaña
  And la barra de reproductor muestra título, artista, carátula y el progreso de la canción

Scenario: Controles básicos
  Given una canción de YouTube Music sonando
  When el usuario usa pausa, play, volumen al 30 % y seek al minuto 1:30
  Then la reproducción se pausa, continúa, cambia a volumen 30 % y sigue desde 1:30

Scenario: Siguiente y anterior
  Given una lista de canciones de YouTube Music en reproducción
  When el usuario pulsa "siguiente" y después "anterior"
  Then suena la canción siguiente y después vuelve a sonar la anterior

Scenario: Canción no reproducible
  Given una canción de YouTube Music que no se puede reproducir (no disponible o con la inserción bloqueada)
  When el usuario intenta reproducirla
  Then ve el aviso "This song can't be played."
  And el reproductor queda listo para reproducir otra canción
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- **Bloqueada por la pregunta abierta 2** (método de reproducción y anuncios).
- Falta decidir si, cuando una canción falla dentro de una lista, se pasa sola a la siguiente. Es la misma decisión pendiente que en T-23 (errores de Spotify): conviene tomarla una vez para los dos servicios.
- El texto "This song can't be played." es el mismo que usa T-23 para Spotify (añadido al enriquecer T-06 el 2026-11-09).

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Escenario que llega de T-13 (D5):** "Cola mixta entre servicios": Given una cola con una canción de Spotify seguida de una de YouTube Music; When termina la de Spotify; Then empieza a sonar la de YouTube Music sin que el usuario haga nada, **en menos de 2 s** desde que terminó la anterior (D2, 2026-11-09). Y al revés.
- **D4 resuelve el Pendiente de saltar canciones que fallan:** "This song can't be played." y se pasa a la siguiente; tras 3 fallos seguidos se para con "Couldn't play 3 songs in a row. Playback stopped." (texto propuesto en T-13).
- **Cola (T-13):** añadir `youtube_music` a las fuentes que acepta la cola. T-10 depende de T-13, no al revés.
- **Escenarios que llegan de T-14:** "Reproducir una playlist mixta" y "YouTube Music desconectado" (sus canciones salen "Unavailable" y solo suena Spotify). Definir el equivalente de YouTube a `playback_error`, que emite el evento de fallo de T-13 `{item_id, origin, reason}` para que T-14 marque la canción.
- **Estado del composable:** exponer la misma forma que T-06 (canción actual con id = id del vídeo, `title`, `artists[]`, `album`, `duration_ms`, posición, `paused`), para que T-16, T-17 y T-19 funcionen sin cambios.
- **Historial (T-19):** las reproducciones de YouTube Music registran escuchas en `POST /history/listens` con `source: "youtube_music"` y las mismas reglas (30 s de reproducción real, D13). Hay que añadir esa fuente a la lista aceptada.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
