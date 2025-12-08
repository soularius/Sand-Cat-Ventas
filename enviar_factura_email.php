<?php
/**
 * Envío de facturas por email usando PHPMailer
 */

// Cargar configuración
require_once __DIR__ . '/config.php';
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

    // Obtener datos de la orden
    $query = "SELECT 
        o.id as orden_id,
        o.total_amount as total,
        o.date_created_gmt as fecha_orden,
        ba.first_name as nombre_cliente,
        ba.last_name as apellido_cliente,
        ba.email as email_cliente,
        ba.phone as telefono_cliente
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

    // Verificar que el cliente tenga email
    if (empty($orden['email_cliente'])) {
        throw new Exception("El cliente no tiene email registrado");
    }

    $email_destino = $orden['email_cliente'];

    // Generar PDF directamente en memoria usando el mismo código que generar_pdf.php
    ob_start();
    
    // Incluir la lógica de generación de PDF
    $factura_num = $factura_id;
    
    // Usar el mismo formato de PDF que el sistema existente
    $fecha = date('d/m/Y H:i', strtotime($orden['fecha_orden']));
    $nombre1 = $orden['nombre_cliente'];
    $nombre2 = $orden['apellido_cliente'];
    $correo = $orden['email_cliente'];
    $celular = $orden['telefono_cliente'];
    $vtotal = $orden['total'];

    // Formatear número de factura con 10 dígitos
    $factura_formateada = str_pad($factura_num, 10, '0', STR_PAD_LEFT);

    // Generar URL para el QR del pedido de WooCommerce usando variables del .env
    $woocommerce_base_url = $_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU';
    $woocommerce_order_path = $_ENV['WOOCOMMERCE_ORDER_PATH'] ?? '/mi-cuenta/ver-pedido/{id_pedido}/';
    $woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $orden_id, $woocommerce_order_path);

    // Obtener productos de la orden
    $query_productos = "SELECT 
        I.order_item_name as nombre_producto,
        L.product_qty as cantidad,
        L.product_net_revenue as total_producto
    FROM miau_woocommerce_order_items I 
    RIGHT JOIN miau_wc_order_product_lookup L ON I.order_item_id = L.order_item_id 
    WHERE I.order_id = ? AND order_item_type='line_item'";
    
    $stmt_productos = $miau->prepare($query_productos);
    $stmt_productos->bind_param("i", $orden_id);
    $stmt_productos->execute();
    $productos = $stmt_productos->get_result();

    // Generar HTML para PDF
    $cuerpo = '
    <html>
    <title>Factura POS '.$factura_num.'</title>
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
      </tr>
      <tr>
        <td colspan="4">Email: '.$correo.'</td>
      </tr>
      <tr>
        <td colspan="4">Teléfono: '.$celular.'</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;"></td>
      </tr>
      <tr>
        <td style="text-align: center"><strong>Cant.</strong></td>
        <td><strong>Descripción</strong></td>
        <td style="text-align: center"><strong>V Un.</strong></td>
        <td style="text-align: center"><strong>TOTAL</strong></td>
      </tr>';

    // Agregar productos si existen
    if ($productos && $productos->num_rows > 0) {
        while ($producto = $productos->fetch_assoc()) {
            $precio_unitario = $producto['total_producto'] / $producto['cantidad'];
            $cuerpo .= '
            <tr>
                <td style="text-align: center">'.$producto['cantidad'].'</td>
                <td style="word-wrap: break-word; width: 120">'.htmlspecialchars($producto['nombre_producto']).'</td>
                <td style="text-align: center">'.number_format($precio_unitario).'</td>
                <td style="text-align: center">'.number_format($producto['total_producto']).'</td>
            </tr>';
        }
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

    // Usar configuración centralizada de mPDF
    require_once __DIR__ . '/mpdf_config.php';
    $mpdf = createMpdfInstance();

    $mpdf->AliasNbPages('{PageTotal}');
    $mpdf->WriteHTML($cuerpo);
    
    // Obtener PDF como string
    $pdf_content = $mpdf->Output('', 'S'); // 'S' = String
    
    ob_end_clean();

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
    
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #e9ecef; padding: 15px; text-align: center; font-size: 12px; }
            .highlight { color: #dc3545; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Sand Y Cat - Hugo Alejandro López</h2>
            <p>Factura Electrónica</p>
        </div>
        
        <div class='content'>
            <p>Estimado/a <strong>{$orden['nombre_cliente']} {$orden['apellido_cliente']}</strong>,</p>
            
            <p>Adjunto encontrará la factura correspondiente a su pedido:</p>
            
            <ul>
                <li><strong>Número de Factura:</strong> <span class='highlight'>$factura_formateada</span></li>
                <li><strong>Número de Pedido:</strong> #$orden_id</li>
                <li><strong>Fecha:</strong> " . date('d/m/Y H:i', strtotime($orden['fecha_orden'])) . "</li>
                <li><strong>Total:</strong> $" . number_format($orden['total'], 2) . "</li>
            </ul>
            
            <p>Gracias por su compra. Si tiene alguna pregunta, no dude en contactarnos.</p>
        </div>
        
        <div class='footer'>
            <p>Sand Y Cat - Hugo Alejandro López<br>
            NIT: 79690971<br>
            Teléfono: 6016378243<br>
            Dirección: Cra. 61 No. 78-25<br>
            www.sandycat.com.co</p>
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
