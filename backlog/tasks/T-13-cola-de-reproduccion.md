---
id: T-13
title: Cola de reproducción con shuffle y repeat
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-13 — Cola de reproducción con shuffle y repeat

## Goal

Ver y editar la cola de reproducción (añadir, quitar, reordenar) y usar aleatorio y repetición, para controlar qué suena a continuación aunque mezcle canciones de los dos servicios.

## Context

Necesidad v1 del discovery: "ver y editar la cola de reproducción, con shuffle y repeat". Depende de T-06 y T-10, porque la cola puede llevar canciones de Spotify y de YouTube Music. Cómo se pasa de una canción de un servicio a otra del otro es la pregunta abierta 5.

## Acceptance criteria

```gherkin
Scenario: Añadir una canción a la cola
  Given una canción sonando
  When el usuario elige "Añadir a la cola" en otra canción
  Then esa canción aparece al final de la cola

Scenario: Ver la cola
  Given una cola con 3 canciones pendientes
  When el usuario abre la cola
  Then ve la canción actual y las 3 pendientes, en orden y con su servicio de origen

Scenario: Reordenar y quitar canciones
  Given una cola con las canciones A, B y C pendientes
  When el usuario mueve C al principio y quita B
  Then la cola queda C, A

Scenario: Cola mixta entre servicios
  Given una cola con una canción de Spotify seguida de una de YouTube Music
  When termina la canción de Spotify
  Then empieza a sonar la canción de YouTube Music sin que el usuario haga nada

Scenario: Aleatorio
  Given una cola con 10 canciones pendientes
  When el usuario activa el modo aleatorio
  Then las siguientes canciones se eligen al azar entre las pendientes
  And ninguna canción pendiente se repite antes de que suenen todas

Scenario: Repetición
  Given la repetición de la cola activada y la última canción sonando
  When termina la última canción
  Then vuelve a sonar la primera canción de la cola

Scenario: Fin de la cola
  Given la repetición desactivada y la última canción de la cola sonando
  When termina la canción
  Then la reproducción se detiene
  And la cola muestra que no quedan canciones pendientes
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Modos de repetición propuestos, a revisar: desactivada, repetir la cola y repetir una canción.
- Falta decidir si la cola se conserva al recargar la página o al cambiar de dispositivo.
- La pregunta abierta 5 decide qué pausa o corte se acepta al cambiar de servicio.
- **Notas del enriquecimiento de T-06/T-07/T-08 (2026-11-09):**
  - **Siguiente y anterior en T-06:** solo se mueven dentro del contexto de Spotify (álbum o playlist). Esta tarea decide cómo la cola de la app controla Spotify: por ejemplo, con `play({uris})` de T-06, que acepta `{contextUri?, uris?, offset}`. También decide cómo convive con el aleatorio y la repetición propios de Spotify.
  - **Playlists que el usuario solo sigue:** desde 2026 no se pueden listar sus canciones (T-08), así que no se pueden cargar en la cola de la app. Se reproducen como contexto de Spotify.
  - **Dónde va "Add to queue":** en las filas de canción de los resultados de T-07 y en las filas de pista del álbum de T-24.
  - **Textos de la interfaz:** van en inglés.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
