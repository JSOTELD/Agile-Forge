---
name: tomar
description: Toma una historia de usuario asignándola a un rol y activando un lock atómico exclusivo para evitar colisiones entre agentes.
---

# Comando: /tomar <US-id> [rol]

Reclama la propiedad de una historia de usuario en el tablero AgileForge utilizando la herramienta MCP `claim_task`:

1. **Argumentos**:
   - `<US-id>`: Código de la historia de usuario (ej: `US-101`, `US-102`).
   - `[rol]` (opcional): Identificador del rol (`frontend_master`, `backend_architect`, `qa_security_guard`, etc.). Si no se proporciona, usa el rol activo del agente invocador.
2. **Procedimiento**:
   - Invoca la herramienta MCP `claim_task({ task_id: "<US-id>", role_key: "<rol>", client: "claude-code" })`.
   - Si la tarea ya está bloqueada por otro agente, informa el conflicto con el nombre del agente que la retiene.
   - Si se bloquea con éxito, consulta el detalle con `get_task({ task_id: "<US-id>" })` y presenta el criterio de aceptación y la Definition of Done para comenzar el desarrollo.

