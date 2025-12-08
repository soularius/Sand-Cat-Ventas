<?php
require_once('config.php');

if (!isset($_SESSION)) {
    session_start();
}

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
$query_factura = "SELECT * FROM facturas WHERE id_order = '$orden_id' AND factura = '$factura_num' AND estado = 'a'";
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
        COALESCE(o.billing_email, '') as email_cliente,
        COALESCE(ba.first_name, '') as nombre_cliente,
        COALESCE(ba.last_name, '') as apellido_cliente,
        COALESCE(ba.phone, '') as telefono_cliente,
        COALESCE(ba.email, o.billing_email) as email_completo
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

// Reemplazar {id_pedido} con el ID real de la orden
$woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $orden_id, $woocommerce_order_path);

// Generar HTML para PDF usando el mismo formato que fact.php
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
    <td colspan="4" style="text-align: center";>NIT 79690971</td>
  </tr>
  <tr>
    <td colspan="4" style="text-align: center";>www.sandycat.com.co</td>
  </tr>
  <tr>
    <td colspan="4" style="text-align: center";><strong>NIT</strong> 6016378243</td>
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
if ($productos && mysqli_num_rows($productos) > 0) {
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

// Configurar salida según parámetros
if ($download) {
    $mpdf->Output("Factura $factura_formateada.pdf", "D"); // Download
} elseif ($embed) {
    $mpdf->Output("Factura $factura_formateada.pdf", "I"); // Inline/Embed
} else {
    $mpdf->Output("Factura $factura_formateada.pdf", "I"); // Inline por defecto
}

mysqli_free_result($result_factura);
mysqli_free_result($result_orden);
if ($productos) mysqli_free_result($productos);
?>
