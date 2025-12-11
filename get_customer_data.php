<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación
requireLogin('facturacion.php');

// Verificar que sea una petición POST
if (!Utils::isPostRequest()) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Capturar order_id del POST
$order_id = Utils::captureValue('order_id', 'POST', '');

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Order ID requerido']);
    exit;
}

try {
    global $miau;
    
    // Buscar datos del cliente asociados al pedido
    $query = "SELECT pm.meta_key, pm.meta_value 
              FROM miau_postmeta pm 
              WHERE pm.post_id = ? 
              AND pm.meta_key IN (
                  '_billing_first_name', '_billing_last_name', '_billing_email', '_billing_phone',
                  '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state',
                  '_billing_neighborhood', '_billing_dni', '_shipping_first_name', '_shipping_last_name',
                  '_shipping_address_1', '_shipping_address_2', '_shipping_city', '_shipping_state',
                  '_shipping_neighborhood', '_shipping_dni'
              )";
    
    $stmt = mysqli_prepare($miau, $query);
    mysqli_stmt_bind_param($stmt, 's', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $customer_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customer_data[$row['meta_key']] = $row['meta_value'];
    }
    
    mysqli_stmt_close($stmt);
    
    if (empty($customer_data)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No se encontraron datos del cliente para este pedido'
        ]);
        exit;
    }
    
    // Formatear datos del cliente para el frontend
    $formatted_customer = [
        'order_id' => $order_id,
        'nombre1' => $customer_data['_billing_first_name'] ?? $customer_data['_shipping_first_name'] ?? '',
        'nombre2' => $customer_data['_billing_last_name'] ?? $customer_data['_shipping_last_name'] ?? '',
        'dni' => $customer_data['_billing_dni'] ?? $customer_data['_shipping_dni'] ?? '',
        'billing_id' => $customer_data['_billing_dni'] ?? $customer_data['_shipping_dni'] ?? '',
        '_billing_email' => $customer_data['_billing_email'] ?? '',
        '_billing_phone' => $customer_data['_billing_phone'] ?? '',
        '_shipping_address_1' => $customer_data['_shipping_address_1'] ?? $customer_data['_billing_address_1'] ?? '',
        '_shipping_address_2' => $customer_data['_shipping_address_2'] ?? $customer_data['_billing_address_2'] ?? '',
        '_shipping_city' => $customer_data['_shipping_city'] ?? $customer_data['_billing_city'] ?? '',
        '_shipping_state' => $customer_data['_shipping_state'] ?? $customer_data['_billing_state'] ?? '',
        '_billing_neighborhood' => $customer_data['_billing_neighborhood'] ?? $customer_data['_shipping_neighborhood'] ?? ''
    ];
    
    echo json_encode([
        'success' => true,
        'customer' => $formatted_customer,
        'message' => 'Datos del cliente cargados correctamente'
    ]);
    
} catch (Exception $e) {
    Utils::logError("Error cargando datos del cliente para order_id $order_id: " . $e->getMessage(), 'ERROR', 'get_customer_data.php');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>
