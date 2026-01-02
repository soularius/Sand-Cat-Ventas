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

// Usar la clase WooCommerceOrders para obtener datos de la orden
$wooOrders = new WooCommerceOrders();
$orden = $wooOrders->getOrderDataForPdf($orden_id);

if (!$orden) {
    die("Orden no encontrada");
}

// Combinar comentarios del post_excerpt y las notas de WooCommerce
$comentarios_combinados = [];

// Agregar comentarios del excerpt si existen
if (!empty($orden['comentarios_excerpt'])) {
    $comentarios_combinados[] = trim($orden['comentarios_excerpt']);
}

// Agregar notas de WooCommerce si existen
if (!empty($orden['comentarios_notas'])) {
    $notas_woocommerce = explode('\n', $orden['comentarios_notas']);
    foreach ($notas_woocommerce as $nota) {
        $nota = trim($nota);
        if (!empty($nota)) {
            $comentarios_combinados[] = $nota;
        }
    }
    $comentarios_combinados = array_unique($comentarios_combinados);
}

// Combinar todos los comentarios en un solo string
$orden['comentarios'] = implode("\n\n", $comentarios_combinados);

// Los valores de envío y descuento ya vienen incluidos en $orden desde getOrderDataForPdf
$envio = (float)$orden['envio'];
$descuento = (float)$orden['descuento'];

// Usar la clase WooCommerceOrders para obtener productos de la orden
$productos = $wooOrders->getOrderProductsForInvoice($orden_id);

// Preparar datos para el generador de PDF
$fecha = date('d/m/Y H:i', strtotime($orden['fecha_orden']));
$factura_formateada = str_pad($factura_num, 10, '0', STR_PAD_LEFT);

// Generar URL para el QR del pedido de WooCommerce usando variables del .env
$woocommerce_base_url = URL_WOOCOMMERCE ?? 'http://localhost/MIAU';
$woocommerce_order_path = URL_WOOCOMMERCE ?? '/mi-cuenta/ver-pedido/{id_pedido}/';
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
    'envio' => $envio,
    'descuento' => $descuento,
    'metodo' => (string)($orden['titulo_metodo_pago'] ?? ''),
    'direccion_1' => $orden['direccion_1'],
    'direccion_2' => $orden['direccion_2'],
    'ciudad' => $orden['ciudad'],
    'departamento' => $orden['departamento'],
    'pais' => $orden['pais'],
    'barrio' => $orden['barrio'],
    'dni' => $orden['dni'],
    'comentarios' => $orden['comentarios']
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
// $result_orden ya no existe porque usamos getOrderDataForPdf()
// $productos ahora es un array, no necesita mysqli_free_result
?>
