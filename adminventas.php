<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
// Cargar configuración desde archivo .env
require_once('config.php');

if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
// La función isAuthorized() ahora está disponible desde tools.php



$MM_restrictGoTo = "http://localhost/ventas/facturacion.php";


if (!((isset($_SESSION['MM_Username'])))) { 
  $MM_qsChar = "?";
  $MM_referrer = $_SERVER['PHP_SELF'];
  if (strpos($MM_restrictGoTo, "?")) $MM_qsChar = "&";
  if (isset($QUERY_STRING) && strlen($QUERY_STRING) > 0) 
  $MM_referrer .= "?" . $QUERY_STRING;
  $MM_restrictGoTo = $MM_restrictGoTo. $MM_qsChar . "accesscheck=" . urlencode($MM_referrer);
  header("Location: ". $MM_restrictGoTo); 
  exit;
}
$colname_usuario = '';
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = isset($row_usuario['elnombre']) ? $row_usuario['elnombre'] : '';
$id_usuarios = isset($row_usuario['id_ingreso']) ? $row_usuario['id_ingreso'] : 0;

// Crear variable compatible para el menú
if (!isset($row_usuario['nombre']) && isset($row_usuario['elnombre'])) {
    $row_usuario['nombre'] = $row_usuario['elnombre'];
}
$hoy = date("Y-m-d");



if(isset($_POST['id_ventas']) && isset($_POST['cancela'])) {
	$_POST['id_ventas'];
	$_POST['num'];
	$venta = $_POST['id_ventas'];
	$factu = $_POST['num'];
	$query = "UPDATE miau_posts SET post_status = 'wc-cancelled' WHERE ID = '$venta'";
	mysqli_query($miau, $query);
	$query = "UPDATE miau_wc_order_stats SET status = 'wc-cancelled' WHERE order_id = '$venta'";
	mysqli_query($miau, $query);

  $query_vcancel = sprintf("SELECT order_id, product_id, product_qty FROM miau_wc_order_product_lookup WHERE order_id = '$venta'");
	$vcancel = mysqli_query($miau, $query_vcancel) or die(mysqli_error($miau));
	$row_vcancel = mysqli_fetch_assoc($vcancel);
	$totalRows_vcancel = mysqli_num_rows($vcancel);
  do { 
    $product_id = $row_vcancel['product_id'];
    $product_qty = $row_vcancel['product_qty'];

  $query_stock = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_stock'");
	$stock = mysqli_query($miau, $query_stock) or die(mysqli_error($miau));
	$row_stock = mysqli_fetch_assoc($stock);
	$totalRows_stock = mysqli_num_rows($stock);

    $_stock1 = $row_stock['meta_value'];
    $_stock2 = $row_stock['meta_value'] + $product_qty;
    $query9 = "UPDATE miau_postmeta SET meta_value = '$_stock2' WHERE post_id = '$product_id' AND meta_key = '_stock'";
	mysqli_query($miau, $query9);
    if($_stock2 > 0) {       
        $query10 = "UPDATE miau_postmeta SET meta_value = 'instock' WHERE post_id = '$product_id' AND meta_key = '_stock_status'";
	    mysqli_query($miau, $query10); 
    }
    } while ($row_vcancel = mysqli_fetch_assoc($vcancel));



}

?>
<?php include("header.php"); ?>
<body>
<div class="container">
<?php include("menf.php"); ?>
<section class=""><br />
<br />
<br />
<br />
			<div class="row justify-content-center">
				<div class="col-md-6 text-center mb-5">
					<h2 class="heading-section">Ventas Woocomerce</h2>
			  </div>
	  </div>
			<div class="row justify-content-center" style="margin-top: -30px">
			  <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
                    <a href="adminf.php" class="btn btn-primary w-100 mb-3" role="button">
                        <i class="fas fa-file-invoice me-2"></i>FACTURAR
                    </a>
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#myModal">
                        <i class="fas fa-plus-circle me-2"></i>GENERAR PEDIDO
                    </button>
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
	        </div>
			  </div>
	        </div>
	<?php include("foot.php"); ?>
</section>

 
<!-- Modal para Generar Pedido -->
<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title text-white" id="myModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Generar Nuevo Pedido
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body">
                <form action="datos_venta.php" class="needs-validation" method="post" target="_self" id="adminventas" novalidate>
                    <div class="mb-3">
                        <label for="billing_id" class="form-label">
                            <i class="fas fa-id-card me-2"></i>Documento del Cliente
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               placeholder="Ingrese el documento del cliente" 
                               id="billing_id" 
                               name="billing_id" 
                               value="" 
                               required>
                        <div class="invalid-feedback">
                            Por favor ingrese un documento válido.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button class="btn btn-success btn-lg" type="submit" name="venta" id="venta">
                            <i class="fas fa-arrow-right me-2"></i>Continuar con el Pedido
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Validación de formulario Bootstrap 5
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>


  </div> 
 
</body>
</html>
<?php
mysqli_free_result($usuario);
?>