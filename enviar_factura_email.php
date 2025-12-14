<?php
/**
 * Envío de facturas por email usando PHPMailer
 */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar dependencias específicas
require_once __DIR__ . '/pdf_generator.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener parámetros
$orden_id = $_POST['orden_id'] ?? null;
$factura_id = $_POST['factura_id'] ?? null;
$email_destino = $_POST['email_destino'] ?? null;

if (!$orden_id || !$factura_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros faltantes: orden_id y factura_id son requeridos']);
    exit;
}

try {
    // Conectar a la base de datos
    $miau = new mysqli(
        $_ENV['DB_WORDPRESS_HOST'] ?? 'localhost',
        $_ENV['DB_WORDPRESS_USER'] ?? 'root', 
        $_ENV['DB_WORDPRESS_PASS'] ?? '',
        $_ENV['DB_WORDPRESS_NAME'] ?? 'miau'
    );

    if ($miau->connect_error) {
        throw new Exception("Error de conexión: " . $miau->connect_error);
    }

    // Obtener datos de la orden (incluyendo dirección completa como generar_pdf.php)
    $query = "SELECT 
        o.id as orden_id,
        o.total_amount as total,
        o.date_created_gmt as fecha_orden,
        ba.first_name as nombre_cliente,
        ba.last_name as apellido_cliente,
        ba.email as email_cliente,
        ba.phone as telefono_cliente,
        ba.address_1 as direccion_1,
        ba.address_2 as direccion_2,
        ba.city as ciudad,
        ba.state as departamento,
        ba.country as pais
    FROM miau_wc_orders o
    LEFT JOIN miau_wc_order_addresses ba ON o.id = ba.order_id AND ba.address_type = 'billing'
    WHERE o.id = ?";

    $stmt = $miau->prepare($query);
    $stmt->bind_param("i", $orden_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orden = $result->fetch_assoc();

    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }

    // Obtener envío/descuento/método/DNI/barrio desde legacy postmeta (compatibilidad)
    $metaQuery = "SELECT meta_key, meta_value FROM miau_postmeta WHERE post_id = ? AND meta_key IN ('_order_shipping','_cart_discount','_payment_method_title','_billing_dni','_billing_barrio')";
    $metaStmt = $miau->prepare($metaQuery);
    $meta = ['_order_shipping' => '0', '_cart_discount' => '0', '_payment_method_title' => '', '_billing_dni' => '', '_billing_barrio' => ''];
    if ($metaStmt) {
        $metaStmt->bind_param('i', $orden_id);
        $metaStmt->execute();
        $metaRes = $metaStmt->get_result();
        if ($metaRes) {
            while ($row = $metaRes->fetch_assoc()) {
                $k = (string)($row['meta_key'] ?? '');
                $meta[$k] = (string)($row['meta_value'] ?? '');
            }
        }
        $metaStmt->close();
    }

    $orden['envio'] = (float)preg_replace('/[^0-9]/', '', (string)($meta['_order_shipping'] ?? '0'));
    $orden['descuento'] = (float)preg_replace('/[^0-9]/', '', (string)($meta['_cart_discount'] ?? '0'));
    $orden['titulo_metodo_pago'] = (string)($meta['_payment_method_title'] ?? '');
    $orden['dni'] = (string)($meta['_billing_dni'] ?? '');
    $orden['barrio'] = (string)($meta['_billing_barrio'] ?? '');

    // Convertir código de departamento a nombre completo (igual que generar_pdf.php)
    if (!empty($orden['departamento'])) {
        // Si el departamento contiene "CO-", extraer solo la parte del código
        $codigo_departamento = $orden['departamento'];
        if (strpos($codigo_departamento, 'CO-') === 0) {
            $codigo_departamento = substr($codigo_departamento, 3); // Quitar "CO-"
        }
        
        // Convertir código a nombre usando los datos del plugin Colombia
        $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
        if (file_exists($states_file)) {
            $colombia_states = include($states_file);
            foreach ($colombia_states as $code => $name) {
                if (strtoupper($code) === strtoupper($codigo_departamento)) {
                    $orden['departamento'] = $name;
                    break;
                }
            }
        }
    }

    // Determinar email de destino: personalizado o del cliente
    if (!empty($email_destino)) {
        // Validar formato del email personalizado
        if (!filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email personalizado no tiene un formato válido: " . $email_destino);
        }
    } else {
        // Usar email del cliente si no se especifica uno personalizado
        if (empty($orden['email_cliente'])) {
            throw new Exception("El cliente no tiene email registrado y no se proporcionó email personalizado");
        }
        $email_destino = $orden['email_cliente'];
    }

    // Generar PDF usando la función centralizada
    $factura_num = $factura_id;
    $fecha = date('d/m/Y H:i', strtotime($orden['fecha_orden']));
    $factura_formateada = str_pad($factura_num, 10, '0', STR_PAD_LEFT);

    // Generar URL para el QR del pedido de WooCommerce usando variables del .env
    $woocommerce_base_url = $_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU';
    $woocommerce_order_path = $_ENV['WOOCOMMERCE_ORDER_PATH'] ?? '/mi-cuenta/ver-pedido/{id_pedido}/';
    $woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $orden_id, $woocommerce_order_path);

    // Obtener productos de la orden usando la misma consulta optimizada que generar_pdf.php
    $query_productos = "
        SELECT
            I.order_item_id,
            I.order_item_name as nombre_producto,
            I.order_id,

            /* Cantidad y totales desde el pedido (lo que realmente se facturó) */
            COALESCE(CAST(IM.qty AS UNSIGNED), 1) AS cantidad,

            COALESCE(CAST(IM.line_total AS DECIMAL(18,2)), 0)    AS line_total,
            COALESCE(CAST(IM.line_subtotal AS DECIMAL(18,2)), 0) AS line_subtotal,

            /* Unitarios basados en el pedido (RECOMENDADO para factura) */
            CASE
                WHEN COALESCE(CAST(IM.qty AS UNSIGNED), 1) > 0
                THEN ROUND(COALESCE(CAST(IM.line_subtotal AS DECIMAL(18,2)), 0) / COALESCE(CAST(IM.qty AS UNSIGNED), 1), 2)
                ELSE 0
            END AS regular_price,  -- precio unitario sin descuentos (del pedido)

            CASE
                WHEN COALESCE(CAST(IM.qty AS UNSIGNED), 1) > 0
                THEN ROUND(COALESCE(CAST(IM.line_total AS DECIMAL(18,2)), 0) / COALESCE(CAST(IM.qty AS UNSIGNED), 1), 2)
                ELSE 0
            END AS sale_price,     -- precio unitario con descuentos (del pedido)

            /* SKU: variación si existe, si no producto */
            COALESCE(PM_sku_var.sku_var, PM_sku_prod.sku_prod, '') AS sku,

            /* IDs para obtener imagen y link del producto */
            CAST(IM.product_id AS UNSIGNED) AS product_id,
            CAST(IM.variation_id AS UNSIGNED) AS variation_id

        FROM miau_woocommerce_order_items I

        /* Pivot de itemmeta: saco lo necesario en un solo join */
        LEFT JOIN (
            SELECT
                order_item_id,
                MAX(CASE WHEN meta_key = '_qty' THEN meta_value END)           AS qty,
                MAX(CASE WHEN meta_key = '_line_total' THEN meta_value END)    AS line_total,
                MAX(CASE WHEN meta_key = '_line_subtotal' THEN meta_value END) AS line_subtotal,
                MAX(CASE WHEN meta_key = '_product_id' THEN meta_value END)    AS product_id,
                MAX(CASE WHEN meta_key = '_variation_id' THEN meta_value END)  AS variation_id
            FROM miau_woocommerce_order_itemmeta
            WHERE meta_key IN ('_qty','_line_total','_line_subtotal','_product_id','_variation_id')
            GROUP BY order_item_id
        ) IM
            ON IM.order_item_id = I.order_item_id

        /* SKU usando subconsultas para evitar duplicados */
        LEFT JOIN (
            SELECT post_id, meta_value as sku_prod
            FROM miau_postmeta 
            WHERE meta_key = '_sku'
        ) PM_sku_prod
            ON PM_sku_prod.post_id = CAST(IM.product_id AS UNSIGNED)

        LEFT JOIN (
            SELECT post_id, meta_value as sku_var
            FROM miau_postmeta 
            WHERE meta_key = '_sku'
        ) PM_sku_var
            ON PM_sku_var.post_id = CAST(IM.variation_id AS UNSIGNED)

        WHERE
            I.order_id = ? 
            AND I.order_item_type = 'line_item'
        GROUP BY I.order_item_id";
    
    $stmt_productos = $miau->prepare($query_productos);
    $stmt_productos->bind_param("i", $orden_id);
    $stmt_productos->execute();
    $productos_result = $stmt_productos->get_result();
    
    // Convertir productos a array para la función centralizada
    $productos_array = [];
    if ($productos_result && $productos_result->num_rows > 0) {
        while ($producto = $productos_result->fetch_assoc()) {
            // Calcular total_producto para compatibilidad con pdf_generator.php
            $producto['total_producto'] = $producto['line_total'];
            
            // URL del producto en la tienda (mantener enlaces pero eliminar lógica de imágenes)
            $producto['product_url'] = $_ENV['WOOCOMMERCE_BASE_URL'] . '/?p=' . $producto['product_id'];
            
            $productos_array[] = $producto;
        }
    }

    // Preparar datos para el generador centralizado (incluyendo dirección completa)
    $datos_pdf = [
        'fecha' => $fecha,
        'nombre1' => $orden['nombre_cliente'],
        'nombre2' => $orden['apellido_cliente'],
        'correo' => $orden['email_cliente'],
        'celular' => $orden['telefono_cliente'],
        'vtotal' => $orden['total'],
        'factura_formateada' => $factura_formateada,
        'factura_num' => $factura_num,
        'orden_id' => $orden_id,
        'woocommerce_url' => $woocommerce_url,
        'productos' => $productos_array,
        'envio' => (float)($orden['envio'] ?? 0),
        'descuento' => (float)($orden['descuento'] ?? 0),
        'metodo' => (string)($orden['titulo_metodo_pago'] ?? ''),
        // Campos de dirección completa
        'direccion_1' => (string)($orden['direccion_1'] ?? ''),
        'direccion_2' => (string)($orden['direccion_2'] ?? ''),
        'ciudad' => (string)($orden['ciudad'] ?? ''),
        'departamento' => (string)($orden['departamento'] ?? ''),
        'pais' => (string)($orden['pais'] ?? ''),
        'barrio' => (string)($orden['barrio'] ?? ''),
        'dni' => (string)($orden['dni'] ?? '')
    ];

    // Generar PDF como string para adjuntar al email
    $pdf_content = generarPDFFactura($datos_pdf, 'S');

    // Configurar PHPMailer
    $mail = new PHPMailer(true);

    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
    $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
    $mail->CharSet = 'UTF-8';

    // Remitente
    $mail->setFrom(
        $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@sandycat.com.co',
        $_ENV['MAIL_FROM_NAME'] ?? 'Sand Y Cat - Sistema de Ventas'
    );

    // Destinatario
    $mail->addAddress($email_destino, $orden['nombre_cliente'] . ' ' . $orden['apellido_cliente']);

    // Adjuntar PDF
    $mail->addStringAttachment($pdf_content, "Factura_$factura_formateada.pdf", 'base64', 'application/pdf');

    // Contenido del email
    $mail->isHTML(true);
    $mail->Subject = "Factura #$factura_formateada - Pedido #$orden_id";
    
    // Construir tabla de productos para el email (sin imágenes para evitar bloqueos)
    $productos_html = '';
    if (!empty($productos_array)) {
        foreach ($productos_array as $producto) {
            $precio_unitario = $producto['cantidad'] > 0 ? $producto['total_producto'] / $producto['cantidad'] : 0;
            $sku_html = !empty($producto['sku']) ? "<small style='color: #666; font-style: italic;'>SKU: {$producto['sku']}</small><br>" : '';
            
            // URL del producto (mantener enlaces pero sin imágenes)
            $product_url = !empty($producto['product_url']) ? $producto['product_url'] : '#';
            
            $productos_html .= "
                <tr>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center; vertical-align: top;'>{$producto['cantidad']}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd; vertical-align: top;'>
                        {$sku_html}
                        <a href='$product_url' target='_blank' style='color: #333; text-decoration: none; font-weight: 500;'>
                            {$producto['nombre_producto']}
                        </a>
                    </td>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: top;'>$" . number_format($precio_unitario, 0) . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: top;'>$" . number_format($producto['total_producto'], 0) . "</td>
                </tr>";
        }
    }

    // Construir dirección completa
    $direccion_completa = '';
    if (!empty($orden['direccion_1'])) {
        $direccion_completa = $orden['direccion_1'];
        if (!empty($orden['direccion_2'])) {
            $direccion_completa .= ', ' . $orden['direccion_2'];
        }
    }

    $ubicacion = '';
    if (!empty($orden['ciudad'])) {
        $ubicacion = $orden['ciudad'];
    }
    if (!empty($orden['departamento'])) {
        $ubicacion .= ($ubicacion ? ', ' : '') . $orden['departamento'];
    }
    if (!empty($orden['pais'])) {
        $pais_nombre = ($orden['pais'] === 'CO') ? 'Colombia' : $orden['pais'];
        $ubicacion .= ($ubicacion ? ', ' : '') . $pais_nombre;
    }

    $mail->Body = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f5f5f5; 
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background-color: white; 
                border-radius: 8px; 
                overflow: hidden; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #28a745, #20c997); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .logo { 
                max-width: 120px; 
                height: auto; 
                margin-bottom: 10px; 
            }
            .content { 
                padding: 30px 20px; 
            }
            .invoice-info { 
                background-color: #f8f9fa; 
                padding: 20px; 
                border-radius: 6px; 
                margin: 20px 0; 
            }
            .customer-info { 
                background-color: #e3f2fd; 
                padding: 15px; 
                border-radius: 6px; 
                margin: 15px 0; 
            }
            .products-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 20px 0; 
            }
            .products-table th { 
                background-color: #28a745; 
                color: white; 
                padding: 12px 8px; 
                text-align: left; 
            }
            .products-table td { 
                padding: 8px; 
                border-bottom: 1px solid #ddd; 
            }
            .total-section { 
                background-color: #f8f9fa; 
                padding: 15px; 
                border-radius: 6px; 
                margin: 20px 0; 
                text-align: right; 
            }
            .footer { 
                background-color: #343a40; 
                color: white; 
                padding: 20px; 
                text-align: center; 
                font-size: 14px; 
            }
            .highlight { 
                color: #dc3545; 
                font-weight: bold; 
            }
            .btn { 
                display: inline-block; 
                padding: 12px 24px; 
                background-color: #28a745; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px; 
                margin: 10px 0; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='http://localhost/ventas/assets/img/logo.png' alt='Sand Y Cat Logo' class='logo'>
                <h2>SAND Y CAT</h2>
                <p>Hugo Alejandro López</p>
                <p>Factura Electrónica</p>
            </div>
            
            <div class='content'>
                <h3>¡Gracias por su compra!</h3>
                <p>Estimado/a <strong>{$orden['nombre_cliente']} {$orden['apellido_cliente']}</strong>,</p>
                
                <p>Su pedido ha sido procesado exitosamente. Adjunto encontrará la factura en formato PDF.</p>
                
                <div class='invoice-info'>
                    <h4>Información de la Factura</h4>
                    <p><strong>Número de Factura:</strong> <span class='highlight'>$factura_formateada</span></p>
                    <p><strong>Número de Pedido:</strong> #$orden_id</p>
                    <p><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($orden['fecha_orden'])) . "</p>
                    <p><strong>Método de Pago:</strong> {$orden['titulo_metodo_pago']}</p>
                </div>

                <div class='customer-info'>
                    <h4>Datos del Cliente</h4>
                    <p><strong>Cliente:</strong> {$orden['nombre_cliente']} {$orden['apellido_cliente']}</p>" .
                    (!empty($orden['dni']) ? "<p><strong>DNI:</strong> {$orden['dni']}</p>" : "") . "
                    <p><strong>Email:</strong> {$orden['email_cliente']}</p>
                    <p><strong>Teléfono:</strong> {$orden['telefono_cliente']}</p>" .
                    (!empty($direccion_completa) ? "<p><strong>Dirección:</strong> $direccion_completa</p>" : "") .
                    (!empty($orden['barrio']) ? "<p><strong>Barrio:</strong> {$orden['barrio']}</p>" : "") .
                    (!empty($ubicacion) ? "<p><strong>Ubicación:</strong> $ubicacion</p>" : "") . "
                </div>

                <h4>Detalle de Productos</h4>
                <table class='products-table'>
                    <thead>
                        <tr>
                            <th style='text-align: center;'>Cant.</th>
                            <th>Descripción</th>
                            <th style='text-align: right;'>V. Unit.</th>
                            <th style='text-align: right;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        $productos_html" .
                        ($orden['envio'] > 0 ? "
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>1</td>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd;'>Domicilio</td>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($orden['envio'], 0) . "</td>
                            <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($orden['envio'], 0) . "</td>
                        </tr>" : "") . "
                    </tbody>
                </table>

                <div class='total-section'>" .
                    ($orden['descuento'] > 0 ? "<p><strong>Descuento:</strong> <span style='color: #dc3545;'>-$" . number_format($orden['descuento'], 0) . "</span></p>" : "") . "
                    <h3><strong>TOTAL: $" . number_format($orden['total'], 0) . "</strong></h3>
                </div>

                <p>Si tiene alguna pregunta sobre su pedido, no dude en contactarnos.</p>
                
                <p style='text-align: center;'>
                    <a href='$woocommerce_url' class='btn'>Ver Pedido en Línea</a>
                </p>
            </div>
            
            <div class='footer'>
                <p><strong>Sand Y Cat - Hugo Alejandro López</strong></p>
                <p>NIT: 79690971 | Teléfono: 6016378243</p>
                <p>Dirección: Cra. 61 No. 78-25</p>
                <p>www.sandycat.com.co</p>
            </div>
        </div>
    </body>
    </html>";

    $mail->AltBody = "
    Estimado/a {$orden['nombre_cliente']} {$orden['apellido_cliente']},
    
    Adjunto encontrará la factura correspondiente a su pedido:
    
    - Número de Factura: $factura_formateada
    - Número de Pedido: #$orden_id
    - Fecha: " . date('d/m/Y H:i', strtotime($orden['fecha_orden'])) . "
    - Total: $" . number_format($orden['total'], 2) . "
    
    Gracias por su compra.
    
    Sand Y Cat - Hugo Alejandro López
    NIT: 79690971
    Teléfono: 6016378243
    www.sandycat.com.co";

    // Enviar email
    $mail->send();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Factura enviada exitosamente',
        'email' => $email_destino,
        'factura' => $factura_formateada
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al enviar email: ' . $e->getMessage()
    ]);
} finally {
    if (isset($miau)) {
        $miau->close();
    }
}
?>
