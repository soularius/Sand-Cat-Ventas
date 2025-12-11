<?php
// Establecer header JSON primero
header('Content-Type: application/json');

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

try {
    // Verificar conexión a la base de datos
    global $miau;
    if (!$miau) {
        throw new Exception('No hay conexión a la base de datos WordPress');
    }
    
    // Log de debugging
    Utils::logError("get_categories.php iniciado - Conexión DB: " . (isset($miau) ? 'OK' : 'FAIL'), 'INFO', 'get_categories.php');
    
    // Query directa para diagnosticar el problema
    $query = "SELECT 
        t.term_id as id_categoria,
        tt.term_taxonomy_id as id_taxonomy,
        t.name as nombre,
        t.slug as slug,
        tt.count as total_productos
    FROM miau_terms t
    INNER JOIN miau_term_taxonomy tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat'
    AND tt.count > 0
    ORDER BY t.name ASC
    LIMIT 20";
    
    $result = mysqli_query($miau, $query);
    
    if (!$result) {
        throw new Exception('Error en query de categorías: ' . mysqli_error($miau));
    }
    
    // Procesar categorías para el formato esperado por el frontend
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = [
            'id' => $row['id_taxonomy'], // Usar term_taxonomy_id para búsquedas
            'term_id' => $row['id_categoria'], // Mantener term_id para referencia
            'name' => $row['nombre'],
            'slug' => $row['slug'],
            'count' => intval($row['total_productos'])
        ];
    }
    
    mysqli_free_result($result);
    
    Utils::logError("Categorías cargadas: " . count($categories) . " categorías encontradas", 'INFO', 'get_categories.php');
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'total' => count($categories)
    ]);
    
} catch (Exception $e) {
    Utils::logError("Error al cargar categorías: " . $e->getMessage(), 'ERROR', 'get_categories.php');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'categories' => []
    ]);
}
?>
