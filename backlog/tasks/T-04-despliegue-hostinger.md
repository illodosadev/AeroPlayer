---
id: T-04
title: Despliegue en Hostinger
status: draft
discovery: reproductor-musica-privado
change: ~
priority: must
estimate: ~
language: es
---

# T-04 — Despliegue en Hostinger

## Goal

Publicar la app en el hosting de Hostinger, en el subdominio `music.<dominio>`, para poder usarla desde fuera de casa y desde el móvil sin afectar a los demás proyectos de la cuenta.

## Context

Necesidades no funcionales del discovery: acceso por Internet desde escritorio y móvil, y aislamiento de otros proyectos alojados en la misma cuenta de Hostinger. Depende de T-01 y T-02: no se publica nada sin login. Spotify exige HTTPS en las URLs de redirección OAuth que no son localhost, y eso afecta a T-05.

## Acceptance criteria

```gherkin
Scenario: La app responde en su subdominio con HTTPS
  Given la app desplegada
  When se abre "https://music.<dominio>"
  Then se muestra la página de login
  And el certificado TLS es válido

Scenario: HTTP redirige a HTTPS
  Given la app desplegada
  When se abre "http://music.<dominio>"
  Then se redirige a "https://music.<dominio>"

Scenario: Los demás proyectos de la cuenta siguen funcionando
  Given otros sitios alojados en la misma cuenta de Hostinger
  When se despliega la app
  Then esos sitios siguen respondiendo igual que antes del despliegue

Scenario: Los errores no exponen detalles en producción
  Given la app desplegada con el modo debug desactivado
  When se produce un error interno
  Then se muestra una página de error genérica
  And la respuesta no contiene trazas de error ni valores de configuración
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

- **Bloqueada en parte por la pregunta abierta 1:** sin saber si el plan es compartido, Cloud o VPS no se puede decidir cómo desplegar (Git, SSH, colas, cron).
- Falta decidir el mecanismo de despliegue y si es manual o automático.
- Esta tarea revisa la versión de PHP fijada en T-01 (PHP 8.4, provisional) contra la que ofrezca el plan real.
- Esta tarea decide dónde se hace el build de Vite (en local o en CI) y cómo se suben los assets compilados, porque en hosting compartido no suele haber Node.
- El escenario "Los errores no exponen detalles en producción" se refiere al entorno desplegado. T-01 ya lo cubre con tests y T-20 en el stack local.
- **Notas del enriquecimiento de T-02, T-05 y T-06 (2026-11-09):**
  - **Cuenta del propietario:** el despliegue incluye ejecutar una vez en el servidor `php artisan app:create-owner` (T-02). El comando es solo interactivo, así que hace falta una terminal en Hostinger, por SSH o similar (pregunta abierta 1).
  - **HTTPS obligatorio**, por dos motivos. Spotify solo acepta URIs de redirección HTTPS, salvo las de loopback (`127.0.0.1`). Y el DRM del Web Playback SDK (EME) no funciona sobre HTTP.
  - **Spotify:**
    - Registrar `https://music.<dominio>/spotify/callback` en la misma app de Spotify que la URI local `http://127.0.0.1:8081/spotify/callback`. En development mode solo hay un Client ID.
    - Configurar en producción `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REDIRECT_URI` y `SPOTIFY_OWNER_ID` (solo se acepta la cuenta del propietario, T-05).
  - **Caché:** el driver de caché debe compartirse entre procesos (`database` o `redis`, no `array` ni `file` por proceso), porque el bloqueo de renovación de tokens y la pausa común tras un 429 de T-05 dependen de él.
  - **Correo:** no hace falta, porque T-02 quita todas las funciones que envían email.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
