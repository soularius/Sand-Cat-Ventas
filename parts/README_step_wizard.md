# Step Wizard Component

Componente dinámico para mostrar el progreso de pasos en formularios tipo wizard.

## Ubicación
`parts/step_wizard.php`

## Uso Básico

```php
<?php 
// Configurar el paso actual
$current_step = 2; // Paso actual (1-4)
include('parts/step_wizard.php'); 
?>
```

## Uso Avanzado

### Configuración Personalizada de Pasos

```php
<?php 
// Configurar pasos personalizados
$steps_config = [
    1 => [
        'label' => 'Información Personal',
        'icon' => 'fas fa-user',
        'page' => 'datos_personales.php'
    ],
    2 => [
        'label' => 'Selección de Productos',
        'icon' => 'fas fa-shopping-cart',
        'page' => 'productos.php'
    ],
    3 => [
        'label' => 'Método de Pago',
        'icon' => 'fas fa-credit-card',
        'page' => 'pago.php'
    ],
    4 => [
        'label' => 'Resumen Final',
        'icon' => 'fas fa-check-circle',
        'page' => 'resumen.php'
    ]
];

$current_step = 2;
include('parts/step_wizard.php'); 
?>
```

### Detección Automática del Paso

```php
<?php 
// El componente detectará automáticamente el paso basado en el nombre del archivo
include('parts/step_wizard.php'); 
?>
```

## Parámetros

### Variables PHP

- `$current_step` (int): Número del paso actual (1-4)
- `$steps_config` (array): Configuración personalizada de pasos (opcional)

### Estructura de $steps_config

```php
$steps_config = [
    step_number => [
        'label' => 'Texto del paso',
        'icon' => 'Clase de FontAwesome (opcional)',
        'page' => 'archivo.php (para navegación)'
    ]
];
```

## Estados de los Pasos

1. **Completado** (`completed`): Pasos anteriores al actual
   - Círculo verde con ✓
   - Clicable para navegación
   - Confirmación antes de navegar

2. **Activo** (`active`): Paso actual
   - Círculo azul escalado
   - No clicable
   - Indicador visual destacado

3. **Pendiente** (sin clase): Pasos futuros
   - Círculo gris
   - No clicable
   - Cursor "not-allowed"

## JavaScript

### Funciones Disponibles

```javascript
// Actualizar progreso dinámicamente
updateStepProgress(3); // Cambiar al paso 3
```

### Eventos

- **Click en paso completado**: Navegación con confirmación
- **Hover en paso completado**: Efecto de opacidad
- **Tooltips**: Información contextual en cada paso

## Estilos CSS

Los estilos están definidos en `assets/css/wizard-form.css`:

- `.step-wizard`: Contenedor principal
- `.step`: Elemento individual de paso
- `.step-circle`: Círculo numerado
- `.step-label`: Etiqueta de texto
- `.step.active`: Estado activo
- `.step.completed`: Estado completado

## Ejemplos de Implementación

### En formulario_cliente.php (Paso 1)
```php
<?php 
$current_step = 1;
include('parts/step_wizard.php'); 
?>
```

### En resumen_cliente.php (Paso 2)
```php
<?php 
$current_step = 2;
include('parts/step_wizard.php'); 
?>
```

### En pago_venta.php (Paso 3)
```php
<?php 
$current_step = 3;
include('parts/step_wizard.php'); 
?>
```

### En confirmar_venta.php (Paso 4)
```php
<?php 
$current_step = 4;
include('parts/step_wizard.php'); 
?>
```

## Características

- ✅ **Responsive**: Se adapta a móviles y desktop
- ✅ **Dinámico**: Configuración flexible de pasos
- ✅ **Navegable**: Click en pasos completados
- ✅ **Accesible**: Tooltips y estados visuales claros
- ✅ **Reutilizable**: Un solo archivo para todo el sistema
- ✅ **Personalizable**: Configuración de pasos y páginas

## Dependencias

- Bootstrap 5 (grid system)
- FontAwesome (iconos, opcional)
- CSS Variables del sistema (colores)
- wizard-form.css (estilos específicos)
