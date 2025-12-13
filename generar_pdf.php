<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar dependencias específicas
require_once('pdf_generator.php');

// Verificar autenticación
if (!isset($_SESSION['MM_Username'])) {
    header("Location: facturacion.php");
    exit;
}

// Obtener parámetros
$orden_id = isset($_GET['orden']) ? intval($_GET['orden']) : 0;
$factura_num = isset($_GET['factura']) ? $_GET['factura'] : '';
$download = isset($_GET['download']) ? true : false;
$embed = isset($_GET['embed']) ? true : false;
$print = isset($_GET['print']) ? true : false;

if (!$orden_id || !$factura_num) {
    die("Parámetros inválidos");
}

// Verificar que la factura existe
$orden_id_safe = (int)$orden_id;
$factura_num_safe = mysqli_real_escape_string($sandycat, (string)$factura_num);
$query_factura = "SELECT * FROM facturas WHERE id_order = '{$orden_id_safe}' AND factura = '{$factura_num_safe}' AND estado = 'a'";
$result_factura = mysqli_query($sandycat, $query_factura);

if (mysqli_num_rows($result_factura) == 0) {
    die("Factura no encontrada");
}

// Obtener datos de la orden desde WooCommerce HPOS
$query_orden = "
    SELECT 
        o.id as order_id,
        o.date_created_gmt as fecha_orden,
        o.status as estado,
        COALESCE(o.total_amount, 0) as total,
        COALESCE(o.shipping_amount, 0) as envio,
        COALESCE(o.discount_amount, 0) as descuento,
        COALESCE(o.billing_email, '') as email_cliente,
        COALESCE(ba.first_name, '') as nombre_cliente,
        COALESCE(ba.last_name, '') as apellido_cliente,
        COALESCE(ba.phone, '') as telefono_cliente,
        COALESCE(ba.email, o.billing_email) as email_completo,
        COALESCE(o.payment_method_title, '') as titulo_metodo_pago
    FROM miau_wc_orders o
    LEFT JOIN miau_wc_order_addresses ba 
        ON o.id = ba.order_id 
        AND ba.address_type = 'billing'
    WHERE o.id = '$orden_id' AND o.type = 'shop_order'
";

$result_orden = mysqli_query($miau, $query_orden);
if (mysqli_num_rows($result_orden) == 0) {
    die("Orden no encontrada");
}

$orden = mysqli_fetch_assoc($result_orden);

// Obtener productos de la orden
$query_productos = "
    SELECT 
        I.order_item_id, 
        order_item_name, 
        I.order_id, 
        L.order_id, 
        order_item_type, 
        product_qty, 
        product_net_revenue, 
        coupon_amount, 
        shipping_amount, 
        L.order_item_id 
    FROM miau_woocommerce_order_items I 
    RIGHT JOIN miau_wc_order_product_lookup L ON I.order_item_id = L.order_item_id 
    WHERE I.order_id = '$orden_id' AND order_item_type='line_item'
";
$productos = mysqli_query($miau, $query_productos);

// Preparar datos para el generador de PDF
$fecha = date('d/m/Y H:i', strtotime($orden['fecha_orden']));
$factura_formateada = str_pad($factura_num, 10, '0', STR_PAD_LEFT);

// Generar URL para el QR del pedido de WooCommerce usando variables del .env
$woocommerce_base_url = $_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU';
$woocommerce_order_path = $_ENV['WOOCOMMERCE_ORDER_PATH'] ?? '/mi-cuenta/ver-pedido/{id_pedido}/';
$woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $orden_id, $woocommerce_order_path);

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
    'productos' => $productos,
    'envio' => (float)($orden['envio'] ?? 0),
    'descuento' => (float)($orden['descuento'] ?? 0),
    'metodo' => (string)($orden['titulo_metodo_pago'] ?? '')
];

// Determinar modo de salida
$output_mode = 'I'; // Por defecto inline
if ($download) {
    $output_mode = 'D'; // Download
} elseif ($embed) {
    $output_mode = 'I'; // Inline/Embed
}

// Generar PDF usando la función centralizada
generarPDFFactura($datos_pdf, $output_mode, "Factura $factura_formateada.pdf");

// Limpiar recursos
mysqli_free_result($result_factura);
mysqli_free_result($result_orden);
if ($productos) mysqli_free_result($productos);
?>
