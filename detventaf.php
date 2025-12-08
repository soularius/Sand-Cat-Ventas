<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
require_once('conectar.php'); 

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
$hoy = date("Y-m-d");

/*if(isset($_POST['id_ventas']) && isset($_POST['valor'])) { */
if(isset($_POST['id_ventas'])) {
	$id_ventas = $_POST['id_ventas'];	
	$query_preventa = sprintf("SELECT * FROM ventas WHERE id_ventas = '$id_ventas'");
	$preventa = mysqli_query($sandycat, $query_preventa) or die(mysqli_error($sandycat));
	$row_preventa = mysqli_fetch_assoc($preventa);
	$totalRows_preventa = mysqli_num_rows($preventa);
	
	$query_artpreventa = sprintf("SELECT detalle.id_detalle, detalle.id_articulos, articulos.nombre, id_ventas, detalle.valor, cantidad, detalle.descuento FROM detalle LEFT JOIN articulos ON detalle.id_articulos = articulos.id_articulos WHERE id_ventas = '$id_ventas' ORDER BY detalle.id_detalle ASC");
	$artpreventa = mysqli_query($sandycat, $query_artpreventa) or die(mysqli_error($sandycat));
	$row_artpreventa = mysqli_fetch_assoc($artpreventa);
	$totalRows_artpreventa = mysqli_num_rows($artpreventa);
	
	$query_totalpreventa = sprintf("SELECT SUM((valor-descuento)*cantidad) AS eltotal FROM detalle WHERE id_ventas= '$id_ventas'");
	$totalpreventa = mysqli_query($sandycat, $query_totalpreventa) or die(mysqli_error($sandycat));
	$row_totalpreventa = mysqli_fetch_assoc($totalpreventa);
	$totalRows_totalpreventa = mysqli_num_rows($totalpreventa);
  	/* $elnuevo = "ventasrrr.php?i=$id_ventas";
    header("Location: $elnuevo"); */
}


?>
<?php include("parts/header.php"); ?>
<div class="container">
<?php include("parts/men.php"); ?>
<section class=""><br />
<br />
<br />
<br />
			<div class="row justify-content-center">
				<div class="col-md-6 text-center mb-5">
					<h2 class="heading-section">Detalle venta</h2>
			  </div>
	  </div>
			<div class="row justify-content-center" style="margin-top: -30px">
			  <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
						<div class="container p-3 my-3 bg-primary text-white">
						   <?php echo $row_preventa['nom_cliente']; ?><br />
							<?php echo $row_preventa['doc_cliente']; ?><br />
							<?php echo "Total: ".number_format($row_totalpreventa['eltotal']); ?><br />
							<?php echo $row_preventa['consecutivo']; ?>
						</div>
						<div class="form-group">
						<form action="admin.php" class="login-form" method="post">
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="factura" name="factura" value="si" />
							  <div class="row">
								  <div class="input-group mb-3 text-center">
									  <input type="text" class="form-control" value="Factura No. <?php echo $row_preventa['factura']; ?>" aria-label="Recipient's username" aria-describedby="basic-addon2" id="num" name="num" disabled>
									</div>
									</div>
					 		 </form>
							  <div class="row">
								<div class="col text-center">
								<form action="admin.php?df=30" class="login-form" method="post">
								<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
								<input type="hidden" id="cancela" name="cancela" value="si" /><button type="submit" class="btn btn-danger rounded submit px-3" name="cancelar" id="cancelar">Cancelar pedido</button>
					  			</form></div>
								<div class="col text-center">
									<a href="admin.php?df=30" class="btn btn-warning rounded submit px-3" role="button">Regresar</a></div>
							</div>
						</div>
							<?php if(!empty($row_preventa['observacion'])) { ?>
						<div class="container p-3 my-3 bg-success text-white">
							<?php echo $row_preventa['observacion']; ?>
						</div>
						  <?php } ?>	
						  <?php 
						do {
					  ?>	
						<div class="container p-3 my-3 border">
						  <?php echo $row_artpreventa['nombre']; ?><br />
							Valor: <?php echo number_format($row_artpreventa['valor']); ?><br />
							Descuento: <?php echo $row_artpreventa['descuento']; ?><br />
							Cantidad: <?php echo $row_artpreventa['cantidad']; ?><br />
							Valor: <?php echo number_format(($row_artpreventa['valor']-$row_artpreventa['descuento'])*$row_artpreventa['cantidad']); ?>
						</div>							
    	 				 <?php } while ($row_artpreventa = mysqli_fetch_assoc($artpreventa)); 
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
mysqli_free_result($usuario);
?>