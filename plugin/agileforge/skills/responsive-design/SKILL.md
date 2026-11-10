---
name: responsive-design
description: Úsala cuando una pantalla deba funcionar bien en móvil, tablet y escritorio, o cuando detectes desbordes, texto diminuto o controles imposibles de tocar.
title: "Diseño responsive y móvil"
category: design
level: Principal
status: active
tags: ["AgileForge","design"]
---

# Diseño responsive y móvil

## Cuándo usarla
Toda vista nueva o modificada, y especialmente tableros, tablas y formularios largos.

## Checklist
1. **Viewport**: `<meta name="viewport" content="width=device-width, initial-scale=1">`. Nunca `user-scalable=no`.
2. **Mobile-first** con puntos de corte 640 / 768 / 1024 / 1280px; diseña primero 360-390px.
3. **Sin scroll horizontal** de la página a 320px: usa `min-width: 0` en hijos de flex/grid, `overflow-wrap: anywhere` en textos largos, `max-width: 100%` en imágenes.
4. **Objetivos táctiles** >= 44x44px con >= 8px entre ellos; la acción principal al alcance del pulgar.
5. **Tipografía**: cuerpo >= 16px (evita zoom automático en inputs iOS); `clamp()` para títulos, p. ej. `clamp(1.5rem, 4vw, 2.25rem)`.
6. **Unidades**: `rem`/`%`/`fr`; `min-height: 100dvh` en vez de `100vh`; respeta `env(safe-area-inset-*)`.
7. **Tablas y Kanban**: en móvil, tarjetas apiladas o scroll horizontal contenido con `scroll-snap`; nunca ocultes datos críticos.
8. **Navegación**: menú colapsable accesible (botón con `aria-expanded`), no solo hover.
9. **Imágenes**: `width`/`height` declarados, `srcset`/`sizes`, `loading="lazy"` bajo el pliegue.
10. **Entradas**: `inputmode`, `type="email|tel|number"` y `autocomplete` correctos.

## Anti-patrones
- Anchos fijos en px.
- Ocultar funciones en móvil en vez de reorganizarlas.
- Hover como única vía de acceso.
- Modales que superan la pantalla sin scroll interno.
- Texto en imágenes.
- Layouts que dependen de un dispositivo concreto.

## Cómo verificar
- Capturas a 320, 375, 768 y 1280px (DevTools o Playwright `page.setViewportSize`).
- Comprobación de desborde: `document.documentElement.scrollWidth <= window.innerWidth` en cada ancho.
- Lista de objetivos táctiles revisados con su tamaño.
- Adjunta todo en `submit_for_review`.
