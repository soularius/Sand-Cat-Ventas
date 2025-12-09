<?php
/**
 * Obtener datos de departamentos y ciudades de Colombia desde la DB
 */

// Cargar autoloader del sistema
require_once('class/autoload.php');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    // Log para debugging
    error_log("get_colombia_data.php - Action: $action");
    
    if ($action === 'departamentos') {
        // Obtener departamentos desde archivo de datos del plugin
        $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
        error_log("Cargando departamentos desde: $states_file");
        
        if (file_exists($states_file)) {
            $colombia_states = include($states_file);
            
            if (is_array($colombia_states)) {
                $departamentos = [];
                foreach ($colombia_states as $code => $name) {
                    $departamentos[] = [
                        'code' => $code,
                        'name' => $name
                    ];
                }
                
                error_log("Departamentos cargados: " . count($departamentos));
                echo json_encode(['success' => true, 'data' => $departamentos]);
            } else {
                error_log("Error: El archivo no retorna un array válido");
                echo json_encode(['success' => false, 'error' => 'Error en formato de datos de departamentos']);
            }
        } else {
            error_log("Error: No se encontró el archivo de departamentos");
            echo json_encode(['success' => false, 'error' => 'No se encontró el archivo de departamentos']);
        }
        
    } elseif ($action === 'ciudades') {
        $departamento = $_GET['departamento'] ?? '';
        
        if (empty($departamento)) {
            echo json_encode(['success' => false, 'error' => 'Departamento requerido']);
            exit;
        }
        
        // Obtener ciudades desde archivo de datos del plugin
        $places_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/places/CO.php';
        error_log("Cargando ciudades desde: $places_file para departamento: $departamento");
        
        if (file_exists($places_file)) {
            // Cargar el archivo que define $places['CO']
            global $places;
            include($places_file);
            
            $ciudades = [];
            
            if (isset($places['CO'][$departamento]) && is_array($places['CO'][$departamento])) {
                foreach ($places['CO'][$departamento] as $index => $city_name) {
                    $ciudades[] = [
                        'code' => strtoupper(str_replace(' ', '_', $city_name)),
                        'name' => $city_name
                    ];
                }
                
                error_log("Ciudades encontradas para $departamento: " . count($ciudades));
                echo json_encode(['success' => true, 'data' => $ciudades]);
            } else {
                error_log("No se encontraron ciudades para el departamento: $departamento");
                echo json_encode(['success' => false, 'error' => "No se encontraron ciudades para el departamento: $departamento"]);
            }
        } else {
            error_log("Error: No se encontró el archivo de ciudades");
            echo json_encode(['success' => false, 'error' => 'No se encontró el archivo de ciudades']);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
