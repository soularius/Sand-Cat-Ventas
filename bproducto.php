<?php
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
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error());
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['elnombre'];
$acti1 = 'active';
$acti2 = 'fade';
$pes1 = 'active';
$pes2 = '';


if(isset($_POST['_order_id'])) {
  $_order_id = $_POST['_order_id'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<title>Sand&Cat</title>
<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="shortcut icon" href="https://sandycat.com.co/wp-content/uploads/2020/05/favicon.jpg" type="image/x-icon" />
	<link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"> -->
    
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">  
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>

</head>
<body">
<div class="container">
  <?php include("menf.php"); ?>
  <section class=""><br />
  <br />
  <br />
  <br />
			<div class="row justify-content-center">
				<div class="col-md-10 text-center mb-5">
					<h2 class="heading-section">Seleccionar producto</h2>
			  </div>
	    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-addon"><span class="glyphicon glyphicon glyphicon-search" aria-hidden="true"></span>
                </span>
                <input type="text" class="form-control" id="search" name="search" placeholder="mínimo 3 carácteres">
                <input type="hidden" class="form-control" id="_order_id" name="_order_id" value="<?php echo $_order_id; ?>">
            </div>
        </div>
        <div class="col-md-6 col-md-offset-6" id="result"></div>
    </div>

</div>
	<?php include("foot.php"); ?>
  
   <!-- The Modal -->
   <div class="modal fade" id="nuevoprod" role="dialog">
    <div class="modal-dialog">


       <div class="modal-content">
         <!-- Modal Header -->
         <div class="modal-header"> 
           <h2 class="modal-title">  
           <button type="button" class="close" data-dismiss="modal">×</button>
           <!-- <input id="nombre_id" type="text" name="nombre_id" value="" class="btn-block" style="font-size: 13px; color: #444; padding: 10px; text-align: center; width: 100%;" disabled /> --></h2>
         </div>
           <textarea id="order_idb" name="order_idb" value="" style="font-size: 18px; color: #444; padding: 10px; text-align: center; width: 100%; border: none;" disabled>      
            </textarea>
         <!-- Modal body -->
         <div class="modal-body">
            Cantidad
						<form action="pros_venta.php" class="login-form" method="post" target="_self" id="newproduct">
                  <div class="form-group">
                    <input id="proceso" type="hidden" name="proceso" value="s"/><!-- id orden -->
                    <input id="order_id" type="hidden" name="order_id" value=""/><!-- id orden -->
                    <input id="order_idbn" type="hidden" name="order_idbn" value=""/><!-- id orden -->
                    <input id="product_id" type="hidden" name="product_id" value=""/><!-- id producto -->
									  <input  id="product_qty" name="product_qty" type="number" class="form-control" value="1" min="1" required>
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



 


<script src="js/jquery-3.4.1.min.js" type="text/javascript"></script>
<script src="js/popper.min.js" type="text/javascript"></script>
<script src="js/bootstrap-4.4.1.js" type="text/javascript"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min-js"></script>
<script src="js/bproducto.js" type="text/javascript"></script><script type="text/javascript">		  
$('#nuevoprod').on('show.bs.modal', function(e) {
    var nombre_id= $(e.relatedTarget).data('nombre-id');    $(e.currentTarget).find('textarea [name="nombre_id"]').val(nombre_id);
    var product_id= $(e.relatedTarget).data('paquete-id');    $(e.currentTarget).find('input[name="product_id"]').val(product_id);
    var order_id= $(e.relatedTarget).data('order-id');    $(e.currentTarget).find('input[name="order_id"]').val(order_id);
    var order_idbn= $(e.relatedTarget).data('order-idb');    $(e.currentTarget).find('input[name="order_idbn"]').val(order_idbn);
    var order_idb= $(e.relatedTarget).data('order-idb');    $(e.currentTarget).find('textarea[name="order_idb"]').val(order_idb);
});	 
		  
</script>

</body>
</html>