# AgileForge Vista · plugin de Claude Code

[English](README.md)

Muestra cómo va el trabajo en AgileForge sin poder cambiar nada: sprint, columnas, quién hace qué, reuniones, decisiones y actividad. **Solo lectura**: no incluye herramientas de escritura ni motores de IA, y no necesita claves de API.

Requisitos: Claude Code, Node.js 18+ en el `PATH` y la web de AgileForge en marcha.

## Instalación

```text
/plugin marketplace add JSOTELD/agile-forge
/plugin install agileforge-vista@agileforge
```

Desde un clon local: `/plugin marketplace add D:/xampp/htdocs/Open-to-code` y el mismo `install`. Luego `/reload-plugins`.

**Vista y Control son excluyentes.** Instala solo uno. Si tienes Control: `/plugin uninstall agileforge@agileforge`.

## Configuración (URL y token de vista)

- **URL por defecto:** `http://localhost/Open-to-code/api`; cámbiala con `AGILEFORGE_API_URL`.
- **Token:** usa el token de solo lectura `AGILEFORGE_VIEW_TOKEN` del `.env` de la raíz de la app (p. ej. `D:\xampp\htdocs\Open-to-code\.env`). Se genera solo si está vacío. Solo permite GET en `api/index.php`: aunque se filtre, no puede modificar el tablero.

Orden de búsqueda: variables `AGILEFORGE_VIEW_TOKEN` (o, si falta, `AGILEFORGE_API_TOKEN`) y `AGILEFORGE_API_URL` → `$AGILEFORGE_HOME/.env` → un `.env` hacia arriba desde el proyecto (6 niveles) → `~/.agileforge/config.json` (`{"api_url": "…", "view_token": "…"}`). Para quien solo debe mirar, comparte únicamente el token de vista.

Comprueba con "ejecuta connection_info". Al iniciar sesión, un hook muestra un resumen de ≤6 líneas.

## Comandos

| comando | qué muestra |
|---|---|
| `/agileforge-vista:estado` | Resumen de 5 líneas: avance y desde ayer, quién hace qué, revisión, reuniones, decisiones |
| `/agileforge-vista:tablero [estado o rol]` | Historias por columna con dueño y puntos |
| `/agileforge-vista:reuniones [MEET-id]` | Últimas reuniones o el detalle de una |
| `/agileforge-vista:decisiones [n]` | Últimas decisiones (ADR) |
| `/agileforge-vista:actividad [n]` | Eventos recientes agrupados por día |

Agente: `observador` (haiku, esfuerzo low), solo con herramientas de lectura. Para cambiar su modelo: `CLAUDE_CODE_SUBAGENT_MODEL` o, en un clon local, `model:`/`effort:` en `agents/observador.md`.

## Avisos

- El plugin usa **tu plan o tu cuota de Claude Code**.
- AgileForge es un proyecto independiente (MIT), **sin afiliación con Anthropic**.
- Las decisiones del rol Legal que aparezcan aquí son orientativas y **no son asesoría legal profesional**.
