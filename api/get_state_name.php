<?php
/**
 * API endpoint para obtener nombre de departamento por código
 * Consulta directamente el archivo CO.php para mantener datos centralizados
 */

// Headers para AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=3600'); // Cache por 1 hora

// Validar parámetro
if (!isset($_GET['code']) || empty($_GET['code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Código de departamento requerido']);
    exit;
}

$state_code = strtoupper(trim($_GET['code']));

// Validar formato del código (3 letras)
if (!preg_match('/^[A-Z]{3}$/', $state_code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de código inválido']);
    exit;
}

// Cargar datos desde el archivo CO.php
$states_file = '../data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';

if (!file_exists($states_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Archivo de departamentos no encontrado']);
    exit;
}

try {
    // Caché estático para evitar múltiples includes
    static $colombia_states = null;
    if ($colombia_states === null) {
        $colombia_states = include($states_file);
    }
    
    if (!is_array($colombia_states)) {
        throw new Exception('Formato de datos inválido');
    }
    
    // Buscar el departamento
    if (isset($colombia_states[$state_code])) {
        echo json_encode([
            'success' => true,
            'code' => $state_code,
            'name' => $colombia_states[$state_code]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'code' => $state_code,
            'name' => $state_code,
            'message' => 'Código de departamento no encontrado'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error cargando datos: ' . $e->getMessage()]);
}
?>
