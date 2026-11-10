---
description: Lanza al Tester (v0, experimental) sobre el proyecto activo. Lee la receta de pruebas, levanta el entorno desechable, intenta romper las reglas, registra los bugs confirmados, apaga y limpia, y entrega el informe.
argument-hint: "[alcance: área, historia US-… o regla; vacío = todo el proyecto]"
---

Alcance pedido: `$ARGUMENTS` (vacío = todo el proyecto activo).

1. `mcp__plugin_agileforge_board__get_overview` una vez para saber cuál es el proyecto activo y su workspace.
2. Comprueba la receta `.agileforge/testing.md` en el workspace del proyecto:
   - No existe → delega igual (paso 3): el tester la propondrá como borrador desde la plantilla y se detendrá.
   - Existe con `estado: borrador` → informa a David que debe revisarla y cambiarla a `estado: aprobada`, y para. No lances pruebas.
3. Delega con la herramienta Agent en `agileforge:tester` (sin pasar `model` ni `effort`). Prompt:
   "Proyecto activo: <nombre> (workspace <ruta>). Alcance: <alcance o 'todo el proyecto'>. Receta: `.agileforge/testing.md`. Plantilla por si falta: `${CLAUDE_PLUGIN_ROOT}/templates/testing.md`. Sigue tu método en orden: 1) lee la receta (sin receta aprobada, propón el borrador y detente); 2) levanta el entorno desechable; 3) reglas primero, luego los cuatro ataques, entradas, perfiles (los de la receta más usuario nuevo y sin credenciales) y revisión tras cada tanda; 4) interfaz al final; 5) registra cada bug confirmado con create_task; 6) apaga y limpia; 7) entrega el informe final."
4. Tú no arreglas lo que encuentre, no apruebas y no priorizas: solo transmites el resultado.
5. Muestra el informe del tester tal cual en sus cinco apartados (reglas y perfiles, qué resistió, bugs por severidad con su ID, sospechas y preguntas, qué no pudo probar). Si hay algún bug crítico o de seguridad, termina con: "Requiere decisión de David".

