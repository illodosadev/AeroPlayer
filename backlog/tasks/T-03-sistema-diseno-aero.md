---
id: T-03
title: Sistema de diseño Frutiger Aero — base y login
status: enriched
discovery: reproductor-musica-privado
change: aero-design-system
priority: must
estimate: 5
language: es
---

# T-03 — Sistema de diseño Frutiger Aero — base y login

## Goal

Crear la base del sistema de diseño Frutiger Aero de la app con cinco piezas: una skill de Claude con las guías de estilo, los tokens de diseño (colores, degradados, sombras, radios, desenfoque, tipografía), los componentes Vue Aero básicos, una página de muestra y la configuración de los tests de navegador. Además, la página de login se reestiliza, para que la app se vea Aero desde el login. Es necesario porque "look años 2000 logrado" es una de las métricas de éxito del proyecto. Con una base común, cada pantalla posterior sale con la misma estética sin reinventarla, y la skill hace que Claude genere UI coherente con ella en las tareas siguientes. Los controles propios del reproductor (sliders, barra y carátula) van en T-21.

## Context

Rasgos clave del discovery:
- **Glossy / cristal:** botones brillantes y transparencias Aero Glass.
- **Naturaleza y cielo:** cielo azul, agua, burbujas, hierba.
- **Reproductores clásicos como inspiración:** WMP 11, iTunes 7–9, Zune, Winamp.
- **Skeuomorfismo:** relieve, reflejos y sombras.

Depende de T-01 y de T-02, porque la página de muestra requiere sesión iniciada y el login que se reestiliza es el que deja T-02. T-02 mantiene "Remember me" como una casilla desmarcada. T-01 usa el starter kit oficial de Laravel con Vue, que trae Tailwind y shadcn-vue. **T-21** (controles del reproductor Aero) depende de esta tarea. Las tareas con interfaz posteriores usan estos componentes y esta skill.

Conjunto mínimo de componentes de esta tarea y por qué cada uno:
- **Botón glossy (`AeroButton`)**, en forma rectangular y redonda (orbe), con los estados normal, hover, pulsado y deshabilitado. Es el control más visible del estilo (gel brillante). El orbe es la base del botón de play de T-21. Lo usa el login.
- **Panel de cristal (`AeroGlassPanel`)**, con transparencia, desenfoque y reflejo. Es el rasgo Aero Glass y el contenedor del formulario de login, de las listas y de la barra de T-21.
- **Campo de texto (`AeroInput`)**. Lo necesitan el login y la búsqueda (T-07, T-11).
- **Casilla (`AeroCheckbox`)**. La necesita la casilla "Remember me" del login.
- **Fondo de cielo/naturaleza (`AeroBackground`)**, la capa de fondo de toda la app.
- **Login reestilizado** con los componentes anteriores. Es la primera pantalla Aero que se ve.

Los sliders, la barra de reproductor y la carátula de reserva van en T-21. Las listas de canciones, las pestañas, los menús y los diálogos los crea cada tarea cuando los necesite, siguiendo la skill.

**Tests de navegador (regla propuesta, la decide esta tarea):** Pest 4 browser testing con Playwright y Chromium, ejecutado con `php artisan test`. T-03 deja el runner configurado y lo usan T-21, T-06, T-07 y las tareas siguientes.

## Acceptance criteria

```gherkin
# --- Skill de diseño ----------------------------------------------------------

Scenario: La skill de diseño Aero existe en el repositorio
  Given el repositorio tras completar la tarea
  When se abre ".claude/skills/frutiger-aero/SKILL.md"
  Then el fichero existe y su frontmatter tiene "name" y "description"
  And la descripción indica que se usa al crear o modificar interfaz de la app

@manual
Scenario: La skill aparece en el listado de skills de Claude Code
  Given una sesión nueva de Claude Code abierta en el repositorio
  When se listan las skills disponibles
  Then aparece la skill "frutiger-aero"

Scenario: La skill cubre los cuatro rasgos, los componentes y los tokens
  Given la skill "frutiger-aero"
  When se lee su contenido
  Then tiene una sección para cada rasgo: glossy / cristal, naturaleza y cielo, reproductores clásicos y skeuomorfismo
  And nombra AeroButton, AeroGlassPanel, AeroInput, AeroCheckbox y AeroBackground con la ruta de su fichero
  And cada token definido en el fichero de tokens de la app aparece en la skill, y la skill no nombra tokens que no existan
  And incluye las reglas de accesibilidad: foco visible, movimiento reducido, reserva sin backdrop-filter, contraste mínimo y área táctil mínima

# --- Página de muestra --------------------------------------------------------

Scenario: Página de muestra con los componentes base
  Given el usuario con sesión iniciada
  When pide "/styleguide"
  Then la respuesta tiene estado 200
  And la respuesta Inertia indica el componente "Styleguide"
  And la página muestra las secciones "Buttons", "Glass panel", "Inputs" y "Checkbox" sobre el fondo de cielo/naturaleza

Scenario: Un visitante sin sesión no ve la página de muestra
  Given un visitante sin sesión
  When pide "/styleguide"
  Then se le redirige a "/login"

# --- Botón glossy -------------------------------------------------------------

Scenario: El botón muestra sus cuatro estados uno al lado de otro
  Given el usuario con sesión iniciada en "/styleguide"
  When mira la sección "Buttons"
  Then ve el botón rectangular y el orbe en los estados "Normal", "Hover", "Pressed" y "Disabled", cada uno con su etiqueta
  And cada estado tiene un fondo calculado (background-image o box-shadow) distinto de los demás

Scenario: Hover y pulsado reales cambian el aspecto del botón
  Given el botón interactivo de la sección "Buttons"
  When el puntero pasa por encima y después se mantiene pulsado el botón del ratón
  Then el estilo calculado con el puntero encima es distinto del estado normal
  And el estilo calculado al mantener pulsado es distinto del de hover

Scenario: El botón habilitado responde al clic
  Given la sección "Buttons" con el contador "Clicks: 0"
  When se hace clic en el botón interactivo
  Then el contador muestra "Clicks: 1"

Scenario: El botón deshabilitado no se puede usar
  Given la sección "Buttons" con el contador "Disabled clicks: 0" asociado al botón en estado "Disabled"
  When se hace clic en el botón deshabilitado y se recorre la página con Tab
  Then el botón tiene el atributo "disabled"
  And no recibe el foco con Tab
  And el contador sigue mostrando "Disabled clicks: 0"

# --- Panel de cristal ---------------------------------------------------------

Scenario: El panel de cristal deja ver el fondo
  Given un navegador con soporte de backdrop-filter
  When se abre "/styleguide"
  Then el panel de la sección "Glass panel" tiene un backdrop-filter calculado distinto de "none"
  And su color de fondo calculado tiene una opacidad menor que 1

Scenario: Navegador sin soporte de desenfoque de fondo
  Given un navegador sin soporte de backdrop-filter, o con la reserva forzada con la clase "aero-no-blur" en <html>
  When se abre "/styleguide"
  Then el backdrop-filter calculado de los paneles es "none"
  And el color de fondo calculado de los paneles tiene una opacidad de al menos 0,85
  And el texto de los paneles tiene un contraste de al menos 4,5:1 con el color del panel

# --- Campo y casilla ----------------------------------------------------------

Scenario: El campo de texto acepta escritura y muestra su error
  Given la sección "Inputs" con un campo "Email" y un campo con el error de ejemplo "This field is required."
  When se escribe "owner@example.com" en el campo "Email"
  Then el valor del campo es "owner@example.com"
  And el campo con error tiene aria-invalid="true" y el mensaje está enlazado con aria-describedby

Scenario: La casilla se marca y se desmarca con el ratón y con el teclado
  Given la sección "Checkbox" con una casilla desmarcada y otra marcada
  When se hace clic en la casilla desmarcada, y después se enfoca con Tab y se pulsa Espacio
  Then tras el clic la casilla está marcada (checked y aria-checked "true", o checkbox nativo marcado)
  And tras pulsar Espacio vuelve a estar desmarcada
  And los estados marcado y desmarcado tienen un aspecto calculado distinto

Scenario: El área táctil de la casilla es suficiente
  Given la casilla de la sección "Checkbox", junto con su etiqueta
  When se mide su área clicable
  Then mide al menos 44x44 px
  And hacer clic en la etiqueta cambia el estado de la casilla

# --- Login reestilizado -------------------------------------------------------

Scenario: El login se ve Aero
  Given un visitante sin sesión
  When abre "/login"
  Then la página usa AeroBackground, un AeroGlassPanel, AeroInput para "Email" y "Password", un AeroCheckbox "Remember me" desmarcado y un AeroButton "Log in" (localizados por data-aero)
  And la página no tiene ningún elemento con el atributo "data-slot"
  And "Login.vue" no importa nada de "@/components/ui"

Scenario: El error de credenciales se lee en el login Aero
  Given el usuario propietario existe y no tiene sesión
  When envía una contraseña incorrecta en "/login"
  Then el mensaje "These credentials do not match our records." se muestra dentro del panel de cristal
  And su texto tiene un contraste de al menos 4,5:1 con el fondo del panel

Scenario: El login Aero sigue funcionando
  Given el usuario propietario existe y no tiene sesión
  When rellena "Email" y "Password" con sus credenciales correctas y pulsa "Log in"
  Then queda autenticado y sale de "/login"

# --- Accesibilidad y adaptación -----------------------------------------------

Scenario: El foco del teclado se ve en todos los controles
  Given el usuario en "/styleguide" o un visitante en "/login"
  When recorre todos los controles con Tab
  Then cada control enfocado muestra un indicador de foco (outline o anillo de al menos 2 px) que no tiene sin el foco
  And ningún control habilitado se salta en el recorrido

Scenario: Movimiento reducido
  Given el navegador con prefers-reduced-motion: reduce
  When se abren "/login" y "/styleguide" y se pasa el puntero por los botones
  Then ningún elemento de la página, incluidas las capas de AeroBackground, tiene animation-name distinto de "none" ni animación con duración mayor que 0
  And las transiciones duran 0,01 s o menos

Scenario: Los componentes funcionan en el móvil
  Given un viewport de 375x667 px
  When se abren "/styleguide" y "/login"
  Then document.documentElement.scrollWidth es menor o igual que 375
  And todos los controles son visibles
  And el área táctil de cada control interactivo mide al menos 44x44 px

Scenario: La imagen de fondo no carga
  Given la petición de la imagen de fondo bloqueada
  When se abre "/login"
  Then el fondo muestra el degradado de cielo de reserva (background-image calculado con "linear-gradient")
  And el texto del formulario tiene un contraste de al menos 4,5:1 con el panel
  And enviar el formulario con las credenciales correctas inicia sesión

Scenario Outline: La app nunca entra en modo oscuro
  Given <origen del modo oscuro>
  When se abren "/login" y "/dashboard"
  Then "<html>" no tiene la clase "dark"

  Examples:
    | origen del modo oscuro                                  |
    | el navegador emula prefers-color-scheme: dark           |
    | la cookie "appearance" del starter kit con valor "dark" |

Scenario: La clase dark del starter kit no afecta al Aero
  Given "<html>" con la clase "dark"
  When se abre "/login"
  Then el color de fondo calculado del panel es el mismo que sin la clase "dark"
  And el texto del panel tiene un contraste de al menos 4,5:1 con el panel

# --- Recursos y validación ----------------------------------------------------

Scenario: El origen de cada recurso visual queda anotado
  Given los ficheros de fondos, iconos y fuentes Aero del repositorio
  When se compara la lista de ficheros con el registro de procedencia
  Then cada fichero tiene una entrada con su origen (URL o "creado a mano") y la fecha en que se añadió

@manual
Scenario: El propietario aprueba la estética de la base Aero
  Given "/login" y "/styleguide" en marcha en local
  When el propietario los revisa frente a los cuatro rasgos del discovery
  Then el "design.md" del cambio tiene una sección "Validación visual" con la fecha
  And incluye capturas a 1280 px y a 375 px de "/login" y de "/styleguide"
  And tiene un veredicto para cada uno de los cuatro rasgos (glossy / cristal, naturaleza y cielo, reproductores clásicos, skeuomorfismo), todos "aprobado"
  And cada corrección que pidió el propietario está marcada con [x]
```

## Notes & edge cases

<!-- Added by /task-enrich: error paths, permissions, empty states, limits. -->

**Decisiones (confirmadas con el usuario el 2026-11-09)**

- **Componentes Vue Aero propios con CSS propio.** Los componentes shadcn-vue del starter kit no se reestilan: se retiran poco a poco. T-03 los quita del login y la página de muestra no los usa. Cada tarea posterior retira los de las pantallas que toque.
- **Recursos visuales (fondos, iconos, fuentes):** vale cualquiera que le guste al usuario. La app es privada y no es pública, así que no se verifican licencias. Aun así, se anota de dónde sale cada recurso (escenario "El origen de cada recurso visual queda anotado").
- **La página de muestra requiere sesión**, así que T-03 depende de T-01 y de T-02.
- **Solo modo claro en la v1.** Los componentes Aero ignoran la clase `.dark`. El ajuste "Appearance" del starter kit desaparece: T-02 quita esa página. T-02 quita la página, y **T-03 deja la app fija en modo claro**: quita o neutraliza el script y el composable de apariencia del starter kit, para que `<html>` nunca reciba la clase `dark`, ni por la cookie ni por la preferencia del sistema. Así tampoco se oscurecen las pantallas shadcn-vue aún no migradas (como el dashboard). Lo comprueba el escenario "La app nunca entra en modo oscuro". El escenario "La clase dark del starter kit no afecta al Aero" queda como protección extra contra una clase `dark` que quede suelta. Las variantes oscuras, si llegan algún día, son cosa de los temas de T-18.
- **"Remember me" se queda en el login** (lo mantiene T-02 como casilla desmarcada), así que `AeroCheckbox` entra en el conjunto mínimo de T-03.

**Reglas propuestas (se pueden revisar en /opsx-propose)**

- **El montaje de Pest 4 browser debe servir para T-06 y T-23:** tiene que poder cargar un script falso en lugar del SDK de Spotify, con la URL del SDK configurable, y ejecutar JavaScript en la página. Añadido al aplicar los cambios del lote el 2026-11-09.

- **Tests de navegador:** Pest 4 browser testing con Playwright y Chromium, dentro de `php artisan test`. Lo decide esta tarea. Las siguientes tareas con interfaz lo reutilizan.
- **Skill propia, con la de la comunidad como referencia.** Se crea `.claude/skills/frutiger-aero/SKILL.md` y se toma como punto de partida la skill `frutiger-aero` de `sickn33/agentic-awesome-skills` (MIT, citada en la skill). Esa skill no cubre lo que este proyecto necesita (ver Investigación). La SKILL.md debería ser corta (un mapa de unas 100 líneas) y llevar el detalle a ficheros de referencia. T-21 la amplía con sus componentes.
- **Tailwind como capa de utilidades.** Tailwind se queda para el maquetado: flex, grid, espaciado y breakpoints. El aspecto Aero (degradados, brillo, cristal, relieve) va en el CSS propio de cada componente y usa los tokens. No hay que decidirlo ahora; se confirma en /opsx-propose.
- **Tokens como propiedades CSS personalizadas** en un único fichero (por ejemplo, `resources/css/aero/tokens.css`) con un prefijo común (por ejemplo, `--aero-*`). Así T-18 puede crear temas redefiniendo variables.
- **Ruta y nombre de la página de muestra:** `/styleguide`, componente Inertia `Styleguide`, solo con sesión. Se mantiene en producción porque solo entra el propietario y sirve de referencia a T-18. Los contadores "Clicks" y "Disabled clicks" solo existen en esta página.
- **Gancho para los tests:** el elemento raíz de cada componente Aero lleva `data-aero="<componente>"`, para que los tests lo localicen sin depender de clases CSS.
- **Reserva sin cristal:** `@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)))` aplica un fondo de panel con al menos 0,85 de opacidad. La misma regla se activa con la clase `aero-no-blur` en `<html>`, para poder forzarla en los tests.
- **Desenfoque contenido:** el Aero de Vista/7 usaba poco desenfoque. Un blur grande parece glassmorphism moderno y no Aero. El valor exacto lo fija la revisión del propietario.
- **Contraste:** 4,5:1 como mínimo para el texto normal (WCAG AA). El tinte del panel se ajusta para cumplirlo también sobre la zona más clara del fondo.
- **Área táctil:** al menos 44x44 px para cada control interactivo. Puede incluir la etiqueta o el relleno, aunque el dibujo del control sea más pequeño.
- **Casilla:** `AeroCheckbox` envuelve un `<input type="checkbox">` nativo, lo que da gratis el teclado, el envío del formulario y la accesibilidad. El aspecto Aero va encima.
- **Movimiento reducido:** una regla global `@media (prefers-reduced-motion: reduce)` que anula animaciones y transiciones.
- **Registro de procedencia:** un fichero (por ejemplo, `resources/aero/CREDITS.md`) con una fila por recurso: fichero, origen y fecha.
- **Registro de la validación visual:** la sección "Validación visual" del `design.md` del cambio. Así queda en el archivo de OpenSpec al hacer `/ship`.
- **Peso del fondo:** como orientación, menos de 400 KB por imagen en WebP/AVIF, porque se carga en el móvil con datos.

**Restricciones descubiertas**

- Todos los navegadores actuales que maneja Playwright soportan backdrop-filter. Por eso el escenario sin soporte se prueba forzando la reserva con la clase de `<html>`, no con un navegador antiguo.
- Las herramientas automáticas de contraste (axe) no pueden medirlo sobre fondos translúcidos o con imagen y lo marcan como "incompleto". El contraste del panel con cristal se comprueba en la revisión del propietario. El automático se hace con la reserva, cuyo color es casi opaco.
- La fuente Frutiger es comercial. Segoe UI viene con Windows pero no con Android ni iOS. Hace falta una pila de fuentes con alternativas que se vea parecida en el móvil.
- El starter kit trae modo claro/oscuro (ajuste "Appearance" y clase `.dark` en `<html>`). El Aero es claro por naturaleza, y la skill de la comunidad excluye los temas oscuros. Aunque T-02 quite la página "Appearance", el script del starter kit que pone `.dark` según la cookie o la preferencia del sistema puede seguir en el layout. Por eso existe el escenario con `<html class="dark">`.
- Las versiones actuales de shadcn-vue marcan sus componentes con el atributo `data-slot`. Por eso el escenario del login comprueba que no haya ninguno, y además que `Login.vue` no importe de `@/components/ui`.
- Pest 4 browser testing necesita Playwright y Chromium instalados donde corren los tests (en local y en el servicio `test` de T-20).

**Casos límite**

- **La fuente web no carga:** se usa la siguiente fuente de la pila sin que se rompa el maquetado.
- **La imagen de fondo carga lenta en el móvil:** primero se pinta el degradado de cielo y la imagen aparece encima cuando llega.
- **Pantallas del starter kit no migradas** (dashboard, ajustes) siguen con shadcn-vue hasta que una tarea las toque. Mezclar estilos un tiempo es aceptable por la decisión de retirarlos poco a poco.
- **Resto de páginas de invitado que deje T-02** (por ejemplo, recuperar contraseña): si comparten el layout de autenticación del starter kit, al reestilizar ese layout reciben el fondo y el panel Aero, aunque sus controles internos sigan siendo shadcn-vue.
- **Pulsar y arrastrar fuera del botón:** el estado pulsado no debe quedarse fijo al soltar fuera.
- **Toque en el móvil:** no hay hover. El estado pulsado debe verse al tocar y el estilo hover no debe quedarse pegado después del toque.
- **Autocompletado del navegador** en "Email" y "Password": el fondo amarillo o azul de autofill no debe romper el aspecto ni el contraste del `AeroInput`.

**Pistas para los tests**

- **Feature tests de Pest:** `/styleguide` con `actingAs` (200 y componente Inertia `Styleguide`) y sin sesión (redirección a `/login`).
- **Tests de navegador (Pest 4):** localizan los componentes por `data-aero` y comparan estilos calculados (`getComputedStyle`) para los estados, el foco, la casilla, la reserva, la clase `dark` y el movimiento reducido. Para el viewport de 375 px se comprueba `scrollWidth` y el área táctil con `getBoundingClientRect`. Para el fondo que no carga se bloquea la petición de la imagen.
- **Movimiento reducido:** se emula con `prefers-reduced-motion: reduce`. Si Pest no lo expone, se usa la emulación de medios de Playwright.
- **Login sin shadcn:** el test de navegador cuenta los elementos `[data-slot]`, y un test de Pest lee `resources/js/pages/auth/Login.vue` (o la ruta que use el starter kit) y comprueba que no contiene `@/components/ui`.
- **Tokens frente a skill:** un test lee el fichero de tokens, extrae los nombres `--aero-*` y comprueba que la SKILL.md (o su referencia de tokens) contiene exactamente esos nombres.
- **Procedencia:** un test recorre las carpetas de fondos, iconos y fuentes y comprueba que cada fichero aparece en el registro de procedencia.
- **Escenarios `@manual`:** el listado de la skill y la validación del propietario. Se verifican a mano y se anotan en el `design.md` del cambio.

**Investigación de skills Aero (pregunta abierta 9, 2026-11-09)**

- **Existe una skill reutilizable:** `frutiger-aero` del repo `sickn33/agentic-awesome-skills` (licencia MIT; es una referencia hija de la skill `design-it`). Cubre el brillo y el cristal, los motivos de naturaleza y agua, el skeuomorfismo, una paleta (cian, azul cielo, verde lima, blanco), una tipografía (Frutiger, Segoe UI, Myriad Pro), un botón gel y un panel de cristal en CSS, además de ejemplos para SwiftUI, Flutter, React Native y Compose. **No cubre** los reproductores clásicos, la accesibilidad (foco, contraste), el movimiento reducido, la reserva sin backdrop-filter ni Vue. Por eso no basta tal cual y se propone una skill propia que la cite. Fuentes: <https://vibehackers.io/claude-code/skills/frutiger-aero-sickn33>, <https://claudskills.com/skills/frutiger-aero/SKILL.md>, <https://claudeskills.info/skills/sickn33/agentic-awesome-skills/frutiger-aero/>.
- **Recursos CSS útiles como referencia (no son skills):**
  - Make Aero (`Visnalize/makeaero`, MIT): generadores de botones glossy, orbes, ventanas de cristal Windows 7 y fondos (cielo, bajo el agua), que exportan CSS o PNG. <https://github.com/Visnalize/makeaero>
  - 7.css (`khang-nd/7.css`, MIT): framework CSS sin JavaScript que imita Windows 7. <https://github.com/khang-nd/7.css>
  - 7-aero-stylesheet (`z2r-yt`): hoja de estilos Windows 7 / Frutiger Aero. <https://awesome.ecosyste.ms/projects/github.com%2Fz2r-yt%2F7-aero-stylesheet>
  - Una guía de la técnica glossy Web 2.0 (degradado con corte a la mitad y reflejo superior) en Frutiger Aero Archive: <https://frutigeraeroarchive.org/blog/posts/20_09_2025>
  - Una recopilación de fuentes, kits de Figma y paletas: <https://www.designpieces.com/2026/08/frutiger-aero-design-resources/>

**Fuera de alcance**

- `AeroSlider`, `AeroPlayerBar` y la carátula de reserva (T-21).
- Los temas y skins intercambiables (T-18) y el visualizador (T-16).
- Las listas de canciones, las pestañas, los menús y los diálogos. Los crea cada tarea que los necesite, siguiendo la skill.
- Reestilar "/". T-02 ya lo decidió: "/" sigue mostrando el `Welcome` del starter kit a los visitantes, sin enlace de registro, y el logout lleva ahí. Esa página queda sin estilo Aero, y su reestilo es trabajo diferido que todavía no tiene tarea (igual que la limpieza final de shadcn-vue).
- Migrar el dashboard y los ajustes del starter kit, y la limpieza final de shadcn-vue.
- Las reglas de autenticación del login, que son de T-02. T-03 solo cambia su aspecto.

**Pendiente**

- **Recursos concretos:** qué fondo, qué iconos (los de lucide del starter kit son planos) y qué fuente. Los elige el propietario.

**Estimación: 5 puntos.**
- **Skill propia y tokens (1 punto):** se parte de la skill de la comunidad, se añaden los reproductores clásicos y la accesibilidad, y se escriben los tokens.
- **Fondo, recursos y registro de procedencia (0,5 puntos):** buscar, optimizar y anotar los recursos.
- **Botón, orbe, panel, campo y casilla con estados, foco y reserva (1,5 puntos):** el brillo convincente en CSS lleva prueba y error.
- **Login y página de muestra con sus feature tests (0,5 puntos).**
- **Configurar Pest 4 browser testing y escribir los tests de navegador (1 punto):** estados, foco, 375 px, movimiento reducido, reserva, clase `dark` y fondo caído.
- **Ronda de revisión del propietario y correcciones (0,5 puntos).**

**Seguimiento del lote `/task-enrich-batch` del 2026-11-09 (T-13, T-14, T-16, T-17, T-18, T-19, T-25, T-26)**

Lo apunta el orquestador del lote. Son cambios que piden las tareas de ese lote; se aplican al proponer (o enriquecer) esta tarea.

- En el escenario "La app nunca entra en modo oscuro", cambiar "/dashboard" por "/styleguide" (D12 elimina `/dashboard`).
- En "Fuera de alcance", sustituir "Reestilar '/'…" por: "'/' deja de ser una página: redirige a /login (D11, T-02) o a /library (D12, T-25). La limpieza final de shadcn-vue y la navegación son de T-25; las páginas de error Aero, de T-26".
- En "Solo modo claro" y en "Casos límite", cambiar "como el dashboard" por "como la navegación del starter kit, hasta T-25".
- **Variantes oscuras:** ya decididas (D10, 2026-11-09): T-18 crea el tema "Aero Graphite" con `data-theme`, nunca con `.dark`.
- **Tokens preparados para T-18** (evita una refactorización):
  - `tokens.css` guarda solo los tokens fijos (radios, espaciado, tipografía, desenfoque) en `:root`.
  - Los tokens temables del tema por defecto (colores, degradados, sombras, imagen de fondo y degradado de reserva, como `--aero-background-image` y `--aero-background-fallback`) van en `resources/css/aero/themes/sky.css` bajo `:where(:root), [data-theme="sky"]`. T-03 puede hacerlo desde el principio, o T-18 los mueve.
  - Los componentes nunca usan colores literales que deban cambiar con el tema.
  - El test de tokens frente a skill lee todos los ficheros que declaran `--aero-*`, no solo `tokens.css`.
  - La vista Blade raíz pinta `data-theme` en `<html>`.
  - Si es posible, nombrar los tokens del cielo, que T-16 usa en sus escenarios de color.

**Seguimiento de project-foundation (2026-11-10)**

- **Errores de tipos del kit:** unos 20 (ver T-01). Corregirlos al tocar los componentes que los tienen.
- **Páginas Inertia en minúsculas:** el kit usa `resources/js/pages`. Los tests lo configuran en `tests/TestCase.php`; mantenerlo al crear páginas nuevas.
- **MODIFICA el requisito "Self-contained automated test suite"** de `project-foundation`: los tests de navegador necesitan un build real.

**Seguimiento de single-owner-login (T-02, 2026-11-10)**

- La página de login ya no tiene "Sign up" ni "Forgot password?", y no queda ninguna página de ajustes. T-03 solo da estilo al login y al dashboard.
- **Automatizar con el plugin de navegador** los tres escenarios que T-02 comprobó a mano: el login sin enlaces de registro ni recuperación, el menú de usuario sin "Settings" ni enlaces que den 404, y "atrás" tras el logout sin páginas protegidas (`openspec/specs/owner-auth`).
- **Comprobación de tipos:** T-02 la dejó aquí. Añadir `vue-tsc` (resolviendo el conflicto de versiones de T-01) y corregir los errores del kit en los componentes que se toquen.
- **Tema:** `useAppearance.ts` y `initializeTheme()` en `app.ts` siguen vivos; quitarlos al fijar el modo claro. `AppearanceTabs.vue` ya está borrado.
- El pie de la barra lateral del kit conserva los enlaces externos "Github Repo" y "Documentation" (y el nombre "Laravel Starter Kit"); quitarlos o sustituirlos al aplicar el estilo Aero.

## Definition of Done

- [ ] Acceptance criteria covered by tests
- [ ] Pest 4 browser testing (Playwright + Chromium) configured and running with `php artisan test`
- [ ] `@manual` scenarios verified and recorded in the change's `design.md`
- [ ] Spec deltas archived in openspec/specs/
- [ ] Work committed on main
