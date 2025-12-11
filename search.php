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
$postData = Utils::capturePostData(['_order_id', 'search'], true);
$order_id = $postData['_order_id'] ?? '';
$search = $postData['search'] ?? '';

if (empty($search) || strlen($search) < 3) {
    echo json_encode(['success' => false, 'message' => 'Búsqueda debe tener al menos 3 caracteres']);
    exit;
}

/**
 * Buscar productos en WooCommerce con datos completos
 * @return array - Array de productos con metadatos completos
 */
function searchProducts($search) {
    global $miau;
    
    $search = mysqli_real_escape_string($miau, $search);
    
    // Query para obtener productos con sus metadatos principales
    $query = "SELECT 
        p.ID,
        p.post_title,
        p.post_content,
        p.post_excerpt,
        p.post_status,
        GROUP_CONCAT(
            CONCAT(pm.meta_key, ':', pm.meta_value) 
            SEPARATOR '|'
        ) as product_meta
    FROM miau_posts p
    LEFT JOIN miau_postmeta pm ON p.ID = pm.post_id
    WHERE p.post_status = 'publish'
        AND p.post_type = 'product'
        AND p.post_title LIKE '%$search%'
        AND pm.meta_key IN ('_stock', '_price', '_regular_price', '_sale_price', '_sku', '_weight', '_stock_status')
    GROUP BY p.ID
    ORDER BY p.post_title ASC
    LIMIT 20";
    
    $result = mysqli_query($miau, $query);
    $products = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $product = [
                'id' => $row['ID'],
                'title' => $row['post_title'],
                'content' => $row['post_content'],
                'excerpt' => $row['post_excerpt'],
                'status' => $row['post_status'],
                'meta' => []
            ];
            
            // Procesar metadatos
            if ($row['product_meta']) {
                $meta_pairs = explode('|', $row['product_meta']);
                foreach ($meta_pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if (count($parts) == 2) {
                        $product['meta'][$parts[0]] = $parts[1];
                    }
                }
            }
            
            // Valores por defecto para campos importantes
            $product['stock'] = $product['meta']['_stock'] ?? '0';
            $product['price'] = $product['meta']['_price'] ?? '0';
            $product['regular_price'] = $product['meta']['_regular_price'] ?? '0';
            $product['sale_price'] = $product['meta']['_sale_price'] ?? '';
            $product['sku'] = $product['meta']['_sku'] ?? '';
            $product['weight'] = $product['meta']['_weight'] ?? '';
            $product['stock_status'] = $product['meta']['_stock_status'] ?? 'outofstock';
            
            // Si no hay SKU, mostrar --
            if (empty($product['sku'])) {
                $product['sku'] = '--';
                Utils::logError("Producto sin SKU: ID={$product['id']}, mostrando '--'", 'INFO', 'search.php');
            } else {
                Utils::logError("Producto con SKU: ID={$product['id']}, SKU={$product['sku']}", 'INFO', 'search.php');
            }
            
            // Determinar disponibilidad
            $product['available'] = ($product['stock_status'] === 'instock' && intval($product['stock']) > 0);
            
            $products[] = $product;
        }
        mysqli_free_result($result);
    }
    
    return $products;
}

try {
    $products = searchProducts($search);
    
    Utils::logError("Búsqueda de productos: '$search' - " . count($products) . " resultados", 'INFO', 'search.php');
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'search_term' => $search,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    Utils::logError("Error en búsqueda de productos: " . $e->getMessage(), 'ERROR', 'search.php');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>