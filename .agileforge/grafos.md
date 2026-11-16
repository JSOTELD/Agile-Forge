---
estado: aprobada          # borrador | aprobada. El Curador de grafos solo construye con "aprobada", y solo David la cambia.
aprobada_por: jsoto (bin\grafo-instalar.cmd)
fecha: 2026-11-15
---

# Receta de grafos · AgileForge

> Receta del Curador de grafos (v0, experimental) para el propio AgileForge, hecha con la plantilla
> `plugin/agileforge/templates/grafos.md`. Probada con graphify 0.9.109 en una copia del repo: 81 archivos de
> código, 965 nodos, 2633 aristas y 58 comunidades en ~3 s, con **0 tokens**.

## 1. Los dos grafos

| grafo | para quién | dónde | lo genera |
|---|---|---|---|
| **Grafo de la IA** | los agentes | `graphify-out/` (`graph.json`, `GRAPH_REPORT.md`, `wiki/index.md`) | graphify |
| **Grafo bibliográfico** | David y la comunidad | `.agileforge/vault/` | graphify (`codigo/`) + `bitacora.mjs` (`bitacora/`) + fichas (`fuentes/`) |

## 2. Qué se escanea
- **Raíz:** `.`
- **Excluir** (van a `.graphifyignore`; graphify ya respeta `.gitignore`: `.env`, `data/*.sqlite`, `node_modules/`, `scratch/`…):
  ```text
  graphify-out/
  .agileforge/vault/codigo/
  .agileforge/vault/.obsidian/
  .agileforge/vault/.agileforge-bitacora.json
  plugin/agileforge/server/
  plugin/agileforge-vista/server/
  mcp-server/dist/
  mcp-server/package.json
  mcp-server/package-lock.json
  mcp-server/tsconfig.json
  package.json
  package-lock.json
  .github/
  .claude-plugin/
  assets/css/app.css
  assets/brand/
  assets/fonts/
  data/
  ```
  Los bundles (`server/*.mjs`, `mcp-server/dist/`), `app.css` y los manifiestos JSON/YAML de infraestructura (`package.json`, `tsconfig.json`, `.github/`) meten nodos de configuración y minificados sin valor de dominio. El código fuente del MCP está en `mcp-server/src/`.

## 3. Extracción semántica
- **Motor:** `ninguno` (solo código, 0 tokens). Para pasar a `ollama` hace falta `graphifyy[ollama]` y Ollama encendido.
- Si algún día se activa un motor, excluir además `CHANGELOG.md` (largo y repetitivo).

## 4. Vault de Obsidian
- **Carpeta del vault:** `.agileforge/vault/`
- **Proyecto del tablero:** `agile-forge` (el proyecto del tablero cuya carpeta es este repo; con «el activo», la bitácora copiaba el proyecto que estuviera abierto, p. ej. Radar de Productos Ganadores).
- **¿Se versiona en git?** `no` (ya está en `.gitignore`): la bitácora copia historias, comentarios y decisiones del tablero, igual que `data/team.json`, que tampoco se publica.

## 5. Cuándo se actualiza
- Tras cada historia aprobada que toque código y antes de `start_sprint` (lo pide el Director).
- Opcional, lo decide David: `graphify hook install` (reconstruye el grafo del código en cada commit, sin LLM).

## 6. Límites
- **Tamaño:** hoy ~1 000 nodos. Si pasa de 20 000, revisar exclusiones.
- **Fuentes externas:** `github.com`, `docs.anthropic.com`, `docs.claude.com`, `modelcontextprotocol.io`, `docs.graphify.com`, `obsidian.md`.
- **API del tablero:** la bitácora solo hace GET (sirve el token de vista) y va a ≤ 176 peticiones/min.

## 7. Fuera de alcance
`data/` completo (base, copias y logs) y las carpetas de los demás proyectos del tablero: la bitácora solo vuelca el proyecto activo.
