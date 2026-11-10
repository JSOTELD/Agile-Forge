---
name: domain-modeling
description: Úsala cuando debas traducir una necesidad de negocio en entidades, estados y reglas antes de crear tablas, endpoints o historias.
title: "Modelado de dominio"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Modelado de dominio

## Cuándo usarla
Antes de tocar el esquema o la API, cuando la historia introduce conceptos nuevos o reglas de negocio (estados, permisos, cálculos).

## Pasos
1. Extrae el lenguaje común: sustantivos (entidades), verbos (comandos) y reglas ("solo si...", "nunca...") de la historia y sus criterios de aceptación. Usa los mismos nombres en código, BD y UI.
2. Distingue entidad (tiene identidad: historia, sprint) de valor (sin identidad: prioridad, rango de fechas).
3. Define el ciclo de vida como máquina de estados: lista estados, transiciones permitidas, quién las dispara y qué invariantes se cumplen en cada una. Ejemplo: `completada` solo por revisión aprobada o humano web.
4. Marca invariantes que deben cumplirse siempre y colócalas en un único lugar (función de dominio), no repartidas por endpoints.
5. Dibuja relaciones y cardinalidades (1-N, N-N con tabla intermedia) y decide quién es dueño del dato.
6. Lista casos límite: vacío, duplicado, concurrencia (dos agentes toman la misma historia), borrado con dependientes.
7. Solo entonces pasa a esquema (ver `database-optimization`) y contrato (ver `api-design-patterns`).

## Anti-patrones
- Modelar a partir de las pantallas o de las columnas en vez de las reglas.
- Estados implícitos en banderas booleanas combinadas (`is_done`, `is_locked`).
- Mismo concepto con tres nombres distintos.
- Reglas validadas solo en el frontend.

## Cómo verificar
- Entrega un glosario breve y la tabla de transiciones (estado origen, evento, estado destino, quién).
- Una prueba o script que intente una transición prohibida y muestre el rechazo.
- Referencia ambos en `submit_for_review`.
