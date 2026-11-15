---
name: csharp-dotnet
description: Úsala cuando escribas o revises código C# en .NET 10 (dominio, servicios, datos o pruebas) y quieras código tipado, asíncrono correcto, con inyección de dependencias y probado con xUnit.
title: "C# y .NET 10"
category: engineering
level: Principal
status: active
tags: ["AgileForge","engineering","csharp","dotnet"]
---

# C# y .NET 10

## Cuándo usarla
En cualquier historia con código C#: núcleo de dominio, servicios, acceso a datos, integraciones o pruebas.

## Pasos
1. **Proyecto:** .NET 10 (LTS). En cada `.csproj`: `<Nullable>enable</Nullable>`, `<TreatWarningsAsErrors>true</TreatWarningsAsErrors>`, `<AnalysisLevel>latest-recommended</AnalysisLevel>`. Versiones de paquetes centralizadas en `Directory.Packages.props`.
2. **Capas:** el dominio va en un proyecto propio sin E/S (sin archivos, red ni base de datos) para poder probarlo solo. Los demás proyectos dependen del dominio, nunca al revés.
3. **Tipos:** `record` para datos inmutables, `sealed` por defecto, `required` en propiedades obligatorias. Dinero y porcentajes siempre en `decimal`, nunca en `double`.
4. **Asíncrono:** `async`/`await` de punta a punta con `CancellationToken`. Prohibido `.Result`, `.Wait()` y `async void` (salvo manejadores de eventos de la interfaz).
5. **Inyección de dependencias:** `Microsoft.Extensions.Hosting` + DI + `IOptions<T>` para la configuración; `ILogger<T>` para registros, sin `Console.WriteLine`.
6. **Errores:** excepciones con mensaje accionable; resultados esperados (validaciones) como valores, no como excepciones.
7. **Pruebas:** xUnit; un proyecto de pruebas por capa; casos con datos reales conocidos (`[Theory]` + `[InlineData]`). El dominio cubierto al 100 %.
8. **Estilo:** espacios de nombres por archivo, nombres que dicen la intención, métodos cortos, sin comentarios que repiten el código (ver `clean-code`).

## Anti-patrones
- `double` para montos; `DateTime.Now` en el dominio (inyecta un reloj).
- Clases estáticas con estado; servicios que crean sus dependencias con `new`.
- Atrapar `Exception` y seguir como si nada.

## Cómo verificar
- `dotnet build -warnaserror` sin advertencias.
- `dotnet test` en verde.
- `dotnet format --verify-no-changes` sin cambios pendientes.
