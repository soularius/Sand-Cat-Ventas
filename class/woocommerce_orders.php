<?php
require_once('config.php');

class WooCommerceOrders {
    
    private $wp_connection;
    
    public function __construct() {
        $this->wp_connection = DatabaseConfig::getWordPressConnection();
        
        // DEBUG: Verificar conexión
        if (isset($_GET['debug_sql']) || isset($_GET['debug_orders'])) {
            if (!$this->wp_connection) {
                echo "<pre>ERROR: No se pudo conectar a la base de datos de WordPress</pre>";
            } else {
                echo "<pre>CONEXIÓN A DB: OK</pre>";
            }
        }
    }
    
    /**
     * Obtener todas las órdenes de WooCommerce
     */
    public function getAllOrders($status = 'all', $limit = 100, $offset = 0) {
        $status_condition = '';
        if ($status !== 'all') {
            $status_condition = "AND o.status = '$status'";
        }
        
        $query = "
            SELECT 
                o.id AS order_id,
                o.date_created_gmt AS fecha_orden,
                o.status AS estado,
                COALESCE(o.total_amount, 0) AS total,
                COALESCE(o.billing_email, '') AS email_cliente,
                COALESCE(o.customer_id, 0) AS customer_id,
                COALESCE(ba.first_name, '') AS nombre_cliente,
                COALESCE(ba.last_name, '') AS apellido_cliente,
                COALESCE(ba.phone, '') AS telefono_cliente,
                COALESCE(ba.email, o.billing_email) AS email_completo
            FROM miau_wc_orders o
            LEFT JOIN miau_wc_order_addresses ba 
                ON o.id = ba.order_id 
                AND ba.address_type = 'billing'
            WHERE o.type = 'shop_order'
            $status_condition
            ORDER BY o.date_created_gmt DESC
            LIMIT $limit OFFSET $offset
        ";
        
        // DEBUG: Mostrar la consulta que se va a ejecutar
        if (isset($_GET['debug_sql'])) {
            echo "<pre>CONSULTA SQL:\n" . $query . "\n</pre>";
        }
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en consulta de órdenes: " . mysqli_error($this->wp_connection));
        }
        
        // DEBUG: Verificar si hay resultados
        $num_rows = mysqli_num_rows($result);
        if (isset($_GET['debug_sql'])) {
            echo "<pre>NÚMERO DE FILAS ENCONTRADAS: " . $num_rows . "\n</pre>";
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // DEBUG: Mostrar datos crudos de la primera fila
            if (isset($_GET['debug_sql']) && count($orders) == 0) {
                echo "<pre>PRIMERA FILA CRUDA:\n";
                var_dump($row);
                echo "</pre>";
            }
            
            // Formatear datos para compatibilidad
            $row['total'] = floatval($row['total'] ?? 0);
            $row['nombre_completo'] = trim(($row['nombre_cliente'] ?? '') . ' ' . ($row['apellido_cliente'] ?? ''));
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            $row['fecha_formateada'] = date('d/m/Y H:i', strtotime($row['fecha_orden']));
            
            // Asegurar que todos los campos existan y usar el email más completo
            $row['email_cliente'] = $row['email_completo'] ?: $row['email_cliente'];
            $row['telefono_cliente'] = $row['telefono_cliente'] ?? '';
            $row['direccion_cliente'] = '';
            $row['ciudad_cliente'] = '';
            
            $orders[] = $row;
        }
        
        // DEBUG: Mostrar el array final
        if (isset($_GET['debug_sql'])) {
            echo "<pre>TOTAL DE ÓRDENES PROCESADAS: " . count($orders) . "\n";
            if (count($orders) > 0) {
                echo "PRIMERA ORDEN PROCESADA:\n";
                var_dump($orders[0]);
            }
            echo "</pre>";
        }
        
        return $orders;
    }
    
    /**
     * Buscar órdenes por cliente, email o ID
     */
    public function searchOrders($search_term, $status = 'all', $limit = 50) {
        $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
        
        $status_condition = '';
        if ($status !== 'all') {
            $status_condition = "AND o.status = '$status'";
        }
        
        $query = "
            SELECT 
                o.id AS order_id,
                o.date_created_gmt AS fecha_orden,
                o.status AS estado,
                COALESCE(o.total_amount, 0) AS total,
                COALESCE(o.billing_email, '') AS email_cliente,
                COALESCE(o.customer_id, 0) AS customer_id,
                COALESCE(ba.first_name, '') AS nombre_cliente,
                COALESCE(ba.last_name, '') AS apellido_cliente,
                COALESCE(ba.phone, '') AS telefono_cliente,
                COALESCE(ba.email, o.billing_email) AS email_completo
            FROM miau_wc_orders o
            LEFT JOIN miau_wc_order_addresses ba 
                ON o.id = ba.order_id 
                AND ba.address_type = 'billing'
            WHERE o.type = 'shop_order'
            $status_condition
            AND (
                o.id LIKE '%$search_term%' 
                OR ba.first_name LIKE '%$search_term%'
                OR ba.last_name LIKE '%$search_term%'
                OR o.billing_email LIKE '%$search_term%'
                OR ba.phone LIKE '%$search_term%'
            )
            ORDER BY o.date_created_gmt DESC
            LIMIT $limit
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en búsqueda de órdenes: " . mysqli_error($this->wp_connection));
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['total'] = floatval($row['total'] ?? 0);
            $row['nombre_completo'] = trim(($row['nombre_cliente'] ?? '') . ' ' . ($row['apellido_cliente'] ?? ''));
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            $row['fecha_formateada'] = date('d/m/Y H:i', strtotime($row['fecha_orden']));
            
            // Asegurar que todos los campos existan y usar el email más completo
            $row['email_cliente'] = $row['email_completo'] ?: $row['email_cliente'];
            $row['telefono_cliente'] = $row['telefono_cliente'] ?? '';
            
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    /**
     * Obtener órdenes por estado
     */
    public function getOrdersByStatus($status, $limit = 100) {
        return $this->getAllOrders($status, $limit);
    }
    
    /**
     * Verificar qué tablas de WooCommerce existen
     */
    public function checkTableStructure() {
        // Primero verificar qué tablas existen
        $query = "SHOW TABLES LIKE 'miau_%'";
        $result = mysqli_query($this->wp_connection, $query);
        
        $available_tables = [];
        while ($row = mysqli_fetch_array($result)) {
            $available_tables[] = $row[0];
        }
        
        $structure = ['available_tables' => $available_tables];
        
        // Verificar estructura de tablas específicas si existen
        $tables_to_check = ['miau_wc_orders', 'miau_wc_order_addresses', 'miau_posts'];
        
        foreach ($tables_to_check as $table) {
            if (in_array($table, $available_tables)) {
                $query = "DESCRIBE $table";
                $desc_result = mysqli_query($this->wp_connection, $query);
                
                if ($desc_result) {
                    $columns = [];
                    while ($row = mysqli_fetch_assoc($desc_result)) {
                        $columns[] = $row['Field'] . ' (' . $row['Type'] . ')';
                    }
                    $structure[$table] = $columns;
                }
            } else {
                $structure[$table] = "Tabla no existe";
            }
        }
        
        return $structure;
    }
    
    /**
     * Obtener todos los estados disponibles en las órdenes
     */
    public function getAllOrderStatuses() {
        $query = "
            SELECT DISTINCT 
                o.status as estado,
                COUNT(*) as cantidad
            FROM miau_wc_orders o
            WHERE o.type = 'shop_order'
            GROUP BY o.status
            ORDER BY cantidad DESC
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            return ['error' => mysqli_error($this->wp_connection)];
        }
        
        $estados = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $estados[] = [
                'estado' => $row['estado'],
                'cantidad' => $row['cantidad'],
                'etiqueta' => $this->getStatusLabel($row['estado'])
            ];
        }
        
        return $estados;
    }
    
    /**
     * Método simple para probar conexión y obtener órdenes básicas
     */
    public function getSimpleOrders($limit = 5) {
        // Primero intentar con HPOS
        $query = "SELECT * FROM miau_wc_orders LIMIT $limit";
        $result = mysqli_query($this->wp_connection, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            return ['source' => 'HPOS', 'data' => $orders];
        }
        
        // Si no funciona HPOS, intentar con posts tradicional
        $query = "SELECT ID, post_date, post_status, post_type FROM miau_posts WHERE post_type = 'shop_order' LIMIT $limit";
        $result = mysqli_query($this->wp_connection, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            return ['source' => 'Posts', 'data' => $orders];
        }
        
        return ['source' => 'None', 'data' => [], 'error' => mysqli_error($this->wp_connection)];
    }
    
    /**
     * Mostrar las consultas SQL para probar manualmente
     */
    public function showQueries() {
        $queries = [];
        
        // Consulta para verificar tablas
        $queries['verificar_tablas'] = "SHOW TABLES LIKE 'miau_%';";
        
        // Consulta HPOS principal (todas las órdenes)
        $queries['hpos_orders'] = "
SELECT 
    o.id AS order_id,
    o.date_created_gmt AS fecha_orden,
    o.status AS estado,
    COALESCE(o.total_amount, 0) AS total,
    COALESCE(o.billing_email, '') AS email_cliente,
    COALESCE(o.customer_id, 0) AS customer_id,
    COALESCE(ba.first_name, '') AS nombre_cliente,
    COALESCE(ba.last_name, '') AS apellido_cliente,
    COALESCE(ba.phone, '') AS telefono_cliente,
    COALESCE(ba.email, o.billing_email) AS email_completo
FROM miau_wc_orders o
LEFT JOIN miau_wc_order_addresses ba 
    ON o.id = ba.order_id 
    AND ba.address_type = 'billing'
WHERE o.type = 'shop_order'
ORDER BY o.date_created_gmt DESC
LIMIT 10;";
        
        // Consulta simple HPOS
        $queries['hpos_simple'] = "SELECT * FROM miau_wc_orders WHERE type = 'shop_order' LIMIT 5;";
        
        // Consulta para obtener todos los estados disponibles
        $queries['estados_disponibles'] = "
SELECT DISTINCT 
    o.status AS estado
FROM miau_wc_orders o
WHERE o.type = 'shop_order'
ORDER BY o.status;";
        
        // Consulta posts tradicional
        $queries['posts_orders'] = "
SELECT 
    p.ID as order_id,
    p.post_date as fecha_orden,
    p.post_status as estado,
    pm_total.meta_value as total,
    pm_email.meta_value as email_cliente
FROM miau_posts p
LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
LEFT JOIN miau_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
WHERE p.post_type = 'shop_order'
AND p.post_status IN ('wc-processing', 'wc-on-hold', 'wc-completed')
ORDER BY p.post_date DESC
LIMIT 5;";
        
        // Consulta simple posts
        $queries['posts_simple'] = "SELECT ID, post_date, post_status, post_type FROM miau_posts WHERE post_type = 'shop_order' LIMIT 5;";
        
        // Verificar estructura de tablas
        $queries['describe_hpos'] = "DESCRIBE miau_wc_orders;";
        $queries['describe_addresses'] = "DESCRIBE miau_wc_order_addresses;";
        $queries['describe_posts'] = "DESCRIBE miau_posts;";
        
        return $queries;
    }
    
    /**
     * Obtener una orden específica por ID
     */
    public function getOrderById($order_id) {
        $order_id = intval($order_id);
        
        $query = "
            SELECT 
                p.ID as order_id,
                p.post_date as fecha_orden,
                p.post_status as estado,
                p.post_modified as fecha_modificacion,
                COALESCE(pm_total.meta_value, '0') as total,
                COALESCE(pm_subtotal.meta_value, '0') as subtotal,
                COALESCE(pm_tax_total.meta_value, '0') as impuestos,
                COALESCE(pm_shipping_total.meta_value, '0') as envio,
                COALESCE(pm_billing_first_name.meta_value, '') as nombre_cliente,
                COALESCE(pm_billing_last_name.meta_value, '') as apellido_cliente,
                COALESCE(pm_billing_email.meta_value, '') as email_cliente,
                COALESCE(pm_billing_phone.meta_value, '') as telefono_cliente,
                COALESCE(pm_billing_address_1.meta_value, '') as direccion_cliente,
                COALESCE(pm_billing_city.meta_value, '') as ciudad_cliente,
                COALESCE(pm_payment_method.meta_value, '') as metodo_pago,
                COALESCE(pm_payment_method_title.meta_value, '') as titulo_metodo_pago
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            LEFT JOIN miau_postmeta pm_subtotal ON p.ID = pm_subtotal.post_id AND pm_subtotal.meta_key = '_order_subtotal'
            LEFT JOIN miau_postmeta pm_tax_total ON p.ID = pm_tax_total.post_id AND pm_tax_total.meta_key = '_order_tax'
            LEFT JOIN miau_postmeta pm_shipping_total ON p.ID = pm_shipping_total.post_id AND pm_shipping_total.meta_key = '_order_shipping'
            LEFT JOIN miau_postmeta pm_billing_first_name ON p.ID = pm_billing_first_name.post_id AND pm_billing_first_name.meta_key = '_billing_first_name'
            LEFT JOIN miau_postmeta pm_billing_last_name ON p.ID = pm_billing_last_name.post_id AND pm_billing_last_name.meta_key = '_billing_last_name'
            LEFT JOIN miau_postmeta pm_billing_email ON p.ID = pm_billing_email.post_id AND pm_billing_email.meta_key = '_billing_email'
            LEFT JOIN miau_postmeta pm_billing_phone ON p.ID = pm_billing_phone.post_id AND pm_billing_phone.meta_key = '_billing_phone'
            LEFT JOIN miau_postmeta pm_billing_address_1 ON p.ID = pm_billing_address_1.post_id AND pm_billing_address_1.meta_key = '_billing_address_1'
            LEFT JOIN miau_postmeta pm_billing_city ON p.ID = pm_billing_city.post_id AND pm_billing_city.meta_key = '_billing_city'
            LEFT JOIN miau_postmeta pm_payment_method ON p.ID = pm_payment_method.post_id AND pm_payment_method.meta_key = '_payment_method'
            LEFT JOIN miau_postmeta pm_payment_method_title ON p.ID = pm_payment_method_title.post_id AND pm_payment_method_title.meta_key = '_payment_method_title'
            WHERE p.ID = $order_id 
            AND p.post_type = 'shop_order'
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener orden: " . mysqli_error($this->wp_connection));
        }
        
        $order = mysqli_fetch_assoc($result);
        
        if ($order) {
            $order['total'] = floatval($order['total'] ?? 0);
            $order['subtotal'] = floatval($order['subtotal'] ?? 0);
            $order['impuestos'] = floatval($order['impuestos'] ?? 0);
            $order['envio'] = floatval($order['envio'] ?? 0);
            $order['nombre_completo'] = trim(($order['nombre_cliente'] ?? '') . ' ' . ($order['apellido_cliente'] ?? ''));
            $order['estado_legible'] = $this->getStatusLabel($order['estado']);
            $order['fecha_formateada'] = date('d/m/Y H:i', strtotime($order['fecha_orden']));
        }
        
        return $order;
    }
    
    /**
     * Obtener productos de una orden
     */
    public function getOrderItems($order_id) {
        $order_id = intval($order_id);
        
        $query = "
            SELECT 
                oi.order_item_id,
                oi.order_item_name as nombre_producto,
                oim_qty.meta_value as cantidad,
                oim_total.meta_value as total_linea,
                oim_product_id.meta_value as product_id
            FROM miau_woocommerce_order_items oi
            LEFT JOIN miau_woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
            LEFT JOIN miau_woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
            LEFT JOIN miau_woocommerce_order_itemmeta oim_product_id ON oi.order_item_id = oim_product_id.order_item_id AND oim_product_id.meta_key = '_product_id'
            WHERE oi.order_id = $order_id 
            AND oi.order_item_type = 'line_item'
            ORDER BY oi.order_item_id
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener items de la orden: " . mysqli_error($this->wp_connection));
        }
        
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['cantidad'] = intval($row['cantidad'] ?? 0);
            $row['total_linea'] = floatval($row['total_linea'] ?? 0);
            $row['product_id'] = intval($row['product_id'] ?? 0);
            
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Obtener etiqueta legible del estado
     */
    private function getStatusLabel($status) {
        $labels = [
            'wc-pending' => 'Pendiente',
            'wc-processing' => 'Procesando',
            'wc-on-hold' => 'En espera',
            'wc-completed' => 'Completada',
            'wc-cancelled' => 'Cancelada',
            'wc-refunded' => 'Reembolsada',
            'wc-failed' => 'Fallida',
            'wc-checkout-draft' => 'Borrador',
            // Sin prefijo también
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'on-hold' => 'En espera',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'refunded' => 'Reembolsada',
            'failed' => 'Fallida',
            'checkout-draft' => 'Borrador'
        ];
        
        return $labels[$status] ?? ucfirst(str_replace(['wc-', '_'], ['', ' '], $status));
    }
    
    /**
     * Obtener estadísticas de órdenes
     */
    public function getOrderStats($days = 30) {
        $date_from = date('Y-m-d', strtotime("-$days days"));
        
        $query = "
            SELECT 
                o.status as estado,
                COUNT(*) as cantidad,
                SUM(COALESCE(o.total_amount, 0)) as total_ventas
            FROM miau_wc_orders o
            WHERE o.type = 'shop_order'
            AND o.date_created_gmt >= '$date_from'
            GROUP BY o.status
            ORDER BY cantidad DESC
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener estadísticas: " . mysqli_error($this->wp_connection));
        }
        
        $stats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['cantidad'] = intval($row['cantidad']);
            $row['total_ventas'] = floatval($row['total_ventas']);
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    public function __destruct() {
        if ($this->wp_connection) {
            mysqli_close($this->wp_connection);
        }
    }
}
?>
