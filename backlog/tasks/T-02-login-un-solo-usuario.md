---
id: T-02
title: Login de un solo usuario
status: proposed
discovery: reproductor-musica-privado
change: single-owner-login
priority: must
estimate: 5
language: es
---

# T-02 — Login de un solo usuario

## Goal

Proteger todo el reproductor con un login propio de email y contraseña, del que solo el propietario tiene cuenta. La cuenta se crea con un comando artisan en la consola, y de la auth del starter kit solo quedan el login y el logout: sin registro, sin recuperación de contraseña por email, sin verificación de email, sin edición de perfil ni cambio de contraseña en ajustes, y sin 2FA. Con la página de apariencia, que también se quita (la v1 es solo en modo claro), no queda ninguna página de ajustes. Hace falta porque la app estará en Internet (`music.<dominio>`) y guardará credenciales de Spotify y YouTube Music. Además, cada función de auth que se quita es superficie de ataque y mantenimiento menos, y la app deja de necesitar correo saliente.

## Context

En el discovery se fijó: un único usuario, login propio, sin registro, y multiusuario fuera de alcance. Depende de T-01, que instala el starter kit oficial de Laravel con Vue sin tocar su auth, así que entre T-01 y T-02 existen `/register`, la recuperación de contraseña, la verificación de email, los ajustes de perfil y contraseña y la 2FA. T-03, T-04 y todas las tareas con pantallas (T-05 en adelante) dependen de esta: todas sus páginas requieren sesión.

Según la documentación del starter kit, la auth la da **Laravel Fortify**: las rutas de login, registro, recuperación, verificación y 2FA se activan o desactivan con el array `features` de `config/fortify.php`, y al quitar una feature hay que quitar también las referencias a sus rutas en el frontend, porque Wayfinder genera las rutas al compilar y el build falla si una página usa una ruta que ya no existe ([Starter Kits, Laravel 12.x](https://laravel.com/docs/12.x/starter-kits#enabling-and-disabling-features)). Las páginas de ajustes (perfil, contraseña, 2FA, apariencia) son del propio starter kit, no de Fortify.

Las decisiones tomadas con el usuario el 2026-11-09 están en las notas, en "Decisiones".

Las páginas son de Inertia sin SSR: el HTML de la respuesta no contiene el texto de la página. Por eso los escenarios comprueban el componente y las props de Inertia, y los pocos que necesitan ver la página renderizada están marcados como **[test de navegador]**.

## Acceptance criteria

```gherkin
# --- Login ----------------------------------------------------------------------

Scenario: Login con credenciales correctas
  Given existe el usuario propietario "owner@example.com" con la contraseña "Correct-Horse-42"
  And el visitante no tiene sesión
  When envía "owner@example.com" y "Correct-Horse-42" en "/login"
  Then queda autenticado como "owner@example.com"
  And se le redirige a "/dashboard"
  And su ID de sesión es distinto del que tenía antes del login

Scenario: El email no distingue mayúsculas en el login
  Given existe el usuario propietario "owner@example.com" con la contraseña "Correct-Horse-42"
  When envía "Owner@Example.com" y "Correct-Horse-42" en "/login"
  Then queda autenticado y se le redirige a "/dashboard"

Scenario Outline: Login fallido
  Given existe el usuario propietario "owner@example.com" con la contraseña "Correct-Horse-42"
  When envía <credenciales> en "/login"
  Then no queda autenticado
  And se le redirige a "/login"
  And la sesión tiene el error "These credentials do not match our records." en el campo "email"

  Examples:
    | credenciales                                   |
    | "owner@example.com" con la contraseña "wrong"  |
    | "nobody@example.com" con "Correct-Horse-42"    |

Scenario Outline: Login con el formulario incompleto
  Given un visitante sin sesión
  When envía en "/login" el formulario con <campo> vacío y el otro campo relleno
  Then no queda autenticado
  And la sesión tiene el error "<mensaje>" en el campo "<clave>"

  Examples:
    | campo      | clave    | mensaje                            |
    | email      | email    | The email field is required.       |
    | contraseña | password | The password field is required.    |

Scenario: Login cuando todavía no existe ningún usuario
  Given la tabla "users" está vacía
  When se envía "owner@example.com" y "Correct-Horse-42" en "/login"
  Then no queda autenticado
  And la sesión tiene el error "These credentials do not match our records." en el campo "email"
  And la respuesta no tiene estado 500

Scenario: Demasiados intentos de login fallidos
  Given 5 intentos de login fallidos para "owner@example.com" desde la misma IP en el último minuto
  When se hace un sexto intento en ese mismo minuto, aunque sea con la contraseña correcta
  Then la respuesta tiene estado 429
  And el visitante no queda autenticado

Scenario: El bloqueo por intentos se levanta al pasar el minuto
  Given el login de "owner@example.com" bloqueado tras 5 intentos fallidos
  When pasan 61 segundos y se envían las credenciales correctas
  Then queda autenticado y se le redirige a "/dashboard"

Scenario Outline: Recordarme
  Given el usuario propietario sin sesión
  When inicia sesión en "/login" con las credenciales correctas y "remember" <valor>
  Then la respuesta <cookie> una cookie "remember_web_*"

  Examples:
    | valor         | cookie          |
    | igual a true  | incluye         |
    | sin enviar    | no incluye      |

Scenario: La cookie de "Remember me" mantiene la sesión cuando la sesión caduca
  Given el usuario inició sesión con "remember" igual a true
  And su sesión ha caducado, pero conserva la cookie "remember_web_*"
  When abre "/dashboard"
  Then la respuesta tiene estado 200 y sigue autenticado

Scenario: Con sesión iniciada no se muestra el formulario de login
  Given el usuario propietario con sesión iniciada
  When abre "/login"
  Then se le redirige a "/dashboard"

# --- Acceso y páginas ------------------------------------------------------------

Scenario: Las páginas protegidas exigen sesión
  Given un visitante sin sesión
  When abre "/dashboard"
  Then se le redirige a "/login"

Scenario: La página de inicio no ofrece registro
  Given un visitante sin sesión
  When abre "/"
  Then la respuesta Inertia indica el componente "Welcome"
  And la prop "canRegister" es false

Scenario: La página de login no ofrece registro ni recuperación de contraseña
  Given un visitante sin sesión
  When abre "/login"
  Then la respuesta Inertia indica el componente de la página de login
  And las props "canRegister" y "canResetPassword" son false

Scenario: Ningún enlace del menú de usuario lleva a un 404 [test de navegador]
  Given el usuario propietario con sesión iniciada en "/dashboard"
  When abre el menú de usuario y sigue cada uno de sus enlaces, salvo la acción "Log out"
  Then ninguna de esas páginas responde con estado 404
  And el menú no muestra el texto "Settings"
  And ninguno de sus enlaces apunta a "/settings" ni a una URL que empiece por "/settings/"

Scenario: Las páginas de login e inicio no muestran registro ni recuperación [test de navegador]
  Given un visitante sin sesión
  When abre "/" y después "/login"
  Then ninguna de las dos páginas muestra los textos "Register", "Sign up" ni "Forgot password?"

# --- Logout ---------------------------------------------------------------------

Scenario: Cerrar sesión
  Given el usuario propietario con sesión iniciada
  When envía un POST a "/logout"
  Then la sesión termina y se le redirige a "/"
  And al abrir "/dashboard" después se le redirige a "/login"

Scenario Outline: El logout exige POST con token CSRF
  Given el usuario propietario con sesión iniciada y la protección CSRF activa
  When envía <petición> a "/logout"
  Then la respuesta tiene estado <estado>
  And sigue autenticado

  Examples:
    | petición                         | estado |
    | una petición GET                 | 405    |
    | un POST sin token CSRF válido    | 419    |

Scenario: Sesiones en dos dispositivos a la vez
  Given el driver de sesión "database"
  And el usuario propietario con sesión iniciada en el dispositivo A y en el dispositivo B, sin "Remember me"
  When cierra sesión en el dispositivo A
  Then en el dispositivo B "/dashboard" sigue respondiendo con estado 200

# --- Funciones de auth eliminadas -----------------------------------------------

Scenario Outline: Las rutas de auth eliminadas devuelven 404
  Given <visitante>
  When envía <método> a "<ruta>"
  Then la respuesta tiene estado 404

  Examples:
    | visitante                         | método | ruta                                 |
    | un visitante sin sesión           | GET    | /register                            |
    | un visitante sin sesión           | POST   | /register                            |
    | un visitante sin sesión           | GET    | /forgot-password                     |
    | un visitante sin sesión           | POST   | /forgot-password                     |
    | un visitante sin sesión           | GET    | /reset-password/any-token            |
    | un visitante sin sesión           | POST   | /reset-password                      |
    | el usuario con sesión iniciada    | GET    | /email/verify                        |
    | el usuario con sesión iniciada    | POST   | /email/verification-notification     |
    | el usuario con sesión iniciada    | GET    | /settings/profile                    |
    | el usuario con sesión iniciada    | PATCH  | /settings/profile                    |
    | el usuario con sesión iniciada    | DELETE | /settings/profile                    |
    | el usuario con sesión iniciada    | GET    | /settings/password                   |
    | el usuario con sesión iniciada    | PUT    | /settings/password                   |
    | el usuario con sesión iniciada    | GET    | /settings                            |
    | el usuario con sesión iniciada    | GET    | /settings/appearance                 |

Scenario: No queda ninguna ruta de 2FA
  Given la aplicación tras quitar las funciones de auth
  When se recorre la lista de rutas registradas
  Then ninguna URI empieza por "two-factor", "user/two-factor" ni "settings/two-factor"
  And ningún nombre de ruta empieza por "two-factor"

Scenario: Un intento de registro no crea usuarios
  Given la tabla "users" está vacía
  When un visitante envía POST a "/register" con nombre, email y contraseña válidos
  Then la respuesta tiene estado 404
  And la tabla "users" sigue vacía

Scenario: El frontend sigue compilando tras quitar las funciones
  Given el proyecto tras quitar las funciones de auth y sus referencias en Vue
  When se ejecutan el build de producción del frontend y la comprobación de tipos
  Then ambos comandos siguen terminando con código 0

# --- Comando para crear el usuario ----------------------------------------------

Scenario: Crear el usuario propietario
  Given la tabla "users" está vacía
  When se ejecuta "php artisan app:create-owner" en modo interactivo y se responde "owner@example.com", "Correct-Horse-42" y la confirmación "Correct-Horse-42"
  Then el comando termina con código 0 y muestra "Owner account created for owner@example.com."
  And la tabla "users" tiene exactamente 1 fila, con el email "owner@example.com"
  And la contraseña guardada no es "Correct-Horse-42" en texto plano
  And se puede iniciar sesión en "/login" con esas credenciales

Scenario: El email se guarda en minúsculas
  Given la tabla "users" está vacía
  When se ejecuta el comando en modo interactivo y se responde "Owner@Example.com" y "Correct-Horse-42" confirmada
  Then la tabla "users" contiene el email "owner@example.com"

Scenario Outline: Un dato no válido se vuelve a preguntar
  Given la tabla "users" está vacía
  When se ejecuta el comando en modo interactivo y se responde <respuesta no válida>
  Then muestra el error "<mensaje>" y vuelve a preguntar ese mismo dato
  When se responde con un valor válido y se completa el resto
  Then el comando termina con código 0
  And la tabla "users" tiene exactamente 1 fila

  Examples:
    | respuesta no válida                                                    | mensaje                                                                          |
    | el email "not-an-email"                                                | The email field must be a valid email address.                                   |
    | un email vacío                                                         | The email field is required.                                                     |
    | la contraseña "Short-7a" (8 caracteres)                                | The password field must be at least 12 characters.                               |
    | la contraseña "correct-horse-42" (sin mayúsculas)                      | The password field must contain at least one uppercase and one lowercase letter. |
    | la contraseña "Correcthorse42" (sin símbolos)                          | The password field must contain at least one symbol.                             |
    | la contraseña "Correct-Horse-x" (sin números)                          | The password field must contain at least one number.                             |
    | la confirmación "Other-Horse-99" para la contraseña "Correct-Horse-42" | The password field confirmation does not match.                                  |

Scenario Outline: Sin terminal interactiva el comando no hace nada
  Given <estado de la tabla>
  When se ejecuta "php artisan app:create-owner --no-interaction"
  Then el comando termina con un código distinto de 0
  And muestra "This command needs an interactive terminal."
  And la tabla "users" no cambia

  Examples:
    | estado de la tabla                                      |
    | la tabla "users" está vacía                             |
    | existe el usuario propietario "owner@example.com"       |

Scenario: Ya existe un usuario y se cambia su contraseña
  Given el driver de sesión "database"
  And existe el usuario propietario "owner@example.com" con la contraseña "Correct-Horse-42"
  And tiene una sesión iniciada en otro dispositivo, con la cookie "remember_web_*"
  When se ejecuta el comando en modo interactivo, se confirma "yes" a la pregunta "An owner account already exists (owner@example.com). Replace its email and password?" y se responde "owner@example.com" y "New-Horse-77" confirmada
  Then el comando termina con código 0 y muestra "Owner account updated for owner@example.com."
  And la tabla "users" sigue teniendo exactamente 1 fila
  And el login con "Correct-Horse-42" falla y el login con "New-Horse-77" funciona
  And en el otro dispositivo "/dashboard" redirige a "/login", también con la cookie "remember_web_*"

Scenario: Ya existe un usuario y no se confirma el cambio
  Given existe el usuario propietario "owner@example.com" con la contraseña "Correct-Horse-42"
  When se ejecuta el comando en modo interactivo y se responde "no" a la pregunta de reemplazo
  Then el comando termina con un código distinto de 0 y muestra "Nothing changed."
  And el login con "Correct-Horse-42" sigue funcionando
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **Alta del usuario:** con un comando artisan que pide el email y la contraseña por consola. Se ejecuta una vez en local y otra en el servidor. Se descarta el seeder que leía las credenciales del `.env`.
- **Auth reducida a login y logout:** se quitan el registro, la verificación de email, la recuperación de contraseña ("forgot password"), el cambio de contraseña en ajustes, la edición de perfil y la 2FA. Las rutas quitadas devuelven 404.
- **Consecuencia: no hace falta correo saliente.** Ninguna función que queda envía emails, así que no se configura buzón (ni Mailpit) ni proveedor de correo.
- **Interfaz en inglés** (decidido en T-01): los textos de los escenarios son los del starter kit y los de `lang/en`.
- **Login por email y contraseña.** El discovery decía "usuario y contraseña"; el identificador es el email, que es lo que trae Fortify y lo que pide el comando.
- **Sin ajustes de apariencia:** la v1 es solo en modo claro (decidido para T-03), así que también se quita la página "Appearance" del starter kit. Con ella desaparecen todas las páginas de ajustes: `/settings` y `/settings/appearance` devuelven 404 y el menú de usuario no tiene enlace "Settings".

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Cookie de sesión con `SameSite=Lax`** (lo que trae Laravel), nunca `Strict`. Así la sesión sobrevive a la vuelta de OAuth desde Spotify (T-05). Añadido al aplicar los cambios del lote el 2026-11-09.

- **Email sin distinguir mayúsculas:** se guarda en minúsculas y el login no distingue mayúsculas.
- **Nombre del comando: `app:create-owner`.** Pide el email, la contraseña y su confirmación, estas dos con entrada oculta. La columna `name` de `users` es obligatoria: el comando la rellena con la parte local del email (`owner`), sin preguntar. Marca `email_verified_at` con la fecha actual, para que el middleware `verified` no bloquee nada si alguna ruta lo conserva.
- **El comando es solo interactivo.** En modo interactivo, si un dato no es válido muestra el error y vuelve a preguntarlo hasta que lo sea (no termina con error). Sin terminal interactiva (`--no-interaction`, o stdin que no es una terminal) no pregunta nada, termina con un código distinto de 0 y no toca la base de datos. No tiene opciones `--email` ni `--password`: una contraseña pasada como argumento queda en el historial de la shell y en la lista de procesos del servidor, y no hace falta automatizar un alta que se hace dos veces.
- **Reglas de contraseña del comando, fijas en todos los entornos:** al menos 12 caracteres, mayúsculas y minúsculas, al menos un número y al menos un símbolo (`Password::min(12)->mixedCase()->numbers()->symbols()`), sin `uncompromised()`. Tras esta tarea el comando es el único sitio de la app que fija contraseñas, así que no hace falta compartir las reglas con ningún formulario. Al ser fijas, los ejemplos valen igual en tests, local y producción. Se descarta `uncompromised()` porque consulta por HTTP la API de Have I Been Pwned, y no está confirmado que el servidor de Hostinger tenga salida a Internet (pregunta abierta 1). Los mensajes salen de `lang/en/validation.php`.
- **Si ya existe un usuario, el comando no crea otro.** Pregunta si se reemplazan el email y la contraseña del existente. Con "no" no cambia nada y termina con error. Con "yes" actualiza el usuario. Esto sustituye a la recuperación de contraseña por email: si se olvida la contraseña, se ejecuta el comando en el servidor.
- **Al cambiar la contraseña con el comando se cierran las demás sesiones:** se regenera el `remember_token` y se borran las filas del usuario en la tabla `sessions`. Borrar esas filas solo funciona con el driver de sesión `database`, que es el de producción desde Laravel 11 (ver T-01). Con otro driver solo se invalida la cookie "Remember me" y las sesiones abiertas duran hasta que caducan. Si la contraseña se cambia porque pudo filtrarse, las sesiones abiertas con ella no deben seguir valiendo.
- **Límite de intentos:** el de Fortify en el starter kit, 5 por minuto por email + IP ([Starter Kits, Rate Limiting](https://laravel.com/docs/12.x/starter-kits#rate-limiting)). En una app de un solo usuario expuesta a Internet es suficiente; si aparecen ataques, se baja o se añade un límite global por IP.
- **"Remember me":** se mantiene la casilla del starter kit, sin marcar por defecto. Es la forma de tener sesiones largas en el móvil sin alargar la sesión normal. La sesión normal sigue con la duración por defecto de Laravel (`SESSION_LIFETIME=120` minutos de inactividad).
- **Página tras el login: `/dashboard`** del starter kit, por ahora (ver Pendiente).
- **Qué se quita en concreto:** las features `registration`, `resetPasswords`, `emailVerification` y `twoFactorAuthentication` de `config/fortify.php`; todas las páginas de ajustes: perfil (incluido "borrar cuenta", que está en esa página y dejaría la app sin su único usuario), contraseña, 2FA y apariencia, con sus rutas (incluida la redirección de `/settings`), controladores, el layout de ajustes y el enlace "Settings" del menú de usuario; las páginas Vue y referencias de Wayfinder a esas rutas; las acciones de Fortify que ya no se usan (`CreateNewUser`, `ResetUserPassword` y, si nada más lo usa, `PasswordValidationRules`); el trait de 2FA del modelo `User` y la migración de las columnas de 2FA (el proyecto aún no está en producción, así que se puede quitar sin migración de vuelta); y los tests del starter kit de esas funciones.
- **Apariencia:** T-02 quita la página, su ruta y su enlace. Si el starter kit trae además código que aplica el tema (por ejemplo, un middleware o un composable de apariencia que lee una cookie), se quita aquí solo si queda sin uso y el build sigue en verde; dejar la app fija en modo claro es trabajo de T-03.
- **Las páginas de ajustes vuelven más adelante:** T-05 añadirá `/settings/spotify` como primera página de ajustes. La tarea que la añada vuelve a poner el enlace "Settings" en el menú de usuario (y `/settings` dejará de dar 404 si redirige a ella).
- **Se dejan como están** la tabla `password_reset_tokens` de la migración por defecto de Laravel: no molesta, y quitarla es divergir del framework sin beneficio.
- **Los mensajes del comando van en inglés**, como el resto de la interfaz.

**Restricciones descubiertas**

- **Inertia sin SSR:** el HTML de la respuesta solo lleva el componente y las props en `data-page`, no el texto renderizado. Los escenarios de "no se muestra X" se comprueban con las props (`canRegister`, `canResetPassword`) en tests de feature, y lo que depende de enlaces escritos en los componentes Vue (menú de usuario, textos visibles) con tests de navegador. Los nombres de las props y del componente de login se comprueban en la versión instalada.
- **Wayfinder:** al quitar una feature de Fortify hay que quitar sus referencias en Vue o el build falla (documentación del starter kit, enlace en Context). Por eso hay un escenario de build y comprobación de tipos.
- **`Password::defaults()` del starter kit:** las versiones recientes pueden definir en `AppServiceProvider` unas reglas por defecto más estrictas en producción (por ejemplo, 12 caracteres, mayúsculas y minúsculas, símbolos y `uncompromised()`, que necesita HTTP saliente) y más laxas en otros entornos. Hay que comprobarlo en la versión instalada. El comando no las usa: fija sus propias reglas (ver Reglas propuestas), así que no le afecta el entorno. Si nada más usa `Password::defaults()` tras quitar los formularios, se puede quitar esa configuración.
- **`/user/confirm-password`:** la documentación de Fortify lista esta ruta sin ligarla a ninguna feature, así que puede seguir registrada tras quitar la 2FA. Requiere sesión y, sin 2FA ni "borrar cuenta", no la usa nadie. En el diseño se comprueba si queda registrada. Por eso no está en la tabla de rutas 404 (ver Pendiente).
- **Limitador de login:** según el código de Fortify (comprobar en el diseño con la versión instalada), cuando `fortify.limiters.login` está configurado el bloqueo lo hace el middleware `throttle`, que responde con 429. Si la versión instalada redirige a `/login` con el error "Too many login attempts. Please try again in N seconds.", se ajusta el escenario de intentos fallidos manteniendo lo esencial: no se autentica.
- **Logout y "Remember me":** al cerrar sesión, Laravel regenera el `remember_token` del usuario. Eso invalida la cookie "Remember me" de los demás dispositivos: el dispositivo B del escenario sigue con sesión mientras dure su sesión normal, pero cuando caduque tendrá que volver a entrar. Por eso ese escenario usa B sin "Remember me". Ver Casos límite.
- **La documentación consultada es la de Laravel 12.x**, y T-01 instala la última versión estable (ya existe Laravel 13). Los nombres de rutas, ficheros, props y textos del starter kit se comprueban con la versión instalada al hacer el diseño.

**Casos límite**

- Tabla `users` vacía (antes de ejecutar el comando): el login falla con el mensaje genérico y nunca da 500 (escenario incluido).
- El comando ejecutado dos veces a la vez: la unicidad del email en la base de datos es la última barrera; el segundo falla sin crear un segundo usuario. No merece escenario.
- Cerrar sesión en el escritorio hace que el móvil tenga que volver a entrar cuando caduque su sesión normal (ver Restricciones). Si molesta en el uso diario, se puede cambiar el logout para que no regenere el `remember_token`, a costa de seguridad.
- Los mensajes de login fallido no dicen si el email existe. Con un solo usuario apenas importa, pero se mantiene el comportamiento del starter kit.

**Pistas para los tests**

- **Props de Inertia:** `assertInertia(fn ($page) => $page->component(...)->where('canRegister', false))` en los escenarios de inicio y login.
- **Tests de navegador:** los escenarios marcados **[test de navegador]** se hacen con el plugin de navegador de Pest. Si se decide no añadir ese plugin en esta tarea, esos dos escenarios se comprueban a mano y se anotan en el diseño; el resto son tests de feature.
- **Comando interactivo:** `$this->artisan('app:create-owner')->expectsQuestion(...)->expectsConfirmation(...)->expectsOutputToContain(...)->assertExitCode(...)`. Para el reintento, se espera la misma pregunta dos veces: la primera con el valor no válido y la segunda con uno válido. **El comando valida él mismo** con `Validator` y las reglas y mensajes de `lang/en`, muestra el error y vuelve a preguntar en su propio bucle. No usa `validate:` ni `required:` de Laravel Prompts. Hay dos motivos. Primero, en tests el modo alternativo de Prompts puede lanzar `PromptValidationException` en vez de volver a preguntar, y eso hay que comprobarlo con la versión instalada. Segundo, el `required` de Prompts muestra "Required." y no el mensaje de `lang/en` que esperan los escenarios ("The email field is required.").
- **Login fallido:** la validación fallida redirige "atrás", así que los tests envían el referer: `$this->from('/login')->post('/login', [...])`. Sin él, la redirección no va a "/login".
- **Sin interacción:** `$this->artisan('app:create-owner', ['--no-interaction' => true])` y comprobar el código de salida y que `users` no cambia.
- **Driver de sesión:** los tests usan el driver `array` (T-01). Los escenarios "Sesiones en dos dispositivos a la vez" y "Ya existe un usuario y se cambia su contraseña" tienen que forzar `SESSION_DRIVER=database` (`config(['session.driver' => 'database'])`) en ese test, con la tabla `sessions` migrada, para que haya filas que borrar y sesiones independientes por dispositivo.
- **CSRF:** Laravel desactiva el CSRF en los tests, así que el ejemplo 419 del logout tiene que reactivarlo en ese test.
- **Bloqueo:** 5 POST fallidos seguidos a `/login` con el mismo email; para que pase el minuto, `$this->travel(61)->seconds()`.
- **"Remember me" con la sesión caducada:** se puede vaciar la sesión y repetir la petición solo con la cookie; el diseño elige.
- **Rutas:** la tabla de rutas 404 se escribe como un dataset de Pest. La comprobación de 2FA recorre `Route::getRoutes()` y mira `uri()` y `getName()`.
- Los tests del starter kit de registro, recuperación, verificación, perfil, contraseña y 2FA se borran, no se dejan en skip.

**Fuera de alcance**

- El estilo Aero de la página de login (T-03).
- Ejecutar el comando en el servidor y cómo se accede a la consola en Hostinger (T-04, depende de la pregunta abierta 1).
- Multiusuario, roles, login social y 2FA (el discovery deja el multiusuario fuera para siempre; la 2FA se quita por decisión del usuario).
- Proveedor de correo: ya no hace falta.

**Pendiente**

- **Página principal tras el login:** hoy es el `/dashboard` del starter kit. Todavía no hay ninguna tarea en el backlog que monte la pantalla principal del reproductor; cuando exista, reemplaza `/dashboard` o cambia el destino (`fortify.home`).
- **Duración de las sesiones en el móvil:** las reglas propuestas (120 minutos más "Remember me") se confirman al usarlo a diario en el móvil. Relacionado con la pregunta abierta 3 (viabilidad en móvil).
- **Ejecutar el comando en Hostinger:** necesita una terminal interactiva (SSH o similar); depende de la pregunta abierta 1 (plan de Hostinger). Lo resuelve T-04.
- **`/user/confirm-password`:** quitarla o dejarla, según lo que se vea en el diseño (ver Restricciones).
- **Plugin de navegador de Pest:** si se añade en esta tarea o más adelante (afecta a los dos escenarios **[test de navegador]**).

**Seguimiento (cambios en otros ficheros, no hechos aquí)**

- **T-20**, Notes, línea "Correo": sustituir por "No hace falta correo: T-02 quita todas las funciones que envían email. Se deja el mailer `log` por defecto y no se añade Mailpit." El escenario "Los datos sobreviven a un reinicio" puede citar el comando `app:create-owner`, que es interactivo (`docker compose exec app php artisan app:create-owner`).
- **T-04:** añadir que el despliegue incluye ejecutar `php artisan app:create-owner` una vez en el servidor, y que eso requiere una terminal interactiva en Hostinger (pregunta abierta 1).
- **T-03:** la página de login ya no tiene "Forgot password?" ni "Sign up", y no queda ninguna página de ajustes (T-02 quita también "Appearance"); T-03 solo da estilo al login y a las páginas que queden.
- **T-05:** al añadir `/settings/spotify`, que es la primera página de ajustes tras T-02, tiene que volver a poner el enlace "Settings" en el menú de usuario y decidir a dónde va `/settings`.
- **Discovery:** en Seguridad / acceso, anotar que el login es por email y contraseña, que la cuenta se crea con un comando artisan y que la app no envía correo.

**Estimación: 5 puntos.** Quitar las features de Fortify, todas las páginas de ajustes, sus referencias en Vue y Wayfinder, la 2FA del modelo y los tests del starter kit, dejando el build en verde (2 puntos). El comando artisan con validación, reintento, el caso de usuario existente y el cierre de las demás sesiones (2 puntos). Los tests de login, bloqueo, "Remember me", logout, props de Inertia y rutas eliminadas (1 punto). Se mantiene como una sola tarea porque cabe en un solo cambio de OpenSpec y las dos mitades se prueban juntas: el login sin registro no se puede probar de punta a punta sin el comando que crea el usuario. Como opción, si el cambio crece en el diseño, se puede partir en dos: primero el comando del propietario y luego quitar las funciones sobrantes.

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **D11 (2026-11-09) lo implementa T-02:** "/" sin sesión → 302 a `/login`; con sesión → `config('fortify.home')`. El logout redirige a `/login` (`fortify.redirects.logout`). Se borra `Welcome.vue`. Escenarios a cambiar:
  - "La página de inicio no ofrece registro" pasa a ser: "/" sin sesión → 302 a "/login".
  - En "Cerrar sesión", el destino pasa a ser "/login".
  - "Las páginas de login e inicio no muestran registro…" se queda solo con "/login".
- **Página principal:** redactar los escenarios con "/dashboard" como "la página principal (`fortify.home`, hoy '/dashboard')". El Pendiente "Página principal tras el login" queda resuelto por **D12 (2026-11-09):** `/library` es la página principal y `/dashboard` se elimina; lo hace T-25.
- **Historial cifrado (sale de T-25):** `inertia.history.encrypt = true` y `Inertia::clearHistory()` en el logout, con el escenario [test de navegador] "Volver atrás tras cerrar sesión no enseña la página anterior": Given el usuario en una página con datos visibles; When pulsa "Log out" y después "atrás"; Then la URL termina en "/login" y no se ven esos datos (usar `/styleguide` o la página principal de ese momento). Fuente: https://inertiajs.com/docs/v2/security/history-encryption; comprobarlo en Inertia 3.
- **Qué se quita:** añadir las passkeys del starter kit actual (`@laravel/passkeys`, `ManagePasskeys`, `PasskeyRegister`, `PasskeyVerify` y su feature de Fortify) y la dependencia `vue-input-otp`.
- **Menú de usuario:** el escenario "Ningún enlace del menú de usuario lleva a un 404" y la regla "Las páginas de ajustes vuelven más adelante" (T-05 vuelve a poner "Settings" en el menú de usuario) valen hasta T-25, que sustituye el menú por la navegación Aero `AeroAppNav` con "Settings" → `/settings/spotify`.
- **`/settings/appearance` → 404** vale hasta T-18, que recupera esa ruta para el selector de temas. El cambio de T-18 modifica ese requisito (MODIFIED) y quita la fila del dataset.
- **Logout sin sesión (regla propuesta en T-25, sin confirmar):** un POST a `/logout` sin usuario autenticado redirigiría a `/login` en lugar de dar 419. Si el usuario lo confirma, afecta a la regla de CSRF de esta tarea.

**Seguimiento de T-04 (despliegue, 2026-11-09)**

Pregunta abierta 1 resuelta: hosting compartido Premium de Hostinger, app en `music.illodosadev.io` (subdominio de `illodosadev.io`), despliegue con GitHub Actions + SSH lanzado a mano (D14–D16).

- El Pendiente "Ejecutar el comando en Hostinger" queda resuelto: `php artisan app:create-owner` se ejecuta por SSH tras el primer despliegue (runbook "Despliegue" de T-04).

**Seguimiento de project-foundation (2026-11-10)**

- **Termina la excepción D8 de T-01:** el código de auth del kit consulta modelos directamente. Hay que añadir `App\Repositories\UserRepository` para lo que quede, y quitar la excepción de AGENTS.md (sección "Code conventions").
- **Errores de tipos del kit:** unos 20 (`tsconfig.json` y `AppHeader.vue`, donde `auth` es `unknown`). Corregirlos al tocar esos componentes y añadir la comprobación de tipos.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
