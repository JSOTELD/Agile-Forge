# AgileForge — Instrucciones para Asistentes de Código / Coding Agent Instructions

> **IMPORTANTE / IMPORTANT:**
> Para instalar o configurar este proyecto, **NO improvises comandos ni uses herramientas externas no especificadas**. Lee y ejecuta estrictamente las instrucciones de `INSTALL.md`.
> *To install or configure this project, **DO NOT improvise commands or use unspecified external tools**. Strictly read and follow the instructions in `INSTALL.md`.*

## Reglas Innegociables de Desarrollo / Non-Negotiable Development Rules

1. **Aislamiento de la Base de Datos:**
   - **NUNCA** ejecutes pruebas ni scripts destructivos contra `data/agileforge.sqlite`.
   - Utiliza siempre la variable de entorno `AGILEFORGE_DB_PATH` apuntando a una base de datos temporal (ej. en carpeta temporal del sistema).

2. **Verificación Previa a Commits:**
   - Ejecuta `npm test` antes de cada commit. Todo commit debe mantener las pruebas en verde (0 fallos).

3. **Mantenimiento de Bundles MCP:**
   - **NO** edites manualmente los bundles distribuidos (`plugin/agileforge/server/agileforge-mcp.mjs` ni `plugin/agileforge-vista/server/agileforge-vista-mcp.mjs`).
   - Edita el código fuente en `mcp-server/src/` y genera los bundles ejecutando:
     ```bash
     cd mcp-server && npm run build && npm run bundle
     ```
