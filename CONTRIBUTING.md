# 🤝 Guía de Contribución para AgileForge v2

¡Gracias por tu interés en contribuir a **AgileForge v2**! Este proyecto combina el desarrollo de software humano con agentes de Inteligencia Artificial colaborando sobre Scrum y la 7ma Edición del PMBOK.

---

## 🏛️ Principios de Arquitectura

1. **Persistencia SQLite Centralizada:**
   - Todo cambio de estado (tareas, reuniones, decisiones, comentarios) debe pasar por `api/Repository.php` utilizando transacciones atómicas de PDO.
   - La base de datos opera en modo `WAL` (`journal_mode = WAL`) con `busy_timeout = 5000ms`.

2. **Servidor MCP como Puente Estándar:**
   - Toda nueva capacidad ofrecida a los agentes debe implementarse como una herramienta tipada en `mcp-server/src/index.ts` con esquemas validados con `zod`.
   - Las herramientas MCP deben seguir el patrón: entradas en snake_case/camelCase validadas, nombres en inglés y descripciones en español claro.

3. **Gobernanza Humana Inquebrantable:**
   - Los agentes pueden mover historias a `en_progreso` (con lock atómico) y a `revision` (aportando evidencia de diffs y tests).
   - El paso a `completada` está reservado exclusivamente para el Product Owner humano o dictamen explícito del rol QA.

4. **Honestidad Técnica:**
   - No se permiten certificaciones simuladas ni puntajes estáticos en la interfaz. Si un dato proviene de una heurística, del DOM o de una API externa, la UI debe indicarlo explícitamente.

---

## 🛠️ Flujo de Desarrollo Local

```bash
# 1. Clonar el repositorio
git clone https://github.com/<tu-usuario>/AgileForge.git
cd AgileForge

# 2. Inicializar base de datos y generar .env
php bin/migrate.php

# 3. Instalar dependencias y compilar servidor MCP
cd mcp-server
npm install
npm run build
cd ..

# 4. Iniciar servidor de desarrollo
php -S localhost:8000
```

---

## 🧪 Pruebas y Validación Antes de un Pull Request

Asegúrate de que todas las pruebas pasen localmente antes de enviar tu PR:

```bash
# 1. Comprobar sintaxis PHP
find . -name "*.php" -exec php -l {} +

# 2. Comprobar sintaxis de módulos ES
node -c assets/js/app.js

# 3. Recompilar TypeScript de MCP
cd mcp-server && npm run build && cd ..

# 4. Probar migración idempotente
php bin/migrate.php
```

¡Bienvenido al equipo!

