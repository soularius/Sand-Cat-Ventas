<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
require_once('config.php'); 

if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
// La función isAuthorized() ahora está disponible desde tools.php

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
$colname_usuario = '';
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM usuarios WHERE documento = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = isset($row_usuario['documento']) ? $row_usuario['documento'] : '';
$id_usuarios = isset($row_usuario['id_usuarios']) ? $row_usuario['id_usuarios'] : 0;
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

if(isset($_POST['id_ventas']) && isset($_POST['ingfact'])) {
	$_POST['id_ventas'];
	$_POST['num'];
	$id_ventas = $_POST['id_ventas'];
	$num = $_POST['num'];
	$query = "UPDATE ventas SET factura = '$num' WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
}
if(isset($_POST['id_ventas']) && isset($_POST['cancelar'])) {
	$_POST['id_ventas'];
	$id_ventas = $_POST['id_ventas'];
	$query = "DELETE FROM ventas WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
}


$query_preventa = sprintf("SELECT ventas.id_ventas, ventas.consecutivo, doc_cliente, nom_cliente, fecha, SUM((valor-detalle.descuento)*cantidad) AS eltotal FROM ventas LEFT JOIN detalle ON ventas.id_ventas = detalle.id_ventas WHERE estado = 'a' AND factura = '' GROUP BY ventas.id_ventas ORDER BY id_ventas ASC");
$preventa = mysqli_query($sandycat, $query_preventa) or die(mysqli_error($sandycat));
$row_preventa = mysqli_fetch_assoc($preventa);
$totalRows_preventa = mysqli_num_rows($preventa);

$query_preventaf = sprintf("SELECT ventas.id_ventas, ventas.consecutivo, doc_cliente, factura, nom_cliente, fecha, SUM((valor-detalle.descuento)*cantidad) AS eltotal FROM ventas LEFT JOIN detalle ON ventas.id_ventas = detalle.id_ventas WHERE estado = 'a' AND factura <> '' AND fecha < '$inifact' AND fecha > '$finfact' GROUP BY ventas.id_ventas ORDER BY id_ventas DESC");
$preventaf = mysqli_query($sandycat, $query_preventaf) or die(mysqli_error($sandycat));
$row_preventaf = mysqli_fetch_assoc($preventaf);
$totalRows_preventaf = mysqli_num_rows($preventaf);


if(isset($_POST['iniciando']) && $_POST['iniciando'] = "si") {
	$_POST['doc_cliente'];
	$_POST['nom_cliente'];
	$doc_cliente = $_POST['doc_cliente'];
	$nom_cliente = strtoupper($_POST['nom_cliente']);
	$estado = "i";
	$query = "INSERT INTO ventas (id_usuarios, doc_cliente, nom_cliente, estado, fecha) VALUES ('$id_usuarios', '$doc_cliente', '$nom_cliente', '$estado', '$hoy')";
	mysqli_query($sandycat, $query);

	$id_creado = mysqli_insert_id($sandycat);
  	$elnuevo = "v_producto.php?i=$id_creado";
    header("Location: $elnuevo");
}

?>
<?php include("header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("men.php"); ?><br />
<br />
  <h2>Ventas</h2>
	<ul class="nav nav-tabs">
  <li class="nav-item">
    <a class="nav-link <?php echo $pes1; ?>" data-toggle="tab" href="#pendiente">Pendientes</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $pes2; ?>" data-toggle="tab" href="#terminada">Facturados</a>
  </li>
</ul>
  <!-- <p>The .table-hover class enables a hover state (grey background on mouse over) on table rows:</p>  -->	
<div class="tab-content">
  <div class="tab-pane container <?php echo $acti1; ?>" id="pendiente"><br />
		<?php if(!empty($row_preventa['id_ventas'])) { ?>
	  <input class="form-control" id="busca" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Cliente</th>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Valor</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php do { ?>		
		<form action="detventa.php" class="login-form" method="post">
		<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $row_preventa['id_ventas']; ?>" />
      <tr>
        <td style="text-align: left"><button type="submit" class="btn btn-link"><?php echo $row_preventa['nom_cliente']; ?></button></td>
        <td style="text-align: center"><?php echo $row_preventa['consecutivo']; ?></td>
        <td style="text-align: right"><?php echo number_format($row_preventa['eltotal']); ?></td>
      </tr>	
			</form>
    	<?php } while ($row_preventa = mysqli_fetch_assoc($preventa)); ?>
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
      <a class="dropdown-item" href="admin.php?df=30">30</a>
      <a class="dropdown-item" href="admin.php?df=60">60</a>
      <a class="dropdown-item" href="admin.php?df=90">90</a>
    </div>
		<?php if(!empty($row_preventaf['id_ventas'])) { ?>
	  <input class="form-control" id="buscac" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Cliente</th>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Valor</th>
      </tr>
    </thead>
    <tbody id="dondec">
		<?php do { ?>		
		<form action="detventaf.php" class="login-form" method="post">
		<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $row_preventaf['id_ventas']; ?>" />
      <tr>
        <td style="text-align: left"><button type="submit" class="btn btn-link"><?php echo $row_preventaf['nom_cliente']; ?></button></td>
        <td style="text-align: center"><?php echo $row_preventaf['consecutivo']; ?></td>
        <td style="text-align: right"><?php echo number_format($row_preventaf['eltotal']); ?></td>
      </tr>	
			</form>
    	<?php } while ($row_preventaf = mysqli_fetch_assoc($preventaf)); ?>
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
</script>

</body>
</html>
<?php
mysqli_free_result($usuario);
?>