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
<link rel="stylesheet" href="css/wizard-form.css">

<body>
<div class="container">
<?php include("menf.php"); ?>
<div class="wizard-container"></div>
<section class="py-5">
    <div class="container">
        <!-- Progress Steps -->
        <div class="step-wizard mb-5">
            <div class="step active">
                <i class="fas fa-user"></i> Datos del Cliente
            </div>
            <div class="step">
                <i class="fas fa-shopping-cart"></i> Productos
            </div>
            <div class="step">
                <i class="fas fa-credit-card"></i> Pago
            </div>
            <div class="step">
                <i class="fas fa-check-circle"></i> Confirmación
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">Crear Nueva Orden</h2>
                <p class="text-muted">Complete los datos del cliente para continuar</p>
            </div>
        </div>

        <?php						 
            include("postmeta.php");
            $documento = !empty($billing_id) ? $billing_id : $documento;
        ?>

        <form action="pros_venta.php" method="post" id="d_usuario">
            <div class="row">
                <!-- Columna Izquierda -->
                <div class="col-lg-6">
                    <!-- Información Personal -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-circle"></i> Información Personal</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nombre1" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre1" name="nombre1" 
                                           value="<?php echo strtoupper($nombre1); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nombre2" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="nombre2" name="nombre2" 
                                           value="<?php echo strtoupper($nombre2); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="billing_id" class="form-label">Documento de Identidad *</label>
                            <input type="number" class="form-control" id="billing_id" name="billing_id" 
                                   value="<?php echo strtoupper($documento); ?>" required>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="form-section">
                        <h5><i class="fas fa-phone"></i> Información de Contacto</h5>
                        <div class="form-group mb-3">
                            <label for="_billing_email" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="_billing_email" name="_billing_email" 
                                   value="<?php echo strtoupper($correo); ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="_billing_phone" class="form-label">Número de Celular *</label>
                            <input type="text" class="form-control" id="_billing_phone" name="_billing_phone" 
                                   value="<?php echo strtoupper($celular); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="col-lg-6">
                    <!-- Dirección de Envío -->
                    <div class="form-section">
                        <h5><i class="fas fa-map-marker-alt"></i> Dirección de Envío</h5>
                        <div class="form-group mb-3">
                            <label for="_shipping_address_1" class="form-label">Dirección Principal *</label>
                            <input type="text" class="form-control" id="_shipping_address_1" name="_shipping_address_1" 
                                   value="<?php echo strtoupper($dir1); ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="_shipping_address_2" class="form-label">Complemento de Dirección</label>
                            <input type="text" class="form-control" id="_shipping_address_2" name="_shipping_address_2" 
                                   value="<?php echo strtoupper($dir2); ?>" placeholder="Apartamento, suite, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_billing_neighborhood" class="form-label">Barrio *</label>
                                    <input type="text" class="form-control" id="_billing_neighborhood" name="_billing_neighborhood" 
                                           value="<?php echo strtoupper($barrio); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_shipping_city" class="form-label">Ciudad *</label>
                                    <input type="text" class="form-control" id="_shipping_city" name="_shipping_city" 
                                           value="<?php echo strtoupper($ciudad); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="_shipping_state" class="form-label">Departamento *</label>
                            <input type="text" class="form-control" id="_shipping_state" name="_shipping_state" 
                                   value="<?php echo strtoupper($departamento); ?>" required>
                        </div>
                    </div>

                    <!-- Información de Pago y Envío -->
                    <div class="form-section">
                        <h5><i class="fas fa-credit-card"></i> Pago y Envío</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_order_shipping" class="form-label">Costo de Envío *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="_order_shipping" name="_order_shipping" 
                                               value="10000" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_payment_method_title" class="form-label">Forma de Pago *</label>
                                    <select class="form-control" id="_payment_method_title" name="_payment_method_title">
                                        <option value="Pago Contra Entrega Aplica solo para Bogotá" selected>
                                            Pago Contra Entrega (Solo Bogotá)
                                        </option>
                                        <option value="Paga con PSE y tarjetas de crédito">
                                            PSE y Tarjetas de Crédito
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="post_expcerpt" class="form-label">Observaciones Adicionales</label>
                            <textarea class="form-control" id="post_expcerpt" name="post_expcerpt" rows="3" 
                                      placeholder="Instrucciones especiales de entrega, comentarios, etc."></textarea>
                        </div>
                        <input type="hidden" id="_cart_discount" name="_cart_discount" value="0">
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="adminventas.php" class="btn btn-danger btn-custom">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary btn-custom">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
<?php include("foot.php"); ?>
</div>
</body>
</html>
<?php
mysqli_free_result($usuario);
?>