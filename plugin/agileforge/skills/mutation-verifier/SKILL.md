---
name: mutation-verifier
description: Úsala cuando quieras comprobar que tus pruebas realmente detectan errores, introduciendo mutaciones deliberadas en la lógica crítica.
title: "Verificación por mutación"
category: quality
level: Principal
status: active
tags: ["AgileForge","quality"]
---

# Verificación por mutación

## Cuándo usarla
Después de que las pruebas pasen, en lógica de alto riesgo: permisos, gobernanza (quién puede aprobar), cálculos, validaciones, transiciones de estado. Una prueba verde no demuestra que sirva.

## Idea
Cambias el código de forma pequeña y deliberada (una mutación). Si las pruebas siguen pasando, el mutante "sobrevive": falta una prueba o sobra el código.

## Pasos
1. **Elige un alcance pequeño**: 1-3 archivos o funciones críticas, no todo el repo.
2. **Lista 5-10 mutaciones** de este tipo:
   - Invertir condiciones (`===` por `!==`, `>` por `>=`).
   - Cambiar `&&` por `||`.
   - Quitar una guarda o un `return` temprano.
   - Sustituir una constante (0, 1, "") o un código HTTP (403 por 200).
   - Eliminar una llamada de efecto (guardar, liberar lock).
3. **Aplica una cada vez** (edición manual temporal o `git stash`/`git checkout -- archivo` para revertir) y ejecuta SOLO las pruebas relevantes.
4. **Clasifica**: muerto (una prueba falló, bien), superviviente (mala señal), equivalente (no cambia comportamiento; descártalo con justificación).
5. **Para cada superviviente** escribe la prueba que lo mata, confirma que falla con la mutación y pasa sin ella.
6. **Revierte siempre** y confirma con `git diff` que no queda ninguna mutación.
7. Opcional con herramienta: `npx stryker run` (JS) o Infection (PHP) limitando `mutate` a los archivos elegidos.

## Anti-patrones
- Dejar una mutación sin revertir.
- Mutar código sin pruebas y concluir "todo bien".
- Perseguir 100 % en código trivial (getters, logs).
- Ejecutar toda la suite en cada mutante.

## Cómo verificar
Tabla en `submit_for_review`: mutación | archivo:línea | resultado (muerto/superviviente/equivalente) | prueba añadida. Meta: >= 80 % muertos en el alcance, supervivientes críticos a 0, y `git diff` limpio de mutaciones.
