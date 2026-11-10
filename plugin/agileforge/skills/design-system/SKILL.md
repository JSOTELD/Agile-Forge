---
name: design-system
description: Úsala cuando definas o reutilices colores, tipografía, espaciado, radios o componentes para que la interfaz sea coherente y mantenible mediante tokens.
title: "Sistema de diseño y tokens"
category: design
level: Principal
status: active
tags: ["AgileForge","design"]
---

# Sistema de diseño y tokens

## Cuándo usarla
Al añadir un componente nuevo, un tema (claro/oscuro) o cuando detectes valores sueltos (`#6366f1`, `13px`) repetidos en varios archivos.

## Pasos
1. **Inventario**: busca antes de crear (`grep -rn "var(--" assets/ views/`). Reutiliza el token o componente existente.
2. **Tokens en `:root`** como variables CSS, en tres capas:
   - Primitivos: `--slate-950`, `--indigo-500`.
   - Semánticos: `--bg`, `--surface`, `--border`, `--text`, `--text-muted`, `--accent`, `--danger`, `--ok`.
   - De componente: `--btn-radius`, `--card-pad`.
3. **Escalas cerradas**:
   - Espaciado base 4px (4, 8, 12, 16, 24, 32, 48).
   - Tipografía modular (12, 14, 16, 20, 24, 32).
   - Radios 6/10/16.
   - Sombras 2-3 niveles.
   - Duraciones 150/200/300ms.
4. **Modo oscuro** por reasignación de los tokens semánticos (`[data-theme="dark"]`), nunca duplicando componentes.
5. **Componentes** con estados completos: default, hover, focus-visible, active, disabled, loading, error. Nómbralos con BEM o clases utilitarias coherentes.
6. **Accesibilidad en el token**: cada par texto/fondo semántico ya cumple 4.5:1; documéntalo junto a la variable.
7. **Documenta** en una página de catálogo (p. ej. `docs/` o vista de estilos) con muestras de cada token y componente.

## Anti-patrones
- Hex o px literales dentro de componentes.
- Cinco grises casi iguales.
- Tokens por pantalla ("--kanban-azul-2").
- Sobrescribir con `!important`.
- Variantes duplicadas de un mismo botón.

## Cómo verificar
- `grep -rnE "#[0-9a-fA-F]{3,8}\b" views/ assets/css` fuera de la definición de tokens: lista vacía o justificada.
- Captura de la pantalla en tema claro y oscuro.
- Lista de tokens añadidos o cambiados y componentes que los usan.
- Adjunta todo en `submit_for_review`.
