<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el manejador de login común
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Requerir autenticación - redirige a index.php si no está logueado
requireLogin("index.php");

// Obtener datos del usuario actual usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    // Si no se pueden obtener los datos del usuario, redirigir al login
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;
$hoy = date("Y-m-d");

// 4. Inicializar la clase de cliente WooCommerce
$customerManager = new WooCommerceCustomer();

// 5. Procesar formulario usando la clase centralizada
$result = $customerManager->processCustomerFromProsVenta();

// 6. Extraer datos para compatibilidad con la vista
$formData = $result['form_data'] ?? [];
$compatibilityVars = $result['compatibility_vars'] ?? [];
$locationData = $result['location_data'] ?? [];
$existingOrderId = $result['existing_order_id'] ?? 0;
$customerProcessed = $result['customer_processed'] ?? false;
$customerId = $result['customer_id'] ?? 0;
$customerCreated = $result['customer_created'] ?? false;
$success = $result['success'] ?? false;
$errorMessage = $result['error'] ?? '';

// 7. Asignar variables de compatibilidad para la vista
foreach ($compatibilityVars as $varName => $varValue) {
    $$varName = $varValue;
}

// 8. Variables específicas para el resumen del cliente
// Ya no necesitamos $elid porque no manejamos productos



// 9. Logging para debugging
if ($customerProcessed) {
    Utils::logError("Cliente procesado exitosamente - Customer ID: $customerId, Creado: " . ($customerCreated ? 'Sí' : 'No'), 'INFO', 'pros_venta.php');
    if (!empty($locationData)) {
        Utils::logError("Procesamiento de ubicación - Estado original: {$locationData['original_state']}, Para usermeta: {$locationData['state_for_usermeta']}, Para addresses: {$locationData['state_for_addresses']}, Ciudad: {$locationData['original_city']}", 'INFO', 'pros_venta.php');
    }
} elseif (!$success && !empty($errorMessage)) {
    Utils::logError("Error procesando cliente: $errorMessage", 'ERROR', 'pros_venta.php');
}

// 10. Establecer variables para el formulario (compatibilidad con la vista)
date_default_timezone_set('America/Bogota');
$hoy = date('Y-m-d H:i:s');

// NOTA: Esta sección anteriormente creaba órdenes, pero ahora solo maneja clientes
// La lógica de creación de órdenes se ha movido a bproducto.php o donde corresponda

if (Utils::captureValue('nombre1', 'POST')) {
    // El cliente ya fue procesado por WooCommerceCustomer en la sección superior
    // Ya no necesitamos lógica SQL aquí - todo se maneja en la clase
    if ($customerProcessed) {
        Utils::logError("Cliente procesado exitosamente - redirigiendo a resumen", 'INFO', 'pros_venta.php');
    }
}

// NOTA: Esta sección manejaba productos y órdenes, pero se ha removido
// porque pros_venta.php ahora solo maneja clientes.
// La lógica de productos se maneja en bproducto.php o donde corresponda.

if (Utils::captureValue('proceso', 'POST')) {
    // Redirigir a donde se debe manejar la lógica de productos
    Utils::logError("Intento de procesar productos en pros_venta.php - redirigiendo a bproducto.php", 'INFO', 'pros_venta.php');
    header("Location: bproducto.php");
    exit();
}

// Ya no necesitamos cargar productos porque esto es solo resumen del cliente

// Inicializar variables para la vista del resumen del cliente
$_shipping_first_name = $_shipping_first_name ?? '';
$_shipping_last_name = $_shipping_last_name ?? '';
$billing_id = $billing_id ?? '';
$_shipping_address_1 = $_shipping_address_1 ?? '';
$_shipping_address_2 = $_shipping_address_2 ?? '';
$_billing_neighborhood = $_billing_neighborhood ?? '';
$_shipping_city = $_shipping_city ?? '';
$_shipping_state = $_shipping_state ?? '';
$_billing_phone = $_billing_phone ?? '';
$_billing_email = $_billing_email ?? '';
$metodo = $metodo ?? '';
$_order_shipping = $_order_shipping ?? 0;
$_cart_discount = $_cart_discount ?? 0;
$post_expcerpt = $post_expcerpt ?? '';
$_payment_method_title = $_payment_method_title ?? '';

// Variables de ubicación específicas
$state_for_usermeta = $locationData['state_for_usermeta'] ?? '';
$city_for_usermeta = $locationData['city_for_usermeta'] ?? '';
$state_for_addresses = $locationData['state_for_addresses'] ?? '';
$city_for_addresses = $locationData['city_for_addresses'] ?? '';

// 3. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<style>
</style>

<?php include("parts/menf.php"); ?>
<?php
// Configurar el paso actual para el wizard
$current_step = 2; // Paso 2: Productos
include('parts/step_wizard.php');
?>
        <!-- Customer Summary Header -->
        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-user-check me-2"></i>Resumen del Cliente
                </h2>
                <p class="text-muted">Información del cliente procesado exitosamente</p>
            </div>
        </div>

        <div class="order-card">
            <div class="customer-info">
                <!-- Two Column Layout -->
                <div class="row">
                    <!-- Left Column: Customer & Shipping Info -->
                    <div class="col-lg-6 col-md-12">
                        <!-- Customer Information -->
                        <?php if ($_shipping_first_name || $_shipping_last_name): ?>
                            <div class="info-section">
                                <h5><i class="fas fa-user"></i>Información del Cliente</h5>
                                <div class="info-row">
                                    <span class="info-label">Nombre Completo:</span>
                                    <span class="info-value"><?php echo strtoupper($_shipping_first_name) . " " . strtoupper($_shipping_last_name); ?></span>
                                </div>
                                <?php if ($billing_id): ?>
                                    <div class="info-row">
                                        <span class="info-label">Documento:</span>
                                        <span class="info-value"><?php echo $billing_id; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_billing_email): ?>
                                    <div class="info-row">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo $_billing_email; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_billing_phone): ?>
                                    <div class="info-row">
                                        <span class="info-label">Teléfono:</span>
                                        <span class="info-value"><?php echo $_billing_phone; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Payment & Order Info -->
                    <div class="col-lg-6 col-md-12">
                        <!-- Shipping Information -->
                        <?php if ($_shipping_address_1 || $_shipping_city): ?>
                            <div class="info-section">
                                <h5><i class="fas fa-map-marker-alt"></i>Dirección de Envío</h5>
                                <?php if ($_shipping_address_1): ?>
                                    <div class="info-row">
                                        <span class="info-label">Dirección:</span>
                                        <span class="info-value"><?php echo $_shipping_address_1 . ($_shipping_address_2 ? " " . $_shipping_address_2 : "") . ($_billing_neighborhood ? " - " . $_billing_neighborhood : ""); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_shipping_city): ?>
                                    <div class="info-row">
                                        <span class="info-label">Ciudad:</span>
                                        <span class="info-value"><?php echo $_shipping_city; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_shipping_state): ?>
                                    <div class="info-row">
                                        <span class="info-label">Departamento:</span>
                                        <span class="info-value"><?php 
                                            // Obtener el nombre completo del departamento
                                            $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
                                            if (file_exists($states_file)) {
                                                $colombia_states = include($states_file);
                                                echo isset($colombia_states[$_shipping_state]) ? $colombia_states[$_shipping_state] : $_shipping_state;
                                            } else {
                                                echo $_shipping_state;
                                            }
                                        ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">País:</span>
                                    <span class="info-value">Colombia</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Payment & Order Info -->
                    <div class="col-12">
                        <!-- Payment Information -->
                        <div class="info-section">
                            <h5><i class="fas fa-credit-card"></i>Información de Pago</h5>
                            <?php if ($metodo): ?>
                                <div class="info-row">
                                    <span class="info-label">Método de Pago:</span>
                                    <span class="info-value"><?php echo $metodo; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($row_obser['post_date']) && $row_obser['post_date']): ?>
                                <div class="info-row">
                                    <span class="info-label">Fecha del Pedido:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($row_obser['post_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value">
                                    <span class="status-badge status-pending">Pendiente</span>
                                </span>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <?php if (Utils::captureValue('proceso', 'POST') && isset($vatotal)): ?>
                            <div class="order-summary">
                                <h5><i class="fas fa-calculator me-2"></i>Resumen del Pedido</h5>
                                <?php
                                $sindesc = $vatotal + $_cart_discount;
                                ?>
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($sindesc); ?></span>
                                </div>
                                <?php if ($_cart_discount > 0): ?>
                                    <div class="summary-row">
                                        <span>Descuento:</span>
                                        <span>-$<?php echo number_format($_cart_discount); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_order_shipping > 0): ?>
                                    <div class="summary-row">
                                        <span>Envío:</span>
                                        <span>$<?php echo number_format($_order_shipping); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span>$<?php echo number_format($sindesc - $_cart_discount + ($_order_shipping ?? 0)); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($post_excerpt)): ?>
            <div class="order-card">
                <div class="customer-info">
                    <div class="info-section">
                        <h5><i class="fas fa-sticky-note"></i>Observaciones</h5>
                        <div class="alert alert-info mb-0">
                            <?php echo nl2br(htmlspecialchars($post_excerpt)); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Botón para continuar al siguiente paso -->
        <?php if ($customerProcessed): ?>
            <div class="order-card">
                <div class="customer-info">
                    <div class="text-center">
                        <h5 class="mb-4"><i class="fas fa-arrow-right me-2"></i>Siguiente Paso</h5>
                        <p class="text-muted mb-4">El cliente ha sido procesado exitosamente. Puede continuar al siguiente paso para agregar productos.</p>
                        
                        <form action="bproducto.php" method="post">
                            <!-- Pasar el ID del cliente para crear un nuevo pedido -->
                            <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                            <button type="submit" class="btn btn-primary btn-custom btn-lg">
                                <i class="fas fa-plus-circle me-2"></i>
                                Continuar - Agregar Productos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

<!-- JavaScript para el resumen del cliente -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Resumen del cliente cargado');
    
    // Agregar animación a las alertas de estado
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            alert.style.transition = 'all 0.3s ease';
            alert.style.opacity = '1';
            alert.style.transform = 'translateY(0)';
        }, 100);
    });
});
</script>

<?php include("parts/foot.php"); ?>
</body>

</html>