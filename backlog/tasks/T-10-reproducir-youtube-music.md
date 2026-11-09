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

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
