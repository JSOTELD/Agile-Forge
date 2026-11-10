---
name: director-toma-decisiones
description: Úsala cuando el Director deba decidir entre alternativas, fijar un rumbo técnico o resolver un desacuerdo del equipo y dejarlo registrado.
title: "Director: toma de decisiones"
category: director
level: Principal
status: active
tags: ["AgileForge","director"]
---

# Director: toma de decisiones

Herramientas (web / plugin): `ver_tablero` / `get_overview`, `ver_historia` / `get_task`, `registrar_decision` / `record_decision`, `comentar_historia` / `add_comment`, `consultar_agente` / Agent con `agileforge:<agente>`.

## Pasos
1. Mira el estado real (`ver_tablero` / `get_overview`) antes de decidir.
2. Formula la decisión en una frase y su plazo.
3. Lista 2-3 alternativas con coste, riesgo y reversibilidad.
4. Consulta solo al especialista que aporte (arquitectura, QA, legal); pide respuesta ≤ 150 palabras.
5. Decide: opción elegida, motivo, consecuencias, quién actúa.
6. Registra con `registrar_decision` / `record_decision` (título, contexto, decisión, consecuencias) y avisa en la historia afectada con un comentario breve.
7. Decisiones reversibles y baratas: decide ya. Irreversibles o legales: contrasta primero y recuerda que no sustituyen asesoría profesional.

## Gobernanza (ADR-004)
El Director nunca marca historias `completada`: solo una revisión aprobada por QA (distinta de quien las tomó) o un humano en la web. El Director asigna, prioriza y desbloquea.

## Anti-patrones
- Decidir sin leer el tablero; decisiones sin registrar; reabrir lo ya decidido sin dato nuevo; codificar en lugar de delegar.

## Cómo verificar
La decisión tiene ID, motivo y responsable, y el tablero o la historia lo reflejan.
