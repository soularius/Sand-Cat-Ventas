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
// Mostrar formulario de login con estilo clásico y texto personalizado
renderLoginForm(isset($_GET['error']), "Facturación Woocommerce", "classic"); 
?>
<?php include("parts/foot.php"); ?>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/popper.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/bootstrap-4.4.1.js" type="text/javascript"></script>

</body>
</html>
