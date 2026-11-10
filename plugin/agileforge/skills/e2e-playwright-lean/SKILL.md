---
name: e2e-playwright-lean
description: Úsala cuando debas automatizar con Playwright los flujos críticos de la web con pruebas pocas, estables y rápidas.
title: "E2E Playwright ligero"
category: quality
level: Principal
status: active
tags: ["AgileForge","quality"]
---

# E2E Playwright ligero

## Cuándo usarla
Para proteger flujos de valor: entrar, crear/mover/revisar una historia, aprobar como humano. No para cada detalle visual.

## Pasos
1. **Alcance**: 3-8 pruebas por producto, cada una un recorrido de usuario completo. Todo lo demás va en unitarias/API.
2. **Configuración** (`playwright.config.js`): `baseURL: 'http://localhost:8000'`, `retries: 1` en CI, `trace: 'on-first-retry'`, `screenshot: 'only-on-failure'`, un solo proyecto Chromium más uno móvil (`devices['Pixel 7']`).
3. **Datos**: arranca con una BD SQLite de prueba (copia desechable) y semilla conocida; limpia al final. Nunca uses datos de producción.
4. **Selectores**: `getByRole`, `getByLabel`, `getByText`; `data-testid` solo si no hay alternativa semántica. Sin XPath ni clases CSS.
5. **Esperas**: aserciones con auto-espera (`await expect(locator).toBeVisible()`); prohibido `waitForTimeout`.
6. **Autenticación**: inicia sesión una vez con `storageState` y reutilízalo.
7. **Red**: intercepta solo terceros; la API propia va real.
8. **Accesibilidad de regalo**: añade `@axe-core/playwright` en las pantallas principales.
9. **Velocidad**: paralelo por archivo, `fullyParallel: true`; suite completa < 2 min.

## Anti-patrones
- Pruebas dependientes entre sí.
- Capturas pixel-perfect de todo.
- Reintentos para tapar pruebas inestables (investiga la causa).
- Lógica condicional dentro de la prueba.

## Cómo verificar
- `npx playwright test --reporter=line` con resultado `N passed`; ejecútala 3 veces seguidas para detectar inestabilidad.
- Adjunta el informe (`npx playwright show-report`) o la salida y, si hubo fallo, el trace.
- Indica en qué viewports se corrió.
- Adjunta todo en `submit_for_review`.
