<?php
/**
 * Configuración de Base de Datos
 * Lee las variables de entorno desde el archivo .env
 */

// Función para cargar variables de entorno desde .env
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Archivo .env no encontrado en: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Separar clave=valor
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            $_ENV[$key] = $value;
        }
    }
}

// Cargar variables de entorno
loadEnv(__DIR__ . '/.env');

// Función para obtener variable de entorno con valor por defecto
function env($key, $default = null) {
    return isset($_ENV[$key]) ? $_ENV[$key] : $default;
}

// Configuración de conexiones de base de datos
class DatabaseConfig {
    
    // Conexión a la base de datos del sistema de ventas
    public static function getVentasConnection() {
        $hostname = env('DB_VENTAS_HOST', 'localhost');
        $database = env('DB_VENTAS_NAME', 'ventassc');
        $username = env('DB_VENTAS_USER', 'root');
        $password = env('DB_VENTAS_PASS', '');
        
        $connection = new mysqli($hostname, $username, $password, $database);
        
        if ($connection->connect_errno) {
            die("Fallo la conexión a MySQL ventassc: (" . $connection->connect_errno . ") " . $connection->connect_error);
        }
        
        if (!mysqli_set_charset($connection, "utf8mb4")) {
            printf("Error utf8mb4 ventassc: %s\n", mysqli_error($connection));
            exit();
        }
        
        return $connection;
    }
    
    // Conexión a la base de datos de WordPress/WooCommerce
    public static function getWordPressConnection() {
        $hostname = env('DB_WORDPRESS_HOST', 'localhost');
        $database = env('DB_WORDPRESS_NAME', 'miau');
        $username = env('DB_WORDPRESS_USER', 'root');
        $password = env('DB_WORDPRESS_PASS', '');
        
        $connection = new mysqli($hostname, $username, $password, $database);
        
        if ($connection->connect_errno) {
            die("Fallo la conexión a MySQL miau: (" . $connection->connect_errno . ") " . $connection->connect_error);
        }
        
        if (!mysqli_set_charset($connection, "utf8mb4")) {
            printf("Error utf8mb4 miau: %s\n", mysqli_error($connection));
            exit();
        }
        
        return $connection;
    }
}

// Crear las conexiones globales
$sandycat = DatabaseConfig::getVentasConnection();
$miau = DatabaseConfig::getWordPressConnection();

// Configurar zona horaria si está definida
if (env('TIMEZONE')) {
    date_default_timezone_set(env('TIMEZONE'));
}

// Cargar constantes del sistema
require_once(__DIR__ . '/constants.php');

// Cargar herramientas y funciones globales
require_once(__DIR__ . '/tools.php');
?>
