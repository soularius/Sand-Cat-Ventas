<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el manejador de login común
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Si ya está logueado, redirigir a inicio.php
if (Utils::isLoggedIn()) {
    Header("Location: inicio.php");
    exit();
}

// Procesar login con redirección dinámica
// IMPORTANTE: Esto debe ejecutarse ANTES de cualquier output
processLogin("inicio.php");

// 4. SOLO DESPUÉS de completar toda la lógica: Cargar presentación
include("parts/header.php");
?>

<body>
<?php 
// Mostrar formulario de login con detección automática de errores
renderLoginForm(isset($_GET['error'])); 
?>
<?php include("parts/footer.php"); ?>
</body>
</html>

