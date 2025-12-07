<?php
/**
 * Herramientas y Funciones Globales del Sistema
 * Funciones utilitarias reutilizables en todo el proyecto
 */

/**
 * Función de autorización de acceso a páginas
 * Verifica si un usuario tiene permisos para acceder a una página específica
 * 
 * @param string $strUsers Lista de usuarios autorizados separados por coma
 * @param string $strGroups Lista de grupos autorizados separados por coma  
 * @param string $UserName Nombre del usuario actual
 * @param string $UserGroup Grupo del usuario actual
 * @return bool True si está autorizado, False si no
 */
function isAuthorized($strUsers, $strGroups, $UserName, $UserGroup) { 
    // Por seguridad, asumimos que el visitante NO está autorizado
    $isValid = false; 

    // Cuando un visitante se ha logueado, la variable de sesión MM_Username se establece con su nombre de usuario
    // Por lo tanto, sabemos que un usuario NO está logueado si esa variable de sesión está vacía
    if (!empty($UserName)) { 
        // Además de estar logueado, podemos restringir el acceso solo a ciertos usuarios basado en un ID establecido cuando se loguean
        // Convertir las cadenas en arrays
        $arrUsers = explode(",", $strUsers); 
        $arrGroups = explode(",", $strGroups); 
        
        // Verificar si el usuario está en la lista de usuarios autorizados
        if (in_array($UserName, $arrUsers)) { 
            $isValid = true; 
        } 
        
        // O verificar si el grupo del usuario está en la lista de grupos autorizados
        if (in_array($UserGroup, $arrGroups)) { 
            $isValid = true; 
        } 
        
        // Condición especial (actualmente deshabilitada)
        if (($strUsers == "") && false) { 
            $isValid = true; 
        } 
    } 
    
    return $isValid; 
}

/**
 * Función para sanitizar entrada de datos
 * Limpia y valida datos de entrada para prevenir inyecciones
 * 
 * @param string $data Datos a sanitizar
 * @param mysqli $connection Conexión de base de datos para escape
 * @return string Datos sanitizados
 */
function sanitizeInput($data, $connection = null) {
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
 * 
 * @param string $email Email a validar
 * @return bool True si es válido, False si no
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para generar token CSRF
 * 
 * @return string Token CSRF generado
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para verificar token CSRF
 * 
 * @param string $token Token a verificar
 * @return bool True si es válido, False si no
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para formatear fechas
 * 
 * @param string $date Fecha a formatear
 * @param string $format Formato deseado (default: 'd/m/Y H:i')
 * @return string Fecha formateada
 */
function formatDate($date, $format = 'd/m/Y H:i') {
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
 * 
 * @param float $amount Cantidad a formatear
 * @param string $currency Símbolo de moneda (default: '$')
 * @return string Cantidad formateada
 */
function formatCurrency($amount, $currency = '$') {
    return $currency . ' ' . number_format($amount, 0, ',', '.');
}

/**
 * Función para redireccionar con mensaje
 * 
 * @param string $url URL de destino
 * @param string $message Mensaje opcional
 * @param string $type Tipo de mensaje (success, error, warning, info)
 */
function redirectWithMessage($url, $message = '', $type = 'info') {
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

/**
 * Función para mostrar mensajes flash
 * 
 * @return array Array con mensaje y tipo, o null si no hay mensaje
 */
function getFlashMessage() {
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
 * 
 * @param string $message Mensaje de error
 * @param string $level Nivel de error (ERROR, WARNING, INFO)
 * @param string $file Archivo donde ocurrió el error
 */
function logError($message, $level = 'ERROR', $file = '') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    
    if (!empty($file)) {
        $logMessage .= " in $file";
    }
    
    $logMessage .= PHP_EOL;
    
    // Crear directorio de logs si no existe
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/system_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Función para verificar si el usuario está logueado
 * 
 * @return bool True si está logueado, False si no
 */
function isLoggedIn() {
    return isset($_SESSION['MM_Username']) && !empty($_SESSION['MM_Username']);
}

/**
 * Función para obtener información del usuario actual
 * 
 * @return array|null Información del usuario o null si no está logueado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'username' => $_SESSION['MM_Username'] ?? '',
        'user_group' => $_SESSION['MM_UserGroup'] ?? '',
        'login_time' => $_SESSION['login_time'] ?? ''
    ];
}

/**
 * Función para generar breadcrumbs
 * 
 * @param array $items Array de items del breadcrumb [['title' => 'Inicio', 'url' => 'index.php']]
 * @return string HTML del breadcrumb
 */
function generateBreadcrumb($items) {
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
 * 
 * @param mixed $data Datos a mostrar
 * @param bool $die Si debe terminar la ejecución
 */
function debug($data, $die = false) {
    if (env('DEBUG_MODE', false)) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}
?>
