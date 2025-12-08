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

    /**
     * Capturar múltiples valores de POST con validación isset
     * @param array $fields - Array de campos a capturar
     * @param bool $sanitize - Si aplicar sanitización básica
     * @return array - Array asociativo con los valores capturados
     */
    public static function capturePostData($fields, $sanitize = true) {
        $data = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                if ($sanitize) {
                    $value = self::sanitizeInput($value);
                }
                $data[$field] = $value;
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    /**
     * Capturar múltiples valores de GET con validación isset
     * @param array $fields - Array de campos a capturar
     * @param bool $sanitize - Si aplicar sanitización básica
     * @return array - Array asociativo con los valores capturados
     */
    public static function captureGetData($fields, $sanitize = true) {
        $data = [];
        foreach ($fields as $field) {
            if (isset($_GET[$field])) {
                $value = $_GET[$field];
                if ($sanitize) {
                    $value = self::sanitizeInput($value);
                }
                $data[$field] = $value;
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    /**
     * Capturar valores de REQUEST (POST o GET) con validación isset
     * @param array $fields - Array de campos a capturar
     * @param bool $sanitize - Si aplicar sanitización básica
     * @return array - Array asociativo con los valores capturados
     */
    public static function captureRequestData($fields, $sanitize = true) {
        $data = [];
        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $value = $_REQUEST[$field];
                if ($sanitize) {
                    $value = self::sanitizeInput($value);
                }
                $data[$field] = $value;
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    /**
     * Capturar un solo valor con validación isset y valor por defecto
     * @param string $field - Campo a capturar
     * @param string $method - Método: 'POST', 'GET', o 'REQUEST'
     * @param mixed $default - Valor por defecto si no existe
     * @param bool $sanitize - Si aplicar sanitización básica
     * @return mixed - Valor capturado o valor por defecto
     */
    public static function captureValue($field, $method = 'POST', $default = null, $sanitize = true) {
        $source = null;
        switch (strtoupper($method)) {
            case 'POST':
                $source = $_POST;
                break;
            case 'GET':
                $source = $_GET;
                break;
            case 'REQUEST':
                $source = $_REQUEST;
                break;
            case 'SESSION':
                $source = $_SESSION;
                break;
            case 'COOKIE':
                $source = $_COOKIE;
                break;
            default:
                return $default;
        }

        if (isset($source[$field])) {
            $value = $source[$field];
            if ($sanitize) {
                $value = self::sanitizeInput($value);
            }
            return $value;
        }
        return $default;
    }

    /**
     * Verificar si existen múltiples campos en POST
     * @param array $fields - Array de campos a verificar
     * @return bool - True si todos los campos existen
     */
    public static function hasPostFields($fields) {
        foreach ($fields as $field) {
            if (!isset($_POST[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Capturar múltiples valores de SESSION con validación isset
     * @param array $fields - Array de campos a capturar
     * @param bool $sanitize - Si aplicar sanitización básica
     * @return array - Array asociativo con los valores capturados
     */
    public static function captureSessionData($fields, $sanitize = true) {
        $data = [];
        foreach ($fields as $field) {
            if (isset($_SESSION[$field])) {
                $value = $_SESSION[$field];
                if ($sanitize) {
                    $value = self::sanitizeInput($value);
                }
                $data[$field] = $value;
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    /**
     * Verificar si existe al menos uno de los campos especificados
     * @param array $fields - Array de campos a verificar
     * @param string $method - Método: 'POST', 'GET', 'REQUEST', 'SESSION'
     * @return bool - True si al menos un campo existe
     */
    public static function hasAnyField($fields, $method = 'POST') {
        $source = null;
        switch (strtoupper($method)) {
            case 'POST':
                $source = $_POST;
                break;
            case 'GET':
                $source = $_GET;
                break;
            case 'REQUEST':
                $source = $_REQUEST;
                break;
            case 'SESSION':
                $source = $_SESSION;
                break;
            case 'COOKIE':
                $source = $_COOKIE;
                break;
            default:
                return false;
        }

        foreach ($fields as $field) {
            if (isset($source[$field])) {
                return true;
            }
        }
        return false;
    }
}
?>
