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

if(isset($_GET['i']) && !empty($_GET['i'])){
	$id_ventas = $_GET['i'];
}

if(isset($_POST['id_ventas']) && isset($_POST['valor']) && isset($_POST['ingventa'])) {
	$_POST['id_ventas'];
	$_POST['valor'];
	$_POST['id_articulos'];
	$_POST['cantidad'];
	$_POST['descuento'];
	$descuento = $_POST['descuento'];
	$id_ventas = $_POST['id_ventas'];
	$valor = $_POST['valor'];
	$id_articulos = $_POST['id_articulos'];
	$cantidad = $_POST['cantidad'];
}

if(isset($_POST['id_ventas']) && isset($_POST['continuar']) && isset($_POST['ingventa'])) {
	$query = "INSERT INTO detalle (id_ventas, id_articulos, valor, cantidad, descuento) VALUES ('$id_ventas', '$id_articulos', '$valor', '$cantidad', '$descuento')";
	mysqli_query($sandycat, $query);
}

if(isset($_POST['quitar']) && isset($_POST['elimina'])) {
	$_POST['quitar'];
	$id_detalle = $_POST['quitar'];
	$query = "DELETE FROM detalle WHERE id_detalle = '$id_detalle'";
	mysqli_query($sandycat, $query);
}

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

if(isset($_POST['id_ventas']) && isset($_POST['final']) && isset($_POST['guardar'])) {
	
	$query_consec = sprintf("SELECT COUNT(id_ventas) AS consecu FROM ventas");
	$consec = mysqli_query($sandycat, $query_consec) or die(mysqli_error($sandycat));
	$row_consec = mysqli_fetch_assoc($consec);
	$totalRows_consec = mysqli_num_rows($consec);
	$num = 932 + $row_consec['consecu'];
	$consecutivo = "V".$num;
	
	$_POST['id_ventas'];
	$_POST['guardar'];
	$_POST['observacion'];
	$observacion = $_POST['observacion'];
	$id_ventas = $_POST['id_ventas'];
	$guardar = $_POST['guardar'];
	$query = "UPDATE ventas SET estado = 'a', observacion = '$observacion', consecutivo = '$consecutivo' WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
	
	$query_artpreventa1 = sprintf("SELECT detalle.id_detalle, detalle.id_articulos, articulos.nombre, id_ventas, detalle.valor, cantidad, detalle.descuento FROM detalle LEFT JOIN articulos ON detalle.id_articulos = articulos.id_articulos WHERE id_ventas = '$id_ventas' ORDER BY detalle.id_detalle ASC");
	$artpreventa1 = mysqli_query($sandycat, $query_artpreventa1) or die(mysqli_error($sandycat));
	$row_artpreventa1 = mysqli_fetch_assoc($artpreventa1);
	$totalRows_artpreventa1 = mysqli_num_rows($artpreventa1);
	do {
	$eltotal .="<tr><td align='center'>".$row_artpreventa1['cantidad']." </td><td>".$row_artpreventa1['nombre']." </td><td align='right'>$ ".number_format($row_artpreventa1['valor'])." </td><td align='right'>$ ".number_format($row_artpreventa1['valor']*$row_artpreventa1['cantidad'])."</td></tr>";
	 } while ($row_artpreventa1 = mysqli_fetch_assoc($artpreventa1)); 
	$headers = "MIME-Version: 1.0"."\n";
	$headers .= "Content-type:text/html; charset=utf-8"."\n";	
	$headers .= "From: Ventas Sand&Cat <ventas@sandycat.com.co>"."\n";
	$headers .= "Bcc: jrmorenoa@hotmail.com"."\n";
	$cuerpo = "Se ha registrado la siguiente venta.<br /><br />";
	$cuerpo .= "Vendedor: ".$row_usuario['nombre']." ".$row_usuario['apellido']."<br />";
	$cuerpo .= "Nombre: ".$row_preventa['nom_cliente']."<br />";
	$cuerpo .= "Identificacion: ".$row_preventa['doc_cliente']."<br />";
	$cuerpo .= "Consecutivo: ".$consecutivo."<br />";
	$cuerpo .= "<table border='0' cellspacing='0' cellpadding='5'>";
	$cuerpo .= "<tr><td align='center'>Cantidad</td><td>Artículo</td><td align='center'>Valor/U</td><td align='center'>Valor/T</td></tr>";
	$cuerpo .= $eltotal;
	$cuerpo .= "<tr><td colspan='3' align='right'>TOTAL COMPRA </td><td>$".number_format($row_totalpreventa['eltotal'])."</td></tr>";
	$cuerpo .= "</table>";
	if(!empty($observacion)) {
	$cuerpo .= "Comentario: ".$observacion;
		}
	$cuerpo .= "<HR width=100% align='center'>";
	$cuerpo .= "<p align='center'>No responda este mensaje, es un mensaje de informacion enviado por un sistema automatico.</p>"."<br />"."<br />";
    mail("gerencia@american-logistic.com","Venta SAND&CAT ".$consecutivo,$cuerpo,$headers);
	
  	$elnuevo = "venta.php";
    header("Location: $elnuevo");
}
if(isset($_POST['id_ventas']) && isset($_POST['final']) && isset($_POST['cancela'])) {
	$_POST['id_ventas'];
	$_POST['cancela'];
	$id_ventas = $_POST['id_ventas'];
	$cancela = $_POST['cancela'];
	$consecutivo = $row_preventa['consecutivo'];
	if(isset($_POST['cancelando'])) {
	    $headers = "MIME-Version: 1.0"."\n";
	$headers .= "Content-type:text/html; charset=utf-8"."\n";	
	$headers .= "From: Ventas Sand&Cat <ventas@sandycat.com.co>"."\n";
	$headers .= "Bcc: jrmorenoa@hotmail.com"."\n";
	$cuerpo = "Se ha cancelado la siguiente venta.<br /><br />";
	$cuerpo .= "Vendedor: ".$row_usuario['nombre']." ".$row_usuario['apellido']."<br />";
	$cuerpo .= "Nombre: ".$row_preventa['nom_cliente']."<br />";
	$cuerpo .= "Identificacion: ".$row_preventa['doc_cliente']."<br />";
	$cuerpo .= "Consecutivo: ".$row_preventa['consecutivo']."<br />";
	$cuerpo .= "<HR width=100% align='center'>";
	$cuerpo .= "<p align='center'>No responda este mensaje, es un mensaje de informacion enviado por un sistema automatico.</p>"."<br />"."<br />";
    mail("gerencia@american-logistic.com","SAND&CAT - Cancelado ".$consecutivo,$cuerpo,$headers);
	}
	
	$query = "DELETE FROM ventas WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
  	$elnuevo = "venta.php";
    header("Location: $elnuevo");
}

$query_articulos = sprintf("SELECT * FROM articulos WHERE estado = 'a' AND id_articulos NOT IN (SELECT id_articulos FROM detalle WHERE id_ventas = '$id_ventas')");
$articulos = mysqli_query($sandycat, $query_articulos) or die(mysqli_error($sandycat));
$row_articulos = mysqli_fetch_assoc($articulos);
$totalRows_articulos = mysqli_num_rows($articulos);


?>
<?php include("parst/header.php"); ?>
<script LANGUAGE="JavaScript">

var cuenta=0;
function enviado() {
if (cuenta == 0)
{
cuenta = 1;
return true;
}
else
{
alert("Ya se ha enviado el formulario. Espere por favor...");
return false;
}
}

</script>
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
						<?php if(isset($_POST['id_ventas']) && isset($_POST['valor']) && $row_totalpreventa['eltotal']>0) { ?>
						<div class="container p-3 my-3 bg-primary text-white">
						  <?php echo $row_preventa['nom_cliente']; ?><br />
							<?php echo $row_preventa['doc_cliente']; ?><br />
							<?php echo "Total: ".number_format($row_totalpreventa['eltotal']); ?><br />
							<?php // echo $row_preventa['consecutivo']; ?>
						</div>
						<form action="v_producto.php" class="login-form" method="post" onsubmit="return enviando()">
						<div class="form-group">
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="final" name="final" value="si" />
							<div class="form-group">
							  <label for="comment">Comentarios</label>
							  <textarea class="form-control" rows="5" id="observacion" name="observacion"></textarea>
							</div>
							  <div class="row">
								<div class="col text-center"><button type="submit" class="btn btn-primary rounded submit px-3" name="guardar" id="guardar">Finalizar pedido</button></div>
								<div class="col text-center"><button type="submit" class="btn btn-danger rounded submit px-3" name="cancela" id="cancela">Cancelar</button></div>
							  </div>					
						</div>
					  </form>
						  <?php 
						do {
					  ?>	
						<div class="container p-3 my-3 border">
						  <?php echo $row_artpreventa['nombre']; ?><br />
							Valor: <?php echo number_format($row_artpreventa['valor']); ?><br />
							Descuento: <?php echo $row_artpreventa['descuento']; ?><br />
							Cantidad: <?php echo $row_artpreventa['cantidad']; ?><br />
							Valor: <?php echo number_format(($row_artpreventa['valor']-$row_artpreventa['descuento'])*$row_artpreventa['cantidad']); ?><br />
						<form action="v_producto.php" class="login-form" method="post">
							<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
							<input type="hidden" id="valor" name="valor" value="si" />
							<input type="hidden" id="quitar" name="quitar" value="<?php echo number_format($row_artpreventa['id_detalle']); ?>" />
							<button type="submit" class="btn btn-info btn-block" id="elimina" name="elimina">Eliminar</button>
					  </form>	
						</div>							
    	 				 <?php } while ($row_artpreventa = mysqli_fetch_assoc($artpreventa)); 
						}
						if($row_articulos['valor']>0) {
					  ?>
		      	<h3 class="text-center mb-4"><?php if(isset($_POST['id_ventas']) && isset($_POST['valor'])) {
					echo "Agregar articulo";
							} else {
					echo "Articulo";
							} ?></h3>
						<form action="v_producto_f.php" class="login-form" method="post">
		      		<div class="form-group">
					  <label for="id_articulos"></label>
					  <select class="form-control" id="id_articulos" name="id_articulos" required>
						  <option selected></option>							
						  <?php 
						do {
					  ?>	
						  <option value="<?php echo $row_articulos['id_articulos']; ?>"><?php echo $row_articulos['nombre']."  $ ".number_format($row_articulos['valor']); ?></option>							
    	 				 <?php } while ($row_articulos = mysqli_fetch_assoc($articulos)); ?>
						</select>
		      		</div>
	            	<div class="form-group d-flex">	
  					<div class="input-group mb-3">
					<div class="input-group-prepend">
					  <span class="input-group-text">Cantidad </span>
					</div>
					<input type="number" size="3" min="1" class="form-control" id="cantidad" name="cantidad"  style="width: 60px;" value="1">
				  </div>
					</div>
	            	<div class="form-group row">
					<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
					<input type="hidden" id="valor" name="valor" value="<?php echo number_format($row_articulos['valor']); ?>" />
					<input type="hidden" id="descuento" name="descuento" value="<?php echo $row_articulos['descuento']; ?>" />
					<?php if(isset($_POST['id_ventas']) && isset($_POST['valor']) && $row_totalpreventa['eltotal'] > 0) { ?>
					<button type="submit" class="form-control btn btn-primary rounded submit px-3" name="continuar" id="continuar">Agregar</button>
						<?php } else {  ?>
					<div class="col text-center"><button type="submit" class="btn btn-primary rounded submit px-3" name="continuar" id="continuar">Continuar</button></div>
	         		 </form>
						<form action="v_producto_f.php" class="login-form" method="post">
								<div class="col text-center">
								<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $id_ventas; ?>" />
									<button type="submit" class="btn btn-danger rounded submit px-3" name="nocrea" id="nocrea">Cancelar</button></div>	
	         			 </form>
						<?php } ?>
	            	<!-- <button type="submit" class="form-control btn btn-primary rounded submit px-3">Continuar</button> -->				
	            </div>
	            <div class="form-group d-md-flex">
	            </div>
						<?php } ?>
	        </div>
			  </div>
	        </div>
	<?php include("parst/foot.php"); ?>
</section>
  </div>
    
</body>
</html>
<?php
mysqli_free_result($usuario);
?>