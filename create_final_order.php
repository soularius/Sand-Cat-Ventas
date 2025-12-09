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

// Capturar datos del POST
$postData = Utils::capturePostData(['order_data'], true);
$orderDataJson = $postData['order_data'] ?? '';

if (empty($orderDataJson)) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos del pedido']);
    exit;
}

try {
    // Decodificar datos del pedido
    $orderData = json_decode($orderDataJson, true);
    
    if (!$orderData || !isset($orderData['products']) || empty($orderData['products'])) {
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
    
    // Conectar a base de datos
    global $miau;
    
    // Iniciar transacción
    mysqli_autocommit($miau, false);
    
    $hoy = date('Y-m-d H:i:s');
    $order_id = null;
    
    // 1. Crear el pedido principal en miau_posts
    $post_title = 'Pedido - ' . date('Y-m-d H:i:s');
    $post_content = 'Pedido creado desde el sistema de ventas';
    
    $query_post = "INSERT INTO miau_posts (
        post_author,
        post_date,
        post_date_gmt,
        post_content,
        post_title,
        post_excerpt,
        post_status,
        comment_status,
        ping_status,
        post_name,
        post_modified,
        post_modified_gmt,
        post_parent,
        guid,
        menu_order,
        post_type,
        post_mime_type,
        comment_count
    ) VALUES (
        1,
        '$hoy',
        '$hoy',
        '$post_content',
        '$post_title',
        '',
        'wc-processing',
        'closed',
        'closed',
        'order-" . time() . "',
        '$hoy',
        '$hoy',
        0,
        '',
        0,
        'shop_order',
        '',
        0
    )";
    
    if (!mysqli_query($miau, $query_post)) {
        throw new Exception('Error creando el pedido: ' . mysqli_error($miau));
    }
    
    $order_id = mysqli_insert_id($miau);
    
    // 2. Insertar metadatos del pedido
    $order_total = $orderData['total_price'] ?? 0;
    $total_items = $orderData['total_items'] ?? 0;
    
    $order_meta = [
        '_order_key' => 'wc_order_' . $order_id . '_' . time(),
        '_order_currency' => 'COP',
        '_order_total' => $order_total,
        '_order_tax' => '0',
        '_order_shipping' => '0',
        '_order_discount' => '0',
        '_cart_discount' => '0',
        '_cart_discount_tax' => '0',
        '_order_shipping_tax' => '0',
        '_order_version' => '8.0.0',
        '_prices_include_tax' => 'no',
        '_billing_country' => 'CO',
        '_shipping_country' => 'CO',
        '_payment_method' => 'manual',
        '_payment_method_title' => 'Pago Manual',
        '_created_via' => 'sistema_ventas',
        '_date_created' => time(),
        '_date_modified' => time(),
        '_customer_user' => 0,
        '_order_stock_reduced' => 'yes'
    ];
    
    // Agregar datos del cliente si existen
    if (isset($orderData['customer_data']) && is_array($orderData['customer_data'])) {
        $customer = $orderData['customer_data'];
        
        $customer_meta = [
            '_billing_first_name' => $customer['nombre1'] ?? '',
            '_billing_last_name' => $customer['nombre2'] ?? '',
            '_billing_email' => $customer['_billing_email'] ?? '',
            '_billing_phone' => $customer['_billing_phone'] ?? '',
            '_billing_address_1' => $customer['_shipping_address_1'] ?? '',
            '_billing_address_2' => $customer['_shipping_address_2'] ?? '',
            '_billing_city' => $customer['_shipping_city'] ?? '',
            '_billing_state' => $customer['_shipping_state'] ?? '',
            '_billing_postcode' => '',
            '_billing_country' => 'CO',
            '_shipping_first_name' => $customer['nombre1'] ?? '',
            '_shipping_last_name' => $customer['nombre2'] ?? '',
            '_shipping_address_1' => $customer['_shipping_address_1'] ?? '',
            '_shipping_address_2' => $customer['_shipping_address_2'] ?? '',
            '_shipping_city' => $customer['_shipping_city'] ?? '',
            '_shipping_state' => $customer['_shipping_state'] ?? '',
            '_shipping_postcode' => '',
            '_shipping_country' => 'CO'
        ];
        
        // Agregar campos personalizados
        if (isset($customer['dni'])) {
            $customer_meta['_billing_dni'] = $customer['dni'];
            $customer_meta['_shipping_dni'] = $customer['dni'];
        }
        
        if (isset($customer['_billing_neighborhood'])) {
            $customer_meta['_billing_barrio'] = $customer['_billing_neighborhood'];
            $customer_meta['_shipping_barrio'] = $customer['_billing_neighborhood'];
        }
        
        $order_meta = array_merge($order_meta, $customer_meta);
    }
    
    // Insertar metadatos del pedido
    $meta_values = [];
    foreach ($order_meta as $key => $value) {
        $value = mysqli_real_escape_string($miau, $value);
        $meta_values[] = "('$order_id', '$key', '$value')";
    }
    
    if (!empty($meta_values)) {
        $query_meta = "INSERT INTO miau_postmeta (post_id, meta_key, meta_value) VALUES " . implode(', ', $meta_values);
        
        if (!mysqli_query($miau, $query_meta)) {
            throw new Exception('Error insertando metadatos del pedido: ' . mysqli_error($miau));
        }
    }
    
    // 3. Insertar productos del pedido
    $line_total = 0;
    $item_count = 0;
    
    foreach ($orderData['products'] as $product) {
        $product_id = intval($product['id']);
        $quantity = intval($product['quantity']);
        $price = floatval($product['sale_price'] ?? $product['price']);
        $subtotal = $price * $quantity;
        $line_total += $subtotal;
        
        // Insertar item del pedido en miau_woocommerce_order_items
        $query_item = "INSERT INTO miau_woocommerce_order_items (
            order_id,
            order_item_name,
            order_item_type
        ) VALUES (
            '$order_id',
            '" . mysqli_real_escape_string($miau, $product['title']) . "',
            'line_item'
        )";
        
        if (!mysqli_query($miau, $query_item)) {
            throw new Exception('Error insertando item del pedido: ' . mysqli_error($miau));
        }
        
        $item_id = mysqli_insert_id($miau);
        
        // Insertar metadatos del item
        $item_meta = [
            '_product_id' => $product_id,
            '_variation_id' => 0,
            '_qty' => $quantity,
            '_tax_class' => '',
            '_line_subtotal' => $subtotal,
            '_line_subtotal_tax' => 0,
            '_line_total' => $subtotal,
            '_line_tax' => 0,
            '_line_tax_data' => 'a:2:{s:5:"total";a:0:{}s:8:"subtotal";a:0:{}}',
            '_reduced_stock' => $quantity
        ];
        
        $item_meta_values = [];
        foreach ($item_meta as $key => $value) {
            $value = mysqli_real_escape_string($miau, $value);
            $item_meta_values[] = "('$item_id', '$key', '$value')";
        }
        
        if (!empty($item_meta_values)) {
            $query_item_meta = "INSERT INTO miau_woocommerce_order_itemmeta (order_item_id, meta_key, meta_value) VALUES " . implode(', ', $item_meta_values);
            
            if (!mysqli_query($miau, $query_item_meta)) {
                throw new Exception('Error insertando metadatos del item: ' . mysqli_error($miau));
            }
        }
        
        // Actualizar stock del producto
        $query_stock = "UPDATE miau_postmeta SET meta_value = meta_value - $quantity 
                       WHERE post_id = $product_id AND meta_key = '_stock'";
        
        if (!mysqli_query($miau, $query_stock)) {
            Utils::logError("Error actualizando stock del producto $product_id: " . mysqli_error($miau), 'WARNING', 'create_final_order.php');
        }
        
        $item_count++;
    }
    
    // 4. Actualizar total final del pedido
    $query_update_total = "UPDATE miau_postmeta SET meta_value = '$line_total' 
                          WHERE post_id = '$order_id' AND meta_key = '_order_total'";
    
    if (!mysqli_query($miau, $query_update_total)) {
        Utils::logError("Error actualizando total del pedido: " . mysqli_error($miau), 'WARNING', 'create_final_order.php');
    }
    
    // 5. Insertar en tabla de lookup de pedidos (si existe)
    $query_lookup = "INSERT INTO miau_wc_order_stats (
        order_id,
        parent_id,
        date_created,
        date_created_gmt,
        num_items_sold,
        total_sales,
        tax_total,
        shipping_total,
        net_total,
        status,
        customer_id
    ) VALUES (
        '$order_id',
        0,
        '$hoy',
        '$hoy',
        '$total_items',
        '$line_total',
        0,
        0,
        '$line_total',
        'wc-processing',
        0
    )";
    
    // Esta tabla puede no existir en todas las instalaciones
    @mysqli_query($miau, $query_lookup);
    
    // Confirmar transacción
    mysqli_commit($miau);
    mysqli_autocommit($miau, true);
    
    // Log del pedido creado
    Utils::logError("Pedido #$order_id creado exitosamente por usuario: $username. Total: $line_total, Items: $item_count", 'INFO', 'create_final_order.php');
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'order_id' => $order_id,
        'total' => $line_total,
        'items' => $item_count
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    mysqli_rollback($miau);
    mysqli_autocommit($miau, true);
    
    Utils::logError("Error creando pedido: " . $e->getMessage(), 'ERROR', 'create_final_order.php');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
?>
