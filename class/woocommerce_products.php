<?php
require_once('config.php');

class WooCommerceProducts {
    
    private $wp_connection;
    
    public function __construct() {
        // Intentar usar la conexión de DatabaseConfig primero
        try {
            $this->wp_connection = DatabaseConfig::getWordPressConnection();
        } catch (Exception $e) {
            // Si falla, usar la conexión global
            global $miau;
            if ($miau) {
                $this->wp_connection = $miau;
            } else {
                throw new Exception('No se pudo establecer conexión a la base de datos');
            }
        }
        
        // Verificar que la conexión sea válida
        if (!$this->wp_connection) {
            throw new Exception('Conexión a base de datos no válida');
        }
    }
    
    /**
     * Obtener el total de productos para paginación (incluye variaciones)
     */
    public function getTotalProductsCount($search_term = '', $category_id = 0) {
        $search_condition = '';
        $category_condition = '';
        
        if (!empty($search_term)) {
            $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
            $search_condition = "AND (
                p.post_title LIKE '%$search_term%' 
                OR pm_sku.meta_value LIKE '%$search_term%'
                OR p.post_content LIKE '%$search_term%'
            )";
        }
        
        if ($category_id > 0) {
            $category_id = intval($category_id);
            $category_condition = "
                INNER JOIN miau_term_relationships tr ON p.ID = tr.object_id
                INNER JOIN miau_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                AND tt.taxonomy = 'product_cat'
                AND tt.term_id = $category_id
            ";
        }
        
        $query = "
            SELECT COUNT(DISTINCT p.ID) as total
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            $category_condition
            WHERE p.post_status IN ('publish', 'private')
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
            )
            $search_condition
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            throw new Exception("Error en consulta de conteo de productos: " . mysqli_error($this->wp_connection));
        }
        
        $row = mysqli_fetch_assoc($result);
        return intval($row['total']);
    }
    
    /**
     * Obtener todos los productos de WooCommerce con paginación
     */
    public function getAllProducts($limit = 20, $offset = 0, $search_term = '', $category_id = 0) {
        // Construir condiciones de búsqueda y categoría
        $search_condition = '';
        $category_condition = '';
        
        if (!empty($search_term)) {
            $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
            $search_condition = "AND (
                p.post_title LIKE '%$search_term%' 
                OR pm_sku.meta_value LIKE '%$search_term%'
                OR p.post_content LIKE '%$search_term%'
            )";
        }
        
        if ($category_id > 0) {
            $category_id = intval($category_id);
            $category_condition = "
                INNER JOIN miau_term_relationships tr ON p.ID = tr.object_id
                INNER JOIN miau_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                AND tt.taxonomy = 'product_cat'
                AND tt.term_id = $category_id
            ";
        }
        
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
                COALESCE(pm_manage_stock.meta_value, 'no') as gestionar_stock,
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
            LEFT JOIN miau_postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN miau_postmeta pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
            LEFT JOIN miau_postmeta pm_length ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
            LEFT JOIN miau_postmeta pm_width ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
            LEFT JOIN miau_postmeta pm_height ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
            LEFT JOIN miau_postmeta pm_manage_stock ON p.ID = pm_manage_stock.post_id AND pm_manage_stock.meta_key = '_manage_stock'
            $category_condition
            WHERE p.post_status IN ('publish', 'private')
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
            )
            $search_condition
            ORDER BY p.post_title ASC
            LIMIT $limit OFFSET $offset
        ";
        
        // Debug: mostrar la query completa
        Utils::logError("getAllProducts SQL Query: " . $query);
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            throw new Exception("Error en consulta de productos: " . mysqli_error($this->wp_connection));
        }
        
        // Debug: contar filas obtenidas
        $row_count = mysqli_num_rows($result);
        Utils::logError("getAllProducts - Rows returned from DB: " . $row_count);
        
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
            
            // Datos de variación
            $row['variation_id'] = $row['variation_id'] ? intval($row['variation_id']) : null;
            $row['product_id'] = intval($row['product_id']);
            $row['es_variacion'] = ($row['post_type'] === 'product_variation');
            
            // Debug: mostrar datos de cada producto
            Utils::logError("Product processed - ID: " . $row['id_producto'] . ", Type: " . $row['post_type'] . ", Name: " . $row['nombre'] . ", Is_variation: " . ($row['es_variacion'] ? 'YES' : 'NO'));
            
            // Si es variación, obtener atributos y label
            if ($row['es_variacion'] && $row['variation_id']) {
                $row['variation_attributes'] = $this->getVariationAttributes($row['variation_id']);
                $row['variation_label'] = $this->buildVariationLabel($row['variation_attributes']);
                Utils::logError("Variation processed - Variation_ID: " . $row['variation_id'] . ", Label: " . $row['variation_label']);
            } else {
                $row['variation_attributes'] = null;
                $row['variation_label'] = '';
            }
            
            $products[] = $row;
        }
        
        return $products;
    }
    
    /**
     * Obtener atributos de variación
     */
    private function getVariationAttributes($variation_id) {
        $variation_id = (int)$variation_id;
        
        $query = "SELECT meta_key, meta_value 
                 FROM miau_postmeta 
                 WHERE post_id = $variation_id 
                 AND meta_key LIKE 'attribute_%'";
        
        $result = mysqli_query($this->wp_connection, $query);
        $attributes = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $attr_name = str_replace('attribute_', '', $row['meta_key']);
                $attributes[$attr_name] = $row['meta_value'];
            }
            mysqli_free_result($result);
        }
        
        return $attributes;
    }
    
    /**
     * Construir label de variación
     */
    private function buildVariationLabel($attributes) {
        if (empty($attributes)) {
            return '';
        }
        
        $labels = [];
        foreach ($attributes as $name => $value) {
            if (!empty($value)) {
                // Limpiar nombre del atributo (quitar pa_ si existe)
                $clean_name = str_replace('pa_', '', $name);
                // Reemplazar guiones por espacios y underscores por espacios
                $clean_name = str_replace(['-', '_'], ' ', $clean_name);
                $clean_name = ucfirst($clean_name);
                $labels[] = "$clean_name: $value";
            }
        }
        
        // Separar por <br> en lugar de comas para mostrar en líneas separadas
        return implode('<br>', $labels);
    }

    /**
     * Buscar productos con variaciones (optimizado usando lógica de search_products_ajax.php)
     */
    public function searchProductsWithVariations($search_term = '', $limit = 50, $offset = 0, $category_id = '') {
        $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
        $limit = (int)$limit;
        $offset = (int)$offset;
        $category_id = mysqli_real_escape_string($this->wp_connection, $category_id);
        
        // Construir query base - INCLUYE PRODUCTOS Y VARIACIONES (igual que search_products_ajax.php)
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
        
        // Condiciones de búsqueda (igual que search_products_ajax.php)
        $conditions = [];
        
        // Filtro por categoría
        if (!empty($category_id)) {
            $conditions[] = "tt.term_taxonomy_id = '$category_id'";
        }
        
        // Filtro por término de búsqueda
        if (!empty($search_term)) {
            $conditions[] = "(
                p.post_title LIKE '%$search_term%' 
                OR pm_sku.meta_value LIKE '%$search_term%'
                OR p.post_content LIKE '%$search_term%'
            )";
        }
        
        // Agregar condiciones a la query
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        
        $query .= "
            ORDER BY p.post_title ASC
            LIMIT $limit OFFSET $offset";
        
        // Debug: mostrar la query completa para búsqueda
        Utils::logError("searchProductsWithVariations SQL Query: " . $query);
        Utils::logError("searchProductsWithVariations Parameters - Search: '$search_term', Category: '$category_id', Limit: $limit, Offset: $offset");
        
        $result = mysqli_query($this->wp_connection, $query);
        if (!$result) {
            throw new Exception('Error en consulta de productos con variaciones: ' . mysqli_error($this->wp_connection));
        }
        
        // Debug: contar filas obtenidas en búsqueda
        $row_count = mysqli_num_rows($result);
        Utils::logError("searchProductsWithVariations - Rows returned from DB: " . $row_count);
        
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Obtener atributos de variación si es una variación
            $variation_attributes = null;
            $variation_label = '';
            
            if ($row['variation_id']) {
                $variation_attributes = $this->getVariationAttributes($row['variation_id']);
                $variation_label = $this->buildVariationLabel($variation_attributes);
            }
            
            // Construir título separando nombre padre y variación
            $parent_name = $row['nombre'];
            $display_title = $parent_name; // Solo nombre del padre
            
            // Debug: mostrar datos de cada producto en búsqueda
            Utils::logError("Search Product processed - ID: " . $row['id_producto'] . ", Type: " . $row['post_type'] . ", Name: " . $row['nombre'] . ", Variation_ID: " . ($row['variation_id'] ?? 'null'));
            
            $products[] = [
                // IDs principales
                'id' => (int)$row['id_producto'],                    // ID único (variación o producto)
                'id_producto' => (int)$row['id_producto'],           // Para compatibilidad con productos.php
                'product_id' => (int)$row['product_id'],             // ID del producto padre
                'variation_id' => $row['variation_id'],              // ID de variación (null si es producto)
                
                // Información básica
                'title' => $display_title,
                'nombre' => $parent_name,                            // Para compatibilidad con productos.php
                'parent_name' => $parent_name,                       // Nombre del producto padre
                'variation_label' => $variation_label,               // Label formateado de variación
                'variation_attributes' => $variation_attributes,     // Atributos raw de variación
                'es_variacion' => ($row['post_type'] === 'product_variation'), // Para compatibilidad
                
                // Descripción
                'descripcion' => $row['descripcion'] ?? '',
                'descripcion_corta' => $row['descripcion_corta'] ?? '',
                
                // Precios
                'precio' => (float)($row['precio'] ?? 0),
                'precio_regular' => (float)($row['precio_regular'] ?? 0),
                'precio_oferta' => !empty($row['precio_oferta']) ? (float)$row['precio_oferta'] : null,
                
                // Stock
                'stock' => (int)($row['stock'] ?? 0),
                'estado_stock' => $row['estado_stock'] ?? 'outofstock',
                'is_available' => ($row['estado_stock'] === 'instock'),
                'en_stock' => ($row['estado_stock'] === 'instock'), // Para compatibilidad
                
                // Otros
                'sku' => $row['sku'] ?? '',
                'post_type' => $row['post_type']
            ];
        }
        
        mysqli_free_result($result);
        return $products;
    }

    /**
     * Buscar productos por nombre o SKU con paginación
     */
    public function searchProducts($search_term, $limit = 50, $offset = 0) {
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
            LIMIT $limit OFFSET $offset
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            throw new Exception("Error en búsqueda de productos: " . mysqli_error($this->wp_connection));
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
    /**
     * ✅ Obtiene productos/variaciones por sus IDs con fallback de imágenes y sin duplicados
     * 
     * @param array $product_ids Array de IDs de productos
     * @param array $variation_ids Array de IDs de variaciones (opcional)
     * @return array Array de productos con todos los datos necesarios
     */
    public function getProductsByIds(array $product_ids, array $variation_ids = []): array
    {
        // Sanitizar y combinar todos los IDs
        $all_ids = array_merge($product_ids, $variation_ids);
        $all_ids = array_map('intval', $all_ids);
        $all_ids = array_filter($all_ids, function($id) { return $id > 0; });
        $all_ids = array_unique($all_ids);
        
        if (empty($all_ids)) {
            return [];
        }
        
        // Crear string seguro de IDs (solo números, separados por coma)
        $ids_str = implode(',', $all_ids);
        
        $query = "
            SELECT 
                p.ID AS id,

                /* product_id = padre si es variación */
                CASE 
                    WHEN p.post_type = 'product_variation' THEN p.post_parent 
                    ELSE p.ID 
                END AS product_id,

                CASE 
                    WHEN p.post_type = 'product_variation' THEN p.ID 
                    ELSE NULL 
                END AS variation_id,

                p.post_title AS nombre,

                CASE 
                    WHEN p.post_type = 'product_variation' THEN parent.post_title 
                    ELSE p.post_title 
                END AS parent_name,

                p.post_type,

                /* Precios (si existen en la variación, salen; si no, quedan null y luego lo ajustamos a fallback al padre) */
                MAX(pm_price.meta_value)   AS precio,
                MAX(pm_regular.meta_value) AS precio_regular,
                MAX(pm_sale.meta_value)    AS precio_oferta,

                /* Stock */
                MAX(pm_stock.meta_value)        AS stock,
                MAX(pm_stock_status.meta_value) AS estado_stock,

                /* SKU */
                MAX(pm_sku.meta_value) AS sku,

                /* ✅ image_id con fallback: variación -> padre */
                COALESCE(thumb_var.thumb_id, thumb_prod.thumb_id, 0) AS image_id,

                /* URL base del sitio */
                opt.base_url AS siteurl,

                /* ✅ image_url: si hay _wp_attached_file armamos URL; si no, fallback guid */
                CASE
                    WHEN COALESCE(att_file.file_path, '') <> '' THEN
                        CONCAT(opt.base_url, '/wp-content/uploads/', att_file.file_path)
                    ELSE
                        COALESCE(att.guid, '')
                END AS image_url

            FROM miau_posts p

            /* Padre (solo para variación) */
            LEFT JOIN miau_posts parent
                ON parent.ID = p.post_parent
               AND p.post_type = 'product_variation'

            /* Metas del post actual */
            LEFT JOIN miau_postmeta pm_price
                ON pm_price.post_id = p.ID AND pm_price.meta_key = '_price'
            LEFT JOIN miau_postmeta pm_regular
                ON pm_regular.post_id = p.ID AND pm_regular.meta_key = '_regular_price'
            LEFT JOIN miau_postmeta pm_sale
                ON pm_sale.post_id = p.ID AND pm_sale.meta_key = '_sale_price'

            LEFT JOIN miau_postmeta pm_stock
                ON pm_stock.post_id = p.ID AND pm_stock.meta_key = '_stock'
            LEFT JOIN miau_postmeta pm_stock_status
                ON pm_stock_status.post_id = p.ID AND pm_stock_status.meta_key = '_stock_status'

            LEFT JOIN miau_postmeta pm_sku
                ON pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku'

            /* ✅ base_url desde options (siteurl/home) */
            CROSS JOIN (
                SELECT TRIM(TRAILING '/' FROM COALESCE(
                    MAX(CASE WHEN option_name = 'siteurl' THEN option_value END),
                    MAX(CASE WHEN option_name = 'home'    THEN option_value END),
                    ''
                )) AS base_url
                FROM miau_options
                WHERE option_name IN ('siteurl', 'home')
            ) opt

            /* ✅ thumb_id de la variación (agrupado para evitar duplicados) */
            LEFT JOIN (
                SELECT post_id, MAX(CAST(meta_value AS UNSIGNED)) AS thumb_id
                FROM miau_postmeta
                WHERE meta_key = '_thumbnail_id' AND meta_value <> ''
                GROUP BY post_id
            ) thumb_var
                ON thumb_var.post_id = p.ID
               AND p.post_type = 'product_variation'

            /* ✅ thumb_id del padre (o del mismo post si no es variación) */
            LEFT JOIN (
                SELECT post_id, MAX(CAST(meta_value AS UNSIGNED)) AS thumb_id
                FROM miau_postmeta
                WHERE meta_key = '_thumbnail_id' AND meta_value <> ''
                GROUP BY post_id
            ) thumb_prod
                ON thumb_prod.post_id = CASE 
                    WHEN p.post_type = 'product_variation' THEN p.post_parent
                    ELSE p.ID
                END

            /* ✅ _wp_attached_file del attachment final */
            LEFT JOIN (
                SELECT post_id, MAX(meta_value) AS file_path
                FROM miau_postmeta
                WHERE meta_key = '_wp_attached_file' AND meta_value <> ''
                GROUP BY post_id
            ) att_file
                ON att_file.post_id = COALESCE(thumb_var.thumb_id, thumb_prod.thumb_id)

            /* ✅ guid del attachment (fallback) */
            LEFT JOIN miau_posts att
                ON att.ID = COALESCE(thumb_var.thumb_id, thumb_prod.thumb_id)
               AND att.post_type = 'attachment'

            WHERE p.ID IN ($ids_str)
              AND p.post_status = 'publish'
              AND p.post_type IN ('product', 'product_variation')

            GROUP BY
                p.ID, p.post_title, p.post_type, parent.post_title,
                opt.base_url, thumb_var.thumb_id, thumb_prod.thumb_id, att_file.file_path, att.guid

            ORDER BY p.post_title ASC
        ";

        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            throw new Exception("Error obteniendo productos por IDs: " . mysqli_error($this->wp_connection));
        }

        $products = [];
        $processed_ids = []; // Para evitar duplicados en el array final
        
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)$row['id'];
            
            // Evitar duplicados en el array final
            if (in_array($id, $processed_ids)) {
                continue;
            }
            $processed_ids[] = $id;
            
            // Obtener atributos de variación si es una variación
            $variation_attributes = null;
            $variation_label = '';
            
            if ($row['post_type'] === 'product_variation') {
                $variation_attributes = $this->getVariationAttributes($id);
                $variation_label = $this->buildVariationLabel($variation_attributes);
            }
            
            // ✅ Usar directamente image_url de la DB (ya viene construida)
            $image_url = $row['image_url'] ?? '';
            
            // Construir array del producto con todos los campos necesarios
            $product = [
                'id' => $id,
                'id_producto' => $id, // Compatibilidad
                'product_id' => (int)$row['product_id'], // Ya viene correcto del SQL
                'variation_id' => ($row['post_type'] === 'product_variation') ? $id : null,
                'nombre' => $row['nombre'] ?? '',
                'title' => $row['nombre'] ?? '', // Compatibilidad
                'parent_name' => $row['parent_name'] ?? $row['nombre'] ?? '',
                'variation_label' => $variation_label,
                'variation_attributes' => $variation_attributes,
                'precio' => (float)($row['precio'] ?? 0),
                'precio_regular' => (float)($row['precio_regular'] ?? 0),
                'precio_oferta' => (float)($row['precio_oferta'] ?? 0),
                'stock' => (int)($row['stock'] ?? 0),
                'estado_stock' => $row['estado_stock'] ?? 'outofstock',
                'sku' => $row['sku'] ?? '',
                'image_id' => (int)($row['image_id'] ?? 0),
                'image_url' => $image_url,
                'permalink' => '', // Se puede agregar lógica específica si se necesita
                'post_type' => $row['post_type'] ?? 'product'
            ];
            
            $products[] = $product;
        }
        
        mysqli_free_result($result);
        
        return $products;
    }
    
    /**
     * Obtener imagen del producto desde WordPress
     */
    public function getProductImage($product_id) {
        $product_id = intval($product_id);
        
        // Obtener thumbnail_id del producto
        $query = "
            SELECT meta_value as thumbnail_id 
            FROM miau_postmeta 
            WHERE post_id = $product_id 
            AND meta_key = '_thumbnail_id'
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        if (!$result || mysqli_num_rows($result) == 0) {
            return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
        }
        
        $row = mysqli_fetch_assoc($result);
        $thumbnail_id = $row['thumbnail_id'];
        
        if (!$thumbnail_id) {
            return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
        }
        
        // Obtener URL de la imagen
        $image_query = "
            SELECT guid 
            FROM miau_posts 
            WHERE ID = $thumbnail_id 
            AND post_type = 'attachment'
        ";
        
        $image_result = mysqli_query($this->wp_connection, $image_query);
        if ($image_result && $image_row = mysqli_fetch_assoc($image_result)) {
            return $image_row['guid'];
        }
        
        return 'https://via.placeholder.com/200x200?text=Sin+Imagen';
    }

    
    /**
     * Obtener múltiples imágenes de productos de una vez (optimizado)
     */
    public function getProductImages($product_ids) {
        if (empty($product_ids)) {
            return [];
        }
        
        $product_ids = array_map('intval', $product_ids);
        $ids_string = implode(',', $product_ids);
        
        // Obtener todos los thumbnail_ids de una vez
        $query = "
            SELECT post_id, meta_value as thumbnail_id 
            FROM miau_postmeta 
            WHERE post_id IN ($ids_string) 
            AND meta_key = '_thumbnail_id'
            AND meta_value != ''
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        if (!$result) {
            return [];
        }
        
        $thumbnail_map = [];
        $thumbnail_ids = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $thumbnail_map[$row['post_id']] = $row['thumbnail_id'];
            $thumbnail_ids[] = $row['thumbnail_id'];
        }
        
        if (empty($thumbnail_ids)) {
            // Retornar placeholders para todos los productos
            $images = [];
            foreach ($product_ids as $product_id) {
                $images[$product_id] = 'https://via.placeholder.com/200x200?text=Sin+Imagen';
            }
            return $images;
        }
        
        // Obtener URLs de todas las imágenes de una vez
        $thumbnail_ids_string = implode(',', $thumbnail_ids);
        $image_query = "
            SELECT ID, guid 
            FROM miau_posts 
            WHERE ID IN ($thumbnail_ids_string) 
            AND post_type = 'attachment'
        ";
        
        $image_result = mysqli_query($this->wp_connection, $image_query);
        $image_urls = [];
        
        if ($image_result) {
            while ($image_row = mysqli_fetch_assoc($image_result)) {
                $image_urls[$image_row['ID']] = $image_row['guid'];
            }
        }
        
        // Mapear imágenes a productos
        $product_images = [];
        foreach ($product_ids as $product_id) {
            if (isset($thumbnail_map[$product_id]) && isset($image_urls[$thumbnail_map[$product_id]])) {
                $product_images[$product_id] = $image_urls[$thumbnail_map[$product_id]];
            } else {
                $product_images[$product_id] = 'https://via.placeholder.com/200x200?text=Sin+Imagen';
            }
        }
        
        return $product_images;
    }
    
    /**
     * Obtener categorías de productos
     */
    public function getProductCategories() {
        $query = "
            SELECT 
                t.term_id as id_categoria,
                tt.term_taxonomy_id as id_taxonomy,
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
     * Obtener productos por categoría con paginación
     */
    public function getProductsByCategory($category_id, $limit = 50, $offset = 0) {
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
            LIMIT $limit OFFSET $offset
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
