---
name: dependency-mapping
description: Úsala cuando necesites entender qué archivos, módulos o tablas dependen de otros antes de modificar, mover o borrar código.
title: "Mapa de dependencias"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Mapa de dependencias

Sustituye a la antigua `graphify`: un mapa ligero obtenido con herramientas del repo, sin dependencias nuevas.

## Cuándo usarla
Antes de renombrar, mover o eliminar archivos, cambiar un contrato, tocar una tabla o evaluar el impacto de una historia.

## Pasos
1. Fija el objetivo: el símbolo, archivo o tabla cuyo impacto quieres conocer.
2. Dependientes directos (quién lo usa): `grep -rn "nombre" --include=*.php --include=*.ts --include=*.js` (excluye `vendor/`, `node_modules/`, `dist/`). Para PHP busca `require`, `include`, `use`, nombres de clase y de función; para TS, `import ... from`.
3. Dependencias salientes: lee los `require`/`import` del archivo objetivo.
4. Datos: busca el nombre de la tabla o columna en consultas SQL, migraciones y en el contrato de la API.
5. Contratos externos: comprueba `team/contrato-api.md`, herramientas MCP y la web (JS que llama a la API).
6. Construye el mapa en texto, nivel 1-2 como máximo:
   `objetivo <- A.php (consulta), B.ts (llamada)`; marca dependencias circulares y archivos huérfanos (nadie los usa).
7. Clasifica el impacto: seguro (sin dependientes), moderado (1-3), amplio (>3 o contrato público).
8. Para mapas grandes, delega la búsqueda en un subagente y pide solo el mapa resumido (ver `context-sentinel`).

## Anti-patrones
- Borrar un archivo sin buscar referencias dinámicas (`require $ruta`, nombres de tabla construidos).
- Mapear todo el repo cuando basta un nivel.
- Confiar solo en el nombre del archivo y no en el símbolo.

## Cómo verificar
- Pega el mapa (≤ 15 líneas) y el comando `grep` usado con su conteo de resultados.
- Tras el cambio, repite el `grep`: cero referencias rotas.
- Prueba mínima (`php -l`, `tsc --noEmit` o la llamada a la API) de cada dependiente afectado. Adjunta en `submit_for_review`.
