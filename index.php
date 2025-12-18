<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el manejador de login común
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Si ya está logueado, redirigir a inicio.php
if (isLoggedIn()) {
    Header("Location: inicio.php");
    exit();
}

// Procesar login con redirección dinámica
processLogin("inicio.php");

// 4. DESPUÉS: Cargar presentación
include("parts/header.php");
?>

<body>
<?php 
// Mostrar formulario de login con detección automática de errores
renderLoginForm(isset($_GET['error'])); 
?>
<?php include("parts/foot.php"); ?>
</body>
</html>

