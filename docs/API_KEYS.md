# 🔑 API Keys Guide

🌐 **English** · [Español](API_KEYS.es.md)

> 💡 **Using the Claude Code plugins (AgileForge Control or AgileForge View)? You do not need any key.** Claude Code is the brain and uses your own Claude Code plan. This guide is only for the web app's own AI engine (server-side API mode). See [Claude Code plugins](../README.md#-claude-code-plugins-agileforge-control--agileforge-view).

AgileForge can answer with cloud models (Claude, Gemini, GPT, DeepSeek), with a **local AI** running on your PC (Ollama / Colibri), or with no AI at all (plugin mode, where Claude Code or another assistant does the work). **API keys are optional**: add only the providers you want to use.

---

## 1. Which keys does AgileForge support?

| Provider | Variable in `.env` | Where to get it | What the key looks like | Models in AgileForge |
|---|---|---|---|---|
| 🟣 Anthropic (Claude) | `ANTHROPIC_API_KEY` | [Claude Console → API keys](https://platform.claude.com/settings/keys) | `sk-ant-…` | Claude Opus 5.5, Sonnet 5.5, Haiku 5.5, Fable 5.1 |
| ✨ Google (Gemini) | `GEMINI_API_KEY` | [Google AI Studio → API keys](https://ai.google.dev/gemini-api/docs/api-key) | usually `AIza…` | Gemini 3 Flash, Gemini 3.1 Pro, Gemini 3.1 Flash-Lite |
| 🟢 OpenAI (GPT) | `OPENAI_API_KEY` | [OpenAI Platform → API keys](https://help.openai.com/en/articles/4936850-where-do-i-find-my-openai-api-key) | `sk-…` (often `sk-proj-…`) | GPT-6 Astra, GPT-6.1 Sol, GPT-6.1 Terra, GPT-6 Luna |
| 🐳 DeepSeek | `DEEPSEEK_API_KEY` | [DeepSeek Platform → API keys](https://platform.deepseek.com/api_keys) | `sk-…` | DeepSeek V4.1 Flash, DeepSeek V4 Pro |
| 🦙 Ollama / 🐦 Colibri | — | Runs on your PC | No key needed | Whatever models you installed |

> 💡 **Recommendation:** one **Anthropic** key is enough to start. The **Director** (the team lead agent) works best with Claude Opus 5.5, and the other roles can use Claude Sonnet / Haiku or your local AI.

---

## 2. Get your key

### 🟣 Anthropic (Claude)
1. Sign in to the [Claude Console](https://platform.claude.com) (create an account if you don't have one).
2. Open the [API keys page](https://platform.claude.com/settings/keys) and click **Create key**.
3. Give it a name (e.g. `agileforge`), choose an expiration and, **important**, pick a **workspace** (for example *Default*). A key that is not tied to a workspace requires every request to say which workspace to use.
4. Copy the key right away: the Console shows the full `sk-ant-…` value **only once**.
5. Recommended: set a monthly spend limit for the workspace in the Console.

### ✨ Google (Gemini)
1. Open [Google AI Studio](https://aistudio.google.com) and go to **API keys** (new users get a project and a key created automatically).
2. Click **Create API key** if you need a new one.
3. **The cURL example is not the key.** AI Studio shows sample code like this:
   ```bash
   curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent" \
     -H "x-goog-api-key: AIzaSyD…your-key…" \
     -H "Content-Type: application/json" \
     -X POST -d '{ "contents": [ … ] }'
   ```
   Copy **only** the value after `x-goog-api-key:` (or after `key=` if it appears in the URL), without quotes or spaces.
4. Note: the Gemini API rejects **unrestricted standard** keys. Keys created in AI Studio today are fine; if an old key fails, create a new one in AI Studio or restrict it to the Gemini API.

### 🟢 OpenAI (GPT)
1. Sign in to the [OpenAI Platform](https://platform.openai.com) and open your project's **API keys** page.
2. Click **Create new secret key** and copy it (it is shown only once).
3. Recommended: set an expiration date and a project spend limit.

### 🐳 DeepSeek
1. Sign in to the [DeepSeek Platform](https://platform.deepseek.com) and open [API keys](https://platform.deepseek.com/api_keys).
2. Create a key (it starts with `sk-`) and copy it.
3. DeepSeek uses peak / off-peak pricing: off-peak costs half.

---

## 3. Add the key to AgileForge

### Option A — From the app (recommended)
1. Open AgileForge (`http://localhost/Open-to-code/` or your URL).
2. In the top bar, click **⚙️ AI Engine** (*Motor IA*).
3. Scroll to **🔑 API keys**.
4. Paste the key in the row of its provider and click **Save**.
5. AgileForge saves it and **tests the connection** automatically: you will see `✅ <model> answered in Xs`. You can repeat the check anytime with **Test**.
6. **Claude only:** if your key is *not* tied to a workspace, paste your workspace ID (`wrkspc_…`, from the Console → *Workspaces*) in the small field under the Anthropic row and click **Save**.

To replace a key, paste the new one and save. To remove it, click **✕**.

### Option B — Editing `.env`
Open the `.env` file in the project root (create it from `.env.example` if it doesn't exist) and fill in the lines you need:

```ini
ANTHROPIC_API_KEY=sk-ant-...
# Only if your Anthropic key is not tied to a workspace:
ANTHROPIC_WORKSPACE_ID=wrkspc_...
GEMINI_API_KEY=AIza...
OPENAI_API_KEY=sk-...
DEEPSEEK_API_KEY=sk-...
```

No restart is needed: PHP reads `.env` on every request.

---

## 4. Choose which model each role uses

1. Go to the **🤖 Agent Team** tab (*Equipo de Agentes*).
2. Each role card has an **AI engine for this role** selector:
   - a specific **API model** (for example *Claude Opus 5.5* for the Director, *Gemini 3 Flash* for the Creative Director),
   - **🏠 Active local AI** (Ollama / Colibri), or
   - **Inherit** (global engine or local AI).
3. Under the selector you can see who will **actually** answer right now.

How AgileForge picks the engine for each role (first available wins):

1. The model assigned to the role, if its key is configured (or the local AI, if the role uses *local AI* and it is on).
2. The **local AI**, if it is turned on.
3. The **global engine** set in *AI Engine*, if it has a key.
4. None: the app says so clearly; it never makes up answers.

So you can safely assign Claude to the Director before adding the key: until you add it, the Director uses your local AI and the card tells you why.

---

## 5. Security

- Keys are stored **only** in the `.env` file on your machine. `.env` is in `.gitignore`, so it is **never** uploaded to GitHub.
- The browser never receives your keys: the app only shows a hint such as `sk-ant-…a1b2`.
- The AgileForge API only accepts requests from `localhost` with the local token from `.env`.
- Never paste keys into chats, issues, pull requests, screenshots or shared documents.
- If a key leaks: create a new one, save it in AgileForge, then disable or delete the old one in the provider's console.
- Set spend limits in each provider's console.

---

## 6. Prices (per 1M tokens, checked 2026-11-08)

| Model | Input | Output |
|---|---|---|
| Claude Fable 5.1 | $10 | $50 |
| Claude Opus 5.5 | $4 | $20 |
| Claude Sonnet 5.5 | $2 | $10 |
| Claude Haiku 5.5 | from $0.10 | from $0.50 |
| Gemini 3.1 Pro (<200k tokens) | $2 | $12 |
| Gemini 3 Flash (has a free tier) | $0.50 | $3 |
| Gemini 3.1 Flash-Lite | $0.25 | $1.50 |
| GPT-6 Astra | $10 | $50 |
| GPT-6.1 Sol | $2 | $10 |
| GPT-6.1 Terra | $1 | $5 |
| GPT-6 Luna | $0.10 | $0.50 |
| DeepSeek V4 Pro (peak) | $1.32 | $3.96 |
| DeepSeek V4.1 Flash (peak) | $0.30 | $1.20 |
| Ollama / Colibri (local) | $0 | $0 |

Prices change: check each provider's pricing page. AgileForge estimates costs from `config/models.json` and shows them under **AI Investment** (*Inversión IA*) in the sprint banner.

---

## 7. Troubleshooting

| Message | What to do |
|---|---|
| `HTTP 401 … invalid key` | The key is wrong, incomplete or revoked. Copy it again (no spaces) or create a new one. |
| Claude asks for a workspace (`anthropic-workspace-id`) | Your key is not tied to a workspace: add the `wrkspc_…` ID under the Anthropic row, or create a new key tied to a workspace. |
| Gemini rejects the key (unrestricted key) | Create a new key in Google AI Studio or restrict the existing one to the Gemini API. |
| `HTTP 404` / model not found | The model was retired. Update `config/models.json` (AgileForge reassigns roles to valid models automatically when the file changes). |
| `HTTP 429` | Rate limit or no credit: wait, add credit, or raise your tier. |
| A role still answers with the local AI | Its provider has no key yet, or role routing is off (*AI Engine → 🎯 Role Routing*). The role card explains which case it is. |
| Other errors | See `data/logs/agileforge.log`. |

---

## 8. For developers

API actions (`POST api/tasks.php`, local token required):

| Action | Body | What it does |
|---|---|---|
| `save_api_key` | `{ provider, api_key }` | Validates and writes the key to `.env`. Returns providers with `api_key_configured` and `key_hint`. |
| `remove_api_key` | `{ provider }` | Removes the key from `.env`. |
| `test_provider` | `{ provider, model? }` | Makes a tiny real call and returns model, text and seconds. |
| `save_anthropic_workspace` | `{ workspace_id }` | Saves `ANTHROPIC_WORKSPACE_ID` (empty string removes it). |
| `update_role_engine` | `{ role_id, ai_provider, ai_model }` | Assigns a model to a role (`ai_provider: "local"` = local AI, empty = inherit). |

The provider and model catalog (endpoints, prices, defaults) lives in `config/models.json`.
