---
id: T-18
title: Skins y temas intercambiables
status: enriched
discovery: reproductor-musica-privado
change: ~
priority: could
estimate: 7
language: es
---

# T-18 — Skins y temas intercambiables

## Goal

Poder elegir entre cinco temas visuales dentro de la estética Aero, como se cambiaba de skin en WMP, Winamp o el Zune en los 2000. Refuerza la métrica de éxito del discovery "look años 2000 logrado" con esa nostalgia de las skins. El aspecto actual de T-03 es el tema por defecto ("Aero Sky"), y hay cuatro temas más, uno de ellos oscuro. El tema se elige en una página de ajustes "Appearance", se aplica al momento sin recargar la página y se guarda en el servidor, así que se mantiene en cualquier navegador. Los temas solo cambian colores, degradados, sombras y fondo. No cambian la forma ni la disposición de los componentes.

## Context

Extra deseable del discovery ("Skins o temas intercambiables, por ejemplo variantes de color Aero"), para después de la v1. Se construye sobre T-03.

Dependencias:
- **T-03:** deja los tokens como propiedades CSS `--aero-*`, para que esta tarea cree temas redefiniéndolos. También deja:
  - `AeroBackground` con su degradado de reserva.
  - La reserva sin cristal (clase `aero-no-blur` en `<html>`).
  - La regla de contraste de 4,5:1 y la regla global de movimiento reducido.
  - El registro de procedencia de recursos.
  - La página `/styleguide`, que se mantiene como referencia para esta tarea.
  - La skill `frutiger-aero` con su test de tokens frente a skill.
  - Los tests de navegador con Pest 4.

  T-03 deja la app fija en modo claro: neutraliza la clase `.dark` del starter kit, y los componentes Aero la ignoran. Las variantes oscuras quedaron para esta tarea.
- **T-21:** controles del reproductor (`AeroSlider`, `AeroPlayerBar`, `AeroCover`). Usan los tokens, así que siguen el tema sin cambios.
- **T-02:** quitó todas las páginas de ajustes. Uno de sus escenarios exige que `/settings/appearance` devuelva 404. Esta tarea recupera esa ruta para el selector de temas (ver "Conflicto con T-02").
- **T-05:** crea la página `/settings/spotify`. No crea un layout de ajustes, ni una subnavegación, ni la redirección de `/settings`. Esta tarea añade "Appearance" como segunda página de ajustes y, si todavía no existe, una subnavegación mínima de ajustes ("Spotify" | "Appearance") compartida por las dos páginas.
- **T-25:** crea la navegación Aero de toda la app (`AeroAppNav`), con el enlace "Settings" que lleva a `/settings/spotify`. Desde ahí se llega a "Appearance" con la subnavegación de ajustes.
- **Consumidores del tema:** T-16 (visualizador) reacciona al cambio de tema con el contrato de "Contrato del cambio de tema" (Reglas propuestas). T-21, T-25 y el resto de componentes siguen el tema solo con CSS, a través de los tokens. Ver Casos límite.

Temas de esta tarea (decisión D10; los nombres y los identificadores son una propuesta):

| Identificador | Nombre en la UI | Inspiración |
|---|---|---|
| `sky` (por defecto) | Aero Sky | El aspecto actual de T-03: cielo azul Aero |
| `green` | Aero Green | Hierba y verde lima |
| `aqua` | Aero Aqua | Agua, turquesa, bajo el agua y burbujas |
| `orchid` | Aero Orchid | Rosa del Zune y cristal púrpura de Vista |
| `graphite` | Aero Graphite | Negro del Zune y WMP 11 oscuro. Es la primera variante oscura |

## Acceptance criteria

```gherkin
# --- Página "Appearance" -------------------------------------------------------

Scenario: Página de apariencia con los cinco temas
  Given el usuario con sesión iniciada y el tema "Aero Sky" activo
  When pide "/settings/appearance"
  Then la respuesta tiene estado 200
  And la respuesta Inertia indica el componente "settings/Appearance"
  And la página muestra un grupo de opciones "Theme" con "Aero Sky", "Aero Green", "Aero Aqua", "Aero Orchid" y "Aero Graphite", en ese orden
  And cada opción tiene una muestra (data-aero="theme-swatch") con data-theme igual a su identificador
  And los background-image calculados de las cinco muestras son distintos entre sí
  And los colores de fondo calculados de los cinco mini paneles (data-aero="theme-swatch-panel") son distintos entre sí
  And la opción "Aero Sky" está marcada y las demás no

Scenario Outline: Un visitante sin sesión no ve ni cambia la apariencia
  Given un visitante sin sesión
  And el usuario propietario tiene guardado el tema "green"
  When envía <método> a "/settings/appearance" con theme "aqua"
  Then se le redirige a "/login"
  And el tema guardado del propietario sigue siendo "green"

  Examples:
    | método |
    | GET    |
    | PATCH  |

Scenario: Se pasa de una página de ajustes a otra
  Given el usuario con sesión iniciada en "/settings/spotify"
  When pulsa "Appearance" en la subnavegación de ajustes (data-aero="settings-nav")
  Then está en "/settings/appearance" y "Appearance" aparece como la página actual (aria-current="page")
  When pulsa "Spotify" en la misma subnavegación
  Then está en "/settings/spotify" y "Spotify" aparece como la página actual

# --- Cambiar de tema -----------------------------------------------------------

Scenario: Cambiar de tema
  Given el usuario con sesión iniciada, el tema "Aero Sky" activo y "/settings/appearance" abierta
  And una marca puesta en window antes de elegir
  And un MutationObserver sobre el atributo data-theme de "<html>"
  When elige "Aero Green"
  Then "<html>" tiene data-theme="green"
  And el MutationObserver ha registrado el cambio a "green"
  And la marca de window sigue existiendo, es decir, la página no se ha recargado
  And el color de fondo calculado de los paneles de cristal es distinto del que tenían con "Aero Sky"
  And el tema guardado del usuario es "green"
  And la página anuncia "Theme saved." en una región aria-live

Scenario: El tema se ve en toda la app
  Given el usuario ha elegido "Aero Aqua"
  When navega a "/dashboard" y a "/styleguide" con los enlaces de la app
  Then en cada página "<html>" tiene data-theme="aqua"
  And, con la clase "aero-no-blur" forzada en "<html>", el color de fondo calculado de los paneles de "/styleguide" es igual al del mini panel (data-aero="theme-swatch-panel") de "Aero Aqua" en "/settings/appearance"

Scenario: El tema elegido se recuerda
  Given el usuario ha elegido "Aero Graphite" en un navegador
  When cierra sesión y vuelve a entrar desde otro navegador sin cookies de la app
  Then tras el login, sin recargar la página, "<html>" tiene data-theme="graphite"

Scenario: La primera carga ya trae el tema, sin destello del tema por defecto
  Given el usuario con sesión iniciada y el tema "green" guardado
  When pide "/dashboard" con una carga completa de página (sin la cabecera X-Inertia)
  Then el HTML de la respuesta ya tiene data-theme="green" en la etiqueta "<html>"

Scenario: Las páginas de invitado vuelven al tema por defecto
  Given el usuario con el tema "orchid" guardado y sesión iniciada
  When cierra sesión con el menú de la app y después abre "/login" con un enlace, sin recargar la página
  Then "<html>" tiene data-theme="sky" en cada página de invitado
  And al abrir "/login" con una carga completa, "<html>" también tiene data-theme="sky"

Scenario: Elegir dos temas seguidos sin esperar
  Given el usuario en "/settings/appearance" con "Aero Sky" activo
  And el guardado del tema tarda 1 segundo en responder
  When elige "Aero Green" e inmediatamente "Aero Aqua"
  Then "<html>" tiene data-theme="aqua" al momento
  And el segundo guardado no se envía hasta que ha respondido el primero
  And, cuando terminan los guardados, la opción marcada es "Aero Aqua" y el tema guardado del usuario es "aqua"

Scenario: Elegir tema con el teclado
  Given el usuario en "/settings/appearance" con "Aero Sky" activo
  When lleva el foco con Tab hasta la opción marcada del grupo "Theme" y pulsa la flecha derecha
  Then la opción "Aero Green" queda marcada y enfocada, con un indicador de foco visible
  And "<html>" tiene data-theme="green"

# --- Errores y concurrencia ----------------------------------------------------

Scenario Outline: Un valor de tema no válido no se guarda
  Given el usuario con sesión iniciada y el tema "green" guardado
  When envía PATCH a "/settings/appearance" como petición JSON con <valor>
  Then la respuesta tiene estado 422
  And el campo "theme" tiene un único error: "<mensaje>"
  And el tema guardado sigue siendo "green"

  Examples:
    | valor                                 | mensaje                            |
    | theme "neon"                          | The selected theme is invalid.     |
    | theme "SKY" (mayúsculas)              | The selected theme is invalid.     |
    | theme "" (llega como null al servidor) | The theme field is required.       |
    | sin el campo theme                    | The theme field is required.       |
    | theme como lista ["sky"]              | The theme field must be a string.  |

Scenario Outline: Falla el guardado
  Given el usuario en "/settings/appearance" con "Aero Sky" activo
  And el guardado del tema <fallo>
  When elige "Aero Green"
  Then "<html>" vuelve a tener data-theme="sky" y la opción "Aero Sky" vuelve a estar marcada
  And la página muestra "Could not save your theme. Please try again."
  And no se abre la ventana de error de Inertia
  And el tema guardado sigue siendo "sky"

  Examples:
    | fallo                         |
    | responde con un error 500     |
    | falla por un error de red     |

Scenario: Falla un guardado con otra elección pendiente
  Given el usuario en "/settings/appearance" con "Aero Sky" activo
  And el primer guardado del tema responde con un error 500 y el siguiente funciona
  When elige "Aero Green" e inmediatamente "Aero Aqua"
  Then, cuando terminan los guardados, "<html>" tiene data-theme="aqua" y el tema guardado es "aqua"
  And no se muestra "Could not save your theme. Please try again."

Scenario Outline: El guardado responde 419
  Given el usuario en "/settings/appearance" con "Aero Sky" activo
  And <situación>
  When elige "Aero Green"
  Then el guardado responde con estado 419 y la página se recarga por completo
  And tras la recarga está en <destino>
  And el tema guardado sigue siendo "sky"

  Examples:
    | situación                                                         | destino                                                   |
    | su sesión se ha cerrado en el servidor (se borran sus cookies)     | "/login", con data-theme="sky"                             |
    | su sesión sigue viva, pero el token CSRF de la página ya no vale   | "/settings/appearance", con "Aero Sky" marcada y activa    |

Scenario Outline: Tema guardado que ya no existe
  Given el tema guardado del usuario es <valor guardado>
  When el usuario abre "/dashboard" y después "/settings/appearance"
  Then las dos respuestas tienen estado 200
  And "<html>" tiene data-theme="sky"
  And la opción marcada es "Aero Sky"

  Examples:
    | valor guardado                          |
    | "retired-theme" (ya no existe en la app) |
    | ninguno (nunca ha elegido tema)          |

Scenario: El botón Atrás del navegador no deshace el tema guardado
  Given el usuario con sesión iniciada, Aero Sky activo, en "/library"
  When abre "/settings/appearance", elige "Aero Green", espera a que el guardado responda y pulsa el botón Atrás del navegador
  Then "<html>" tiene data-theme="green" en "/library"
  And al recargar la página "<html>" sigue teniendo data-theme="green"

Scenario: Otra pestaña abierta con el tema anterior
  Given dos pestañas de la app abiertas con "Aero Sky"
  When en la primera elige "Aero Aqua"
  Then la segunda sigue con data-theme="sky" mientras no navegue
  When en la segunda pulsa un enlace de la app
  Then la segunda tiene data-theme="aqua" sin recargar la página

# --- Cada tema -----------------------------------------------------------------

Scenario Outline: Todos los temas se leen bien
  Given el usuario con el tema <tema> y la reserva sin cristal forzada con la clase "aero-no-blur" en "<html>"
  When abre "/styleguide" y "/settings/appearance"
  Then el texto de los paneles, incluido el error de ejemplo "This field is required.", tiene un contraste de al menos 4,5:1 con el color del panel
  And al recorrer los controles con Tab, el indicador de foco de cada uno tiene un contraste de al menos 3:1 con el panel

  Examples:
    | tema     |
    | sky      |
    | green    |
    | aqua     |
    | orchid   |
    | graphite |

Scenario Outline: Cada tema tiene su fondo y su degradado de reserva
  Given el usuario con el tema <tema> y la petición de la imagen de fondo bloqueada
  When abre "/styleguide"
  Then el background-image calculado de AeroBackground contiene "linear-gradient"
  And el texto de los paneles tiene un contraste de al menos 4,5:1 con el panel

  Examples:
    | tema     |
    | sky      |
    | green    |
    | aqua     |
    | orchid   |
    | graphite |

Scenario: Los cinco temas se distinguen
  Given "/styleguide" abierta con cada uno de los cinco temas, uno tras otro, con la imagen de fondo bloqueada
  When se leen el color de fondo calculado del panel y el background-image calculado de AeroBackground
  Then los cinco colores de panel son distintos entre sí
  And los cinco degradados de reserva son distintos entre sí

Scenario: Solo se descarga el fondo del tema activo
  Given el usuario con el tema "green"
  When abre "/settings/appearance", que muestra las cinco muestras
  Then el navegador ha pedido la imagen de fondo de "Aero Green"
  And no ha pedido ninguna imagen de fondo que solo usen otros temas

Scenario: El tema oscuro no activa el modo oscuro del starter kit
  Given el usuario con el tema "graphite"
  When abre "/dashboard" y "/styleguide"
  Then "<html>" tiene data-theme="graphite"
  And "<html>" no tiene la clase "dark"

Scenario: Movimiento reducido al cambiar de tema
  Given el navegador con prefers-reduced-motion: reduce y el usuario en "/settings/appearance"
  When elige otro tema
  Then ningún elemento tiene animation-name distinto de "none"
  And las transiciones duran 0,01 s o menos

Scenario: El selector funciona en el móvil
  Given un viewport de 375x667 px
  When se abre "/settings/appearance"
  Then document.documentElement.scrollWidth es menor o igual que 375
  And el área táctil de cada opción de tema y de cada enlace de la subnavegación de ajustes mide al menos 44x44 px

# --- Mecanismo y documentación -------------------------------------------------

Scenario: Los temas solo redefinen tokens
  Given el fichero de tokens de T-03 y los ficheros CSS de los temas
  When se leen sus reglas
  Then cada regla de los ficheros de temas usa el selector [data-theme="<id>"] de un tema (la del tema por defecto, además, ":where(:root)") y solo declara propiedades "--aero-*" o "color-scheme"
  And los cinco temas declaran exactamente el mismo conjunto de tokens
  And ningún token de los temas se declara también en el fichero de tokens de T-03
  And la lista de temas del servidor y los selectores de CSS coinciden: no hay tema sin CSS ni CSS sin tema

Scenario: La skill explica los temas
  Given la skill "frutiger-aero"
  When se lee su contenido
  Then tiene una sección "Themes" que nombra los cinco identificadores y la ruta de los ficheros de temas
  And indica que los componentes toman los colores, los degradados y las sombras solo de los tokens
  And describe el contrato del cambio de tema (el atributo data-theme de "<html>")
  And el test de tokens frente a skill de T-03, ampliado a los ficheros de temas, pasa: la skill nombra cada token del fichero de tokens y de los temas, y ninguno más

@manual
Scenario: El propietario aprueba los cinco temas
  Given "/styleguide" y "/settings/appearance" en marcha en local
  When el propietario los revisa con cada tema
  Then el "design.md" del cambio tiene una sección "Validación visual de temas" con la fecha
  And incluye capturas a 1280 px y a 375 px de las dos páginas con cada tema
  And tiene un veredicto por tema, todos "aprobado"
  And cada corrección que pidió el propietario está marcada con [x]
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **D10, temas:** el tema por defecto es el aspecto actual de T-03 (aquí "Aero Sky"). Esta tarea añade cuatro más: Aero Green (hierba, lima), Aqua / bajo el agua (turquesa, agua, burbujas), rosa / púrpura (rosa del Zune, cristal púrpura de Vista) y oscuro / grafito (negro del Zune, WMP 11 oscuro). En total, cinco temas. El oscuro es la primera variante oscura: T-03 las aplazó a esta tarea.
- **El tema se guarda en el servidor, por usuario** (decisión del usuario del 2026-11-09, en el mismo registro que D10). Por eso el escenario "El tema elegido se recuerda" entra desde otro navegador. No vale una cookie ni `localStorage`.
- **Solo modo claro en la v1** (T-03): se mantiene. El tema oscuro es un tema más (`data-theme="graphite"`), no la clase `.dark` del starter kit, que sigue neutralizada.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **Nombres e identificadores:** los de la tabla del Context. Los nombres de la UI van en inglés ("Appearance", "Theme", "Aero Sky", "Aero Green", "Aero Aqua", "Aero Orchid", "Aero Graphite"). Los identificadores son los valores que se guardan y los del atributo `data-theme`. El propietario puede cambiar los nombres en la revisión (ver Pendiente).
- **Ficheros de tokens y de temas:**
  - El fichero de tokens de T-03 (por ejemplo, `resources/css/aero/tokens.css`) se queda solo con los tokens **fijos** (radios, espaciado, tipografía, desenfoque) en `:root`.
  - Los tokens **temables** (colores, degradados, sombras, imagen de fondo y degradado de reserva) del tema por defecto pasan a `resources/css/aero/themes/sky.css`.
  - Cada tema nuevo tiene su fichero en esa carpeta (`green.css`, `aqua.css`, `orchid.css`, `graphite.css`).
  - Así todos los temas tienen la misma forma, y el test de conjuntos iguales es directo.
  - El test de tokens frente a skill de T-03 se amplía para leer el fichero de tokens más los ficheros de temas.
  - **Tokens que añadan otras tareas:** a partir de T-18, cualquier token temable nuevo (color, degradado, sombra; por ejemplo los `--aero-visualizer-*` de T-16 o los de T-21) va en los cinco ficheros de temas, nunca en `tokens.css`. Si T-16 o T-21 se construyen antes que T-18 y dejan tokens temables en `tokens.css`, T-18 los mueve a `themes/sky.css` y los declara en los demás temas.
- **Selectores:**
  - Cada tema declara sus tokens bajo `[data-theme="<id>"]`.
  - El tema por defecto los declara bajo `:where(:root), [data-theme="sky"]`, para que se apliquen sin `data-theme` (por ejemplo, en una vista sin la vista Blade raíz).
  - `:where()` deja la especificidad de `:root` a cero, así que `[data-theme="green"]` gana sobre él en `<html>` sea cual sea el orden de carga (ver Restricciones).
  - El selector no se limita a `html`, para que cada muestra del selector pueda pintarse con su propio tema (`data-theme` en la muestra).
  - Los componentes no cambian. Nunca usan la clase `.dark`.
  - En los ficheros de temas solo se permiten propiedades `--aero-*` y `color-scheme`. El tema oscuro declara `color-scheme: dark` para que las barras de desplazamiento y los controles nativos se vean oscuros.
- **Cada tema declara todos los tokens temables**, no solo los que cambia. Así una muestra con `data-theme` que está dentro de una página con otro tema (por ejemplo, la muestra de "Aero Green" mientras la página está en "Aero Graphite") toma todos sus colores de su propio tema y no hereda ninguno del tema de la página.
- **Imagen de fondo por tema:** es un token (por ejemplo, `--aero-background-image`) junto con su degradado de reserva (`--aero-background-fallback`). La imagen se anota en el registro de procedencia de T-03 y pesa menos de 400 KB en WebP o AVIF, como la de T-03.
- **Muestras del selector:**
  - Cada opción tiene una muestra (`data-aero="theme-swatch"`, con `data-theme="<id>"`) cuyo fondo es el degradado de reserva de su tema.
  - Dentro va un mini panel (`data-aero="theme-swatch-panel"`) con el color de panel de la reserva sin cristal de su tema, un botón pequeño con sus colores, y el nombre del tema.
  - Las muestras no cargan la imagen de fondo completa, así que en la página solo se descarga la imagen del tema activo.
- **Lista de temas en el servidor:** una sola fuente de verdad en PHP (por ejemplo, un enum `App\Enums\Theme` o `config/aero.php`) con el identificador, el nombre y cuál es el tema por defecto. Valida el guardado, resuelve el tema activo y alimenta el selector. Un test comprueba que coincide con los ficheros CSS.
- **Almacenamiento:**
  - Una columna `theme` en `users`, de tipo cadena y que admite nulo. Nulo significa que el usuario nunca ha elegido tema, y se usa el tema por defecto.
  - Se lee y se escribe a través del `UserRepository`, una clase concreta en `app/Repositories/`: la que deje T-02, o se crea aquí. Sin interfaz.
  - No hace falta una tabla de preferencias para un solo campo de un solo usuario.
- **Resolución del tema activo:**
  - Con sesión, se usa el tema guardado si existe en la lista. Si no existe o es nulo, se usa `sky`.
  - Sin sesión (login y demás páginas de invitado), siempre `sky`.
  - Un valor guardado que ya no existe **no se borra ni se corrige al leerlo**: el siguiente guardado lo sustituye.
- **Sin destello del tema por defecto:** la vista Blade raíz pinta `<html data-theme="...">` con el tema activo resuelto en el servidor. Así el primer pintado ya trae el tema.
- **Prop compartida `appearance.theme` y un único gancho en toda la app:**
  - Las props compartidas de Inertia exponen el tema activo ya resuelto, siempre un identificador válido.
  - Un único gancho en `resources/js/app.ts` escucha el evento `navigate` del router de Inertia, que salta en cada visita correcta. Lee `appearance.theme` y actualiza `data-theme` de `<html>`, **solo si el valor es distinto**.
  - No hay un gancho por layout. Así la sincronización vale igual para el layout de la app y para el de invitado: tras el login, tras el logout (vuelve a `sky`) y en otra pestaña cuando navega.
  - **Visitas del historial (Atrás/Adelante):** Inertia restaura las props de la entrada del historial sin preguntar al servidor, así que pueden traer un `appearance.theme` antiguo. En esas visitas el gancho **no aplica** el tema restaurado: se queda el último tema aplicado o guardado por el cliente hasta la siguiente respuesta del servidor. El gancho distingue la visita del historial por el evento `popstate` previo (o el indicador que dé la versión de Inertia instalada; se comprueba en el diseño).
- **Contrato del cambio de tema:**
  - La única señal pública de que el tema ha cambiado es el atributo `data-theme` de `<html>`. Solo se escribe cuando cambia el valor, así que cada escritura es un cambio real.
  - El CSS reacciona solo, a través de los tokens.
  - El código que pinta por su cuenta (por ejemplo, el canvas del visualizador de T-16) observa ese atributo con un `MutationObserver` (`attributeFilter: ['data-theme']`) y vuelve a leer los tokens con `getComputedStyle(document.documentElement)`.
  - No hace falta un evento propio. Si en el diseño se añade uno, es un extra que no sustituye al atributo.
  - Consumidores conocidos: T-16.
- **Ruta y página:**
  - `GET /settings/appearance` muestra el componente Inertia `settings/Appearance`.
  - `PATCH /settings/appearance` guarda el campo `theme` con las reglas `bail`, `required`, `string` e `in:<lista de temas>`. `bail` hace que cada valor no válido dé un único error. El valor distingue mayúsculas.
  - Las dos rutas llevan el middleware `auth`.
  - Esta tarea no toca la ruta `/settings`: su redirección es un seguimiento para T-05.
- **Subnavegación de ajustes:**
  - Si cuando se haga esta tarea no hay subnavegación de ajustes (T-05 no la crea), T-18 crea una mínima (`data-aero="settings-nav"`) con los enlaces "Spotify" (`/settings/spotify`) y "Appearance" (`/settings/appearance`), en ese orden, y la pone en las dos páginas.
  - La página actual se marca con `aria-current="page"`.
  - Se hace con los componentes Aero y la skill. No es un layout de ajustes completo.
  - El enlace "Settings" de la navegación principal es de T-25.
- **Aplicar sin recargar:**
  - Las opciones son un grupo de radios nativos (`<input type="radio">`, como `AeroCheckbox` en T-03), sin botón "Save". El teclado (flechas) y la accesibilidad vienen de serie.
  - Al elegir una opción, el tema se aplica al momento: se cambia `data-theme` de forma optimista y se guarda.
  - Si el guardado funciona, se anuncia "Theme saved." en una región `aria-live="polite"`.
- **Guardados de uno en uno:**
  - Nunca hay dos guardados en vuelo a la vez. Cancelar una petición en el cliente no impide que el servidor la procese, y entonces un "green" que llega tarde podría pisar a "aqua".
  - Mientras hay un guardado en vuelo, solo se recuerda la **última** elección pendiente (las intermedias se descartan). Cuando el guardado en vuelo termina, bien o mal, se envía la pendiente si es distinta de lo último guardado.
  - El tema de la pantalla siempre es la última elección.
  - Si falla un guardado y hay otra elección pendiente, no se revierte nada: se envía la pendiente.
  - Si falla el último guardado (sin nada pendiente), se vuelve al último tema guardado con éxito y se muestra "Could not save your theme. Please try again.".
- **Errores al guardar:**
  - **500 o error de red:** se revierte y se muestra el mensaje, sin la ventana de error de Inertia.
  - **419** (token CSRF que no vale, normalmente porque la sesión ha caducado): se revierte y se recarga la página por completo. Si la sesión sigue viva, la recarga trae un token nuevo y muestra el tema guardado. Si no, el middleware `auth` lleva a `/login`. En el móvil, una pestaña que vuelve tras horas en segundo plano es el caso típico.
  - **Validación (422):** desde la UI no puede ocurrir, porque las opciones son fijas. Si ocurre, se trata como un fallo de guardado.
- **Dos pestañas:** no hay sincronización en tiempo real (ni broadcasting ni evento `storage`). La otra pestaña recibe el tema nuevo en su siguiente visita de Inertia o al recargar.
- **Cambio de tema instantáneo:** sin fundido animado entre temas. Si en la revisión se añade uno, sigue la regla global de movimiento reducido de T-03.
- **Contraste:**
  - El texto mantiene 4,5:1 sobre el panel con la reserva sin cristal, igual que en T-03, en los cinco temas.
  - Además, se propone 3:1 para el indicador de foco frente al panel (WCAG 1.4.11, contraste de elementos no textuales). Es especialmente necesario en Graphite, donde un anillo de foco claro pensado para fondos claros se puede perder.
- **Textos de la UI** (inglés): "Appearance", "Theme", "Spotify", los cinco nombres, "Theme saved." y "Could not save your theme. Please try again.". Los mensajes de validación son los de `lang/en/validation.php`: "The selected theme is invalid.", "The theme field is required." y "The theme field must be a string.".

**Conflicto con T-02**

- T-02 tiene en su tabla de rutas que devuelven 404 la fila `GET /settings/appearance`. Esta tarea recupera esa ruta: en `/opsx-propose`, el delta de spec de T-18 debe **modificar** el requisito de T-02 (MODIFIED), quitar esa fila del dataset de tests de T-02 y añadir sus propios escenarios.
- Lo mismo con la nota de T-02 "no queda ninguna página de ajustes": deja de ser cierta con T-05 y con esta tarea. Se devuelve como seguimiento.

**Restricciones descubiertas**

- **Las propiedades personalizadas se resuelven donde se declaran.** Si un tema solo redefiniera los tokens base (por ejemplo, `--aero-sky-200`), un token derivado declarado más arriba (`--aero-panel: var(--aero-sky-200)`) llegaría ya resuelto con los valores de ese nivel y no cambiaría. Es otra razón para que cada tema declare todos sus tokens temables con valores propios.
- **Especificidad de `:root` frente a `[data-theme]`:** las dos valen (0,1,0). Si el tema por defecto se declarara en `:root` sin más, ganaría la regla que se cargue la última, y un tema podría no aplicarse según el orden de los `@import` o del bundle. Con `:where(:root)` el resultado no depende del orden.
- **CSRF va antes que `auth`:** en el grupo `web`, `VerifyCsrfToken` se ejecuta antes que el middleware `auth` de la ruta. Si la sesión ya no existe, el token de la página no coincide y un PATCH recibe **419**, no una redirección a `/login`. Por eso el 419 tiene su propia regla (recargar).
- **Inertia solo pinta la vista Blade raíz en la carga completa.** El login, el logout y la navegación son visitas XHR, así que `data-theme` se mantiene al día en el cliente con la prop compartida y el gancho de `app.ts`.
- **Errores de servidor en Inertia:** ante una respuesta que no es de Inertia (un 500 o un 419 con HTML), Inertia abre por defecto una ventana con la respuesta. Para revertir y mostrar el mensaje propio hay que capturarlo: con el evento `invalid` o `exception` de la visita, o haciendo el guardado con una petición HTTP normal que devuelva 204. Se comprueba en el diseño con la versión instalada de Inertia. Como los guardados van en cola, una petición HTTP normal es probablemente lo más sencillo.
- **La validación de Inertia no da 422 en el navegador:** una visita Inertia con un error de validación redirige atrás con el error en la sesión. El 422 de los escenarios se prueba con una petición JSON (`patchJson`).
- **Cadena vacía:** el middleware `ConvertEmptyStringsToNull` de Laravel convierte `""` en `null` antes de validar. Por eso `theme ""` da el mismo error que "sin el campo".
- **`color-scheme` no es un token:** por eso es la única excepción a "solo `--aero-*`" en los ficheros de temas.

**Casos límite**

- **Pantallas shadcn-vue aún no migradas** (dashboard y otras, hasta T-25): no usan los tokens `--aero-*`, así que con Graphite siguen claras, aunque el fondo de la app sea oscuro. Mezclar estilos un tiempo es aceptable por la decisión de T-03 de retirarlos poco a poco. Cada pantalla que migra T-25, y su `AeroAppNav`, sigue el tema.
- **Página de inicio "/"** (`Welcome` del starter kit, sin estilo Aero, T-03): no cambia con el tema.
- **Visualizador (T-16):** pinta en un canvas, así que no lo cambia el CSS. Sigue el contrato del cambio de tema: observa `data-theme` con un `MutationObserver` y vuelve a leer los tokens. En Graphite tiene que seguir viéndose sobre fondo oscuro.
- **Carátula de reserva (T-21):** si toma sus colores de los tokens, sigue el tema. Si tiene colores fijos (por ejemplo, el degradado de cielo), en Graphite desentona. Se devuelve como seguimiento.
- **Autocompletado del navegador en Graphite:** el fondo de autofill no debe dejar texto claro sobre fondo claro en `AeroInput`. Lo cubre en parte el `color-scheme: dark`, y se revisa en la validación del propietario.
- **Tema guardado con un valor raro** (de una versión anterior o editado en la base de datos): se usa el de por defecto y no hay error 500 (escenario "Tema guardado que ya no existe").
- **Volver a pulsar la opción ya marcada:** un radio marcado no emite `change`, así que no hay guardado ni cambio visible.
- **Cerrar la pestaña justo después de elegir:** si el guardado no llegó, al volver se ve el tema anterior. Es aceptable.

**Pistas para los tests**

- **Feature tests de Pest:**
  - `GET` y `PATCH /settings/appearance` con `actingAs`: 200, componente Inertia y props (lista de temas y tema activo).
  - Sin sesión, redirección a `/login`. Laravel desactiva la comprobación CSRF cuando se ejecutan tests, así que el PATCH sin sesión llega a `auth` y redirige. En el navegador real ese caso da 419 (ver Restricciones), y lo cubre el escenario del 419.
  - `patchJson` con los valores no válidos del Scenario Outline: 422 y `assertJsonValidationErrors(['theme' => ...])`, y que `errors.theme` tiene un solo mensaje. El tema guardado se comprueba con `assertDatabaseHas`.
  - Primera carga: `get('/dashboard')` sin cabecera `X-Inertia` y buscar `data-theme="green"` en la etiqueta `<html>` del HTML.
  - Tema que ya no existe: guardar `retired-theme` con una actualización directa en la base de datos (los tests pueden usar el modelo) y comprobar la prop compartida y el HTML.
- **Tests de navegador (Pest 4):**
  - Se lee `document.documentElement.dataset.theme` y se comparan estilos calculados localizando los elementos por `data-aero` (`theme-swatch`, `theme-swatch-panel`, `settings-nav` y los de T-03).
  - "Sin recargar": se pone `window.__marker = 1` antes de elegir y se comprueba que sigue existiendo. En el escenario del 419 se comprueba lo contrario: la marca desaparece.
  - El contrato se prueba instalando un `MutationObserver` desde el test que guarda en `window` los valores de `data-theme` que ve.
  - El 500, el fallo de red, el 419 con sesión viva y el retraso de 1 segundo se simulan interceptando el PATCH con el enrutado de peticiones de Playwright. Ahí mismo se cuentan los PATCH y se comprueba que el segundo sale después de la respuesta del primero.
  - "Sesión cerrada": se borran las cookies de la app en el contexto del navegador antes de elegir; el PATCH real responde 419.
  - Dos pestañas: dos páginas del mismo contexto del navegador.
  - Descargas: se escuchan las peticiones de la página y se comparan con las imágenes de fondo de cada tema, sacadas de sus ficheros CSS.
- **Contraste:** se reutiliza la utilidad de contraste de T-03 (colores calculados con la reserva forzada con `aero-no-blur`). Para el foco, se compara `outline-color` (o el color del anillo) con el fondo del panel.
- **Mecanismo:**
  - Un test de Pest lee los ficheros de temas y comprueba que cada declaración es `--aero-*` o `color-scheme`.
  - Extrae los nombres de los tokens de cada tema, compara los conjuntos entre sí y comprueba que no se solapan con los del fichero de tokens.
  - Compara la lista de temas del servidor con los selectores `[data-theme="..."]`.
  - Basta con expresiones regulares sobre el CSS fuente; no hace falta un parser.
- **Procedencia:** el test de T-03 que recorre las carpetas de recursos ya cubre las imágenes nuevas, siempre que vayan en esas carpetas.
- **Escenario `@manual`:** la validación del propietario. Se verifica a mano y se anota en el `design.md` del cambio.

**Fuera de alcance**

- Skins que cambian la forma o la disposición de los componentes, al estilo Winamp clásico. Aquí solo cambian los tokens.
- Temas creados por el usuario, o un selector de color libre.
- Elegir el tema automáticamente según la hora o `prefers-color-scheme` (por ejemplo, Graphite cuando el sistema está en oscuro).
- Recordar el tema en las páginas de invitado (login) con una cookie: sin sesión siempre se usa el tema por defecto.
- Sincronizar el tema en tiempo real entre pestañas o dispositivos abiertos.
- `<meta name="theme-color">` por tema, para el color de la barra del navegador en el móvil.
- Un layout de ajustes completo, la redirección de `/settings` (T-05) y el enlace "Settings" de la navegación principal (T-25).
- Migrar las pantallas shadcn-vue que quedan (T-25).

**Pendiente**

- **Colores exactos de cada tema** (paneles, acentos, degradados, sombras y degradado de reserva): los fija la revisión del propietario, cumpliendo el contraste.
- **Imágenes de fondo de cada tema:** las elige el propietario, como en T-03, y se anotan en el registro de procedencia. Si dos temas comparten la misma imagen, el escenario "Solo se descarga el fondo del tema activo" se comprueba por fichero: no se pide ninguna imagen que no use el tema activo. Si un tema no tiene imagen (solo degradado), el escenario comprueba que no se pide ninguna imagen de fondo.
- **Nombres definitivos de los temas** (sobre todo "Aero Orchid" para rosa / púrpura y "Aero Graphite" para el oscuro): son una propuesta.
- **Contenido de las muestras:** si basta con el degradado y los colores, o si el propietario quiere miniaturas de las imágenes de fondo. En ese caso, la regla "Solo se descarga el fondo del tema activo" se cambia por un límite de peso de las miniaturas.

**Tamaño y posible división**

Se mantiene como una sola tarea porque el mecanismo, el selector y los temas se prueban juntos. Si en `/opsx-propose` resulta demasiado grande para un cambio, se puede dividir en **T-18a — Mecanismo de temas y página "Appearance"**, con "Aero Sky" y "Aero Green" (unos 4,5 puntos), y **T-18b — Temas Aqua, Orchid y Graphite** (unos 2,5 puntos).

**Estimación: 7 puntos** (antes 6; sube por la subnavegación de ajustes, la cola de guardados y el 419).
- **Lista de temas, columna `theme`, `UserRepository`, rutas, validación, prop compartida y `data-theme` en la vista raíz (1 punto):** guardar y leer un campo, con la resolución del tema por defecto.
- **Página "Appearance" con muestras y subnavegación de ajustes, aplicación optimista, cola de guardados, reversión, recarga ante el 419 y gancho de sincronización en `app.ts` (2 puntos):** la cola y la captura de errores de Inertia añaden casos.
- **Separar los tokens temables y fijos, y los cuatro temas nuevos con su fondo, su reserva y su procedencia (1,5 puntos):** ajustar el contraste, sobre todo en Graphite, lleva prueba y error.
- **Tests de feature y de navegador (contraste y fondo por tema, cola, 419, descargas, dos pestañas, móvil, mecanismo), test de tokens frente a skill ampliado y sección "Themes" de la skill (2 puntos).**
- **Ronda de revisión del propietario y correcciones (0,5 puntos).**

**Seguimiento de spotify-connection (T-05, 2026-11-14)**

- `/settings` redirige a `/settings/spotify`.
- La página de Spotify es Aero y tiene un enlace "Back" a `/dashboard`. El menú de usuario tiene un elemento "Settings". Las dos cosas se quedan así hasta que existan la subnavegación de ajustes (Spotify | Appearance) de esta tarea y la navegación Aero de T-25.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] `@manual` scenarios verified and recorded in the change's `design.md`
- [ ] New background images listed in the provenance register (T-03)
- [ ] Spec deltas archived in openspec/specs/ (including the MODIFIED T-02 requirement for `/settings/appearance`)
- [ ] Work committed on main
