<?php
// Establecer headers para JSON y CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 1. Cargar autoloader del sistema PRIMERO
require_once('class/autoload.php');

// Logging inicial para debugging DESPUÉS de cargar Utils
Utils::logError("get_order_details.php: Iniciando procesamiento", 'INFO', 'get_order_details.php');

// 2. Cargar login handler centralizado  
require_once('parts/login_handler.php');

// 3. Verificar autenticación - pero no redirigir en AJAX
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

// 4. Cargar clase WooCommerce Orders
require_once('class/woocommerce_orders.php');

// 5. Verificar que sea petición POST
if (!Utils::isPostRequest()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// 6. Obtener y validar order_id
$order_id = Utils::captureValue('order_id', 'POST', '');
Utils::logError("get_order_details.php: order_id recibido = " . $order_id, 'INFO', 'get_order_details.php');

if (empty($order_id) || !is_numeric($order_id)) {
    Utils::logError("get_order_details.php: order_id inválido", 'ERROR', 'get_order_details.php');
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit();
}

try {
    // 7. Inicializar clase de órdenes WooCommerce
    Utils::logError("get_order_details.php: Inicializando WooCommerceOrders", 'INFO', 'get_order_details.php');
    $wc_orders = new WooCommerceOrders();
    
    // 8. Obtener detalles completos del pedido
    Utils::logError("get_order_details.php: Obteniendo detalles para order_id = " . $order_id, 'INFO', 'get_order_details.php');
    $order_details = $wc_orders->getOrderDetails((int)$order_id);
    
    if (empty($order_details)) {
        Utils::logError("get_order_details.php: Pedido no encontrado para ID = " . $order_id, 'ERROR', 'get_order_details.php');
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit();
    }
    
    Utils::logError("get_order_details.php: Detalles obtenidos exitosamente", 'INFO', 'get_order_details.php');
    
    // 9. Formatear datos para el frontend con valores por defecto
    $formatted_data = [
        'ID' => $order_details['ID'] ?? $order_id,
        'post_date' => !empty($order_details['post_date']) ? date('d/m/Y H:i', strtotime($order_details['post_date'])) : date('d/m/Y H:i'),
        'post_status' => $order_details['post_status'] ?? 'wc-processing',
        'billing_first_name' => $order_details['billing_first_name'] ?? '',
        'billing_last_name' => $order_details['billing_last_name'] ?? '',
        'billing_email' => $order_details['billing_email'] ?? '',
        'billing_phone' => $order_details['billing_phone'] ?? '',
        'billing_address_1' => $order_details['billing_address_1'] ?? '',
        'billing_address_2' => $order_details['billing_address_2'] ?? '',
        'billing_city' => $order_details['billing_city'] ?? '',
        'billing_state' => $order_details['billing_state'] ?? '',
        'billing_country' => $order_details['billing_country'] ?? 'CO',
        'billing_barrio' => $order_details['billing_barrio'] ?? '',
        'payment_method' => $order_details['payment_method'] ?? '',
        'payment_method_title' => $order_details['payment_method_title'] ?? $order_details['payment_method'] ?? 'N/A',
        'shipping_cost' => $order_details['shipping_cost'] ?? '0',
        'total' => $order_details['total'] ?? '0',
        'has_invoice' => $order_details['has_invoice'] ?? false,
        'items' => []
    ];
    
    // 10. Formatear productos si existen
    if (!empty($order_details['items'])) {
        foreach ($order_details['items'] as $item) {
            $formatted_data['items'][] = [
                'order_item_name' => $item['order_item_name'] ?? $item['product_name'] ?? 'Producto sin nombre',
                'product_qty' => $item['product_qty'] ?? $item['qty'] ?? $item['quantity'] ?? 1,
                'line_total' => $item['line_total'] ?? $item['total'] ?? 0,
                'subtotal_linea' => $item['subtotal_linea'] ?? $item['line_subtotal'] ?? 0,
                'sku' => $item['sku'] ?? $item['product_sku'] ?? '',
                
                // Campos de descuento necesarios para ventas.php
                'regular_price' => $item['regular_price'] ?? 0,
                'sale_price' => $item['sale_price'] ?? 0,
                '_regular_price' => $item['_regular_price'] ?? $item['regular_price'] ?? 0,
                '_sale_price' => $item['_sale_price'] ?? $item['sale_price'] ?? 0,
                'has_discount' => $item['has_discount'] ?? false,
                'unit_price' => $item['unit_price'] ?? 0
            ];
        }
    }
    
    // 11. Logging para debugging
    Utils::logError("Detalles de pedido obtenidos: ID=$order_id, Status={$formatted_data['post_status']}, HasInvoice={$formatted_data['has_invoice']}", 'INFO', 'get_order_details.php');
    Utils::logError("Raw order_details post_status: " . ($order_details['post_status'] ?? 'NULL'), 'INFO', 'get_order_details.php');
    Utils::logError("Raw order_details has_invoice: " . ($order_details['has_invoice'] ? 'TRUE' : 'FALSE'), 'INFO', 'get_order_details.php');
    
    // 12. Retornar respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $formatted_data
    ]);
    
} catch (Exception $e) {
    // 13. Manejo de errores
    Utils::logError("Error obteniendo detalles del pedido $order_id: " . $e->getMessage(), 'ERROR', 'get_order_details.php');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>
