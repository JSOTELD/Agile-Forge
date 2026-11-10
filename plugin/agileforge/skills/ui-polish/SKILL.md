---
name: ui-polish
description: Úsala cuando una interfaz funcione pero se vea genérica, desordenada o sin carácter y debas pulir jerarquía, tipografía, espaciado y microinteracciones evitando el aspecto de plantilla de IA.
title: "Pulido de interfaz (UI polish)"
category: design
level: Principal
status: active
tags: ["AgileForge","design"]
---

# Pulido de interfaz (UI polish)

## Cuándo usarla
Al terminar una pantalla funcional, al revisar una que "se ve rara" o al detectar rasgos de interfaz genérica. Trabaja sobre los tokens de `design-system` y respeta `accessibility-wcag`.

## Checklist
1. **Propósito por elemento**: cada bloque informa o permite actuar. Elimina adornos, wrappers `div` vacíos y tarjetas dentro de tarjetas.
2. **Jerarquía**: un único foco principal por pantalla. Diferencia por tamaño, peso y color, no solo por tamaño. Una acción primaria visible; las demás secundarias o de texto.
3. **Tipografía**: máximo 2 familias; escala 12/14/16/20/24/32; interlineado 1,5 en cuerpo y 1,2 en títulos; líneas de 45-75 caracteres; `font-variant-numeric: tabular-nums` en cifras.
4. **Espaciado**: múltiplos de 4px; agrupa por proximidad (menos espacio dentro del grupo que entre grupos); padding generoso y consistente.
5. **Color**: un acento, neutros bien graduados, colores semánticos solo para estado. Sin degradados morados ni brillos neón por defecto. Contraste >= 4.5:1.
6. **Superficies**: bordes de 1px sutiles y sombras suaves de 1-2 niveles; radios coherentes (6/10/16), no todo redondeado al máximo.
7. **Microinteracciones**: transiciones de 150-200ms con `ease-out` en hover/foco/estado; 300ms máximo en paneles; animar solo `opacity` y `transform`; respetar `prefers-reduced-motion`.
8. **Estados**: vacío (con siguiente paso), carga (esqueleto sin saltos, CLS 0), error (qué pasó y cómo arreglarlo), éxito discreto.
9. **Texto**: verbos concretos en botones ("Guardar historia"), sin relleno tipo "Bienvenido a la plataforma del futuro", sin emojis como iconos estructurales.
10. **Datos reales**: prueba con textos largos, cifras grandes y listas vacías, no con "Lorem ipsum".

## Señales de interfaz genérica (evítalas)
Tres tarjetas idénticas con icono en círculo; degradado en el título; sombra y borde en todo; centrar todo; botones sin jerarquía; cuadrículas simétricas sin intención; ilustraciones de stock.

## Cómo verificar
- Capturas antes/después en 375px y 1280px.
- Lista de cambios agrupada (jerarquía, tipografía, espaciado, estados).
- Contrastes medidos y confirmación de `prefers-reduced-motion`.
- Medición de CLS (0) con Lighthouse.
- Adjunta todo en `submit_for_review`.
