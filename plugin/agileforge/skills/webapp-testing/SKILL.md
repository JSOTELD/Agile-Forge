---
name: webapp-testing
description: Úsala cuando necesites probar una aplicación web (PHP + JS) de forma pragmática: qué probar, con qué herramienta y qué evidencia entregar.
title: "Pruebas de aplicaciones web"
category: quality
level: Principal
status: active
tags: ["AgileForge","quality"]
---

# Pruebas de aplicaciones web

## Cuándo usarla
En cualquier historia que cambie comportamiento: endpoints PHP, lógica JS o flujos de UI. Complementa a `e2e-playwright-lean` y `mutation-verifier`.

## Estrategia (pirámide)
1. **Unitarias** (muchas, rápidas): lógica pura en módulos ES (`node --test` o Vitest) y funciones PHP (PHPUnit). Casos: feliz, límites (vacío, null, máximo), error.
2. **Integración de API**: llama al endpoint real con `curl` o `fetch` contra una base SQLite temporal; valida código HTTP, JSON y efectos en BD. Cubre 401/403/404/409/422.
3. **E2E** (pocas): solo flujos críticos, ver `e2e-playwright-lean`.
4. **Manual exploratorio** breve: móvil y teclado.

## Reglas
- Una prueba, una razón para fallar; nombre que describe el comportamiento ("rechaza token de solo lectura en escritura").
- Aislamiento: datos propios por prueba, sin depender del orden, sin tocar la BD real (`data/*.sqlite`).
- Reloj, azar y red externa simulados.
- Escribe primero la prueba que reproduce el bug; debe fallar antes de la corrección.
- Prueba reglas de negocio y permisos, no detalles de implementación.
- Sin `sleep`: espera condiciones.
- Cobertura como pista, no como meta: >= 80 % en lógica nueva.

## Anti-patrones
- Pruebas que siempre pasan (sin aserciones).
- Mocks de todo, incluido lo que se quiere probar.
- Pruebas frágiles por selectores CSS; usa roles/etiquetas.
- Dejar pruebas `skip` sin motivo.

## Cómo verificar
- Comando ejecutado y salida resumida (`N passed, 0 failed`), p. ej. `php -l archivo.php`, `node --test`, `vendor/bin/phpunit`.
- Lista de casos nuevos con su motivo.
- Para un bug: prueba fallando antes y pasando después.
- Adjunta todo en `submit_for_review`.
