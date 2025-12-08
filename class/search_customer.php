<?php
/**
 * Búsqueda de Clientes en WooCommerce
 * Busca clientes en la base de datos de WooCommerce por documento de identidad
 */

// Cargar autoloader del sistema
require_once('autoload.php');

// Verificar que sea una petición AJAX POST usando Utils
if (!Utils::isPostRequest() || !Utils::hasPostFields(['action']) || Utils::captureValue('action', 'POST') !== 'search_customer') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Petición inválida']);
    exit;
}

// Capturar y validar el documento usando Utils
$billing_id = Utils::captureValue('billing_id', 'POST', '');

if (empty($billing_id) || strlen($billing_id) < 3) {
    echo json_encode([
        'success' => false, 
        'error' => 'Documento debe tener al menos 3 caracteres'
    ]);
    exit;
}

// Escapar el documento para evitar inyección SQL
$billing_id = mysqli_real_escape_string($miau, $billing_id);

try {
    // Buscar cliente en WooCommerce por documento (billing_id)
    // Primero buscar en postmeta el documento
    $query_customer = "
        SELECT DISTINCT pm.post_id, pm.meta_value as billing_id
        FROM miau_postmeta pm
        INNER JOIN miau_posts p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_billing_id' 
        AND pm.meta_value = '$billing_id'
        AND p.post_type = 'shop_order'
        AND p.post_status NOT IN ('trash', 'auto-draft')
        ORDER BY p.post_date DESC
        LIMIT 1
    ";
    
    $result_customer = mysqli_query($miau, $query_customer);
    
    if (!$result_customer) {
        throw new Exception('Error en la consulta: ' . mysqli_error($miau));
    }
    
    if (mysqli_num_rows($result_customer) > 0) {
        $row_customer = mysqli_fetch_assoc($result_customer);
        $order_id = $row_customer['post_id'];
        
        // Obtener todos los metadatos del cliente de esa orden
        $query_meta = "
            SELECT meta_key, meta_value 
            FROM miau_postmeta 
            WHERE post_id = '$order_id' 
            AND meta_key IN (
                '_billing_first_name', '_billing_last_name', '_billing_email', 
                '_billing_phone', '_billing_address_1', '_billing_address_2',
                '_billing_city', '_billing_state', '_billing_postcode', '_billing_country',
                '_shipping_first_name', '_shipping_last_name', '_shipping_address_1',
                '_shipping_address_2', '_shipping_city', '_shipping_state', 
                '_shipping_postcode', '_shipping_country', '_billing_id'
            )
        ";
        
        $result_meta = mysqli_query($miau, $query_meta);
        
        if (!$result_meta) {
            throw new Exception('Error obteniendo metadatos: ' . mysqli_error($miau));
        }
        
        // Procesar metadatos usando la función Utils
        $customer_data = Utils::processMetaFromResult($result_meta, [
            '_billing_first_name', '_billing_last_name', '_billing_email', 
            '_billing_phone', '_billing_address_1', '_billing_address_2',
            '_billing_city', '_billing_state', '_billing_postcode', '_billing_country',
            '_shipping_first_name', '_shipping_last_name', '_shipping_address_1',
            '_shipping_address_2', '_shipping_city', '_shipping_state', 
            '_shipping_postcode', '_shipping_country', '_billing_id'
        ]);
        
        // Formatear datos del cliente usando valores seguros
        $customer = [
            'found' => true,
            'order_id' => $order_id,
            'billing_id' => $customer_data['_billing_id'] ?? $billing_id,
            'first_name' => Utils::sanitizeInput($customer_data['_billing_first_name'] ?? ''),
            'last_name' => Utils::sanitizeInput($customer_data['_billing_last_name'] ?? ''),
            'email' => filter_var($customer_data['_billing_email'] ?? '', FILTER_SANITIZE_EMAIL),
            'phone' => Utils::sanitizeInput($customer_data['_billing_phone'] ?? ''),
            'address_1' => Utils::sanitizeInput($customer_data['_billing_address_1'] ?? ''),
            'address_2' => Utils::sanitizeInput($customer_data['_billing_address_2'] ?? ''),
            'city' => Utils::sanitizeInput($customer_data['_billing_city'] ?? ''),
            'state' => Utils::sanitizeInput($customer_data['_billing_state'] ?? ''),
            'postcode' => Utils::sanitizeInput($customer_data['_billing_postcode'] ?? ''),
            'country' => Utils::sanitizeInput($customer_data['_billing_country'] ?? ''),
            'shipping_first_name' => Utils::sanitizeInput($customer_data['_shipping_first_name'] ?? ''),
            'shipping_last_name' => Utils::sanitizeInput($customer_data['_shipping_last_name'] ?? ''),
            'shipping_address_1' => Utils::sanitizeInput($customer_data['_shipping_address_1'] ?? ''),
            'shipping_address_2' => Utils::sanitizeInput($customer_data['_shipping_address_2'] ?? ''),
            'shipping_city' => Utils::sanitizeInput($customer_data['_shipping_city'] ?? ''),
            'shipping_state' => Utils::sanitizeInput($customer_data['_shipping_state'] ?? ''),
            'shipping_postcode' => Utils::sanitizeInput($customer_data['_shipping_postcode'] ?? ''),
            'shipping_country' => Utils::sanitizeInput($customer_data['_shipping_country'] ?? '')
        ];
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'customer' => $customer,
            'message' => 'Cliente encontrado exitosamente'
        ]);
        
    } else {
        // Cliente no encontrado
        echo json_encode([
            'success' => false,
            'customer' => null,
            'message' => 'Cliente no encontrado en la base de datos de WooCommerce'
        ]);
    }
    
} catch (Exception $e) {
    // Error en la búsqueda - usar Utils para logging
    Utils::logError('Error en búsqueda de cliente: ' . $e->getMessage(), 'ERROR', 'search_customer.php');
    
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => 'No se pudo realizar la búsqueda. Intente nuevamente.'
    ]);
}

// Liberar recursos
if (isset($result_customer)) {
    mysqli_free_result($result_customer);
}
if (isset($result_meta)) {
    mysqli_free_result($result_meta);
}
?>
