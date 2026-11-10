---
name: performance-budget
description: Úsala cuando una historia añada recursos, scripts, imágenes o consultas y debas mantener la web dentro de un presupuesto medible de rendimiento.
title: "Presupuesto de rendimiento"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Presupuesto de rendimiento

## Cuándo usarla
Al añadir librerías, fuentes, imágenes, polling, endpoints o vistas con listas largas.

## Presupuestos (percentil 75 móvil, red 4G lenta simulada)
- **LCP** <= 2,5 s; **INP** <= 200 ms; **CLS** <= 0,1; TTFB <= 600 ms.
- JS inicial <= 170 KB comprimido; CSS crítico <= 50 KB; imagen héroe <= 150 KB.
- Lighthouse rendimiento >= 90 en móvil.
- API: respuesta p95 <= 300 ms; nada de N+1.

## Pasos
1. **Mide antes**: guarda la línea base (Lighthouse CLI o DevTools Performance).
2. **Carga**: `<script type="module">` (diferido por defecto) o `defer`; fuentes con `font-display: swap` y `preload` de la crítica; `preconnect` solo a orígenes realmente usados.
3. **Imágenes**: WebP/AVIF, tamaños responsivos, `width`/`height` fijos (evita CLS), `loading="lazy"` salvo el LCP, que lleva `fetchpriority="high"`.
4. **JS**: divide con `import()` dinámico lo no crítico; sin librerías enteras para una función; trocea tareas > 50 ms; `debounce` en inputs y `requestAnimationFrame` en animaciones.
5. **Red**: caché con `Cache-Control` e `ETag` para estáticos; compresión gzip/brotli; polling con intervalo creciente y pausa en pestaña oculta (`document.visibilityState`).
6. **Backend PHP**: opcache activo, índices en columnas filtradas, paginación (LIMIT) y sin consultas dentro de bucles.
7. **DOM**: listas > 200 filas con paginación o virtualización.

## Anti-patrones
- Cargar Tailwind CDN en producción sin evaluarlo.
- Imágenes de 3 MB.
- Scripts bloqueantes en `<head>`.
- Medir una sola vez en tu equipo rápido.

## Cómo verificar
- `npx lighthouse http://localhost:8000/ --preset=desktop --only-categories=performance --output=json` y también móvil; adjunta LCP/INP(TBT)/CLS antes y después.
- Tamaños transferidos de la pestaña Network.
- `EXPLAIN` de consultas nuevas.
- Adjunta todo en `submit_for_review`.
