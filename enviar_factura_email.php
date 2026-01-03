<?php
/**
 * Envío de facturas por email usando PHPMailer
 */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar dependencias específicas
require_once __DIR__ . '/pdf_generator.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once('parts/login_handler.php');
require_once('class/woocommerce_orders.php');
require_once('class/email_template.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Verificar autenticación usando login_handler
if (!Utils::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado. Debe iniciar sesión para enviar facturas.']);
    exit;
}

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
    // Usar la clase WooCommerceOrders para obtener datos de la orden
    $wooOrders = new WooCommerceOrders();
    $orden = $wooOrders->getOrderDataForEmail((int)$orden_id);

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
    $woocommerce_base_url = URL_WOOCOMMERCE;
    $woocommerce_order_path = WOOCOMMERCE_ORDER_PATH;
    $woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $orden_id, $woocommerce_order_path);

    // Obtener productos de la orden usando el método centralizado
    $productos_array = $wooOrders->getOrderProductsForInvoice((int)$orden_id);

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
        'dni' => (string)($orden['dni'] ?? ''),
        'comentarios' => (string)($orden['customer_note'] ?? ''),
    ];

    // Generar PDF como string para adjuntar al email
    $pdf_content = generarPDFFactura($datos_pdf, 'S');

    // Configurar PHPMailer
    $mail = new PHPMailer(true);

    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    $mail->Port = MAIL_PORT;
    $mail->CharSet = 'UTF-8';

    // Remitente
    $mail->setFrom(
        MAIL_FROM_ADDRESS,
        MAIL_FROM_NAME
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

    // Preparar datos para el template
    $template_data = [
        'nombre_cliente' => $orden['nombre_cliente'],
        'apellido_cliente' => $orden['apellido_cliente'],
        'factura_formateada' => $factura_formateada,
        'orden_id' => $orden_id,
        'fecha_orden' => date('d/m/Y H:i', strtotime($orden['fecha_orden'])),
        'metodo_pago' => $orden['metodo_pago'],
        'email_cliente' => $orden['email_cliente'],
        'telefono_cliente' => $orden['telefono_cliente'],
        'productos_html' => $productos_html,
        'total' => $orden['total'],
        'woocommerce_url' => $woocommerce_url,
        'dni' => $orden['dni'] ?? '',
        'direccion_completa' => $direccion_completa,
        'barrio' => $orden['barrio'] ?? '',
        'ubicacion' => $ubicacion,
        'envio' => (float)($orden['envio'] ?? 0),
        'descuento' => (float)($orden['descuento'] ?? 0),
        'customer_note' => $orden['customer_note'] ?? '',
    ];

    // Procesar template
    $mail->Body = EmailTemplate::processInvoiceTemplate($template_data);

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
