<?php
/**
 * Generador centralizado de PDFs para facturas
 * Elimina duplicación de código entre generar_pdf.php, fact.php y enviar_factura_email.php
 */

/**
 * Convierte código de departamento a nombre completo
 * @param string $codigo Código del departamento (ej: "SAN", "ANT")
 * @return string Nombre completo del departamento
 */
function convertirCodigoDepartamento($codigo) {
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

    // Generar HTML base
    $cuerpo = '
    <html>
    <head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <title>Factura POS '.$datos['factura_num'].'</title>
    <link rel="shortcut icon" href="http://localhost/ventas/assets/img/logo.png" type="image/x-icon" />
    <style>
    @page { 
      sheet-size: 80mm 297mm; 
      size: auto;
    }
    .precio-tachado {
      text-decoration: line-through;
      color: #666;
      font-size: 0.9em;
    }
    .precio-descuento {
      color: #d9534f;
      font-weight: bold;
    }
    .sku-text {
      font-size: 1rem;
      color: #8b8b8b;
      font-weight: 400;
    }
    </style>
    <body>
    <table border="0"; style="table-layout: fixed; width: 180">
      <tr align: "center">
        <td colspan="4" style="text-align: center"><img src="http://localhost/ventas/assets/img/logo.png" width="130"></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";><strong>SAND Y CAT HUGO ALEJANDRO LOPEZ</strong></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";><strong>NIT</strong> 79690971</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";>www.sandycat.com.co</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";><strong>Teléfono</strong> 6016378243</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin; text-align: center"><strong>Dirección</strong> Cra. 61 No. 78-25</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";><strong>RECIBO DE VENTA</strong></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center">'.$fecha.'</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center"><strong>Serie y número: </strong> '.$factura_formateada.'</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin; text-align: center";><strong>Orden #'.$orden_id.'</strong></td>
      </tr>
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180"><strong>Cliente: </strong>'.strtoupper($nombre1).' '.strtoupper($nombre2).'</td>
      </tr>';

    // Agregar método de pago arriba con los datos del cliente
    if (!empty($metodo)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180"><strong>Método de Pago: </strong>'.$metodo.'</td>
      </tr>';
    }

    // Agregar DNI del cliente
    if (!empty($dni)) {
        $cuerpo .= '
      <tr>
        <td colspan="4">DNI: '.$dni.'</td>
      </tr>';
    }
    
    // Agregar documento si existe (para fact.php - compatibilidad)
    if (!empty($documento)) {
        $cuerpo .= '
      <tr>
        <td colspan="4">Documento: '.$documento.'</td>
      </tr>';
    }

    $cuerpo .= '
      <tr>
        <td colspan="4">Teléfono: '.$celular.'</td>
      </tr>
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Email: '.$correo.'</td>
      </tr>';

    // Agregar dirección completa del cliente
    $direccion_completa = '';
    if (!empty($direccion_1)) {
        $direccion_completa = $direccion_1;
        if (!empty($direccion_2)) {
            $direccion_completa .= ', ' . $direccion_2;
        }
    }
    
    if (!empty($direccion_completa)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Dirección: '.$direccion_completa.'</td>
      </tr>';
    }

    // Agregar barrio si existe
    if (!empty($barrio)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Barrio: '.$barrio.'</td>
      </tr>';
    }

    // Agregar ciudad, departamento y país
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
    
    if (!empty($ubicacion)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Ubicación: '.$ubicacion.'</td>
      </tr>';
    }

    // Agregar dirección si existe (para fact.php - compatibilidad)
    if (!empty($direccion)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Dirección: '.$direccion.'</td>
      </tr>';
    }

    // Agregar observaciones si existen (para fact.php)
    if (!empty($observaciones)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180"><strong>'.$observaciones.'</strong></td>
      </tr>';
    }

    $cuerpo .= '
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;"></td>
      </tr>
      <tr>
        <td style="text-align: center"><strong>Cant.</strong></td>
        <td><strong>Descripción</strong></td>
        <td style="text-align: center"><strong>V Un.</strong></td>
        <td style="text-align: center"><strong>TOTAL</strong></td>
      </tr>';

    // Agregar productos
    if ($productos && (is_array($productos) || mysqli_num_rows($productos) > 0)) {
        // Si es un resultado de MySQL
        if (is_resource($productos) || (is_object($productos) && get_class($productos) === 'mysqli_result')) {
            while ($row_productos = mysqli_fetch_assoc($productos)) {
                $nomprod = $row_productos['order_item_name'];
                $cant = (int)$row_productos['product_qty'];
                $line_total = (float)$row_productos['line_total'];
                $line_subtotal = (float)$row_productos['line_subtotal'];
                $regular_price = (float)$row_productos['regular_price'];
                $sale_price = (float)$row_productos['sale_price'];
                $sku = $row_productos['product_sku'] ?? '';
                
                // Truncar nombre del producto para evitar desbordamientos
                $nomprod_truncado = truncarTexto($nomprod, 35);
                $descripcion_completa = "";

                // Agregar SKU si existe
                if (!empty($sku)) {
                    $descripcion_completa .= '<span class="sku-text">SKU: '.$sku.'<br></span>';
                }
                $descripcion_completa .= $nomprod_truncado;
                
                // Calcular precio unitario correctamente
                $vunit = $cant > 0 ? $line_total / $cant : 0;
                
                // Detectar si hay descuento
                $hay_descuento = false;
                $precio_original = 0;
                $precio_con_descuento = $vunit;
                
                // Si hay diferencia entre subtotal y total, hay descuento
                if ($line_subtotal > $line_total && $line_subtotal > 0) {
                    $hay_descuento = true;
                    $precio_original = $cant > 0 ? $line_subtotal / $cant : 0;
                    $precio_con_descuento = $vunit;
                }
                // O si hay precio regular y precio de oferta diferentes
                else if ($regular_price > 0 && $sale_price > 0 && $regular_price > $sale_price) {
                    $hay_descuento = true;
                    $precio_original = $regular_price;
                    $precio_con_descuento = $sale_price;
                }
                
                // Generar HTML del precio unitario
                $precio_html = '';
                if ($hay_descuento) {
                    $precio_html = '<span class="precio-tachado">'.number_format($precio_original).'</span><br>';
                    $precio_html .= '<span class="precio-descuento">'.number_format($precio_con_descuento).'</span>';
                } else {
                    $precio_html = number_format($vunit);
                }
                
                $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top"><br>'.$cant.'</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">'.$descripcion_completa.'</td>
        <td style="text-align: right; vertical-align: top"><br>'.$precio_html.'</td>
        <td style="text-align: right; vertical-align: top"><br>'.number_format($line_total).'</td>
      </tr>';
            }
        }
        // Si es un array (para enviar_factura_email.php)
        else if (is_array($productos)) {
            foreach ($productos as $producto) {
                $precio_unitario = $producto['total_producto'] / $producto['cantidad'];
                
                // Truncar nombre del producto para evitar desbordamientos
                $nomprod_truncado = truncarTexto($producto['nombre_producto'], 35);
                
                // Agregar SKU si existe
                $descripcion_completa = htmlspecialchars($nomprod_truncado);
                if (!empty($producto['sku'])) {
                    $descripcion_completa .= '<br><span class="sku-text">SKU: '.htmlspecialchars($producto['sku']).'</span>';
                }
                
                $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">'.$producto['cantidad'].'</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">'.$descripcion_completa.'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($precio_unitario).'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($producto['total_producto']).'</td>
      </tr>';
            }
        }
    } else {
        // Si no hay productos específicos, mostrar la orden completa
        $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">1</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">Orden WooCommerce #'.$orden_id.'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($vtotal).'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($vtotal).'</td>
      </tr>';
    }

    // Agregar domicilio si existe (para fact.php)
    if ($envio > 0) {
        $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">1</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">Domicilio</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
      </tr>';
    }

    // Agregar descuento si existe (para fact.php)
    if ($descuento > 0) {
        $cuerpo .= '
      <tr>
        <td colspan="3" style="text-align: right; vertical-align: right;word-wrap: break-word; width: 120"><span class="precio-descuento">Descuento:</span></td>
        <td style="text-align: right"><span class="precio-descuento">-'.number_format($descuento).'</span></td>
      </tr>';
    }

    $cuerpo .= '
      <tr>
        <td colspan="4" align: "center"; style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;"></td>
      </tr>
      <tr> 
        <td colspan="3" style="text-align: right; vertical-align: right;word-wrap: break-word; width: 120"><strong>TOTAL:</strong></td>
        <td style="text-align: right"><strong>'.number_format($vtotal).'</strong></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center"><br><strong>No existen devoluciones</strong></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center"><barcode code="'.$factura_formateada.'" type="C39" size="0.6" height="1.0" /><br><small>'.$factura_formateada.'</small></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center; padding-top: 5mm;">
          <div style="text-align: center;">
            <barcode code="'.$woocommerce_url.'" type="QR" class="barcode" size="0.8" error="M" />
            <br>
            <br>
            <p style=""><small>Escanea para ver el pedido</small></p>
          </div>
        </td>
      </tr>
    </table>
    </body></html>';

    return $cuerpo;
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
