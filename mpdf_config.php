<?php
/**
 * Configuración centralizada para mPDF
 * Usando Composer autoloader (v8.2.7)
 */

// Cargar autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Crear instancia de mPDF con configuración estándar para facturas
 */
function createMpdfInstance($config = []) {
    $defaultConfig = [
        'mode' => 'utf-8',
        'format' => [80, 297], // 80mm x 297mm (formato ticket)
        'default_font_size' => 8,
        'margin_left' => 2,
        'margin_right' => 1,
        'margin_top' => 1,
        'margin_bottom' => 1,
        'margin_header' => 0,
        'margin_footer' => 0,
        'orientation' => 'P',
        'tempDir' => __DIR__ . '/tmp', // Usar directorio temporal local
        'fontDir' => [
            __DIR__ . '/vendor/mpdf/mpdf/ttfonts',
            __DIR__ . '/ttfonts'
        ]
    ];
    
    // Combinar configuración por defecto con la personalizada
    $finalConfig = array_merge($defaultConfig, $config);
    
    // Crear directorio temporal si no existe
    if (!is_dir($finalConfig['tempDir'])) {
        mkdir($finalConfig['tempDir'], 0755, true);
    }
    
    try {
        return new \Mpdf\Mpdf($finalConfig);
    } catch (Exception $e) {
        // Fallback con configuración mínima si hay problemas
        $fallbackConfig = [
            'mode' => 'utf-8',
            'format' => [80, 297],
            'default_font_size' => 8,
            'margin_left' => 2,
            'margin_right' => 1,
            'margin_top' => 1,
            'margin_bottom' => 1,
            'orientation' => 'P'
        ];
        return new \Mpdf\Mpdf($fallbackConfig);
    }
}
?>
