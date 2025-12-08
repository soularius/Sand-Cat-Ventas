# Sistema de Login Dinámico

## 📋 Descripción

El sistema de login ha sido refactorizado para ser más dinámico, reutilizable y mantenible. Elimina la duplicación de código y proporciona funciones flexibles para manejar autenticación.

## 🚀 Funciones Principales

### `isLoggedIn()`
Verifica si el usuario está autenticado.
```php
if (isLoggedIn()) {
    echo "Usuario logueado";
} else {
    echo "Usuario no logueado";
}
```

### `processLogin($success_redirect, $error_redirect)`
Procesa el formulario de login con redirecciones dinámicas.
```php
// Redirigir a adminventas.php en éxito, error automático
processLogin("adminventas.php");

// Redirecciones personalizadas
processLogin("dashboard.php", "login.php?error=failed");
```

### `requireLogin($login_page, $return_url)`
Requiere autenticación, redirige a login si no está autenticado.
```php
// Redirigir a index.php si no está logueado
requireLogin();

// Página de login personalizada
requireLogin("custom_login.php");

// URL de retorno específica
requireLogin("index.php", "/admin/dashboard");
```

### `renderLoginForm($show_error, $button_text, $form_style)`
Renderiza el formulario de login con estilos personalizables.
```php
// Formulario básico
renderLoginForm();

// Con error y texto personalizado
renderLoginForm(true, "Acceder al Panel", "modern");

// Estilo clásico
renderLoginForm(false, "Facturación", "classic");
```

## 📁 Estructura de Archivos

- `login_handler.php` - Lógica principal de autenticación
- `tools.php` - Funciones auxiliares (incluye `isLoggedIn()`)
- `config.php` - Configuración de base de datos

## 🔧 Implementación

### Página de Login Simple
```php
<?php
require_once('login_handler.php');

// Redirigir si ya está logueado
if (isLoggedIn()) {
    Header("Location: dashboard.php");
    exit();
}

// Procesar login
processLogin("dashboard.php");
?>
<?php include("header.php"); ?>
<body>
<?php renderLoginForm(isset($_GET['error'])); ?>
<?php include("footer.php"); ?>
</body>
</html>
```

### Página Protegida
```php
<?php
require_once('login_handler.php');

// Requerir autenticación
requireLogin('index.php');

// El usuario está autenticado aquí
$user = getCurrentUser();
?>
<h1>Bienvenido <?php echo $user['username']; ?></h1>
```

### Login con Redirección Personalizada
```php
<?php
require_once('login_handler.php');

if (isLoggedIn()) {
    Header("Location: admin/panel.php");
    exit();
}

// Redirigir a panel específico después del login
processLogin("admin/panel.php", "login.php?error=invalid");
?>
```

## 🎨 Estilos Disponibles

### Estilo Moderno (`modern`)
- Diseño con cards y gradientes
- Labels flotantes
- Iconos FontAwesome
- Responsive

### Estilo Clásico (`classic`)
- Diseño tradicional
- Campos con placeholders
- Compatible con tema existente

## 🔄 URL de Retorno

El sistema maneja automáticamente las URLs de retorno:

1. Usuario intenta acceder a página protegida
2. Se redirige a login con `?return=/pagina/protegida`
3. Después del login exitoso, regresa a la página original

## 🛡️ Seguridad

- Validación de URLs de retorno (solo rutas relativas)
- Escape de datos de entrada
- Verificación de sesiones
- Protección contra redirecciones abiertas

## 📝 Ejemplos de Uso

### Login Básico
```php
require_once('login_handler.php');
if (isLoggedIn()) Header("Location: admin.php");
processLogin("admin.php");
renderLoginForm(isset($_GET['error']));
```

### Página Administrativa
```php
require_once('login_handler.php');
requireLogin(); // Redirige a index.php si no está logueado
// Contenido protegido aquí
```

### Login Personalizado
```php
require_once('login_handler.php');
processLogin("custom_dashboard.php", "custom_login.php?failed=1");
renderLoginForm(isset($_GET['failed']), "Acceso Administrativo", "classic");
```

## 🔧 Migración

Para migrar páginas existentes:

1. Reemplazar lógica de login duplicada:
```php
// Antes (código duplicado)
if (isset($_POST['usuario'])) {
    // 40+ líneas de código...
}

// Después (una línea)
processLogin("destino.php");
```

2. Reemplazar formularios HTML:
```php
// Antes (HTML estático)
<form>...</form>

// Después (función dinámica)
renderLoginForm(isset($_GET['error']));
```

3. Agregar protección a páginas:
```php
// Al inicio de páginas protegidas
requireLogin();
```
