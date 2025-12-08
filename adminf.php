<?php
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
$acti1 = 'active';
$acti2 = 'fade';
$pes1 = 'active';
$pes2 = '';
if(isset($_GET['df']) && !empty($_GET['df'])){
	$diasfin = $_GET['df'];
	$acti1 = 'fade';
	$acti2 = 'active';
	$pes1 = '';
	$pes2 = 'active';
}
$hoy = date("Y-m-d");
$inifact = date("Y-m-d",strtotime ( '+1 day' , strtotime ( $hoy ) ) );
if(isset($diasfin)) {
	$dias = $diasfin;
	$finfact = date("Y-m-d",strtotime ( '-'.$dias.' day' , strtotime ( $hoy ) ) );
} else {
	$dias = 30;	
	$finfact = date("Y-m-d",strtotime ( '-30 day' , strtotime ( $hoy ) ) );
}
$carga = '';
if(isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) {
	$_POST['id_ventas'];
	$_POST['num'];
	$venta = $_POST['id_ventas'];
	$factu = $_POST['num'];
	$query = "INSERT INTO facturas (id_order, factura, estado) VALUES ('$venta', '$factu', 'a')";
	mysqli_query($sandycat, $query);
	$query = "UPDATE miau_posts SET post_status = 'wc-completed' WHERE ID = '$venta'";
	mysqli_query($miau, $query);
	$query = "UPDATE miau_wc_order_stats SET status = 'wc-completed' WHERE id_ventas = '$venta'";
	mysqli_query($miau, $query);
}
if(isset($_POST['id_ventas']) && isset($_POST['cancela'])) {
	$_POST['id_ventas'];
	$_POST['num'];
	$venta = $_POST['id_ventas'];
	$factu = $_POST['num'];
	$query = "UPDATE miau_posts SET post_status = 'wc-cancelled' WHERE ID = '$venta'";
	mysqli_query($miau, $query);
	$query = "UPDATE miau_wc_order_stats SET status = 'wc-cancelled' WHERE order_id = '$venta'";
	mysqli_query($miau, $query);
	$query = "UPDATE facturas SET estado = 'i' WHERE id_order = '$venta'";
	mysqli_query($sandycat, $query);

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
if(isset($_POST['fin_pedido'])) { 
	$_POST['fin_pedido'];
	$venta = $_POST['fin_pedido'];
  
	$query = "UPDATE miau_posts SET post_status = 'wc-processing' WHERE ID = '$venta'";
	mysqli_query($miau, $query);

	$query = "UPDATE miau_wc_order_stats SET status = 'wc-processing' WHERE order_id = '$venta'";
	mysqli_query($miau, $query);
}


/* $query_pendientes = sprintf("SELECT ID, post_date, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_first_name' AND post_id = ID ) AS nombre1, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_last_name' AND post_id = ID ) AS nombre2, (SELECT total_sales FROM miau_wc_order_stats WHERE order_id = ID ) AS valor FROM miau_posts WHERE post_status = 'wc-processing' ORDER BY ID DESC");  codigo hasta dic 23 2023*/
    // codigo para que reconozca las compras de cheque(datafono) status wc-on-hold
    $query_pendientes = sprintf("SELECT miau_posts.ID, post_date, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_first_name' AND post_id = miau_posts.ID ) AS nombre1, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_last_name' AND post_id = miau_posts.ID ) AS nombre2, (SELECT total_sales FROM miau_wc_order_stats WHERE order_id = miau_posts.ID ) AS valor FROM miau_posts WHERE post_status = 'wc-processing' OR miau_posts.ID IN (SELECT miau_posts.ID FROM miau_posts JOIN miau_postmeta ON miau_posts.ID = miau_postmeta.post_id WHERE miau_posts.post_status = 'wc-on-hold' AND miau_postmeta.meta_value = 'cheque') ORDER BY ID DESC;");
$pendientes = mysqli_query($miau, $query_pendientes) or die(mysqli_error($miau));
$row_pendientes = mysqli_fetch_assoc($pendientes);
$totalRows_pendientes = mysqli_num_rows($pendientes);

// Primero obtenemos los IDs de órdenes facturadas del sistema local
$query_facturas = sprintf("SELECT id_order FROM facturas WHERE estado = 'a'");
$facturas_result = mysqli_query($sandycat, $query_facturas) or die(mysqli_error($sandycat));
$facturas_ids = array();
while($row_fact = mysqli_fetch_assoc($facturas_result)) {
    $facturas_ids[] = $row_fact['id_order'];
}

// Si hay facturas, consultamos WordPress, sino creamos resultado vacío
if(!empty($facturas_ids)) {
    $ids_string = implode(',', $facturas_ids);
    $query_pendientesf = sprintf("SELECT ID, post_date, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_first_name' AND post_id = ID ) AS nombre1, (SELECT meta_value FROM miau_postmeta WHERE meta_key='_billing_last_name' AND post_id = ID ) AS nombre2, (SELECT total_sales FROM miau_wc_order_stats WHERE order_id = ID ) AS valor FROM miau_posts WHERE ID IN ($ids_string) AND post_date < '$inifact' AND post_date > '$finfact' ORDER BY ID DESC");
    $pendientesf = mysqli_query($miau, $query_pendientesf) or die(mysqli_error($miau));
} else {
    // Crear resultado vacío si no hay facturas
    $pendientesf = mysqli_query($miau, "SELECT ID, post_date, '' AS nombre1, '' AS nombre2, 0 AS valor FROM miau_posts WHERE 1=0");
}
$row_pendientesf = mysqli_fetch_assoc($pendientesf);
$totalRows_pendientesf = mysqli_num_rows($pendientesf);
?>
<?php include("header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("menf.php"); ?><br />
<br />
  <h2>Ventas Woocommerce</h2>
	<ul class="nav nav-tabs">
  <li class="nav-item">
    <a class="nav-link <?php echo $pes1; ?>" data-toggle="tab" href="#pendiente">Pendientes</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $pes2; ?>" data-toggle="tab" href="#terminada">Facturados</a>
  </li>
</ul>
  <!-- <p>The .table-hover class enables a hover state (grey background on mouse over) on table rows:</p>  -->	
	
		<?php if(isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) { ?>
		<form action="fact.php" class="login-form" method="post" target="_blank" id="impr" name="impr">
			<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $venta; ?>" />
			<input type="hidden" id="factura" name="factura" value="si" />
			<div class="row">
				<div class="input-group mb-3 text-center">
					<input type="hidden" class="form-control" id="num" name="num" value="<?php echo $fact; ?>" readonly>
					<div class="input-group-append">
						<button class="btn btn-outline-primary" type="submit" name="ingfact" id="ingfact" style="visibility: hidden"></button>
									  </div>
									</div>
									</div>
					 		 </form>
	  <?php } ?>
<div class="tab-content">
  <div class="tab-pane container <?php echo $acti1; ?>" id="pendiente"><br />
		<?php if(!empty($row_pendientes['ID'])) { ?>
	  <input class="form-control" id="busca" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Fecha</th>
        <th>Cliente</th>
        <th style="text-align: center">Valor</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php do { ?>		
		<form action="detventafact.php" class="login-form" method="post">
		<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $row_pendientes['ID']; ?>" />
      <tr>
        <td style="text-align: center"><?php echo $row_pendientes['ID']; ?></td>
        <td style="text-align: center"><?php echo $row_pendientes['post_date']; ?></td>
        <td style="text-align: left"><button type="submit" class="btn btn-link"><?php echo strtoupper($row_pendientes['nombre1']." ".$row_pendientes['nombre2']); ?></button></td>
        <td style="text-align: right"><?php echo number_format($row_pendientes['valor']); ?></td>
      </tr>	
			</form>
    	<?php } while ($row_pendientes = mysqli_fetch_assoc($pendientes)); ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra pedidos pendientes para facturar.</h3>
	  <?php } ?>
	</div>
  <div class="tab-pane container <?php echo $acti2; ?>" id="terminada">
	<br />
		
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">Ultimos <?php echo $dias; ?> dias </a>
    <div class="dropdown-menu">
      <a class="dropdown-item" href="adminf.php?df=30">30</a>
      <a class="dropdown-item" href="adminf.php?df=60">60</a>
      <a class="dropdown-item" href="adminf.php?df=90">90</a>
    </div>
		<?php if(!empty($row_pendientesf['ID'])) { ?>
	  <input class="form-control" id="buscac" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Fecha</th>
        <th>Cliente</th>
        <th style="text-align: center">Valor</th>
      </tr>
    </thead>
    <tbody id="dondec">
		<?php do { ?>		
		<form action="detventafinal.php" method="post">
		<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $row_pendientesf['ID']; ?>" />
      <tr>
        <td style="text-align: center"><?php echo $row_pendientesf['ID']; ?></td>
        <td style="text-align: center"><?php echo $row_pendientesf['post_date']; ?></td>
        <td style="text-align: left"><button type="submit" class="btn btn-link"><?php echo strtoupper($row_pendientesf['nombre1']." ".$row_pendientesf['nombre2']); ?></button></td>
        <td style="text-align: right"><?php echo number_format($row_pendientesf['valor']); ?></td>
      </tr>	
			</form>
    	<?php } while ($row_pendientesf = mysqli_fetch_assoc($pendientesf)); ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra pedidos facturados en el periodo seleccionado.</h3>
	  <?php } ?>	  
	</div>
  <div class="tab-pane container fade" id="menu2">...</div>
</div>
</div>
	<?php include("foot.php"); ?>
    
<script src="js/jquery-3.4.1.min.js" type="text/javascript"></script>
<script src="js/popper.min.js" type="text/javascript"></script>
<script src="js/bootstrap-4.4.1.js" type="text/javascript"></script>
<script>
$(document).ready(function(){
  $("#busca").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#donde tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
$(document).ready(function(){
  $("#buscac").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dondec tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
	window.onload=function(){
                // Una vez cargada la página, el formulario se enviara automáticamente.
		document.forms["impr"].submit();
    }
</script>

</body>
</html>
<?php
?>