<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Requerir autenticación - redirige a index.php si no está logueado
requireLogin('index.php');

// Obtener datos del usuario actual
$colname_usuario = Utils::captureValue('MM_Username', 'SESSION', '');
if ($colname_usuario) {
    $colname_usuario = mysqli_real_escape_string($sandycat, $colname_usuario);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

// Crear variable compatible para el menú
if (!isset($row_usuario['nombre']) && isset($row_usuario['elnombre'])) {
    $row_usuario['nombre'] = $row_usuario['elnombre'];
}
$hoy = date("Y-m-d");




// Verificar si se está procesando una cancelación de venta
if (Utils::hasPostFields(['id_ventas', 'cancela'])) {
    // Capturar datos de cancelación usando función optimizada
    $cancelData = Utils::capturePostData(['id_ventas', 'num']);
    $venta = $cancelData['id_ventas'];
    $factu = $cancelData['num'];
    $query = "UPDATE miau_posts SET post_status = 'wc-cancelled' WHERE ID = '$venta'";
    mysqli_query($miau, $query);
    $query = "UPDATE miau_wc_order_stats SET status = 'wc-cancelled' WHERE order_id = '$venta'";
    mysqli_query($miau, $query);

    $query_vcancel = sprintf("SELECT order_id, product_id, product_qty FROM miau_wc_order_product_lookup WHERE order_id = '$venta'");
    $vcancel = mysqli_query($miau, $query_vcancel) or die(mysqli_error($miau));
    $row_vcancel = mysqli_fetch_assoc($vcancel);
    $totalRows_vcancel = mysqli_num_rows($vcancel);
    do {
        $product_id = $row_vcancel['product_id'];
        $product_qty = $row_vcancel['product_qty'];

        $query_stock = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_stock'");
        $stock = mysqli_query($miau, $query_stock) or die(mysqli_error($miau));
        $row_stock = mysqli_fetch_assoc($stock);
        $totalRows_stock = mysqli_num_rows($stock);

        $_stock1 = $row_stock['meta_value'];
        $_stock2 = $row_stock['meta_value'] + $product_qty;
        $query9 = "UPDATE miau_postmeta SET meta_value = '$_stock2' WHERE post_id = '$product_id' AND meta_key = '_stock'";
        mysqli_query($miau, $query9);
        if ($_stock2 > 0) {
            $query10 = "UPDATE miau_postmeta SET meta_value = 'instock' WHERE post_id = '$product_id' AND meta_key = '_stock_status'";
            mysqli_query($miau, $query10);
        }
    } while ($row_vcancel = mysqli_fetch_assoc($vcancel));
}

// 4. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<style>
</style>

<body>
    <div class="container">
        <?php include("parts/menf.php"); ?>
        <div class="py-5"></div>
        <section class="">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center mb-5">
                    <h2 class="heading-section">Ventas Woocomerce</h2>
                    <div class="user-info mt-3">
                        <p class="mb-2">
                            <i class="fas fa-user-circle"></i>
                            Bienvenido, <strong><?php echo htmlspecialchars($ellogin); ?></strong>
                        </p>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center" style="margin-top: -30px">
                <div class="col-md-7 col-lg-5">
                    <div class="login-wrap p-4 p-md-5 justify-content-center">
                        <a href="adminf.php" class="btn btn-primary btn-custom w-100 mb-3" role="button">
                            <i class="fas fa-file-invoice me-2"></i>FACTURAR
                        </a>
                        <button type="button" class="btn btn-secondary btn-custom w-100" data-bs-toggle="modal" data-bs-target="#myModal">
                            <i class="fas fa-plus-circle me-2"></i>GENERAR PEDIDO
                        </button>
                    </div>
                </div>
            </div>
            <?php include("parts/foot.php"); ?>
        </section>


        <!-- Modal para Generar Pedido -->
        <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <!-- Modal Header -->
                    <div class="modal-header bg-success bg-custom text-white">
                        <h5 class="modal-title text-white" id="myModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Generar Nuevo Pedido
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body">
                        <form action="datos_venta.php" class="needs-validation" method="post" target="_self" id="adminventas" novalidate>
                            <div class="mb-3">
                                <label for="billing_id" class="form-label">
                                    <i class="fas fa-id-card me-2"></i>Documento del Cliente
                                </label>
                                <input type="number"
                                    class="form-control form-control-lg"
                                    placeholder="Ingrese el documento del cliente"
                                    id="billing_id"
                                    name="billing_id"
                                    value=""
                                    required>
                                <div class="invalid-feedback">
                                    Por favor ingrese un documento válido.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-success btn-custom btn-lg" type="submit" name="venta" id="venta">
                                    <i class="fas fa-arrow-right me-2"></i>Continuar con el Pedido
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Validación de formulario Bootstrap 5
            (function() {
                'use strict';
                window.addEventListener('load', function() {
                    var forms = document.getElementsByClassName('needs-validation');
                    var validation = Array.prototype.filter.call(forms, function(form) {
                        form.addEventListener('submit', function(event) {
                            if (form.checkValidity() === false) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                            form.classList.add('was-validated');
                        }, false);
                    });
                }, false);
            })();
        </script>


    </div>

</body>

</html>
<?php
mysqli_free_result($usuario);
?>