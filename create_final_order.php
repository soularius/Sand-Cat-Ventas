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

header('Content-Type: application/json; charset=utf-8');

// Capturar datos del POST (IMPORTANTE: no sanitizar JSON, porque sanitizeInput aplica htmlspecialchars y rompe el payload)
$postData = Utils::capturePostData(['order_data'], false);
$orderDataJson = $postData['order_data'] ?? '';

// Fallback por si capturePostData no lo trae (edge cases)
if ($orderDataJson === '' && isset($_POST['order_data'])) {
    $orderDataJson = $_POST['order_data'];
}

if (empty($orderDataJson)) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos del pedido']);
    exit;
}

try {
    // Decodificar datos del pedido
    $orderData = json_decode($orderDataJson, true);
    if ($orderData === null && json_last_error() !== JSON_ERROR_NONE) {
        Utils::logError(
            'JSON inválido en create_final_order.php. json_last_error=' . json_last_error_msg() .
                ' | payload_preview=' . substr((string)$orderDataJson, 0, 500),
            'ERROR',
            'create_final_order.php'
        );
        echo json_encode(['success' => false, 'message' => 'Datos del pedido inválidos (JSON)']);
        exit;
    }

    if (!$orderData || !isset($orderData['products']) || empty($orderData['products'])) {
        Utils::logError(
            'Estructura inválida en create_final_order.php: faltan productos. keys=' . implode(',', array_keys((array)$orderData)),
            'ERROR',
            'create_final_order.php'
        );
        echo json_encode(['success' => false, 'message' => 'Datos del pedido inválidos']);
        exit;
    }

    // Obtener datos del usuario actual
    $row_usuario = getCurrentUserFromDB();
    if (!$row_usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $user_id = $row_usuario['id_ingreso'] ?? 0;
    $username = $row_usuario['elnombre'] ?? '';

    // Crear pedido real en WooCommerce/WordPress usando clase dedicada
    require_once('class/woocommerce_orders.php');
    $ordersService = new WooCommerceOrders();

    // La nueva implementación devuelve {success: bool, order_id?, total?, error?, debug?}
    $result = $ordersService->createOrderFromSalesData($orderData, 'prod');

    if (!empty($result['success'])) {
        Utils::logError(
            "Pedido #" . ($result['order_id'] ?? '') . " creado exitosamente por usuario: $username.",
            'INFO',
            'create_final_order.php'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'order_id' => $result['order_id'] ?? null,
            'total' => $result['total'] ?? 0
        ]);
        exit;
    }

    $errorMessage = $result['error'] ?? 'Error creando el pedido';
    Utils::logError(
        "Error creando pedido (resultado): " . $errorMessage . " | user=" . $username,
        'ERROR',
        'create_final_order.php'
    );

    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
    exit;
} catch (Exception $e) {
    Utils::logError("Error creando pedido: " . $e->getMessage(), 'ERROR', 'create_final_order.php');

    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
