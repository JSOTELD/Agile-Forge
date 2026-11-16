---
name: avalonia-ui
description: Úsala cuando construyas o revises interfaces de escritorio nativas con Avalonia 12 (XAML y MVVM) para Windows, macOS y Linux, sin tecnología web.
title: "Interfaces nativas con Avalonia (XAML)"
category: design
level: Principal
status: active
tags: ["AgileForge","design","avalonia","xaml","desktop"]
---

# Interfaces nativas con Avalonia (XAML)

## Cuándo usarla
Pantallas, componentes, temas y navegación de una app de escritorio en Avalonia 12. Avalonia dibuja toda la interfaz por su cuenta: no hay HTML, CSS ni navegador.

## Pasos
1. **MVVM estricto:** vistas en `.axaml`, lógica en ViewModels con `CommunityToolkit.Mvvm` (`[ObservableProperty]`, `[RelayCommand]`). Nada de lógica en el code-behind salvo detalles puramente visuales.
2. **Bindings compilados:** `x:DataType` en cada vista y `AvaloniaUseCompiledBindingsByDefault` en el proyecto; un binding roto debe fallar al compilar.
3. **Tokens de diseño como recursos:** colores, tipografías, espaciados y radios en `App.axaml` (`ResourceDictionary` con `ThemeDictionaries` Light/Dark). Ningún color escrito a mano en una vista.
4. **Tema:** `FluentTheme` como base, con estilos propios para los componentes del sistema de diseño (chips, tarjetas, botones).
5. **Fuentes incrustadas:** las tipografías van dentro del ejecutable (`avares://`), no se asume que estén instaladas en la PC.
6. **Hilos:** el trabajo largo corre fuera del hilo de la interfaz; los cambios visibles vuelven con `Dispatcher.UIThread`. La interfaz nunca se congela.
7. **Listas largas:** controles virtualizados (`ListBox`, `ItemsRepeater`, `DataGrid`); nunca cientos de controles creados a mano.
8. **Accesibilidad:** `AutomationProperties.Name` en controles sin texto, orden de tabulación lógico, foco visible, estado con color + ícono + texto, contraste AA.
9. **Textos:** en recursos (`.resx` o diccionarios) para poder corregirlos sin tocar la lógica.

## Anti-patrones
- Incrustar un WebView para «hacerlo más rápido» con HTML.
- Valores mágicos de tamaño y color repartidos por las vistas.
- Llamadas de red o base de datos desde el code-behind.

## Cómo verificar
- La app compila sin advertencias de bindings.
- Pruebas de interfaz con `Avalonia.Headless.XUnit` para los flujos principales.
- Revisión manual: tema claro y oscuro, 1280×800 y 1920×1080, uso completo solo con teclado.
