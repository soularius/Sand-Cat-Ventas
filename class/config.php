<?php
/**
 * Configuración de Base de Datos
 * Solo contiene las clases de configuración de base de datos
 */

class DatabaseConfig {
    
    // Conexión a la base de datos del sistema de ventas
    public static function getVentasConnection() {
        $hostname = Utils::env('DB_VENTAS_HOST', 'localhost');
        $database = Utils::env('DB_VENTAS_NAME', 'ventassc');
        $username = Utils::env('DB_VENTAS_USER', 'root');
        $password = Utils::env('DB_VENTAS_PASS', '');
        
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
        $hostname = Utils::env('DB_WORDPRESS_HOST', 'localhost');
        $database = Utils::env('DB_WORDPRESS_NAME', 'miau');
        $username = Utils::env('DB_WORDPRESS_USER', 'root');
        $password = Utils::env('DB_WORDPRESS_PASS', '');
        
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
?>
