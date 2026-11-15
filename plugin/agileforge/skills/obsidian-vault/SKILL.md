---
name: obsidian-vault
description: Úsala al escribir o revisar notas del vault de Obsidian del proyecto (grafo bibliográfico): fichas de fuentes, notas propias y enlaces con la bitácora del tablero y con el mapa del código.
title: "Vault de Obsidian como bibliografía"
category: product
level: Principal
status: active
tags: ["AgileForge","product","obsidian"]
---

# Vault de Obsidian como bibliografía

El vault documenta **lo que se hizo y por qué**, con enlaces que Obsidian muestra como grafo. Por defecto vive en `.agileforge/vault/`.

## Estructura
| carpeta | contenido | quién escribe |
|---|---|---|
| `Inicio.md` | Mapa del vault (MOC) | `bitacora.mjs` |
| `codigo/` | Una nota por símbolo y por comunidad del código, más `graph.canvas` | `graphify export obsidian` |
| `bitacora/` | `historias/US-…`, `decisiones/ADR-…`, `reuniones/MEET-…`, `memoria/MEM-…`, `roles/`, `sprints/` (instantáneas) | `bitacora.mjs` |
| `fuentes/` | Una ficha por fuente consultada (`_plantilla.md`) y su índice `_indice.md` (generado) | Curador y personas |
| `notas/` | Conceptos, aprendizajes, borradores | Personas |

`codigo/` y `bitacora/` se regeneran: **no se editan a mano**. Una nota generada que alguien edite deja de actualizarse (la bitácora lo avisa) hasta que se borre.

## Convenciones
- **Enlaces con ruta** desde la raíz del vault y alias legible: `[[bitacora/historias/US-101|US-101]]`, `[[bitacora/decisiones/ADR-002|ADR-002]]`, `[[codigo/TaskController.php|TaskController.php]]`.
- **Ficha de fuente** (`fuentes/<slug>.md`, slug en minúsculas con guiones): frontmatter `titulo`, `autor`, `anio`, `tipo`, `url`, `consultada` (AAAA-MM-DD), `relacionado` (IDs del tablero); cuerpo con **Resumen** (con tus palabras), **Qué tomamos de aquí** (cada idea enlazada a la historia o ADR donde se usó) y, como mucho, **una** cita corta.
- **Etiquetas** jerárquicas: `agileforge/fuente`, `tema/<tema>`; nada de etiquetas sueltas por cada palabra.
- Una idea por nota en `notas/`; enlázala desde la historia o la fuente que la originó.

## Anti-patrones
- Copiar artículos o documentación enteros: resume y enlaza la URL.
- Escribir dentro de `codigo/` o `bitacora/` (se pierde en la siguiente regeneración).
- Datos privados (tokens, correos, datos de clientes) en cualquier nota: el vault se puede compartir.

## Cómo verificar
- Abre `Inicio.md` en Obsidian: cada enlace nuevo resuelve (sin notas fantasma salvo IDs que aún no existen) y la ficha aparece en `fuentes/_indice.md` tras regenerar la bitácora.
