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
    
    /**
     * Métodos para gestión de configuración
     */
    
    /**
     * Agregar o actualizar una clave de configuración
     * @param string $clave - Clave de configuración
     * @param string $valor - Valor de configuración
     * @param string $tipo - Tipo de configuración (FILE, TEXT, NUMBER)
     * @return bool - True si se guardó correctamente
     */
    public static function setConfigValue($clave, $valor, $tipo = 'TEXT') {
        $connection = self::getVentasConnection();
        
        try {
            // Sanitizar datos
            $clave = mysqli_real_escape_string($connection, $clave);
            $valor = mysqli_real_escape_string($connection, $valor);
            $tipo = mysqli_real_escape_string($connection, $tipo);
            
            // Validar tipo
            if (!in_array($tipo, ['FILE', 'TEXT', 'NUMBER'])) {
                $tipo = 'TEXT';
            }
            
            // Verificar si la clave ya existe
            $query_check = "SELECT id FROM configuracion WHERE clave = '$clave'";
            $result = mysqli_query($connection, $query_check);
            
            if (mysqli_num_rows($result) > 0) {
                // Actualizar valor existente
                $query = "UPDATE configuracion SET valor = '$valor', tipo = '$tipo' WHERE clave = '$clave'";
                Utils::logError("Actualizando configuración: $clave (tipo: $tipo)", 'INFO', 'config.php');
            } else {
                // Insertar nueva configuración
                $query = "INSERT INTO configuracion (clave, valor, tipo) VALUES ('$clave', '$valor', '$tipo')";
                Utils::logError("Creando nueva configuración: $clave (tipo: $tipo)", 'INFO', 'config.php');
            }
            
            $success = mysqli_query($connection, $query);
            
            if (!$success) {
                Utils::logError("Error guardando configuración $clave: " . mysqli_error($connection), 'ERROR', 'config.php');
                return false;
            }
            
            mysqli_close($connection);
            return true;
            
        } catch (Exception $e) {
            Utils::logError("Excepción en setConfigValue: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return false;
        }
    }
    
    /**
     * Obtener valor de configuración
     * @param string $clave - Clave de configuración
     * @param string $default - Valor por defecto si no existe
     * @return string - Valor de configuración
     */
    public static function getConfigValue($clave, $default = '') {
        $connection = self::getVentasConnection();
        
        try {
            $clave = mysqli_real_escape_string($connection, $clave);
            $query = "SELECT valor FROM configuracion WHERE clave = '$clave'";
            $result = mysqli_query($connection, $query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                mysqli_close($connection);
                return $row['valor'];
            }
            
            mysqli_close($connection);
            return $default;
            
        } catch (Exception $e) {
            Utils::logError("Error obteniendo configuración $clave: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return $default;
        }
    }
    
    /**
     * Obtener todas las configuraciones
     * @return array - Array asociativo con todas las configuraciones incluyendo tipo
     */
    public static function getAllConfig() {
        $connection = self::getVentasConnection();
        $config = [];
        
        try {
            $query = "SELECT clave, valor, tipo FROM configuracion ORDER BY clave";
            $result = mysqli_query($connection, $query);
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $config[] = [
                        'clave' => $row['clave'],
                        'valor' => $row['valor'],
                        'tipo' => $row['tipo'] ?? 'TEXT'
                    ];
                }
            }
            
            mysqli_close($connection);
            return $config;
            
        } catch (Exception $e) {
            Utils::logError("Error obteniendo todas las configuraciones: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return [];
        }
    }
    
    /**
     * Eliminar una configuración
     * @param string $clave - Clave de configuración a eliminar
     * @return bool - True si se eliminó correctamente
     */
    public static function deleteConfig($clave) {
        $connection = self::getVentasConnection();
        
        try {
            $clave = mysqli_real_escape_string($connection, $clave);
            $query = "DELETE FROM configuracion WHERE clave = '$clave'";
            $success = mysqli_query($connection, $query);
            
            if ($success) {
                Utils::logError("Configuración eliminada: $clave", 'INFO', 'config.php');
            } else {
                Utils::logError("Error eliminando configuración $clave: " . mysqli_error($connection), 'ERROR', 'config.php');
            }
            
            mysqli_close($connection);
            return $success;
            
        } catch (Exception $e) {
            Utils::logError("Excepción eliminando configuración: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return false;
        }
    }
    
    /**
     * Actualizar contraseña de usuario en tabla ingreso
     * @param string $username - Nombre de usuario (no se puede cambiar)
     * @param string $new_password - Nueva contraseña
     * @return bool - True si se actualizó correctamente
     */
    public static function updateUserPassword($username, $new_password) {
        $connection = self::getVentasConnection();
        
        try {
            // Sanitizar datos
            $username = mysqli_real_escape_string($connection, $username);
            
            // Hash de la nueva contraseña
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $hashed_password = mysqli_real_escape_string($connection, $hashed_password);
            
            // Verificar que el usuario existe
            $query_check = "SELECT id FROM ingreso WHERE elnombre = '$username'";
            $result = mysqli_query($connection, $query_check);
            
            if (mysqli_num_rows($result) == 0) {
                Utils::logError("Usuario no encontrado para actualizar contraseña: $username", 'WARNING', 'config.php');
                mysqli_close($connection);
                return false;
            }
            
            // Actualizar contraseña
            $query = "UPDATE ingreso SET lapass = '$hashed_password' WHERE elnombre = '$username'";
            $success = mysqli_query($connection, $query);
            
            if ($success) {
                Utils::logError("Contraseña actualizada para usuario: $username", 'INFO', 'config.php');
            } else {
                Utils::logError("Error actualizando contraseña para $username: " . mysqli_error($connection), 'ERROR', 'config.php');
            }
            
            mysqli_close($connection);
            return $success;
            
        } catch (Exception $e) {
            Utils::logError("Excepción actualizando contraseña: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return false;
        }
    }
    
    /**
     * Obtener lista de usuarios del sistema
     * @return array - Array con información de usuarios
     */
    public static function getSystemUsers() {
        $connection = self::getVentasConnection();
        $users = [];
        
        try {
            $query = "SELECT id, elnombre FROM ingreso ORDER BY elnombre";
            $result = mysqli_query($connection, $query);
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $users[] = [
                        'id' => $row['id'],
                        'username' => $row['elnombre']
                    ];
                }
            }
            
            mysqli_close($connection);
            return $users;
            
        } catch (Exception $e) {
            Utils::logError("Error obteniendo usuarios del sistema: " . $e->getMessage(), 'ERROR', 'config.php');
            mysqli_close($connection);
            return [];
        }
    }
}
?>
