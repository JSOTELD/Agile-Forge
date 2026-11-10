---
name: compliance-sentinel
description: Úsala cuando necesites revisar una historia o entrega contra requisitos legales, de licencias y de privacidad antes de enviarla a revisión.
title: "Centinela de cumplimiento"
category: legal
level: Principal
status: active
tags: ["AgileForge","legal"]
---

# Centinela de cumplimiento

> Aviso: esta guía es orientativa y no sustituye la asesoría de un abogado o profesional colegiado. Valida con un profesional antes de publicar o firmar.

## Cuándo usarla
Como puerta final en historias con datos personales, pagos, contenido de terceros, dependencias nuevas o textos públicos.

## Checklist
1. **Datos:** ¿qué datos personales nuevos aparecen? ¿hay finalidad, consentimiento y retención definidos (ver `legal-frameworks`)? ¿se registran en logs?
2. **Secretos:** busca claves, tokens o contraseñas en código y commits; deben vivir en `.env` ignorado por git.
3. **Licencias:** toda dependencia, fuente, icono o imagen nueva tiene licencia compatible (MIT, Apache, OFL) y atribución si la exige. Nada de código o texto copiado sin permiso.
4. **Textos públicos:** términos, privacidad, Libro de Reclamaciones y avisos presentes cuando haya venta o captura de datos (ver `terms-and-disclaimers`).
5. **Promesas:** afirmaciones de producto verificables; sin garantías ni cifras inventadas.
6. **Terceros:** servicios externos listados con qué datos reciben.
7. **Menores y datos sensibles:** salud, biometría, ubicación exigen consentimiento reforzado; si no es indispensable, no se recoge.
8. **Trazabilidad:** decisión legal relevante registrada con `record_decision` (o `registrar_decision` en la web).

## Severidad
- Bloqueante: dato sensible sin consentimiento, secreto expuesto, licencia incompatible.
- Alta: falta política o aviso obligatorio.
- Media/baja: redacción, mejoras.

## Anti-patrones
- Aprobar "porque es un prototipo".
- Revisar solo el código y no los textos.

## Cómo verificar
- Informe breve (≤ 200 palabras): hallazgos por severidad con archivo y línea.
- Salida de `grep` para secretos y lista de licencias nuevas.
- Recuerda: no sustituye asesoría profesional; marca lo que requiera abogado. Entrégalo con `add_comment` o `submit_for_review`.
