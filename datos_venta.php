<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// Cargar configuración desde archivo .env
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
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? '';
$hoy = date("Y-m-d");

// Inicializar variables por defecto
$billing_id = '';
$post_id = '';
$row_lista = null;
$lista = null;
$totalRows_lista = 0;

if(isset($_POST['billing_id'])) {
	$billing_id = $_POST['billing_id'];	
    
	$query_idlast = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE meta_value = '$billing_id' AND meta_key = 'billing_id ' ORDER BY post_id DESC LIMIT 1");
	$idlast = mysqli_query($miau, $query_idlast) or die(mysqli_error($miau));
	$row_idlast = mysqli_fetch_assoc($idlast);
	$totalRows_idlast = mysqli_num_rows($idlast);
  $post_id = $row_idlast['post_id'] ?? '';
    
    // Solo ejecutar la segunda consulta si se encontró un post_id válido
    if (!empty($post_id)) {
        $query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$post_id'");
        $lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
        $row_lista = mysqli_fetch_assoc($lista);
        $totalRows_lista = mysqli_num_rows($lista);
    } else {
        // Inicializar variables si no se encontró post_id
        $lista = null;
        $row_lista = null;
        $totalRows_lista = 0;
    }


}


?>
<?php include("header.php"); ?>
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
              $documento = !empty($billing_id) ? $billing_id : $documento;
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