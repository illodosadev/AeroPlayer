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

Necesidad v1 del discovery. Es la mitad de YouTube de la biblioteca; la de Spotify es T-08. Depende de T-27 (el spike de YouTube Music, que decide la ruta técnica), de T-09 y de T-10.

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

- **Pregunta abierta 2, resuelta por T-27 (2026-11-15):**
  - **Token (T-09, cerrada el 2026-11-15):** los handlers inyectan `App\YouTubeMusic\YouTubeMusicCredentials` y llaman a `accessToken()`, que devuelve un token válido al menos 60 s y lo renueva si hace falta. Falla con uno de cuatro errores, que heredan de `YouTubeMusicCredentialsFailed`: `YouTubeMusicNotConnected`, `YouTubeMusicReconnectRequired`, `YouTubeMusicUnavailable` y `YouTubeMusicMisconfigured`. Esta tarea decide qué mensaje muestra cada uno. Un 401 de la Data API con un token que parece válido no lo trata el servicio: lo decide esta tarea (spec `youtube-music-credentials`);
  - **Ruta: la API oficial** (YouTube Data API v3) con el token de T-09 y el scope `youtube.readonly`, no la API interna. La interna solo lee la biblioteca con el scope completo, que incluye escritura, y el usuario lo descarta por D6;
  - **Playlists:** `playlists?part=snippet,contentDetails&mine=true`, las playlists propias;
  - **"Me gusta":** `playlistItems?playlistId=LM`, el "Liked Music" de YouTube Music;
  - **Canciones de una playlist:** `playlistItems?playlistId=<id>`, paginado con `pageToken`, hasta 50 por página;
  - **Límite:** la API oficial no lista las playlists de otros usuarios guardadas en la biblioteca. Los escenarios deben decir "sus playlists" (las propias) y "Liked Music";
  - **Cuota:** 10 000 unidades al día y 1 por página de listado. Conviene caché, como la de T-08;
  - **Errores:** la integración es oficial, así que el escenario "La integración no oficial falla" pasa a ser "YouTube Music no responde" (5xx, timeout o cuota agotada, 403 `quotaExceeded`).
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

**Seguimiento de spotify-library-overview (T-08, 2026-11-14)**

- **Dónde va:** YouTube Music se añade dentro de `GetLibraryHandler` (`app/Application/Library/Queries`) y en la misma lista de `resources/js/pages/Library.vue`, con su propio `source` y la etiqueta del servicio en el mapa `SOURCE_LABELS`.

**Seguimiento de spotify-library-tracks (T-08, 2026-11-14)**

- **Rutas:** las páginas de playlist llevan el servicio en la ruta (`/library/playlists/spotify/{id}`); YouTube Music añade `/library/playlists/youtube-music/{id}` con su propio controlador o el mismo con otro lector.
- **Reutilizar:** `SongRow.vue`, `usePagedSongs` y `LibraryCursor`, con un scope propio.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
