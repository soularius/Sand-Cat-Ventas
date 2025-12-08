<?php

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
// Cargar configuración desde archivo .env
require_once('class/config.php');

/* if(isset($_POST['id_ventas']) && isset($_POST['valor'])) { */
if(isset($_POST['id_ventas'])) {
	$id_ventas = $_POST['id_ventas'];	
	$query_datos = sprintf("SELECT ID, post_date, post_status, post_excerpt FROM miau_posts WHERE ID = '$id_ventas'");
	$datos = mysqli_query($miau, $query_datos) or die(mysqli_error($miau));
	$row_datos = mysqli_fetch_assoc($datos);
	$totalRows_datos = mysqli_num_rows($datos);
	$consecutivo = $row_datos['ID'];

	$query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id  = '$id_ventas'");
	$lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
	$row_lista = mysqli_fetch_assoc($lista);
	$totalRows_lista = mysqli_num_rows($lista);
	
	
	$query_productos = sprintf("SELECT I.order_item_id, order_item_name, I.order_id, L.order_id, order_item_type, product_qty, product_net_revenue, coupon_amount, shipping_amount, L.order_item_id FROM miau_woocommerce_order_items I RIGHT JOIN miau_wc_order_product_lookup L ON  I.order_item_id = L.order_item_id WHERE I.order_id = '$id_ventas' AND order_item_type='line_item'");
	$productos = mysqli_query($miau, $query_productos) or die(mysqli_error($miau));
	$row_productos = mysqli_fetch_assoc($productos);
	$totalRows_productos = mysqli_num_rows($productos);
		
	$query_datos = sprintf("SELECT ID, post_date, post_status, post_excerpt FROM miau_posts WHERE ID = '$id_ventas'");
	$datos = mysqli_query($miau, $query_datos) or die(mysqli_error($miau));
	$row_datos = mysqli_fetch_assoc($datos);
	$totalRows_datos = mysqli_num_rows($datos);
	
	$query_numfact = sprintf("SELECT factura FROM facturas WHERE id_order = '$id_ventas'");
	$numfact = mysqli_query($sandycat, $query_numfact) or die(mysqli_error($sandycat));
	$row_numfact = mysqli_fetch_assoc($numfact);
	$totalRows_numfact = mysqli_num_rows($numfact);
	$numfact = $row_numfact['factura'];
	
	// Formatear número de factura con 10 dígitos
	$numfact_formateado = str_pad($numfact, 10, '0', STR_PAD_LEFT);
	
	// Generar URL para el QR del pedido de WooCommerce usando variables del .env
	$woocommerce_base_url = $_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU';
	$woocommerce_order_path = $_ENV['WOOCOMMERCE_ORDER_PATH'] ?? '/mi-cuenta/ver-pedido/{id_pedido}/';
	
	// Reemplazar {id_pedido} con el ID real de la orden
	$woocommerce_url = $woocommerce_base_url . str_replace('{id_pedido}', $id_ventas, $woocommerce_order_path);
	
  	/* $elnuevo = "ventasrrr.php?i=$id_ventas";
    header("Location: $elnuevo"); */
}

do {
					if($row_lista['meta_key']=='_shipping_first_name') {
						$nombre1 = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_shipping_last_name') {
						$nombre2 = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='billing_id') {
						$documento = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_shipping_city') {
						$ciudad = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_shipping_state') {
						$departamento = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_shipping_address_1') {
						$dir1 = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_shipping_address_2') {
						$dir2 = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_billing_neighborhood') {
						$barrio = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_billing_phone') {
						$celular = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_billing_email') {
						$correo = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_paid_date') {
						$fecha = date("d m Y", strtotime($row_lista['meta_value']));
					}
					if($row_lista['meta_key']=='_payment_method_title') {
						$metodo = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_cart_discount') {
						$descuento = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_order_total') {
						$vtotal = $row_lista['meta_value'];
					}
					if($row_lista['meta_key']=='_order_shipping') {
						$envio = $row_lista['meta_value'];
					}
						
						 } while ($row_lista = mysqli_fetch_assoc($lista));
					$sindesc = $vtotal+$descuento;
					$fecha = $row_datos['post_date'];
$cuerpo = '
<html>
<title>Factura '.$numfact_formateado.'</title>
	<link rel="shortcut icon" href="https://sandycat.com.co/wp-content/uploads/2020/05/favicon.jpg" type="image/x-icon" />
	<style>
	@page { 
	  sheet-size: 80mm 297mm; 
	  size: auto;
	}
</style>
<body>
<table border="0"; style="table-layout: fixed; width: 180">
      <tr align: "center">
        <td colspan="4" style="text-align: center"><img src="https://sandycat.com.co/wp-content/uploads/2019/09/Logo-sandycat-200px-01.png" width="130"></td>
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
        <td colspan="4" style="text-align: center";>6016378243</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin; text-align: center">Cra. 61 No. 78-25</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center";>RECIBO DE VENTA</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center">'.$fecha.'</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center">Serie y número: '.$numfact_formateado.'</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin; text-align: center";>Pedido '.$consecutivo.'</td>
      </tr>
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Cliente: '.strtoupper($nombre1).' '.strtoupper($nombre2).'</td>
      </tr>
      <tr>
        <td colspan="4">Documento: '.$documento.'</td>
      </tr>
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Dirección: '.$dir1.' '.$dir2.' '.$barrio.'</td>
      </tr>';
				 if(!empty($row_datos['post_excerpt'])) {
					 $observacion = "<strong>".$row_datos['post_excerpt']."</strong>";
              $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">'.$observacion.'</td>
      </tr>';
								};
              $cuerpo .= '
      <tr>
        <td colspan="4" style="word-wrap: break-word; width: 180">Ciudad: '.$ciudad.' ('.$departamento.')</td>
      </tr>
      <tr>
        <td colspan="4">Telefono: '.$celular.'</td>
      </tr>
      <tr>
        <td colspan="4" style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;">Email: '.$correo.'</td>
      </tr>
      <tr>
        <td style="text-align: center">Cant.</td>
        <td>Descripción</td>
        <td style="text-align: center">V Un.</td>
        <td style="text-align: center">TOTAL</td>
      </tr>';
              do {;
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
				 } while ($row_productos = mysqli_fetch_assoc($productos)); 			$elenvio = '';
				 if($envio > 0) {;
              $cuerpo .= '
      <tr>
        <td style="text-align: center; vertical-align: top">1</td>
        <td style="word-wrap: break-word; width: 180; vertical-align: top">Gastos de envio</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
        <td style="text-align: right; vertical-align: top">'.number_format($envio).'</td>
      </tr>';
								};
              $cuerpo .= '
      <tr>
        <td colspan="4" align: "center"; style="border-bottom-style:solid; border-bottom-color:#000; border-bottom:thin;"></td>
      </tr>
			  <tr>
        <td colspan="3" style="text-align: right">Sub-total:</td>
        <td style="text-align: right">'.number_format($sindesc).'</td>
      </tr>
      <tr>
        <td colspan="3" style="text-align: right">Descuentos:</td>
        <td style="text-align: right">'.number_format($descuento).'</td>
      </tr>
      <tr> 
        <td colspan="3" style="text-align: right; vertical-align: right;word-wrap: break-word; width: 120">'.$metodo.'</td>
        <td style="text-align: right">'.number_format($vtotal).'</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center"><br>No existen devoluciones</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center"><barcode code="'.$numfact_formateado.'" type="C39" size="0.6" height="1.0" /><br><small>'.$numfact_formateado.'</small></td>
      </tr>
      <tr>
        <td colspan="4" style="text-align: center; padding-top: 5mm;">
          <div style="text-align: center;">
            <barcode code="'.$woocommerce_url.'" type="QR" class="barcode" size="0.8" error="M" />
            <br><small>Escanea para ver el pedido</small>
          </div>
        </td>
      </tr>
        </table>
</body></html>
'.mysqli_free_result($productos);

// Usar configuración centralizada de mPDF
require_once __DIR__ . '/mpdf_config.php';
$mpdf = createMpdfInstance();

// Configurar footer si existe la variable $pie
if (isset($pie)) {
    $mpdf->SetHTMLFooter($pie);
}

// Configurar CSS si existe la variable $stylesheet
if (isset($stylesheet)) {
    $mpdf->WriteHTML($stylesheet, 1); // 1 = CSS mode
}

$mpdf->WriteHTML($cuerpo);
$mpdf->Output("Factura $numfact_formateado.pdf", "I"); // "I" = Inline
?>