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

/* if (!function_exists("GetSQLValueString")) {
function GetSQLValueString($theValue, $theType, $theDefinedValue = "", $theNotDefinedValue = "") 
{
  if (PHP_VERSION < 8) {
    $theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;
	  global $sandycat;
  }
  $theValue = function_exists("mysqli_real_escape_string") ? mysqli_real_escape_string($sandycat,$theValue) : mysqli_real_escape_string($sandycat,$theValue);
  switch ($theType) {
    case "text":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;    
    case "long":
    case "int":
      $theValue = ($theValue != "") ? intval($theValue) : "NULL";
      break;
    case "double":
      $theValue = ($theValue != "") ? doubleval($theValue) : "NULL";
      break;
    case "date":
      $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
      break;
    case "defined":
      $theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
      break;
  }
  return $theValue;
}
}
*/
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

if(isset($_POST['id_ventas']) && isset($_POST['nocrea'])) {
	$_POST['id_ventas'];
	$id_ventas = $_POST['id_ventas'];
	$query = "DELETE FROM ventas WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
  	$elnuevo = "venta.php";
    header("Location: $elnuevo");
}

if(isset($_POST['id_ventas']) && isset($_POST['id_articulos']) && isset($_POST['continuar'])) {
	$_POST['id_ventas'];
	$_POST['id_articulos'];
	$_POST['cantidad'];
	$id_ventas = $_POST['id_ventas'];
	$id_articulos = $_POST['id_articulos'];
	$cantidad = $_POST['cantidad'];
	
	$query_articulos = sprintf("SELECT * FROM articulos WHERE id_articulos = '$id_articulos'");
	$articulos = mysqli_query($sandycat, $query_articulos) or die(mysqli_error($sandycat));
	$row_articulos = mysqli_fetch_assoc($articulos);
	$totalRows_articulos = mysqli_num_rows($articulos);

	if(isset($_GET['i']) && !empty($_GET['i'])){
		$id_ventas = $_GET['i'];
}
}

?>
<?php include("parts/header.php"); ?>
<body>
<div class="container">
  <div class="row">
    <div class="col-sm-9"></div>
    <div class="col-sm-3 text-capitalize" style=""><?php echo $row_usuario['nombre']." ".$row_usuario['apellido'];?> - <a href="index.php?logout=login3Et" title="Cerrar sesión" target="_self">Salir</a></div>
</div>
<div class="row">
  <div class="col-sm-12"><img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" class="img-fluid" alt="SAND&CAT" /></div>
  </div>
<section class="">
			<div class="row justify-content-center">
				<div class="col-md-6 text-center mb-5">
					<h2 class="heading-section">Registrar venta</h2>
			  </div>
	  </div>
			<div class="row justify-content-center" style="margin-top: -30px">
			  <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
		      	<h3 class="text-center mb-4">Articulo</h3>
						<form action="v_producto.php" class="login-form" method="post">
		      		<div class="form-group">
		      			<input type="text" id="nombre" name="nombre" class="form-control rounded-left" value="<?php echo $row_articulos['nombre']; ?>" readonly required>
		      		</div>
		      		<div class="form-group">
						<label for="valornum">Valor Uni.</label>
		      			<input type="text" id="valornum" name="valornum" class="form-control rounded-left" value="<?php echo number_format($row_articulos['valor']); ?>" readonly required>
		      		</div>
		      		<div class="form-group">
						<label for="descuento">Descuento</label>
		      			<input type="text" id="descuento" name="descuento" class="form-control rounded-left" value="<?php echo $row_articulos['descuento']; ?>" readonly required>
		      		</div>
		      		<div class="form-group">
						<label for="cantidad">Cantidad</label>
		      			<input type="text" id="cantidad" name="cantidad" class="form-control rounded-left" value="<?php echo $cantidad; ?>" readonly required>
		      		</div>
		      		<div class="form-group">
						<label for="valort">Total</label>
		      			<input type="text" id="valort" name="valort" class="form-control rounded-left" value="<?php echo number_format(($cantidad*$row_articulos['valor'])-$row_articulos['descuento']); ?>" readonly required>
		      		</div>
	            <div class="form-group row">
					<input type="hidden" id="ingventa" name="ingventa" value="1" />
					<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
					<input type="hidden" id="valor" name="valor" value="<?php echo $row_articulos['valor']; ?>" />
					<input type="hidden" id="id_articulos" name="id_articulos" value="<?php echo $id_articulos; ?>" />
	            	<div class="col text-center"><button type="submit" class="btn btn-primary rounded submit px-3" name="continuar" id="continuar">Continuar</button></div>
								<div class="col text-center"><button type="submit" class="btn btn-danger rounded submit px-3" name="nocrea" id="nocrea">Regresar</button></div>
	            </div>
	            <div class="form-group d-md-flex">
	            </div>
	          </form>
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