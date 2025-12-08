<?php
require_once('config.php');
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
            Header("Location: http://localhost/ventas?error=login3Et");
		}
    }
}
 ?>
<?php include("header.php"); ?>
<link rel="stylesheet" href="css/wizard-form.css">
<link rel="stylesheet" href="css/login.css">

<body>
<div class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 d-flex justify-content-center">
                <div class="login-card">
                    <img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" 
                         class="brand-logo" alt="SAND&CAT" />
                    
                    <h2 class="login-title">Bienvenido</h2>
                    <p class="login-subtitle">Ingresa tus credenciales para acceder al sistema</p>
                    
                    <form action="index.php" method="post">
                        <div class="form-group-enhanced">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="usuario" name="usuario" class="form-control" 
                                   placeholder=" " required>
                            <label for="usuario">Usuario</label>
                        </div>
                        
                        <div class="form-group-enhanced">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="clave" name="clave" class="form-control" 
                                   placeholder=" " required>
                            <label for="clave">Contraseña</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt"></i> Ingresar al Sistema
                        </button>
                        
                        <?php
                        // show potential errors / feedback (from login object)
                        if (isset($_GET['error'])) {
                            ?>
                            <div class="alert alert-danger alert-enhanced" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Usuario y contraseña no coinciden</strong>
                                <br><small>Verifica tus credenciales e intenta nuevamente</small>
                            </div>
                            <?php
                        }
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("foot.php"); ?>

	<script src="js/jquery.min.js"></script>
  <script src="js/popper.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
  <script src="js/bootstrap-4.4.1.js" type="text/javascript"></script>

	</body>
</html>

