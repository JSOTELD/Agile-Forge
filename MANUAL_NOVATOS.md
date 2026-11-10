# 🔰 Manual de AgileForge v2 para Novatos

🌐 [English](BEGINNERS_GUIDE.md) · **Español**

> *Guía paso a paso para dominar tu centro de comando de agentes de IA sin enredos técnicos.*

Bienvenido a **AgileForge v2**. Si nunca has usado un entorno ágil con agentes de Inteligencia Artificial, este manual es para ti: aquí aprendes lo necesario desde cero, de forma sencilla y directa.

---

## 1. 🔰 ¿Qué es AgileForge y para qué sirve?

Imagina tener a un equipo completo trabajando a tu lado:
- 👑 **Director General:** el jefe del equipo. Le das una instrucción, revisa el tablero, decide y reparte el trabajo.
- 📋 **Project Manager:** organiza el trabajo y define prioridades.
- 🎨 **Director Creativo & UX/UI:** diseña interfaces limpias y cuida la usabilidad.
- 🏛️ **Arquitecto Jefe:** elige las tecnologías y diseña la estructura.
- 💻 **Frontend Master:** programa las pantallas, botones y diseño visual.
- ⚙️ **Backend Master:** programa la base de datos, las APIs y la lógica del servidor.
- 🛡️ **QA & Seguridad:** revisa que el código no tenga errores ni vulnerabilidades.
- ⚖️ **Asesor Legal:** revisa que cumplas las leyes (protección de datos, consumidor, etc.).

En lugar de copiar y pegar prompts gigantes en un chat, **AgileForge les da a estos agentes un tablero Scrum real**. Leen las tareas, las toman, reportan avances y se reúnen para debatir soluciones contigo, el Product Owner humano.

---

## 2. ⚡ Puesta en marcha (primer uso)

1. **Abre una terminal** en la carpeta del proyecto.
2. **Inicializa todo:**
   ```bash
   php bin/migrate.php
   ```
   *Crea la base de datos local y tu token seguro en `.env`.*
3. **Abre la aplicación:**
   - Con XAMPP: `http://localhost/Open-to-code/` (o el nombre de tu carpeta).
   - Con PHP directo: ejecuta `php -S localhost:8080 router.php` y abre `http://localhost:8080`. (El `router.php` es importante: el servidor de PHP ignora los `.htaccess` y, sin él, cualquiera en tu red podría descargar tu `.env` con las claves y tu base de datos.)

---

## 3. 🧠 Dale cerebro a tus agentes (elige una opción)

Los agentes necesitan una IA para responder. Tienes tres caminos y puedes combinarlos:

| Opción | Costo | Cómo |
|---|---|---|
| **IA local** (Ollama / Colibri) | Gratis, 100 % privado | Ver sección 7. |
| **Modelos en la nube** (Claude, Gemini, GPT, DeepSeek) | Pagas por uso | Pega tu clave en **⚙️ Motor IA → 🔑 Claves de API**. |
| **Modo plugin** (Claude Code) | Lo que ya pagas de Claude Code | Trabaja con los agentes desde Claude Code (sección 10). No necesita claves. |

### Cómo agregar una clave de API (2 minutos)
1. Consigue tu clave en la consola del proveedor. La guía completa, con el formato de cada clave, está en **[docs/API_KEYS.es.md](docs/API_KEYS.es.md)**.
2. En AgileForge pulsa **⚙️ Motor IA** (barra superior) y baja a **🔑 Claves de API**.
3. Pega la clave en la fila de su proveedor y pulsa **Guardar**. Se prueba sola y verás `✅ … respondió en Xs`.

> ⚠️ **Google Gemini:** AI Studio te muestra un ejemplo con `curl`. Eso **no** es la clave: la clave es solo el texto que va después de `x-goog-api-key:` (suele empezar por `AIza`).
>
> 🔒 Tus claves se guardan solo en el archivo `.env` de tu PC, nunca se suben a GitHub y el navegador solo ve una pista (`sk-ant-…a1b2`). No las compartas en chats ni capturas.

### Elige qué IA usa cada agente
En la pestaña **🤖 Equipo de Agentes**, cada tarjeta tiene el selector **Motor de IA de este rol**: un modelo concreto (por ejemplo *Claude Opus 5.5* para el Director), la **IA local** o *Heredar*. Debajo ves quién responde de verdad en este momento. Si a un rol le falta su clave, responde la IA local y la tarjeta te avisa.

---

## 4. 👑 El Director General: dale instrucciones

En el **Tablero Scrum**, arriba, está el panel del **Director General**:

1. Escribe lo que quieres lograr, como se lo dirías a un jefe de proyecto. Por ejemplo:
   - *"Prioriza lo necesario para lanzar las reservas online esta semana y reparte el trabajo."*
   - *"¿Qué está bloqueado? Decide los siguientes pasos."*
2. Pulsa **Dar instrucción** (o `Ctrl + Enter`).
3. El Director revisa el tablero, decide, crea o reasigna historias, consulta a los especialistas si hace falta y te responde con: **Decisión**, **Qué hice**, **Siguientes pasos** y **Riesgos o preguntas**. Debajo verás cada acción que hizo en el tablero.

> Con Claude responde en segundos; con IA local puede tardar 1–3 minutos. El Director **nunca** marca historias como terminadas: eso lo apruebas tú.

También puedes hablarle desde **💬 Consultar** (el chat) o, en cada historia, con el botón **👑 Pedir decisión al Director**.

---

## 5. 📋 El tablero Scrum

| Columna | ¿Qué significa? | ¿Quién actúa aquí? |
| :--- | :--- | :--- |
| **1. Backlog** | Ideas, tareas y requerimientos por hacer. | Tú, el Director o el Project Manager. |
| **2. En Progreso** | Trabajo en curso (la historia queda bloqueada para que nadie más la pise). | Un agente o tú. |
| **3. En Revisión (DoD)** | Trabajo terminado esperando control de calidad. | El agente aporta pruebas o diffs. |
| **4. Completadas** | Trabajo aprobado. | **Solo tú o el rol QA**. |

### Crear tu primera historia
1. Pulsa **+ Nueva Historia**.
2. Escribe el título (ej.: *"Crear pantalla de inicio de sesión"*), elige el agente, la prioridad y los puntos (1, 2, 3, 5, 8, 13).
3. Completa la historia de usuario y la Definition of Done (qué debe cumplirse para darla por terminada).
4. Pulsa **Crear historia y generar prompt**. Aparece en el Backlog y se abre su prompt de misión, listo para copiar en Claude Code.

### Editar una historia
Haz clic en el título de cualquier tarjeta: puedes cambiar agente, prioridad, puntos y textos, copiar su prompt, pedir una decisión al Director o eliminarla (🗑️).

---

## 6. 🌓 Modo día y modo noche

- **Rápido:** en la barra superior pulsa **🌙 Noche / ☀️ Día**.
- **Paletas:** pulsa **🎨 Tema** y elige entre 6: *Cyber Obsidian*, *Electric Indigo*, *Neon Emerald*, *Midnight Violet* (noche), *Solar Day* y *Clean Paper* (día). También puedes elegir la densidad (*Cómodo* o *Compacto*).
- Tu elección se guarda en el navegador.

---

## 7. 🦙 Inteligencia artificial local (gratis y privada)

1. Instala **Ollama** ([ollama.com](https://ollama.com)) y descarga un modelo:
   ```bash
   ollama pull gemma4:e4b
   ```
2. Abre AgileForge: el botón **🦙 IA local** detecta el modelo.
3. Actívalo: los agentes sin motor de API propio responderán **en tu PC**. Cero costo y ningún dato sale a internet.

¿Tienes una PC potente? En Windows puedes usar **Colibri** para modelos más grandes: ejecuta `bin\colibri-instalar.cmd` y luego `bin\colibri-crear-acceso-directo.cmd` para tener el acceso directo **"Colibri IA"** en el escritorio.

---

## 8. 🌐 Idioma: español e inglés

En la barra superior pulsa **🌐 ES | EN**: la interfaz cambia entre español e inglés (*Tablero Scrum ⇄ Scrum Board*, *Sala de Reuniones ⇄ Meeting Room*…). Los agentes responden en el idioma en que les escribes.

---

## 9. 🎙️ Sala de reuniones

1. Ve a **Sala de Reuniones** (`Alt + 4`).
2. Elige una reunión rápida o arma la tuya: tipo (*Brainstorming*, *Consejo de Arquitectura*, *Compliance*, *Daily*), tema y participantes.
3. Cada agente interviene por turnos con su propia IA (verás el progreso en pantalla) y al final el Director redacta el **acta con resolución y acuerdos**.
4. Si estás de acuerdo, pulsa **📥 Convertir al Backlog** y los acuerdos pasan a tu Backlog como historias.

---

## 10. 🤖 Trabajar desde Claude Code

Puedes usar todo el equipo de agentes desde **Claude Code** sin ninguna clave de API y sin IA local: el cerebro es Claude Code. Solo usas tu plan normal de Claude Code.

1. Comprueba que AgileForge está en marcha (el tablero se abre en tu navegador).
2. En Claude Code escribe estas dos líneas, una por una:
   ```text
   /plugin marketplace add JSOTELD/agile-forge
   /plugin install agileforge@agileforge
   ```
   Así se instala el plugin **AgileForge Control**. Luego escribe `/reload-plugins` (o abre una sesión nueva).
3. Prueba los comandos principales:

| Comando | Qué hace |
|---|---|
| `/agileforge:director <lo que quieres>` | Hablas con el Director General: decide y reparte el trabajo. |
| `/agileforge:equipo` | Muestra el equipo: quién hace qué y con qué modelo. |
| `/agileforge:sprint` | Te cuenta cómo va el sprint. |
| `/agileforge:tomar US-101` | El especialista adecuado toma una historia, la construye y la manda a revisión. |
| `/agileforge:dod US-101` | QA revisa si la historia está realmente terminada. |

Si algo no conecta, pídele a Claude "ejecuta connection_info": te dice a qué dirección está intentando conectarse. Más detalles en [adapters/README.es.md](adapters/README.es.md) y en la [guía del plugin](plugin/agileforge/README.es.md).

> 🛡️ **Regla de seguridad:** un agente nunca puede aprobar su propio trabajo. Una historia solo pasa a *Completadas* cuando tú la apruebas o cuando el agente de QA aprueba un trabajo hecho por otro.

---

## 11. 👁️ Ver el avance (Vista)

A veces tú, tu jefe o un cliente solo quieren **ver** cómo van las cosas, sin riesgo de cambiar algo por accidente. Para eso existe **Vista**, en dos formas:

- **El botón 👁️ Vista:** en la barra superior de AgileForge. Abre en otra pestaña una página con el sprint, quién trabaja en qué, las reuniones, las decisiones y la actividad reciente. También puedes abrirla directamente en `vista.php` (por ejemplo `http://localhost/Open-to-code/vista.php`).
- **El plugin AgileForge Vista (para Claude Code):** si solo quieres mirar, instala este en lugar de Control:
  ```text
  /plugin marketplace add JSOTELD/agile-forge
  /plugin install agileforge-vista@agileforge
  ```
  Después usa `/agileforge-vista:estado` para un resumen de 5 líneas, o `/agileforge-vista:tablero`, `/agileforge-vista:reuniones`, `/agileforge-vista:decisiones` y `/agileforge-vista:actividad`.

Conviene saber:
- Vista es de **solo lectura**: nadie puede cambiar el tablero desde ahí.
- Usa un **token de solo lectura** especial (`AGILEFORGE_VIEW_TOKEN` en tu `.env`) que se crea solo. Si quieres que alguien mire, comparte solo ese, **nunca** tu token principal.
- Los plugins Control y Vista **no se instalan juntos**: elige uno. Si quieres cambiar, desinstala primero el otro.

---

## 12. ⌨️ Atajos de teclado

| Atajo | Acción |
| :--- | :--- |
| <kbd>Ctrl</kbd> + <kbd>K</kbd> | Búsqueda rápida de historias y comandos |
| <kbd>Ctrl</kbd> + <kbd>Enter</kbd> | Enviar la instrucción al Director |
| <kbd>Alt</kbd> + <kbd>M</kbd> | Abrir este manual en pantalla |
| <kbd>Alt</kbd> + <kbd>1</kbd> … <kbd>5</kbd> | Tablero · UX/UI · Legal · Reuniones · Equipo |
| <kbd>Esc</kbd> | Cerrar cualquier ventana |

---

## 13. 🆘 Si algo falla

| Problema | Solución |
|---|---|
| Un agente responde "⚠️ sin motor" | Agrega una clave en Motor IA o activa la IA local (no hace falta si usas el plugin de Claude Code). |
| El plugin no conecta | Comprueba que AgileForge está en marcha y pídele a Claude "ejecuta connection_info" para ver qué dirección usa. |
| `HTTP 401` al probar una clave | La clave está mal copiada o fue revocada: cópiala de nuevo o crea otra. |
| El Director tarda mucho | Con IA local es normal (1–3 min). Con una clave de Claude responde en segundos. |
| Otro error | Revisa `data/logs/agileforge.log` y la guía [docs/API_KEYS.es.md](docs/API_KEYS.es.md#7-solución-de-problemas). |

---

> ⚠️ Los agentes de IA (incluido el Asesor Legal) ofrecen orientación y no sustituyen la asesoría profesional. Los costos de las API de terceros corren por tu cuenta. AgileForge es software independiente, con licencia MIT y entregado "tal cual"; no está afiliado a Anthropic, Google, OpenAI ni DeepSeek. Más en el [README](README.es.md).

*Hecho con ❤️ por el equipo de AgileForge.*
