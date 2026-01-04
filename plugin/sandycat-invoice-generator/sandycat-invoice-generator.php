<?php
/**
 * Plugin Name: Sand y Cat Invoice Generator
 * Plugin URI: https://sandycat.com
 * Description: Genera facturas PDF para pedidos de WooCommerce usando el sistema de ventas SandCat
 * Version: 1.0.0
 * Author: SandCat Team
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('SANDCAT_INVOICE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SANDCAT_INVOICE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SANDCAT_INVOICE_VERSION', '1.0.0');

/**
 * Clase principal del plugin Sand y Cat Invoice Generator
 */
class SandCatInvoiceGenerator {
    
    private $ventas_db;
    private $logger;
    
    public function __construct() {
        // Cargar la clase logger
        require_once SANDCAT_INVOICE_PLUGIN_PATH . 'includes/class-sandcat-logger.php';
        $this->logger = new SandCat_Logger();
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Hooks para WooCommerce
        add_action('add_meta_boxes', array($this, 'add_invoice_metabox'));
        add_action('wp_ajax_generate_sandcat_invoice', array($this, 'ajax_generate_invoice'));
        add_action('wp_ajax_get_invoice_pdf_url', array($this, 'ajax_get_invoice_pdf_url'));
        add_action('wp_ajax_stream_invoice_pdf', array($this, 'ajax_stream_invoice_pdf'));
        add_action('wp_ajax_check_invoice_status', array($this, 'ajax_check_invoice_status'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Hooks para configuración
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_test_sandcat_db_connection', array($this, 'ajax_test_db_connection'));
        
        // Hook adicional para asegurar que el script se carga
        add_action('admin_footer', array($this, 'ensure_script_loaded'));
        
        // Hook de activación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Declarar compatibilidad con características de WooCommerce
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
    }
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Inicializar conexión a base de datos de ventas
        $this->init_ventas_db_connection();
    }
    
    /**
     * Cargar textdomain para traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain('sandcat-invoice', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Declarar compatibilidad con características de WooCommerce
     */
    public function declare_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('analytics', __FILE__, true);
        }
    }
    
    /**
     * Inicializar conexión a base de datos de ventas
     */
    private function init_ventas_db_connection() {
        try {
            // Obtener configuraciones guardadas
            $db_settings = get_option('sandcat_invoice_db_settings', array());
            
            $host = isset($db_settings['db_host']) ? $db_settings['db_host'] : 'localhost';
            $user = isset($db_settings['db_user']) ? $db_settings['db_user'] : 'root';
            $password = isset($db_settings['db_password']) ? $db_settings['db_password'] : '';
            $database = isset($db_settings['db_name']) ? $db_settings['db_name'] : 'ventassc';
            
            $this->ventas_db = new mysqli($host, $user, $password, $database);
            
            if ($this->ventas_db->connect_error) {
                throw new Exception('Error conectando a base de datos de ventas: ' . $this->ventas_db->connect_error);
            }
            
            $this->ventas_db->set_charset('utf8');
            $this->logger->info('Successfully connected to sales database');
            
            // Cargar constantes desde la base de datos
            $this->load_constants_from_db();
            
        } catch (Exception $e) {
            $this->logger->error('Error connecting to sales database', array('error' => $e->getMessage()));
            add_action('admin_notices', array($this, 'database_error_notice'));
        }
    }
    
    /**
     * Cargar constantes desde la base de datos de ventas
     */
    private function load_constants_from_db() {
        try {
            $query = "SELECT clave, valor FROM configuracion WHERE clave IN (
                'DEBUG_MODE', 'DIRECCION', 'LOGO_FACTURA', 'LOGO_VENTAS', 
                'NIT', 'NOMBRE_NEGOCIO', 'SERIE_NUMERO_FACTURA', 'TAX_RATE', 'TELEFONO', 'URL_WOOCOMMERCE', 'VENTAS_URL'
            )";
            
            $result = $this->ventas_db->query($query);
            if ($result) {
                $constants = array();
                while ($row = $result->fetch_assoc()) {
                    $constants[$row['clave']] = $row['valor'];
                }
                
                // Definir constantes si no están ya definidas
                if (!defined('DEBUG_MODE')) define('DEBUG_MODE', $constants['DEBUG_MODE'] ?? false);
                if (!defined('DIRECCION')) define('DIRECCION', $constants['DIRECCION'] ?? '');
                if (!defined('LOGO_FACTURA')) define('LOGO_FACTURA', $constants['LOGO_FACTURA'] ?? '');
                if (!defined('LOGO_VENTAS')) define('LOGO_VENTAS', $constants['LOGO_VENTAS'] ?? '');
                if (!defined('NIT')) define('NIT', $constants['NIT'] ?? '');
                if (!defined('NOMBRE_NEGOCIO')) define('NOMBRE_NEGOCIO', $constants['NOMBRE_NEGOCIO'] ?? '');
                if (!defined('SERIE_NUMERO_FACTURA')) define('SERIE_NUMERO_FACTURA', intval($constants['SERIE_NUMERO_FACTURA'] ?? 0));
                if (!defined('TAX_RATE')) define('TAX_RATE', intval($constants['TAX_RATE'] ?? 0));
                if (!defined('TELEFONO')) define('TELEFONO', $constants['TELEFONO'] ?? '');
                if (!defined('URL_WOOCOMMERCE')) define('URL_WOOCOMMERCE', $constants['URL_WOOCOMMERCE'] ?? '');
                if (!defined('VENTAS_URL')) define('VENTAS_URL', $constants['VENTAS_URL'] ?? '');
                
                $this->logger->info('Constants loaded from sales database', array(
                    'constants_loaded' => array_keys($constants)
                ));
                
                $result->free();
            } else {
                throw new Exception('Error loading constants: ' . $this->ventas_db->error);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error loading constants from database', array('error' => $e->getMessage()));
            
            // Definir valores por defecto si falla la carga
            if (!defined('DEBUG_MODE')) define('DEBUG_MODE', false);
            if (!defined('DIRECCION')) define('DIRECCION', '');
            if (!defined('LOGO_FACTURA')) define('LOGO_FACTURA', '');
            if (!defined('LOGO_VENTAS')) define('LOGO_VENTAS', '');
            if (!defined('NIT')) define('NIT', '');
            if (!defined('NOMBRE_NEGOCIO')) define('NOMBRE_NEGOCIO', '');
            if (!defined('SERIE_NUMERO_FACTURA')) define('SERIE_NUMERO_FACTURA', 1);
            if (!defined('TAX_RATE')) define('TAX_RATE', 0);
            if (!defined('TELEFONO')) define('TELEFONO', '');
            if (!defined('URL_WOOCOMMERCE')) define('URL_WOOCOMMERCE', '');
        }
    }
    
    /**
     * Agregar metabox de facturación en detalles del pedido
     */
    public function add_invoice_metabox() {
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
            
        add_meta_box(
            'sandcat_invoice_generator',
            __('Generar Factura SandCat', 'sandcat-invoice'),
            array($this, 'invoice_metabox_content'),
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Contenido del metabox de facturación
     */
    public function invoice_metabox_content($post_or_order_object) {
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        
        if (!$order) {
            echo '<p>' . __('Error: No se pudo cargar el pedido.', 'sandcat-invoice') . '</p>';
            return;
        }
        
        $order_id = $order->get_id();
        
        // Verificar si ya existe factura
        $existing_invoice = $this->get_existing_invoice($order_id);
        
        echo '<div id="sandcat-invoice-container">';
        
        if ($existing_invoice) {
            echo '<div class="notice notice-info inline">';
            var_dump($existing_invoice);
            echo '<p><strong>' . __('Factura existente:', 'sandcat-invoice') . '</strong></p>';
            echo '<p>' . sprintf(__('Número: %s', 'sandcat-invoice'), $existing_invoice['factura']) . '</p>';
            echo '<p>' . sprintf(__('Fecha: %s', 'sandcat-invoice'), $existing_invoice['fecha_creacion']) . '</p>';
            echo '</div>';
            
            echo '<button type="button" class="button button-secondary" id="view-invoice" data-order-id="' . $order_id . '">';
            echo __('Ver Factura', 'sandcat-invoice');
            echo '</button>';
        } else {
            echo '<p>' . __('Este pedido aún no tiene factura generada.', 'sandcat-invoice') . '</p>';
            
            echo '<button type="button" class="button button-primary" id="generate-invoice" data-order-id="' . $order_id . '">';
            echo __('Generar Factura', 'sandcat-invoice');
            echo '</button>';
        }
        
        echo '<div id="invoice-loading" style="display:none;">';
        echo '<p><span class="spinner is-active"></span> ' . __('Generando factura...', 'sandcat-invoice') . '</p>';
        echo '</div>';
        
        echo '<div id="invoice-result"></div>';
        echo '</div>';
        
        // Nonce para seguridad
        wp_nonce_field('sandcat_invoice_action', 'sandcat_invoice_nonce');
    }
    
    /**
     * Verificar si existe factura para el pedido
     */
    private function get_existing_invoice($order_id) {
        if (!$this->ventas_db) {
            return false;
        }
        
        // Verificar si la conexión está activa
        if ($this->ventas_db->ping() === false) {
            $this->logger->error('Database connection lost in get_existing_invoice');
            return false;
        }
        
        $stmt = $this->ventas_db->prepare("SELECT id_facturas, id_order, factura, fecha_creacion, estado FROM facturas WHERE id_order = ? LIMIT 1");
        
        // Verificar si prepare() fue exitoso
        if ($stmt === false) {
            $this->logger->error('Error preparing SELECT statement', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $stmt->bind_param('i', $order_id);
        
        if (!$stmt->execute()) {
            $this->logger->error('Error executing SELECT statement', array('mysql_error' => $stmt->error));
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        
        if ($result === false) {
            $this->logger->error('Error getting SELECT result', array('mysql_error' => $stmt->error));
            $stmt->close();
            return false;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row : false;
    }
    
    /**
     * Enqueue scripts y estilos para admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        $this->logger->info('Enqueue admin scripts called', array(
            'hook' => $hook,
            'post_type' => $post_type,
            'get_current_screen' => get_current_screen() ? get_current_screen()->id : 'null'
        ));
        
        // Verificar si estamos en una página de pedidos (compatible con HPOS)
        $is_order_page = false;
        
        // Método tradicional
        if (('post.php' === $hook || 'post-new.php' === $hook) && 'shop_order' === $post_type) {
            $is_order_page = true;
        }
        
        // Método HPOS
        $current_screen = get_current_screen();
        if ($current_screen && (
            $current_screen->id === 'woocommerce_page_wc-orders' ||
            strpos($current_screen->id, 'shop-order') !== false ||
            strpos($hook, 'wc-orders') !== false
        )) {
            $is_order_page = true;
        }
        
        // También cargar en páginas de WooCommerce admin
        if (strpos($hook, 'woocommerce') !== false) {
            $is_order_page = true;
        }
        
        // Verificar por URL si contiene order o pedido
        if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            $is_order_page = true;
        }
        
        // Verificar si estamos editando un pedido específico
        if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $is_order_page = true;
        }
        
        $this->logger->info('Order page check result', array('is_order_page' => $is_order_page));
        
        if ($is_order_page) {
            $this->logger->info('Loading admin scripts');
            
            wp_enqueue_script(
                'sandcat-invoice-admin',
                SANDCAT_INVOICE_PLUGIN_URL . 'assets/admin.js',
                array('jquery'),
                SANDCAT_INVOICE_VERSION,
                true
            );
            
            wp_localize_script('sandcat-invoice-admin', 'sandcat_invoice_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sandcat_invoice_nonce'),
                'messages' => array(
                    'generating' => __('Generando factura...', 'sandcat-invoice'),
                    'success' => __('Factura generada exitosamente', 'sandcat-invoice'),
                    'error' => __('Error generando factura', 'sandcat-invoice')
                )
            ));
            
            $this->logger->info('Admin scripts loaded successfully');
        }
    }
    
    /**
     * Asegurar que el script se carga en páginas de pedidos
     */
    public function ensure_script_loaded() {
        // Verificar si estamos en una página que podría tener pedidos
        $current_screen = get_current_screen();
        if (!$current_screen) {
            return;
        }
        
        $should_load = false;
        
        // Verificar si es una página de WooCommerce
        if (strpos($current_screen->id, 'woocommerce') !== false ||
            strpos($current_screen->id, 'shop-order') !== false ||
            strpos($current_screen->id, 'wc-orders') !== false ||
            (isset($_GET['page']) && $_GET['page'] === 'wc-orders')) {
            $should_load = true;
        }
        
        if ($should_load && !wp_script_is('sandcat-invoice-admin', 'enqueued')) {
            $this->logger->info('Loading script via admin_footer fallback');
            
            wp_enqueue_script(
                'sandcat-invoice-admin',
                SANDCAT_INVOICE_PLUGIN_URL . 'assets/admin.js',
                array('jquery'),
                SANDCAT_INVOICE_VERSION,
                true
            );
            
            wp_localize_script('sandcat-invoice-admin', 'sandcat_invoice_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sandcat_invoice_nonce'),
                'messages' => array(
                    'generating' => __('Generando factura...', 'sandcat-invoice'),
                    'success' => __('Factura generada exitosamente', 'sandcat-invoice'),
                    'error' => __('Error generando factura', 'sandcat-invoice')
                )
            ));
        }
    }
    
    /**
     * Asegurar que las constantes estén cargadas
     */
    private function ensure_constants_loaded() {
        $this->logger->info('ensure_constants_loaded called', array(
            'NOMBRE_NEGOCIO_defined' => defined('NOMBRE_NEGOCIO'),
            'NIT_defined' => defined('NIT'),
            'NOMBRE_NEGOCIO_value' => defined('NOMBRE_NEGOCIO') ? NOMBRE_NEGOCIO : 'NOT_DEFINED',
            'NIT_value' => defined('NIT') ? NIT : 'NOT_DEFINED'
        ));
        
        // Si las constantes ya están definidas y no están vacías, no hacer nada
        if (defined('NOMBRE_NEGOCIO') && defined('NIT') && !empty(NOMBRE_NEGOCIO) && !empty(NIT)) {
            $this->logger->info('Constants already loaded and not empty');
            return;
        }
        
        $this->logger->info('Constants not loaded or empty, attempting to load from database');
        
        // Si no hay conexión a la DB, establecerla
        if (!$this->ventas_db) {
            $this->logger->info('No database connection, initializing...');
            $this->init_ventas_db_connection();
        } else {
            // Si hay conexión, solo cargar las constantes
            $this->logger->info('Database connection exists, loading constants...');
            $this->load_constants_from_db();
        }
        
        // Log final para verificar si se cargaron
        $this->logger->info('Constants after loading attempt', array(
            'NOMBRE_NEGOCIO_defined' => defined('NOMBRE_NEGOCIO'),
            'NIT_defined' => defined('NIT'),
            'NOMBRE_NEGOCIO_value' => defined('NOMBRE_NEGOCIO') ? NOMBRE_NEGOCIO : 'NOT_DEFINED',
            'NIT_value' => defined('NIT') ? NIT : 'NOT_DEFINED'
        ));
    }
    
    /**
     * AJAX handler para generar factura
     */
    public function ajax_generate_invoice() {
        // Asegurar que las constantes estén cargadas
        $this->ensure_constants_loaded();
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sandcat_invoice_nonce')) {
            wp_die(__('Error de seguridad', 'sandcat-invoice'));
        }
        
        // Verificar permisos
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Permisos insuficientes', 'sandcat-invoice'));
        }
        
        $order_id = intval($_POST['order_id']);

        try {
            // Verificar si existe una factura para este pedido
            $existing_invoice = $this->get_existing_invoice($order_id);
            if ($existing_invoice && $existing_invoice['estado'] === 'c') {
                wp_send_json_error(array(
                    'message' => sprintf(__('La factura #%s fue cancelada desde el sistema de ventas y no puede ser regenerada', 'sandcat-invoice'), $existing_invoice['factura']),
                    'cancelled' => true,
                    'invoice_number' => $existing_invoice['factura']
                ));
                return;
            }
            
            // Generar número de factura
            $invoice_number = $this->get_next_invoice_number();
            
            if (!$invoice_number) {
                wp_send_json_error(array(
                    'message' => __('Error generando número de factura', 'sandcat-invoice')
                ));
                return;
            }
            
            // Obtener pedido
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error(array(
                    'message' => __('Pedido no encontrado', 'sandcat-invoice')
                ));
                return;
            }
            // Crear PDF temporal
            $pdf_result = $this->create_pdf_temp($order, $invoice_number);
            
            if (!$pdf_result['success']) {
                wp_send_json_error(array(
                    'message' => $pdf_result['error'] ?? __('Error generando PDF', 'sandcat-invoice')
                ));
                return;
            }
            
            // Guardar registro de factura
            $save_result = $this->save_invoice_record($order_id, $invoice_number, '');
            
            // Cambiar estado de la orden a completado automáticamente
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() !== 'completed') {
                $previous_status = $order->get_status();
                $previous_status_name = wc_get_order_status_name($previous_status);
                
                $order->update_status('completed', __('Orden completada automáticamente después de generar factura.', 'sandcat-invoice'));
                
                // Agregar nota adicional con el cambio de estado
                $bogota_time = new DateTime('now', new DateTimeZone('America/Bogota'));
                $order->add_order_note(sprintf(
                    __('Estado cambiado de "%s" a "Completado" automáticamente después de generar la factura #%s el %s.', 'sandcat-invoice'),
                    $previous_status_name,
                    $invoice_number,
                    $bogota_time->format('Y-m-d H:i:s')
                ));
                
                $this->logger->info('Order status changed to completed after invoice generation', array(
                    'order_id' => $order_id,
                    'invoice_number' => $invoice_number,
                    'previous_status' => $previous_status,
                    'new_status' => 'completed'
                ));
            }
            
            wp_send_json_success(array(
                'message' => __('Factura generada exitosamente', 'sandcat-invoice'),
                'invoice_number' => $invoice_number,
                'pdf_url' => admin_url('admin-ajax.php?action=stream_invoice_pdf&order_id=' . $order_id)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Error in ajax_generate_invoice', array('error' => $e->getMessage()));
            wp_send_json_error(array(
                'message' => __('Error interno del servidor', 'sandcat-invoice')
            ));
        }
    }
    
    /**
     * Obtener siguiente número de factura
     */
    private function get_next_invoice_number() {
        if (!$this->ventas_db) {
            $this->logger->error('No database connection in get_next_invoice_number');
            return false;
        }
        
        // Verificar si la conexión está activa
        if ($this->ventas_db->ping() === false) {
            $this->logger->error('Database connection lost in get_next_invoice_number');
            return false;
        }
        
        // Obtener número actual
        $result = $this->ventas_db->query("SELECT valor FROM configuracion WHERE clave = 'SERIE_NUMERO_FACTURA' LIMIT 1");
        
        if (!$result) {
            $this->logger->error('Error querying configuracion table', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $row = $result->fetch_assoc();
        if (!$row) {
            $this->logger->warning('No configuration found in configuracion table');
            return false;
        }
        
        $current_number = intval($row['valor']);
        $next_number = $current_number + 1;
        
        // Incrementar número en base de datos
        $stmt = $this->ventas_db->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'SERIE_NUMERO_FACTURA'");
        
        if ($stmt === false) {
            $this->logger->error('Error preparing UPDATE statement', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $stmt->bind_param('i', $next_number);
        
        if (!$stmt->execute()) {
            $this->logger->error('Error executing UPDATE statement', array('mysql_error' => $stmt->error));
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        $this->logger->info('Successfully generated new invoice number', array('invoice_number' => $next_number));
        return $next_number;
    }
    
    /**
     * Guardar registro de factura en base de datos
     */
    private function save_invoice_record($order_id, $invoice_number, $pdf_path) {
        if (!$this->ventas_db) {
            $this->logger->error('No database connection in save_invoice_record');
            return false;
        }
        
        // Verificar si la conexión está activa
        if ($this->ventas_db->ping() === false) {
            $this->logger->error('Database connection lost in save_invoice_record');
            return false;
        }
        
        $stmt = $this->ventas_db->prepare("INSERT INTO facturas (id_order, factura, fecha_creacion, estado) VALUES (?, ?, NOW(), 'a')");
        
        if ($stmt === false) {
            $this->logger->error('Error preparing INSERT statement for facturas', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $stmt->bind_param('ii', $order_id, $invoice_number);
        
        if (!$stmt->execute()) {
            $this->logger->error('Error executing INSERT statement for facturas', array('mysql_error' => $stmt->error));
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        $this->logger->info('Successfully saved invoice record', array(
            'order_id' => $order_id,
            'invoice_number' => $invoice_number
        ));
        return true;
    }
    
    /**
     * Crear PDF usando mPDF con template HTML (temporal, no guarda archivo)
     */
    private function create_pdf_temp($order, $invoice_number) {
        // Verificar si mPDF está disponible
        if (!class_exists('\Mpdf\Mpdf')) {
            // Intentar cargar desde vendor del plugin
            $vendor_path = SANDCAT_INVOICE_PLUGIN_PATH . 'vendor/autoload.php';
            $this->logger->info('Checking mPDF vendor path', array('path' => $vendor_path, 'exists' => file_exists($vendor_path)));
            
            if (file_exists($vendor_path)) {
                require_once $vendor_path;
                $this->logger->info('mPDF vendor loaded successfully');
            } else {
                $this->logger->error('mPDF vendor not found', array('path' => $vendor_path));
                return array('success' => false, 'error' => 'mPDF no está disponible. Ejecute: composer install en el directorio del plugin');
            }
        }

        // Verificar nuevamente después de cargar
        if (!class_exists('\Mpdf\Mpdf')) {
            $this->logger->error('mPDF class still not available after vendor load');
            return array('success' => false, 'error' => 'mPDF class no disponible después de cargar vendor');
        }
        
        try {
            // Configuración de mPDF igual que mpdf_config.php
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => [80, 297], // 80mm x 297mm (formato ticket)
                'default_font_size' => 8,
                'margin_left' => 2,
                'margin_right' => 1,
                'margin_top' => 1,
                'margin_bottom' => 1,
                'margin_header' => 0,
                'margin_footer' => 0,
                'orientation' => 'P',
                // Habilitar soporte para códigos QR y de barras
                'enableBarcodes' => true,
                'debug' => false
            ]);
            
            // Generar HTML usando template
            $html = $this->generate_pdf_from_template($order, $invoice_number);
            
            // Escribir HTML al PDF
            $mpdf->WriteHTML($html);
            
            $this->logger->info('PDF generated successfully (temporary)', array(
                'invoice_number' => $invoice_number,
                'order_id' => $order->get_id()
            ));
            
            return array(
                'success' => true,
                'mpdf_object' => $mpdf,
                'invoice_number' => $invoice_number,
                'order_id' => $order->get_id()
            );
            
        } catch (Exception $e) {
            $this->logger->error('Error generating temporary PDF', array(
                'error' => $e->getMessage(),
                'invoice_number' => $invoice_number,
                'order_id' => $order->get_id()
            ));
            return array('success' => false, 'error' => 'Error generando PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear PDF usando mPDF con template HTML (mantener compatibilidad)
     */
    private function create_pdf($order, $invoice_number) {
        return $this->create_pdf_temp($order, $invoice_number);
    }
    
    /**
     * Generar HTML usando template con placeholders
     */
    private function generate_pdf_from_template($order, $invoice_number) {
        // Cargar template HTML
        $template_path = SANDCAT_INVOICE_PLUGIN_PATH . 'templates/pdf_factura.html';
        if (!file_exists($template_path)) {
            throw new Exception('Template de factura no encontrado: ' . $template_path);
        }
        
        $html_template = file_get_contents($template_path);
        
        // Obtener datos del pedido
        $order_data = $this->extract_order_data($order);
        
        // Procesar datos para el template
        $template_data = $this->prepare_template_data($order, $invoice_number, $order_data);
        
        // Reemplazar placeholders en el template
        foreach ($template_data as $placeholder => $value) {
            $html_template = str_replace('{{' . $placeholder . '}}', $value, $html_template);
        }
        
        // Debug: Log algunos reemplazos importantes
        $this->logger->info('Template placeholders replaced', array(
            'nombre_negocio_replaced' => strpos($html_template, '{{nombre_negocio}}') === false,
            'nit_replaced' => strpos($html_template, '{{nit}}') === false,
            'telefono_negocio_replaced' => strpos($html_template, '{{telefono_negocio}}') === false,
            'direccion_negocio_replaced' => strpos($html_template, '{{direccion_negocio}}') === false
        ));
        
        return $html_template;
    }
    
    /**
     * Preparar datos para el template
     */
    private function prepare_template_data($order, $invoice_number, $order_data) {
        // Verificar si la factura está cancelada
        $existing_invoice = $this->get_existing_invoice($order->get_id());
        $is_cancelled = $existing_invoice && $existing_invoice['estado'] === 'c';
        
        // Datos básicos
        $data = array(
            'factura_num' => $invoice_number,
            'factura_formateada' => sprintf('%010d', $invoice_number),
            'fecha' => date('d/m/Y H:i', strtotime($order_data['fecha'])),
            'orden_id' => $order_data['id'],
            'nombre_cliente_upper' => strtoupper($order_data['nombre_completo']),
            'apellido_cliente_upper' => '', // Ya incluido en nombre_completo
            'celular' => $order_data['telefono'],
            'correo' => $order_data['email'],
            'total_formateado' => number_format($order_data['total'], 0, '.', ','),
            'logo_factura' => $this->get_logo_url(),
            'woocommerce_url' => defined('URL_WOOCOMMERCE') ? URL_WOOCOMMERCE : home_url(),
            'woocommerce_url_pedido' => $order->get_view_order_url(),
            // Datos de la empresa desde la base de datos de ventas
            'nombre_negocio' => defined('NOMBRE_NEGOCIO') ? NOMBRE_NEGOCIO : '',
            'nit' => defined('NIT') ? NIT : '',
            'telefono_negocio' => defined('TELEFONO') ? TELEFONO : '',
            'direccion_negocio' => defined('DIRECCION') ? DIRECCION : ''
        );
        
        // Secciones condicionales
        $data['metodo_pago_section'] = $this->get_metodo_pago_section($order_data['metodo_pago']);
        $data['dni_section'] = $this->get_dni_section($order_data['dni']);
        $data['documento_section'] = ''; // Placeholder para compatibilidad
        $data['direccion_completa_section'] = $this->get_direccion_section($order_data);
        $data['barrio_section'] = $this->get_barrio_section($order_data['barrio']);
        $data['ubicacion_section'] = $this->get_ubicacion_section($order_data);
        $data['direccion_section'] = ''; // Ya incluido en direccion_completa_section
        $data['comentarios_section'] = $this->get_comentarios_section($order);
        
        // Productos
        $data['productos_html'] = $this->get_productos_html($order);
        
        // Totales
        $data['envio_section'] = $this->get_envio_section($order_data['total_envio']);
        $data['descuento_section'] = $this->get_descuento_section($order_data['total_descuento']);
        
        // Overlay de cancelación
        $data['cancelada_overlay'] = $is_cancelled ? $this->get_cancelada_overlay($existing_invoice) : '';
        
        // Debug: Log template data para verificar que se están pasando correctamente
        $this->logger->info('Template data prepared', array(
            'nombre_negocio' => $data['nombre_negocio'],
            'nit' => $data['nit'],
            'telefono_negocio' => $data['telefono_negocio'],
            'direccion_negocio' => $data['direccion_negocio'],
            'constants_defined' => array(
                'NOMBRE_NEGOCIO' => defined('NOMBRE_NEGOCIO'),
                'NIT' => defined('NIT'),
                'TELEFONO' => defined('TELEFONO'),
                'DIRECCION' => defined('DIRECCION')
            )
        ));
        
        return $data;
    }
    
    /**
     * Obtener URL del logo
     */
    private function get_logo_url() {
        // Usar logo de la base de datos de ventas si está disponible
        if (defined('LOGO_FACTURA') && !empty(LOGO_FACTURA) && defined('VENTAS_URL') && !empty(VENTAS_URL)) {
            return VENTAS_URL ."/". LOGO_FACTURA;
        }
        
        // Usar logo de WordPress como fallback
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            return $logo_url;
        }
        
        // Logo por defecto o placeholder
        return SANDCAT_INVOICE_PLUGIN_URL . 'assets/logo-default.png';
    }
    
    /**
     * Sección método de pago
     */
    private function get_metodo_pago_section($metodo_pago) {
        if (empty($metodo_pago)) {
            return '';
        }
        
        return '<tr><td colspan="4"><strong>Método de Pago:</strong> ' . htmlspecialchars($metodo_pago) . '</td></tr>';
    }
    
    /**
     * Sección DNI (igual que EmailTemplate::processPDFTemplate)
     */
    private function get_dni_section($dni) {
        if (empty($dni)) {
            return '';
        }
        
        return '
            <tr>
                <td colspan="4"><strong>DNI:</strong> ' . htmlspecialchars($dni) . '</td>
            </tr>';
    }
    
    /**
     * Sección dirección
     */
    private function get_direccion_section($order_data) {
        $direccion_parts = array();
        
        if (!empty($order_data['direccion'])) {
            $direccion_parts[] = $order_data['direccion'];
        }
        
        if (!empty($order_data['direccion_2'])) {
            $direccion_parts[] = $order_data['direccion_2'];
        }
        
        if (empty($direccion_parts)) {
            return '';
        }
        
        $direccion_completa = implode(', ', $direccion_parts);
        return '<tr><td colspan="4" style="word-wrap: break-word; width: 180"><strong>Dirección:</strong> ' . htmlspecialchars($direccion_completa) . '</td></tr>';
    }
    
    /**
     * Sección barrio (igual que EmailTemplate::processPDFTemplate)
     */
    private function get_barrio_section($barrio) {
        if (empty($barrio)) {
            return '';
        }
        
        return '
            <tr>
                <td colspan="4" style="word-wrap: break-word; width: 180"><strong>Barrio:</strong> ' . htmlspecialchars($barrio) . '</td>
            </tr>';
    }
    
    /**
     * Sección ubicación (ciudad, departamento)
     */
    private function get_ubicacion_section($order_data) {
        $ubicacion_parts = array();
        
        if (!empty($order_data['ciudad'])) {
            $ubicacion_parts[] = $order_data['ciudad'];
        }
        
        if (!empty($order_data['departamento'])) {
            // Convertir código de departamento a nombre si es necesario
            $departamento_nombre = $this->convert_department_code($order_data['departamento']);
            $ubicacion_parts[] = $departamento_nombre;
        }
        
        if (!empty($order_data['pais'])) {
            $pais_nombre = ($order_data['pais'] === 'CO') ? 'Colombia' : $order_data['pais'];
            $ubicacion_parts[] = $pais_nombre;
        }
        
        if (empty($ubicacion_parts)) {
            return '';
        }
        
        $ubicacion = implode(', ', $ubicacion_parts);
        return '<tr><td colspan="4"><strong>Ubicación:</strong> ' . htmlspecialchars($ubicacion) . '</td></tr>';
    }
    
    /**
     * Convertir código de departamento a nombre
     */
    private function convert_department_code($code) {
        // Datos de departamentos de Colombia
        $departments = array(
            'ANT' => 'Antioquia',
            'ATL' => 'Atlántico', 
            'BOG' => 'Bogotá D.C.',
            'BOL' => 'Bolívar',
            'BOY' => 'Boyacá',
            'CAL' => 'Caldas',
            'CAQ' => 'Caquetá',
            'CAS' => 'Casanare',
            'CAU' => 'Cauca',
            'CES' => 'Cesar',
            'CHO' => 'Chocó',
            'COR' => 'Córdoba',
            'CUN' => 'Cundinamarca',
            'GUA' => 'Guainía',
            'GUV' => 'Guaviare',
            'HUI' => 'Huila',
            'LAG' => 'La Guajira',
            'MAG' => 'Magdalena',
            'MET' => 'Meta',
            'NAR' => 'Nariño',
            'NSA' => 'Norte de Santander',
            'PUT' => 'Putumayo',
            'QUI' => 'Quindío',
            'RIS' => 'Risaralda',
            'SAN' => 'Santander',
            'SUC' => 'Sucre',
            'TOL' => 'Tolima',
            'VAC' => 'Valle del Cauca',
            'VAU' => 'Vaupés',
            'VID' => 'Vichada'
        );
        
        // Si el código contiene "CO-", extraer solo la parte del código
        if (strpos($code, 'CO-') === 0) {
            $code = substr($code, 3);
        }
        
        return isset($departments[strtoupper($code)]) ? $departments[strtoupper($code)] : $code;
    }
    
    /**
     * Sección comentarios/observaciones usando customer_note de miau_wc_orders
     */
    private function get_comentarios_section($order) {
        $comentarios = $this->get_customer_note_from_db($order->get_id());
        
        if (empty($comentarios)) {
            return '';
        }
        
        return '
            <tr>
                <td colspan="4" style="word-wrap: break-word; width: 180"><strong>Observaciones:</strong> ' . htmlspecialchars($comentarios) . '</td>
            </tr>';
    }
    
    /**
     * Obtener customer_note directamente de la tabla miau_wc_orders
     */
    private function get_customer_note_from_db($order_id) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'wc_orders';
            $query = $wpdb->prepare(
                "SELECT customer_note FROM {$table_name} WHERE id = %d",
                $order_id
            );
            
            $customer_note = $wpdb->get_var($query);
            
            if ($customer_note !== null) {
                $this->logger->info("Customer note found in wc_orders table", array(
                    'order_id' => $order_id,
                    'note_length' => strlen($customer_note)
                ));
                return $customer_note;
            }
            
            // Fallback: intentar obtener desde postmeta si no existe en wc_orders
            $fallback_note = get_post_meta($order_id, '_customer_note', true);
            if (!empty($fallback_note)) {
                $this->logger->info("Customer note found in postmeta fallback", array(
                    'order_id' => $order_id,
                    'note_length' => strlen($fallback_note)
                ));
                return $fallback_note;
            }
            
            $this->logger->info("No customer note found", array('order_id' => $order_id));
            return '';
            
        } catch (Exception $e) {
            $this->logger->error("Error getting customer note from database", array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
            
            // Fallback a método original en caso de error
            $order = wc_get_order($order_id);
            return $order ? $order->get_customer_note() : '';
        }
    }
    
    /**
     * HTML de productos
     */
    private function get_productos_html($order) {
        $html = '';
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            $total = $item->get_total();
            $subtotal = $item->get_subtotal();
            $unit_price = $total / $quantity;
            
            // Nombre del producto
            $product_name = $item->get_name();
            
            // SKU del producto
            $sku = '';
            if ($product) {
                $sku = $product->get_sku();
            }
            
            // Descripción con SKU
            $description = '';
            if (!empty($sku)) {
                $description .= '<span class="sku-text">SKU: ' . htmlspecialchars($sku) . '</span><br>';
            }
            $description .= htmlspecialchars($product_name);
            
            // Verificar si hay descuento
            $has_discount = $subtotal > $total;
            $unit_subtotal = $subtotal / $quantity;
            
            if ($has_discount) {
                // Mostrar precio original tachado y precio con descuento
                $price_html = '<span class="precio-tachado">' . number_format($unit_subtotal) . '</span><br>';
                $price_html .= '<span class="precio-descuento">' . number_format($unit_price) . '</span>';
            } else {
                $price_html = '<br>' . number_format($unit_price);
            }
            
            $html .= '<tr>
                <td style="text-align: center; vertical-align: top"><br>' . $quantity . '</td>
                <td style="word-wrap: break-word; width: 180; vertical-align: top">' . $description . '</td>
                <td style="text-align: center; vertical-align: top">' . $price_html . '</td>
                <td style="text-align: center; vertical-align: top"><br>' . number_format($total) . '</td>
            </tr>';
        }
        
        return $html;
    }
    
    /**
     * Obtener sección de envío (igual que EmailTemplate::processPDFTemplate)
     */
    private function get_envio_section($total_envio) {
        if ($total_envio > 0) {
            return '<tr>
                <td style="text-align: center; vertical-align: top"><br>1</td>
                <td style="vertical-align: top"><br>Domicilio</td>
                <td style="text-align: right; vertical-align: top"><br>' . number_format($total_envio) . '</td>
                <td style="text-align: right; vertical-align: top"><br>' . number_format($total_envio) . '</td>
            </tr>';
        }
        return '';
    }
    
    /**
     * Obtener sección de descuento (igual que EmailTemplate::processPDFTemplate)
     */
    private function get_descuento_section($total_descuento) {
        if ($total_descuento > 0) {
            return '<tr>
                <td style="text-align: center; vertical-align: top"><br>1</td>
                <td style="vertical-align: top"><br>Descuento</td>
                <td style="text-align: right; vertical-align: top"><br>-' . number_format($total_descuento) . '</td>
                <td style="text-align: right; vertical-align: top"><br>-' . number_format($total_descuento) . '</td>
            </tr>';
        }
        return '';
    }
    
    /**
     * Obtener overlay de factura cancelada
     */
    private function get_cancelada_overlay($cancelled_invoice) {
        if (!$cancelled_invoice) {
            return '';
        }
        
        $invoice_number = $cancelled_invoice['factura'];
        $fecha_cancelacion = date('d/m/Y', strtotime($cancelled_invoice['fecha_creacion']));
        
        return '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                           background: rgba(255, 0, 0, 0.1); border: 3px solid #ff0000; 
                           padding: 20px; text-align: center; font-size: 24px; font-weight: bold; 
                           color: #ff0000; z-index: 1000; width: 80%; max-width: 400px;">
                    <div style="font-size: 28px; margin-bottom: 10px;">FACTURA CANCELADA</div>
                    <div style="font-size: 18px; margin-bottom: 5px;">Número: ' . htmlspecialchars($invoice_number) . '</div>
                    <div style="font-size: 16px;">Fecha: ' . $fecha_cancelacion . '</div>
                    <div style="font-size: 14px; margin-top: 10px; font-style: italic;">
                        Esta factura fue cancelada desde el sistema de ventas
                    </div>
                </div>';
    }
    
    /**
     * Generar HTML para el PDF usando el template existente (método legacy)
     */
    private function generate_pdf_html($order, $invoice_number) {
        // Obtener datos del pedido
        $order_data = $this->extract_order_data($order);
        
        // CSS para el PDF (basado en el template existente)
        $css = $this->get_pdf_css();
        
        // HTML del PDF
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>' . $css . '</style>
</head>
<body>';
        
        // Header de la empresa
        $html .= $this->get_company_header($invoice_number, $order_data['fecha']);
        
        // Información del cliente
        $html .= $this->get_customer_info($order_data);
        
        // Tabla de productos
        $html .= $this->get_products_table($order);
        
        // Totales
        $html .= $this->get_totals_section($order_data);
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Truncar texto
     */
    private function truncate_text($text, $limit = 35) {
        if (mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit - 3, 'UTF-8') . '...';
        }
        return $text;
    }
    
    /**
     * Extraer datos del pedido de WooCommerce
     */
    private function extract_order_data($order) {
        return array(
            'id' => $order->get_id(),
            'fecha' => $order->get_date_created(),
            'nombre_completo' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'telefono' => $order->get_billing_phone(),
            'direccion' => $order->get_billing_address_1(),
            'direccion_2' => $order->get_billing_address_2(),
            'ciudad' => $order->get_billing_city(),
            'departamento' => $order->get_billing_state(),
            'pais' => $order->get_billing_country(),
            'metodo_pago' => $order->get_payment_method_title(),
            'subtotal' => $order->get_subtotal(),
            'total_envio' => $order->get_shipping_total(),
            'total_descuento' => $order->get_total_discount(),
            'total' => $order->get_total(),
            'dni' => $this->get_custom_field_value($order, 'dni', 'billing'),
            'barrio' => $this->get_custom_field_value($order, 'barrio', 'billing')
        );
    }
    
    /**
     * Obtener valor de campo personalizado con múltiples formatos de meta key
     */
    private function get_custom_field_value($order, $field_key, $address_type = 'billing') {
        // Intentar diferentes formatos de meta key
        $possible_keys = array(
            '_' . $address_type . '_' . $field_key,  // _billing_dni
            $address_type . '_' . $field_key,        // billing_dni
            '_' . $field_key,                        // _dni
            $field_key                               // dni
        );
        
        foreach ($possible_keys as $meta_key) {
            $value = $order->get_meta($meta_key, true);
            if (!empty($value)) {
                $this->logger->info("Custom field found", array(
                    'field' => $field_key,
                    'meta_key' => $meta_key,
                    'value' => $value,
                    'order_id' => $order->get_id()
                ));
                return $value;
            }
        }
        
        // Si no se encuentra, intentar obtener desde user meta si el cliente está logueado
        $customer_id = $order->get_customer_id();
        if ($customer_id > 0) {
            $user_meta_key = $address_type . '_' . $field_key;
            $value = get_user_meta($customer_id, $user_meta_key, true);
            if (!empty($value)) {
                $this->logger->info("Custom field found in user meta", array(
                    'field' => $field_key,
                    'user_meta_key' => $user_meta_key,
                    'value' => $value,
                    'customer_id' => $customer_id
                ));
                return $value;
            }
        }
        
        $this->logger->warning("Custom field not found", array(
            'field' => $field_key,
            'tried_keys' => $possible_keys,
            'order_id' => $order->get_id()
        ));
        
        return '';
    }
    
    /**
     * CSS para el PDF (basado en el template existente)
     */
    private function get_pdf_css() {
        return '
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-logo { max-width: 200px; height: auto; }
        .invoice-title { font-size: 18px; font-weight: bold; margin: 10px 0; }
        .customer-info { margin-bottom: 20px; }
        .customer-info table { width: 100%; border-collapse: collapse; }
        .customer-info td { padding: 5px; border-bottom: 1px solid #ddd; }
        .products-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .products-table th, .products-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .products-table th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { margin-top: 20px; }
        .totals table { width: 100%; }
        .totals td { padding: 5px; }
        .total-final { font-weight: bold; font-size: 14px; }
        .sku-text { font-size: 0.8em; color: #666; font-style: italic; }
        ';
    }
    
    /**
     * Header de la empresa
     */
    private function get_company_header($invoice_number, $fecha) {
        return '
        <div class="header">
            <h1 class="invoice-title">FACTURA DE VENTA</h1>
            <p><strong>Número:</strong> ' . $invoice_number . '</p>
            <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($fecha)) . '</p>
        </div>';
    }
    
    /**
     * Información del cliente
     */
    private function get_customer_info($data) {
        $html = '<div class="customer-info">
            <h3>Información del Cliente</h3>
            <table>
                <tr><td><strong>Nombre:</strong></td><td>' . htmlspecialchars($data['nombre_completo']) . '</td></tr>';
        
        if (!empty($data['dni'])) {
            $html .= '<tr><td><strong>DNI:</strong></td><td>' . htmlspecialchars($data['dni']) . '</td></tr>';
        }
        
        $html .= '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($data['email']) . '</td></tr>
                <tr><td><strong>Teléfono:</strong></td><td>' . htmlspecialchars($data['telefono']) . '</td></tr>
                <tr><td><strong>Dirección:</strong></td><td>' . htmlspecialchars($data['direccion']);
        
        if (!empty($data['direccion_2'])) {
            $html .= ' ' . htmlspecialchars($data['direccion_2']);
        }
        
        $html .= '</td></tr>';
        
        if (!empty($data['barrio'])) {
            $html .= '<tr><td><strong>Barrio:</strong></td><td>' . htmlspecialchars($data['barrio']) . '</td></tr>';
        }
        
        $html .= '<tr><td><strong>Ciudad:</strong></td><td>' . htmlspecialchars($data['ciudad']) . ', ' . htmlspecialchars($data['departamento']) . '</td></tr>
                <tr><td><strong>Método de Pago:</strong></td><td>' . htmlspecialchars($data['metodo_pago']) . '</td></tr>
            </table>
        </div>';
        
        return $html;
    }
    
    /**
     * Tabla de productos
     */
    private function get_products_table($order) {
        $html = '<table class="products-table">
            <thead>
                <tr>
                    <th class="text-center">Cant.</th>
                    <th>Descripción</th>
                    <th class="text-right">V. Unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();
            $total = $item->get_total();
            $unit_price = $total / $quantity;
            
            // Nombre del producto
            $product_name = $item->get_name();
            
            // SKU del producto
            $sku = '';
            if ($product) {
                $sku = $product->get_sku();
            }
            
            // Descripción completa con SKU
            $description = htmlspecialchars($product_name);
            if (!empty($sku)) {
                $description .= '<br><span class="sku-text">SKU: ' . htmlspecialchars($sku) . '</span>';
            }
            
            $html .= '<tr>
                <td class="text-center">' . $quantity . '</td>
                <td>' . $description . '</td>
                <td class="text-right">$' . number_format($unit_price, 0, ',', '.') . '</td>
                <td class="text-right">$' . number_format($total, 0, ',', '.') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Sección de totales
     */
    private function get_totals_section($data) {
        $html = '<div class="totals">
            <table>
                <tr>
                    <td class="text-right"><strong>Subtotal:</strong></td>
                    <td class="text-right">$' . number_format($data['subtotal'], 0, ',', '.') . '</td>
                </tr>';
        
        if ($data['total_envio'] > 0) {
            $html .= '<tr>
                <td class="text-right"><strong>Envío:</strong></td>
                <td class="text-right">$' . number_format($data['total_envio'], 0, ',', '.') . '</td>
            </tr>';
        }
        
        if ($data['total_descuento'] > 0) {
            $html .= '<tr>
                <td class="text-right"><strong>Descuento:</strong></td>
                <td class="text-right">-$' . number_format($data['total_descuento'], 0, ',', '.') . '</td>
            </tr>';
        }
        
        $html .= '<tr class="total-final">
                <td class="text-right"><strong>TOTAL:</strong></td>
                <td class="text-right">$' . number_format($data['total'], 0, ',', '.') . '</td>
            </tr>
            </table>
        </div>';
        
        return $html;
    }
    
    /**
     * Guardar factura en base de datos
     */
    private function save_invoice_to_db($order_id, $invoice_number) {
        if (!$this->ventas_db) {
            return false;
        }
        
        // Verificar si la conexión está activa
        if ($this->ventas_db->ping() === false) {
            $this->logger->error('Database connection lost in save_invoice_to_db');
            return false;
        }
        
        $fecha_actual = date('Y-m-d H:i:s');
        
        $stmt = $this->ventas_db->prepare("
            INSERT INTO facturas (
                factura, 
                id_order, 
                fecha_creacion, 
                estado
            ) VALUES (?, ?, ?, 'a')
        ");
        
        if ($stmt === false) {
            $this->logger->error('Error preparing INSERT statement', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $stmt->bind_param('iis', $invoice_number, $order_id, $fecha_actual);
        
        if (!$stmt->execute()) {
            $this->logger->error('Error executing INSERT statement', array('mysql_error' => $stmt->error));
            $stmt->close();
            return false;
        }
        
        $stmt->close();
        $this->logger->info('Successfully saved invoice to database', array('order_id' => $order_id, 'invoice_number' => $invoice_number));
        return true;
    }
    
    /**
     * Aviso de WooCommerce faltante
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Sand y Cat Invoice Generator requiere WooCommerce para funcionar.', 'sandcat-invoice');
        echo '</p></div>';
    }
    
    /**
     * Aviso de error de base de datos
     */
    public function database_error_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Sand y Cat Invoice Generator: Error conectando a la base de datos de ventas.', 'sandcat-invoice');
        echo '</p></div>';
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Verificar requisitos
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requiere WooCommerce para funcionar.', 'sandcat-invoice'));
        }
        
        // Crear directorio de facturas
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/sandcat-invoices/';
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
    }
    
    /**
     * AJAX handler para servir PDF directamente (temporal)
     */
    public function ajax_stream_invoice_pdf() {
        $this->logger->info('AJAX stream_invoice_pdf called', array('post_data' => $_POST, 'get_data' => $_GET));
        
        // Asegurar que las constantes estén cargadas
        $this->logger->info('ajax_stream_invoice_pdf - loading constants');
        $this->ensure_constants_loaded();
        // Verificar nonce (solo para peticiones POST, GET puede venir sin nonce)
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_GET['nonce']) ? $_GET['nonce'] : '');
        if ($nonce && !wp_verify_nonce($nonce, 'sandcat_invoice_nonce')) {
            $this->logger->error('Nonce verification failed in stream PDF');
            wp_die(__('Error de seguridad', 'sandcat-invoice'));
        }
        
        // Verificar permisos
        if (!current_user_can('edit_shop_orders')) {
            $this->logger->error('Insufficient permissions for PDF streaming');
            wp_die(__('Permisos insuficientes', 'sandcat-invoice'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);
        
        try {
            // Verificar si existe factura
            $existing_invoice = $this->get_existing_invoice($order_id);
            if (!$existing_invoice) {
                wp_die(__('No se encontró factura para este pedido.', 'sandcat-invoice'));
            }
            
            // Obtener datos del pedido
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_die(__('Pedido no encontrado.', 'sandcat-invoice'));
            }
            
            // Generar PDF temporal
            $invoice_number = $existing_invoice['factura'];
            $pdf_result = $this->create_pdf_temp($order, $invoice_number);
            
            if ($pdf_result && $pdf_result['success'] && isset($pdf_result['mpdf_object'])) {
                $this->logger->info('Streaming PDF directly', array(
                    'order_id' => $order_id,
                    'invoice_number' => $invoice_number
                ));
                
                // Configurar headers para PDF
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="factura_' . $invoice_number . '_' . $order_id . '.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                // Generar nombre del archivo similar a pdf_generator.php
                $filename = "Factura " . sprintf('%010d', $invoice_number) . ".pdf";
                
                // Servir PDF directamente en línea (similar a pdf_generator.php)
                $pdf_result['mpdf_object']->Output($filename, 'I');
                exit;
                
            } else {
                $error_message = isset($pdf_result['error']) ? $pdf_result['error'] : __('Error generando PDF.', 'sandcat-invoice');
                wp_die($error_message);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Exception in ajax_stream_invoice_pdf', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
            wp_die(__('Error interno del servidor.', 'sandcat-invoice'));
        }
    }
    
    /**
     * AJAX handler para obtener URL del PDF de factura
     */
    public function ajax_get_invoice_pdf_url() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'sandcat_invoice_nonce')) {
                wp_send_json_error(__('Error de seguridad.', 'sandcat-invoice'));
                return;
            }
            
            $order_id = intval($_POST['order_id']);
            if (!$order_id) {
                wp_send_json_error(__('ID de pedido inválido.', 'sandcat-invoice'));
                return;
            }
            
            // Verificar si existe factura
            $invoice_data = $this->get_invoice_from_ventas_db($order_id);
            if (!$invoice_data) {
                wp_send_json_error(__('No se encontró factura para este pedido.', 'sandcat-invoice'));
                return;
            }
            
            // Generar URL para el PDF
            $pdf_url = admin_url('admin-ajax.php') . '?' . http_build_query(array(
                'action' => 'stream_invoice_pdf',
                'order_id' => $order_id,
                'nonce' => wp_create_nonce('sandcat_invoice_nonce')
            ));
            
            $this->logger->info('PDF URL generated successfully', array(
                'order_id' => $order_id,
                'invoice_number' => $invoice_data['numero_factura']
            ));
            
            wp_send_json_success(array(
                'pdf_url' => $pdf_url,
                'invoice_number' => $invoice_data['numero_factura']
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Error in ajax_get_invoice_pdf_url', array(
                'order_id' => $order_id ?? 0,
                'error' => $e->getMessage()
            ));
            wp_send_json_error(__('Error interno del servidor.', 'sandcat-invoice'));
        }
    }
    
    /**
     * Obtener datos de factura desde la base de datos de ventas
     */
    private function get_invoice_from_ventas_db($order_id) {
        if (!$this->ventas_db) {
            return false;
        }
        
        try {
            $stmt = $this->ventas_db->prepare("SELECT id_facturas, factura as numero_factura, fecha_creacion as fecha, id_order FROM facturas WHERE id_order = ? AND estado = 'a' LIMIT 1");
            
            if ($stmt === false) {
                $this->logger->error('Error preparing SELECT statement for facturas', array('mysql_error' => $this->ventas_db->error));
                return false;
            }
            
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $invoice_data = $result->fetch_assoc();
                $stmt->close();
                
                $this->logger->info('Invoice found in ventas database', array(
                    'order_id' => $order_id,
                    'invoice_number' => $invoice_data['numero_factura']
                ));
                
                return $invoice_data;
            }
            
            $stmt->close();
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting invoice from ventas database', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * AJAX handler para verificar estado de factura
     */
    public function ajax_check_invoice_status() {
        try {
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'], 'sandcat_invoice_nonce')) {
                wp_die(__('Error de seguridad.', 'sandcat-invoice'));
            }
            
            $order_id = intval($_POST['order_id']);
            if (!$order_id) {
                wp_send_json_error(__('ID de pedido inválido.', 'sandcat-invoice'));
                return;
            }
            
            // Verificar si existe factura para este pedido
            $existing_invoice = $this->get_existing_invoice($order_id);
            if ($existing_invoice) {
                if ($existing_invoice['estado'] === 'c') {
                    // Factura cancelada
                    wp_send_json_success(array(
                        'has_invoice' => true,
                        'is_cancelled' => true,
                        'invoice_number' => $existing_invoice['factura'],
                        'invoice_date' => $existing_invoice['fecha_creacion'],
                        'message' => sprintf(__('La factura #%s fue cancelada desde el sistema de ventas', 'sandcat-invoice'), $existing_invoice['factura'])
                    ));
                    return;
                } else {
                    // Factura activa
                    wp_send_json_success(array(
                        'has_invoice' => true,
                        'is_cancelled' => false,
                        'invoice_number' => $existing_invoice['factura'],
                        'invoice_date' => $existing_invoice['fecha_creacion']
                    ));
                    return;
                }
            }
            
            // Si no hay factura en la tabla facturas, verificar en la base de datos de ventas
            $invoice_data = $this->get_invoice_from_ventas_db($order_id);
            
            if ($invoice_data) {
                wp_send_json_success(array(
                    'has_invoice' => true,
                    'is_cancelled' => false,
                    'invoice_number' => $invoice_data['numero_factura'],
                    'invoice_date' => $invoice_data['fecha'],
                    'total' => $invoice_data['total']
                ));
            } else {
                wp_send_json_success(array(
                    'has_invoice' => false,
                    'is_cancelled' => false
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error in ajax_check_invoice_status', array(
                'order_id' => $order_id ?? 0,
                'error' => $e->getMessage()
            ));
            wp_send_json_error(__('Error interno del servidor.', 'sandcat-invoice'));
        }
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('Configuración SandCat Invoice', 'sandcat-invoice'),
            __('Sand&Cat Invoice', 'sandcat-invoice'),
            'manage_options',
            'sandcat-invoice-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        // Registrar grupo de configuraciones
        register_setting('sandcat_invoice_settings', 'sandcat_invoice_db_settings', array(
            'sanitize_callback' => array($this, 'sanitize_db_settings')
        ));
        
        // Sección de configuración de base de datos
        add_settings_section(
            'sandcat_invoice_db_section',
            __('Configuración de Base de Datos de Ventas', 'sandcat-invoice'),
            array($this, 'db_section_callback'),
            'sandcat-invoice-settings'
        );
        
        // Campos de configuración
        add_settings_field(
            'db_host',
            __('Host de Base de Datos', 'sandcat-invoice'),
            array($this, 'db_host_callback'),
            'sandcat-invoice-settings',
            'sandcat_invoice_db_section'
        );
        
        add_settings_field(
            'db_user',
            __('Usuario de Base de Datos', 'sandcat-invoice'),
            array($this, 'db_user_callback'),
            'sandcat-invoice-settings',
            'sandcat_invoice_db_section'
        );
        
        add_settings_field(
            'db_password',
            __('Contraseña de Base de Datos', 'sandcat-invoice'),
            array($this, 'db_password_callback'),
            'sandcat-invoice-settings',
            'sandcat_invoice_db_section'
        );
        
        add_settings_field(
            'db_name',
            __('Nombre de Base de Datos', 'sandcat-invoice'),
            array($this, 'db_name_callback'),
            'sandcat-invoice-settings',
            'sandcat_invoice_db_section'
        );
    }
    
    /**
     * Página de configuraciones
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('sandcat_invoice_settings');
                do_settings_sections('sandcat-invoice-settings');
                submit_button(__('Guardar Configuración', 'sandcat-invoice'));
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Probar Conexión', 'sandcat-invoice'); ?></h2>
                <p><?php _e('Haz clic en el botón para probar la conexión con la base de datos de ventas.', 'sandcat-invoice'); ?></p>
                <button type="button" id="test-db-connection" class="button button-secondary">
                    <?php _e('Probar Conexión', 'sandcat-invoice'); ?>
                </button>
                <div id="connection-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-db-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#connection-result');
                
                $button.prop('disabled', true).text('<?php _e('Probando...', 'sandcat-invoice'); ?>');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_sandcat_db_connection',
                        nonce: '<?php echo wp_create_nonce('test_db_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error"><p><?php _e('Error de conexión AJAX', 'sandcat-invoice'); ?></p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Probar Conexión', 'sandcat-invoice'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Callback para sección de base de datos
     */
    public function db_section_callback() {
        echo '<p>' . __('Configure los parámetros de conexión a la base de datos de ventas SandCat.', 'sandcat-invoice') . '</p>';
    }
    
    /**
     * Callback para campo host
     */
    public function db_host_callback() {
        $settings = get_option('sandcat_invoice_db_settings', array());
        $value = isset($settings['db_host']) ? $settings['db_host'] : 'localhost';
        echo '<input type="text" name="sandcat_invoice_db_settings[db_host]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Dirección del servidor de base de datos (ej: localhost)', 'sandcat-invoice') . '</p>';
    }
    
    /**
     * Callback para campo usuario
     */
    public function db_user_callback() {
        $settings = get_option('sandcat_invoice_db_settings', array());
        $value = isset($settings['db_user']) ? $settings['db_user'] : 'root';
        echo '<input type="text" name="sandcat_invoice_db_settings[db_user]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Nombre de usuario de la base de datos', 'sandcat-invoice') . '</p>';
    }
    
    /**
     * Callback para campo contraseña
     */
    public function db_password_callback() {
        $settings = get_option('sandcat_invoice_db_settings', array());
        $value = isset($settings['db_password']) ? $settings['db_password'] : '';
        echo '<input type="password" name="sandcat_invoice_db_settings[db_password]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Contraseña de la base de datos', 'sandcat-invoice') . '</p>';
    }
    
    /**
     * Callback para campo nombre de base de datos
     */
    public function db_name_callback() {
        $settings = get_option('sandcat_invoice_db_settings', array());
        $value = isset($settings['db_name']) ? $settings['db_name'] : 'ventassc';
        echo '<input type="text" name="sandcat_invoice_db_settings[db_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Nombre de la base de datos de ventas', 'sandcat-invoice') . '</p>';
    }
    
    /**
     * Sanitizar configuraciones de base de datos
     */
    public function sanitize_db_settings($input) {
        $sanitized = array();
        
        if (isset($input['db_host'])) {
            $sanitized['db_host'] = sanitize_text_field($input['db_host']);
        }
        
        if (isset($input['db_user'])) {
            $sanitized['db_user'] = sanitize_text_field($input['db_user']);
        }
        
        if (isset($input['db_password'])) {
            $sanitized['db_password'] = $input['db_password']; // No sanitizar contraseña para mantener caracteres especiales
        }
        
        if (isset($input['db_name'])) {
            $sanitized['db_name'] = sanitize_text_field($input['db_name']);
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler para probar conexión a base de datos
     */
    public function ajax_test_db_connection() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_db_connection')) {
            wp_send_json_error(array('message' => __('Error de seguridad.', 'sandcat-invoice')));
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos para realizar esta acción.', 'sandcat-invoice')));
            return;
        }
        
        try {
            // Obtener configuraciones guardadas
            $db_settings = get_option('sandcat_invoice_db_settings', array());
            
            $host = isset($db_settings['db_host']) ? $db_settings['db_host'] : 'localhost';
            $user = isset($db_settings['db_user']) ? $db_settings['db_user'] : 'root';
            $password = isset($db_settings['db_password']) ? $db_settings['db_password'] : '';
            $database = isset($db_settings['db_name']) ? $db_settings['db_name'] : 'ventassc';
            
            // Intentar conexión
            $test_connection = new mysqli($host, $user, $password, $database);
            
            if ($test_connection->connect_error) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Error de conexión: %s', 'sandcat-invoice'), $test_connection->connect_error)
                ));
                return;
            }
            
            // Probar una consulta simple
            $result = $test_connection->query("SELECT 1");
            if (!$result) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Error ejecutando consulta: %s', 'sandcat-invoice'), $test_connection->error)
                ));
                $test_connection->close();
                return;
            }
            
            // Verificar si existe la tabla configuracion
            $table_check = $test_connection->query("SHOW TABLES LIKE 'configuracion'");
            $has_config_table = $table_check && $table_check->num_rows > 0;
            
            // Verificar si existe la tabla facturas
            $table_check2 = $test_connection->query("SHOW TABLES LIKE 'facturas'");
            $has_facturas_table = $table_check2 && $table_check2->num_rows > 0;
            
            $test_connection->close();
            
            $message = __('✅ Conexión exitosa a la base de datos.', 'sandcat-invoice');
            
            if (!$has_config_table) {
                $message .= '<br>' . __('⚠️ Advertencia: No se encontró la tabla "configuracion".', 'sandcat-invoice');
            }
            
            if (!$has_facturas_table) {
                $message .= '<br>' . __('⚠️ Advertencia: No se encontró la tabla "facturas".', 'sandcat-invoice');
            }
            
            if ($has_config_table && $has_facturas_table) {
                $message .= '<br>' . __('✅ Todas las tablas necesarias están presentes.', 'sandcat-invoice');
            }
            
            wp_send_json_success(array('message' => $message));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', 'sandcat-invoice'), $e->getMessage())
            ));
        }
    }
    
    /**
     * Hook de desactivación
     */
    public function deactivate() {
        // Limpiar cualquier tarea programada si las hay
    }
}

// Inicializar el plugin
new SandCatInvoiceGenerator();
