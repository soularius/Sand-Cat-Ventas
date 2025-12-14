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
    
    // Construir query base - INCLUYE PRODUCTOS Y VARIACIONES
    $query = "
        SELECT 
            p.ID as id_producto,
            p.post_parent as producto_padre_id,
            p.post_type,
            CASE 
                WHEN p.post_type = 'product_variation' THEN COALESCE(parent.post_title, p.post_title)
                ELSE p.post_title 
            END as nombre,
            p.post_content as descripcion,
            p.post_excerpt as descripcion_corta,
            CASE 
                WHEN p.post_type = 'product_variation' THEN parent.post_name
                ELSE p.post_name 
            END as slug,
            p.guid as guid,
            COALESCE(pm_price.meta_value, '0') as precio,
            COALESCE(pm_regular_price.meta_value, '0') as precio_regular,
            COALESCE(pm_sale_price.meta_value, '') as precio_oferta,
            COALESCE(pm_stock.meta_value, '0') as stock,
            COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
            COALESCE(pm_sku.meta_value, '') as sku,
            
            -- Datos específicos de variaciones
            CASE 
                WHEN p.post_type = 'product_variation' THEN p.ID
                ELSE NULL 
            END as variation_id,
            CASE 
                WHEN p.post_type = 'product_variation' THEN p.post_parent
                ELSE p.ID 
            END as product_id
            
        FROM miau_posts p
        LEFT JOIN miau_posts parent ON p.post_parent = parent.ID AND p.post_type = 'product_variation'
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
        WHERE p.post_status = 'publish'
        AND (
            -- Productos simples (sin variaciones)
            (p.post_type = 'product' AND p.ID NOT IN (
                SELECT DISTINCT post_parent 
                FROM miau_posts 
                WHERE post_type = 'product_variation' 
                AND post_status = 'publish'
                AND post_parent IS NOT NULL
            ))
            OR
            -- Solo variaciones (no productos padre)
            p.post_type = 'product_variation'
        )";
    
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
    
    // Función para obtener atributos de variación
    function getVariationAttributes($variation_id) {
        global $miau;
        
        $query = "SELECT meta_key, meta_value 
                 FROM miau_postmeta 
                 WHERE post_id = $variation_id 
                 AND meta_key LIKE 'attribute_%'";
        
        $result = mysqli_query($miau, $query);
        $attributes = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $attr_name = str_replace('attribute_', '', $row['meta_key']);
                $attributes[$attr_name] = $row['meta_value'];
            }
        }
        
        return $attributes;
    }
    
    // Función para construir label de variación
    function buildVariationLabel($attributes) {
        if (empty($attributes)) {
            return '';
        }
        
        $labels = [];
        foreach ($attributes as $name => $value) {
            if (!empty($value)) {
                // Limpiar nombre del atributo (quitar pa_ si existe)
                $clean_name = str_replace('pa_', '', $name);
                $clean_name = ucfirst(str_replace('_', ' ', $clean_name));
                $labels[] = "$clean_name: $value";
            }
        }
        
        return implode(', ', $labels);
    }
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Formatear datos básicos
        $row['precio'] = floatval($row['precio'] ?? 0);
        $row['precio_regular'] = floatval($row['precio_regular'] ?? 0);
        $row['precio_oferta'] = !empty($row['precio_oferta']) ? floatval($row['precio_oferta']) : 0;
        $row['stock'] = intval($row['stock'] ?? 0);
        $row['en_stock'] = ($row['estado_stock'] === 'instock');
        $row['sku'] = $row['sku'] ?? '';
        $row['descripcion_corta'] = $row['descripcion_corta'] ?? '';
        $row['descripcion'] = $row['descripcion'] ?? '';
        
        // Datos de variación
        $row['variation_id'] = $row['variation_id'] ? intval($row['variation_id']) : null;
        $row['product_id'] = intval($row['product_id']);
        $row['es_variacion'] = ($row['post_type'] === 'product_variation');
        
        // Si es variación, obtener atributos y label
        if ($row['es_variacion'] && $row['variation_id']) {
            $row['variation_attributes'] = getVariationAttributes($row['variation_id']);
            $row['variation_label'] = buildVariationLabel($row['variation_attributes']);
        } else {
            $row['variation_attributes'] = null;
            $row['variation_label'] = '';
        }
        
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
        
        // Obtener imagen del producto (usar product_id para variaciones)
        $image_product_id = $product['es_variacion'] ? $product['product_id'] : $product['id_producto'];
        $image_url = getSimpleProductImage($image_product_id);
        
        // Construir permalink del producto (usar slug del padre para variaciones)
        $base_url = env('WOOCOMMERCE_BASE_URL'); // URL base de WordPress
        $permalink = $base_url . '/producto/' . $product['slug'] . '/';
        
        // Formatear precio
        $price = $product['precio'];
        $regular_price = $product['precio_regular'];
        $sale_price = $product['precio_oferta'];
        
        $has_sale = ($sale_price > 0 && $sale_price < $regular_price);
        $display_price = $has_sale ? $sale_price : $price;
        
        // Construir título con variación
        $title = $product['nombre'];
        if ($product['es_variacion'] && !empty($product['variation_label'])) {
            $title .= ' - ' . $product['variation_label'];
        }
        
        $processed_products[] = [
            // IDs principales
            'id' => (int)$product['id_producto'],                    // ID único (variación o producto)
            'product_id' => (int)$product['product_id'],             // ID del producto padre
            'variation_id' => $product['variation_id'],              // ID de variación (null si es producto)
            
            // Información básica
            'title' => $title,
            'short_description' => $product['descripcion_corta'] ?? '',
            'sku' => $product['sku'] ?? '',
            'permalink' => $permalink,
            
            // Precios
            'price' => number_format($display_price, 0, ',', '.'),
            'regular_price' => $regular_price > 0 ? number_format($regular_price, 0, ',', '.') : null,
            'sale_price' => $sale_price > 0 ? number_format($sale_price, 0, ',', '.') : null,
            'has_sale' => $has_sale,
            
            // Stock y disponibilidad
            'image_url' => $image_url,
            'stock_quantity' => (int)$product['stock'],
            'is_available' => $product['en_stock'],
            'availability_text' => $product['en_stock'] ? 'Disponible' : 'Agotado',
            'stock_status_class' => $product['en_stock'] ? 'text-success' : 'text-danger',
            'availability_icon' => $product['en_stock'] ? 'fas fa-check-circle' : 'fas fa-times-circle',
            'price_badge_class' => $has_sale ? 'bg-danger' : 'bg-primary',
            'card_border_class' => $product['en_stock'] ? 'border-success' : 'border-warning',
            
            // Datos de variación
            'is_variation' => $product['es_variacion'],
            'variation_label' => $product['variation_label'] ?? '',
            'variation_attributes' => $product['variation_attributes'] ?? null
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
