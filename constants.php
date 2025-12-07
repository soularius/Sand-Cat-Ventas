<?php
/**
 * Constantes Globales del Sistema
 * Definiciones de constantes utilizadas en todo el proyecto
 */

// Información del Sistema
define('SYSTEM_NAME', 'Sand&Cat Ventas');
define('SYSTEM_VERSION', '2.0.0');
define('SYSTEM_AUTHOR', 'Sand&Cat Team');

// URLs del Sistema
define('BASE_URL', env('BASE_URL', 'http://localhost/ventas/'));
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOADS_URL', BASE_URL . 'uploads/');

// Rutas del Sistema
define('ROOT_PATH', __DIR__ . '/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('LOGS_PATH', ROOT_PATH . 'logs/');

// Estados de Pedidos WooCommerce
define('WC_STATUS_PENDING', 'wc-pending');
define('WC_STATUS_PROCESSING', 'wc-processing');
define('WC_STATUS_ON_HOLD', 'wc-on-hold');
define('WC_STATUS_COMPLETED', 'wc-completed');
define('WC_STATUS_CANCELLED', 'wc-cancelled');
define('WC_STATUS_REFUNDED', 'wc-refunded');
define('WC_STATUS_FAILED', 'wc-failed');

// Estados del Sistema Local
define('VENTA_ESTADO_INICIADA', 'i');
define('VENTA_ESTADO_ACTIVA', 'a');
define('VENTA_ESTADO_CANCELADA', 'c');
define('VENTA_ESTADO_COMPLETADA', 'f');

// Estados de Facturas
define('FACTURA_ESTADO_ACTIVA', 'a');
define('FACTURA_ESTADO_INACTIVA', 'i');
define('FACTURA_ESTADO_CANCELADA', 'c');

// Tipos de Usuario
define('USER_TYPE_ADMIN', 'a');
define('USER_TYPE_VENDEDOR', 'v');
define('USER_TYPE_CLIENTE', 'c');

// Configuración de Paginación
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// Configuración de Archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Configuración de Sesión
define('SESSION_TIMEOUT', 3600); // 1 hora
define('SESSION_NAME', 'SANDYCAT_SESSION');

// Configuración de Seguridad
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración de Email
define('EMAIL_FROM_NAME', env('EMAIL_FROM_NAME', 'Sand&Cat Ventas'));
define('EMAIL_FROM_ADDRESS', env('EMAIL_FROM_ADDRESS', 'noreply@sandycat.com.co'));

// Configuración de Moneda
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_CODE', 'COP');
define('CURRENCY_DECIMALS', 0);

// Mensajes del Sistema
define('MSG_SUCCESS', 'success');
define('MSG_ERROR', 'error');
define('MSG_WARNING', 'warning');
define('MSG_INFO', 'info');

// Configuración de Logs
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_DEBUG', 'DEBUG');

// Configuración de Cache
define('CACHE_ENABLED', env('CACHE_ENABLED', false));
define('CACHE_LIFETIME', env('CACHE_LIFETIME', 3600));

// URLs de Redirección
define('LOGIN_SUCCESS_URL', 'admin.php');
define('LOGIN_FAILED_URL', 'index.php?error=1');
define('LOGOUT_URL', 'index.php?logout=1');
define('ACCESS_DENIED_URL', 'index.php?access=denied');

// Configuración de WordPress/WooCommerce
define('WP_TABLE_PREFIX', env('WP_TABLE_PREFIX', 'miau_'));
define('WP_POST_TYPE_ORDER', 'shop_order');
define('WP_POST_TYPE_PRODUCT', 'product');
define('WP_POST_TYPE_VARIATION', 'product_variation');

// Metakeys de WooCommerce
define('WC_META_BILLING_FIRST_NAME', '_billing_first_name');
define('WC_META_BILLING_LAST_NAME', '_billing_last_name');
define('WC_META_BILLING_EMAIL', '_billing_email');
define('WC_META_BILLING_PHONE', '_billing_phone');
define('WC_META_SHIPPING_ADDRESS_1', '_shipping_address_1');
define('WC_META_ORDER_TOTAL', '_order_total');
define('WC_META_CART_DISCOUNT', '_cart_discount');
define('WC_META_PRODUCT_STOCK', '_stock');
define('WC_META_STOCK_STATUS', '_stock_status');
define('WC_META_PRODUCT_PRICE', '_price');
define('WC_META_REGULAR_PRICE', '_regular_price');

// Estados de Stock
define('STOCK_IN_STOCK', 'instock');
define('STOCK_OUT_OF_STOCK', 'outofstock');
define('STOCK_ON_BACKORDER', 'onbackorder');

// Configuración de Fecha y Hora
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');

// Configuración de Números
define('NUMBER_DECIMAL_SEPARATOR', ',');
define('NUMBER_THOUSANDS_SEPARATOR', '.');

// Rutas de Archivos Importantes
define('CONFIG_FILE', ROOT_PATH . 'config.php');
define('TOOLS_FILE', ROOT_PATH . 'tools.php');
define('CONSTANTS_FILE', ROOT_PATH . 'constants.php');

// Configuración de Debug
define('DEBUG_ENABLED', env('DEBUG_MODE', false));
define('DEBUG_SHOW_ERRORS', DEBUG_ENABLED);
define('DEBUG_LOG_QUERIES', DEBUG_ENABLED);

// Información de Contacto
define('SUPPORT_EMAIL', env('SUPPORT_EMAIL', 'soporte@sandycat.com.co'));
define('SUPPORT_PHONE', env('SUPPORT_PHONE', '+57 300 123 4567'));
define('COMPANY_NAME', env('COMPANY_NAME', 'Sand&Cat'));
define('COMPANY_ADDRESS', env('COMPANY_ADDRESS', 'Bogotá, Colombia'));
?>
