---
name: tailwind-mastery
description: Úsala cuando maquetes con Tailwind (CDN o compilado) y necesites clases consistentes, configuración de tema y bajo coste de mantenimiento.
title: "Tailwind con criterio"
category: design
level: Principal
status: active
tags: ["AgileForge","design"]
---

# Tailwind con criterio

## Cuándo usarla
Al escribir o refactorizar vistas PHP/HTML que usan utilidades Tailwind, sea por CDN o por build.

## Pasos
1. **Tema primero**: define colores, fuentes y radios en `tailwind.config` (CDN: `tailwind.config = { theme: { extend: {...} } }` antes de cargar el script) enlazados a los tokens del `design-system`. Evita valores arbitrarios `[#123456]`.
2. **Orden de clases estable**: layout (display, posición) -> caja (tamaño, espaciado) -> tipografía -> color -> efectos -> estados (`hover:`, `focus-visible:`) -> responsive.
3. **Mobile-first**: clases base para móvil y `sm:`/`md:`/`lg:` para ampliar; nunca al revés.
4. **Extrae repetición** a componentes (partial PHP o `@apply` solo en build) cuando un bloque se repite 3 o más veces. No uses `@apply` para todo.
5. **Estados completos**: `hover:`, `focus-visible:ring-2`, `disabled:opacity-50`, `aria-[expanded=true]:`, `motion-reduce:transition-none`, `dark:` si hay tema oscuro.
6. **Clases dinámicas**: nunca construyas nombres con concatenación (`"bg-" . $color . "-500"`); el escáner no los detecta. Usa mapas completos de clases literales.
7. **Menos DOM**: si un wrapper solo añade una clase, pásala al hijo; prefiere `gap-*` y `space-*` a márgenes sueltos.
8. **Producción**: el CDN es para prototipos; para entrega con presupuesto de rendimiento compila con la CLI y `content` correcto para purgar CSS.

## Anti-patrones
- Cadenas de 30 clases ilegibles repetidas sin extraer.
- Mezclar CSS propio y utilidades que se pisan.
- `!` (important) por todas partes.
- Anchos fijos en px en vez de `max-w-*`/`w-full`.
- Olvidar el foco visible.

## Cómo verificar
- Revisa en móvil (375px), tablet (768px) y escritorio (1280px) con capturas.
- Si compilas: `npx tailwindcss -i in.css -o out.css --minify` y cita el tamaño final (objetivo < 30 KB gzip).
- `grep -rnE '"bg-" ?\.|\bclass="[^"]*\$' views/` sin clases construidas dinámicamente.
- Adjunta todo en `submit_for_review`.
