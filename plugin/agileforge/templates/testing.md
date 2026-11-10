---
estado: borrador          # borrador | aprobada. El tester solo prueba con "aprobada", y solo David la cambia.
aprobada_por:             # nombre de quien la aprobó
fecha:                    # AAAA-MM-DD de la aprobación
---

# Receta de pruebas · <nombre del proyecto>

> Plantilla de `.agileforge/testing.md` para el Tester de AgileForge (v0, experimental).
> Rellena cada sección con comandos concretos y copiables. Aquí **nunca** van credenciales reales:
> solo credenciales DE PRUEBA, o la indicación de dónde las genera el entorno desechable.

## 1. Entorno desechable
Cómo arrancar la app **sin tocar datos reales**: base de datos temporal, carpeta temporal propia y puerto libre.

```bash
# Ejemplo: copiar y adaptar
export TESTER_TMP="$(mktemp -d)"
export DB_URL="sqlite:///$TESTER_TMP/tester.sqlite"
<comando que arranca la app> --port 8099 2> "$TESTER_TMP/server.log" &
echo $! > "$TESTER_TMP/server.pid"
```

- **URL base:** `http://127.0.0.1:8099`
- **Suite existente** (debe estar en verde antes de empezar): `<comando>`

## 2. Datos de ejemplo
Cómo sembrar la base desechable (usuarios, proyectos, registros de ejemplo) y cómo volver a dejarla limpia.

```bash
<comando de semilla>
```

## 3. Perfiles (credenciales DE PRUEBA)
Entre 2 y 3 perfiles propios del proyecto. El tester añade siempre **usuario nuevo** y **sin credenciales**.

| perfil | qué puede hacer | cómo se obtiene la credencial (nunca el valor real) |
|---|---|---|
| <admin> | <…> | <usuario/contraseña de prueba de la semilla, o archivo del entorno desechable> |
| <usuario> | <…> | <…> |
| usuario nuevo | instalación desde cero con los datos de ejemplo | <pasos de instalación limpia> |
| sin credenciales | nada protegido | sin cabecera ni cookie |

## 4. Log del servidor
- **Dónde está:** `<ruta>` (y la salida de error del proceso, si la hay).
- **Cómo leer solo lo nuevo de una tanda:** anotar el tamaño o el número de líneas antes y leer desde ahí después.

## 5. Límite de peticiones
- **Límite del sistema:** `<N peticiones por minuto, o "ninguno">`.
- **Ritmo del tester:** `<p. ej. ≤ 2 peticiones por segundo; ante un 429, esperar 60 s>`.

## 6. Reiniciar y apagar
```bash
# Reiniciar desde cero
<comandos>

# Apagar y limpiar (procesos, bases y carpetas temporales)
kill "$(cat "$TESTER_TMP/server.pid")"; rm -rf "$TESTER_TMP"
```

## 7. Carpeta del tester
- **Único lugar donde el tester puede escribir:** `<ruta, p. ej. tests/tester/>`
- **Cómo ejecutar sus pruebas:** `<comando>`
- Las pruebas que reproducen bugs fallan a propósito: esa carpeta **no** debe formar parte de la suite que corre el CI.

## 8. Fuera de alcance
Lo que el tester no debe probar ni tocar (servicios de pago, envío real de correos, integraciones externas, …).

