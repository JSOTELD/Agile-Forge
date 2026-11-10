---
name: director-priorizacion-valor
description: Úsala cuando el Director deba ordenar el backlog, elegir qué entra al sprint o recortar alcance según valor, esfuerzo y riesgo.
title: "Director: priorización por valor"
category: director
level: Principal
status: active
tags: ["AgileForge","director"]
---

# Director: priorización por valor

Herramientas (web / plugin): `ver_tablero` / `get_overview`, `ver_historia` / `get_task`, `actualizar_historia`, `ajustar_sprint`, `registrar_decision` / `record_decision`.

## Pasos
1. Parte del tablero actual y de la meta del sprint.
2. Puntúa cada historia candidata (1-5): valor para el usuario o negocio, urgencia (fecha, riesgo legal), esfuerzo y riesgo técnico.
3. Prioridad = (valor + urgencia) / esfuerzo; sube lo que desbloquea a otros y lo legal o de seguridad.
4. Ordena en tres cortes: imprescindible (entra), deseable (si hay margen), después (backlog).
5. Respeta capacidad: deja ~20% de margen; trabajo en curso antes que empezar nuevo.
6. Recorta alcance dividiendo historias (primero la rebanada mínima con valor) en lugar de añadir plazo.
7. Aplica con `actualizar_historia` y `ajustar_sprint`; registra el criterio con `registrar_decision` / `record_decision` y comunica el cambio en una línea.

## Gobernanza (ADR-004)
El Director ordena y asigna, nunca marca `completada`.

## Anti-patrones
- Priorizar por quién lo pidió o por novedad; todo "alta"; meter trabajo nuevo sin sacar otro; cambiar el orden a mitad de una historia sin registrarlo.

## Cómo verificar
El tablero refleja el orden nuevo y existe una decisión con el criterio (valor, esfuerzo, riesgo).
