<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación
requireLogin('facturacion.php');

// Verificar que sea una petición POST
if (!Utils::isPostRequest()) {
    header('Location: inicio.php');
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

    // Verificar si viene un order_id existente para edición
    $existingOrderId = null;
    if (isset($orderData['form_data']['_order_id']) && !empty($orderData['form_data']['_order_id'])) {
        $candidateOrderId = (int)$orderData['form_data']['_order_id'];
        
        // Verificar que la orden realmente existe usando método público
        if ($ordersService->orderExists($candidateOrderId)) {
            $existingOrderId = $candidateOrderId;
        }
        
        Utils::logError(
            "Detectado order_id en datos: $candidateOrderId " . ($existingOrderId ? "(existe)" : "(no existe)"),
            'INFO',
            'create_final_order.php'
        );
    }

    // Si hay orden existente, usar updateExistingOrder directamente
    if ($existingOrderId) {
        $debug = ['mode' => 'edit', 'steps' => [], 'warnings' => []];
        $updateResult = $ordersService->updateExistingOrder($existingOrderId, $orderData, $debug);
        
        if ($updateResult['success']) {
            $result = [
                'success' => true,
                'order_id' => $existingOrderId,
                'total' => $updateResult['total'],
                'message' => 'Orden actualizada exitosamente',
                'debug' => $debug
            ];
        } else {
            $result = [
                'success' => false,
                'error' => 'Error actualizando orden: ' . $updateResult['error'],
                'debug' => $debug
            ];
        }
    } else {
        // La nueva implementación devuelve {success: bool, order_id?, total?, error?, debug?}
        $result = $ordersService->createOrderFromSalesData($orderData, 'prod');
    }

    if (!empty($result['success'])) {
        // Generar factura automáticamente en BD ventassc (tabla facturas)
        $createdFactura = '';
        try {
            $orderId = (int)($result['order_id'] ?? 0);
            if ($orderId > 0) {
                // Evitar duplicados: si ya hay factura activa para la orden, reutilizarla
                $checkSql = "SELECT factura FROM facturas WHERE id_order = ? AND estado = 'a' LIMIT 1";
                $stmtCheck = mysqli_prepare($sandycat, $checkSql);
                if ($stmtCheck) {
                    mysqli_stmt_bind_param($stmtCheck, 'i', $orderId);
                    mysqli_stmt_execute($stmtCheck);
                    $res = mysqli_stmt_get_result($stmtCheck);
                    if ($res && ($row = mysqli_fetch_assoc($res)) && !empty($row['factura'])) {
                        $createdFactura = (string)$row['factura'];
                    }
                    mysqli_stmt_close($stmtCheck);
                }

                if ($createdFactura === '') {
                    // Obtener siguiente número de factura usando SERIE_NUMERO_FACTURA
                    $nextFactura = Utils::getNextInvoiceNumber();
                    
                    // Verificar que el número no exista (por seguridad)
                    if (Utils::invoiceNumberExists($nextFactura)) {
                        Utils::logError("Número de factura $nextFactura ya existe, generando nuevo número", 'WARNING', 'create_final_order.php');
                        $nextFactura = Utils::getNextInvoiceNumber();
                    }

                    $insSql = "INSERT INTO facturas (id_order, factura, estado) VALUES (?, ?, 'a')";
                    $stmtIns = mysqli_prepare($sandycat, $insSql);
                    if ($stmtIns) {
                        mysqli_stmt_bind_param($stmtIns, 'is', $orderId, $nextFactura);
                        mysqli_stmt_execute($stmtIns);
                        mysqli_stmt_close($stmtIns);
                        $createdFactura = $nextFactura;
                        
                        Utils::logError("Factura #$nextFactura creada automáticamente para pedido #$orderId", 'INFO', 'create_final_order.php');
                    }
                }
            }
        } catch (Exception $e) {
            Utils::logError('Error generando factura automática: ' . $e->getMessage(), 'ERROR', 'create_final_order.php');
        }

        Utils::logError(
            "Pedido #" . ($result['order_id'] ?? '') . " creado exitosamente por usuario: $username.",
            'INFO',
            'create_final_order.php'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Pedido creado exitosamente',
            'order_id' => $result['order_id'] ?? null,
            'total' => $result['total'] ?? 0,
            'factura' => $createdFactura
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
