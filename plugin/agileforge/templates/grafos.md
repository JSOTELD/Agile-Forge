---
estado: borrador          # borrador | aprobada. El Curador de grafos solo construye con "aprobada", y solo David la cambia.
aprobada_por:             # nombre de quien la aprobó
fecha:                    # AAAA-MM-DD de la aprobación
---

# Receta de grafos · <nombre del proyecto>

> Plantilla de `.agileforge/grafos.md` para el Curador de grafos de AgileForge (v0, experimental).
> Dice qué entra en los dos grafos, dónde se guardan y cuánto puede gastar. Aquí **nunca** van claves ni tokens.

## 1. Los dos grafos

| grafo | para quién | dónde | lo genera |
|---|---|---|---|
| **Grafo de la IA** | los agentes: consultan en vez de leer archivos | `graphify-out/` (`graph.json`, `GRAPH_REPORT.md`, `wiki/index.md`) | graphify |
| **Grafo bibliográfico** | personas: bibliografía de lo que se hizo, estilo Obsidian | carpeta del vault (abajo) | graphify (`codigo/`) + bitácora del tablero (`bitacora/`) + fichas (`fuentes/`) |

## 2. Qué se escanea
- **Raíz:** `.`
- **Excluir** (el Curador escribe estas líneas en `.graphifyignore`; graphify ya respeta `.gitignore`):
  ```text
  graphify-out/
  .agileforge/vault/codigo/
  .agileforge/vault/.obsidian/
  .agileforge/vault/.agileforge-bitacora.json
  node_modules/
  vendor/
  dist/
  build/
  <archivos minificados, bundles generados, datos, copias de seguridad>
  ```
- **Nunca** se escanean secretos: `.env`, claves, certificados ni bases de datos (graphify ya omite los archivos sensibles; si aparece alguno, el Curador lo excluye y avisa).

## 3. Extracción semántica (documentos, PDFs, imágenes)
El código se analiza siempre en local con AST: **0 tokens**. Los documentos solo entran si se elige un motor:

- **Motor:** `ninguno`
  - `ninguno` — solo código. Gratis y sin red. (Recomendado para empezar.)
  - `ollama` — documentos con la IA local (`OLLAMA_BASE_URL`); nada sale del equipo. Necesita `graphifyy[ollama]`.
  - `claude-cli` — documentos con tu plan de Claude Code (**gasta tu cuota**).
  - `api` — documentos con una clave propia (Gemini, Claude, OpenAI…); la clave va en variables de entorno, nunca aquí.
- Con un motor, entran **todos los documentos no excluidos** (README, `docs/`, y del vault: `bitacora/`, `fuentes/` y `notas/`). Excluye en la sección 2 los que no aporten (changelogs largos, traducciones, actas antiguas).
- **Nunca** entra `<vault>/codigo/` (lo genera graphify: sería contar el código dos veces).

## 4. Vault de Obsidian (grafo bibliográfico)
- **Carpeta del vault:** `.agileforge/vault/`
- **Proyecto del tablero:** `<id del proyecto, o vacío = el activo>`
- **Subcarpetas:** `codigo/` (graphify, no editar) · `bitacora/` (tablero, no editar) · `fuentes/` (fichas) · `notas/` (tuyas).
- **¿Se versiona en git?** `no` — la bitácora copia datos del tablero (historias, comentarios, decisiones). Si dices `sí`, revisa antes que no haya datos privados.

## 5. Cuándo se actualiza
- Al cerrar o aprobar historias, al terminar un sprint, o cuando el Director lo pida (`/agileforge:grafo actualizar`).
- Opcional (lo instala David, no el Curador): `graphify hook install` reconstruye el grafo del código en cada commit, sin LLM.

## 6. Límites
- **Tamaño máximo razonable:** `<p. ej. 20 000 nodos; por encima, el Curador propone excluir carpetas>`.
- **Fuentes externas:** el Curador puede leer páginas públicas para hacer fichas en `fuentes/`. `<dominios permitidos, o "cualquiera">`.
- **API del tablero:** la bitácora solo hace GET y respeta el límite de peticiones (≤ 176/min; ante un 429 espera).

## 7. Fuera de alcance
Lo que el Curador no debe escanear ni enlazar (repos de terceros, datos de clientes, carpetas privadas, …).
