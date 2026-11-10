# 📦 AgileForge Installation Guide

🌐 **English** · [Español](INSTALL.es.md)

This is the single canonical installation guide for **AgileForge v2**. Follow these instructions to set up the environment deterministically across Windows, macOS, and Linux.

---

## 1. Prerequisites

Before installing, ensure the following software is installed on your machine:

1. **Node.js:**
   - **Required:** `>= 22.13.0` (Native `node:sqlite` support without experimental flags).
   - **Recommended:** Latest active LTS release.
   - Download: [nodejs.org](https://nodejs.org/)
2. **PHP (Hybrid transition phase):**
   - **Required:** `>= 8.0` (with `pdo_sqlite`, `mbstring`, and `curl` extensions enabled).
   - Download: [php.net/downloads](https://www.php.net/downloads) or [XAMPP](https://www.apachefriends.org/)
3. **Git:**
   - Download: [git-scm.com](https://git-scm.com/)

---

## 2. Quick Start (1-Step Setup)

Clone the repository and run the automated, idempotent setup script:

```bash
git clone https://github.com/JSOTELD/Agile-Forge.git
cd Agile-Forge
npm run setup
```

### What `npm run setup` does automatically:
1. Validates Node.js version (`>= 22.13.0`).
2. Locates PHP (`AGILEFORGE_PHP`, PATH, or standard XAMPP/system directories).
3. Creates `.env` with cryptographically secure random API and View tokens (never overwrites an existing `.env`).
4. Ensures local storage directories exist (`data/`, `data/backup/`, `data/logs/`).
5. Installs `mcp-server/` dependencies and compiles TypeScript bundles.
6. Initializes SQLite schema and migrations idempotently without altering existing data.

---

## 3. Environment Health Check

Verify your setup at any time with the read-only diagnostics command:

```bash
npm run doctor
```

This verifies:
- Node.js runtime and version.
- PHP binary and required extensions (`pdo_sqlite`, `mbstring`, `curl`).
- SQLite database permissions and WAL mode.
- MCP server compilation and bundle integrity.
- Token security and presence in `.env` (tokens are never displayed).
- AI provider key status (informative; keys are optional for plugin mode).
- Web panel port availability.

---

## 4. Running the Web Panel

Start the local web dashboard using PHP's built-in server:

```bash
npm start
```

Access the panel in your browser at:
👉 **`http://127.0.0.1:8080`**

*(Press `Ctrl+C` in your terminal to stop the server).*

---

## 5. Installing the Plugin in Claude Code

AgileForge provides two official Claude Code plugins via its self-contained marketplace:

### AgileForge Control (Full multi-agent team & MCP tools)
```bash
# From GitHub marketplace
claude plugin install agileforge

# Or for local development from the repository root:
claude plugin dev ./plugin/agileforge
```

### AgileForge View (Read-only observer)
```bash
# From GitHub marketplace
claude plugin install agileforge-vista

# Or for local development:
claude plugin dev ./plugin/agileforge-vista
```

---

## 6. Running Contract Tests

Run the full test suite (API, MCP, and repository integrity) on a temporary isolated database:

```bash
npm test
```

*(On Windows with custom XAMPP paths, set `AGILEFORGE_PHP=C:\xampp\php\php.exe` or `D:\xampp\php\php.exe`).*

---

## 7. Troubleshooting & FAQ

### PHP is not found or not in PATH
Define the `AGILEFORGE_PHP` environment variable with the absolute path to your `php` executable:
- **Windows (PowerShell):** `$env:AGILEFORGE_PHP = "D:\xampp\php\php.exe"`
- **Windows (CMD):** `set AGILEFORGE_PHP=D:\xampp\php\php.exe`
- **macOS / Linux:** `export AGILEFORGE_PHP=/usr/bin/php`
You can also add `AGILEFORGE_PHP=/path/to/php` inside your `.env` file.

### Missing PHP Extensions (`pdo_sqlite`, `mbstring`, `curl`)
Open your `php.ini` file (found via `php --ini`) and uncomment or add:
```ini
extension=pdo_sqlite
extension=mbstring
extension=curl
```
Then rerun `npm run doctor` to confirm.

### Port 8080 is already in use
Specify an alternate port with `AGILEFORGE_PORT`:
```bash
# Windows PowerShell
$env:AGILEFORGE_PORT = "8085"; npm start

# macOS / Linux
AGILEFORGE_PORT=8085 npm start
```

### Database Permissions / SQLite Locks
AgileForge operates in WAL mode with active busy timeouts. Ensure the `data/` folder and `data/agileforge.sqlite` have read and write permissions for the user running the process.
