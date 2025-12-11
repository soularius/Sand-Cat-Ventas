<?php
/**
 * Endpoint AJAX para búsqueda de productos usando la clase WooCommerceProducts
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar autoloader del sistema
require_once('class/autoload.php');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Capturar parámetros de búsqueda
$search_term = $_POST['search'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$filter = $_POST['filter'] ?? 'all';
$limit = (int)($_POST['limit'] ?? 20);

try {
    // Verificar conexión a la base de datos
    global $miau;
    if (!$miau) {
        throw new Exception('No hay conexión a la base de datos WordPress');
    }
    
    // Construir query base
    $query = "
        SELECT 
            p.ID as id_producto,
            p.post_title as nombre,
            p.post_content as descripcion,
            p.post_excerpt as descripcion_corta,
            p.post_name as slug,
            p.guid as guid,
            COALESCE(pm_price.meta_value, '0') as precio,
            COALESCE(pm_regular_price.meta_value, '0') as precio_regular,
            COALESCE(pm_sale_price.meta_value, '') as precio_oferta,
            COALESCE(pm_stock.meta_value, '0') as stock,
            COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
            COALESCE(pm_sku.meta_value, '') as sku
        FROM miau_posts p
        LEFT JOIN miau_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
        LEFT JOIN miau_postmeta pm_regular_price ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
        LEFT JOIN miau_postmeta pm_sale_price ON p.ID = pm_sale_price.post_id AND pm_sale_price.meta_key = '_sale_price'
        LEFT JOIN miau_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
        LEFT JOIN miau_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
        LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'";
    
    // Agregar JOIN para categorías si es necesario
    if (!empty($category_id)) {
        $query .= "
        INNER JOIN miau_term_relationships tr ON p.ID = tr.object_id
        INNER JOIN miau_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
    }
    
    $query .= "
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'";
    
    // Condiciones de búsqueda
    $conditions = [];
    
    // Filtro por categoría
    if (!empty($category_id)) {
        $category_id_escaped = mysqli_real_escape_string($miau, $category_id);
        $conditions[] = "tt.term_taxonomy_id = '$category_id_escaped'";
    }
    
    // Filtro por término de búsqueda
    if (!empty($search_term)) {
        $search_term_escaped = mysqli_real_escape_string($miau, $search_term);
        $conditions[] = "(
            p.post_title LIKE '%$search_term_escaped%' 
            OR pm_sku.meta_value LIKE '%$search_term_escaped%'
            OR p.post_content LIKE '%$search_term_escaped%'
        )";
    }
    
    // Agregar condiciones a la query
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    $query .= "
        ORDER BY p.post_title ASC
        LIMIT $limit";
    
    // Log de la query para debugging
    Utils::logError("Query ejecutada - Search: '$search_term', Category: '$category_id', Filter: '$filter'", 'INFO', 'search_products_ajax.php');
    
    $result = mysqli_query($miau, $query);
    if (!$result) {
        throw new Exception('Error en consulta de productos: ' . mysqli_error($miau));
    }
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Formatear datos
        $row['precio'] = floatval($row['precio'] ?? 0);
        $row['precio_regular'] = floatval($row['precio_regular'] ?? 0);
        $row['precio_oferta'] = !empty($row['precio_oferta']) ? floatval($row['precio_oferta']) : 0;
        $row['stock'] = intval($row['stock'] ?? 0);
        $row['en_stock'] = ($row['estado_stock'] === 'instock');
        $row['sku'] = $row['sku'] ?? '';
        $row['descripcion_corta'] = $row['descripcion_corta'] ?? '';
        $row['descripcion'] = $row['descripcion'] ?? '';
        
        $products[] = $row;
    }
    
    // Función simple para obtener imagen
    function getSimpleProductImage($product_id) {
        global $miau;
        
        $query = "SELECT meta_value as thumbnail_id FROM miau_postmeta WHERE post_id = $product_id AND meta_key = '_thumbnail_id'";
        $result = mysqli_query($miau, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
        }
        
        $row = mysqli_fetch_assoc($result);
        $thumbnail_id = $row['thumbnail_id'];
        
        if (!$thumbnail_id) {
            return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
        }
        
        $image_query = "SELECT guid FROM miau_posts WHERE ID = $thumbnail_id AND post_type = 'attachment'";
        $image_result = mysqli_query($miau, $image_query);
        
        if ($image_result && $image_row = mysqli_fetch_assoc($image_result)) {
            return $image_row['guid'];
        }
        
        return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
    }
    
    // Procesar productos para el frontend
    $processed_products = [];
    foreach ($products as $product) {
        // Aplicar filtros
        $include_product = true;
        switch ($filter) {
            case 'available':
                $include_product = $product['en_stock'];
                break;
            case 'featured':
                // Para productos destacados, podríamos agregar lógica adicional
                $include_product = true;
                break;
        }
        
        if (!$include_product) continue;
        
        // Obtener imagen del producto
        $image_url = getSimpleProductImage($product['id_producto']);
        
        // Construir permalink del producto
        $base_url = env('WOOCOMMERCE_BASE_URL'); // URL base de WordPress
        $permalink = $base_url . '/producto/' . $product['slug'] . '/';
        
        // Formatear precio
        $price = $product['precio'];
        $regular_price = $product['precio_regular'];
        $sale_price = $product['precio_oferta'];
        
        $has_sale = ($sale_price > 0 && $sale_price < $regular_price);
        $display_price = $has_sale ? $sale_price : $price;
        
        $processed_products[] = [
            'id' => (int)$product['id_producto'],
            'title' => $product['nombre'],
            'short_description' => $product['descripcion_corta'] ?? '',
            'sku' => $product['sku'] ?? '',
            'permalink' => $permalink,
            'price' => number_format($display_price, 0, ',', '.'),
            'regular_price' => $regular_price > 0 ? number_format($regular_price, 0, ',', '.') : null,
            'sale_price' => $sale_price > 0 ? number_format($sale_price, 0, ',', '.') : null,
            'has_sale' => $has_sale,
            'image_url' => $image_url,
            'stock_quantity' => (int)$product['stock'],
            'is_available' => $product['en_stock'],
            'availability_text' => $product['en_stock'] ? 'Disponible' : 'Agotado',
            'stock_status_class' => $product['en_stock'] ? 'text-success' : 'text-danger',
            'availability_icon' => $product['en_stock'] ? 'fas fa-check-circle' : 'fas fa-times-circle',
            'price_badge_class' => $has_sale ? 'bg-danger' : 'bg-primary',
            'card_border_class' => $product['en_stock'] ? 'border-success' : 'border-warning'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $processed_products,
        'total' => count($processed_products),
        'showing' => count($processed_products),
        'search_term' => $search_term,
        'category_id' => $category_id,
        'filter' => $filter
    ]);
    
} catch (Exception $e) {
    Utils::logError("Error en search_products_ajax.php: " . $e->getMessage(), 'ERROR', 'search_products_ajax.php');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
