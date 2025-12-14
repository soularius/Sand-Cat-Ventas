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

// 5. Verificar si hay datos en localStorage (navegación hacia atrás)
$useStoredData = false;
$isNavigatingBack = !Utils::captureValue('nombre1', 'POST') && !Utils::captureValue('_order_id', 'POST');

// 6. Procesar formulario usando la clase centralizada solo si hay nuevos datos
if (!$isNavigatingBack) {
    $result = $customerManager->processCustomerFromProsVenta();
} else {
    // Navegación hacia atrás - usar datos almacenados si existen
    $result = [
        'success' => true,
        'customer_processed' => false, // No procesamos nuevamente
        'customer_id' => 0,
        'customer_created' => false,
        'form_data' => [],
        'compatibility_vars' => [],
        'location_data' => [],
        'existing_order_id' => 0
    ];
    $useStoredData = true;
}

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

$formFields = [
    'nombre1',
    'nombre2',
    'billing_id',
    '_billing_email',
    '_billing_phone',
    '_shipping_address_1',
    '_shipping_address_2',
    '_billing_neighborhood',
    '_shipping_city',
    '_shipping_state',
    'post_expcerpt',
    '_order_shipping',
    '_cart_discount',
    '_payment_method_title',
    '_order_id'
];

$formData = Utils::capturePostData($formFields);

// Usar datos capturados del POST para poblar variables de la vista
$_shipping_first_name = $formData['nombre1'] ?? '';
$_shipping_last_name = $formData['nombre2'] ?? '';
$billing_id = $formData['billing_id'] ?? '';
$_billing_email = $formData['_billing_email'] ?? '';
$_billing_phone = $formData['_billing_phone'] ?? '';
$_shipping_address_1 = $formData['_shipping_address_1'] ?? '';
$_shipping_address_2 = $formData['_shipping_address_2'] ?? '';
$_billing_neighborhood = $formData['_billing_neighborhood'] ?? '';
$_shipping_city = $formData['_shipping_city'] ?? '';
$_shipping_state = $formData['_shipping_state'] ?? '';
$post_excerpt = $formData['post_expcerpt'] ?? '';
$_order_shipping = $formData['_order_shipping'] ?? 0;
$_cart_discount = $formData['_cart_discount'] ?? 0;
$_payment_method_title = $formData['_payment_method_title'] ?? '';
$metodo = $_payment_method_title;

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

        <div class="container">
            <div class="order-card">
                <div class="customer-info">
                    <!-- Two Column Layout -->
                    <div class="row">
                        <!-- Left Column: Customer & Shipping Info -->
                        <div class="col-lg-6 col-md-12">
                            <!-- Customer Information -->
                            <div class="info-section">
                                <h5><i class="fas fa-user"></i>Información del Cliente</h5>
                                <div class="info-row">
                                    <span class="info-label">Nombre Completo:</span>
                                    <span class="info-value" id="sum_full_name"><?php echo strtoupper($_shipping_first_name) . " " . strtoupper($_shipping_last_name); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Documento:</span>
                                    <span class="info-value" id="sum_dni"><?php echo $billing_id; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value" id="sum_email"><?php echo $_billing_email; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Teléfono:</span>
                                    <span class="info-value" id="sum_phone"><?php echo $_billing_phone; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Payment & Order Info -->
                        <div class="col-lg-6 col-md-12">
                            <!-- Shipping Information -->
                            <div class="info-section">
                                <h5><i class="fas fa-map-marker-alt"></i>Dirección de Envío</h5>
                                <div class="info-row">
                                    <span class="info-label">Dirección:</span>
                                    <span class="info-value" id="sum_address"><?php echo $_shipping_address_1 . ($_shipping_address_2 ? " " . $_shipping_address_2 : "") . ($_billing_neighborhood ? " - " . $_billing_neighborhood : ""); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Ciudad:</span>
                                    <span class="info-value" id="sum_city"><?php echo $_shipping_city; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Departamento:</span>
                                    <span class="info-value" id="sum_state"><?php 
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
                                <div class="info-row">
                                    <span class="info-label">País:</span>
                                    <span class="info-value">Colombia</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Payment & Order Info -->
                        <div class="col-12">
                            <!-- Payment Information -->
                            <div class="info-section">
                                <h5><i class="fas fa-credit-card"></i>Información de Pago</h5>
                                <div class="info-row">
                                    <span class="info-label">Método de Pago:</span>
                                    <span class="info-value" id="sum_payment_method"><?php echo $metodo; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Envío:</span>
                                    <span class="info-value" id="sum_shipping"><?php
                                        // ✅ Si no hay envío desde server (ej: navegación back sin POST), lo dejamos vacío
                                        $shipping_value = (string)($_order_shipping ?? '');
                                        $shipping_clean = preg_replace('/[^\d]/', '', $shipping_value);
                                        $shipping_num = (int)$shipping_clean;

                                        if ($shipping_num > 0) {
                                            echo '$' . number_format($shipping_num, 0, ',', '.');
                                        } else {
                                            echo ''; // <-- CLAVE: vacío para que JS lo hidrate desde localStorage
                                        }
                                    ?></span>
                                </div>
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
                <div class="customer-info">
                    <div class="info-section">
                        <h5><i class="fas fa-sticky-note"></i>Observaciones</h5>
                        <div class="alert alert-info mb-0">
                            <?php echo nl2br(htmlspecialchars($post_excerpt)); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <!-- Botones de navegación -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <!-- Botón Volver -->
                        <a href="datos_venta.php" class="btn btn-danger btn-custom">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver - Editar Datos
                        </a>
                    
                    <!-- Botón Continuar -->
                    <?php if ($customerProcessed): ?>
                        <form action="bproducto.php" method="post">
                            <!-- Pasar el ID del cliente para crear un nuevo pedido -->
                            <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                            <button type="submit" class="btn btn-success btn-custom">
                                <i class="fas fa-plus-circle me-2"></i>
                                Continuar - Agregar Productos
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="bproducto.php" class="btn btn-success btn-custom">
                            <i class="fas fa-plus-circle me-2"></i>
                            Continuar - Agregar Productos
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


<!-- Sistema de Persistencia de Formularios -->
<script src="assets/js/form-persistence.js"></script>

<script>
// Hidratación selectiva desde localStorage
document.addEventListener('DOMContentLoaded', function() {
    const key = 'ventas_wizard_form_data';
    const raw = localStorage.getItem(key);
    if (!raw) return;

    let data;
    try { 
        data = JSON.parse(raw); 
    } catch (e) { 
        console.error('Error parsing localStorage data:', e);
        return; 
    }

    console.log('Hidratando datos desde localStorage:', data);

    // ✅ Solo hidratar si el server vino vacío
    const setIfEmpty = (id, value) => {
        const el = document.getElementById(id);
        if (!el) return;
        if ((el.textContent || '').trim() !== '') return; // no pisar server
        el.textContent = value || '';
    };

    const fullName = `${(data.nombre1||'').toUpperCase()} ${(data.nombre2||'').toUpperCase()}`.trim();

    // Información del cliente
    setIfEmpty('sum_full_name', fullName);
    setIfEmpty('sum_dni', data.billing_id || '');
    setIfEmpty('sum_email', data._billing_email || '');
    setIfEmpty('sum_phone', data._billing_phone || '');

    // Dirección de envío
    const address = `${data._shipping_address_1 || ''} ${data._shipping_address_2 || ''} ${data._billing_neighborhood ? '- ' + data._billing_neighborhood : ''}`.trim();
    setIfEmpty('sum_address', address);
    setIfEmpty('sum_city', data._shipping_city || '');
    
    // Convertir código de departamento a nombre completo
    const stateCode = data._shipping_state || '';
    if (stateCode && window.VentasUtils && window.VentasUtils.convertStateCodeToName) {
        const stateName = window.VentasUtils.convertStateCodeToName(stateCode);
        setIfEmpty('sum_state', stateName);
    } else {
        setIfEmpty('sum_state', stateCode);
    }

    // Información de pago
    setIfEmpty('sum_payment_method', data._payment_method_title || '');
    
    // Formatear envío si existe
    const shippingValue = data._order_shipping_value || data._order_shipping || '';
    console.log('Shipping value from localStorage:', shippingValue);
    
    if (shippingValue) {
        const cleanShipping = String(shippingValue).replace(/[^\d]/g, '');
        console.log('Clean shipping:', cleanShipping);
        
        if (cleanShipping && cleanShipping !== '0') {
            const numericShipping = parseInt(cleanShipping);
            const formattedShipping = '$' + numericShipping.toLocaleString('es-CO');
            console.log('Formatted shipping:', formattedShipping);
            setIfEmpty('sum_shipping', formattedShipping);
        }
    }

    console.log('Hidratación completada');
});
</script>

<?php include("parts/foot.php"); ?>
</body>

</html>