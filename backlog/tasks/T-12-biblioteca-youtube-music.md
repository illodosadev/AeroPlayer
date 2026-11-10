---
id: T-12
title: Biblioteca de YouTube Music
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-12 — Biblioteca de YouTube Music

## Goal

Ver y reproducir las playlists y los "me gusta" del usuario en YouTube Music, para tener su música habitual de los dos servicios en el reproductor.

## Context

Necesidad v1 del discovery. Es la mitad de YouTube de la biblioteca; la de Spotify es T-08. Depende de T-09 y T-10.

## Acceptance criteria

```gherkin
Scenario: Ver mis playlists de YouTube Music
  Given YouTube Music conectado y el usuario con playlists
  When abre la biblioteca de YouTube Music
  Then ve todas sus playlists con nombre y carátula

Scenario: Reproducir una playlist
  Given la biblioteca de YouTube Music abierta
  When el usuario abre una playlist y pulsa reproducir
  Then empieza a sonar la primera canción de la playlist
  And las siguientes canciones de la playlist quedan a continuación

Scenario: Ver y reproducir mis "me gusta"
  Given YouTube Music conectado y el usuario con canciones marcadas como "me gusta"
  When abre "Me gusta" de YouTube Music y pulsa una canción
  Then esa canción empieza a sonar

Scenario: Biblioteca vacía
  Given YouTube Music conectado y el usuario sin playlists ni "me gusta"
  When abre la biblioteca de YouTube Music
  Then ve un mensaje de que su biblioteca de YouTube Music está vacía

Scenario: La integración no oficial falla
  Given YouTube Music conectado y la integración no oficial devuelve un error
  When el usuario abre la biblioteca de YouTube Music
  Then ve un mensaje de que no se pudo cargar la biblioteca
  And la biblioteca de Spotify sigue disponible
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Depende de la pregunta abierta 2.
- **Pregunta abierta 6, resuelta el 2026-11-09:** una sola lista mezclada y sin duplicados. Al enriquecer, reescribir esta tarea así:
  - **Misma vista que T-08:** alimenta la vista unificada `/library` de T-08 con elementos de formato común y `source: "youtube_music"`. No hace una "biblioteca de YouTube Music" aparte. Las playlists usan la ruta `/library/playlists/youtube-music/{id}`.
  - **Biblioteca vacía:** el escenario se reescribe para la vista unificada.
  - **Fallo parcial:** si YouTube Music falla, la parte de Spotify sigue visible dentro de la vista unificada.
  - **Duplicados:** van en T-22.
- **Textos de la interfaz:** van en inglés.
- Falta decidir el límite de elementos y la paginación de bibliotecas grandes.

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **T-14:** "Add to playlist" en las filas de canción de la biblioteca de YouTube Music. Si esta tarea llega antes que T-11, añade también `youtube_music` a las fuentes que acepta T-14.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
