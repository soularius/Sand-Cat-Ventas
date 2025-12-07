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
if (!isset($_SESSION)) {
  session_start();
}
if (isset($_GET['logout']) && !empty($_GET['logout'])) {
session_unset();
session_destroy();
}
if (isset($_POST['usuario']) && !empty($_POST['usuario'])) {
    
  $loginUsername=mysqli_real_escape_string($sandycat,$_POST['usuario']);
  $password=mysqli_real_escape_string($sandycat,$_POST['clave']);
  $MM_fldUserAuthorization = "";
  $MM_redirectLoginSuccess = "conectar.php";
  $MM_redirectLoginFailed = "http://localhost/ventas";
  $MM_redirecttoReferrer = false;
  $LoginRS_query="SELECT * FROM ingreso WHERE elnombre ='$loginUsername' AND lapass='$password'";
   
			$userok = "";
   
			if($resulta = $sandycat->query($LoginRS_query)) {
				while($row_LoginRS_query = $resulta->fetch_array()) {
 
					$userok = $row_LoginRS_query["elnombre"];
					$passok = $row_LoginRS_query["lapass"];
				}
				$resulta->close();
			}
			$sandycat->close();
 
			if(isset($loginUsername) && isset($password)) {
 
				if($loginUsername == $userok && $password == $passok) {
					$_SESSION["logueado"] = TRUE;
					$_SESSION['MM_Username'] = $userok;
					Header("Location: adminventas.php");
 
				}
				else {
					
				Header("Location: http://localhost/ventas/facturacion.php?error=login3Et");
		
				}
 
				
				// fin isset
			}

		}


 ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//ES" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
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
<section class="ftco-section">
	<div class="container">
			<!-- <div class="row justify-content-center">
				<div class="col-md-6 text-center mb-5">
					<h2 class="heading-section"></h2>
				</div>
			</div> -->
			<div class="row justify-content-center">
				<div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">		
						<img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" class="img-fluid" alt="SAND&CAT" />
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
		      	<h3 class="text-center mb-4"></h3>
						<form action="facturacion.php" class="login-form" method="post">
		      		<div class="form-group">
		      			<input type="text" id="usuario" name="usuario" class="form-control rounded-left" placeholder="Usuario" required>
		      		</div>
	            <div class="form-group d-flex">
	              <input type="password" id="clave" name="clave" class="form-control rounded-left" placeholder="Contraseña" required>
	            </div>
	            <div class="form-group">
	            	<button type="submit" class="form-control btn btn-primary rounded submit px-3"><strong>Facturación Woocommerce</strong></button>

	            </div>
	            <div class="form-group d-md-flex">
	            	<!-- <div class="w-50">
	            		<label class="checkbox-wrap checkbox-primary">Recordarme
									  <input type="checkbox" checked>
									  <span class="checkmark"></span>
									</label>
								</div> -->
								<div class="w-50 text-md-right">
									<!-- <a href="#">Recordar contraseña</a> -->
								</div>
	            </div>
										<?php
				// show potential errors / feedback (from login object)
				if (isset($_GET['error'])) {
						?>
						<div class="alert alert-danger alert-dismissible" role="alert">
						    <strong>Usuario y contrase&ntilde;a no coinciden</strong>
							
						</div>
						<?php
				}
				?>
	          </form>
	        </div>
				</div>
			</div>
		</div>
	<?php include("foot.php"); ?>
	</section>

	<script src="js/jquery.min.js"></script>
  <script src="js/popper.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script src="js/bootstrap-4.4.1.js" type="text/javascript"></script>

	</body>
</html>

