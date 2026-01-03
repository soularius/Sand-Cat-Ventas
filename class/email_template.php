<?php

/**
 * Clase para manejar templates de email y PDF
 */
class EmailTemplate
{
    /**
     * Procesar template de email de factura
     * @param array $data - Datos para reemplazar en el template
     * @return string - HTML procesado
     */
    public static function processInvoiceTemplate(array $data): string
    {
        $template_path = __DIR__ . '/../templates/email_factura.html';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template de email no encontrado: $template_path");
        }
        
        $template = file_get_contents($template_path);
        
        // Procesar secciones condicionales
        $dni_section = !empty($data['dni']) ? "<p><strong>DNI:</strong> {$data['dni']}</p>" : "";
        $direccion_section = !empty($data['direccion_completa']) ? "<p><strong>Dirección:</strong> {$data['direccion_completa']}</p>" : "";
        $barrio_section = !empty($data['barrio']) ? "<p><strong>Barrio:</strong> {$data['barrio']}</p>" : "";
        $ubicacion_section = !empty($data['ubicacion']) ? "<p><strong>Ubicación:</strong> {$data['ubicacion']}</p>" : "";
        
        $envio_section = ($data['envio'] > 0) ? "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>1</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>Domicilio</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($data['envio'], 0) . "</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($data['envio'], 0) . "</td>
            </tr>" : "";
        
        $descuento_section = ($data['descuento'] > 0) ? "<p><strong>Descuento:</strong> <span style='color: #dc3545;'>-$" . number_format($data['descuento'], 0) . "</span></p>" : "";
        
        // Reemplazos simples
        $replacements = [
            '{{nombre_cliente}}' => $data['nombre_cliente'],
            '{{apellido_cliente}}' => $data['apellido_cliente'],
            '{{factura_formateada}}' => $data['factura_formateada'],
            '{{orden_id}}' => $data['orden_id'],
            '{{fecha_orden}}' => $data['fecha_orden'],
            '{{metodo_pago}}' => $data['metodo_pago'],
            '{{email_cliente}}' => $data['email_cliente'],
            '{{telefono_cliente}}' => $data['telefono_cliente'],
            '{{productos_html}}' => $data['productos_html'],
            '{{total_formateado}}' => number_format($data['total'], 0),
            '{{woocommerce_url_pedido}}' => $data['woocommerce_url'],
            // Secciones condicionales
            '{{dni_section}}' => $dni_section,
            '{{direccion_section}}' => $direccion_section,
            '{{barrio_section}}' => $barrio_section,
            '{{ubicacion_section}}' => $ubicacion_section,
            '{{envio_section}}' => $envio_section,
            '{{descuento_section}}' => $descuento_section,
            '{{logo_ventas}}' => LOGO_VENTAS,
            '{{woocommerce_url}}' => VENTAS_URL,
            '{{customer_note}}' => $data['customer_note'],
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generar HTML de productos para PDF
     * @param mixed $productos - Productos (array o mysqli_result)
     * @param int $orden_id - ID de la orden
     * @param float $vtotal - Total de la orden
     * @return string - HTML de productos
     */
    private static function generateProductsHTML($productos, $orden_id, $vtotal): string
    {
        $productos_html = '';
        
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
                    
                    $productos_html .= self::generateProductRow($nomprod, $cant, $line_total, $line_subtotal, $regular_price, $sale_price, $sku);
                }
            }
            // Si es un array de productos
            else if (is_array($productos)) {
                foreach ($productos as $producto) {
                    $nomprod = $producto['nombre_producto'] ?? $producto['order_item_name'] ?? $producto['nombre'] ?? '';
                    $cant = (int)($producto['cantidad'] ?? $producto['product_qty'] ?? 0);
                    $line_total = (float)($producto['total'] ?? $producto['line_total'] ?? 0);
                    $line_subtotal = (float)($producto['subtotal'] ?? $producto['line_subtotal'] ?? 0);
                    $regular_price = (float)($producto['precio_regular'] ?? $producto['regular_price'] ?? 0);
                    $sale_price = (float)($producto['precio_oferta'] ?? $producto['sale_price'] ?? 0);
                    $sku = $producto['sku'] ?? $producto['product_sku'] ?? '';
                    
                    $productos_html .= self::generateProductRow($nomprod, $cant, $line_total, $line_subtotal, $regular_price, $sale_price, $sku);
                }
            }
        } else {
            // Si no hay productos específicos, mostrar la orden completa
            $productos_html .= '
          <tr>
            <td style="text-align: center; vertical-align: top">1</td>
            <td style="word-wrap: break-word; width: 180; vertical-align: top">Orden WooCommerce #'.$orden_id.'</td>
            <td style="text-align: right; vertical-align: top">'.number_format($vtotal).'</td>
            <td style="text-align: right; vertical-align: top">'.number_format($vtotal).'</td>
          </tr>';
        }
        
        return $productos_html;
    }

    /**
     * Generar fila HTML de un producto
     * @param string $nomprod - Nombre del producto
     * @param int $cant - Cantidad
     * @param float $line_total - Total de línea
     * @param float $line_subtotal - Subtotal de línea
     * @param float $regular_price - Precio regular
     * @param float $sale_price - Precio de oferta
     * @param string $sku - SKU del producto
     * @return string - HTML de la fila del producto
     */
    private static function generateProductRow($nomprod, $cant, $line_total, $line_subtotal, $regular_price, $sale_price, $sku): string
    {
        $descripcion_completa = "";

        // Agregar SKU si existe
        if (!empty($sku)) {
            $descripcion_completa .= '<span class="sku-text">SKU: '.$sku.'<br></span>';
        }
        $descripcion_completa .= $nomprod;
        
        // Calcular precio unitario correctamente
        $vunit = $cant > 0 ? $line_total / $cant : 0;
        
        // Detectar si hay descuento
        $hay_descuento = false;
        $precio_original = 0;
        $precio_con_descuento = $vunit;
        
        if ($line_subtotal > $line_total && $line_subtotal > 0) {
            $hay_descuento = true;
            $precio_original = $cant > 0 ? $line_subtotal / $cant : 0;
            $precio_con_descuento = $vunit;
        }
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
            $precio_html = '<br>'.number_format($vunit);
        }
        
        return '
              <tr>
                <td style="text-align: center; vertical-align: top"><br>'.$cant.'</td>
                <td style="word-wrap: break-word; width: 180; vertical-align: top">'.$descripcion_completa.'</td>
                <td style="text-align: right; vertical-align: top">'.$precio_html.'</td>
                <td style="text-align: right; vertical-align: top"><br>'.number_format($line_total).'</td>
              </tr>';
    }

    /**
     * Procesar template de PDF de factura
     * @param array $data - Datos para reemplazar en el template
     * @return string - HTML procesado
     */
    public static function processPDFTemplate(array $data): string
    {
        $template_path = __DIR__ . '/../templates/pdf_factura.html';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template de PDF no encontrado: $template_path");
        }
        
        $template = file_get_contents($template_path);
        
        // Generar HTML de productos
        $productos_html = self::generateProductsHTML(
            $data['productos'] ?? null, 
            $data['orden_id'] ?? 0, 
            $data['vtotal'] ?? 0
        );
        
        // Procesar secciones condicionales
        $metodo_pago_section = !empty($data['metodo']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>Método de Pago: </strong>{$data['metodo']}</td>
            </tr>" : "";
        
        $dni_section = !empty($data['dni']) ? "
            <tr>
                <td colspan=\"4\"><strong>DNI:</strong> {$data['dni']}</td>
            </tr>" : "";
        
        $documento_section = !empty($data['documento']) ? "
            <tr>
                <td colspan=\"4\"><strong>Documento:</strong> {$data['documento']}</td>
            </tr>" : "";
        
        $direccion_completa_section = !empty($data['direccion_completa']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>Dirección:</strong> {$data['direccion_completa']}</td>
            </tr>" : "";
        
        $barrio_section = !empty($data['barrio']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>Barrio:</strong> {$data['barrio']}</td>
            </tr>" : "";
        
        $ubicacion_section = !empty($data['ubicacion']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>Ubicación:</strong> {$data['ubicacion']}</td>
            </tr>" : "";
        
        $direccion_section = !empty($data['direccion']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\">Dirección: {$data['direccion']}</td>
            </tr>" : "";
        
        $observaciones_section = !empty($data['observaciones']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>{$data['observaciones']}</strong></td>
            </tr>" : "";
        
        $comentarios_section = !empty($data['comentarios']) ? "
            <tr>
                <td colspan=\"4\" style=\"word-wrap: break-word; width: 180\"><strong>Observaciones:</strong> " . htmlspecialchars($data['comentarios']) . "</td>
            </tr>" : "";
        
        $envio_section = ($data['envio'] > 0) ? "
            <tr>
                <td style=\"text-align: center; vertical-align: top\"><br>1</td>
                <td style=\"vertical-align: top\"><br>Domicilio</td>
                <td style=\"text-align: right; vertical-align: top\"><br>" . number_format($data['envio']) . "</td>
                <td style=\"text-align: right; vertical-align: top\"><br>" . number_format($data['envio']) . "</td>
            </tr>" : "";
        
        $descuento_section = ($data['descuento'] > 0) ? "
            <tr>
                <td style=\"text-align: center; vertical-align: top\"><br>1</td>
                <td style=\"vertical-align: top\"><br>Descuento</td>
                <td style=\"text-align: right; vertical-align: top\"><br>-" . number_format($data['descuento']) . "</td>
                <td style=\"text-align: right; vertical-align: top\"><br>-" . number_format($data['descuento']) . "</td>
            </tr>" : "";
        
        // Reemplazos simples
        $replacements = [
            '{{factura_num}}' => $data['factura_num'],
            '{{logo_factura}}' => LOGO_FACTURA,
            '{{woocommerce_url}}' => VENTAS_URL,
            '{{fecha}}' => $data['fecha'],
            '{{factura_formateada}}' => $data['factura_formateada'],
            '{{orden_id}}' => $data['orden_id'] ?? '',
            '{{nombre_cliente_upper}}' => strtoupper($data['nombre1'] ?? ''),
            '{{apellido_cliente_upper}}' => strtoupper($data['nombre2'] ?? ''),
            '{{celular}}' => $data['celular'] ?? '',
            '{{correo}}' => $data['correo'] ?? '',
            '{{productos_html}}' => $productos_html,
            '{{total_formateado}}' => number_format($data['vtotal'] ?? 0),
            '{{woocommerce_url_pedido}}' => $data['woocommerce_url'] ?? '',
            // Secciones condicionales
            '{{metodo_pago_section}}' => $metodo_pago_section,
            '{{dni_section}}' => $dni_section,
            '{{documento_section}}' => $documento_section,
            '{{direccion_completa_section}}' => $direccion_completa_section,
            '{{barrio_section}}' => $barrio_section,
            '{{ubicacion_section}}' => $ubicacion_section,
            '{{direccion_section}}' => $direccion_section,
            '{{comentarios_section}}' => $comentarios_section,
            '{{envio_section}}' => $envio_section,
            '{{descuento_section}}' => $descuento_section,
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
