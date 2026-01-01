<?php


// Configurar constantes de base de datos de ventas
define('DB_VENTAS_HOST', DatabaseConfig::getConfigValue('DB_VENTAS_HOST', ''));
define('DB_VENTAS_NAME', DatabaseConfig::getConfigValue('DB_VENTAS_NAME', ''));
define('DB_VENTAS_PASS', DatabaseConfig::getConfigValue('DB_VENTAS_PASS', ''));
define('DB_VENTAS_USER', DatabaseConfig::getConfigValue('DB_VENTAS_USER', ''));

// Configurar constantes de base de datos de wordpress
define('DB_WORDPRESS_HOST', DatabaseConfig::getConfigValue('DB_WORDPRESS_HOST', ''));
define('DB_WORDPRESS_NAME', DatabaseConfig::getConfigValue('DB_WORDPRESS_NAME', ''));
define('DB_WORDPRESS_PASS', DatabaseConfig::getConfigValue('DB_WORDPRESS_PASS', ''));
define('DB_WORDPRESS_USER', DatabaseConfig::getConfigValue('DB_WORDPRESS_USER', ''));

// Configuracion correos
define('MAIL_ENCRYPTION', DatabaseConfig::getConfigValue('MAIL_ENCRYPTION', 'tls'));
define('MAIL_FROM_ADDRESS', DatabaseConfig::getConfigValue('MAIL_FROM_ADDRESS', ''));
define('MAIL_FROM_NAME', DatabaseConfig::getConfigValue('MAIL_FROM_NAME', ''));
define('MAIL_HOST', DatabaseConfig::getConfigValue('MAIL_HOST', ''));
define('MAIL_MAILER', DatabaseConfig::getConfigValue('MAIL_MAILER', 'smtp'));
define('MAIL_PASSWORD', DatabaseConfig::getConfigValue('MAIL_PASSWORD', ''));
define('MAIL_PORT', intval(DatabaseConfig::getConfigValue('MAIL_PORT', '587')));
define('MAIL_USERNAME', DatabaseConfig::getConfigValue('MAIL_USERNAME', ''));

// configuracion globales
define('DEBUG_MODE', DatabaseConfig::getConfigValue('DEBUG_MODE', ''));
define('DIRECCION', DatabaseConfig::getConfigValue('DIRECCION', ''));
define('LOGO_FACTURA', DatabaseConfig::getConfigValue('LOGO_FACTURA', ''));
define('LOGO_VENTAS', DatabaseConfig::getConfigValue('LOGO_VENTAS', ''));
define('NIT', DatabaseConfig::getConfigValue('NIT', ''));
define('NOMBRE_NEGOCIO', DatabaseConfig::getConfigValue('NOMBRE_NEGOCIO', ''));
define('SERIE_NUMERO_FACTURA', intval(DatabaseConfig::getConfigValue('SERIE_NUMERO_FACTURA', '0')));
define('TAX_RATE', intval(DatabaseConfig::getConfigValue('TAX_RATE', '0')));
define('TELEFONO', DatabaseConfig::getConfigValue('TELEFONO', ''));
define('TIMEZONE', DatabaseConfig::getConfigValue('TIMEZONE', 'America/Bogota'));
define('URL_WOOCOMMERCE', DatabaseConfig::getConfigValue('URL_WOOCOMMERCE', 'http://localhost/MIAU/'));
define('VENTAS_URL', DatabaseConfig::getConfigValue('VENTAS_URL', 'http://localhost/ventas/'));
define('WOOCOMMERCE_ORDER_PATH', DatabaseConfig::getConfigValue('WOOCOMMERCE_ORDER_PATH', '/mi-cuenta/ver-pedido/{id_pedido}/'));