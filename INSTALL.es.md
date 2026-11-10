# 📦 Guía Canónica de Instalación de AgileForge

🌐 [English](INSTALL.md) · **Español**

Esta es la única fuente canónica de instalación para **AgileForge v2**. Sigue estas instrucciones para configurar el entorno de forma determinista y reproducible en Windows, macOS y Linux.

---

## 1. Requisitos Previos

Antes de instalar, asegúrate de tener instalado el siguiente software en tu equipo:

1. **Node.js:**
   - **Obligatorio:** `>= 22.13.0` (soporte nativo para `node:sqlite` sin flags experimentales).
   - **Recomendado:** Última versión LTS activa.
   - Descarga: [nodejs.org](https://nodejs.org/)
2. **PHP (Fase de transición híbrida):**
   - **Obligatorio:** `>= 8.0` (con las extensiones `pdo_sqlite`, `mbstring` y `curl` habilitadas).
   - Descarga: [php.net/downloads](https://www.php.net/downloads) o [XAMPP](https://www.apachefriends.org/)
3. **Git:**
   - Descarga: [git-scm.com](https://git-scm.com/)

---

## 2. Instalación Rápida en 1 Paso

Clona el repositorio y ejecuta el script automatizado e idempotente:

```bash
git clone https://github.com/JSOTELD/Agile-Forge.git
cd Agile-Forge
npm run setup
```

### Qué realiza `npm run setup` de forma automática:
1. Valida la versión de Node.js (`>= 22.13.0`).
2. Localiza PHP (`AGILEFORGE_PHP`, PATH o rutas habituales de XAMPP/sistema).
3. Crea el archivo `.env` con tokens criptográficos aleatorios y seguros (nunca sobrescribe un `.env` existente ni regenera tokens válidos).
4. Crea las carpetas de almacenamiento local (`data/`, `data/backup/`, `data/logs/`).
5. Instala las dependencias en `mcp-server/` y compila los bundles de plugin MCP.
6. Inicializa el esquema de SQLite y aplica las migraciones de forma aditiva sin alterar datos existentes.

---

## 3. Diagnóstico de Salud del Entorno

Verifica el estado del entorno en cualquier momento con el comando de diagnóstico (solo lectura):

```bash
npm run doctor
```

Este comando audita:
- Versión de Node.js compatible.
- Ejecutable de PHP y extensiones requeridas (`pdo_sqlite`, `mbstring`, `curl`).
- Permisos de base de datos SQLite y activación de modo WAL.
- Compilación del servidor MCP e integridad de los bundles de plugin.
- Presencia y longitud segura de tokens en `.env` (nunca imprime los tokens).
- Estado de claves de proveedores de IA (informativo; opcionales en modo plugin).
- Disponibilidad del puerto del panel web.

---

## 4. Iniciar el Panel Web

Inicia el servidor local embebido con:

```bash
npm start
```

Abre tu navegador en:
👉 **`http://127.0.0.1:8080`**

*(Presiona `Ctrl+C` en la terminal para detener el servidor).*

---

## 5. Instalar o Usar el Plugin en Claude Code

AgileForge distribuye dos plugins oficiales a través de su marketplace integrado:

### Dentro de Claude Code:
Agrega el marketplace:
```text
/plugin marketplace add JSOTELD/agile-forge
```
Instala el de Control o el de Vista:
```text
/plugin install agileforge@agileforge          # Control (equipo completo)
/plugin install agileforge-vista@agileforge    # Vista (solo lectura, en lugar del de control)
```

### Desarrollo local (sin instalar):
Inicia Claude Code cargando directamente la carpeta del plugin:
```bash
claude --plugin-dir ./plugin/agileforge
```

---

## 6. Pruebas de Contrato

Ejecuta la suite completa de pruebas de contrato (API, MCP y repositorio) sobre una base de datos aislada temporal:

```bash
npm test
```

*(En Windows con XAMPP en otra unidad, define `AGILEFORGE_PHP=D:\xampp\php\php.exe` o `C:\xampp\php\php.exe`).*

---

## 7. Preguntas Frecuentes y Solución de Problemas

### PHP no se encuentra en el PATH
Define la variable de entorno `AGILEFORGE_PHP` con la ruta completa al binario `php`:
- **Windows (PowerShell):** `$env:AGILEFORGE_PHP = "D:\xampp\php\php.exe"`
- **Windows (CMD):** `set AGILEFORGE_PHP=D:\xampp\php\php.exe`
- **macOS / Linux:** `export AGILEFORGE_PHP=/usr/bin/php`
También puedes agregar `AGILEFORGE_PHP=/ruta/a/php` en tu archivo `.env`.

### Faltan extensiones de PHP (`pdo_sqlite`, `mbstring`, `curl`)
Abre tu archivo `php.ini` (localízalo con `php --ini`) y descomenta o agrega:
```ini
extension=pdo_sqlite
extension=mbstring
extension=curl
```
Vuelve a ejecutar `npm run doctor` para confirmar.

### El puerto 8080 está ocupado
Define un puerto alternativo mediante `AGILEFORGE_PORT`:
```bash
# Windows PowerShell
$env:AGILEFORGE_PORT = "8085"; npm start

# macOS / Linux
AGILEFORGE_PORT=8085 npm start
```

### Permisos de SQLite o Bloqueos
AgileForge opera bajo modo WAL con timeout de contención configurado. Asegúrate de que la carpeta `data/` y el archivo `data/agileforge.sqlite` cuenten con permisos de lectura y escritura para el usuario que ejecuta el proceso.

