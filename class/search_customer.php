<?php
/**
 * Búsqueda de Clientes en WooCommerce por DNI/Cédula
 * Busca clientes en WordPress user_meta usando el plugin WooCommerce Custom Address Fields
 * 
 * Compatible con:
 * - Plugin WooCommerce Custom Address Fields v1.0.0
 * - WordPress user_meta system
 * 
 * Campos soportados:
 * - billing_dni / shipping_dni (DNI/Cédula en user_meta)
 * - billing_barrio / shipping_barrio (Barrio en user_meta)
 * 
 * IMPORTANTE: Busca en miau_usermeta, NO en miau_postmeta
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
    // Buscar cliente por DNI en user_meta (según plugin WooCommerce Custom Address Fields)
    // El plugin guarda el DNI en billing_dni y shipping_dni en user_meta
    $query_customer = "
        SELECT DISTINCT u.ID as user_id, um.meta_value as billing_dni, u.user_email, u.display_name
        FROM miau_users u
        INNER JOIN miau_usermeta um ON u.ID = um.user_id
        WHERE um.meta_key IN ('billing_dni', 'shipping_dni')
        AND um.meta_value = '$billing_id'
        ORDER BY u.ID DESC
        LIMIT 1
    ";
    
    // Log de la consulta SQL para depuración
    Utils::logError("SQL Query DNI Search: " . $query_customer, 'DEBUG', 'search_customer.php');
    Utils::logError("Searching for DNI: " . $billing_id, 'DEBUG', 'search_customer.php');
    
    $result_customer = mysqli_query($miau, $query_customer);
    
    if (!$result_customer) {
        Utils::logError("SQL Error: " . mysqli_error($miau), 'ERROR', 'search_customer.php');
        throw new Exception('Error en la consulta: ' . mysqli_error($miau));
    }
    
    $num_rows = mysqli_num_rows($result_customer);
    Utils::logError("Query returned $num_rows rows", 'DEBUG', 'search_customer.php');
    
    if (mysqli_num_rows($result_customer) > 0) {
        $row_customer = mysqli_fetch_assoc($result_customer);
        $user_id = $row_customer['user_id'];
        $user_email = $row_customer['user_email'];
        $display_name = $row_customer['display_name'];
        
        Utils::logError("Cliente encontrado - User ID: $user_id, Email: $user_email", 'INFO', 'search_customer.php');
        
        // Obtener todos los metadatos del usuario (billing y shipping)
        $meta_keys = "
            'billing_first_name', 'billing_last_name', 'billing_email', 
            'billing_phone', 'billing_address_1', 'billing_address_2',
            'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_barrio', 'billing_dni',
            'shipping_first_name', 'shipping_last_name', 'shipping_address_1',
            'shipping_address_2', 'shipping_city', 'shipping_state', 
            'shipping_postcode', 'shipping_country', 'shipping_barrio', 'shipping_dni'
        ";
        
        $query_meta = "
            SELECT meta_key, meta_value
            FROM miau_usermeta 
            WHERE user_id = '$user_id' 
            AND meta_key IN ($meta_keys)
        ";
        
        // Log de la consulta de metadatos
        Utils::logError("SQL Query User Metadata: " . $query_meta, 'DEBUG', 'search_customer.php');
        
        $result_meta = mysqli_query($miau, $query_meta);
        
        if (!$result_meta) {
            Utils::logError("User Metadata SQL Error: " . mysqli_error($miau), 'ERROR', 'search_customer.php');
            throw new Exception('Error obteniendo metadatos del usuario: ' . mysqli_error($miau));
        }
        
        $meta_rows = mysqli_num_rows($result_meta);
        Utils::logError("User metadata query returned $meta_rows rows", 'DEBUG', 'search_customer.php');
        
        // Procesar metadatos usando la función Utils (sin prefijo _ para user_meta)
        $customer_data = Utils::processMetaFromResult($result_meta, [
            'billing_first_name', 'billing_last_name', 'billing_email', 
            'billing_phone', 'billing_address_1', 'billing_address_2',
            'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_barrio', 'billing_dni',
            'shipping_first_name', 'shipping_last_name', 'shipping_address_1',
            'shipping_address_2', 'shipping_city', 'shipping_state', 
            'shipping_postcode', 'shipping_country', 'shipping_barrio', 'shipping_dni'
        ]);
        
        // Formatear datos del cliente usando valores seguros (user_meta sin prefijo _)
        $customer = [
            'found' => true,
            'user_id' => $user_id,
            'dni' => Utils::sanitizeInput($customer_data['billing_dni'] ?? $billing_id),
            'billing_id' => Utils::sanitizeInput($customer_data['billing_dni'] ?? $billing_id), // Mantener compatibilidad
            'first_name' => Utils::sanitizeInput($customer_data['billing_first_name'] ?? ''),
            'last_name' => Utils::sanitizeInput($customer_data['billing_last_name'] ?? ''),
            'email' => filter_var($customer_data['billing_email'] ?? $user_email, FILTER_SANITIZE_EMAIL),
            'phone' => Utils::sanitizeInput($customer_data['billing_phone'] ?? ''),
            'address_1' => Utils::sanitizeInput($customer_data['billing_address_1'] ?? ''),
            'address_2' => Utils::sanitizeInput($customer_data['billing_address_2'] ?? ''),
            'city' => Utils::sanitizeInput($customer_data['billing_city'] ?? ''),
            'state' => Utils::sanitizeInput($customer_data['billing_state'] ?? ''),
            'postcode' => Utils::sanitizeInput($customer_data['billing_postcode'] ?? ''),
            'country' => Utils::sanitizeInput($customer_data['billing_country'] ?? ''),
            'barrio' => Utils::sanitizeInput($customer_data['billing_barrio'] ?? ''),
            'shipping_first_name' => Utils::sanitizeInput($customer_data['shipping_first_name'] ?? ''),
            'shipping_last_name' => Utils::sanitizeInput($customer_data['shipping_last_name'] ?? ''),
            'shipping_address_1' => Utils::sanitizeInput($customer_data['shipping_address_1'] ?? ''),
            'shipping_address_2' => Utils::sanitizeInput($customer_data['shipping_address_2'] ?? ''),
            'shipping_city' => Utils::sanitizeInput($customer_data['shipping_city'] ?? ''),
            'shipping_state' => Utils::sanitizeInput($customer_data['shipping_state'] ?? ''),
            'shipping_postcode' => Utils::sanitizeInput($customer_data['shipping_postcode'] ?? ''),
            'shipping_country' => Utils::sanitizeInput($customer_data['shipping_country'] ?? ''),
            'shipping_barrio' => Utils::sanitizeInput($customer_data['shipping_barrio'] ?? ''),
            'shipping_dni' => Utils::sanitizeInput($customer_data['shipping_dni'] ?? '')
        ];
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'customer' => $customer,
            'message' => 'Cliente encontrado exitosamente'
        ]);
        
    } else {
        // Cliente no encontrado
        Utils::logError("Cliente no encontrado para DNI: " . $billing_id, 'INFO', 'search_customer.php');
        
        // Verificar si existen registros con billing_dni en user_meta
        $check_query = "SELECT COUNT(*) as total FROM miau_usermeta WHERE meta_key IN ('billing_dni', 'shipping_dni')";
        $check_result = mysqli_query($miau, $check_query);
        if ($check_result) {
            $check_row = mysqli_fetch_assoc($check_result);
            Utils::logError("Total records with billing_dni/shipping_dni in user_meta: " . $check_row['total'], 'DEBUG', 'search_customer.php');
        }
        
        // Verificar si existe el DNI con espacios o caracteres adicionales
        $similar_query = "SELECT meta_key, meta_value FROM miau_usermeta WHERE meta_key IN ('billing_dni', 'shipping_dni') AND meta_value LIKE '%$billing_id%' LIMIT 5";
        $similar_result = mysqli_query($miau, $similar_query);
        if ($similar_result && mysqli_num_rows($similar_result) > 0) {
            Utils::logError("Similar DNI values found in user_meta:", 'DEBUG', 'search_customer.php');
            while ($similar_row = mysqli_fetch_assoc($similar_result)) {
                Utils::logError("  - " . $similar_row['meta_key'] . ": '" . $similar_row['meta_value'] . "'", 'DEBUG', 'search_customer.php');
            }
        }
        
        // Verificar también en postmeta (pedidos) por si acaso
        $order_query = "SELECT COUNT(*) as total FROM miau_postmeta WHERE meta_key = '_billing_dni' AND meta_value = '$billing_id'";
        $order_result = mysqli_query($miau, $order_query);
        if ($order_result) {
            $order_row = mysqli_fetch_assoc($order_result);
            Utils::logError("Records found with _billing_dni in orders (postmeta): " . $order_row['total'], 'DEBUG', 'search_customer.php');
        }
        
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
