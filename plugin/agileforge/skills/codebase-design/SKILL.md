---
name: codebase-design
description: Úsala cuando vayas a estructurar módulos, capas o carpetas de una funcionalidad nueva o a refactorizar la arquitectura de PHP 8.2 o del servidor MCP en TypeScript.
title: "Diseño de código y arquitectura"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Diseño de código y arquitectura

## Cuándo usarla
Al crear un módulo, separar responsabilidades o decidir dónde vive una pieza de lógica.

## Pasos
1. Identifica el flujo: entrada (HTTP o herramienta MCP) -> validación -> caso de uso -> persistencia -> respuesta.
2. Separa en tres capas: transporte (`api/*.php`, handlers MCP), lógica de negocio (funciones o clases sin I/O directo) y acceso a datos (PDO). La lógica no debe leer `$_REQUEST` ni imprimir JSON.
3. Dependencias hacia adentro: el dominio no conoce PDO ni HTTP; se le inyecta lo que necesite.
4. En PHP 8.2 usa `declare(strict_types=1)`, tipos en parámetros y retorno, `readonly` para valores, enums para estados y `match` en lugar de cadenas de `if`.
5. En TypeScript (MCP) valida la entrada con un esquema por herramienta, devuelve `isError: true` con mensaje accionable y no mezcles cliente HTTP con definición de herramientas.
6. Un archivo = una razón para cambiar. Si supera ~300 líneas o mezcla capas, divídelo.
7. Escribe primero la interfaz pública (firmas y contrato) y luego el cuerpo; evita configuración global y estado oculto.

## Anti-patrones
- Archivos "dios" que consultan BD, validan y formatean la respuesta.
- Abstracciones para un solo caso de uso (interfaz con una única implementación sin necesidad).
- Lógica duplicada entre la API y el MCP en vez de una función compartida.
- Leer superglobales fuera de la capa de transporte.

## Cómo verificar
- `php -l` sobre cada archivo tocado y la salida del linter o `tsc --noEmit`.
- Muestra el árbol de archivos nuevos o movidos y una frase por cada uno con su responsabilidad.
- Demuestra con `grep` que la capa de dominio no contiene `PDO`, `$_GET`, `$_POST` ni `echo`.
- Adjunta esa evidencia en `submit_for_review`.
