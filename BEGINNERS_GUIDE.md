# 🔰 AgileForge v2 Beginner's Guide

🌐 **English** · [Español](MANUAL_NOVATOS.md)

> *A step-by-step guide to your AI agent command center, without the technical jargon.*

Welcome to **AgileForge v2**. If you have never used an agile workspace with AI agents, this guide is for you: you will learn everything you need from scratch, simply and directly.

---

## 1. 🔰 What is AgileForge?

Imagine a full team working next to you:
- 👑 **Managing Director:** the team lead. Give it an instruction and it reviews the board, decides and hands out the work.
- 📋 **Project Manager:** organizes the work and sets priorities.
- 🎨 **Creative Director & UX/UI:** designs clean interfaces and looks after usability.
- 🏛️ **Chief Architect:** picks the technologies and designs the structure.
- 💻 **Frontend Master:** builds screens, buttons and visual design.
- ⚙️ **Backend Master:** builds the database, APIs and server logic.
- 🛡️ **QA & Security:** checks that the code has no bugs or vulnerabilities.
- ⚖️ **Legal Counsel:** checks that you comply with the law (data protection, consumer rights, etc.).

Instead of copy-pasting huge prompts into a chat, **AgileForge gives these agents a real Scrum board**. They read the stories, claim them, report progress and meet to debate solutions with you, the human Product Owner.

---

## 2. ⚡ First run

1. **Open a terminal** in the project folder.
2. **Initialize everything:**
   ```bash
   php bin/migrate.php
   ```
   *This creates the local database and your secure token in `.env`.*
3. **Open the app:**
   - With XAMPP: `http://localhost/Open-to-code/` (or your folder name).
   - With plain PHP: run `php -S localhost:8080 router.php` and open `http://localhost:8080`. (Keep the `router.php`: PHP's built-in server ignores `.htaccess`, so without it anyone on your network could download your `.env` with the keys and your database.)

---

## 3. 🧠 Give your agents a brain (pick one)

Agents need an AI to answer. You have three options and you can mix them:

| Option | Cost | How |
|---|---|---|
| **Local AI** (Ollama / Colibri) | Free, 100% private | See section 7. |
| **Cloud models** (Claude, Gemini, GPT, DeepSeek) | Pay as you go | Paste your key in **⚙️ AI Engine → 🔑 API keys**. |
| **Plugin mode** (Claude Code) | What you already pay for Claude Code | Work with the agents from Claude Code (section 10). No keys needed. |

### Add an API key (2 minutes)
1. Get your key from the provider's console. The full guide, including what each key looks like, is in **[docs/API_KEYS.md](docs/API_KEYS.md)**.
2. In AgileForge click **⚙️ AI Engine** (top bar) and scroll to **🔑 API keys**.
3. Paste the key in its provider's row and click **Save**. It is tested automatically and you will see `✅ … answered in Xs`.

> ⚠️ **Google Gemini:** AI Studio shows you a `curl` example. That is **not** the key: the key is only the text after `x-goog-api-key:` (it usually starts with `AIza`).
>
> 🔒 Your keys are stored only in your PC's `.env` file, never uploaded to GitHub, and the browser only sees a hint (`sk-ant-…a1b2`). Don't share them in chats or screenshots.

### Choose which AI each agent uses
In the **🤖 Agent Team** tab, each card has an **AI engine for this role** selector: a specific model (for example *Claude Opus 5.5* for the Director), the **local AI** or *Inherit*. Below it you can see who is actually answering right now. If a role's key is missing, the local AI answers and the card tells you.

---

## 4. 👑 The Managing Director: give it instructions

At the top of the **Scrum Board** you will find the **Managing Director** panel:

1. Write what you want to achieve, as you would to a project lead. For example:
   - *"Prioritize what we need to launch online bookings this week and split the work."*
   - *"What is blocked? Decide the next steps."*
2. Click **Send instruction** (or `Ctrl + Enter`).
3. The Director reviews the board, decides, creates or reassigns stories, consults specialists when needed, and answers with **Decision**, **What I did**, **Next steps** and **Risks or questions**. Below the answer you will see every action it took on the board.

> With Claude it answers in seconds; with local AI it can take 1–3 minutes. The Director **never** marks stories as done: you approve that.

You can also talk to it from **💬 Ask Agents** (the chat) or, on any story, with **👑 Ask the Director to decide**.

---

## 5. 📋 The Scrum board

| Column | What it means | Who acts here |
| :--- | :--- | :--- |
| **1. Backlog** | Ideas, tasks and requirements to do. | You, the Director or the Project Manager. |
| **2. In Progress** | Work being done (the story is locked so nobody else steps on it). | An agent or you. |
| **3. DoD Review** | Finished work waiting for quality control. | The agent provides tests or diffs. |
| **4. Done** | Approved work. | **Only you or the QA role**. |

### Create your first story
1. Click **+ New Story**.
2. Write the title (e.g. *"Build the login screen"*), choose the agent, priority and points (1, 2, 3, 5, 8, 13).
3. Fill in the user story and the Definition of Done (what must be true to call it finished).
4. Click **Create story and generate prompt**. It appears in the Backlog and its mission prompt opens, ready to paste into Claude Code.

### Edit a story
Click the title of any card: you can change the agent, priority, points and texts, copy its prompt, ask the Director to decide, or delete it (🗑️).

---

## 6. 🌓 Day and night modes

- **Quick:** click **🌙 Night / ☀️ Day** in the top bar.
- **Palettes:** click **🎨 Theme** and choose from 6: *Cyber Obsidian*, *Electric Indigo*, *Neon Emerald*, *Midnight Violet* (night), *Solar Day* and *Clean Paper* (day). You can also choose the density (*Comfortable* or *Compact*).
- Your choice is saved in the browser.

---

## 7. 🦙 Local AI (free and private)

1. Install **Ollama** ([ollama.com](https://ollama.com)) and download a model:
   ```bash
   ollama pull gemma4:e4b
   ```
2. Open AgileForge: the **🦙 Local AI** button detects the model.
3. Turn it on: agents without their own API engine will answer **on your PC**. Zero cost and no data leaves your machine.

Powerful PC on Windows? You can use **Colibri** for bigger models: run `bin\colibri-instalar.cmd`, then `bin\colibri-crear-acceso-directo.cmd` to get the **"Colibri IA"** desktop shortcut.

---

## 8. 🌐 Language: Spanish and English

Click **🌐 ES | EN** in the top bar to switch the interface between Spanish and English (*Tablero Scrum ⇄ Scrum Board*, *Sala de Reuniones ⇄ Meeting Room*…). Agents answer in the language you write in.

---

## 9. 🎙️ Meeting room

1. Go to the **Meeting Room** (`Alt + 4`).
2. Pick a quick meeting or build your own: type (*Brainstorming*, *Architecture Council*, *Compliance*, *Daily*), topic and participants.
3. Each agent speaks in turn with its own AI (you will see the progress on screen) and at the end the Director writes the **minutes with the resolution and agreements**.
4. If you agree, click **📥 Convertir al Backlog** (*send to backlog*) and the agreements become stories in your Backlog.

---

## 10. 🤖 Working from Claude Code

You can use the whole agent team from **Claude Code** without any API key and without a local AI: Claude Code is the brain. You only use your normal Claude Code plan.

1. Make sure AgileForge is running (the board opens in your browser).
2. In Claude Code, type these two lines, one at a time:
   ```text
   /plugin marketplace add JSOTELD/agile-forge
   /plugin install agileforge@agileforge
   ```
   This installs the **AgileForge Control** plugin. Then type `/reload-plugins` (or open a new session).
3. Try the main commands:

| Command | What it does |
|---|---|
| `/agileforge:director <what you want>` | Talk to the Managing Director: it decides and hands out the work. |
| `/agileforge:equipo` | Shows the team: who does what and with which model. |
| `/agileforge:sprint` | Tells you how the sprint is going. |
| `/agileforge:tomar US-101` | The right specialist takes a story, builds it and sends it to review. |
| `/agileforge:dod US-101` | QA checks if the story is really finished. |

If something does not connect, ask Claude "run connection_info": it tells you which address it is trying. More details in [adapters/README.md](adapters/README.md) and in the [plugin guide](plugin/agileforge/README.md).

> 🛡️ **Safety rule:** an agent can never approve its own work. A story only becomes *Done* when you approve it or when the QA agent approves work done by someone else.

---

## 11. 👁️ Following progress (View)

Sometimes you, your boss or a client only want to **see** how things are going, without any risk of changing something by accident. For that there is **View**, in two forms:

- **The 👁️ View button:** in the top bar of AgileForge. It opens a page in a new tab with the sprint, who is working on what, meetings, decisions and recent activity. You can also open it directly at `vista.php` (for example `http://localhost/Open-to-code/vista.php`).
- **The AgileForge View plugin (for Claude Code):** if you only want to watch, install this one instead of Control:
  ```text
  /plugin marketplace add JSOTELD/agile-forge
  /plugin install agileforge-vista@agileforge
  ```
  Then use `/agileforge-vista:estado` for a 5-line summary, or `/agileforge-vista:tablero`, `/agileforge-vista:reuniones`, `/agileforge-vista:decisiones` and `/agileforge-vista:actividad`.

Good to know:
- View is **read-only**: nobody can change the board from it.
- It uses a special **read-only token** (`AGILEFORGE_VIEW_TOKEN` in your `.env`) that is created automatically. If you want someone to watch, share only that one, **never** your main token.
- The Control and View plugins are **not installed together**: pick one. If you want to switch, uninstall the other first.

---

## 12. ⌨️ Keyboard shortcuts

| Shortcut | Action |
| :--- | :--- |
| <kbd>Ctrl</kbd> + <kbd>K</kbd> | Quick search for stories and commands |
| <kbd>Ctrl</kbd> + <kbd>Enter</kbd> | Send the instruction to the Director |
| <kbd>Alt</kbd> + <kbd>M</kbd> | Open this guide on screen |
| <kbd>Alt</kbd> + <kbd>1</kbd> … <kbd>5</kbd> | Board · UX/UI · Legal · Meetings · Team |
| <kbd>Esc</kbd> | Close any window |

---

## 13. 🆘 If something fails

| Problem | Fix |
|---|---|
| An agent answers "⚠️ no engine" | Add a key in AI Engine or turn on the local AI (not needed if you use the Claude Code plugin). |
| The plugin cannot connect | Make sure AgileForge is running and ask Claude "run connection_info" to see which address it uses. |
| `HTTP 401` when testing a key | The key was copied wrong or revoked: copy it again or create a new one. |
| The Director is slow | Normal with local AI (1–3 min). With a Claude key it answers in seconds. |
| Anything else | Check `data/logs/agileforge.log` and the [API keys guide](docs/API_KEYS.md#7-troubleshooting). |

---

> ⚠️ AI agents (including Legal Counsel) give guidance and do not replace professional advice. Costs of third-party APIs are yours. AgileForge is independent, MIT software provided "as is" and is not affiliated with Anthropic, Google, OpenAI or DeepSeek. More in the [README](README.md).

*Made with ❤️ by the AgileForge team.*
