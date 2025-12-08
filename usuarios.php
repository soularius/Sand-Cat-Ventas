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

if(isset($_POST['id_usuarios']) && isset($_POST['m_usuarios'])) {
	$_POST['id_usuarios'];
	$_POST['nombre'];
	$_POST['apellido'];
	$_POST['documento'];
	$_POST['clave'];
	$_POST['permiso'];
	$id_usuarios = $_POST['id_usuarios'];
	$apellido = $_POST['apellido'];
	$nombre = $_POST['nombre'];
	$documento = $_POST['documento'];
	$clave = $_POST['clave'];
	$permiso = $_POST['permiso'];
	$query = "UPDATE usuarios SET nombre = '$nombre', apellido ='$apellido', documento='$documento', clave='$clave', rol='$permiso' WHERE id_usuarios = '$id_usuarios'";
	mysqli_query($sandycat, $query);
}

if(isset($_POST['id_usuarios']) && isset($_POST['n_usuarios'])) {
	$_POST['id_usuarios'];
	$_POST['nombre'];
	$_POST['apellido'];
	$_POST['documento'];
	$_POST['clave'];
	$_POST['permiso'];
	$id_usuarios = $_POST['id_usuarios'];
	$apellido = $_POST['apellido'];
	$nombre = $_POST['nombre'];
	$documento = $_POST['documento'];
	$clave = $_POST['clave'];
	$permiso = $_POST['permiso'];
	$query = "INSERT INTO usuarios (nombre, apellido, documento, clave, rol, estado) VALUES ('$nombre', '$apellido', '$documento', '$clave', '$permiso', 'a')";
	mysqli_query($sandycat, $query);
}

if(isset($_POST['id_usuarios']) && isset($_POST['m_estado'])) {
	$estadoac = "";
	$_POST['id_usuarios'];
	$_POST['estado'];
	$id_usuarios = $_POST['id_usuarios'];
	$estadoac = $_POST['estado'];
	if($estadoac == "a") {
		$estadoc = "i";
	} else {
		$estadoc = "a";		
	}
	$query = "UPDATE usuarios SET estado = '$estadoc' WHERE id_usuarios = '$id_usuarios'";
	mysqli_query($sandycat, $query);
}

$query_usuarios = sprintf("SELECT * FROM usuarios");
$usuarios = mysqli_query($sandycat, $query_usuarios) or die(mysqli_error($sandycat));
$row_usuarios = mysqli_fetch_assoc($usuarios);
$totalRows_usuarios = mysqli_num_rows($usuarios);

$ellogin = '';
$ellogin = isset($row_usuario['documento']) ? $row_usuario['documento'] : '';
$id_usuarios = isset($row_usuario['id_usuarios']) ? $row_usuario['id_usuarios'] : 0;


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
<?php include("parts/header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("parts/men.php"); ?><br />
<br />
  <h2>Usuarios del sistema <a class="btn btn-default" href="#" title="Agregar" data-toggle="modal" data-target="#creausu"><i class="fa fa-plus-circle fa-lg"></i></a></h2>
<div class="tab-content">
  <br />
		<?php if($row_usuarios && isset($row_usuarios['id_usuarios']) && !empty($row_usuarios['id_usuarios'])) { ?>
	  <input class="form-control" id="busca" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Apellido</th>
        <th style="text-align: right">Documento</th>
        <th style="text-align: center">Clave</th>
        <th style="text-align: center">Permisos</th>
        <th style="text-align: center">Estado</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php do { 		
			if($row_usuarios['estado']=="a") {
				$estado = "Activo";
			} else {
				$estado = "Inactivo";
			}		
			if($row_usuarios['rol']=="a") {
				$permiso = "Administrador";
			} else {
				$permiso = "Vendedor";
			}
	
		?>
      <tr>
        <td style="text-align: left"><button type="submit" class="btn btn-link">
			<a href="#" data-toggle="modal" data-target="#editarprod" data-id_usuarios="<?php echo $row_usuarios['id_usuarios']; ?>" data-nombre="<?php echo $row_usuarios['nombre']; ?>" data-apellido="<?php echo $row_usuarios['apellido']; ?>" data-documento="<?php echo $row_usuarios['documento']; ?>" data-clave="<?php echo $row_usuarios['clave']; ?>" data-permiso="<?php echo $row_usuarios['rol']; ?>" title="Editar"><?php echo $row_usuarios['nombre']; ?></a></button></td>
        <td style="text-align: left"><?php echo $row_usuarios['apellido']; ?></td>
        <td style="text-align: right"><?php echo $row_usuarios['documento']; ?></td>
        <td style="text-align: center"><?php echo $row_usuarios['clave']; ?></td>
        <td style="text-align: center"><?php echo $permiso; ?></td>
        <td style="text-align: center">		
		<form action="usuarios.php" class="login-form" method="post">
        <input id="m_estado" type="hidden" name="m_estado" value="1"/>
        <input id="estado" type="hidden" name="estado" value="<?php echo $row_usuarios['estado']; ?>"/>				
        <input id="id_usuarios" class="form-control" type="hidden" name="id_usuarios" value="<?php echo $row_usuarios['id_usuarios']; ?>"/>
			<button type="submit" class="btn btn-link"><?php echo $estado; ?></button></td>
			</form>
      </tr>
    	<?php } while ($row_usuarios = mysqli_fetch_assoc($usuarios)); ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra usuarios en la base de datos.</h3>
	  <?php } ?>
</div>
</div>
	<?php include("parts/foot.php"); ?>

	
<!-- Modal -->
  <div class="modal fade" id="editarprod" role="dialog">
    <div class="modal-dialog">
		<!-- Modal content-->
      <div class="modal-content">
        <div class="modal-body">
  <h2>Editar usuario</h2>
  <p>Por favor modifique los datos.</p>
  <form action="usuarios.php" method="POST">
        <input id="m_usuarios" type="hidden" name="m_usuarios" value="1"/>
        <input id="id_usuarios" class="form-control" type="hidden" name="id_usuarios" value=""/>
    <div class="form-group">
      <label for="text">Nombre:</label>
      <input type="text" class="form-control" id="nombre" value="" name="nombre" required>
    </div>
    <div class="form-group">
      <label for="text">Apellido:</label>
      <input type="text" class="form-control" id="apellido" name="apellido" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Documento:</label>
      <input type="number" class="form-control" id="documento" name="documento" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Clave:</label>
      <input type="text" class="form-control" id="clave" name="clave" value="">
    </div>
    <div class="form-group">
      <label for="permiso">Permisos:</label>
      <select class="form-control" id="permiso" name="permiso">
        <option value="v">Vendedor</option>
        <option value="a">Administrador</option>
      </select>
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
  <div class="modal fade" id="creausu" role="dialog">
    <div class="modal-dialog">
		<!-- Modal content-->
      <div class="modal-content">
        <div class="modal-body">
  <h2>Crear usuario</h2>
  <p>Por favor ingrese la información.</p>
  <form action="usuarios.php" method="POST">
        <input id="n_usuarios" type="hidden" name="n_usuarios" value="1"/>
        <input id="id_usuarios" class="form-control" type="hidden" name="id_usuarios" value=""/>
    <div class="form-group">
      <label for="text">Nombre:</label>
      <input type="text" class="form-control" id="nombre" value="" name="nombre" required>
    </div>
    <div class="form-group">
      <label for="text">Apellido:</label>
      <input type="text" class="form-control" id="apellido" name="apellido" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Documento:</label>
      <input type="number" class="form-control" id="documento" name="documento" value="" required>
    </div>
    <div class="form-group">
      <label for="text">Clave:</label>
      <input type="text" class="form-control" id="clave" name="clave" value="">
    </div>
    <div class="form-group">
      <label for="permiso">Permisos:</label>
      <select class="form-control" id="permiso" name="permiso">
        <option value="v">Vendedor</option>
        <option value="a">Administrador</option>
      </select>
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
    var id_usuarios= $(e.relatedTarget).data('id_usuarios');
	 $(e.currentTarget).find('input[name="id_usuarios"]').val(id_usuarios);
    var nombre= $(e.relatedTarget).data('nombre'); $(e.currentTarget).find('input[name="nombre"]').val(nombre);
    var apellido= $(e.relatedTarget).data('apellido'); $(e.currentTarget).find('input[name="apellido"]').val(apellido);
    var clave= $(e.relatedTarget).data('clave');	 $(e.currentTarget).find('input[name="clave"]').val(clave);
    var documento= $(e.relatedTarget).data('documento');	 $(e.currentTarget).find('input[name="documento"]').val(documento);
    var permiso= $(e.relatedTarget).data('permiso');	 $(e.currentTarget).find('input[name="permiso"]').val(permiso);
});
</script>

</body>
</html>
<?php
mysqli_free_result($usuario);
?>