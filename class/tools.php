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
     * Verifica si la petición actual es POST
     * 
     * @return bool True si es petición POST
     */
    public static function isPostRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Verifica si la petición actual es GET
     * 
     * @return bool True si es petición GET
     */
    public static function isGetRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
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
        $source = [];
        
        switch(strtoupper($method)) {
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
            case 'POST':
            default:
                $source = $_POST;
                break;
        }
        
        foreach($fields as $field) {
            if(isset($source[$field])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Procesa un array de resultados de base de datos con estructura meta_key/meta_value
     * y extrae los valores en un array asociativo
     * 
     * @param array $rows Array de filas de la base de datos
     * @param array $keys Array de meta_keys a extraer
     * @param string $keyColumn Nombre de la columna que contiene las claves (default: 'meta_key')
     * @param string $valueColumn Nombre de la columna que contiene los valores (default: 'meta_value')
     * @param array $processors Array asociativo de funciones de procesamiento por clave
     * @return array Array asociativo con los valores extraídos
     */
    public static function processMetaData($rows, $keys = [], $keyColumn = 'meta_key', $valueColumn = 'meta_value', $processors = []) {
        $result = [];
        
        // Inicializar todas las claves con valores vacíos
        foreach($keys as $key) {
            $result[$key] = '';
        }
        
        // Procesar cada fila
        foreach($rows as $row) {
            if(!isset($row[$keyColumn]) || !isset($row[$valueColumn])) {
                continue;
            }
            
            $metaKey = $row[$keyColumn];
            $metaValue = $row[$valueColumn];
            
            // Solo procesar si la clave está en la lista de claves deseadas (o si no se especificó lista)
            if(empty($keys) || in_array($metaKey, $keys)) {
                // Aplicar procesador específico si existe
                if(isset($processors[$metaKey]) && is_callable($processors[$metaKey])) {
                    $result[$metaKey] = $processors[$metaKey]($metaValue);
                } else {
                    $result[$metaKey] = $metaValue;
                }
            }
        }
        
        return $result;
    }

    /**
     * Procesa datos meta desde un resultado de mysqli mientras itera
     * 
     * @param mysqli_result $result Resultado de mysqli
     * @param array $keys Array de meta_keys a extraer
     * @param string $keyColumn Nombre de la columna que contiene las claves
     * @param string $valueColumn Nombre de la columna que contiene los valores
     * @param array $processors Array asociativo de funciones de procesamiento por clave
     * @return array Array asociativo con los valores extraídos
     */
    public static function processMetaFromResult($result, $keys = [], $keyColumn = 'meta_key', $valueColumn = 'meta_value', $processors = []) {
        $rows = [];
        
        // Recopilar todas las filas
        while($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        
        // Resetear el puntero del resultado si es posible
        if(mysqli_num_rows($result) > 0) {
            mysqli_data_seek($result, 0);
        }
        
        return self::processMetaData($rows, $keys, $keyColumn, $valueColumn, $processors);
    }

    /**
     * Convierte un código de departamento colombiano a su nombre completo
     * @param string $codigo_departamento - Código del departamento (ej: "SAN", "CO-SAN", "ANT")
     * @return string - Nombre completo del departamento o el código original si no se encuentra
     */
    public static function convertDepartmentCode($codigo_departamento) {
        if (empty($codigo_departamento)) {
            return '';
        }
        
        // Si el departamento contiene "CO-", extraer solo la parte del código
        if (strpos($codigo_departamento, 'CO-') === 0) {
            $codigo_departamento = substr($codigo_departamento, 3); // Quitar "CO-"
        }
        
        // Cargar datos de departamentos desde el plugin Colombia
        $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
        if (file_exists($states_file)) {
            $colombia_states = include($states_file);
            
            // Buscar el nombre del departamento por código
            foreach ($colombia_states as $code => $name) {
                if (strtoupper($code) === strtoupper($codigo_departamento)) {
                    return $name;
                }
            }
        }
        
        // Si no se encuentra, devolver el código original
        return $codigo_departamento;
    }

    /**
     * Formatea una ubicación completa con ciudad y departamento
     * @param string $ciudad - Nombre de la ciudad
     * @param string $departamento - Código o nombre del departamento
     * @param string $pais - Código del país (opcional, default: 'CO')
     * @return string - Ubicación formateada (ej: "Giron (Santander)" o "Giron, Santander, Colombia")
     */
    public static function formatLocation($ciudad, $departamento, $pais = null, $includeCountry = false) {
        $ubicacion = '';
        
        // Agregar ciudad
        if (!empty($ciudad)) {
            $ubicacion = $ciudad;
        }
        
        // Convertir y agregar departamento
        if (!empty($departamento)) {
            $departamento_nombre = self::convertDepartmentCode($departamento);
            if (!empty($ubicacion)) {
                $ubicacion .= $includeCountry ? ', ' : ' (';
                $ubicacion .= $departamento_nombre;
                $ubicacion .= $includeCountry ? '' : ')';
            } else {
                $ubicacion = $departamento_nombre;
            }
        }
        
        // Agregar país si se solicita
        if ($includeCountry && !empty($pais)) {
            $pais_nombre = ($pais === 'CO') ? 'Colombia' : $pais;
            if (!empty($ubicacion)) {
                $ubicacion .= ', ' . $pais_nombre;
            } else {
                $ubicacion = $pais_nombre;
            }
        }
        
        return $ubicacion;
    }

    /**
     * Obtiene todas las ciudades de un departamento específico
     * @param string $codigo_departamento - Código del departamento (ej: "SAN", "CO-SAN", "ANT")
     * @return array - Array de ciudades del departamento o array vacío si no se encuentra
     */
    public static function getCitiesByDepartment($codigo_departamento) {
        if (empty($codigo_departamento)) {
            return [];
        }
        
        // Si el departamento contiene "CO-", extraer solo la parte del código
        if (strpos($codigo_departamento, 'CO-') === 0) {
            $codigo_departamento = substr($codigo_departamento, 3); // Quitar "CO-"
        }
        
        // Cargar datos de ciudades desde el plugin Colombia
        $places_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/places/CO.php';
        if (file_exists($places_file)) {
            global $places;
            include($places_file);
            
            if (isset($places['CO']) && isset($places['CO'][strtoupper($codigo_departamento)])) {
                return $places['CO'][strtoupper($codigo_departamento)];
            }
        }
        
        return [];
    }

    /**
     * Valida si una ciudad existe en un departamento específico
     * @param string $ciudad - Nombre de la ciudad
     * @param string $codigo_departamento - Código del departamento
     * @return bool - True si la ciudad existe en el departamento
     */
    public static function validateCityInDepartment($ciudad, $codigo_departamento) {
        if (empty($ciudad) || empty($codigo_departamento)) {
            return false;
        }
        
        $cities = self::getCitiesByDepartment($codigo_departamento);
        
        // Búsqueda insensible a mayúsculas/minúsculas
        foreach ($cities as $city) {
            if (strtolower(trim($city)) === strtolower(trim($ciudad))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtiene todas las ciudades de Colombia organizadas por departamento
     * @return array - Array asociativo con departamentos como keys y arrays de ciudades como values
     */
    public static function getAllCitiesByDepartments() {
        $places_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/places/CO.php';
        if (file_exists($places_file)) {
            global $places;
            include($places_file);
            
            if (isset($places['CO'])) {
                return $places['CO'];
            }
        }
        
        return [];
    }

    /**
     * Busca ciudades que coincidan con un término de búsqueda
     * @param string $search_term - Término de búsqueda
     * @param string $codigo_departamento - Código del departamento (opcional, para filtrar por departamento)
     * @param int $limit - Límite de resultados (default: 50)
     * @return array - Array de ciudades que coinciden con la búsqueda
     */
    public static function searchCities($search_term, $codigo_departamento = null, $limit = 50) {
        if (empty($search_term)) {
            return [];
        }
        
        $results = [];
        $search_term = strtolower(trim($search_term));
        
        if (!empty($codigo_departamento)) {
            // Buscar solo en un departamento específico
            $cities = self::getCitiesByDepartment($codigo_departamento);
            $dept_name = self::convertDepartmentCode($codigo_departamento);
            
            foreach ($cities as $city) {
                if (strpos(strtolower($city), $search_term) !== false) {
                    $results[] = [
                        'city' => $city,
                        'department_code' => strtoupper($codigo_departamento),
                        'department_name' => $dept_name
                    ];
                    
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        } else {
            // Buscar en todos los departamentos
            $all_places = self::getAllCitiesByDepartments();
            
            foreach ($all_places as $dept_code => $cities) {
                $dept_name = self::convertDepartmentCode($dept_code);
                
                foreach ($cities as $city) {
                    if (strpos(strtolower($city), $search_term) !== false) {
                        $results[] = [
                            'city' => $city,
                            'department_code' => $dept_code,
                            'department_name' => $dept_name
                        ];
                        
                        if (count($results) >= $limit) {
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $results;
    }

    /**
     * Obtiene información completa de una ciudad (nombre, departamento, etc.)
     * @param string $ciudad - Nombre de la ciudad
     * @param string $codigo_departamento - Código del departamento (opcional)
     * @return array|null - Array con información de la ciudad o null si no se encuentra
     */
    public static function getCityInfo($ciudad, $codigo_departamento = null) {
        if (empty($ciudad)) {
            return null;
        }
        
        if (!empty($codigo_departamento)) {
            // Buscar en departamento específico
            if (self::validateCityInDepartment($ciudad, $codigo_departamento)) {
                return [
                    'city' => $ciudad,
                    'department_code' => strtoupper($codigo_departamento),
                    'department_name' => self::convertDepartmentCode($codigo_departamento),
                    'formatted_location' => self::formatLocation($ciudad, $codigo_departamento)
                ];
            }
        } else {
            // Buscar en todos los departamentos
            $search_results = self::searchCities($ciudad, null, 1);
            if (!empty($search_results)) {
                $result = $search_results[0];
                return [
                    'city' => $result['city'],
                    'department_code' => $result['department_code'],
                    'department_name' => $result['department_name'],
                    'formatted_location' => self::formatLocation($result['city'], $result['department_code'])
                ];
            }
        }
        
        return null;
    }
}
?>
