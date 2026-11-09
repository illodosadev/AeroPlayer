---
id: T-18
title: Skins y temas intercambiables
status: draft
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: ~
language: es
---

# T-18 — Skins y temas intercambiables

## Goal

Elegir entre varios temas visuales dentro de la estética Aero (por ejemplo, variantes de color), como en los reproductores con skins de los 2000.

## Context

Extra deseable del discovery, para después de la v1. Se construye sobre los componentes de T-03.

## Acceptance criteria

```gherkin
Scenario: Cambiar de tema
  Given el usuario con sesión iniciada y el tema por defecto activo
  When elige otro tema en los ajustes
  Then la interfaz cambia a los colores de ese tema sin recargar la página

Scenario: El tema elegido se recuerda
  Given el usuario ha elegido un tema distinto al de por defecto
  When cierra sesión y vuelve a entrar desde otro navegador
  Then la interfaz usa el tema que eligió

Scenario: Tema guardado que ya no existe
  Given el tema guardado del usuario ya no existe en la app
  When el usuario abre la app
  Then se usa el tema por defecto
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Falta decidir qué temas habrá y cuál es el tema por defecto.
- **Cómo se hacen los temas:** redefiniendo los tokens CSS `--aero-*` de T-03, sin tocar los componentes.
- **Variantes oscuras:** la v1 es solo clara (decisión del 2026-11-09, T-03). Si algún día hay variantes oscuras, son un tema más de esta tarea.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
