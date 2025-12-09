<?php
/**
 * Manejo de datos de sesión para el sistema de ventas
 * Guarda y recupera datos del cliente en sesiones PHP
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cargar autoloader del sistema
require_once('autoload.php');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Capturar acción con debugging
$action = $_POST['action'] ?? '';

// Log para debugging
Utils::logError("save_session_data.php - Acción recibida: '$action', POST data: " . json_encode($_POST), 'DEBUG', 'save_session_data.php');

if (empty($action)) {
    Utils::logError("save_session_data.php - No se recibió acción válida", 'ERROR', 'save_session_data.php');
    echo json_encode(['success' => false, 'error' => 'No se especificó acción']);
    exit;
}

try {
    switch ($action) {
        case 'save_customer':
            saveCustomerData();
            break;
            
        case 'get_customer':
            getCustomerData();
            break;
            
        case 'clear_customer':
            clearCustomerData();
            break;
            
        default:
            Utils::logError("save_session_data.php - Acción no reconocida: '$action'", 'ERROR', 'save_session_data.php');
            throw new Exception("Acción no válida: '$action'");
    }
} catch (Exception $e) {
    Utils::logError("Error en save_session_data.php: " . $e->getMessage(), 'ERROR', 'save_session_data.php');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Guardar datos del cliente en sesión
 */
function saveCustomerData() {
    $customer_data = $_POST['customer_data'] ?? '';
    $billing_id = $_POST['billing_id'] ?? '';
    
    Utils::logError("saveCustomerData - customer_data length: " . strlen($customer_data) . ", billing_id: '$billing_id'", 'DEBUG', 'save_session_data.php');
    
    if (empty($customer_data) || empty($billing_id)) {
        throw new Exception('Datos del cliente o DNI faltantes');
    }
    
    // Decodificar datos del cliente
    $customer = json_decode($customer_data, true);
    if (!$customer) {
        throw new Exception('Datos del cliente inválidos');
    }
    
    // Guardar en sesión con timestamp
    $_SESSION['last_customer_data'] = [
        'customer' => $customer,
        'billing_id' => $billing_id,
        'timestamp' => time(),
        'source' => 'search' // Indica que viene de búsqueda
    ];
    
    // También guardar solo el último DNI buscado
    $_SESSION['last_billing_id'] = $billing_id;
    
    Utils::logError("Cliente guardado en sesión - DNI: $billing_id", 'INFO', 'save_session_data.php');
    
    echo json_encode([
        'success' => true,
        'message' => 'Datos del cliente guardados en sesión',
        'billing_id' => $billing_id
    ]);
}

/**
 * Obtener datos del cliente desde sesión
 */
function getCustomerData() {
    $billing_id = $_POST['billing_id'] ?? '';
    
    // Si hay datos de cliente en sesión
    if (isset($_SESSION['last_customer_data'])) {
        $sessionData = $_SESSION['last_customer_data'];
        
        // Verificar si el DNI coincide o si no se especifica DNI (obtener último)
        if (empty($billing_id) || $sessionData['billing_id'] === $billing_id) {
            // Verificar que los datos no sean muy antiguos (1 hora)
            $maxAge = 3600; // 1 hora
            if ((time() - $sessionData['timestamp']) <= $maxAge) {
                Utils::logError("Cliente recuperado desde sesión - DNI: " . $sessionData['billing_id'], 'INFO', 'save_session_data.php');
                
                echo json_encode([
                    'success' => true,
                    'customer' => $sessionData['customer'],
                    'billing_id' => $sessionData['billing_id'],
                    'source' => $sessionData['source'],
                    'age_minutes' => round((time() - $sessionData['timestamp']) / 60)
                ]);
                return;
            } else {
                // Datos muy antiguos, limpiar
                unset($_SESSION['last_customer_data']);
                Utils::logError("Datos de sesión expirados, limpiando", 'INFO', 'save_session_data.php');
            }
        }
    }
    
    // Si hay último DNI pero no datos completos
    if (isset($_SESSION['last_billing_id'])) {
        echo json_encode([
            'success' => false,
            'has_dni' => true,
            'billing_id' => $_SESSION['last_billing_id'],
            'message' => 'Solo DNI disponible en sesión'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => false,
        'has_dni' => false,
        'message' => 'No hay datos de cliente en sesión'
    ]);
}

/**
 * Limpiar datos del cliente de la sesión
 */
function clearCustomerData() {
    $clear_type = $_POST['clear_type'] ?? 'all';
    
    switch ($clear_type) {
        case 'customer_only':
            // Solo limpiar datos del cliente, mantener DNI
            unset($_SESSION['last_customer_data']);
            Utils::logError("Datos de cliente limpiados de sesión, DNI mantenido", 'INFO', 'save_session_data.php');
            break;
            
        case 'all':
        default:
            // Limpiar todo
            unset($_SESSION['last_customer_data']);
            unset($_SESSION['last_billing_id']);
            Utils::logError("Todos los datos de cliente limpiados de sesión", 'INFO', 'save_session_data.php');
            break;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Datos limpiados de sesión'
    ]);
}

/**
 * Función auxiliar para obtener el último DNI (para uso en otros archivos)
 */
function getLastBillingId() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['last_billing_id'] ?? null;
}

/**
 * Función auxiliar para obtener datos completos del cliente (para uso en otros archivos)
 */
function getLastCustomerData() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['last_customer_data'])) {
        $sessionData = $_SESSION['last_customer_data'];
        
        // Verificar que no sean muy antiguos (1 hora)
        $maxAge = 3600;
        if ((time() - $sessionData['timestamp']) <= $maxAge) {
            return $sessionData;
        } else {
            // Limpiar datos antiguos
            unset($_SESSION['last_customer_data']);
        }
    }
    
    return null;
}
?>
