<?php
require_once('config.php');

class WooCommerceProducts {
    
    private $wp_connection;
    
    public function __construct() {
        $this->wp_connection = DatabaseConfig::getWordPressConnection();
    }
    
    /**
     * Obtener todos los productos de WooCommerce
     */
    public function getAllProducts($limit = 100, $offset = 0) {
        $query = "
            SELECT 
                p.ID as id_producto,
                p.post_title as nombre,
                p.post_content as descripcion,
                p.post_excerpt as descripcion_corta,
                p.post_status as estado,
                p.post_date as fecha_creacion,
                COALESCE(pm_price.meta_value, '0') as precio,
                COALESCE(pm_regular_price.meta_value, '0') as precio_regular,
                COALESCE(pm_sale_price.meta_value, '') as precio_oferta,
                COALESCE(pm_stock.meta_value, '0') as stock,
                COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
                COALESCE(pm_sku.meta_value, '') as sku,
                COALESCE(pm_weight.meta_value, '') as peso,
                COALESCE(pm_length.meta_value, '') as largo,
                COALESCE(pm_width.meta_value, '') as ancho,
                COALESCE(pm_height.meta_value, '') as alto,
                COALESCE(pm_manage_stock.meta_value, 'no') as gestionar_stock
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN miau_postmeta pm_regular_price ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
            LEFT JOIN miau_postmeta pm_sale_price ON p.ID = pm_sale_price.post_id AND pm_sale_price.meta_key = '_sale_price'
            LEFT JOIN miau_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN miau_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN miau_postmeta pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
            LEFT JOIN miau_postmeta pm_length ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
            LEFT JOIN miau_postmeta pm_width ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
            LEFT JOIN miau_postmeta pm_height ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
            LEFT JOIN miau_postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'private')
            ORDER BY p.post_title ASC
            LIMIT $limit OFFSET $offset
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en consulta de productos: " . mysqli_error($this->wp_connection));
        }
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Formatear datos para compatibilidad y asegurar que todos los campos existan
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
        
        return $products;
    }
    
    /**
     * Buscar productos por nombre o SKU
     */
    public function searchProducts($search_term, $limit = 50) {
        $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
        
        $query = "
            SELECT 
                p.ID as id_producto,
                p.post_title as nombre,
                p.post_content as descripcion,
                p.post_excerpt as descripcion_corta,
                COALESCE(pm_price.meta_value, '0') as precio,
                COALESCE(pm_stock.meta_value, '0') as stock,
                COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
                COALESCE(pm_sku.meta_value, '') as sku
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN miau_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN miau_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND (
                p.post_title LIKE '%$search_term%' 
                OR pm_sku.meta_value LIKE '%$search_term%'
                OR p.post_content LIKE '%$search_term%'
            )
            ORDER BY p.post_title ASC
            LIMIT $limit
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en búsqueda de productos: " . mysqli_error($this->wp_connection));
        }
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Formatear datos para compatibilidad y asegurar que todos los campos existan
            $row['precio'] = floatval($row['precio'] ?? 0);
            $row['precio_regular'] = floatval($row['precio'] ?? 0); // Usar precio como regular si no hay precio_regular
            $row['precio_oferta'] = 0; // No hay precio de oferta en búsqueda simple
            $row['stock'] = intval($row['stock'] ?? 0);
            $row['en_stock'] = ($row['estado_stock'] === 'instock');
            $row['sku'] = $row['sku'] ?? '';
            $row['descripcion_corta'] = $row['descripcion_corta'] ?? '';
            $row['descripcion'] = $row['descripcion'] ?? '';
            
            $products[] = $row;
        }
        
        return $products;
    }
    
    /**
     * Obtener un producto específico por ID
     */
    public function getProductById($product_id) {
        $product_id = intval($product_id);
        
        $query = "
            SELECT 
                p.ID as id_producto,
                p.post_title as nombre,
                p.post_content as descripcion,
                p.post_excerpt as descripcion_corta,
                COALESCE(pm_price.meta_value, '0') as precio,
                COALESCE(pm_regular_price.meta_value, '0') as precio_regular,
                COALESCE(pm_sale_price.meta_value, '') as precio_oferta,
                COALESCE(pm_stock.meta_value, '0') as stock,
                COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
                COALESCE(pm_sku.meta_value, '') as sku,
                COALESCE(pm_weight.meta_value, '') as peso
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN miau_postmeta pm_regular_price ON p.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
            LEFT JOIN miau_postmeta pm_sale_price ON p.ID = pm_sale_price.post_id AND pm_sale_price.meta_key = '_sale_price'
            LEFT JOIN miau_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN miau_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN miau_postmeta pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
            WHERE p.ID = $product_id 
            AND p.post_type = 'product'
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener producto: " . mysqli_error($this->wp_connection));
        }
        
        $product = mysqli_fetch_assoc($result);
        
        if ($product) {
            $product['precio'] = floatval($product['precio']);
            $product['precio_regular'] = floatval($product['precio_regular']);
            $product['precio_oferta'] = $product['precio_oferta'] ? floatval($product['precio_oferta']) : null;
            $product['stock'] = intval($product['stock']);
            $product['en_stock'] = ($product['estado_stock'] === 'instock');
        }
        
        return $product;
    }
    
    /**
     * Obtener categorías de productos
     */
    public function getProductCategories() {
        $query = "
            SELECT 
                t.term_id as id_categoria,
                t.name as nombre,
                t.slug as slug,
                tt.count as total_productos
            FROM miau_terms t
            INNER JOIN miau_term_taxonomy tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'product_cat'
            AND tt.count > 0
            ORDER BY t.name ASC
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener categorías: " . mysqli_error($this->wp_connection));
        }
        
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
        
        return $categories;
    }
    
    /**
     * Obtener productos por categoría
     */
    public function getProductsByCategory($category_id, $limit = 50) {
        $category_id = intval($category_id);
        
        $query = "
            SELECT 
                p.ID as id_producto,
                p.post_title as nombre,
                COALESCE(pm_price.meta_value, '0') as precio,
                COALESCE(pm_stock.meta_value, '0') as stock,
                COALESCE(pm_stock_status.meta_value, 'outofstock') as estado_stock,
                COALESCE(pm_sku.meta_value, '') as sku
            FROM miau_posts p
            INNER JOIN miau_term_relationships tr ON p.ID = tr.object_id
            INNER JOIN miau_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN miau_postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN miau_postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN miau_postmeta pm_stock_status ON p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status'
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND tt.term_id = $category_id
            ORDER BY p.post_title ASC
            LIMIT $limit
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener productos por categoría: " . mysqli_error($this->wp_connection));
        }
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Formatear datos para compatibilidad y asegurar que todos los campos existan
            $row['precio'] = floatval($row['precio'] ?? 0);
            $row['precio_regular'] = floatval($row['precio'] ?? 0); // Usar precio como regular si no hay precio_regular
            $row['precio_oferta'] = 0; // No hay precio de oferta en consulta por categoría
            $row['stock'] = intval($row['stock'] ?? 0);
            $row['en_stock'] = ($row['estado_stock'] === 'instock');
            $row['sku'] = $row['sku'] ?? '';
            $row['descripcion_corta'] = '';
            $row['descripcion'] = '';
            
            $products[] = $row;
        }
        
        return $products;
    }
    
    /**
     * Actualizar stock de un producto
     */
    public function updateProductStock($product_id, $new_stock) {
        $product_id = intval($product_id);
        $new_stock = intval($new_stock);
        
        // Actualizar stock
        $query_stock = "
            UPDATE miau_postmeta 
            SET meta_value = '$new_stock' 
            WHERE post_id = $product_id AND meta_key = '_stock'
        ";
        
        $result = mysqli_query($this->wp_connection, $query_stock);
        
        if (!$result) {
            die("Error al actualizar stock: " . mysqli_error($this->wp_connection));
        }
        
        // Actualizar estado del stock
        $stock_status = ($new_stock > 0) ? 'instock' : 'outofstock';
        $query_status = "
            UPDATE miau_postmeta 
            SET meta_value = '$stock_status' 
            WHERE post_id = $product_id AND meta_key = '_stock_status'
        ";
        
        mysqli_query($this->wp_connection, $query_status);
        
        return true;
    }
    
    public function __destruct() {
        if ($this->wp_connection) {
            mysqli_close($this->wp_connection);
        }
    }
}
?>
