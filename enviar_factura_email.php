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

    // Generar PDF usando la función centralizada
    $factura_num = $factura_id;
    $fecha = date('d/m/Y H:i', strtotime($orden['fecha_orden']));
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
    $productos_result = $stmt_productos->get_result();
    
    // Convertir productos a array para la función centralizada
    $productos_array = [];
    if ($productos_result && $productos_result->num_rows > 0) {
        while ($producto = $productos_result->fetch_assoc()) {
            $productos_array[] = $producto;
        }
    }

    // Preparar datos para el generador centralizado
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
        'productos' => $productos_array
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
