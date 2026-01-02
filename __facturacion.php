<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 3. Lógica de autenticación y procesamiento
// Si ya está logueado, redirigir a inicio.php
if (Utils::isLoggedIn()) {
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
// Mostrar formulario de login con estilo clásico y texto personalizado
renderLoginForm(isset($_GET['error']), "Facturación Woocommerce", "classic"); 
?>
<?php include("parts/footer.php"); ?>

</body>
</html>
