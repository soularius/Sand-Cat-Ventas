<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
$hostname_sandycat = "localhost";
$database_sandycat = "ventassc";
$username_sandycat = "root";
$password_sandycat = "";
$sandycat = new mysqli($hostname_sandycat, $username_sandycat, $password_sandycat, $database_sandycat);
if ($sandycat -> connect_errno) {
die( "Fallo la conexión a MySQL: (" . $mysqli -> mysqli_connect_errno() 
. ") " . $mysqli -> mysqli_connect_error());
}
if (!mysqli_set_charset($sandycat, "utf8mb4")) {
    printf(" utf8mb4: %s\n", mysqli_error($sandycat));
    exit();
}

if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
function isAuthorized($strUsers, $strGroups, $UserName, $UserGroup) { 
  // For security, start by assuming the visitor is NOT authorized. 
  $isValid = False; 

  // When a visitor has logged into this site, the Session variable MM_Username set equal to their username. 
  // Therefore, we know that a user is NOT logged in if that Session variable is blank. 
  if (!empty($UserName)) { 
    // Besides being logged in, you may restrict access to only certain users based on an ID established when they login. 
    // Parse the strings into arrays. 
    $arrUsers = Explode(",", $strUsers); 
    $arrGroups = Explode(",", $strGroups); 
    if (in_array($UserName, $arrUsers)) { 
      $isValid = true; 
    } 
    // Or, you may restrict access to only certain users based on their username. 
    if (in_array($UserGroup, $arrGroups)) { 
      $isValid = true; 
    } 
    if (($strUsers == "") && false) { 
      $isValid = true; 
    } 
  } 
  return $isValid; 
}



$MM_restrictGoTo = "http://localhost/ventas";


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
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error());
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['elnombre'];
$id_usuarios = $row_usuario['id_ingreso'];
$hoy = date("Y-m-d");




/* if(isset($_POST['id_ventas']) && isset($_POST['valor'])) { */
if(isset($_POST['id_ventas'])) {
	$id_ventas = $_POST['id_ventas'];	
	$query_datos = sprintf("SELECT ID, post_date, post_status, post_excerpt FROM ch_posts WHERE ID = '$id_ventas'");
	$datos = mysqli_query($sandycat, $query_datos) or die(mysqli_error());
	$row_datos = mysqli_fetch_assoc($datos);
	$totalRows_datos = mysqli_num_rows($datos);

	$query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM ch_postmeta WHERE post_id  = '$id_ventas'");
	$lista = mysqli_query($sandycat, $query_lista) or die(mysqli_error());
	$row_lista = mysqli_fetch_assoc($lista);
	$totalRows_lista = mysqli_num_rows($lista);
	
	
	$query_productos = sprintf("SELECT I.order_item_id, order_item_name, I.order_id, L.order_id, order_item_type, product_qty, product_net_revenue, coupon_amount, shipping_amount, L.order_item_id FROM ch_woocommerce_order_items I RIGHT JOIN ch_wc_order_product_lookup L ON  I.order_item_id = L.order_item_id WHERE I.order_id = '$id_ventas' AND order_item_type='line_item'");
	$productos = mysqli_query($sandycat, $query_productos) or die(mysqli_error());
	$row_productos = mysqli_fetch_assoc($productos);
	$totalRows_productos = mysqli_num_rows($productos);
	
	$query_numfact = sprintf("SELECT COUNT(id_facturas) AS numero FROM facturas");
	$numfact = mysqli_query($sandycat, $query_numfact) or die(mysqli_error());
	$row_numfact = mysqli_fetch_assoc($numfact);
	$totalRows_numfact = mysqli_num_rows($numfact);
	$numfact = $row_numfact['numero']+1;
	
  	/* $elnuevo = "ventasrrr.php?i=$id_ventas";
    header("Location: $elnuevo"); */
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
    <link href="css/bootstrap-4.4.1.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="css/style.css">
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
					<h2 class="heading-section">Detalle venta Woocomerce</h2>
			  </div>
	  </div>
			<div class="row justify-content-center" style="margin-top: -30px">
			  <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
						<div class="container p-3 my-3 bg-primary text-white">
							<?php					 
							include("postmeta.php");
							$sindesc = $vtotal+$descuento;
							?>
							Pedido: <?php echo $row_datos['ID']; ?><br />
							Nombre: <?php echo strtoupper($nombre1)." ".strtoupper($nombre2); ?><br />
							Documento: <?php echo $documento; ?><br />
							Dirección: <?php echo $dir1." ".$dir2." ".$barrio; ?><br />
							Ciudad: <?php echo $ciudad." (".$departamento.")"; ?><br />
							<?php echo "Teléfono: ".$celular; ?><br />
							<?php echo "Email: ".$correo; ?><br />
							<?php echo "Subtotal: ".number_format($sindesc); ?><br />
							<?php echo "Descuento: ".number_format($descuento); ?><br />
							<?php echo "Total: ".number_format($vtotal); ?><br />
							<?php echo $metodo; ?><br />
							<?php 
							$elenvio = '';
							if($envio > 0) {
								$elenvio = $envio;
							echo "Valor envio: ".number_format($elenvio)."<br />";
							} ?>
							<?php echo "Fecha: ".$row_datos['post_date']; ?><br />
						</div>
							<?php if(!empty($row_datos['post_excerpt'])) { ?>
						<div class="container p-3 my-3 bg-success text-white">
							Observaciones:<br />
							<?php echo $row_datos['post_excerpt']; ?>
						</div>
						  <?php } ?>
						<div class="form-group">
						<form action="adminf.php" class="login-form" method="post" >
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="imprimiendo" name="imprimiendo" value="1" />
							<input type="hidden" id="factura" name="factura" value="si" />
							  <div class="row">
								  <div class="input-group mb-3 text-center">
									  <input type="text" class="form-control" placeholder="Factura No. <?php echo "POS ".$numfact; ?>" aria-label="Recipient's username" aria-describedby="basic-addon2" id="num" name="num" value="<?php echo $numfact; ?>" readonly>
									  <div class="input-group-append">
										<button class="btn btn-outline-primary" type="submit" name="ingfact" id="ingfact">Facturar</button>
									  </div>
									</div>
									</div>
					 		 </form>
							  <div class="row">
								<div class="col text-center">
								<form action="adminf.php" method="post">
								<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
								<input type="hidden" id="num" name="num" value="<?php echo $numfact; ?>" />
								<input type="hidden" id="cancela" name="cancela" value="si" /><button type="submit" class="btn btn-danger rounded submit px-3" name="cancelar" id="cancelar">Cancelar pedido</button>
					  			</form></div>
								<div class="col text-center">
									<a href="adminf.php" class="btn btn-warning rounded submit px-3" role="button">Regresar</a></div>
							</div>
						</div>	
						  <?php 
						do {
					  ?>	
						<div class="container p-3 my-3 border">
						  Producto: <?php echo $row_productos['order_item_name']."<br /> Cantidad: ".$row_productos['product_qty']."<br /> Subtotal: ".number_format($row_productos['coupon_amount']+$row_productos['product_net_revenue'])."<br />Descuento: ".number_format($row_productos['coupon_amount'])."<br /> Total: ".number_format($row_productos['product_net_revenue']); ?><br />
						</div>							
    	 				 <?php } while ($row_productos = mysqli_fetch_assoc($productos)); 
					  ?>
	        </div>
			  </div>
	        </div>
	<?php include("foot.php"); ?>
</section>
  </div>   
</body>
</html>
<?php
mysqli_free_result($usuario);
?>