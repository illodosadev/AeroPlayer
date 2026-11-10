# Discovery — reproductor-musica-privado

> Output of `/req-capture`. Raw material for `/task-generate`.

## Context

- **Stakeholders:** Un único usuario: el propietario del proyecto, que es a la vez quien lo desarrolla y quien lo usa. No hay más usuarios ni público.
- **Problem statement:** Hacer un reproductor de música web privado (Laravel + Vue) que reproduzca la música de las suscripciones de pago del usuario (Spotify Premium y YouTube Music Premium) en una sola interfaz con estética Frutiger Aero / años 2000.
- **Current behavior / pain:**
  - La música está repartida entre dos apps (Spotify y YouTube Music).
  - No le gusta la estética ni la experiencia de las apps oficiales.
  - Además, el proyecto sirve para aprender y practicar Laravel + Vue.
- **Business goal / success metric:**
  - **Uso diario:** el usuario lo usa cada día en lugar de las apps oficiales.
  - **Look años 2000 logrado:** la interfaz transmite claramente Frutiger Aero / estética de los 2000.
  - **Biblioteca unificada (deseable):** ver y reproducir playlists y "me gusta" de los dos servicios en un mismo sitio. Si técnicamente no se puede, el proyecto no se considera un fracaso.

## Functional needs

### Imprescindibles (v1)

- Como usuario, quiero entrar con un login propio de un solo usuario, sin registro público.
- Como usuario, quiero conectar mi cuenta de Spotify Premium y mi cuenta de YouTube Music Premium.
- Como usuario, quiero buscar canciones, álbumes y artistas y reproducirlos con los controles básicos: play, pausa, siguiente, anterior, volumen y avanzar/retroceder en la canción (seek).
- Como usuario, quiero ver y reproducir mis playlists y canciones guardadas ("me gusta") de cada servicio.
- Como usuario, quiero ver y editar la cola de reproducción, con aleatorio (shuffle) y repetición (repeat). **Decidido el 2026-11-09 (T-13):** la cola se guarda en el servidor y es la misma en todos los dispositivos (D3). Si una canción falla, se avisa y se pasa a la siguiente; tras 3 fallos seguidos se para (D4).
- Como usuario, quiero crear playlists propias dentro de la app que mezclen canciones de Spotify y de YouTube Music. **Decidido el 2026-11-09 (T-14):** ver la pregunta abierta 4.
- **Decidido el 2026-11-09 (D5):** la cola y las playlists propias se construyen primero con Spotify, con un modelo independiente del servicio (cada canción lleva `source`). Las partes que mezclan servicios pasan a las tareas de YouTube Music (T-10, T-11, T-12).
- **Página principal y "/" (2026-11-09):** un visitante sin sesión que abre "/" va a `/login`, y la página `Welcome` desaparece (D11, T-02). Tras el login, la página principal es `/library`, y `/dashboard` se elimina (D12, T-25).
- En Spotify, la música suena en la pestaña del navegador del reproductor. No hace falta controlar otros dispositivos con Spotify Connect, salvo como plan B en móvil (ver Constraints).

### Deseables (después de la v1)

- Visualizador animado al estilo Windows Media Player / Winamp. **Decidido el 2026-11-09 (D8, T-16):** animación simulada, porque la app no puede leer el audio real. Sigue el play, la pausa y los cambios de canción, con un solo estilo "burbujas y agua" Aero.
- Letra de la canción que suena. **Decidido el 2026-11-09 (D9, T-17):** la fuente es LRCLIB (https://lrclib.net). Se muestra la letra sincronizada si existe y, si no, sin sincronizar.
- Skins o temas intercambiables (por ejemplo, variantes de color Aero). **Decidido el 2026-11-09 (D10, T-18):** cinco temas. "Aero Sky" (el aspecto de T-03) es el de por defecto; los otros son Aero Green, Aqua, Pink/Purple y Graphite, que es el oscuro. El tema elegido se guarda por usuario en el servidor.
- Historial de escucha y estadísticas propias. **Decidido el 2026-11-09 (D13, T-19):** una canción cuenta como escuchada tras 30 s de reproducción.

### Diseño

- Estética Frutiger Aero / años 2000 con estos rasgos clave:
  - Glossy / cristal: botones brillantes y transparencias tipo Aero Glass de Windows Vista/7.
  - Naturaleza y cielo: cielo azul, agua, burbujas, hierba.
  - Reproductores clásicos como inspiración: WMP 11, iTunes 7–9, Zune, skins de Winamp.
  - Skeuomorfismo: controles con relieve, reflejos y sombras realistas.
- Tarea de investigación: buscar o crear skills de Claude con guías de diseño Frutiger Aero / años 2000 para usarlas al construir la UI.

## Non-functional needs

- **Dispositivos:** navegador de escritorio y navegador móvil. Accesible por Internet desde fuera de casa, no solo en la red local.
- **Móvil:** el usuario lo considera imprescindible: la música debe sonar en el móvil, también con la pantalla bloqueada. Si con Spotify no se puede (ver Constraints), acepta como alternativa que en móvil suene la app oficial de Spotify y la web la controle con Spotify Connect.
- **Seguridad / acceso:** solo un usuario, con login propio por email y contraseña (confirmado el 2026-11-09). No hay registro: la cuenta se crea con un comando artisan, y la app no envía ningún email (T-02). Solo se puede conectar la cuenta de Spotify del propietario (T-05). Los tokens de Spotify / YouTube y las cookies de sesión de YouTube Music son credenciales personales y deben guardarse de forma segura en el servidor.
- **Hosting:** el hosting contratado en Hostinger, en el subdominio `music.illodosadev.io` (subdominio del sitio `illodosadev.io`, D16), aislado de otros proyectos alojados en la misma cuenta.

## Constraints & assumptions

- **Stack:** Laravel + Vue unidos con Inertia (monolito; por ejemplo, el starter kit oficial de Laravel con Vue).
- **Proyecto nuevo:** va en un repositorio propio, separado de LaravelSellerInventory. Se asume que el repo nuevo usará la misma plantilla de spec-driven development (OpenSpec + backlog) para continuar con `/task-generate`.
- **Specs existentes:** las specs de `openspec/specs/` de LaravelSellerInventory (user-auth, products, modules, app-layout, error-pages) son de otro producto y no se aplican aquí. Hay una diferencia a tener en cuenta: `user-auth` de ese repo incluye registro público y verificación de email, y este proyecto no los quiere (un solo usuario, sin registro).
- **Spotify:**
  - Para reproducir en el navegador se usa el Web Playback SDK, que requiere Premium. El usuario tiene Premium.
  - ~~El Web Playback SDK no está soportado oficialmente en navegadores móviles.~~ **Actualizado el 2026-11-09:** la documentación actual del SDK dice que funciona en navegadores móviles Android e iOS, con limitaciones en iOS (la reproducción necesita un gesto del usuario y el volumen no se puede cambiar). Los planes Premium solo para móvil (Mini/Lite) no funcionan. La prueba de la pregunta abierta 3 lo confirmará en el móvil real, y el plan B de Spotify Connect (T-15) se mantiene por si falla. Fuente: https://developer.spotify.com/documentation/web-playback-sdk
  - **La app de Spotify se queda en "development mode"** (condiciones verificadas el 2026-11-09, ver la pregunta 10). El extended quota solo se concede a organizaciones con 250.000 usuarios activos al mes o más. Condiciones del development mode:
    - **Usuarios y cuentas:** el propietario de la app necesita Premium. Como mucho hay 5 usuarios y un Client ID por desarrollador.
    - **Datos de `/me`:** ya no devuelve `product`, `email` ni `country`.
    - **Búsqueda y artistas:** la búsqueda devuelve como mucho 10 resultados por tipo. Ya no existen `artists/{id}/top-tracks` ni los endpoints de novedades y categorías.
    - **Playlists:** solo se pueden listar las canciones de las playlists propias o colaborativas. Las que el usuario solo sigue se pueden reproducir, pero no listar.
    - **Tokens:** el refresh token dura 6 meses desde la autorización.
    - **URIs de redirección:** tienen que ser HTTPS o loopback (`http://127.0.0.1:PUERTO`), nunca `localhost`.

    Fuentes: https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security, https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide, https://developer.spotify.com/documentation/web-api/concepts/quota-modes, https://developer.spotify.com/documentation/web-api/concepts/redirect_uri
- **YouTube Music:**
  - No tiene API oficial. El usuario acepta librerías no oficiales (por ejemplo, autenticación con sus cookies), asumiendo el riesgo de que dejen de funcionar o de que el servicio bloquee el acceso. Es para uso personal.
  - Las librerías no oficiales más conocidas están en Python (por ejemplo, `ytmusicapi`). Ejecutarlas depende del plan de Hostinger (ver Open questions).
- **Reproducción en móvil:** la reproducción en segundo plano o con la pantalla bloqueada en navegadores móviles es limitada, tanto con el SDK de Spotify como con el reproductor embebido de YouTube.

## Out of scope

- Multiusuario o uso público: nunca habrá registro ni otros usuarios.
- Descargar o guardar audio: no se descarga ni se almacena música.
- Apps nativas de iOS o Android.
- Controlar otros dispositivos con Spotify Connect como función general (solo se contempla como plan B en móvil).

## Open questions

1. ~~**Plan de Hostinger:** ¿es hosting compartido, Cloud o VPS? De esto depende si se puede ejecutar Python (librerías no oficiales de YT Music), workers de colas o Docker, y qué versión de PHP/Node hay disponible.~~ **Resuelta el 2026-11-09** (leído de la cuenta con la extensión MCP de Hostinger; detalle en T-04):
   - **Plan:** "Premium Web Hosting", compartido (CloudLinux). No tiene Node.js, Docker, procesos permanentes ni Redis.
   - **PHP:** 8.3 en `illodosadev.io`, con 7.3–8.5 disponibles. `exec` y `shell_exec` están desactivadas.
   - **Despliegue:** GitHub Actions + SSH con un botón manual (D14, D15). La app va en `music.illodosadev.io`, como subdominio de `illodosadev.io` (D16).
   - **Python para YT Music:** sigue abierto en la pregunta 2.
2. **Viabilidad de YT Music:** spike técnico para elegir la librería o el enfoque no oficial, cómo autenticar (cookies o OAuth del cliente TV), y cómo reproducir (¿reproductor embebido de YouTube? ¿Premium evita anuncios en el embed?).
3. **Viabilidad en móvil:** spike en el móvil real del usuario. ¿Qué sistema y navegador usa (iOS Safari / Android Chrome)? ¿Suena con la pantalla bloqueada para Spotify y para YouTube? ¿Funciona el plan B de Spotify Connect con la UX esperada? *(Nota del 2026-11-09: Spotify dice ahora que el SDK funciona en navegadores móviles, así que la prueba debe empezar por el SDK. Ver Constraints > Spotify.)*
4. ~~**Playlists mixtas:** ¿se guardan solo en la base de datos de la app, o se sincronizan con los servicios? ¿Qué pasa si una canción deja de estar disponible?~~ **Resuelta el 2026-11-09 (T-14):**
   - **D6:** se guardan solo en la base de datos de la app. No se sincronizan con Spotify ni con YouTube Music, y no se piden permisos de escritura.
   - **D7:** una canción que deja de estar disponible se queda en la playlist, en gris y marcada "Unavailable". Se salta al reproducir y se quita a mano.
5. ~~**Cola mixta:** ¿se pasa sin cortes de una canción de Spotify a una de YouTube? ¿Qué se acepta (pausa breve, etc.)?~~ **Resuelta el 2026-11-09 (D2):** se acepta una pausa breve. La canción siguiente tiene que empezar menos de 2 s después de que termine la anterior. El escenario está en T-10 (D5).
6. **Resuelta el 2026-11-09:** una sola lista mezclada y sin duplicados; si una canción está en los dos servicios, sale una vez. Siguen pendientes el criterio para decidir que dos canciones son la misma y qué servicio la reproduce. Pregunta original: ~~**Biblioteca unificada:** ¿cómo se presentan juntos los resultados de búsqueda y las bibliotecas de los dos servicios (separados por pestañas o mezclados)? ¿Hay que eliminar duplicados?~~
7. **Archivos locales:** no se marcó como fuera de alcance. ¿Se quiere reproducir MP3 propios subidos al servidor, o no?
8. ~~**Idioma de la interfaz:** ¿castellano, inglés o ambos?~~ **Resuelta el 2026-11-09:** inglés (ver T-01).
9. ~~**Skills de diseño:** ¿existen skills de Claude con guías Frutiger Aero que se puedan reutilizar, o hay que crearlas? ¿Hay que reunir recursos visuales (iconos, fondos, fuentes) y con qué licencias?~~ **Resuelta el 2026-11-09:**
   - **Skill:** existe una skill de la comunidad (`frutiger-aero`, MIT), pero está incompleta, así que T-03 crea una propia que la cita.
   - **Recursos visuales:** vale cualquiera que le guste al usuario. No se verifican licencias, pero se anota la procedencia de cada uno.
   - El detalle está en T-03, sección "Investigación".
10. ~~**Condiciones de la API de Spotify:** verificar las restricciones actuales del development mode y del Web Playback SDK para uso personal.~~ **Resuelta el 2026-11-09:** ver Constraints > Spotify, y el detalle con fuentes en T-05 ("Restricciones descubiertas"). Consecuencias:
    - **T-05:** solo la cuenta del propietario.
    - **T-07:** como mucho 10 resultados por petición.
    - **T-08:** las playlists que el usuario solo sigue no se pueden listar.
    - **T-24:** la página de artista no tiene "top tracks".
11. **Política de desarrolladores de Spotify y mezcla de servicios** (detectado el 2026-11-09 al enriquecer T-16). La Developer Policy de Spotify, sección III.5, dice: "Do not create any product or service which is integrated with streams or content from another service". Eso puede afectar a la cola mixta (T-13, T-10), a las playlists propias mixtas (T-14) y a la biblioteca unificada (T-22). Es una app privada de un solo usuario en development mode, pero falta decidir:
    - si se acepta el riesgo, como se aceptó el de las librerías no oficiales de YouTube Music;
    - o si se limita la mezcla.

    Fuente: https://developer.spotify.com/policy
