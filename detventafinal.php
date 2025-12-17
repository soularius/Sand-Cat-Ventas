<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// 1. Cargar autoloader del sistema (incluye conexiones de BD)
require_once('class/autoload.php');

// 2. Incluir el manejador de login común
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Requerir autenticación - redirige a index.php si no está logueado
requireLogin("index.php");

// Obtener datos del usuario actual usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    // Si no se pueden obtener los datos del usuario, redirigir al login
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;
$hoy = date("Y-m-d");




/* if(isset($_POST['id_ventas']) && isset($_POST['valor'])) { */
if(isset($_POST['id_ventas'])) {
	$id_ventas = $_POST['id_ventas'];	
	$query_datos = sprintf("SELECT ID, post_date, post_status, post_excerpt FROM miau_posts WHERE ID = '$id_ventas'");
	$datos = mysqli_query($miau, $query_datos) or die(mysqli_error($miau));
	$row_datos = mysqli_fetch_assoc($datos);
	$totalRows_datos = mysqli_num_rows($datos);

	$query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id  = '$id_ventas'");
	$lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
	$row_lista = mysqli_fetch_assoc($lista);
	$totalRows_lista = mysqli_num_rows($lista);
	
	
	$query_productos = sprintf("SELECT I.order_item_id, order_item_name, I.order_id, L.order_id, order_item_type, product_qty, product_net_revenue, coupon_amount, shipping_amount, L.order_item_id FROM miau_woocommerce_order_items I RIGHT JOIN miau_wc_order_product_lookup L ON  I.order_item_id = L.order_item_id WHERE I.order_id = '$id_ventas' AND order_item_type='line_item'");
	$productos = mysqli_query($miau, $query_productos) or die(mysqli_error($miau));
	$row_productos = mysqli_fetch_assoc($productos);
	$totalRows_productos = mysqli_num_rows($productos);
	
	$query_numfact = sprintf("SELECT factura FROM facturas WHERE id_order = '$id_ventas'");
	$numfact = mysqli_query($sandycat, $query_numfact) or die(mysqli_error($sandycat));
	$row_numfact = mysqli_fetch_assoc($numfact);
	$totalRows_numfact = mysqli_num_rows($numfact);
	$numfact = $row_numfact['factura'];
	
  	/* $elnuevo = "ventasrrr.php?i=$id_ventas";
    header("Location: $elnuevo"); */
}


?>
<?php include("parts/header.php"); ?>
<body>
<div class="container">
<?php include("parts/menf.php"); ?>
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
					$sindesc = $vtotal+$descuento;?>
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
						<form action="fact.php" class="login-form" method="post" target="_blank">
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="imprimiendo" name="imprimiendo" value="1" />
							<input type="hidden" id="factura" name="factura" value="si" />
							<input type="hidden" id="num" name="num" value="<?php echo $numfact; ?>" />
							  <div class="row">
								  <div class="input-group mb-3 text-center">
									  <input type="text" class="form-control" placeholder="Factura No. <?php echo "POS ".$numfact; ?>" id="numfinal" name="numfinal" value="" readonly>
									  <div class="input-group-append">
										<button class="btn btn-outline-primary" type="submit" name="ingfact" id="ingfact">Imprimir</button>
									  </div>
									</div>
									</div>
					 		 </form>
							  <div class="row">
								<div class="col text-center">
								<form action="pedidos.php" method="post">
								<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
								<input type="hidden" id="num" name="num" value="<?php echo $numfact; ?>" />
								<input type="hidden" id="cancela" name="cancela" value="si" /><button type="submit" class="btn btn-danger rounded submit px-3" name="cancelar" id="cancelar">Cancelar pedido</button>
					  			</form>
								  </div>
								<div class="col text-center">
									<a href="pedidos.php" class="btn btn-warning rounded submit px-3" role="button">Regresar</a></div>
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
	<?php include("parts/foot.php"); ?>
</section>
  </div>  
</body>
</html>
<?php
// Resource cleanup handled automatically by getCurrentUserFromDB()
?>