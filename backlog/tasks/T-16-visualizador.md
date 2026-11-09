---
id: T-16
title: Visualizador estilo WMP / Winamp
status: draft
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: ~
language: es
---

# T-16 — Visualizador estilo WMP / Winamp

## Goal

Mostrar un visualizador animado al estilo de Windows Media Player / Winamp mientras suena la música, como refuerzo de la estética años 2000.

## Context

Extra deseable del discovery, para después de la v1. Depende de T-03 (estética), T-06 y T-10 (reproducción). Restricción técnica a confirmar en `/task-enrich`: ni el Web Playback SDK de Spotify ni el reproductor embebido de YouTube dan acceso a la señal de audio, así que probablemente el visualizador no podrá reaccionar al audio real.

## Acceptance criteria

```gherkin
Scenario: Activar el visualizador mientras suena música
  Given una canción sonando
  When el usuario activa el visualizador
  Then se muestra una animación de visualización

Scenario: El visualizador sigue el estado de reproducción
  Given el visualizador activo y una canción sonando
  When el usuario pausa la canción
  Then la animación se detiene
  When reanuda la canción
  Then la animación continúa

Scenario: Visualizador sin música
  Given ninguna canción sonando
  When el usuario activa el visualizador
  Then se muestra el visualizador en estado de reposo, sin animación de audio
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- La preferencia "reducir movimiento" del sistema **se respeta**: T-03 fija una regla global de movimiento reducido y el visualizador la sigue. Falta medir el impacto en la batería del móvil.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
