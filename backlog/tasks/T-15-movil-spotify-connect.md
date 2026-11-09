---
id: T-15
title: Controlar Spotify en el móvil con Spotify Connect
status: draft
discovery: reproductor-musica-privado
change: ~
priority: should
estimate: ~
language: es
---

# T-15 — Controlar Spotify en el móvil con Spotify Connect

## Goal

En el móvil, que la música de Spotify suene en la app oficial de Spotify y la web la controle con Spotify Connect. Es el plan B que aceptó el usuario, porque el Web Playback SDK no está soportado en navegadores móviles y la música debe sonar en el móvil, también con la pantalla bloqueada.

## Context

El discovery marca el móvil como imprescindible y acepta este plan B. El control general de otros dispositivos con Connect queda fuera de alcance; solo se contempla para este caso. Depende de T-05 (scopes de control de dispositivos y cliente compartido de Spotify), de T-06 y de T-23. El aviso de navegador no compatible de T-23 es la puerta de entrada a este plan B. Su alcance final depende del spike en el móvil (pregunta abierta 3).

## Acceptance criteria

```gherkin
Scenario: Reproducir en el móvil a través de la app de Spotify
  Given el reproductor abierto en el navegador del móvil y la app de Spotify abierta en ese móvil
  When el usuario reproduce una canción de Spotify
  Then la canción suena en la app de Spotify del móvil
  And la barra de reproductor de la web muestra la canción y su progreso

Scenario: Controles desde la web
  Given una canción de Spotify sonando en la app del móvil a través de Connect
  When el usuario usa pausa, siguiente, volumen o seek en la web
  Then la app de Spotify del móvil responde a cada acción

Scenario: Sigue sonando con la pantalla bloqueada
  Given una canción de Spotify sonando en la app del móvil a través de Connect
  When el usuario bloquea la pantalla
  Then la música sigue sonando

Scenario: No hay ningún dispositivo de Spotify disponible
  Given el reproductor abierto en el navegador del móvil y la app de Spotify cerrada
  When el usuario intenta reproducir una canción de Spotify
  Then ve el aviso de que abra la app de Spotify en el móvil para reproducir
  And no suena nada
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- **Depende de la pregunta abierta 3:** qué sistema y navegador usa el móvil real y qué funciona en él.
- Falta decidir cómo detecta la web que está en un móvil donde el SDK no funciona.
- El control de Connect usa la Web API de Spotify, que tiene límites de peticiones. Se usa el cliente compartido de T-05, con su pausa común tras un 429 y sus errores tipados.
- **Cambio en la documentación de Spotify (detectado el 2026-11-09):** la página del Web Playback SDK dice ahora que funciona en navegadores móviles Android e iOS, con limitaciones en iOS. Por eso la prueba de la pregunta abierta 3 debe probar primero el SDK en el móvil real: si funciona, este plan B puede no hacer falta. Los planes Premium solo para móvil (Mini/Lite) no funcionan con el SDK. Fuente: https://developer.spotify.com/documentation/web-playback-sdk
- Scopes que hacen falta: `user-read-playback-state`, `user-modify-playback-state` y `user-read-currently-playing` (ya están en la lista de T-05).

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
