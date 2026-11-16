---
name: claude-dotnet
description: Úsala cuando una app .NET deba usar Claude (SDK oficial `Anthropic`) y servidores MCP (SDK oficial `ModelContextProtocol`) para agentes con herramientas, control de costos y cálculos en código.
title: "Agentes con Claude y MCP desde .NET"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering","claude","mcp","dotnet","agents"]
---

# Agentes con Claude y MCP desde .NET

## Cuándo usarla
Integrar Claude en C#: bucles de agente con herramientas, subagentes por rol, conexión a MCP remotos y registro de gasto.

## Pasos
1. **Paquetes oficiales:** `Anthropic` (SDK oficial desde la versión 10; las versiones 1–3 eran de otro proyecto) y `ModelContextProtocol` (SDK oficial de MCP para C#). Integración opcional con `Microsoft.Extensions.AI` (`IChatClient`).
2. **Bucle de agente propio:** mensaje → si la respuesta pide `tool_use`, ejecutar la herramienta en código → devolver `tool_result` → repetir. Cortar por máximo de vueltas, por presupuesto o por cancelación del usuario.
3. **Modelo por rol:** el que dirige y decide con Opus; los que buscan con Sonnet; extracción y verificación con Haiku. Los IDs de modelo van en configuración, no en el código.
4. **Herramientas deterministas:** todo cálculo (dinero, márgenes, umbrales) es una herramienta en C#; el modelo nunca calcula cifras ni decide si una regla se cumple.
5. **MCP remoto:** cliente HTTP del SDK de MCP con la clave del servicio en una cabecera; listar herramientas al iniciar y exponer solo las permitidas.
6. **Costos:** registrar `usage` (tokens de entrada, salida y caché) de cada llamada; sumar por corrida; avisar al 80 % del presupuesto y detener al 100 %.
7. **Caché de prompt:** instrucciones de sistema y conocimiento estable al inicio y marcados para caché.
8. **Robustez:** reintentos con espera creciente ante 429/529 (configurar `MaxRetries`), tiempo máximo por llamada, y estado de la corrida guardado para poder retomarla.
9. **Seguridad:** la clave de API sale del baúl de secretos; nunca se registra ni se muestra completa.

## Anti-patrones
- Pedirle al modelo que «calcule el margen».
- Bucles sin límite de vueltas ni de gasto.
- Pasar todas las herramientas de un MCP sin filtrar.

## Cómo verificar
- Pruebas con un cliente falso o respuestas grabadas: el bucle termina, respeta el presupuesto y llama a las herramientas correctas.
- Una corrida real corta muestra el gasto por modelo y coincide con el panel de la consola de Anthropic.
