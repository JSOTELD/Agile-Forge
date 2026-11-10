# 🔑 Guía de claves de API

🌐 [English](API_KEYS.md) · **Español**

> 💡 **¿Usas los plugins de Claude Code (AgileForge Control o AgileForge Vista)? No necesitas ninguna clave.** Claude Code es el cerebro y usa tu propio plan de Claude Code. Esta guía es solo para el motor de IA propio de la web (modo API en el servidor). Ver [Plugins de Claude Code](../README.es.md#-plugins-de-claude-code-agileforge-control-y-agileforge-vista).

AgileForge puede responder con modelos en la nube (Claude, Gemini, GPT, DeepSeek), con una **IA local** en tu PC (Ollama / Colibri) o sin IA (modo plugin, donde Claude Code u otro asistente hace el trabajo). **Las claves de API son opcionales**: agrega solo los proveedores que quieras usar.

---

## 1. ¿Qué claves admite AgileForge?

| Proveedor | Variable en `.env` | Dónde se consigue | Cómo se ve la clave | Modelos en AgileForge |
|---|---|---|---|---|
| 🟣 Anthropic (Claude) | `ANTHROPIC_API_KEY` | [Consola de Claude → API keys](https://platform.claude.com/settings/keys) | `sk-ant-…` | Claude Opus 5.5, Sonnet 5.5, Haiku 5.5, Fable 5.1 |
| ✨ Google (Gemini) | `GEMINI_API_KEY` | [Google AI Studio → Claves de API](https://ai.google.dev/gemini-api/docs/api-key?hl=es-419) | normalmente `AIza…` | Gemini 3 Flash, Gemini 3.1 Pro, Gemini 3.1 Flash-Lite |
| 🟢 OpenAI (GPT) | `OPENAI_API_KEY` | [OpenAI Platform → API keys](https://help.openai.com/es-419/articles/4936850-where-do-i-find-my-openai-api-key) | `sk-…` (a menudo `sk-proj-…`) | GPT-6 Astra, GPT-6.1 Sol, GPT-6.1 Terra, GPT-6 Luna |
| 🐳 DeepSeek | `DEEPSEEK_API_KEY` | [DeepSeek Platform → API keys](https://platform.deepseek.com/api_keys) | `sk-…` | DeepSeek V4.1 Flash, DeepSeek V4 Pro |
| 🦙 Ollama / 🐦 Colibri | — | Corren en tu PC | No necesitan clave | Los modelos que instales |

> 💡 **Recomendación:** para empezar basta con una clave de **Anthropic**. El **Director** (el agente jefe del equipo) funciona mejor con Claude Opus 5.5, y los demás roles pueden usar Claude Sonnet / Haiku o tu IA local.

---

## 2. Consigue tu clave

### 🟣 Anthropic (Claude)
1. Entra a la [consola de Claude](https://platform.claude.com) (crea una cuenta si no tienes).
2. Abre la [página de claves](https://platform.claude.com/settings/keys) y pulsa **Create key**.
3. Ponle un nombre (por ejemplo `agileforge`), elige la caducidad y, **muy importante**, selecciona un **workspace** (por ejemplo *Default*). Una clave sin workspace obliga a indicar el workspace en cada petición.
4. Copia la clave en ese momento: la consola muestra el valor completo `sk-ant-…` **una sola vez**.
5. Recomendado: pon un límite de gasto mensual al workspace en la consola.

### ✨ Google (Gemini)
1. Abre [Google AI Studio](https://aistudio.google.com) y entra a **Claves de API** (a los usuarios nuevos se les crea un proyecto y una clave automáticamente).
2. Pulsa **Crear clave de API** si necesitas una nueva.
3. **El cURL no es la clave.** AI Studio te muestra un ejemplo de código como este:
   ```bash
   curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent" \
     -H "x-goog-api-key: AIzaSyD…tu-clave…" \
     -H "Content-Type: application/json" \
     -X POST -d '{ "contents": [ … ] }'
   ```
   Copia **solo** el valor que va después de `x-goog-api-key:` (o después de `key=` si aparece en la dirección), sin comillas ni espacios.
4. Ojo: la API de Gemini rechaza las claves **estándar sin restricciones**. Las claves que se crean hoy en AI Studio funcionan; si una clave antigua falla, crea otra en AI Studio o restríngela a la API de Gemini.

### 🟢 OpenAI (GPT)
1. Entra a [OpenAI Platform](https://platform.openai.com) y abre la página **API keys** de tu proyecto.
2. Pulsa **Create new secret key** y cópiala (solo se muestra una vez).
3. Recomendado: ponle fecha de caducidad y un límite de gasto al proyecto.

### 🐳 DeepSeek
1. Entra a [DeepSeek Platform](https://platform.deepseek.com) y abre [API keys](https://platform.deepseek.com/api_keys).
2. Crea una clave (empieza por `sk-`) y cópiala.
3. DeepSeek cobra distinto en hora punta y fuera de ella: fuera de hora punta cuesta la mitad.

---

## 3. Agrega la clave en AgileForge

### Opción A — Desde la aplicación (recomendada)
1. Abre AgileForge (`http://localhost/Open-to-code/` o tu dirección).
2. En la barra superior pulsa **⚙️ Motor IA**.
3. Baja hasta **🔑 Claves de API**.
4. Pega la clave en la fila de su proveedor y pulsa **Guardar**.
5. AgileForge la guarda y **prueba la conexión** sola: verás `✅ <modelo> respondió en Xs`. Puedes repetir la prueba cuando quieras con **Probar**.
6. **Solo Claude:** si tu clave *no* está asociada a un workspace, pega el ID del workspace (`wrkspc_…`, lo ves en la consola → *Workspaces*) en el campo pequeño bajo la fila de Anthropic y pulsa **Guardar**.

Para reemplazar una clave, pega la nueva y guarda. Para quitarla, pulsa **✕**.

### Opción B — Editando `.env`
Abre el archivo `.env` de la raíz del proyecto (créalo a partir de `.env.example` si no existe) y completa las líneas que necesites:

```ini
ANTHROPIC_API_KEY=sk-ant-...
# Solo si tu clave de Anthropic no está asociada a un workspace:
ANTHROPIC_WORKSPACE_ID=wrkspc_...
GEMINI_API_KEY=AIza...
OPENAI_API_KEY=sk-...
DEEPSEEK_API_KEY=sk-...
```

No hace falta reiniciar nada: PHP lee el `.env` en cada petición.

---

## 4. Elige qué modelo usa cada rol

1. Ve a la pestaña **🤖 Equipo de Agentes**.
2. Cada tarjeta de rol tiene un selector **Motor de IA de este rol**:
   - un **modelo de API** concreto (por ejemplo *Claude Opus 5.5* para el Director, *Gemini 3 Flash* para el Director Creativo),
   - **🏠 IA local activa** (Ollama / Colibri), o
   - **Heredar** (motor global o IA local).
3. Debajo del selector ves quién responde **de verdad** en este momento.

Cómo elige AgileForge el motor de cada rol (gana el primero disponible):

1. El modelo asignado al rol, si su clave está configurada (o la IA local, si el rol usa *IA local* y está encendida).
2. La **IA local**, si está activada.
3. El **motor global** de *Motor IA*, si tiene clave.
4. Ninguno: la aplicación lo dice claramente; nunca inventa respuestas.

Por eso puedes asignar Claude al Director antes de tener la clave: mientras no la agregues, el Director usa tu IA local y la tarjeta te explica por qué.

---

## 5. Seguridad

- Las claves se guardan **solo** en el archivo `.env` de tu equipo. `.env` está en `.gitignore`, así que **nunca** se sube a GitHub.
- El navegador nunca recibe tus claves: la aplicación solo muestra una pista como `sk-ant-…a1b2`.
- La API de AgileForge solo acepta peticiones desde `localhost` con el token local del `.env`.
- Nunca pegues claves en chats, issues, pull requests, capturas de pantalla ni documentos compartidos.
- Si una clave se filtra: crea una nueva, guárdala en AgileForge y luego desactiva o borra la antigua en la consola del proveedor.
- Configura límites de gasto en la consola de cada proveedor.

---

## 6. Precios (por millón de tokens, consultados el 2026-11-08)

| Modelo | Entrada | Salida |
|---|---|---|
| Claude Fable 5.1 | $10 | $50 |
| Claude Opus 5.5 | $4 | $20 |
| Claude Sonnet 5.5 | $2 | $10 |
| Claude Haiku 5.5 | desde $0.10 | desde $0.50 |
| Gemini 3.1 Pro (<200k tokens) | $2 | $12 |
| Gemini 3 Flash (tiene nivel gratuito) | $0.50 | $3 |
| Gemini 3.1 Flash-Lite | $0.25 | $1.50 |
| GPT-6 Astra | $10 | $50 |
| GPT-6.1 Sol | $2 | $10 |
| GPT-6.1 Terra | $1 | $5 |
| GPT-6 Luna | $0.10 | $0.50 |
| DeepSeek V4 Pro (hora punta) | $1.32 | $3.96 |
| DeepSeek V4.1 Flash (hora punta) | $0.30 | $1.20 |
| Ollama / Colibri (local) | $0 | $0 |

Los precios cambian: revisa la página de precios de cada proveedor. AgileForge estima los costos con `config/models.json` y los muestra en **Inversión IA**, en el banner del sprint.

---

## 7. Solución de problemas

| Mensaje | Qué hacer |
|---|---|
| `HTTP 401 … invalid key` | La clave está mal, incompleta o revocada. Cópiala de nuevo (sin espacios) o crea otra. |
| Claude pide un workspace (`anthropic-workspace-id`) | Tu clave no está asociada a un workspace: agrega el ID `wrkspc_…` bajo la fila de Anthropic, o crea una clave asociada a un workspace. |
| Gemini rechaza la clave (clave sin restricciones) | Crea una clave nueva en Google AI Studio o restringe la actual a la API de Gemini. |
| `HTTP 404` / modelo no encontrado | El modelo fue retirado. Actualiza `config/models.json` (AgileForge reasigna los roles a modelos vigentes automáticamente cuando el archivo cambia). |
| `HTTP 429` | Límite de uso o sin saldo: espera, recarga saldo o sube de nivel. |
| Un rol sigue respondiendo con la IA local | Su proveedor aún no tiene clave, o el enrutamiento por rol está apagado (*Motor IA → 🎯 Enrutamiento Especializado por Rol*). La tarjeta del rol te dice cuál es el caso. |
| Otros errores | Revisa `data/logs/agileforge.log`. |

---

## 8. Para desarrolladores

Acciones de la API (`POST api/tasks.php`, requieren el token local):

| Acción | Cuerpo | Qué hace |
|---|---|---|
| `save_api_key` | `{ provider, api_key }` | Valida y escribe la clave en `.env`. Devuelve los proveedores con `api_key_configured` y `key_hint`. |
| `remove_api_key` | `{ provider }` | Quita la clave del `.env`. |
| `test_provider` | `{ provider, model? }` | Hace una llamada real mínima y devuelve modelo, texto y segundos. |
| `save_anthropic_workspace` | `{ workspace_id }` | Guarda `ANTHROPIC_WORKSPACE_ID` (cadena vacía para quitarlo). |
| `update_role_engine` | `{ role_id, ai_provider, ai_model }` | Asigna un modelo a un rol (`ai_provider: "local"` = IA local, vacío = heredar). |

El catálogo de proveedores y modelos (endpoints, precios, valores por defecto) está en `config/models.json`.
