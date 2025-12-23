<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar login handler centralizado
require_once('parts/login_handler.php');

// 3. Cargar clases específicas
require_once('class/woocommerce_orders.php');

// 4. Lógica de autenticación
requireLogin('ventas.php');

// 5. Obtener datos del usuario usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    Utils::logError("No se pudieron obtener datos del usuario en facturacion.php", 'ERROR', 'facturacion.php');
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

// 6. Obtener order_id desde URL (GET parameter)
$order_id = Utils::captureValue('id', 'GET', '');

if (empty($order_id) || !is_numeric($order_id)) {
    Utils::logError("ID de pedido inválido o faltante en facturacion.php: $order_id", 'ERROR', 'facturacion.php');
    Header("Location: ventas.php");
    exit();
}

// 7. Inicializar clase de órdenes WooCommerce
$wc_orders = new WooCommerceOrders();

// 8. Obtener detalles completos del pedido usando clase WooCommerce
try {
    $order_details = $wc_orders->getOrderDetails((int)$order_id);
    
    if (empty($order_details)) {
        Utils::logError("Pedido no encontrado para ID: $order_id", 'ERROR', 'facturacion.php');
        Header("Location: ventas.php");
        exit();
    }
    
    Utils::logError("Detalles del pedido cargados exitosamente para ID: $order_id", 'INFO', 'facturacion.php');
    
} catch (Exception $e) {
    Utils::logError("Error obteniendo detalles del pedido $order_id: " . $e->getMessage(), 'ERROR', 'facturacion.php');
    Header("Location: ventas.php");
    exit();
}

// 9. Obtener número de factura siguiente
$query_numfact = "SELECT COUNT(id_facturas) AS numero FROM facturas";
$numfact_result = mysqli_query($sandycat, $query_numfact);
if ($numfact_result) {
    $row_numfact = mysqli_fetch_assoc($numfact_result);
    $numfact = ($row_numfact['numero'] ?? 0) + 1;
    mysqli_free_result($numfact_result);
} else {
    $numfact = 1;
    Utils::logError("Error obteniendo número de factura: " . mysqli_error($sandycat), 'ERROR', 'facturacion.php');
}

// 10. Procesar datos del pedido para mostrar
$customer_data = [
    'nombre1' => $order_details['billing_first_name'] ?? '',
    'nombre2' => $order_details['billing_last_name'] ?? '',
    'documento' => $order_details['dni_cliente'] ?? $order_details['dni_cliente'] ?? '',
    'correo' => $order_details['billing_email'] ?? '',
    'celular' => $order_details['billing_phone'] ?? '',
    'dir1' => $order_details['billing_address_1'] ?? '',
    'dir2' => $order_details['billing_address_2'] ?? '',
    'barrio' => $order_details['billing_barrio'] ?? '',
    'ciudad' => $order_details['billing_city'] ?? '',
    'departamento' => $order_details['billing_state'] ?? '',
    'metodo' => $order_details['payment_method_title'] ?? 'N/A',
    'envio' => (float)($order_details['shipping_cost'] ?? 0),
    'vtotal' => (float)($order_details['total'] ?? 0),
    'descuento' => 0, // Se calculará desde los items
    'fecha' => $order_details['post_date'] ?? date('Y-m-d H:i:s'),
    'observaciones' => $order_details['post_excerpt'] ?? ''
];

// 11. Calcular subtotal y descuento usando los campos de get_order_details.php
$subtotal_productos = 0;
$descuento_total = 0;
$subtotal_sin_descuento = 0;

if (!empty($order_details['items'])) {
    foreach ($order_details['items'] as $item) {
        // Usar line_total para el total final del producto (con descuentos aplicados)
        $line_total = (float)($item['line_total'] ?? 0);
        $subtotal_productos += $line_total;
        
        // Usar subtotal_linea que viene de get_order_details.php
        $subtotal_linea = (float)($item['subtotal_linea'] ?? $line_total);
        $subtotal_sin_descuento += $subtotal_linea;
        
        // Calcular descuento usando los campos proporcionados
        $descuento_item = $subtotal_linea - $line_total;
        if ($descuento_item > 0) {
            $descuento_total += $descuento_item;
        }
    }
}

// Actualizar customer_data con los valores correctos
$customer_data['subtotal'] = $subtotal_sin_descuento;
$customer_data['descuento'] = $descuento_total;

// Verificar que el total coincida: subtotal - descuento + envío = total
$total_calculado = $subtotal_sin_descuento - $descuento_total + $customer_data['envio'];
if (abs($total_calculado - $customer_data['vtotal']) > 0.01) {
    Utils::logError("Discrepancia en cálculo de totales. Calculado: $total_calculado, BD: {$customer_data['vtotal']}", 'WARNING', 'facturacion.php');
}

Utils::logError("Datos del cliente procesados para pedido $order_id", 'INFO', 'facturacion.php');

// 12. Procesar generación de factura si se envió el formulario
if (Utils::isPostRequest() && isset($_POST['ingfact'])) {
    try {
        $invoice_number = Utils::captureValue('num', 'POST', '');
        
        if (empty($invoice_number) || !is_numeric($invoice_number)) {
            Utils::logError("Número de factura inválido: $invoice_number", 'ERROR', 'facturacion.php');
            $error_message = "Número de factura inválido";
        } else {
            // Verificar si ya existe una factura para este pedido
            $check_sql = "SELECT id_facturas FROM facturas WHERE id_order = ?";
            $check_stmt = mysqli_prepare($sandycat, $check_sql);
            
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, 'i', $order_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    Utils::logError("Factura ya existe para pedido $order_id", 'WARNING', 'facturacion.php');
                    $error_message = "Ya existe una factura para este pedido";
                } else {
                    // Crear la factura
                    $insert_sql = "INSERT INTO facturas (id_order, factura, estado) VALUES (?, ?, 'a')";
                    $insert_stmt = mysqli_prepare($sandycat, $insert_sql);
                    
                    if ($insert_stmt) {
                        $factura_str = (string)$invoice_number;
                        mysqli_stmt_bind_param($insert_stmt, 'is', $order_id, $factura_str);
                        
                        if (mysqli_stmt_execute($insert_stmt)) {
                            Utils::logError("Factura #$invoice_number generada exitosamente para pedido $order_id por usuario: $ellogin", 'INFO', 'facturacion.php');
                            
                            // Redirigir a ventas.php con mensaje de éxito
                            $_SESSION['factura_success'] = "Factura #$invoice_number generada exitosamente";
                            Header("Location: ventas.php");
                            exit();
                        } else {
                            Utils::logError("Error ejecutando inserción de factura: " . mysqli_error($sandycat), 'ERROR', 'facturacion.php');
                            $error_message = "Error al generar la factura";
                        }
                        
                        mysqli_stmt_close($insert_stmt);
                    } else {
                        Utils::logError("Error preparando inserción de factura: " . mysqli_error($sandycat), 'ERROR', 'facturacion.php');
                        $error_message = "Error al preparar la factura";
                    }
                }
                
                mysqli_stmt_close($check_stmt);
            } else {
                Utils::logError("Error preparando verificación de factura: " . mysqli_error($sandycat), 'ERROR', 'facturacion.php');
                $error_message = "Error al verificar factura existente";
            }
        }
    } catch (Exception $e) {
        Utils::logError("Error generando factura: " . $e->getMessage(), 'ERROR', 'facturacion.php');
        $error_message = "Error inesperado al generar la factura";
    }
}

?>
<?php include("parts/header.php"); ?>
<body>
<div class="container">
<?php include("parts/menu.php"); ?>
<section class=""><br />
<br />
<br />
<br />
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice me-2"></i>Facturación de Pedido</h2>
        <div class="d-flex gap-2">
            <a href="ventas.php" class="btn btn-secondary btn-custom">
                <i class="fas fa-arrow-left me-1"></i>Volver a Ventas
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Detalles del Pedido -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary bg-custom text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>Detalles del Pedido #<?php echo $order_id; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Información del Pedido y Cliente -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <h6><i class="fas fa-info-circle me-2"></i> Información del Pedido</h6>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <?php 
                                            $status = $order_details['post_status'] ?? 'wc-processing';
                                            $statusBadge = '';
                                            switch($status) {
                                                case 'wc-completed':
                                                    $statusBadge = '<span class="badge bg-success bg-custom">Completado</span>';
                                                    break;
                                                case 'wc-processing':
                                                    $statusBadge = '<span class="badge bg-primary bg-custom">Procesando</span>';
                                                    break;
                                                case 'wc-pending':
                                                    $statusBadge = '<span class="badge bg-warning bg-custom">Pendiente</span>';
                                                    break;
                                                case 'wc-cancelled':
                                                    $statusBadge = '<span class="badge bg-danger bg-custom">Cancelado</span>';
                                                    break;
                                                default:
                                                    $statusBadge = '<span class="badge bg-primary bg-custom">Procesando</span>';
                                            }
                                            echo $statusBadge;
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha:</strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($customer_data['fecha'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Método de Pago:</strong></td>
                                        <td><?php echo $customer_data['metodo']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Facturación:</strong></td>
                                        <td><span class="badge bg-warning bg-custom">Pendiente</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <h6><i class="fas fa-user me-2"></i> Información del Cliente</h6>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td><?php echo strtoupper($customer_data['nombre1'] . ' ' . $customer_data['nombre2']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo $customer_data['correo'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>DNI:</strong></td>
                                        <td><?php echo $customer_data['documento'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td><?php echo $customer_data['celular'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Dirección:</strong></td>
                                        <td><?php 
                                            $address_parts = array_filter([
                                                $customer_data['dir1'],
                                                $customer_data['dir2'],
                                                $customer_data['barrio']
                                            ]);
                                            echo !empty($address_parts) ? implode(' ', $address_parts) : 'N/A';
                                        ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Ciudad:</strong></td>
                                        <td><?= Utils::formatLocation($customer_data['ciudad'], $customer_data['departamento'], 'CO', true); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos del Pedido -->
            <?php if (!empty($order_details['items'])) { ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary bg-custom text-white">
                    <h6 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Productos del Pedido</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="px-3 py-2">Cant.</th>
                                    <th class="px-3 py-2">Producto</th>
                                    <th class="text-right px-3 py-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($order_details['items'] as $item) { 
                                    $qty = (int)($item['product_qty'] ?? $item['qty'] ?? $item['quantity'] ?? 1);
                                    $line_total = (float)($item['line_total'] ?? 0);
                                    $subtotal_linea = (float)($item['subtotal_linea'] ?? $line_total);
                                    $product_name = htmlspecialchars($item['order_item_name'] ?? 'Producto sin nombre');
                                    
                                    // Usar los campos de precio que vienen de get_order_details.php
                                    $regular_price = (float)($item['regular_price'] ?? $item['_regular_price'] ?? 0);
                                    $sale_price = (float)($item['sale_price'] ?? $item['_sale_price'] ?? 0);
                                    $has_discount = $item['has_discount'] ?? ($subtotal_linea > $line_total);
                                    
                                    // Si no hay precios unitarios definidos, calcularlos
                                    if ($regular_price == 0 && $sale_price == 0 && $qty > 0) {
                                        $sale_price = $line_total / $qty;
                                        $regular_price = $subtotal_linea / $qty;
                                        $has_discount = $regular_price > $sale_price;
                                    }
                                ?>
                                <tr>
                                    <td class="px-3 py-2"><?php echo $qty; ?></td>
                                    <td class="px-3 py-2">
                                        <?php echo $product_name; ?>
                                        <?php if (!empty($item['sku'])) { ?>
                                        <br><small class="text-muted">SKU: <?php echo htmlspecialchars($item['sku']); ?></small>
                                        <?php } ?>
                                        <?php if ($has_discount) { ?>
                                        <br><small class="text-success"><i class="fas fa-tag me-1"></i>Producto con descuento</small>
                                        <?php } ?>
                                    </td>
                                    <td class="text-right px-3 py-2">
                                        <?php if ($has_discount) { ?>
                                            <span class="text-danger fw-bold me-2">$<?php echo number_format($line_total); ?></span>
                                            <br><span class="text-muted text-decoration-line-through small">$<?php echo number_format($subtotal_linea); ?></span>
                                        <?php } else { ?>
                                            $<?php echo number_format($line_total); ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <th colspan="2" class="px-3 py-2">Total del Pedido</th>
                                    <th class="text-right px-3 py-2">$<?php echo number_format($customer_data['vtotal']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php } else { ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No hay productos para mostrar en este pedido.
            </div>
            <?php } ?>
            
            <!-- Resumen Financiero -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success bg-custom text-white">
                    <h6 class="card-title mb-0"><i class="fas fa-calculator me-2"></i>Resumen Financiero</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Subtotal:</strong> $<?php echo number_format($customer_data['subtotal']); ?>
                        </div>
                        <?php if ($customer_data['descuento'] > 0) { ?>
                        <div class="col-md-3">
                            <strong>Descuento:</strong> <span class="text-danger">-$<?php echo number_format($customer_data['descuento']); ?></span>
                        </div>
                        <?php } ?>
                        <?php if ($customer_data['envio'] > 0) { ?>
                        <div class="col-md-3">
                            <strong>Envío:</strong> $<?php echo number_format($customer_data['envio']); ?>
                        </div>
                        <?php } ?>
                        <div class="col-md-3">
                            <strong>Total:</strong> <span class="text-success fw-bold">$<?php echo number_format($customer_data['vtotal']); ?></span>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <strong>Método de Pago:</strong> <?php echo $customer_data['metodo']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($customer_data['observaciones'])) { ?>
            <!-- Observaciones -->
            <div class="alert alert-warning mb-4">
                <h6 class="alert-heading"><i class="fas fa-sticky-note me-2"></i>Observaciones</h6>
                <?php echo nl2br(htmlspecialchars($customer_data['observaciones'])); ?>
            </div>
            <?php } ?>
            
            <?php if (isset($error_message)) { ?>
            <!-- Error Message -->
            <div class="alert alert-danger mb-4">
                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Error</h6>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php } ?>
            
            <!-- Acciones de Facturación -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-danger bg-custom text-white">
                    <h6 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Generar Factura</h6>
                </div>
                <div class="card-body">
                    <form action="facturacion.php?id=<?php echo $order_id; ?>" method="post" class="mb-3">
                        <input type="hidden" name="id_ventas" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="imprimiendo" value="1">
                        <input type="hidden" name="factura" value="si">
                        
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-primary bg-custom text-white">
                                <i class="fas fa-hashtag"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   value="<?php echo $numfact; ?>" 
                                   disabled style="background-color: #f8f9fa; color: #6c757d;">
                            <input type="hidden" name="num" value="<?php echo $numfact; ?>">
                            <button class="btn btn-success btn-custom" type="submit" name="ingfact">
                                <i class="fas fa-file-invoice me-2"></i>GENERAR FACTURA
                            </button>
                        </div>
                    </form>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form action="ventas.php" method="post" class="d-inline">
                                <input type="hidden" name="id_ventas" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="num" value="<?php echo $numfact; ?>">
                                <input type="hidden" name="cancela" value="si">
                                <button type="submit" class="btn btn-danger btn-custom w-100" name="cancelar" 
                                        onclick="return confirm('¿Está seguro de cancelar este pedido?')">
                                    <i class="fas fa-times me-2"></i>CANCELAR PEDIDO
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <a href="ventas.php" class="btn btn-secondary btn-custom w-100">
                                <i class="fas fa-arrow-left me-2"></i>VOLVER A VENTAS
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <?php include("parts/foot.php"); ?>
</section>
</div>
</body>
</html>