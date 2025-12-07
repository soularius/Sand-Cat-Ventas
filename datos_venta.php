<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
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



if(isset($_POST['billing_id'])) {
	$billing_id = $_POST['billing_id'];	
    
	$query_idlast = sprintf("SELECT post_id, meta_key, meta_value FROM ch_postmeta WHERE meta_value = '$billing_id' AND meta_key = 'billing_id ' ORDER BY post_id DESC LIMIT 1");
	$idlast = mysqli_query($sandycat, $query_idlast) or die(mysqli_error());
	$row_idlast = mysqli_fetch_assoc($idlast);
	$totalRows_idlast = mysqli_num_rows($idlast);
  $post_id = $row_idlast['post_id'];
    
	$query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM ch_postmeta WHERE post_id = '$post_id'");
	$lista = mysqli_query($sandycat, $query_lista) or die(mysqli_error());
	$row_lista = mysqli_fetch_assoc($lista);
	$totalRows_lista = mysqli_num_rows($lista);


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
				<div class="col-md-10 text-center mb-5">
					<h2 class="heading-section">Datos del cliente</h2>
			  </div>
	    </div>
			<div class="row justify-content-center" style="margin-top: -30px">
        <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
            <?php						 
					    include("postmeta.php");
              $documento = $billing_id ;
              ?>
            <form action="pros_venta.php" method="post" id="d_usuario">
              <div class="form-group">
                <label for="nombre">Nombre:</label>
                <input type="text" class="form-control" id="nombre1" name="nombre1" value="<?php echo strtoupper($nombre1); ?>" required>
              </div>
              <div class="form-group">
                <label for="apellido">Apellido:</label>
                <input type="text" class="form-control" id="nombre2" name="nombre2" value="<?php echo strtoupper($nombre2); ?>" required>
              </div>
              <div class="form-group">
                <label for="billing_id">Documento:</label>
                <input type="number" class="form-control" id="billing_id" name="billing_id" value="<?php echo strtoupper($documento); ?>" required>
              </div>
              <div class="form-group">
                <label for="_billing_email">Email:</label>
                <input type="email" class="form-control" id="_billing_email" name="_billing_email" value="<?php echo strtoupper($correo); ?>" required>
              </div>
              <div class="form-group">
                <label for="_billing_phone'">Celular:</label>
                <input type="text" class="form-control" id="_billing_phone" name="_billing_phone" value="<?php echo strtoupper($celular); ?>" required>
              </div>
              <div class="form-group">
                <label for="_shipping_address_1">Dirección:</label>
                <input type="text" class="form-control" id="_shipping_address_1" name="_shipping_address_1" value="<?php echo strtoupper($dir1); ?>" required>
              </div>
              <div class="form-group">
                <label for="_shipping_address_2">Complemento dirección:</label>
                <input type="text" class="form-control" id="_shipping_address_2" name="_shipping_address_2" value="<?php echo strtoupper($dir2); ?>">
              </div>
              <div class="form-group">
                <label for="_billing_neighborhood">Barrio:</label>
                <input type="text" class="form-control" id="_billing_neighborhood" name="_billing_neighborhood" value="<?php echo strtoupper($barrio); ?>" required>
              </div>
              <div class="form-group">
                <label for="_billing_city">Ciudad:</label>
                <input type="text" class="form-control" id="_shipping_city" name="_shipping_city" value="<?php echo strtoupper($ciudad); ?>" required>
              </div>
              <div class="form-group">
                <label for="_shipping_state">Departamento:</label>
                <input type="text" class="form-control" id="_shipping_state" name="_shipping_state" value="<?php echo strtoupper($departamento); ?>" required>
              </div>
              <div class="form-group">
                <label for="_order_shipping">Envio:</label>
                <input type="number" class="form-control" id="_order_shipping" name="_order_shipping" value="10000" required>
              </div>
              <div class="form-group">
                <!-- <label for="_order_shipping">Descuento:</label> -->                
                <div class="form-group">
                  <label for="sel1">Forma de pago:</label>
                  <select class="form-control" id="_payment_method_title" name="_payment_method_title">
                    <option value="Pago Contra Entrega Aplica solo para Bogotá" selected>Pago Contra Entrega Aplica solo para Bogotá</option>
                    <option value="Paga con PSE y tarjetas de crédito">Paga con PSE y tarjetas de crédito</option>
                  </select>
                </div>
                <input type="hidden" class="form-control" id="_cart_discount" name="_cart_discount" value="0" required>
              </div>
              <div class="form-group">
                <label for="post_expcerpt">Observaciones:</label>
                <input type="text" class="form-control" id="post_expcerpt" name="post_expcerpt" value="">
              </div>

              
							<div class="row">
								<div class="col text-center">
                  <button type="submit" class="btn btn-primary btn-block">Continuar</button>
								  </div>
              </div> 
            </form>
            <div class="row">
								<div class="col text-center"></br>
                <a href="adminventas.php" class="btn btn-danger btn-block" role="button">Cancelar</a>
								  </div>
								  </div>

              
                        
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