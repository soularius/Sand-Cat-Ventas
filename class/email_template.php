<?php

/**
 * Clase para manejar templates de email
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
            '{{woocommerce_url}}' => VENTAS_URL
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
