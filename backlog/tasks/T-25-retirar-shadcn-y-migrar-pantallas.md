---
id: T-25
title: Retirar shadcn-vue y pasar a Aero las pantallas que quedan
status: draft
discovery: reproductor-musica-privado
change: ~
priority: should
estimate: ~
language: es
---

# T-25 — Retirar shadcn-vue y pasar a Aero las pantallas que quedan

## Goal

Pasar al estilo Aero las pantallas del starter kit que ninguna otra tarea toca, como la página de inicio "/" (`Welcome`) y el dashboard, y quitar del proyecto los componentes shadcn-vue que ya no se usen. Así se cierra la retirada gradual de shadcn-vue que se decidió en T-03 y toda la app transmite la estética Frutiger Aero / años 2000, que es una métrica de éxito del discovery.

## Context

Creada por el orquestador del lote `/task-enrich-batch` del 2026-11-09. Los revisores de T-03 detectaron que este trabajo no tenía dueño.

- **Decisión del 2026-11-09 (T-03):** los componentes shadcn-vue no se reestilan, se retiran poco a poco. Cada tarea quita los de las pantallas que toca.
- **"/":** T-02 deja el `Welcome` del starter kit para los visitantes, sin enlace de registro, y el logout lleva ahí. T-03 no lo reestila.
- **Depende de** T-03 y T-21, que aportan los componentes Aero. Conviene hacerla cuando ya existan las pantallas principales del reproductor.

## Acceptance criteria

```gherkin
Scenario: La página de inicio tiene estilo Aero
  Given un visitante sin sesión
  When abre "/"
  Then la página usa el fondo y los componentes Aero
  And no contiene elementos con el atributo "data-slot"

Scenario: No queda código de shadcn-vue sin usar
  Given el repositorio al terminar la tarea
  When se buscan importaciones de "@/components/ui" en "resources/js"
  Then no hay ninguna, o solo las de componentes que todavía use alguna pantalla, apuntadas en la tarea
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Pendiente**

- Qué pantallas quedan cuando se haga esta tarea. Depende de lo que hagan antes las tareas de interfaz.
- Si "/" sigue siendo una página para visitantes o pasa a redirigir a `/login`.
- Qué pasa con el dashboard cuando exista la pantalla principal del reproductor. Ninguna tarea del backlog la construye todavía.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
