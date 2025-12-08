<?php
/**
 * Login Handler - Lógica común de autenticación
 * Este archivo contiene la lógica de login que se puede reutilizar en múltiples páginas
 * 
 * NOTA: Este archivo asume que el autoloader ya fue cargado desde el archivo principal
 */

// Asegurar que la sesión esté iniciada
if (!isset($_SESSION)) {
    session_start();
}

// Manejar logout
if (isset($_GET['logout']) && !empty($_GET['logout'])) {
    session_unset();
    session_destroy();
}

/**
 * Procesar login con redirección dinámica
 * @param string $success_redirect - URL a donde redirigir en caso de éxito
 * @param string $error_redirect - URL a donde redirigir en caso de error
 */
function processLogin($success_redirect = "adminventas.php", $error_redirect = null) {
    global $sandycat;
    
    if (!isset($_POST['usuario']) || empty($_POST['usuario'])) {
        return;
    }
    
    $loginUsername = mysqli_real_escape_string($sandycat, $_POST['usuario']);
    $password = mysqli_real_escape_string($sandycat, $_POST['clave']);
    $LoginRS_query = "SELECT * FROM ingreso WHERE elnombre ='$loginUsername' AND lapass='$password'";
     
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
            
            // Verificar si hay URL de retorno
            if (isset($_POST['return_url']) && !empty($_POST['return_url'])) {
                $return_url = $_POST['return_url'];
                // Validar que la URL sea segura (mismo dominio)
                if (strpos($return_url, '/') === 0) {
                    Header("Location: $return_url");
                    exit();
                }
            }
            
            // Redirigir al destino especificado por defecto
            Header("Location: $success_redirect");
            exit();
        } else {
            // Redirigir con error
            if ($error_redirect === null) {
                $current_file = basename($_SERVER['PHP_SELF']);
                $error_redirect = $current_file . "?error=login3Et";
            }
            Header("Location: $error_redirect");
            exit();
        }
    }
}

/**
 * Requerir login - redirige a página de login si no está autenticado
 * @param string $login_page - Página de login a donde redirigir
 * @param string $return_url - URL de retorno después del login
 */
function requireLogin($login_page = "index.php", $return_url = null) {
    if (!isLoggedIn()) {
        if ($return_url === null) {
            $return_url = $_SERVER['REQUEST_URI'];
        }
        $redirect_url = $login_page . "?return=" . urlencode($return_url);
        Header("Location: $redirect_url");
        exit();
    }
}

/**
 * Función para mostrar el formulario de login
 * @param bool $show_error - Si mostrar mensaje de error
 * @param string $button_text - Texto personalizado para el botón
 * @param string $form_style - Estilo del formulario ('modern' o 'classic')
 */
function renderLoginForm($show_error = false, $button_text = "Ingresar al Sistema", $form_style = "modern") {
    if ($form_style == "classic") {
        // Estilo clásico para facturacion.php
        ?>
        <section class="ftco-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-7 col-lg-5">
                        <div class="login-wrap p-4 p-md-5 justify-content-center">		
                            <img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" class="img-fluid" alt="SAND&CAT" />
                            <h3 class="text-center mb-4"></h3>
                            <form action="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="login-form" method="post">
                                <?php if (isset($_GET['return'])): ?>
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_GET['return']); ?>">
                                <?php endif; ?>
                                <div class="form-group">
                                    <input type="text" id="usuario" name="usuario" class="form-control rounded-left" placeholder="Usuario" required>
                                </div>
                                <div class="form-group d-flex">
                                    <input type="password" id="clave" name="clave" class="form-control rounded-left" placeholder="Contraseña" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="form-control btn btn-primary rounded submit px-3"><strong><?php echo $button_text; ?></strong></button>
                                </div>
                                <div class="form-group d-md-flex">
                                    <div class="w-50 text-md-right"></div>
                                </div>
                                <?php if ($show_error): ?>
                                    <div class="alert alert-danger alert-dismissible" role="alert">
                                        <strong>Usuario y contraseña no coinciden</strong>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php
    } else {
        // Estilo moderno por defecto
        ?>
        <div class="login-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 d-flex justify-content-center">
                        <div class="login-card">
                            <img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" 
                                 class="brand-logo" alt="SAND&CAT" />
                            
                            <h2 class="login-title">Bienvenido</h2>
                            <p class="login-subtitle">Ingresa tus credenciales para acceder al sistema</p>
                            
                            <form action="<?php echo basename($_SERVER['PHP_SELF']); ?>" method="post">
                                <?php if (isset($_GET['return'])): ?>
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_GET['return']); ?>">
                                <?php endif; ?>
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
                                    <i class="fas fa-sign-in-alt"></i> <?php echo $button_text; ?>
                                </button>
                                
                                <?php if ($show_error): ?>
                                    <div class="alert alert-danger alert-enhanced" role="alert">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong>Usuario y contraseña no coinciden</strong>
                                        <br><small>Verifica tus credenciales e intenta nuevamente</small>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
?>
