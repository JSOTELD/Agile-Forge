# 🔰 Manual de AgileForge v2 para Novatos (Principiantes)
> *Guía paso a paso para dominar tu Centro de Comando de Agentes IA sin enredos técnicos.*

[![Español](https://img.shields.io/badge/Idioma-Espa%C3%B1ol-yellow.svg)](#-guía-en-español)
[![English](https://img.shields.io/badge/Language-English-blue.svg)](#-english-beginners-guide)
[![Modo Día/Noche](https://img.shields.io/badge/Temas-D%C3%ADa%20%2F%20Noche-purple.svg)](#3--colores-modo-día-y-modo-noche)

---

## 🇪🇸 Guía en Español

Bienvenido a **AgileForge v2**. Si nunca has usado un entorno de desarrollo ágil con agentes de Inteligencia Artificial, ¡este manual es para ti! Aquí aprenderás todo lo necesario desde cero, de forma sencilla y directa.

---

### 1. 🔰 ¿Qué es AgileForge y para qué sirve?

Imagina tener a un equipo completo de profesionales sentados a tu lado:
- 📋 **Project Manager:** Organiza el trabajo y define las prioridades.
- 🎨 **Director Creativo & UX/UI:** Diseña interfaces limpias y verifica la usabilidad.
- 🏛️ **Jefe de Arquitectura:** Elige las mejores tecnologías y diseña la estructura.
- 💻 **Frontend Master:** Programa los botones, pantallas y diseño visual.
- ⚙️ **Backend Master:** Programa la base de datos, APIs y lógica del servidor.
- 🛡️ **QA & Seguridad:** Revisa que el código no tenga errores ni vulnerabilidades.
- ⚖️ **Abogado / Legal Counsel:** Revisa que cumplas las leyes de privacidad (RGPD, Ley de Protección de Datos, etc.).

En lugar de tener que copiar y pegar prompts gigantescos en ChatGPT o Claude, **AgileForge les da a estos agentes un tablero Scrum real**. Ellos leen las tareas, las toman, escriben reportes técnicos y se reúnen para debatir soluciones contigo como el Product Owner humano.

---

### 2. ⚡ Puesta en Marcha Rápida (Primer Uso)

Si acabas de descargar el proyecto:

1. **Abre tu terminal** en la carpeta del proyecto.
2. **Ejecuta la inicialización automática:**
   ```bash
   php bin/migrate.php
   ```
   *Esto crea la base de datos local y configura tu token seguro.*
3. **Inicia el servidor:**
   - Si usas XAMPP: abre tu navegador en `http://localhost/Open-to-code/`.
   - Si prefieres PHP directo:
     ```bash
     php -S localhost:8000
     ```
     y abre `http://localhost:8000` en tu navegador.

---

### 3. 🌓 Colores: Modo Día y Modo Noche

AgileForge cuida tu vista y se adapta a cualquier ambiente de iluminación:

#### ☀️ ¿Cómo activar el Modo Día (Luz)?
- **Opción rápida:** En la barra superior, haz clic en el botón con el icono **🌙 Noche / ☀️ Día**. Al hacer clic pasará inmediatamente a **☀️ Modo Día (Solar Day)** con fondo blanco y tipografía oscura de máximo contraste.
- **Opción avanzada (Paletas):** Haz clic en el botón **🎨 Tema** para elegir entre 6 paletas:
  1. **Cyber Obsidian (Noche):** Fondo ultra oscuro tipo terminal cyberpunk (predeterminado).
  2. **Electric Indigo (Noche Azul):** Fondo azul profundo relajante para la vista nocturna.
  3. **Neon Emerald (Noche Verde):** Estilo hacker verde matriz con alta legibilidad.
  4. **Midnight Violet (Noche Púrpura):** Estilo sobrio y moderno en tonos violeta.
  5. **☀️ Solar Day (Modo Día):** Fondo blanco puro de alto contraste, ideal para oficinas con luz o proyectores.
  6. **📄 Clean Paper (Día Suave):** Fondo cálido tipo papel libro, ideal para leer sin encandilarte.

> 💡 **Nota:** Tu tema preferido se guarda automáticamente. Cuando cierres y vuelvas a abrir el navegador, recordará tu elección.

---

### 4. 🌐 Idioma: Español e Inglés (i18n)

¿Prefieres la interfaz en inglés o en español?

- En la barra superior, busca el botón **🌐 ES | EN**.
- Haz clic una vez y la aplicación cambiará instantáneamente todo su vocabulario entre **Español** e **Inglés**:
  - *Tablero Scrum* ⇄ *Scrum Board*
  - *Laboratorio UX/UI* ⇄ *UX/UI & Design Lab*
  - *Sala de Reuniones* ⇄ *Meeting Room*
  - *Nueva Historia* ⇄ *New Story*
- La preferencia se almacena en tu navegador para tus próximas sesiones.

---

### 5. 📋 El Tablero Scrum (Cómo organizar el trabajo)

El tablero Kanban tiene **4 columnas** que representan el ciclo de vida de cualquier trabajo:

| Columna | ¿Qué significa? | ¿Quién actúa aquí? |
| :--- | :--- | :--- |
| **1. Historias (Backlog)** | Ideas, tareas y requerimientos por hacer. | Tú (humano) o el Project Manager. |
| **2. En Desarrollo** | Tareas que alguien está programando o diseñando activamente. | Un agente o tú. (La tarea queda bloqueada para que nadie más la pise). |
| **3. Revisión DoD / QA** | Trabajo terminado esperando control de calidad. | El agente aporta pruebas o diffs. |
| **4. Aprobadas / Done** | Tareas finalizadas y listas para producción. | **Solo tú (humano) o el rol QA**. |

#### 📝 ¿Cómo crear tu primera tarea?
1. Haz clic en el botón azul **"+ Nueva Historia"** (esquina superior derecha).
2. Escribe el título (ej: *"Crear pantalla de inicio de sesión"*).
3. Asigna el rol (ej: *Frontend Master* o *Backend Master*).
4. Elige los puntos de esfuerzo (Story Points: 1, 2, 3, 5, 8).
5. Escribe la historia de usuario y qué condiciones debe cumplir para considerarse terminada (DoD).
6. Haz clic en **Guardar Historia**. ¡Aparecerá en la columna Backlog de inmediato!

---

### 6. 🦙 Inteligencia Artificial Local (Ollama & Colibri)

¿Quieres usar IA **completamente gratis, sin pagar suscripciones a OpenAI o Anthropic y con 100% de privacidad**?

1. Instala **Ollama** en tu computadora (descárgalo gratis en [ollama.com](https://ollama.com)) y descarga un modelo rápido en tu terminal:
   ```bash
   ollama run gemma:2b
   # o
   ollama run llama3.2
   ```
2. Abre AgileForge: notarás que el botón **🦙 IA local** en la cabecera se pone en verde indicando el modelo detectado.
3. Al encenderlo, las consultas del chat y los debates de las reuniones se procesarán **directamente en tu tarjeta de video o memoria RAM**. ¡Cero costos y ningún dato saldrá a internet!

---

### 7. 🎙️ Salas de Reunión: Debates Multi-Agente

Cuando tengas una duda difícil o necesites innovar:
1. Haz clic en la pestaña **"Sala de Reuniones"** (o presiona `Alt + 4`).
2. Elige el tipo de reunión:
   - **💡 Brainstorming de Innovación:** Ideal para generar ideas locas y creativas para tu producto.
   - **🏛️ Consejo de Arquitectura (ADR):** Para tomar decisiones técnicas cruciales (ej: ¿SQL o NoSQL?).
3. Selecciona los agentes participantes (ej: Arquitecto + Director Creativo + Seguridad).
4. Haz clic en **Iniciar Reunión**.
5. Los agentes hablarán por turnos, debatirán entre sí y redactarán un **Acta Formal con Acuerdos**.
6. Si estás de acuerdo con una propuesta, presiona **"Convertir en Historia de Backlog"** y pasará directo a tu tablero Kanban.

---

### 8. ⌨️ Atajos de Teclado Imprescindibles

| Atajo | Acción |
| :--- | :--- |
| <kbd>Ctrl</kbd> + <kbd>K</kbd> | Búsqueda rápida de historias y comandos |
| <kbd>Alt</kbd> + <kbd>M</kbd> | Abrir este manual de novatos en pantalla |
| <kbd>Alt</kbd> + <kbd>1</kbd> | Ir al Tablero Scrum |
| <kbd>Alt</kbd> + <kbd>2</kbd> | Ir al Laboratorio UX/UI |
| <kbd>Alt</kbd> + <kbd>3</kbd> | Ir a Auditoría Legal |
| <kbd>Alt</kbd> + <kbd>4</kbd> | Ir a Sala de Reuniones |
| <kbd>Alt</kbd> + <kbd>5</kbd> | Ir al Equipo de Agentes |
| <kbd>ESC</kbd> | Cerrar cualquier ventana emergente |

---

## 🇺🇸 English Beginner's Guide

Welcome to **AgileForge v2**! This quick start guide will help you understand and use the multi-agent AI workspace in minutes.

### 1. What is AgileForge?
AgileForge is a multi-agent Scrum Command Center. Instead of manually copy-pasting prompts into chat models, AgileForge provides an autonomous squad of 7 AI specialists (Project Manager, UX Designer, Chief Architect, Frontend Master, Backend Master, QA Sentinel, Legal Counsel) working collaboratively on a real Kanban board with SQLite transactional persistence.

### 2. Day & Night Modes (Theme Colors)
- Click the **🌙 Night / ☀️ Day** button in the top navbar to instantly toggle between dark mode and high-contrast light mode.
- Click the **🎨 Theme** button to choose from 6 calibrated palettes:
  - *Cyber Obsidian* (Default Cyber Dark)
  - *Electric Indigo* (Deep Night Blue)
  - *Neon Emerald* (Matrix Green)
  - *Midnight Violet* (Deep Purple)
  - *Solar Day* (Clean White, High Contrast)
  - *Clean Paper* (Warm Soft Paper)

### 3. Language Switcher (Spanish / English)
- Click the **🌐 ES | EN** button in the top header.
- The entire interface instantly updates all labels, tabs, and columns to English.
- Your language preference is saved in your browser storage.

### 4. Scrum Kanban Flow
1. **Backlog:** Work waiting to be tackled. Click `+ New Story` to create one.
2. **In Progress:** Stories currently being worked on (atomically locked to prevent conflicts).
3. **DoD / QA Review:** Finished work submitted with diff and test evidence.
4. **Approved / Done:** Stories verified and completed. *(Governance rule: AI agents cannot approve their own work; approval requires human sign-off or QA Sentinel clearance)*.

### 5. Local AI Support (Zero Cloud Cost & Privacy)
Toggle the **🦙 Local AI** switch in the header to run all agents locally using **Ollama** or **Colibri** on your PC. No data leaves your machine and API cost is \$0.00.

### 6. Keyboard Shortcuts
- <kbd>Ctrl</kbd> + <kbd>K</kbd>: Instant search
- <kbd>Alt</kbd> + <kbd>M</kbd>: Open Beginner Manual
- <kbd>Alt</kbd> + <kbd>1..5</kbd>: Switch tabs
- <kbd>ESC</kbd>: Close modals

---

*Desarrollado con ❤️ por el equipo de AgileForge. ¡A programar el futuro!*

