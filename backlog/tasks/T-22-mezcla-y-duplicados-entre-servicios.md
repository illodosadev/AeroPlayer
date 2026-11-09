---
id: T-22
title: Mezclar y quitar duplicados entre Spotify y YouTube Music
status: draft
discovery: reproductor-musica-privado
change: ~
priority: should
estimate: ~
language: es
---

# T-22 — Mezclar y quitar duplicados entre Spotify y YouTube Music

## Goal

Que la búsqueda y la biblioteca muestren los resultados de Spotify y de YouTube Music en una sola lista mezclada, en la que una canción que está en los dos servicios aparece una sola vez. Así se cumple la "biblioteca unificada" del discovery tal como la decidió el usuario el 2026-11-09 (pregunta abierta 6).

## Context

Creada por el orquestador del lote `/task-enrich-batch` del 2026-11-09. La propusieron T-07 y T-08 al enriquecerse: la mezcla y la eliminación de duplicados no se pueden probar hasta que existan los dos servicios.

- **Depende de** T-07 (búsqueda de Spotify), T-08 (biblioteca de Spotify), T-11 (búsqueda de YouTube Music) y T-12 (biblioteca de YouTube Music).
- **Lo que ya existe:** T-07 y T-08 dejan una lista única con un formato común (`source`, `id`, `uri`, `title`, `artists`, `album`, `duration_ms`, `image`, `playable`) y un `cursor` opaco por sección. T-11 y T-12 añaden `source: "youtube_music"`.
- **Prioridad should:** el discovery dice que la biblioteca unificada es deseable, pero que no conseguirla no sería un fracaso.

## Acceptance criteria

```gherkin
Scenario: Una canción que está en los dos servicios aparece una vez
  Given los dos servicios conectados
  And la búsqueda "One More Time" devuelve esa canción en Spotify y en YouTube Music
  When el usuario busca "One More Time"
  Then la sección "Songs" muestra esa canción una sola vez

Scenario: Si un servicio falla, se ve el otro sin mezclar
  Given los dos servicios conectados y YouTube Music devuelve un error
  When el usuario busca "One More Time"
  Then la sección "Songs" muestra los resultados de Spotify
  And la página avisa de que YouTube Music no está disponible ahora mismo
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Pendiente (hay que decidirlo con el usuario antes de enriquecer)**

- **Cuándo dos canciones son la misma.** Opciones:
  - Por ISRC. Spotify vuelve a devolver `external_ids` desde marzo de 2026, pero no se sabe si YouTube Music lo expone (pregunta abierta 2).
  - Por título, artista y duración normalizados, con una tolerancia de unos segundos.
- **Qué servicio reproduce** una canción que está en los dos.
- **Cómo se marca** una canción que está en los dos servicios.
- **Playlists:** si se deduplican o solo se listan juntas, cada una con su servicio.
- **Orden de la lista mezclada.** Spotify da la fecha de guardado de las canciones guardadas, pero no de las playlists.
- **Paginación entre servicios:** un cursor opaco con un offset o una continuación por servicio.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
