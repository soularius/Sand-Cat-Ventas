<?php
/**
 * Generador centralizado de PDFs para facturas
 * Elimina duplicación de código entre generar_pdf.php, fact.php y enviar_factura_email.php
 */

require_once('mpdf_config.php');
require_once('class/email_template.php');

/**
 * Convierte código de departamento a nombre completo
 * @param string $codigo Código del departamento (ej: "SAN", "ANT")
 * @return string Nombre completo del departamento
 */
function convertirCodigoDepartamento($codigo) {
    // Si el código contiene "CO-", extraer solo la parte del departamento
    if (strpos($codigo, 'CO-') === 0) {
        $codigo = substr($codigo, 3); // Quitar "CO-" del inicio
    }
    
    // Cargar datos de departamentos desde el plugin Colombia
    $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
    
    if (file_exists($states_file)) {
        $colombia_states = include($states_file);
        
        // Buscar el nombre del departamento por código
        foreach ($colombia_states as $code => $name) {
            if (strtoupper($code) === strtoupper($codigo)) {
                return $name;
            }
        }
    }
    // Si no se encuentra, devolver el código original
    return $codigo;
}

/**
 * Trunca texto largo para evitar desbordamientos
 * @param string $texto Texto a truncar
 * @param int $limite Límite de caracteres (por defecto 35)
 * @return string Texto truncado con "..." si es necesario
 */
function truncarTexto($texto, $limite = 35) {
    if (mb_strlen($texto, 'UTF-8') > $limite) {
        return mb_substr($texto, 0, $limite - 3, 'UTF-8') . '...';
    }
    return $texto;
}

/**
 * Genera el HTML completo para una factura PDF
 * 
 * @param array $datos Datos necesarios para el PDF
 * @return string HTML completo para el PDF
 */
function generarHTMLFactura($datos) {
    // Extraer datos
    $fecha = $datos['fecha'];
    $nombre1 = $datos['nombre1'];
    $nombre2 = $datos['nombre2'];
    $correo = $datos['correo'];
    $celular = $datos['celular'];
    $vtotal = $datos['vtotal'];
    $factura_formateada = $datos['factura_formateada'];
    $orden_id = $datos['orden_id'];
    $woocommerce_url = $datos['woocommerce_url'];
    $productos = $datos['productos'] ?? null;
    
    // Datos adicionales opcionales
    $documento = $datos['documento'] ?? '';
    $direccion = $datos['direccion'] ?? '';
    $ciudad = $datos['ciudad'] ?? '';
    $departamento = $datos['departamento'] ?? '';
    $observaciones = $datos['observaciones'] ?? '';
    $envio = $datos['envio'] ?? 0;
    $descuento = $datos['descuento'] ?? 0;
    $metodo = $datos['metodo'] ?? '';
    // Nuevos campos de dirección
    $direccion_1 = $datos['direccion_1'] ?? '';
    $direccion_2 = $datos['direccion_2'] ?? '';
    $pais = $datos['pais'] ?? '';
    $barrio = $datos['barrio'] ?? '';
    $dni = $datos['dni'] ?? '';
    $comentarios = $datos['comentarios'] ?? '';
    $estado_factura = $datos['estado_factura'] ?? 'a';

    // Construir dirección completa
    $direccion_completa = '';
    if (!empty($direccion_1)) {
        $direccion_completa = $direccion_1;
        if (!empty($direccion_2)) {
            $direccion_completa .= ', ' . $direccion_2;
        }
    }

    // Construir ubicación
    $ubicacion = '';
    if (!empty($ciudad)) {
        $ubicacion = $ciudad;

        // Convertir código de departamento a nombre completo
        if (!empty($departamento)) {
            $departamento_nombre = convertirCodigoDepartamento($departamento);
            $ubicacion .= ', ' . $departamento_nombre;
        }
        
        // Convertir código de país a nombre completo
        if (!empty($pais)) {
            $pais_nombre = ($pais === 'CO') ? 'Colombia' : $pais;
            $ubicacion .= ', ' . $pais_nombre;
        }
    }

    // Preparar datos para el template
    $template_data = [
        'factura_num' => $datos['factura_num'],
        'fecha' => $fecha,
        'factura_formateada' => $factura_formateada,
        'orden_id' => $orden_id,
        'nombre1' => $nombre1,
        'nombre2' => $nombre2,
        'celular' => $celular,
        'correo' => $correo,
        'productos' => $productos,
        'vtotal' => $vtotal,
        'woocommerce_url' => $woocommerce_url,
        'metodo' => $metodo,
        'dni' => $dni,
        'documento' => $documento,
        'direccion_completa' => $direccion_completa,
        'barrio' => $barrio,
        'ubicacion' => $ubicacion,
        'direccion' => $direccion,
        'observaciones' => $observaciones,
        'comentarios' => $comentarios,
        'envio' => $envio,
        'descuento' => $descuento,
        'estado_factura' => $estado_factura
    ];

    // Usar el sistema de templates
    return EmailTemplate::processPDFTemplate($template_data);
}

/**
 * Genera un PDF completo usando mPDF
 * 
 * @param array $datos Datos para el PDF
 * @param string $output_mode Modo de salida: 'I' (inline), 'D' (download), 'S' (string)
 * @param string $filename Nombre del archivo (opcional)
 * @return mixed Dependiendo del output_mode
 */
function generarPDFFactura($datos, $output_mode = 'I', $filename = null) {
    // Generar HTML
    $html = generarHTMLFactura($datos);
    
    // Crear PDF
    $mpdf = createMpdfInstance();
    
    $mpdf->AliasNbPages('{PageTotal}');
    $mpdf->WriteHTML($html);
    
    // Generar nombre de archivo si no se proporciona
    if (!$filename) {
        $filename = "Factura " . $datos['factura_formateada'] . ".pdf";
    }
    
    return $mpdf->Output($filename, $output_mode);
}
?>
