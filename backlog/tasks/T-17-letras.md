---
id: T-17
title: Letra de la canción actual
status: draft
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: ~
language: es
---

# T-17 — Letra de la canción actual

## Goal

Mostrar la letra de la canción que suena, para seguirla sin salir del reproductor.

## Context

Extra deseable del discovery, para después de la v1. Depende de T-06 y T-10. El discovery no dice de dónde sale la letra.

## Acceptance criteria

```gherkin
Scenario: Ver la letra de la canción que suena
  Given una canción con letra disponible sonando
  When el usuario abre el panel de letra
  Then ve la letra de esa canción

Scenario: La letra cambia con la canción
  Given el panel de letra abierto
  When empieza a sonar otra canción
  Then el panel muestra la letra de la nueva canción

Scenario: Letra no disponible
  Given una canción sin letra disponible sonando
  When el usuario abre el panel de letra
  Then ve el mensaje "Letra no disponible"
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Falta decidir la fuente de las letras (API, servicio, librería no oficial) y sus condiciones de uso.
- Falta decidir si la letra va sincronizada con el tiempo de la canción.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
