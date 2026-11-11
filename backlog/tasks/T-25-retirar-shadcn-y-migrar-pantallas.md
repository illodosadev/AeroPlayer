---
id: T-25
title: Retirar shadcn-vue y pasar a Aero las pantallas que quedan
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: should
estimate: 5
language: es
---

# T-25 — Retirar shadcn-vue y pasar a Aero las pantallas que quedan

## Goal

Cerrar la retirada gradual de shadcn-vue que se decidió en T-03. Al terminar, la app usa solo componentes Aero y no queda ni una pantalla ni un componente shadcn-vue del starter kit. Para conseguirlo, la tarea hace tres cosas:

- **`/library` como página principal (D12).** `/library` (T-08) pasa a ser la página principal tras el login. Con sesión, "/" y `/login` llevan a `/library`, y `/dashboard` y su página `Dashboard` desaparecen. D11 (sin sesión, "/" lleva a `/login`; el logout lleva a `/login`; `Welcome` desaparece) lo implementa T-02. Aquí solo se verifica el estado final.
- **Navegación Aero y avisos flash.** La barra lateral, la cabecera y el menú de usuario del starter kit se sustituyen por una navegación propia ("Library", "Search", "Settings", "Log out"), dentro del layout persistente donde vive la barra de reproductor. Los toasts de `vue-sonner` se sustituyen por un aviso Aero.
- **Limpieza.** Se borran `resources/js/components/ui`, los layouts y componentes del starter kit que ya no se usen, `components.json`, las variables CSS de shadcn y las dependencias npm que quedan sin uso. Un test impide que vuelvan.

Es necesario porque "look años 2000 logrado" es una métrica de éxito del discovery. Una app que mezcla pantallas Aero con la barra lateral gris de shadcn no la cumple.

## Context

Creada por el orquestador del lote `/task-enrich-batch` del 2026-11-09. Los revisores de T-03 detectaron que este trabajo no tenía dueño.

- **Decisión de T-03 (2026-11-09):** los componentes shadcn-vue no se reestilan, se retiran poco a poco. Cada tarea quita los de las pantallas que toca. T-03 deja Aero el login y el layout de autenticación. T-21 añade los controles del reproductor.
- **D11 (usuario, 2026-11-09):** un visitante sin sesión que abre "/" va a `/login`. La página `Welcome` del starter kit se elimina. El logout también lleva a `/login`. **Lo implementa T-02.**
- **D12 (usuario, 2026-11-09):** `/library` (T-08) es la página principal tras el login. `/dashboard` se elimina. Con sesión, "/" y `/login` llevan a `/library`, y `fortify.home` apunta a `/library`. **Lo implementa esta tarea**, porque necesita `/library`.
- **Pantallas del starter kit, una por una, cuando llegue esta tarea** (comprobado en `laravel/vue-starter-kit`, rama `main`, el 2026-11-09; ver Restricciones):
  - `Welcome.vue`: la quita T-02 (D11).
  - `Dashboard.vue`: se elimina aquí (D12).
  - Ajustes de perfil, contraseña, 2FA y apariencia: los quita T-02, igual que el layout de ajustes. T-02 también quita las passkeys y la 2FA, con sus dependencias (ver Seguimiento).
  - `auth/Login.vue` y el layout de autenticación: ya son Aero (T-03). Los layouts de autenticación alternativos que no se usen (variantes "card" y "split") se borran aquí.
  - `auth/ConfirmPassword.vue` (`/user/confirm-password`): depende de lo que decida T-02 (ver Pendiente).
  - **Layout de la app:** `AppLayout` con `AppSidebarLayout`/`AppHeaderLayout`, más `AppSidebar`, `AppHeader`, `NavMain`, `NavFooter`, `NavUser`, `UserMenuContent`, `UserInfo`, `Breadcrumbs`, `AppShell`, `AppContent`, `AppSidebarHeader` y `PlaceholderPattern`. **Ninguna tarea los migra:**
    - T-06 crea el layout persistente con la barra (T-21), pero no la navegación.
    - T-05, T-07, T-08 y T-24 meten sus páginas dentro de `AppLayout`.

    Por eso la navegación Aero entra en esta tarea.
  - **Avisos flash (toasts):** el starter kit los muestra con `vue-sonner` (`components/ui/sonner`), que se inicializa en `app.ts`. No se puede borrar `components/ui` sin un sustituto Aero.
  - **Páginas de T-05 (`/settings/spotify`), T-07 (`/search`), T-08 (`/library…`) y T-24 (álbum y artista):** ningún escenario de esas tareas comprueba que no usen shadcn-vue. Si usan alguno, se migra aquí (ver Seguimiento).
- **Las páginas de error Aero** (404, 419, 429, 500 y 503) van en T-26 ("Páginas de error Aero"). Solo depende de T-01, T-02 y T-03.
- **Depende de:**
  - T-02 (D11 implementado, y passkeys y 2FA quitadas);
  - T-03 (componentes Aero base, skill y runner de tests de navegador Pest 4);
  - T-21 (barra y controles del reproductor);
  - T-06 (layout persistente con el reproductor);
  - T-23 (zona de avisos de la barra, que la navegación no debe tapar);
  - T-05 (`/settings/spotify`, destino del enlace "Settings");
  - T-07 (`/search`);
  - T-08 (`/library`, la nueva página principal);
  - T-24 (páginas de álbum y de artista, que entran en la comprobación de la navegación).
- **Orden recomendado:** después de las tareas con pantalla de la v1 de Spotify (T-05, T-06, T-07, T-08, T-21, T-23 y T-24) y antes de las de YouTube Music (T-09 a T-14). Así las tareas siguientes nacen dentro de la navegación Aero, y el test de limpieza les impide volver a usar shadcn-vue. Si se hace más tarde, hay más pantallas que repasar y la estimación sube.

## Acceptance criteria

```gherkin
# --- Rutas de entrada: D11 (lo implementa T-02, aquí se verifica) y D12 -------

Scenario: Un visitante sin sesión que abre "/" va al login
  Given un visitante sin sesión
  When pide "/"
  Then la respuesta es una redirección 302 con la cabecera Location "/login"
  And al seguir la redirección, la respuesta Inertia indica el componente de la página de login (el de T-02/T-03 en la versión instalada)

Scenario: El usuario con sesión que abre "/" va a la biblioteca
  Given el usuario propietario con sesión iniciada
  When pide "/"
  Then la respuesta es una redirección 302 con la cabecera Location "/library"

Scenario: Tras el login se llega a la biblioteca
  Given el usuario propietario sin sesión y sin URL pendiente en la sesión
  When envía sus credenciales correctas en "/login"
  Then queda autenticado y se le redirige a "/library"

Scenario: Tras el login se vuelve a la página que se pidió
  Given un visitante sin sesión que ha pedido "/library/liked" y ha sido redirigido a "/login"
  When envía las credenciales correctas del propietario
  Then se le redirige a "/library/liked"

Scenario: El usuario con sesión que abre el login va a la biblioteca
  Given el usuario propietario con sesión iniciada
  When pide "/login"
  Then la respuesta es una redirección 302 con la cabecera Location "/library", sin pasar por "/"

Scenario: Cerrar sesión lleva al login
  Given el usuario propietario con sesión iniciada en "/library"
  When envía un POST a "/logout" con un token CSRF válido
  Then la sesión termina y se le redirige a "/login"
  And al pedir "/library" después se le redirige a "/login"

# Depende de la regla propuesta sobre "/dashboard" (302 a "/library"; la alternativa es un 404). Ver Pendiente.
Scenario Outline: Los marcadores antiguos de "/dashboard" llevan a la biblioteca
  Given <visitante>
  When pide "/dashboard"
  Then la respuesta es una redirección 302 con la cabecera Location "/library"
  And al seguir las redirecciones acaba en <destino final>

  Examples:
    | visitante                                  | destino final |
    | el usuario propietario con sesión iniciada | "/library"    |
    | un visitante sin sesión                    | "/login"      |

Scenario: Primera entrada sin Spotify conectado
  Given el usuario propietario sin sesión y Spotify sin conectar
  When inicia sesión con las credenciales correctas
  Then llega a "/library" y ve "Connect Spotify to see your library." y el botón "Connect Spotify" (T-08)

Scenario: Ya no existen las páginas Welcome ni Dashboard
  Given el repositorio al terminar la tarea
  When se buscan los componentes de página y las rutas registradas
  Then no existen "resources/js/pages/Welcome.vue" ni "resources/js/pages/Dashboard.vue"
  And no hay ninguna ruta con el nombre "dashboard"
  And "config('fortify.home')" vale "/library"

# --- Cierre de sesión repetido o desde una pestaña antigua --------------------

# Depende de la regla propuesta "Logout sin sesión" (ver Pendiente).
Scenario: Un logout sin sesión acaba en el login sin página de error
  Given un visitante cuya sesión ya ha terminado (por ejemplo, tras cerrar sesión en otra pestaña)
  And la protección CSRF activa
  When envía un POST a "/logout" con el token CSRF de la sesión anterior
  Then la respuesta es una redirección 302 con la cabecera Location "/login"
  And la respuesta no tiene estado 419

Scenario: Doble clic en "Log out" [test de navegador]
  Given el usuario propietario en "/library"
  When hace doble clic en "Log out" en la navegación
  Then llega a "/login"
  And no se muestra ninguna página ni modal de error
  And ninguna respuesta del servidor a "/logout" tiene estado 419 ni 500

# --- Navegación Aero ---------------------------------------------------------

Scenario Outline: La navegación Aero aparece en todas las páginas con sesión [test de navegador]
  Given el usuario propietario con sesión iniciada
  When abre "<página>"
  Then ve la navegación (data-aero="app-nav") con los enlaces "Library", "Search" y "Settings" y el botón "Log out"
  And los enlaces apuntan a "/library", "/search" y "/settings/spotify"
  And ve la barra de reproductor de T-21 (data-aero="player-bar")
  And la página no tiene ningún elemento con el atributo "data-slot"

  Examples:
    | página                                      |
    | /library                                    |
    | /library/liked                              |
    | /search                                     |
    | /settings/spotify                           |
    | /styleguide                                 |
    | /spotify/albums/2noRn2Aes5aoNVsU6iWThc      |
    | /spotify/artists/4tZwfgrHOc3mvqYlEYSvVi     |

Scenario Outline: El enlace de la página actual está marcado [test de navegador]
  Given el usuario propietario con sesión iniciada
  When abre "<página>"
  Then el enlace "<enlace activo>" de la navegación tiene aria-current="page"
  And los otros dos enlaces de la navegación no tienen el atributo aria-current

  Examples:
    | página                            | enlace activo |
    | /library                          | Library       |
    | /library/liked                    | Library       |
    | /library/playlists/spotify/road   | Library       |
    | /search                           | Search        |
    | /settings/spotify                 | Settings      |

Scenario: Cerrar sesión desde la navegación [test de navegador]
  Given el usuario propietario en "/library"
  When pulsa "Log out" en la navegación
  Then llega a "/login"
  And se ha enviado un POST a "/logout" (no un GET)

Scenario: La música sigue sonando al navegar con la navegación Aero [test de navegador]
  Given el SDK simulado de T-06 listo y un estado de reproducción con "Track A"
  When el usuario pulsa "Library", después "Search" y después "Settings" en la navegación
  Then "Spotify.Player" se ha creado una sola vez y no se ha llamado a "disconnect()"
  And la barra sigue mostrando "Track A"

Scenario: La navegación funciona en el móvil [test de navegador]
  Given un viewport de 375x667 px y el usuario propietario en "/library"
  When busca los destinos "Library", "Search", "Settings" y "Log out"
  Then cada uno es visible o se alcanza con un solo toque previo en un botón de menú con nombre accesible "Menu"
  And document.documentElement.scrollWidth es menor o igual que 375
  And el área táctil de cada enlace, del botón "Log out" y del botón "Menu" (si existe) mide al menos 44x44 px
  And la barra de reproductor sigue visible

Scenario: La navegación se usa con el teclado [test de navegador]
  Given el usuario propietario en "/library" con un viewport de 1280x800 px
  When recorre la navegación con Tab y pulsa Enter sobre "Search"
  Then cada elemento enfocado muestra un indicador de foco de al menos 2 px que no tiene sin el foco
  And la página pasa a "/search"

# --- Avisos flash ------------------------------------------------------------

Scenario: Los avisos flash se ven en Aero [test de navegador]
  Given el usuario propietario con sesión iniciada
  And una ruta, registrada solo en el test, que guarda el mensaje flash "Saved." y redirige a "/library"
  When abre esa ruta
  Then en "/library" ve el texto "Saved." dentro de un aviso Aero (data-aero="flash") con role="status"
  And la página no tiene ningún elemento con el atributo "data-slot"
  When pulsa "Search" en la navegación
  Then el aviso "Saved." ya no se ve

# --- Limpieza de shadcn-vue --------------------------------------------------

Scenario: No queda código de shadcn-vue
  Given el repositorio al terminar la tarea
  When se recorren los ficheros de "resources/js"
  Then ningún fichero importa de "@/components/ui" (cero coincidencias, sin excepciones)
  And no existe la carpeta "resources/js/components/ui"
  And no existe "components.json" en la raíz
  And ningún fichero define ni importa la función "cn" de "@/lib/utils"
  And esta comprobación es un test que corre con "php artisan test"

Scenario: No quedan los layouts ni los componentes de navegación del starter kit
  Given el repositorio al terminar la tarea
  When se buscan en "resources/js" los componentes AppSidebar, AppSidebarHeader, AppHeader, AppShell, AppContent, NavMain, NavFooter, NavUser, UserMenuContent, UserInfo, Breadcrumbs, PlaceholderPattern y AppLogoIcon, y los layouts "layouts/app/AppSidebarLayout" y "layouts/app/AppHeaderLayout"
  Then no existe ninguno de esos ficheros y ningún fichero los importa
  And en "layouts/auth" solo queda el layout que usa el login Aero

Scenario: Las dependencias de shadcn-vue desaparecen
  Given el "package.json" y el "package-lock.json" al terminar la tarea
  When se leen "dependencies" y "devDependencies" de "package.json" y la entrada raíz (packages[""]) del lockfile
  Then ninguno de los dos lista "reka-ui", "class-variance-authority", "clsx", "tailwind-merge", "tw-animate-css" ni "vue-sonner" como dependencia directa
  And "@lucide/vue" y "@vueuse/core" solo siguen si algún fichero de "resources/js" los importa
  And "npm ci" desde cero termina con código 0

Scenario: El CSS no conserva el tema de shadcn-vue
  Given "resources/css/app.css" al terminar la tarea
  When se lee su contenido
  Then no contiene "tw-animate-css", "@custom-variant dark", ninguna variable "--sidebar" ni el bloque ".dark"

Scenario: El proyecto sigue compilando y los tests pasan
  Given el proyecto tras la limpieza
  When se ejecutan el build de producción del frontend, la comprobación de tipos y "php artisan test"
  Then los tres comandos terminan con código 0

Scenario: La guarda contra shadcn-vue detecta una regresión
  Given la comprobación de "No queda código de shadcn-vue", que recibe como parámetro la carpeta que recorre
  When se ejecuta sobre una carpeta temporal creada por el test que contiene un fichero "Broken.vue" que importa de "@/components/ui/button"
  Then la comprobación falla y nombra "Broken.vue"

# --- Validación visual -------------------------------------------------------

@manual
Scenario: El propietario aprueba la navegación
  Given la app en marcha en local con Spotify conectado
  When el propietario revisa "/library", "/search" y "/settings/spotify" a 1280 px y a 375 px frente a los cuatro rasgos del discovery
  Then el "design.md" del cambio tiene una sección "Validación visual" con la fecha, las capturas y un veredicto "aprobado" para cada rasgo (glossy / cristal, naturaleza y cielo, reproductores clásicos, skeuomorfismo)
  And cada corrección que pidió el propietario está marcada con [x]
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D11:** sin sesión, "/" redirige a `/login`. La página `Welcome` del starter kit se elimina, no se reestila. El logout lleva a `/login`. Lo implementa T-02; esta tarea lo verifica.
- **D12:** `/library` (T-08) es la página principal tras el login. `/dashboard` se elimina, y `fortify.home` apunta a `/library`. Con sesión, "/" y `/login` llevan a `/library`. Lo implementa esta tarea.
- **Retirada gradual (T-03):** shadcn-vue no se reestila. Esta tarea es el último paso: termina con cero importaciones de `@/components/ui`.
- Con D11 y D12 se resuelven los tres "Pendiente" del borrador: qué pantallas quedan, qué pasa con "/" y qué pasa con el dashboard.

**Decisiones del lote (orquestador, 2026-11-09)**

- Las páginas de error Aero pasan a T-26 (solo depende de T-01, T-02 y T-03).
- El historial cifrado de Inertia y `Inertia::clearHistory()` en el logout (botón "atrás" tras cerrar sesión) pasan a T-02.
- D11 lo implementa T-02. Las passkeys y `vue-input-otp` los quita T-02.
- T-14 pone "My playlists" dentro de `/library` y no añade ningún enlace a la navegación.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **`/dashboard` redirige con un 302 a `/library`**, sin middleware `auth`. La alternativa es un 404 (ver Pendiente). Se propone la redirección por tres motivos:
  - Tras semanas usando `/dashboard`, el navegador lo autocompleta y puede haber marcadores.
  - Laravel guarda la URL pendiente ("intended"): un visitante que pidió `/dashboard` antes del login volvería a una página rota.
  - No expone nada: un visitante sin sesión acaba en `/login`.

  Se usa 302 y no 301 porque el navegador guarda los 301 indefinidamente y así el cambio se puede deshacer.
- **"/" con sesión va a `config('fortify.home')`** (la redirección de "/" la crea T-02). Aquí solo cambia `fortify.home`. Se mantiene el nombre de ruta `home` si Wayfinder o el layout de autenticación lo usan.
- **Redirecciones directas, sin saltos intermedios:**
  - `fortify.home = '/library'`.
  - `$middleware->redirectUsersTo('/library')` en `bootstrap/app.php`, para el middleware `guest` (un usuario con sesión que abre `/login`). Sin ese ajuste, `RedirectIfAuthenticated` busca una ruta llamada `dashboard` o `home` y acabaría en "/", con un segundo salto.
  - `fortify.redirects.logout = '/login'` lo pone T-02.
- **Logout sin sesión:**
  - Un POST a `/logout` que llega sin usuario autenticado y con un token CSRF caducado redirige a `/login`, en lugar de responder 419. La intención del usuario (salir) ya se ha cumplido y no hay nada que proteger.
  - El escenario de T-02 "El logout exige POST con token CSRF" sigue igual, porque es con la sesión iniciada: ahí sigue el 419 y el usuario sigue autenticado.
  - Cómo se implementa (por ejemplo, un `render` para el 419 de la ruta `logout` cuando `Auth::guest()`) se decide en el diseño.
- **Botón "Log out":** queda desactivado mientras su petición está en curso (`processing` de Inertia). Así un doble clic no envía dos POST. Si llegan dos de todas formas, se aplica la regla anterior.
- **Navegación Aero (`AeroAppNav`) dentro del layout persistente de T-06:**
  - Es un único layout (por ejemplo, `AeroAppLayout`) con `AeroBackground`, la navegación, el contenido de la página y la barra de T-21 con su zona de avisos (T-23).
  - Sustituye a `AppLayout` en la resolución de layouts de `app.ts`. Las páginas `auth/` siguen con el layout de autenticación Aero, y todas las demás usan `AeroAppLayout`.
  - El `Spotify.Player` se sigue creando una sola vez por pestaña (escenario de la música al navegar).
- **Destinos de la navegación:**
  - "Library" (`/library`), "Search" (`/search`), "Settings" (`/settings/spotify`) y "Log out" (POST a `/logout`).
  - "My playlists" de T-14 vive dentro de `/library`, sin enlace propio. Los ajustes de YouTube Music (T-09) cuelgan de "Settings".
  - `/styleguide` no sale en la navegación: se abre escribiendo la URL.
  - "Library" se marca como activo en `/library` y en todas sus subrutas (`/library/liked`, `/library/playlists/…`, incluidas las playlists propias de T-14).
- **Ganchos para los tests:** `data-aero="app-nav"`, `data-aero="flash"` y `data-aero="player-bar"` (este último, si T-21 no le da otro nombre), según la regla `data-aero` de T-03.
- **Sin logo de Laravel:** se borra `AppLogoIcon` (el logo de Laravel). Mientras el propietario no elija un logo (ver Pendiente), la navegación muestra el nombre de la app (`APP_NAME`) como texto.
- **Avisos flash:**
  - Un componente Aero (`AeroFlash`, con `role="status"`) sustituye al `Toaster` de `vue-sonner`.
  - Lee el mismo mensaje flash compartido que usa el backend, el mecanismo del starter kit en la versión instalada.
  - Desaparece al navegar a otra página.
- **Textos de la interfaz (inglés, propuesta):** "Library", "Search", "Settings", "Log out", "Menu".
- **Qué se borra (lista de partida, se ajusta a la versión instalada):**
  - `resources/js/components/ui/` entero y `components.json`.
  - `lib/utils.ts`, si solo contenía `cn()`.
  - Los componentes de navegación y layout del starter kit listados en Context.
  - Los composables sin uso: `useInitials`, `useCurrentUrl`, y `useAppearance` si T-03 no lo quitó.
  - La página `Dashboard`, su ruta y las referencias de Wayfinder a ella.
  - Las variables CSS de shadcn y `tw-animate-css` en `app.css`.
  - Las dependencias npm que quedan sin importaciones.
  - Tailwind **se queda** para el maquetado (regla de T-03).
- **Dependencias que deben desaparecer:** `reka-ui`, `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css` y `vue-sonner`. `@lucide/vue` y `@vueuse/core` se quedan solo si algo los importa, porque los iconos son un Pendiente de T-03/T-21. `vue-input-otp` y `@laravel/passkeys` ya no están tras T-02.
- **Tests de otras tareas que cambian aquí:**
  - Los que esperan "/dashboard" como destino tras el login (T-02, T-03 y T-18) se actualizan en este cambio, salvo que antes se apliquen los seguimientos que los hacen independientes.
  - El delta de spec de este cambio usa MODIFIED para el requisito del destino tras el login.

**Restricciones descubiertas**

- **Starter kit actual** (`laravel/vue-starter-kit`, rama `main`, consultado el 2026-11-09):
  - **Dependencias** ([`package.json`](https://github.com/laravel/vue-starter-kit/blob/main/package.json)): `@inertiajs/vue3` ^3, `@laravel/passkeys`, `@lucide/vue` (ya no `lucide-vue-next`), `@vueuse/core`, `class-variance-authority`, `clsx`, `reka-ui`, `tailwind-merge`, `tailwindcss` ^4, `tw-animate-css`, `vue-input-otp` y `vue-sonner`.
  - **[`components.json`](https://github.com/laravel/vue-starter-kit/blob/main/components.json):** estilo "new-york", con los alias `ui` en `@/components/ui` y `utils` en `@/lib/utils`.
  - **`resources/js/components/ui`:** 22 carpetas (alert, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, input-otp, input, label, navigation-menu, select, separator, sheet, sidebar, skeleton, sonner, spinner y tooltip).
  - **[`routes/web.php`](https://github.com/laravel/vue-starter-kit/blob/main/routes/web.php):** `Route::inertia('/', 'Welcome')->name('home')` y `Route::inertia('dashboard', 'Dashboard')->name('dashboard')`, este con `auth` y `verified`.
  - **`app.ts`:** elige el layout por el nombre de la página (`Welcome` sin layout, `auth/` con `AuthLayout`, `settings/` con `AppLayout` y `SettingsLayout`, y el resto con `AppLayout`). También inicializa el tema y los toasts flash.
  - **`resources/css/app.css`:** importa `tw-animate-css`, declara `@custom-variant dark` y define las variables de shadcn (`--background`, `--primary`, `--sidebar-*`…) para `:root` y `.dark`.

  T-01 instala la última versión estable, así que esta lista se comprueba con la versión instalada al hacer el diseño.
- **`RedirectIfAuthenticated`** (Laravel 12.x): sin `redirectUsersTo`, redirige a la ruta llamada `dashboard` o `home`, y si no existe ninguna, a "/". Al quitar `dashboard` hay que configurar el destino, o se añade un salto por "/". Fuente: [`RedirectIfAuthenticated.php`](https://github.com/laravel/framework/blob/12.x/src/Illuminate/Auth/Middleware/RedirectIfAuthenticated.php).
- **Fortify:** `config/fortify.php` tiene `home` y un array `redirects` con la clave `logout`. Fuente: [`config/fortify.php`](https://github.com/laravel/fortify/blob/1.x/config/fortify.php).
- **CSRF en el logout:** al cerrar sesión, Laravel invalida la sesión y regenera el token CSRF. Un segundo POST con el token anterior, desde un doble clic o desde otra pestaña, recibe 419 si no se aplica la regla de "Logout sin sesión". Inertia mostraría esa respuesta HTML en un modal.
- **Wayfinder:** borrar la ruta `dashboard` (y `home`, si se renombra) rompe el build si algún componente sigue importando sus funciones (`dashboard()`, `home()`). Las usan, por ejemplo, el logo del layout de autenticación, `AppSidebar` y `AppHeader`. Por eso el escenario de build va después de la limpieza.

**Casos límite**

- **Enlace profundo a `/dashboard`:** redirige (ver Reglas propuestas). Las rutas que quita T-02 dan 404, y su aspecto es cosa de la tarea de páginas de error.
- **Sesión caducada con la app abierta:** al pulsar un enlace de la navegación, el middleware `auth` manda a `/login` y guarda la URL pendiente. Tras entrar, se vuelve a esa página.
- **"Log out" con la sesión ya caducada, o desde una pestaña antigua:** se aplica la regla de "Logout sin sesión" y se acaba en `/login` sin página de error (escenario incluido).
- **"Remember me" y "/" con la sesión caducada:** "/" usa el guard normal, así que la cookie `remember_web_*` restaura la sesión y se va a `/library`, no a `/login`.
- **Dos pestañas:** si se cierra sesión en una y se navega en la otra, se acaba en `/login`. La otra pestaña puede seguir sonando hasta que navega. Es el comportamiento de T-06 y no se cambia.
- **Nombre de página largo o zoom al 200 %:** la navegación no debe tapar la barra de reproductor ni su zona de avisos (T-23), ni provocar scroll horizontal.
- **Pantallas de tareas posteriores (T-09 a T-19):** nacen dentro de `AeroAppLayout`, y el test de limpieza les impide importar de `@/components/ui`.

**Pistas para los tests**

- **Feature tests de Pest:**
  - Las redirecciones de "/", `/login`, `/dashboard` y `/logout`, con `assertRedirect('/library')` / `assertRedirect('/login')`, con y sin `actingAs`.
  - Seguir la redirección de "/" con `followingRedirects()` y `assertInertia(fn ($page) => $page->component(...))`.
  - La URL pendiente: `get('/library/liked')` seguido del login y `assertRedirect('/library/liked')`.
  - `config('fortify.home')`, y que no exista la ruta `dashboard` con `Route::has('dashboard')`.
  - **Logout sin sesión:** se reactiva el CSRF en el test (como en T-02) y se envía el POST sin `actingAs`, con un token que no coincide con la sesión.
- **Guarda contra shadcn-vue:**
  - Es una función que recibe la carpeta que recorre. El test normal la ejecuta sobre `resources/js` y falla si algún `.vue` o `.ts` contiene `@/components/ui` o importa `cn`.
  - El escenario de regresión la ejecuta sobre una carpeta temporal creada por el propio test (por ejemplo, en `storage/framework/testing`), nunca dentro de `resources/js`.
  - Otro test comprueba que no existen la carpeta `components/ui` ni `components.json`, y lee `package.json` y `packages[""]` de `package-lock.json` para la lista de dependencias prohibidas.
- **Tests de navegador (Pest 4, T-03):**
  - Cuentan `[data-slot]` en cada página del Scenario Outline, y comprueban la navegación por `data-aero`, `aria-current`, el foco, los 375 px y las áreas táctiles.
  - La música al navegar usa el SDK simulado de T-06. Las páginas de T-24 se abren con su API de Spotify falsa y la playlist "road" con la de T-08.
  - El aviso flash usa una ruta registrada solo en el test, sin depender de T-05.
  - **Doble clic:** se comprueba el destino final, que no hay modal de error de Inertia en el DOM y, del lado del servidor, que no hubo respuestas 419 al logout.
- **Build:** `npm ci`, el build de producción y `vue-tsc` en el servicio `test` de T-20, o en local.

**Fuera de alcance**

- **Páginas de error Aero:** van en T-26.
- **Historial cifrado de Inertia y botón "atrás" tras el logout:** van en T-02. También es de T-02 implementar D11: "/" sin sesión, el destino del logout y borrar `Welcome`.
- **Contenido de las pantallas de T-05, T-07, T-08 y T-24:** no se reestila nada más allá de cambiar sus componentes shadcn-vue por los Aero equivalentes.
- **Ajustes de YouTube Music (T-09) y "My playlists" (T-14):** las playlists de T-14 viven dentro de `/library`, sin enlace en la navegación.
- **Temas y skins (T-18) y modo oscuro.**
- **Subnavegación de ajustes:** T-25 solo pone el enlace "Settings" (a `/settings/spotify`) en `AeroAppNav`. No hay subnavegación dentro de ajustes mientras solo exista `/settings/spotify`. La construye la primera tarea que añada una segunda página de ajustes: T-18 ("Spotify | Appearance", ya previsto en T-18) o T-09 si llega antes, y la otra se suma a ella.
- **Quitar Tailwind:** se queda como capa de maquetado.
- **Nuevas funciones en la navegación,** como un buscador en la cabecera, el estado de conexión de Spotify o un avatar.
- **Las reglas de autenticación (T-02).** Aquí solo cambian el destino tras el login y el caso del logout sin sesión.

**Pendiente**

- **`/dashboard`: 302 a `/library` (regla propuesta) o 404.** Lo confirma el propietario. Si elige 404, el Scenario Outline de `/dashboard` pasa a esperar estado 404.
- **Logout sin sesión (regla propuesta):** responder 302 a `/login` en lugar de 419 a un POST a `/logout` sin usuario autenticado es una excepción al CSRF en una ruta de autenticación. Lo confirma el usuario en /opsx-propose. La alternativa es dejar el 419 y mostrar la página 419 Aero de T-26. El escenario del doble clic vale en los dos casos, porque el botón desactivado solo envía un POST.
- **Prioridad:** se propone subir la tarea a `must`, porque es la única que implementa D12 y la navegación de la app. Lo decide el usuario. Por ahora se mantiene `should`.
- **Forma de la navegación en el móvil:** barra de pestañas abajo, junto a la barra de reproductor, o botón "Menu" con un panel. Los escenarios valen para las dos opciones. Lo decide la revisión del propietario.
- **Logo de la app** que sustituye al de Laravel, e **iconos de la navegación**. Los elige el propietario, igual que los recursos de T-03 y T-21.
- **`/user/confirm-password`:** depende del Pendiente de T-02. Si la ruta sigue registrada y su página usa shadcn-vue, esta tarea la pasa a Aero (un campo de contraseña y un botón, como el login). Si T-02 la quita, no hay nada que hacer.
- **Cuántas pantallas quedan con shadcn-vue al llegar aquí:** depende de si T-05, T-07, T-08 y T-24 aplican el seguimiento de abajo. La estimación supone que sí.

**Seguimiento (cambios en otros ficheros, no hechos aquí)**

- **T-02 (`backlog/tasks/T-02-login-un-solo-usuario.md`):**
  - **Implementar D11.** "/" redirige a `/login` sin sesión y a `config('fortify.home')` con sesión. El logout redirige a `/login` (`fortify.redirects.logout`), y se borra `Welcome.vue`.
  - **Cambiar los escenarios afectados por D11:**
    - "La página de inicio no ofrece registro" pasa a ser: "/" sin sesión → 302 a "/login".
    - En "Cerrar sesión", el destino pasa a ser "se le redirige a '/login'".
    - "Las páginas de login e inicio no muestran registro…" se queda solo con "/login".
  - **Historial cifrado:** `inertia.history.encrypt = true` y `Inertia::clearHistory()` en el logout, con el escenario **[test de navegador]** "Volver atrás tras cerrar sesión no enseña la página anterior". Es el que salió de T-25: Given el usuario en una página con datos visibles; When pulsa "Log out" y después "atrás"; Then la URL termina en "/login" y no se ven esos datos. Como en T-02 aún no existe `/library`, se puede usar `/styleguide` o la página principal de ese momento. Fuente: https://inertiajs.com/docs/v2/security/history-encryption. Hay que comprobarlo en Inertia 3.
  - **Página principal:** redactar los escenarios con "/dashboard" como "la página principal (`fortify.home`, hoy '/dashboard')", para que T-25 solo cambie la configuración. Su Pendiente "Página principal tras el login" queda resuelto por D12 (`/library`, en T-25).
  - **Qué se quita:** añadir las passkeys del starter kit actual (`@laravel/passkeys`, `ManagePasskeys`, `PasskeyRegister`, `PasskeyVerify` y la feature de Fortify que corresponda) y la dependencia `vue-input-otp` de la 2FA.
  - **Menú de usuario:**
    - Anotar en el escenario "Ningún enlace del menú de usuario lleva a un 404" que vale hasta T-25, que sustituye el menú de usuario por `AeroAppNav`. Desde entonces lo cubren los escenarios de navegación de T-25.
    - En la regla "Las páginas de ajustes vuelven más adelante", decir que T-05 vuelve a poner "Settings" en el menú de usuario del starter kit solo hasta T-25, que lo lleva a la navegación Aero.
- **T-01 (`backlog/tasks/T-01-base-del-proyecto.md`):** añadir una nota a "La página de inicio es un componente Vue servido por Inertia" diciendo que vale solo hasta T-02 (D11 elimina `Welcome`). En "Base de datos no accesible", pedir "/login" en lugar de "/", para que el escenario no dependa de D11.
- **T-03 (`backlog/tasks/T-03-sistema-diseno-aero.md`):**
  - En el escenario "La app nunca entra en modo oscuro", cambiar "/dashboard" por "/styleguide", que existe desde T-03 y no desaparece.
  - En "Fuera de alcance", sustituir "Reestilar '/'…" por: "'/' deja de ser una página: redirige a /login (D11, T-02) o a /library (D12, T-25). La limpieza final de shadcn-vue, la navegación y el dashboard son de T-25; las páginas de error Aero, de T-26".
  - En la decisión de "Solo modo claro" y en "Casos límite", cambiar "como el dashboard" por "como la navegación del starter kit, hasta T-25".
- **T-18 (`backlog/tasks/T-18-skins-temas.md`):**
  - Cambiar "/dashboard" por "/styleguide" (o "/library" si T-18 va después de T-25) en todos los escenarios y pistas de test que lo usan como página con sesión (buscar "/dashboard" en el fichero, incluida la pista `get('/dashboard')`). No tocar "/settings/appearance": T-18 recupera esa ruta a propósito ("Conflicto con T-02").
  - En las Notas, "dashboard y otras, hasta T-25" pasa a ser "la navegación del starter kit y otras, hasta T-25".
  - La subnavegación de ajustes (Spotify | Appearance) es de T-18, no de T-25 (ver "Fuera de alcance").
- **T-20 (`backlog/tasks/T-20-stack-docker-local.md`):** hacer los escenarios independientes de D11.
  - En "Primer arranque…", pedir "http://localhost:8081/login" y comprobar que `data-page` indica el componente de la página de login, no "Welcome".
  - En "Base de datos caída", "Recuperación…", "Recarga en caliente" y "El puerto se elige con APP_PORT", pedir "/login".
  - En "Smoke test del stack", comprobar "/" → 302 con Location "/login", "/login" → 200, la hoja de estilos → 200 y una ruta inexistente → 404.
- **T-08 (`backlog/tasks/T-08-biblioteca-spotify.md`):**
  - Anotar en Context que `/library` pasa a ser la página principal tras el login (D12, lo conecta T-25), y que su estado "Connect Spotify to see your library." es lo primero que ve el usuario en la primera entrada.
  - Añadir un escenario "La página no tiene elementos con el atributo 'data-slot'" para `/library`, `/library/liked` y el detalle de playlist.
- **T-05 (`backlog/tasks/T-05-conectar-spotify.md`):**
  - T-02 quita el layout de ajustes del starter kit, así que `/settings/spotify` se construye con componentes Aero. Añadir el escenario "la página no tiene elementos con el atributo 'data-slot'".
  - Indicar cómo muestra los mensajes de redirección ("Spotify disconnected.", etc.): dentro de la página o como aviso flash compartido. T-25 sustituye los toasts de `vue-sonner` por `AeroFlash`.
  - Su Pendiente "Ubicación de la página" queda resuelto: `/settings/spotify`, enlazada desde la navegación Aero de T-25.
- **T-07 (`backlog/tasks/T-07-buscar-spotify.md`) y T-24 (`backlog/tasks/T-24-spotify-album-y-artista.md`):** añadir a cada una el escenario "la página no tiene elementos con el atributo 'data-slot'" y la nota de que se construyen con componentes Aero (regla de T-03).
- **T-06 (`backlog/tasks/T-06-reproducir-spotify.md`):** anotar que el layout persistente que crea es el que T-25 convierte en `AeroAppLayout`, con la navegación Aero. El reproductor debe vivir en un único componente de layout, para que T-25 lo pueda envolver sin recrear el `Spotify.Player`.
- **Nueva tarea de páginas de error Aero:** la crea el orquestador con el contenido que devolvió este enriquecimiento.
- **Discovery (`backlog/discovery/reproductor-musica-privado.md`):** registrar D11 y D12 con fecha 2026-11-09.

**Tamaño**

Se mantiene como una sola tarea y no se propone dividirla, porque sus partes están acopladas:
- La navegación sustituye a `components/ui/sidebar` y `dropdown-menu`, así que la limpieza no se cierra sin ella.
- `AeroFlash` sustituye a `components/ui/sonner`.
- El enlace "Library" de la navegación apunta a la página principal de D12.

**Estimación: 5 puntos.**
- **Navegación Aero en el layout persistente, con versión móvil, foco y `aria-current` (1,5 puntos):** es la pieza visual nueva, y el móvil necesita prueba y error.
- **D12, `/dashboard`, la URL pendiente, el logout sin sesión con el botón desactivado, y la actualización de los tests de T-02, T-03 y T-18 que esperan "/dashboard" (1 punto).**
- **Limpieza de `components/ui`, layouts, composables, CSS, dependencias y Wayfinder, con el build en verde y la guarda (1 punto):** se borra mucho, pero es mecánico.
- **`AeroFlash` en lugar de `vue-sonner` (0,5 puntos).**
- **Tests de navegador y ronda de revisión del propietario (1 punto).**

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- **Navegación ampliable:** el escenario que lista los enlaces de `AeroAppNav` ("Library", "Search", "Settings", "Log out") tiene que permitir que otras tareas añadan enlaces: T-19b añade "History" y "Stats".
- **Colores de `AeroAppNav` y de cada pantalla migrada:** solo de tokens `--aero-*`, para que sigan los temas de T-18 (también Graphite).

**Seguimiento de aero-design-system (T-03, 2026-11-11)**

- Sustituir el logo de Laravel del login (`AppLogoIcon`) por uno de AeroPlayer.
- Sustituir el fondo SVG hecho a mano (`resources/aero/backgrounds/sky.svg`) por una fotografía real de cielo y naturaleza: WebP o AVIF de menos de 400 KB, con su fila en `CREDITS.md`, y ajustar `--aero-background-fallback` a sus colores. El propietario aprobó el aspecto actual pero quiere una imagen real.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] `@manual` scenarios verified and recorded in the change's `design.md`
- [ ] The shadcn-vue guard test runs with `php artisan test`
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
