<?php
// Incluir el manejador de login común
require_once('login_handler.php');

// Si ya está logueado, redirigir a adminventas.php
if (isLoggedIn()) {
    Header("Location: adminventas.php");
    exit();
}

// Procesar login con redirección dinámica
processLogin("adminventas.php");
?>
<?php include("header.php"); ?>
<body>
<?php 
// Mostrar formulario de login con estilo clásico y texto personalizado
renderLoginForm(isset($_GET['error']), "Facturación Woocommerce", "classic"); 
?>
<?php include("foot.php"); ?>

<script src="js/jquery.min.js"></script>
<script src="js/popper.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="js/bootstrap-4.4.1.js" type="text/javascript"></script>

</body>
</html>
