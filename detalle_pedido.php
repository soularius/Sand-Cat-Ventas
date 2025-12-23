<?php
require_once('class/autoload.php');
require_once('parts/login_handler.php');
requireLogin('facturacion.php');

$order_id = Utils::captureValue('id-orden', 'GET', '');
$common = Utils::captureValue('common', 'GET', '');

$order_id = intval($order_id);
if ($order_id <= 0) {
    Header("Location: inicio.php");
    exit();
}

// Obtener número de factura si existe
$factura_num = '';
$query_factura = "SELECT factura FROM facturas WHERE id_order = '$order_id' AND estado = 'a' ORDER BY id_facturas DESC LIMIT 1";
$result_factura = mysqli_query($sandycat, $query_factura);
if ($result_factura && mysqli_num_rows($result_factura) > 0) {
    $row_factura = mysqli_fetch_assoc($result_factura);
    $factura_num = (string)($row_factura['factura'] ?? '');
}
if ($result_factura) {
    mysqli_free_result($result_factura);
}

require_once('class/woocommerce_orders.php');
$ordersService = new WooCommerceOrders();

$order = $ordersService->getOrderById($order_id);
if (!$order) {
    Header("Location: inicio.php");
    exit();
}

$items = $ordersService->getOrderItems($order_id);

// Obtener notas de la orden
$order_notes = $ordersService->getOrderNotes($order_id);

// DEBUG: Ver qué items se obtuvieron del pedido
Utils::logError("ORDER ITEMS DEBUG: " . json_encode($items), 'DEBUG', 'detalle_pedido.php');

require_once('class/woocommerce_products.php');
$productsService = new WooCommerceProducts();

// Recopilar IDs de productos y variaciones específicos de los items del pedido
$product_ids = [];
$variation_ids = [];

foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $vid = (int)($it['variation_id'] ?? 0);
    
    if ($pid > 0) $product_ids[] = $pid;
    if ($vid > 0) $variation_ids[] = $vid;
}

// ✅ Obtener productos usando método optimizado sin duplicados
$products_map = [];
if (!empty($product_ids) || !empty($variation_ids)) {
    $products_with_variations = $productsService->getProductsByIds($product_ids, $variation_ids);
    
    // DEBUG: Ver qué productos se obtuvieron
    Utils::logError("PRODUCTS DEBUG: Productos obtenidos: " . json_encode(array_map(function($p) { 
        return ['id' => $p['id'], 'nombre' => $p['nombre'], 'image_url' => $p['image_url']]; 
    }, $products_with_variations)), 'DEBUG', 'detalle_pedido.php');
    
    // Crear mapa indexado por ID único (el método ya evita duplicados)
    foreach ($products_with_variations as $prod) {
        $products_map[$prod['id']] = $prod;
    }
    
    // DEBUG: Ver qué claves tiene el mapa
    Utils::logError("PRODUCTS DEBUG: Claves del products_map: " . json_encode(array_keys($products_map)), 'DEBUG', 'detalle_pedido.php');
}

// ✅ Obtener descuentos completos del pedido
$order_discounts = $ordersService->getOrderDiscounts($order_id);

$products_payload = [];
$total_items = 0;
$items_total_price = 0;

foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $vid = (int)($it['variation_id'] ?? 0);
    $qty = (int)($it['cantidad'] ?? 0);
    $total_items += $qty;

    $line_total = (float)($it['total_linea'] ?? 0);
    $items_total_price += $line_total;

    // Buscar producto: primero por variation_id si existe, luego por product_id
    $lookup_id = $vid > 0 ? $vid : $pid;
    $p = $products_map[$lookup_id] ?? null;

    // Precios desde el pedido (line_total/cantidad), no desde producto
    $unit_price = $qty > 0 ? ($line_total / $qty) : 0;
    $regular = $p ? (float)($p['precio_regular'] ?? $unit_price) : $unit_price;
    $sale = $p ? (float)($p['precio_oferta'] ?? 0) : 0;
    $price = $unit_price; // Precio real del pedido

    // ✅ Imagen ya viene optimizada del método getProductsByIds
    $image_url = $p ? ($p['image_url'] ?? '') : '';
    
    // Si no hay imagen del producto, usar placeholder directo
    if (empty($image_url)) {
        $image_url = env('WOOCOMMERCE_BASE_URL') .'/wp-content/uploads/woocommerce-placeholder.webp';
    }
    
    // Log mejorado para debugging
    Utils::logError("ORDER {$order_id} ITEM pid={$pid} vid={$vid} lookup_id={$lookup_id} image={$image_url}", 'DEBUG', 'detalle_pedido.php');

    $products_payload[] = [
        'id' => $lookup_id,               // ID único (variación o producto)
        'product_id' => $pid,             // ID del producto padre
        'variation_id' => $vid > 0 ? $vid : null,  // ID de variación
        
        'title' => $p ? ($p['parent_name'] ?? $p['nombre'] ?? ($it['nombre_producto'] ?? 'Producto')) : ($it['nombre_producto'] ?? 'Producto'),
        'parent_name' => $p ? ($p['parent_name'] ?? $p['nombre'] ?? ($it['nombre_producto'] ?? 'Producto')) : ($it['nombre_producto'] ?? 'Producto'),
        'variation_label' => $p ? ($p['variation_label'] ?? '') : '',
        'variation_attributes' => $p ? ($p['variation_attributes'] ?? null) : null,
        
        'quantity' => $qty,
        'price' => $price,                // Precio real del pedido
        'regular_price' => $regular,
        'sale_price' => ($sale > 0 && $sale < $regular) ? $sale : null,
        'sku' => $p ? ($p['sku'] ?? '') : '',
        'image_url' => $image_url,        // URL de imagen construida correctamente
        'line_total' => $line_total
    ];
}

$serverOrderData = [
    'order_id' => (int)$order_id,
    'products' => $products_payload,
    'total_items' => (int)$total_items,
    'total_price' => (float)($order['total'] ?? 0),
    '_order_shipping' => (string)($order['envio'] ?? ''),
    '_cart_discount' => (string)($order['descuento'] ?? ''),
    '_payment_method_title' => (string)($order['titulo_metodo_pago'] ?? ''),
    'post_expcerpt' => '',
    
    // ✅ Descuentos detallados desde múltiples fuentes
    'discounts_detail' => $order_discounts,
    'total_discount' => (float)$order_discounts['total_discount'],
    'cart_discount_amount' => (float)$order_discounts['cart_discount'],
    'coupons_used' => $order_discounts['coupons'],
    'fees_applied' => $order_discounts['fees'],
    
    // ✅ Notas de la orden
    'order_notes' => $order_notes
];

$serverCustomerData = [
    'order_id' => (int)$order_id,
    'nombre1' => (string)($order['nombre_cliente'] ?? ''),
    'nombre2' => (string)($order['apellido_cliente'] ?? ''),

    // ✅ DNI: manda ambos keys para compatibilidad con tu JS / formularios
    'dni' => (string)($order['dni_cliente'] ?? ''),
    'billing_id' => (string)($order['dni_cliente'] ?? ''),

    '_billing_email' => (string)($order['email_cliente'] ?? ''),
    '_billing_phone' => (string)($order['telefono_cliente'] ?? ''),

    '_shipping_address_1' => (string)($order['direccion_cliente'] ?? ''),
    '_shipping_address_2' => (string)($order['direccion_2'] ?? ''),

    // ✅ Barrio (YA NO VACÍO)
    '_billing_neighborhood' => (string)($order['barrio'] ?? ''),

    // ✅ Ciudad / Depto / País (YA NO VACÍO)
    '_shipping_city' => (string)($order['ciudad_cliente'] ?? ''),
    '_shipping_state' => (string)($order['departamento'] ?? ''),
    '_shipping_country' => (string)($order['pais'] ?? 'CO'),
];

$serverFormData = [
    '_order_shipping' => (string)($order['envio'] ?? ''),
    '_cart_discount' => (string)($order['descuento'] ?? ''),
    '_payment_method_title' => (string)($order['titulo_metodo_pago'] ?? ''),
    'post_expcerpt' => ''
];

$current_step = 5;
include('parts/header.php');
?>

<body class="order-summary-container">
    <div class="container-fluid">
        <?php include("parts/menu.php"); ?>

        <input type="hidden" id="_order_id" name="_order_id" value="<?php echo htmlspecialchars((string)$order_id); ?>">

        <?php if(empty($common)) include('parts/step_wizard.php'); ?>

        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-check-circle me-2"></i>Detalle del Pedido
                </h2>
                <p class="text-muted">Pedido #<?php echo htmlspecialchars((string)$order_id); ?></p>
            </div>
        </div>

        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="summary-panel">
                        <div class="panel-header bg-primary bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-user me-2"></i>Información del Cliente
                            </h5>
                        </div>

                        <div class="panel-body">
                            <div id="customer-info">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando información del cliente...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-warning bg-custom d-flex justify-content-between">
                            <h5 class="text-white">
                                <i class="fas fa-info-circle me-2"></i>Detalles del Pedido
                            </h5>
                            <a href="<?= env('WOOCOMMERCE_BASE_URL'); ?>/wp-admin/admin.php?page=wc-orders&action=edit&id=<?php echo htmlspecialchars((string)$order_id); ?>" class="text-white wp-eye-link" title="Ver en panel administrativo de WordPress" target="_blank">
                                <i class="fas fa-eye wp-eye-icon"></i>
                            </a>
                        </div>

                        <div class="panel-body">
                            <div id="order-details">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando detalles del pedido...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-info bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-sticky-note me-2"></i>Comentarios y Notas
                            </h5>
                        </div>

                        <div class="panel-body">
                            <div id="order-notes">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando comentarios...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-md-12">
                    <div class="summary-panel">
                        <div class="panel-header bg-success bg-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="text-white">
                                    <i class="fas fa-shopping-cart me-2"></i>Productos
                                </h5>
                                <span class="badge text-white fs-4" id="products-count">0</span>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div id="products-summary">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando productos...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-primary bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-calculator me-2"></i>Total del Pedido
                            </h5>
                        </div>

                        <div class="panel-body">
                            <div id="order-total">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Calculando total...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-danger btn-custom btn-lg w-100" onclick="window.location.href='inicio.php'">
                                    <i class="fas fa-arrow-left me-2"></i>Volver a Pedidos
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary btn-custom btn-lg w-100" id="btnImprimirPDF" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-print me-2"></i>Imprimir PDF
                                </button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <button class="btn btn-danger btn-custom btn-lg w-100" id="btnAbrirPDF" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-external-link-alt me-2"></i>Abrir PDF
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success btn-custom btn-lg w-100" id="btnDescargarPDF" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-download me-2"></i>Descargar
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-primary btn-custom btn-lg w-100 text-white" id="btnEnviarEmail" <?php echo empty($factura_num) ? 'disabled' : ''; ?> data-bs-toggle="modal" data-bs-target="#emailModal">
                                    <i class="fas fa-envelope me-2"></i>Enviar Email
                                </button>
                            </div>
                        </div>

                        <?php if (empty($factura_num)): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No hay factura activa para este pedido. Genere la factura desde el panel de administración.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Envío de Email -->
    <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary bg-custom text-white">
                    <h5 class="modal-title" id="emailModalLabel">
                        <i class="fas fa-envelope me-2"></i>Enviar Factura por Email
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-4">
                        <i class="fas fa-paper-plane text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-4">¿A dónde desea enviar la factura?</h6>
                    
                    <!-- Opción 1: Enviar al cliente -->
                    <div class="row g-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-success btn-custom w-100 py-3" id="btnEnviarCliente">
                                <i class="fas fa-user me-2"></i>
                                <div>
                                    <strong>Enviar al Cliente</strong>
                                    <br>
                                    <small class="text-white-50" id="clienteEmail"><?php echo htmlspecialchars($order['email_cliente'] ?? 'No disponible'); ?></small>
                                </div>
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="text-muted my-2">
                                <i class="fas fa-minus me-2"></i> O <i class="fas fa-minus ms-2"></i>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-warning btn-custom w-100 py-3" id="btnEnviarCustom">
                                <i class="fas fa-at me-2"></i>
                                <div>
                                    <strong>Enviar a Correo Personalizado</strong>
                                    <br>
                                    <small class="text-white-50">Especificar dirección de email</small>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Campo de email personalizado (oculto inicialmente) -->
                    <div class="mt-4" id="customEmailSection" style="display: none;">
                        <div class="input-group">
                            <span class="input-group-text bg-primary bg-custom">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control border-primary border-custom" id="customEmail" placeholder="ejemplo@correo.com" required>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            Ingrese una dirección de email válida
                        </small>
                    </div>
                </div>
                <div class="modal-footer justify-content-center" id="modalFooterDefault">
                    <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </div>
                <div class="modal-footer justify-content-center" id="modalFooterCustom" style="display: none;">
                    <button type="button" class="btn btn-secondary btn-custom" id="btnCancelarCustom">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </button>
                    <button type="button" class="btn btn-warning btn-custom" id="btnConfirmarCustom">
                        <i class="fas fa-paper-plane me-2"></i>Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include("parts/foot.php"); ?>
    <script>
        window.serverOrderData = <?php echo json_encode($serverOrderData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.serverCustomerData = <?php echo json_encode($serverCustomerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.serverFormData = <?php echo json_encode($serverFormData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.facturaNumero = <?php echo json_encode($factura_num, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.orderId = <?php echo json_encode((string)$order_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
        (function() {
            const orderId = window.orderId;
            const factura = window.facturaNumero;

            const btnAbrir = document.getElementById('btnAbrirPDF');
            const btnDescargar = document.getElementById('btnDescargarPDF');
            const btnEnviar = document.getElementById('btnEnviarEmail');
            const btnImprimir = document.getElementById('btnImprimirPDF');

            if (!orderId || !factura) {
                return;
            }

            if (btnAbrir) {
                btnAbrir.onclick = function() {
                    const url = `generar_pdf.php?orden=${encodeURIComponent(orderId)}&factura=${encodeURIComponent(factura)}`;
                    window.open(url, '_blank');
                };
            }

            if (btnDescargar) {
                btnDescargar.onclick = function() {
                    const url = `generar_pdf.php?orden=${encodeURIComponent(orderId)}&factura=${encodeURIComponent(factura)}&download=1`;
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `factura_${factura}.pdf`;
                    link.click();
                };
            }

            // Manejar modal de envío de email
            const btnEnviarCliente = document.getElementById('btnEnviarCliente');
            const btnEnviarCustom = document.getElementById('btnEnviarCustom');
            const btnCancelarCustom = document.getElementById('btnCancelarCustom');
            const btnConfirmarCustom = document.getElementById('btnConfirmarCustom');
            const customEmailSection = document.getElementById('customEmailSection');
            const customEmailInput = document.getElementById('customEmail');
            const modalFooterDefault = document.getElementById('modalFooterDefault');
            const modalFooterCustom = document.getElementById('modalFooterCustom');

            // Función para enviar email
            function enviarEmail(emailDestino) {
                const formData = new FormData();
                formData.append('orden_id', orderId);
                formData.append('factura_id', factura);
                formData.append('email_destino', emailDestino);

                return fetch('enviar_factura_email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json());
            }

            // Función para mostrar resultado
            function mostrarResultado(data, emailDestino) {
                const emailModal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
                emailModal.hide();

                if (data.success) {
                    // Crear modal de éxito
                    const successModal = document.createElement('div');
                    successModal.innerHTML = `
                        <div class="modal fade" id="successModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-success bg-custom text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-check-circle me-2"></i>Email Enviado
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center py-4">
                                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3 mb-2">¡Factura enviada exitosamente!</h6>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-envelope me-1"></i>
                                            Enviado a: <strong>${emailDestino}</strong>
                                        </p>
                                    </div>
                                    <div class="modal-footer justify-content-center">
                                        <button type="button" class="btn btn-success btn-custom" data-bs-dismiss="modal">
                                            <i class="fas fa-check me-2"></i>Entendido
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(successModal);
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                    
                    // Limpiar modal después de cerrarlo
                    document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(successModal);
                    });
                } else {
                    // Crear modal de error
                    const errorModal = document.createElement('div');
                    errorModal.innerHTML = `
                        <div class="modal fade" id="errorModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger bg-custom text-white">
                                        <h5 class="modal-title">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Error al Enviar
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center py-4">
                                        <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3 mb-2">Error al enviar la factura</h6>
                                        <p class="text-muted mb-0">${data.error || 'Error desconocido'}</p>
                                    </div>
                                    <div class="modal-footer justify-content-center">
                                        <button type="button" class="btn btn-danger btn-custom" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-2"></i>Cerrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(errorModal);
                    new bootstrap.Modal(document.getElementById('errorModal')).show();
                    
                    // Limpiar modal después de cerrarlo
                    document.getElementById('errorModal').addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(errorModal);
                    });
                }
            }

            // Enviar al cliente
            if (btnEnviarCliente) {
                btnEnviarCliente.onclick = function() {
                    const btn = this;
                    const originalText = btn.innerHTML;
                    const clienteEmail = window.serverCustomerData._billing_email;

                    if (!clienteEmail) {
                        mostrarResultado({success: false, error: 'No se encontró email del cliente'}, '');
                        return;
                    }

                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
                    btn.disabled = true;

                    enviarEmail(clienteEmail)
                        .then(data => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            mostrarResultado(data, clienteEmail);
                        })
                        .catch(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            mostrarResultado({success: false, error: 'Error de conexión'}, clienteEmail);
                        });
                };
            }

            // Mostrar campo de email personalizado
            if (btnEnviarCustom) {
                btnEnviarCustom.onclick = function() {
                    customEmailSection.style.display = 'block';
                    modalFooterDefault.style.display = 'none';
                    modalFooterCustom.style.display = 'block';
                    customEmailInput.focus();
                };
            }

            // Volver a opciones principales
            if (btnCancelarCustom) {
                btnCancelarCustom.onclick = function() {
                    customEmailSection.style.display = 'none';
                    modalFooterDefault.style.display = 'block';
                    modalFooterCustom.style.display = 'none';
                    customEmailInput.value = '';
                };
            }

            // Confirmar envío a email personalizado
            if (btnConfirmarCustom) {
                btnConfirmarCustom.onclick = function() {
                    const customEmail = customEmailInput.value.trim();
                    
                    if (!customEmail) {
                        customEmailInput.classList.add('is-invalid');
                        return;
                    }

                    // Validar formato de email
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(customEmail)) {
                        customEmailInput.classList.add('is-invalid');
                        return;
                    }

                    customEmailInput.classList.remove('is-invalid');

                    const btn = this;
                    const originalText = btn.innerHTML;

                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
                    btn.disabled = true;

                    enviarEmail(customEmail)
                        .then(data => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            mostrarResultado(data, customEmail);
                            // Resetear modal
                            customEmailSection.style.display = 'none';
                            modalFooterDefault.style.display = 'block';
                            modalFooterCustom.style.display = 'none';
                            customEmailInput.value = '';
                        })
                        .catch(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            mostrarResultado({success: false, error: 'Error de conexión'}, customEmail);
                        });
                };
            }

            // Limpiar validación al escribir
            if (customEmailInput) {
                customEmailInput.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            }

            if (btnImprimir) {
                btnImprimir.onclick = function() {
                    const url = `generar_pdf.php?orden=${encodeURIComponent(orderId)}&factura=${encodeURIComponent(factura)}&print=1`;
                    const printWindow = window.open(url, '_blank');
                    if (!printWindow) return;
                    printWindow.onload = function() {
                        printWindow.print();
                    };
                };
            }

            // Función para renderizar las notas de la orden
            function renderOrderNotes() {
                const orderNotesContainer = document.getElementById('order-notes');
                const orderNotes = window.serverOrderData?.order_notes || [];

                if (!orderNotesContainer) return;

                if (orderNotes.length === 0) {
                    orderNotesContainer.innerHTML = `
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-comment-slash fa-2x mb-2"></i>
                            <p>No hay comentarios o notas para este pedido</p>
                        </div>
                    `;
                    return;
                }

                let notesHtml = '';
                orderNotes.forEach(note => {
                    const noteTypeIcon = note.type === 'customer' ? 'fas fa-user' : 'fas fa-lock';
                    const noteTypeLabel = note.type === 'customer' ? 'Visible al cliente' : 'Nota privada';
                    const noteTypeClass = note.type === 'customer' ? 'text-success' : 'text-warning';

                    notesHtml += `
                        <div class="order-note-item mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="note-header">
                                    <span class="badge ${noteTypeClass}">
                                        <i class="${noteTypeIcon} me-1"></i>${noteTypeLabel}
                                    </span>
                                    <small class="text-muted ms-2">
                                        <i class="fas fa-clock me-1"></i>${note.formatted_date}
                                    </small>
                                </div>
                                <small class="text-muted">por ${note.author}</small>
                            </div>
                            <div class="note-content">
                                <p class="mb-0">${note.content.replace(/\n/g, '<br>')}</p>
                            </div>
                        </div>
                    `;
                });

                orderNotesContainer.innerHTML = notesHtml;
            }

            // Renderizar las notas cuando se carga la página
            document.addEventListener('DOMContentLoaded', function() {
                renderOrderNotes();
            });
        })();
    </script>
    <script src="assets/js/resumen_pedido.js"></script>
</body>
</html>
