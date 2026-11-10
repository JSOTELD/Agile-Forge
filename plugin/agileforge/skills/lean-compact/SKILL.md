---
name: lean-compact
description: Úsala cuando redactes informes, respuestas, traspasos entre agentes o entregas de código, para ahorrar tokens sin perder información útil.
title: "Entregas y respuestas compactas"
category: agile
level: Principal
status: active
tags: ["AgileForge","agile"]
---

# Entregas y respuestas compactas

Economía de tokens: decir lo necesario, una vez, con evidencia. Complementa `context-sentinel` (qué leer); esta skill cubre qué escribir.

## Cuándo usarla
En cualquier informe, comentario, traspaso o cambio de código; y al notar que la conversación crece.

## Reglas
1. **Límites de palabras:** informe de entrega ≤ 200 palabras (≤ 350 si el encargo lo permite); comentario de tablero ≤ 80; traspaso ≤ 120; respuesta a una pregunta simple: la respuesta primero, en 1-3 frases.
2. **Estructura fija del informe:** Qué cambió (archivos) · Cómo se verificó (comando y resultado) · Pendientes. Sin preámbulos ni repetir el encargo.
3. **Diffs, no archivos:** muestra solo líneas cambiadas con rutas (`ruta:línea`) o un parche; no pegues archivos completos. Edita con reemplazos puntuales en vez de reescribir.
4. **Salidas recortadas:** de un comando pega las 5-10 líneas relevantes (`| head`, `| tail`, `grep`), nunca el volcado completo.
5. **Traspaso entre agentes** (resumen autosuficiente): objetivo en una línea; estado hecho/pendiente; archivos clave; decisiones tomadas (IDs); riesgo o bloqueo; siguiente acción exacta. Quien recibe no debe releer el hilo.
6. **Código compacto:** sin abstracciones especulativas (YAGNI), sin comentarios que repiten el código, sin dependencias para algo de 5 líneas.
7. **Cuándo compactar la conversación:** al cerrar una fase o historia, tras una exploración larga, o cuando el hilo repite contexto. Antes de compactar, escribe el traspaso (punto 5) y registra decisiones con `record_decision` / `registrar_decision`. Reinicia o delega en lugar de arrastrar 50 turnos.
8. No repitas lo ya dicho ni lo que consta en el tablero; enlaza el ID.

## Anti-patrones
- Informes de 1.000 palabras con la conclusión al final.
- Pegar logs completos, árboles de carpetas enteros o archivos sin cambios.
- Listas de "qué voy a hacer" antes de hacerlo.

## Cómo verificar
- Cuenta de palabras del informe dentro del límite (`wc -w`).
- Cada afirmación de éxito lleva comando + resultado recortado.
- El traspaso contiene los 6 elementos. Usa ese informe en `submit_for_review`.
