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

require_once('class/woocommerce_orders.php');
$ordersService = new WooCommerceOrders();

$order = $ordersService->getOrderById($order_id);
if (!$order) {
    Header("Location: adminventas.php");
    exit();
}

$items = $ordersService->getOrderItems($order_id);

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
                            <div class="customer-details">
                                <div class="customer-name">
                                    <h6><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($order['nombre_completo'] ?? ''); ?></h6>
                                </div>
                                <div class="customer-contact">
                                    <div class="contact-item">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <span><?php echo htmlspecialchars($order['email_cliente'] ?? ''); ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <span><?php echo htmlspecialchars($order['telefono_cliente'] ?? ''); ?></span>
                                    </div>
                                </div>
                                <div class="customer-address">
                                    <h6 class="address-title"><i class="fas fa-map-marker-alt me-2"></i>Dirección</h6>
                                    <div class="address-details">
                                        <p class="mb-1 text-uppercase"><?php echo htmlspecialchars($order['direccion_cliente'] ?? ''); ?></p>
                                        <p class="mb-0 text-uppercase"><?php echo htmlspecialchars($order['ciudad_cliente'] ?? ''); ?></p>
                                    </div>
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
                            <div class="order-info">
                                <div class="info-item">
                                    <i class="fas fa-hashtag text-muted me-2"></i>
                                    <div>
                                        <strong>Pedido: </strong>
                                        <span class="text-muted">#<?php echo htmlspecialchars((string)$order_id); ?></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="info-item">
                                    <i class="fas fa-calendar text-muted me-2"></i>
                                    <div>
                                        <strong>Fecha: </strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($order['fecha_formateada'] ?? ''); ?></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="info-item">
                                    <i class="fas fa-receipt text-muted me-2"></i>
                                    <div>
                                        <strong>Estado: </strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($order['estado_legible'] ?? ($order['estado'] ?? '')); ?></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="info-item">
                                    <i class="fas fa-credit-card text-muted me-2"></i>
                                    <div>
                                        <strong>Método de Pago: </strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($order['titulo_metodo_pago'] ?? ''); ?></span>
                                    </div>
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
                                <span class="badge text-white fs-4"><?php echo htmlspecialchars((string)count($items)); ?></span>
                            </div>
                        </div>

                        <div class="panel-body">
                            <?php if (empty($items)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-box-open text-muted"></i>
                                    <p>No hay productos</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($items as $it): ?>
                                        <div class="col-md-6 col-lg-4 mb-6">
                                            <div class="card product-card position-relative">
                                                <div class="card-body d-flex flex-column p-3 card-body-product">
                                                    <h6 class="card-title fw-bold mb-2" style="color: #2c3e50; line-height: 1.3;"><?php echo htmlspecialchars($it['nombre_producto'] ?? ''); ?></h6>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="current-price text-error fw-bold fs-6">Cantidad:</span>
                                                        <span class="current-price text-error fw-bold fs-6">×<?php echo htmlspecialchars((string)($it['cantidad'] ?? 0)); ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between mt-2">
                                                        <span class="current-price text-error fw-bold fs-6">Total:</span>
                                                        <span class="current-price text-error fw-bold fs-6">$<?php echo number_format((float)($it['total_linea'] ?? 0), 0, ',', '.'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-primary bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-calculator me-2"></i>Total del Pedido
                            </h5>
                        </div>

                        <div class="panel-body">
                            <div class="order-totals">
                                <div class="total-line">
                                    <span class="label text-draft fs-6">
                                        <i class="fa-solid fa-piggy-bank me-1"></i>Subtotal:
                                    </span>
                                    <span class="value text-draft fs-6">$<?php echo number_format((float)($order['subtotal'] ?? 0), 0, ',', '.'); ?></span>
                                </div>

                                <?php if (!empty($order['envio']) && floatval($order['envio']) > 0): ?>
                                    <div class="total-line">
                                        <span class="label text-pending fs-6">
                                            <i class="fas fa-truck me-1"></i>Envío:
                                        </span>
                                        <span class="value text-pending fs-6">$<?php echo number_format((float)($order['envio'] ?? 0), 0, ',', '.'); ?></span>
                                    </div>
                                <?php endif; ?>

                                <hr class="total-separator">
                                <div class="total-line final-total">
                                    <span class="label text-primary fw-bold fs-5">
                                        <i class="fa-solid fa-money-bill-transfer me-1"></i>
                                        Total Final:
                                    </span>
                                    <span class="value text-primary fw-bold fs-5">$<?php echo number_format((float)($order['total'] ?? 0), 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-danger btn-custom btn-lg w-100" onclick="window.location.href='adminventas.php'">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary btn-custom btn-lg w-100" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include("parts/foot.php"); ?>
</body>
</html>
