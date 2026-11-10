---
name: security-hardening
description: Úsala cuando toques autenticación, tokens, entradas de usuario, subida de archivos, sesiones o cabeceras de una app PHP o un servidor MCP.
title: "Endurecimiento de seguridad"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering"]
---

# Endurecimiento de seguridad

## Cuándo usarla
Siempre que una historia procese entrada externa, credenciales, permisos o salida HTML.

## Checklist (alineado con OWASP Top 10)
1. Inyección: sentencias preparadas PDO; nunca interpoles SQL. Comandos de sistema con `escapeshellarg` o, mejor, sin shell.
2. XSS: escapa toda salida con `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`; en JS usa `textContent`, no `innerHTML` con datos externos. Añade `Content-Security-Policy` razonable.
3. Autenticación: comparar tokens con `hash_equals`; secretos en `.env`, nunca en el repositorio ni en logs. Separa credenciales de escritura y de solo lectura (principio de mínimo privilegio).
4. Autorización: comprueba permisos en el servidor en cada acción, con la identidad de sesión/token. Nunca confíes en parámetros de la petición como `human_approved`.
5. Sesiones y cookies: `HttpOnly`, `Secure`, `SameSite=Lax` o `Strict`; regenera el id al iniciar sesión; token CSRF en formularios que cambian estado.
6. Subidas: lista blanca de tipo MIME y extensión, nombre generado por el servidor, almacenamiento fuera del directorio público.
7. Rutas: valida con `realpath` y verifica que el resultado queda bajo el directorio permitido (evita `../`).
8. Errores: mensaje genérico al cliente, detalle solo en log; `display_errors=0` en producción.
9. Dependencias: `composer audit` / `npm audit`; fija versiones.
10. Cabeceras: `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options` o `frame-ancestors`.
11. Servidor MCP: el token nunca se imprime (ni en `connection_info`); los errores no filtran URLs con credenciales.

## Anti-patrones
- Validar solo en el cliente; usar `md5` para contraseñas; `unserialize` de entrada externa; CORS `*` con credenciales.

## Cómo verificar
- `grep` de patrones peligrosos (`$_REQUEST`, `innerHTML`, `exec(`, concatenación en SQL) con resultado limpio.
- Prueba negativa: petición sin token y con token de solo lectura devuelve 401/403.
- Entrada maliciosa (`<script>`, `' OR 1=1`) neutralizada. Pega estas pruebas en `submit_for_review`.
