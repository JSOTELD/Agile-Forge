---
name: accessibility-wcag
description: Úsala cuando crees o cambies cualquier pantalla, formulario o componente interactivo y debas cumplir WCAG 2.1 AA (contraste, teclado, lectores de pantalla).
title: "Accesibilidad WCAG 2.1 AA"
category: design
level: Principal
status: active
tags: ["AgileForge","design"]
---

# Accesibilidad WCAG 2.1 AA

## Cuándo usarla
En toda historia que toque HTML/CSS/JS visible: formularios, modales, tablas, menús, tableros Kanban, notificaciones.

## Checklist
1. **Contraste**: texto normal >= 4.5:1; texto grande (>=24px o 18.66px negrita) y bordes/iconos de controles >= 3:1. Revisa también estados hover, disabled y foco.
2. **Teclado**: todo lo clicable es `<button>` o `<a href>`; Tab sigue el orden visual; Enter/Espacio activan; Esc cierra modales. Sin `tabindex` positivo.
3. **Foco visible**: `:focus-visible` con contorno de 2px y contraste 3:1; nunca `outline: none` sin sustituto.
4. **Modales**: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`; al abrir mueve el foco dentro, atrapa Tab y al cerrar devuelve el foco al disparador.
5. **Formularios**: `<label for>` real por campo; errores con `aria-describedby` y `aria-invalid="true"`; no dependas solo del color.
6. **Nombres accesibles**: botones de solo icono con `aria-label`; imágenes con `alt` descriptivo o `alt=""` si son decorativas; un solo `<h1>` y niveles de encabezado sin saltos.
7. **Regiones vivas**: mensajes de estado (guardado, error de API) en `role="status"` (`aria-live="polite"`); errores críticos en `role="alert"`.
8. **Movimiento**: respeta `prefers-reduced-motion: reduce`; nada parpadea más de 3 veces por segundo.
9. **Objetivos táctiles**: mínimo 44x44px en móvil (24x24px absoluto en escritorio).
10. **Idioma y zoom**: `<html lang="es">`; el layout aguanta 200 % de zoom y 320px de ancho sin scroll horizontal.
11. **Arrastrar y soltar**: ofrece alternativa por teclado (p. ej. menú "Mover a…") para tarjetas Kanban.

## Anti-patrones
- `<div onclick>` sin rol ni teclado.
- Placeholder como única etiqueta.
- `aria-label` que contradice el texto visible.
- ARIA donde bastaría HTML semántico.
- Color como único indicador de estado (añade icono o texto).

## Cómo verificar
- Ejecuta axe (`npx @axe-core/cli http://localhost:8000/ --exit`) o Lighthouse accesibilidad >= 95; adjunta el resumen sin violaciones críticas.
- Recorre la pantalla solo con teclado y anota "Tab/Shift+Tab/Esc OK".
- Mide contrastes con las herramientas de color de DevTools y cita los pares revisados.
- Prueba breve con lector de pantalla (NVDA/VoiceOver) en el flujo principal y anota lo anunciado.
- Adjunta todo en `submit_for_review`.
