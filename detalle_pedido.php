<?php
require_once('class/autoload.php');
require_once('parts/login_handler.php');
requireLogin('facturacion.php');

$order_id = Utils::captureValue('id-orden', 'GET', '');
$order_id = intval($order_id);
if ($order_id <= 0) {
    Header("Location: adminventas.php");
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
    Header("Location: adminventas.php");
    exit();
}

$items = $ordersService->getOrderItems($order_id);

require_once('class/woocommerce_products.php');
$productsService = new WooCommerceProducts();

$product_ids = [];
foreach ($items as $it) {
    if (!empty($it['product_id'])) {
        $product_ids[] = (int)$it['product_id'];
    }
}

$products_map = $productsService->getProductsByIds($product_ids);

$products_payload = [];
$total_items = 0;
$items_total_price = 0;

foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $p = $products_map[$pid] ?? null;

    $qty = (int)($it['cantidad'] ?? 0);
    $total_items += $qty;

    $line_total = (float)($it['total_linea'] ?? 0);
    $items_total_price += $line_total;

    $regular = $p ? (float)($p['precio_regular'] ?? 0) : 0;
    $sale = $p ? (float)($p['precio_oferta'] ?? 0) : 0;
    $price = $p ? (float)($p['precio'] ?? 0) : 0;

    $products_payload[] = [
        'id' => $pid,
        'title' => $p ? ($p['nombre'] ?? ($it['nombre_producto'] ?? 'Producto')) : ($it['nombre_producto'] ?? 'Producto'),
        'quantity' => $qty,
        'price' => $price,
        'regular_price' => $regular,
        'sale_price' => ($sale > 0 ? $sale : null),
        'sku' => $p ? ($p['sku'] ?? '') : '',
        'image_url' => $p ? ($p['image_url'] ?? '') : '',
        'permalink' => $p ? ($p['permalink'] ?? '') : ''
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
    'post_expcerpt' => ''
];

$serverCustomerData = [
    'order_id' => (int)$order_id,
    'nombre1' => (string)($order['nombre_cliente'] ?? ''),
    'nombre2' => (string)($order['apellido_cliente'] ?? ''),
    '_billing_email' => (string)($order['email_cliente'] ?? ''),
    '_billing_phone' => (string)($order['telefono_cliente'] ?? ''),
    '_shipping_address_1' => (string)($order['direccion_cliente'] ?? ''),
    '_shipping_address_2' => '',
    '_billing_neighborhood' => '',
    '_shipping_city' => (string)($order['ciudad_cliente'] ?? ''),
    '_shipping_state' => ''
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
        <?php include("parts/menf.php"); ?>

        <input type="hidden" id="_order_id" name="_order_id" value="<?php echo htmlspecialchars((string)$order_id); ?>">

        <?php include('parts/step_wizard.php'); ?>

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
                        <div class="panel-header bg-warning bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-info-circle me-2"></i>Detalles del Pedido
                            </h5>
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
                                <button class="btn btn-danger btn-custom btn-lg w-100" onclick="window.location.href='adminventas.php'">
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
                                <button class="btn btn-secondary btn-custom btn-lg w-100" id="btnAbrirPDF" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-external-link-alt me-2"></i>Abrir PDF
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success btn-custom btn-lg w-100" id="btnDescargarPDF" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-download me-2"></i>Descargar
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-warning btn-custom btn-lg w-100 text-white" id="btnEnviarEmail" <?php echo empty($factura_num) ? 'disabled' : ''; ?>>
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

            if (btnEnviar) {
                btnEnviar.onclick = function() {
                    const btn = this;
                    const originalText = btn.innerHTML;

                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
                    btn.disabled = true;

                    const formData = new FormData();
                    formData.append('orden_id', orderId);
                    formData.append('factura_id', factura);

                    fetch('enviar_factura_email.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            if (data.success) {
                                alert(`Factura enviada exitosamente a: ${data.email}`);
                            } else {
                                alert('Error al enviar factura: ' + (data.error || 'Error desconocido'));
                            }
                        })
                        .catch(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            alert('Error al enviar factura');
                        });
                };
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
        })();
    </script>
    <script src="assets/js/resumen_pedido.js"></script>
</body>
</html>
