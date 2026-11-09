---
id: T-14
title: Playlists propias mixtas
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-14 — Playlists propias mixtas

## Goal

Crear dentro de la app playlists que mezclen canciones de Spotify y de YouTube Music, para tener listas propias que ninguno de los dos servicios permite por separado.

## Context

Necesidad v1 del discovery. Depende de T-07/T-08 y T-11/T-12 para encontrar canciones, y de T-13 para reproducir la lista en la cola. Si las playlists se guardan solo en la base de datos de la app o se sincronizan con los servicios es la pregunta abierta 4.

## Acceptance criteria

```gherkin
Scenario: Crear una playlist
  Given el usuario con sesión iniciada
  When crea una playlist con el nombre "Verano 2007"
  Then "Verano 2007" aparece en su lista de playlists propias, vacía

Scenario: Nombre vacío
  Given el usuario creando una playlist
  When la guarda sin nombre
  Then la playlist no se crea
  And ve un error de que el nombre es obligatorio

Scenario: Añadir canciones de los dos servicios
  Given la playlist "Verano 2007"
  When el usuario añade una canción de Spotify y otra de YouTube Music
  Then la playlist contiene las dos canciones, cada una con su servicio de origen

Scenario: Quitar y reordenar canciones
  Given la playlist con las canciones A, B y C
  When el usuario mueve C al principio y quita B
  Then la playlist queda C, A
  And el orden se mantiene al recargar la página

Scenario: Renombrar y borrar
  Given la playlist "Verano 2007"
  When el usuario la renombra a "Verano 2008" y después la borra
  Then la playlist deja de aparecer en su lista

Scenario: Reproducir una playlist mixta
  Given una playlist con canciones de Spotify y de YouTube Music
  When el usuario pulsa reproducir
  Then sus canciones se cargan en la cola en el orden de la playlist
  And empieza a sonar la primera

Scenario: Servicio desconectado
  Given una playlist con canciones de YouTube Music y YouTube Music desconectado
  When el usuario reproduce la playlist
  Then las canciones de YouTube Music se marcan como no disponibles
  And suenan solo las canciones de Spotify
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- **Depende de la pregunta abierta 4:** dónde se guardan las playlists y qué pasa cuando una canción deja de estar disponible en su servicio.
- Falta decidir si se permiten nombres duplicados y canciones repetidas en una misma lista.
- **Notas del enriquecimiento de T-07/T-08 (2026-11-09):**
  - **Playlists que el usuario solo sigue:** no se pueden añadir canciones desde ellas, porque desde 2026 Spotify no deja listar sus canciones (T-08).
  - **Dónde va "Add to playlist":** en las filas de canción de los resultados de T-07 y en las filas de pista del álbum de T-24.
  - **Formato de canción:** se usa el formato común de T-07 (`source`, `id`, `uri`, `title`…).
  - **Textos de la interfaz:** van en inglés.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
