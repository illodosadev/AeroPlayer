---
id: T-11
title: Buscar y reproducir en YouTube Music
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-11 — Buscar y reproducir en YouTube Music

## Goal

Buscar canciones, álbumes y artistas de YouTube Music y reproducirlos desde los resultados, igual que en Spotify (T-07).

## Context

Necesidad v1 del discovery. Depende de T-09 y T-10. Cómo se presentan juntos los resultados de los dos servicios es la pregunta abierta 6.

## Acceptance criteria

```gherkin
Scenario: Buscar en YouTube Music
  Given YouTube Music conectado
  When el usuario busca "Daft Punk"
  Then ve resultados de canciones, álbumes y artistas de YouTube Music relacionados con "Daft Punk"

Scenario: Reproducir una canción desde los resultados
  Given resultados de búsqueda de YouTube Music en pantalla
  When el usuario pulsa una canción
  Then esa canción empieza a sonar en el reproductor

Scenario: Abrir un álbum o artista desde los resultados
  Given resultados de búsqueda de YouTube Music con un álbum y un artista
  When el usuario abre el álbum
  Then ve la lista de pistas del álbum
  When abre el artista
  Then ve sus canciones y álbumes

Scenario: Búsqueda sin resultados
  Given YouTube Music conectado
  When el usuario busca "zzqxjkwvvy"
  Then ve un mensaje de que no hay resultados en YouTube Music

Scenario: La integración no oficial falla
  Given YouTube Music conectado y la integración no oficial devuelve un error
  When el usuario busca algo
  Then ve un mensaje de que la búsqueda en YouTube Music no está disponible ahora mismo
  And la búsqueda en Spotify sigue funcionando
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Depende de la pregunta abierta 2 (librería).
- **Pregunta abierta 6, resuelta el 2026-11-09:** una sola lista mezclada y sin duplicados. Al enriquecer, reescribir esta tarea así:
  - **Segunda fuente de la lista de T-07:** YouTube Music se suma a la lista única de búsqueda, con el formato común de T-07 y `source: "youtube_music"`, sin cambiar los componentes de la lista. El paginado usa un `cursor` opaco por sección.
  - **Sin resultados:** el mensaje "no hay resultados en YouTube Music" se sustituye por el global 'No results for "…"', que solo aparece si ningún servicio devuelve nada.
  - **Fallo parcial:** se mantienen los resultados de Spotify y se avisa con "YouTube Music search isn't available right now."
  - **Duplicados:** la mezcla y la eliminación de duplicados entre servicios es T-22.
- **Textos de la interfaz:** van en inglés, porque la interfaz es en inglés.
- **Páginas de álbum y artista de YouTube Music:** quedan diferidas y ninguna tarea las tiene todavía. T-24 solo hace las de Spotify.

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **T-14:** "Add to playlist" en las filas de canción de YouTube Music; el endpoint de añadir de T-14 acepta `source: "youtube_music"`. Esta tarea se lleva el escenario de T-14 "Añadir canciones de los dos servicios".

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
