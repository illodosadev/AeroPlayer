---
id: T-19
title: Historial de escucha y estadísticas
status: draft
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: ~
language: es
---

# T-19 — Historial de escucha y estadísticas

## Goal

Guardar un historial propio de lo que se escucha en los dos servicios y mostrar estadísticas, para tener en un solo sitio lo que ninguno de los dos servicios ofrece combinado.

## Context

Extra deseable del discovery, para después de la v1. Depende de T-06 y T-10.

## Acceptance criteria

```gherkin
Scenario: Una canción escuchada aparece en el historial
  Given el usuario escucha una canción hasta que cuenta como escuchada
  When abre el historial
  Then la canción aparece la primera, con fecha, hora y servicio de origen

Scenario: Una canción saltada enseguida no cuenta
  Given el usuario salta una canción antes de que cuente como escuchada
  When abre el historial
  Then esa reproducción no aparece

Scenario: Estadísticas
  Given un historial con escuchas de varios artistas
  When el usuario abre las estadísticas
  Then ve las canciones y los artistas más escuchados, ordenados por número de escuchas

Scenario: Historial vacío
  Given el usuario no ha escuchado nada todavía
  When abre el historial
  Then ve un mensaje de que aún no hay escuchas
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- Falta definir el umbral para que una canción cuente como escuchada (segundos o porcentaje).
- Falta definir los periodos de las estadísticas (semana, mes, siempre) y si se puede borrar el historial.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
