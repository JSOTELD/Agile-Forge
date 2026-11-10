---
name: legal-frameworks
description: Úsala cuando un producto trate datos personales o necesites saber qué normativa aplica (Perú, GDPR, LATAM) antes de publicar una función.
title: "Marcos legales y datos personales"
category: legal
level: Principal
status: active
tags: ["AgileForge","legal"]
---

# Marcos legales y datos personales

> Aviso: esta guía es orientativa y no sustituye la asesoría de un abogado o profesional colegiado. Valida con un profesional antes de publicar o firmar.

## Cuándo usarla
Al recoger, almacenar o transferir datos de personas (formularios, cuentas, correos, pagos, ubicación) o al lanzar en un país nuevo.

## Pasos
1. Determina el país de los usuarios y del titular del servicio; aplica la norma del lugar donde están las personas.
2. **Perú:** Ley 29733 de Protección de Datos Personales y su reglamento (D.S. 003-2013-JUS): consentimiento previo, informado, expreso e inequívoco; finalidad concreta; derechos ARCO (acceso, rectificación, cancelación, oposición); medidas de seguridad; inscripción de bancos de datos personales ante la Autoridad Nacional de Protección de Datos Personales; reglas para flujo transfronterizo.
3. **UE (GDPR):** base legal (consentimiento, contrato, interés legítimo), minimización, derechos de supresión y portabilidad, notificación de brecha en 72 h, representante si aplica.
4. **LATAM en general:** existen leyes propias (LGPD Brasil, Ley 25.326 Argentina, Ley 1581 Colombia, LFPDPPP México); patrón común: consentimiento, finalidad, derechos del titular y seguridad.
5. Inventario: por cada dato, anota finalidad, base legal, dónde se guarda, quién accede y plazo de conservación.
6. Aplica minimización: no pidas ni guardes lo que no uses. Cifra o hashea lo sensible; evita datos de menores salvo necesidad y autorización de padres.
7. Entrega mecanismos: política de privacidad accesible, casilla de consentimiento no premarcada, canal para ejercer derechos y borrado real.
8. Terceros (hosting, analítica, IA): regístralos y revisa dónde procesan los datos.

## Anti-patrones
- Casillas premarcadas o consentimiento "por usar el sitio".
- Copiar una política de otra empresa sin adaptarla.
- Guardar datos indefinidamente "por si acaso".
- Enviar datos personales a un servicio externo sin informar.

## Cómo verificar
- Tabla de inventario de datos (dato, finalidad, base, retención) adjunta.
- Capturas o rutas del texto de consentimiento y del canal de derechos ARCO.
- Lista de supuestos pendientes de validar con un profesional. Inclúyelo en `submit_for_review`.
