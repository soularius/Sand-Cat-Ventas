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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
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
            $this->ventas_db = new mysqli(
                'localhost',    // DB_VENTAS_HOST
                'root',         // DB_VENTAS_USER
                '',             // DB_VENTAS_PASS
                'ventassc'      // DB_VENTAS_NAME
            );
            
            if ($this->ventas_db->connect_error) {
                throw new Exception('Error conectando a base de datos de ventas: ' . $this->ventas_db->connect_error);
            }
            
            $this->ventas_db->set_charset('utf8');
            $this->logger->info('Successfully connected to sales database');
            
        } catch (Exception $e) {
            $this->logger->error('Error connecting to sales database', array('error' => $e->getMessage()));
            add_action('admin_notices', array($this, 'database_error_notice'));
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
        
        $stmt = $this->ventas_db->prepare("SELECT id_facturas, id_order, factura, fecha_creacion FROM facturas WHERE id_order = ? AND estado = 'a' LIMIT 1");
        
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
        
        // Solo cargar en páginas de pedidos
        if (('post.php' === $hook || 'post-new.php' === $hook) && 'shop_order' === $post_type) {
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
     * AJAX handler para generar factura
     */
    public function ajax_generate_invoice() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sandcat_invoice_nonce')) {
            wp_die(__('Error de seguridad', 'sandcat-invoice'));
        }
        
        // Verificar permisos
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Permisos insuficientes', 'sandcat-invoice'));
        }
        
        $order_id = intval($_POST['order_id']);
        $regenerate = isset($_POST['regenerate']) && $_POST['regenerate'] === 'true';
        
        try {
            $result = $this->generate_invoice_pdf($order_id, $regenerate);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => __('Factura generada exitosamente', 'sandcat-invoice'),
                    'invoice_number' => $result['invoice_number'],
                    'pdf_url' => $result['pdf_url']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['error'] ?? __('Error desconocido', 'sandcat-invoice')
                ));
            }
            
        } catch (Exception $e) {
            error_log('SandCat Invoice Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Error interno del servidor', 'sandcat-invoice')
            ));
        }
    }
    
    /**
     * Generar PDF de factura
     */
    private function generate_invoice_pdf($order_id, $regenerate = false) {
        // Obtener orden de WooCommerce
        $order = wc_get_order($order_id);
        if (!$order) {
            return array('success' => false, 'error' => 'Pedido no encontrado');
        }
        
        // Verificar si ya existe factura (a menos que sea regeneración)
        if (!$regenerate) {
            $existing = $this->get_existing_invoice($order_id);
            if ($existing) {
                return array('success' => false, 'error' => 'Ya existe una factura para este pedido');
            }
        }
        
        // Obtener número de factura
        $invoice_number = $this->get_next_invoice_number();
        if (!$invoice_number) {
            return array('success' => false, 'error' => 'Error obteniendo número de factura');
        }
        
        // Generar PDF
        $pdf_result = $this->create_pdf($order, $invoice_number);
        if (!$pdf_result['success']) {
            return $pdf_result;
        }
        
        // Guardar factura en base de datos (solo si no es regeneración)
        if (!$regenerate) {
            $save_result = $this->save_invoice_to_db($order_id, $invoice_number);
            if (!$save_result) {
                return array('success' => false, 'error' => 'Error guardando factura en base de datos');
            }
        }
        
        return array(
            'success' => true,
            'invoice_number' => $invoice_number,
            'pdf_url' => $pdf_result['pdf_url']
        );
    }
    
    /**
     * Obtener siguiente número de factura
     */
    private function get_next_invoice_number() {
        if (!$this->ventas_db) {
            return false;
        }
        
        // Verificar si la conexión está activa
        if ($this->ventas_db->ping() === false) {
            $this->logger->error('Database connection lost in get_next_invoice_number');
            return false;
        }
        
        // Obtener número actual
        $result = $this->ventas_db->query("SELECT SERIE_NUMERO_FACTURA FROM configuracion LIMIT 1");
        if (!$result) {
            $this->logger->error('Error querying configuracion table', array('mysql_error' => $this->ventas_db->error));
            return false;
        }
        
        $row = $result->fetch_assoc();
        if (!$row) {
            $this->logger->warning('No configuration found in configuracion table');
            return false;
        }
        
        $current_number = intval($row['SERIE_NUMERO_FACTURA']);
        $next_number = $current_number + 1;
        
        // Incrementar número en base de datos
        $stmt = $this->ventas_db->prepare("UPDATE configuracion SET SERIE_NUMERO_FACTURA = ?");
        
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
     * Crear PDF usando mPDF
     */
    private function create_pdf($order, $invoice_number) {
        // Verificar si mPDF está disponible
        if (!class_exists('\Mpdf\Mpdf')) {
            // Intentar cargar desde vendor del proyecto principal
            $vendor_path = ABSPATH . '../vendor/autoload.php';
            if (file_exists($vendor_path)) {
                require_once $vendor_path;
            } else {
                return array('success' => false, 'error' => 'mPDF no está disponible');
            }
        }
        
        try {
            // Configuración de mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'Letter',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'Arial'
            ]);
            
            // Generar HTML del PDF
            $html = $this->generate_pdf_html($order, $invoice_number);
            
            // Escribir HTML al PDF
            $mpdf->WriteHTML($html);
            
            // Crear directorio de facturas si no existe
            $upload_dir = wp_upload_dir();
            $invoice_dir = $upload_dir['basedir'] . '/sandcat-invoices/';
            if (!file_exists($invoice_dir)) {
                wp_mkdir_p($invoice_dir);
            }
            
            // Nombre del archivo PDF
            $filename = 'factura_' . $invoice_number . '_' . $order->get_id() . '.pdf';
            $filepath = $invoice_dir . $filename;
            
            // Guardar PDF
            $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
            
            // URL del PDF
            $pdf_url = $upload_dir['baseurl'] . '/sandcat-invoices/' . $filename;
            
            return array(
                'success' => true,
                'pdf_path' => $filepath,
                'pdf_url' => $pdf_url
            );
            
        } catch (Exception $e) {
            error_log('Error generando PDF: ' . $e->getMessage());
            return array('success' => false, 'error' => 'Error generando PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Generar HTML para el PDF usando el template existente
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
     * Extraer datos del pedido de WooCommerce
     */
    private function extract_order_data($order) {
        return array(
            'id' => $order->get_id(),
            'fecha' => $order->get_date_created()->format('Y-m-d H:i:s'),
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
            'dni' => $order->get_meta('_billing_dni'),
            'barrio' => $order->get_meta('_billing_barrio')
        );
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
            
            // Truncar nombre si es muy largo
            $product_name_truncated = $this->truncate_text($product_name, 35);
            
            // Descripción completa con SKU
            $description = htmlspecialchars($product_name_truncated);
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
     * Truncar texto
     */
    private function truncate_text($text, $limit = 35) {
        if (mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit - 3, 'UTF-8') . '...';
        }
        return $text;
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
     * AJAX handler para obtener URL del PDF de factura existente
     */
    public function ajax_get_invoice_pdf_url() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sandcat_invoice_nonce')) {
            wp_die(__('Error de seguridad', 'sandcat-invoice'));
        }
        
        // Verificar permisos
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Permisos insuficientes', 'sandcat-invoice'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        $this->logger->info('Getting PDF URL for existing invoice', array('order_id' => $order_id));
        
        try {
            // Verificar si existe factura
            $existing_invoice = $this->get_existing_invoice($order_id);
            
            if (!$existing_invoice) {
                wp_send_json_error(array(
                    'message' => __('No se encontró factura para este pedido.', 'sandcat-invoice')
                ));
                return;
            }
            
            // Obtener datos del pedido
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error(array(
                    'message' => __('Pedido no encontrado.', 'sandcat-invoice')
                ));
                return;
            }
            
            // Generar PDF con los datos existentes
            $invoice_number = $existing_invoice['factura'];
            $pdf_result = $this->create_pdf($order, $invoice_number);
            
            if ($pdf_result && isset($pdf_result['success']) && $pdf_result['success']) {
                $this->logger->info('PDF URL generated successfully', array(
                    'order_id' => $order_id,
                    'invoice_number' => $invoice_number
                ));
                
                wp_send_json_success(array(
                    'pdf_url' => $pdf_result['pdf_url'],
                    'invoice_number' => $invoice_number,
                    'message' => __('PDF generado correctamente.', 'sandcat-invoice')
                ));
            } else {
                $error_message = isset($pdf_result['message']) ? $pdf_result['message'] : __('Error generando PDF.', 'sandcat-invoice');
                
                $this->logger->error('Error generating PDF for viewing', array(
                    'order_id' => $order_id,
                    'error' => $error_message
                ));
                
                wp_send_json_error(array(
                    'message' => $error_message
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Exception in ajax_get_invoice_pdf_url', array(
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => __('Error interno del servidor.', 'sandcat-invoice')
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
