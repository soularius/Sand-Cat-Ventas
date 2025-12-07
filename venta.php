<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
require_once('conectar.php'); 

if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "v";
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

$query_usuario = sprintf("SELECT * FROM usuarios WHERE documento = '$colname_usuario' AND rol = 'v'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error());
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['documento'];
$id_usuarios = $row_usuario['id_usuarios'];
$hoy = date("Y-m-d");

$query_preventa = sprintf("SELECT ventas.id_ventas, consecutivo, doc_cliente, nom_cliente, fecha, SUM((valor-detalle.descuento)*cantidad) AS eltotal FROM ventas LEFT JOIN detalle ON ventas.id_ventas = detalle.id_ventas WHERE id_usuarios = '$id_usuarios' AND estado = 'a' GROUP BY ventas.id_ventas ORDER BY id_ventas DESC LIMIT 3");
$preventa = mysqli_query($sandycat, $query_preventa) or die(mysqli_error());
$row_preventa = mysqli_fetch_assoc($preventa);
$totalRows_preventa = mysqli_num_rows($preventa);


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
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
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
		      	<h3 class="text-center mb-4">Cliente</h3>
						<form action="venta.php" class="login-form" method="post">
		      		<div class="form-group">
		      			<input type="text" id="nom_cliente" name="nom_cliente" class="form-control rounded-left" placeholder="Nombre Cliente" required>
		      		</div>
	            <div class="form-group d-flex">
		      			<input type="number" id="doc_cliente" name="doc_cliente" class="form-control rounded-left" placeholder="Documento Cliente" required>
	            </div>
	            <div class="form-group"><input type="hidden" id="iniciando" name="iniciando" value="si" />
	            	<button type="submit" class="form-control btn btn-primary rounded submit px-3">Continuar</button>
	            </div><br /></form>
							<?php if($row_preventa['eltotal']>0) { ?>							
					<p class="text-center" style="font-size: 1rem">ULTIMOS REGISTROS</p>
						  <?php 
							do {
							    $id_ventas = $row_preventa['id_ventas'];
							?>	
					<div class="container p-3 my-3 bg-secondary text-white border border-info">
						  <?php echo $row_preventa['nom_cliente']; ?><br />
							<?php echo $row_preventa['doc_cliente']; ?><br />
							<?php echo "Total: ".number_format($row_preventa['eltotal']); ?><br />
							<?php echo $row_preventa['consecutivo']; ?><br />
							<?php echo $row_preventa['fecha']; ?>
							<form action="v_producto.php" id="yano" method="post">
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="cancelando" name="cancelando" value=si />
							<input type="hidden" id="final" name="final" value="si" />
							<div class="col text-center">
							    <button type="submit" class="btn btn-danger rounded submit px-3" form="yano" name="cancela" id="cancela">Cancelar</button></div>
				<!--	  </form>  -->
							
						</div>						
    	 				 <?php } while ($row_preventa = mysqli_fetch_assoc($preventa)); 
								}?>
	          </form>
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