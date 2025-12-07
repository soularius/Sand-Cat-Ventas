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
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM usuarios WHERE documento = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error());
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

if(isset($_POST['id_articulos']) && isset($_POST['m_producto'])) {
	$_POST['id_articulos'];
	$_POST['codigo'];
	$_POST['nombre'];
	$_POST['valor'];
	$_POST['descuento'];
	$id_articulos = $_POST['id_articulos'];
	$codigo = $_POST['codigo'];
	$nombre = $_POST['nombre'];
	$valor = $_POST['valor'];
	$descuento = $_POST['descuento'];
	$query = "UPDATE articulos SET nombre = '$nombre', codigo ='$codigo', valor='$valor', descuento='$descuento' WHERE id_articulos = '$id_articulos'";
	mysqli_query($sandycat, $query);
}

if(isset($_POST['id_articulos']) && isset($_POST['m_estado'])) {
	$estadoac = "";
	$_POST['id_articulos'];
	$_POST['estado'];
	$id_articulos = $_POST['id_articulos'];
	$estadoac = $_POST['estado'];
	if($estadoac == "a") {
		$estadoc = "i";
	} else {
		$estadoc = "a";		
	}
	$query = "UPDATE articulos SET estado = '$estadoc' WHERE id_articulos = '$id_articulos'";
	mysqli_query($sandycat, $query);
}


if(isset($_POST['n_productos']) && isset($_POST['n_productos'])) {
	$_POST['nombre'];
	$_POST['codigo'];
	$_POST['medida'];
	$_POST['valor'];
	$_POST['descuento'];
	$_POST['iva'];
	$codigo = $_POST['codigo'];
	$nombre = $_POST['nombre'];
	$valor = $_POST['valor'];
	$descuento = $_POST['descuento'];
	$medida = "";
	$query = "INSERT INTO articulos (nombre, codigo, medida, valor, estado, descuento, iva) VALUES ('$nombre', '$codigo', '$medida', '$valor', 'a', '$descuento', '0')";
	mysqli_query($sandycat, $query);
}

$query_articulos = sprintf("SELECT * FROM articulos");
$articulos = mysqli_query($sandycat, $query_articulos) or die(mysqli_error());
$row_articulos = mysqli_fetch_assoc($articulos);
$totalRows_articulos = mysqli_num_rows($articulos);

$ellogin = '';
$ellogin = $row_usuario['documento'];
$id_usuarios = $row_usuario['id_usuarios'];


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
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body style="padding-top: 70px">
<div class="container">
<?php include("men.php"); ?><br />
<br />
  <h2>Productos <a class="btn btn-default" href="#" title="Agregar" data-toggle="modal" data-target="#creapro"><i class="fa fa-plus-circle fa-lg"></i></a></h2>
<div class="tab-content">
  <br />
		<?php if(!empty($row_articulos['id_articulos'])) { ?>
	  <input class="form-control" id="busca" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Nombre</th>
        <th style="text-align: center">Código</th>
        <th style="text-align: right">Valor</th>
        <th style="text-align: center">Descuento</th>
        <th style="text-align: center">Estado</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php do { 		
			if($row_articulos['estado']=="a") {
				$estado = "Activo";
			} else {
				$estado = "Inactivo";
			}
		?>	
      <tr>
        <td style="text-align: left"><button type="submit" class="btn btn-link">
			<a href="#" data-toggle="modal" data-target="#editarprod" data-id_articulos="<?php echo $row_articulos['id_articulos']; ?>" data-nombre="<?php echo $row_articulos['nombre']; ?>" data-codigo="<?php echo $row_articulos['codigo']; ?>" data-valor="<?php echo $row_articulos['valor']; ?>" data-descuento="<?php echo $row_articulos['descuento']; ?>" title="Editar"><?php echo $row_articulos['nombre']; ?></a></button></td>
        <td style="text-align: center"><?php echo $row_articulos['codigo']; ?></td>
        <td style="text-align: right"><?php echo number_format($row_articulos['valor']); ?></td>
        <td style="text-align: center"><?php echo number_format($row_articulos['descuento']); ?></td>
        <td style="text-align: center">		
		<form action="productos.php" class="login-form" method="post">
        <input id="m_estado" type="hidden" name="m_estado" value="1"/>
        <input id="estado" type="hidden" name="estado" value="<?php echo $row_articulos['estado']; ?>"/>				
        <input id="id_articulos" class="form-control" type="hidden" name="id_articulos" value="<?php echo $row_articulos['id_articulos']; ?>"/>
			<button type="submit" class="btn btn-link"><?php echo $estado; ?></button></td>
			</form>
      </tr>
    	<?php } while ($row_articulos = mysqli_fetch_assoc($articulos)); ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra productos en la base de datos.</h3>
	  <?php } ?>
</div>
</div>
	<?php include("foot.php"); ?>

	
<!-- Modal -->
  <div class="modal fade" id="editarprod" role="dialog">
    <div class="modal-dialog">
		<!-- Modal content-->
      <div class="modal-content">
        <div class="modal-body">
  <h2>Editar producto</h2>
  <p>Por favor modifique los datos.</p>
  <form action="productos.php" method="POST">
        <input id="m_producto" type="hidden" name="m_producto" value="1"/>
        <input id="id_articulos" class="form-control" type="hidden" name="id_articulos" value=""/>
    <div class="form-group">
      <label for="text">Nombre:</label>
      <input type="text" class="form-control" id="nombre" value="" name="nombre" required>
    </div>
    <div class="form-group">
      <label for="text">Valor:</label>
      <input type="number" class="form-control" id="valor" name="valor" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Descuento:</label>
      <input type="number" class="form-control" id="descuento" name="descuento" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Código:</label>
      <input type="text" class="form-control" id="codigo" name="codigo" value="">
    </div>
    <button type="submit" class="btn btn-primary">Continuar</button>
  </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-info" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
        </div>
      </div>

		
<!-- Modal -->
  <div class="modal fade" id="creapro" role="dialog">
    <div class="modal-dialog">
		<!-- Modal content-->
      <div class="modal-content">
        <div class="modal-body">
  <h2>Crear producto</h2>
  <p>Por favor ingrese la información.</p>
  <form action="productos.php" method="POST">
        <input id="n_productos" type="hidden" name="n_productos" value="1"/>
        <input id="nuevo" class="form-control" type="hidden" name="nuevo" value=""/>
    <div class="form-group">
      <label for="text">Nombre:</label>
      <input type="text" class="form-control" id="nombre" value="" name="nombre" required>
    </div>
    <div class="form-group">
      <label for="text">Código:</label>
      <input type="text" class="form-control" id="codigo" name="codigo" value="" required>
    </div>
	  <!--
    <div class="form-group">
      <label for="text">Medida:</label>
      <input type="text" class="form-control" id="medida" name="medida" value="" placeholder="Kg, Caja, Litro, etc" required>
    </div>
	-->
    <div class="form-group">
      <label for="number">Valor:</label>
      <input type="text" class="form-control" id="valor" name="valor" value="">
    </div>
    <div class="form-group">
      <label for="number">Descuento:</label>
      <input type="text" class="form-control" id="descuento" name="descuento" value="0">
    </div>
    <button type="submit" class="btn btn-primary">Enviar</button>
  </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-info" data-dismiss="modal">Cerrar</button>
        </div>
      </div>
        </div>
      </div>


          
	
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
 $('#editarprod').on('show.bs.modal', function(e) {
    var id_articulos= $(e.relatedTarget).data('id_articulos');
	 $(e.currentTarget).find('input[name="id_articulos"]').val(id_articulos);
    var nombre= $(e.relatedTarget).data('nombre'); $(e.currentTarget).find('input[name="nombre"]').val(nombre);
    var valor= $(e.relatedTarget).data('valor'); $(e.currentTarget).find('input[name="valor"]').val(valor);
    var codigo= $(e.relatedTarget).data('codigo');	 $(e.currentTarget).find('input[name="codigo"]').val(codigo);
    var descuento= $(e.relatedTarget).data('descuento');	 $(e.currentTarget).find('input[name="descuento"]').val(descuento);
});
</script>

</body>
</html>
<?php
mysqli_free_result($usuario);
?>