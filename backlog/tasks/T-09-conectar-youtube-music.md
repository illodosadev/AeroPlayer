---
id: T-09
title: Conectar la cuenta de YouTube Music
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-09 — Conectar la cuenta de YouTube Music

## Goal

Conectar la cuenta de YouTube Music Premium del usuario con un método no oficial y guardar sus credenciales de forma segura, para que la app pueda buscar, leer la biblioteca y reproducir de YouTube Music.

## Context

YouTube Music no tiene API oficial. El usuario acepta librerías no oficiales (por ejemplo, autenticación con cookies) sabiendo que pueden romperse y que van contra los ToS. Las credenciales son personales y deben guardarse de forma segura. Depende de T-02. T-10, T-11 y T-12 dependen de esta.

## Acceptance criteria

```gherkin
Scenario: Conectar YouTube Music
  Given el usuario con sesión iniciada y YouTube Music sin conectar
  When completa el proceso de conexión con credenciales válidas
  Then ve "YouTube Music conectado" junto al nombre de su cuenta

Scenario: Las credenciales se guardan cifradas
  Given YouTube Music conectado
  When se lee directamente el registro de credenciales en la base de datos
  Then las credenciales no aparecen en texto plano

Scenario: Credenciales no válidas al conectar
  Given el usuario con sesión iniciada y YouTube Music sin conectar
  When completa el proceso de conexión con credenciales no válidas
  Then ve un mensaje de que no se pudo conectar
  And YouTube Music sigue sin conectar

Scenario: Las credenciales caducan
  Given YouTube Music conectado y las credenciales ya no son válidas
  When la app hace una petición a YouTube Music
  Then se muestra un aviso para volver a conectar YouTube Music
  And la app no muestra una página de error

Scenario: Desconectar YouTube Music
  Given YouTube Music conectado
  When el usuario pulsa "Desconectar YouTube Music"
  Then las credenciales se borran de la base de datos
  And se muestra la opción de conectar de nuevo
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- **Bloqueada por las preguntas abiertas 1 y 2:** el método de autenticación (cookies, OAuth del cliente TV, otro) y la librería dependen del spike de YT Music. Si la librería es Python, también depende de que Hostinger permita ejecutarla.
- Como la integración no es oficial, conviene aislarla para poder cambiarla si se rompe.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
