<?php
/**
 * Clase de utilidades generales del sistema
 * Contiene todas las funciones auxiliares y de utilidad
 */

class Utils {
    
    /**
     * Función para cargar variables de entorno desde .env
     */
    public static function loadEnv($path) {
        if (!file_exists($path)) {
            return false; // No terminar ejecución si no existe
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
        return true;
    }
    
    /**
     * Función para obtener variable de entorno con valor por defecto
     */
    public static function env($key, $default = null) {
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * Función de autorización de acceso a páginas
     */
    public static function isAuthorized($strUsers, $strGroups, $UserName, $UserGroup) { 
        $isValid = false; 
        if (!empty($UserName)) { 
            $arrUsers = explode(",", $strUsers); 
            $arrGroups = explode(",", $strGroups); 
            
            if (in_array($UserName, $arrUsers)) { 
                $isValid = true; 
            } 
            
            if (in_array($UserGroup, $arrGroups)) { 
                $isValid = true; 
            } 
        } 
        return $isValid; 
    }

    /**
     * Función para sanitizar entrada de datos
     */
    public static function sanitizeInput($data, $connection = null) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        
        if ($connection) {
            $data = mysqli_real_escape_string($connection, $data);
        }
        
        return $data;
    }

    /**
     * Función para validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Función para verificar si el usuario está logueado
     */
    public static function isLoggedIn() {
        return isset($_SESSION['MM_Username']) && !empty($_SESSION['MM_Username']);
    }

    /**
     * Función para obtener información del usuario actual
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'username' => $_SESSION['MM_Username'] ?? '',
            'user_group' => $_SESSION['MM_UserGroup'] ?? '',
            'login_time' => $_SESSION['login_time'] ?? ''
        ];
    }

    /**
     * Función para formatear fechas
     */
    public static function formatDate($date, $format = 'd/m/Y H:i') {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '';
        }
        
        $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        }
        
        return $dateObj ? $dateObj->format($format) : $date;
    }

    /**
     * Función para formatear números como moneda
     */
    public static function formatCurrency($amount, $currency = '$') {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Función para generar token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Función para verificar token CSRF
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Función para redireccionar con mensaje
     */
    public static function redirectWithMessage($url, $message = '', $type = 'info') {
        if (!empty($message)) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header("Location: $url");
        exit;
    }

    /**
     * Función para mostrar mensajes flash
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = [
                'text' => $_SESSION['flash_message'],
                'type' => $_SESSION['flash_type'] ?? 'info'
            ];
            
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            
            return $message;
        }
        
        return null;
    }

    /**
     * Función para logging de errores
     */
    public static function logError($message, $level = 'ERROR', $file = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message";
        
        if (!empty($file)) {
            $logMessage .= " in $file";
        }
        
        $logMessage .= PHP_EOL;
        
        // Crear directorio de logs si no existe
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/system_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Función para generar breadcrumbs
     */
    public static function generateBreadcrumb($items) {
        if (empty($items)) {
            return '';
        }
        
        $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        
        $lastIndex = count($items) - 1;
        foreach ($items as $index => $item) {
            if ($index === $lastIndex) {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
            } else {
                $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></li>';
            }
        }
        
        $html .= '</ol></nav>';
        
        return $html;
    }

    /**
     * Función para debug (solo en modo desarrollo)
     */
    public static function debug($data, $die = false) {
        if (self::env('DEBUG_MODE', false)) {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
            
            if ($die) {
                die();
            }
        }
    }
}
?>
