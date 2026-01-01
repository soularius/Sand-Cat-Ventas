<?php
/**
 * Autoloader del sistema
 * Carga todas las clases y configuraciones básicas
 */

// Cargar la clase de utilidades primero
require_once(__DIR__ . '/tools.php');

// Cargar variables de entorno (si existe el archivo)
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    Utils::loadEnv($envPath);
}

// Configurar zona horaria si está definida
if (Utils::env('TIMEZONE')) {
    date_default_timezone_set(Utils::env('TIMEZONE'));
}

// Cargar configuración de base de datos
require_once(__DIR__ . '/config.php');

// Crear las conexiones globales
$sandycat = DatabaseConfig::getVentasConnection();
$miau = DatabaseConfig::getWordPressConnection();

// Cargar constantes del sistema (está en la carpeta padre)
require_once(dirname(__DIR__) . '/constants.php');

// Cargar constantes del sistema
require_once(dirname(__DIR__) . '/class/load_const.php');


require_once(dirname(__DIR__) . '/class/tools.php');

// 2. Incluir el sistema de login dinámico
require_once(dirname(__DIR__) . '/parts/login_handler.php');

// Cargar configuración de mPDF
require_once(dirname(__DIR__) . '/mpdf_config.php');

// Cargar clases del sistema
require_once(__DIR__ . '/woocommerce_customer.php');



// Iniciar sesión si no está iniciada
if (!isset($_SESSION)) {
    session_start();
}
?>