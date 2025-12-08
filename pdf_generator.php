<?php
/**
 * Generador centralizado de PDFs para facturas
 * Elimina duplicación de código entre generar_pdf.php, fact.php y enviar_factura_email.php
 */

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

    // Generar HTML base
    $cuerpo = '
    <html>
    <title>Factura POS '.$datos['factura_num'].'</title>
    <link rel="shortcut icon" href="http://localhost/ventas/logo.png" type="image/x-icon" />
    <style>
    @page { 
      sheet-size: 80mm 297mm; 
      size: auto;
    }
    </style>
    <body>
    <table border="0"; style="table-layout: fixed; width: 180">
      <tr align: "center">
        <td colspan="4" style="text-align: center"><img src="http://localhost/ventas/logo.png" width="130"></td>
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

    // Agregar documento si existe (para fact.php)
    if (!empty($documento)) {
        $cuerpo .= '
      <tr>
        <td colspan="4">Documento: '.$documento.'</td>
      </tr>';
    }

    // Agregar dirección si existe (para fact.php)
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

    // Agregar ciudad si existe (para fact.php)
    if (!empty($ciudad)) {
        $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Ciudad: '.$ciudad.' ('.$departamento.')</td>
      </tr>';
    }

    $cuerpo .= '
      <tr>
        <td colspan="4">Teléfono: '.$celular.'</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;">Email: '.$correo.'</td>
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
                $cant = $row_productos['product_qty'];
                $vunit = number_format(($row_productos['coupon_amount']+$row_productos['product_net_revenue'])/$cant);
                $vtot = number_format($row_productos['coupon_amount']+$row_productos['product_net_revenue']);
                
                $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">'.$cant.'</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">'.$nomprod.'</td>
        <td style="text-align: right; vertical-align: top">'.$vunit.'</td>
        <td style="text-align: right; vertical-align: top">'.$vtot.'</td>
      </tr>';
            }
        }
        // Si es un array (para enviar_factura_email.php)
        else if (is_array($productos)) {
            foreach ($productos as $producto) {
                $precio_unitario = $producto['total_producto'] / $producto['cantidad'];
                $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">'.$producto['cantidad'].'</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">'.htmlspecialchars($producto['nombre_producto']).'</td>
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

    // Agregar envío si existe (para fact.php)
    if ($envio > 0) {
        $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">1</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">Envío</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
      </tr>';
    }

    // Agregar descuento si existe (para fact.php)
    if ($descuento > 0) {
        $cuerpo .= '
      <tr>
        <td colspan="3" style="text-align: right; vertical-align: right;word-wrap: break-word; width: 120">Descuento:</td>
        <td style="text-align: right">'.number_format($descuento).'</td>
      </tr>';
    }

    // Agregar método de pago si existe (para fact.php)
    if (!empty($metodo)) {
        $cuerpo .= '
      <tr> 
        <td colspan="3" style="text-align: right; vertical-align: right;word-wrap: break-word; width: 120">'.$metodo.'</td>
        <td style="text-align: right">'.number_format($vtotal).'</td>
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
    require_once('class/config.php');
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
