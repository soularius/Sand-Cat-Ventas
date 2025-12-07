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
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['elnombre'];
$id_usuarios = $row_usuario['id_ingreso'];
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
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8'" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<title>Sand&Cat</title>
<meta charset="utf-8'">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="shortcut icon" href="https://sandycat.com.co/wp-content/uploads/2020/05/favicon.jpg" type="image/x-icon" />
	<link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"> -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">  
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
</head>
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
                    <a href="adminf.php" class="btn btn-primary btn-block" role="button">FACTURAR</a><br>
                    <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#myModal">GENERAR PEDIDO</button>
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
	        </div>
			  </div>
	        </div>
	<?php include("foot.php"); ?>
</section>

 
 <!-- The Modal -->
 <div class="modal fade" id="myModal">
     <div class="modal-dialog">


       <div class="modal-content">
         <!-- Modal Header -->
         <div class="modal-header"> 
           <h4 class="modal-title">Generar pedido</h4>
           <button type="button" class="close" data-dismiss="modal">×</button>
         </div>
         <!-- Modal body -->
         <div class="modal-body">
          
						<form action="datos_venta.php" class="login-form" method="post" target="_self" id="adminventas">
                  <div class="form-group">
									  <input type="number" class="form-control" placeholder="Documento cliente" id="billing_id" name="billing_id" value="" required>
									</div>
										<button class="btn btn-primary" type="submit" name="venta" id="venta">Continuar</button>
					 		 </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
      
    </div>
  </div>


  </div> 
 
</body>
</html>
<?php
mysqli_free_result($usuario);
?>