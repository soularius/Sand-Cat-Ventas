<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// Cargar configuración desde archivo .env
require_once('class/config.php');

if (!isset($_SESSION)) {
  session_start();
}
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
// La función isAuthorized() ahora está disponible desde tools.php



$MM_restrictGoTo = "http://localhost/ventas";


if (!((isset($_SESSION['MM_Username'])))) { 
  $MM_qsChar = "?";
  $MM_referrer = $_SERVER['PHP_SELF'];
  if (strpos($MM_restrictGoTo, "?")) $MM_qsChar = "&";
  if (isset($QUERY_STRING) && strlen($QUERY_STRING) > 0) 
  $MM_referrer .= "?" . $QUERY_STRING;
  $MM_restrictGoTo = $MM_restrictGoTo. $MM_qsChar . "accesscheck=" . urlencode($MM_referrer);
  header("Location: ". $MM_restrictGoTo); 
  exit;
}
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = isset($row_usuario['elnombre']) ? $row_usuario['elnombre'] : '';
$id_usuarios = isset($row_usuario['id_ingreso']) ? $row_usuario['id_ingreso'] : 0;
$hoy = date("Y-m-d");



if(isset($_POST['nombre1'])) {
    $_shipping_first_name = $_POST['nombre1'];
    $metodo = $_POST['_payment_method_title'];
}

if(isset($_POST['_payment_method_title'])) {
    $_payment_method_title = $_POST['_payment_method_title'];
}
if(isset($_POST['nombre2'])) {
    $_shipping_last_name = $_POST['nombre2'];
}
if(isset($_POST['billing_id'])) {
    $billing_id = $_POST['billing_id'];
}
if(isset($_POST['_billing_email'])) {
    $_billing_email = $_POST['_billing_email'];
}
if(isset($_POST['_billing_phone'])) {
    $_billing_phone = $_POST['_billing_phone'];
}
if(isset($_POST['_shipping_address_1'])) {
    $_shipping_address_1 = $_POST['_shipping_address_1'];
}
if(isset($_POST['_shipping_address_2'])) {
    $_shipping_address_2 = $_POST['_shipping_address_2'];
}
if(isset($_POST['_billing_neighborhood'])) {
    $_billing_neighborhood = $_POST['_billing_neighborhood'];
}
if(isset($_POST['_shipping_city'])) {
    $_shipping_city = $_POST['_shipping_city'];
}
if(isset($_POST['_shipping_state'])) {
    $_shipping_state = $_POST['_shipping_state'];
}
if(isset($_POST['post_expcerpt'])) {
    $post_expcerpt = $_POST['post_expcerpt'];
}
if(isset($_POST['_order_shipping'])) {
    $_order_shipping = $_POST['_order_shipping'];
}
if(isset($_POST['_cart_discount'])) {
    $_cart_discount = $_POST['_cart_discount'];
}
date_default_timezone_set('America/Bogota');
$hoy = date('Y-m-d H:i:s');

if(isset($_POST['nombre1'])) {
    
	$query = "INSERT INTO miau_posts (post_author, post_status, comment_status, ping_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_excerpt) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy', '$post_expcerpt')";
	mysqli_query($miau, $query);
   // $query = "INSERT INTO miau_posts (post_author, post_status, comment_status, ping_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_expcerpt) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy', '$post_expcerpt')";
	// mysqli_query($sandycat, $query);

   
    if($query){
        $elid = mysqli_insert_id($miau);
    }else{
        echo "No se inserto el registro correctamente.";
    }
    /* $query2 = "INSERT INTO miau_postmeta (_shipping_first_name, _shipping_last_name, billing_id, _billing_email, _billing_phone, _shipping_address_1) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy')";
	mysqli_query($sandycat, $query2); */

    $query2 = "INSERT INTO miau_postmeta (
        post_id,
        meta_key,
        meta_value
    )
    VALUES
        (
            '$elid',
            '_shipping_first_name',
            '$_shipping_first_name'
        ),
        (
            '$elid',
            '_shipping_last_name',
            '$_shipping_last_name'
        ),
        (
            '$elid',
            '_billing_first_name',
            '$_shipping_first_name'
        ),
        (
            '$elid',
            '_billing_last_name',
            '$_shipping_last_name'
        ),
        (
            '$elid',
            '_order_shipping',
            '$_order_shipping'
        ),
        (
            '$elid',
            '_paid_date',
            '$hoy'
        ),
        (
            '$elid',
            '_recorded_sales',
            'yes'
        ),
        (
            '$elid',
            'billing_id',
            '$billing_id'
        ),
        (
            '$elid',
            '_billing_id',
            '$billing_id'
        ),
        (
            '$elid',
            '_billing_email',
            '$_billing_email'
        ),
        (
            '$elid',
            'Card number',
            '$_billing_email'
        ),
        (
            '$elid',
            '_billing_phone',
            '$_billing_phone'
        ),
        (
            '$elid',
            '_shipping_address_1',
            '$_shipping_address_1'
        ),
        (
            '$elid',
            '_billing_address_1',
            '$_shipping_address_1'
        ),
        (
            '$elid',
            '_cart_discount',
            '0'
        ),
        (
            '$elid',
            '_billing_neighborhood',
            '$_billing_neighborhood'
        ),
        (
            '$elid',
            'billing_neighborhood',
            '$_billing_neighborhood'
        ),
        (
            '$elid',
            '_shipping_city',
            '$_shipping_city'
        ),
        (
            '$elid',
            '_billing_city',
            '$_shipping_city'
        ),
        (
            '$elid',
            '_shipping_state',
            '$_shipping_state'
        ),
        (
            '$elid',
            '_billing_state',
            '$_shipping_state'
        ),
        (
            '$elid',
            '_payment_method_title',
            '$_payment_method_title'
        ),
        (
            '$elid',
            '_billing_country',
            'Co'
        ),
        (
            '$elid',
            '_shipping_country',
            'Co'
        ),
        (
            '$elid',
            '_order_stock_reduced',
            'yes'
        ),
        (
            '$elid',
            '_billing_address_2',
            '$_shipping_address_2'
        ),
        (
            '$elid',
            '_order_total',
            '0'
        ),
        (
            '$elid',
            '_shipping_address_2',
            '$_shipping_address_2'
        )";
	mysqli_query($miau, $query2);

    $query_cliente = sprintf("SELECT customer_id, email FROM miau_wc_customer_lookup WHERE email = '$_billing_email'");
	$cliente = mysqli_query($miau, $query_cliente) or die(mysqli_error($miau));
	$row_cliente = mysqli_fetch_assoc($cliente);
	$totalRows_cliente = mysqli_num_rows($cliente);

    if(!isset($row_cliente['email'])) {
    $query3 = "INSERT INTO miau_wc_customer_lookup (first_name, last_name, email, date_last_active, country, city, state) VALUES ('$_shipping_first_name', '$_shipping_last_name', '$_billing_email', '$hoy', 'Co', '$_shipping_city', '$_shipping_state')";
	mysqli_query($miau, $query3);
    }

}

if(isset($_POST['proceso'])) {
    $elid = $_POST['order_id'];
    $product_id = $_POST['product_id'];
    $product_qty = $_POST['product_qty'];
    $order_item_name = $_POST['order_idbn'];
    
    $query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$elid'");
	$lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
	$row_lista = mysqli_fetch_assoc($lista);
	$totalRows_lista = mysqli_num_rows($lista);
    
    $query_stock = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_stock'");
	$stock = mysqli_query($miau, $query_stock) or die(mysqli_error($miau));
	$row_stock = mysqli_fetch_assoc($stock);
	$totalRows_stock = mysqli_num_rows($stock);

    $_stock1 = $row_stock['meta_value'];
    $_stock2 = $row_stock['meta_value'] - $product_qty;
    $query9 = "UPDATE miau_postmeta SET meta_value = '$_stock2' WHERE post_id = '$product_id' AND meta_key = '_stock'";
	mysqli_query($miau, $query9);
    if($_stock2 < 1) {       
        $query10 = "UPDATE miau_postmeta SET meta_value = 'outofstock' WHERE post_id = '$product_id' AND meta_key = '_stock_status'";
	    mysqli_query($miau, $query10); 
    }
    					 
    include("postventa.php");

    
    $elenvio == 0;
    if($_order_shipping > 0) {
        $elenvio = $_order_shipping;
    }

    $query4 = "INSERT INTO miau_woocommerce_order_items (order_item_name, order_item_type, order_id) VALUES ('$order_item_name', 'line_item', '$elid')";
	mysqli_query($miau, $query4);

    if($query4){
        $order_item_id = mysqli_insert_id($miau);
    }else{
        echo "No se inserto el registro correctamente.";
    }

    $query_lookprod = sprintf("SELECT ID, post_title, post_status, post_parent, post_type FROM miau_posts WHERE ID = '$product_id' AND post_status = 'publish'");
	$lookprod = mysqli_query($miau, $query_lookprod) or die(mysqli_error($miau));
	$row_lookprod = mysqli_fetch_assoc($lookprod);
	$totalRows_lookprod = mysqli_num_rows($lookprod);

    $product_id = $row_lookprod['ID'];
 
    $query_precio = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_price'");
    $precio = mysqli_query($miau, $query_precio) or die(mysqli_error($miau));
    $row_precio = mysqli_fetch_assoc($precio);
    $totalRows_precio = mysqli_num_rows($precio);

    $product_net_revenue = $row_precio['meta_value'];

    $query_vrnormal = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_regular_price'");
    $vrnormal = mysqli_query($miau, $query_vrnormal) or die(mysqli_error($miau));
    $row_vrnormal = mysqli_fetch_assoc($vrnormal);
    $totalRows_vrnormal = mysqli_num_rows($vrnormal);

    $elvrnormal = $row_vrnormal['meta_value'];

    $coupon_amount = ($elvrnormal - $product_net_revenue) * $product_qty;

    $query_cliente = sprintf("SELECT customer_id, email FROM miau_wc_customer_lookup WHERE email = '$_billing_email'");
	$cliente = mysqli_query($miau, $query_cliente) or die(mysqli_error($miau));
	$row_cliente = mysqli_fetch_assoc($cliente);
	$totalRows_cliente = mysqli_num_rows($cliente);

    $customer_id = $row_cliente['customer_id'];
    $prodvalor = $product_qty * $product_net_revenue;

    if($row_lookprod['post_type'] == 'product_variation') {
        $product_id = $row_lookprod['post_parent'];
        $variation_id = $row_lookprod['ID']; 
     } elseif ($row_lookprod['post_type'] == 'product') {
     $product_id = $row_lookprod['ID']; 
     $variation_id = '0'; 
     }
 
    $query2 = "INSERT INTO miau_wc_order_product_lookup (order_item_id, order_id, product_id, variation_id, customer_id, date_created, product_qty, product_net_revenue, coupon_amount, tax_amount, shipping_tax_amount) VALUES ('$order_item_id', '$elid', '$product_id', '$variation_id', '$customer_id', '$hoy', '$product_qty', '$prodvalor', '$coupon_amount', '0', '0')";
	mysqli_query($miau, $query2);
    
    $query_vrtotal = sprintf("SELECT SUM(product_net_revenue) AS total FROM miau_wc_order_product_lookup WHERE order_id = '$elid'");
	$vrtotal = mysqli_query($miau, $query_vrtotal) or die(mysqli_error($miau));
	$row_vrtotal = mysqli_fetch_assoc($vrtotal);
	$totalRows_vrtotal = mysqli_num_rows($vrtotal);
    $vractual = $row_vrtotal['total'];

    $query_vrdescuento = sprintf("SELECT SUM(coupon_amount) AS total FROM miau_wc_order_product_lookup WHERE order_id = '$elid'");
	$vrdescuento = mysqli_query($miau, $query_vrdescuento) or die(mysqli_error($miau));
	$row_vrdescuento = mysqli_fetch_assoc($vrdescuento);
	$totalRows_vrdescuento = mysqli_num_rows($vrdescuento);
    $vrdesc = $row_vrdescuento['total'];

    $vatotal = $vractual + $elenvio;
    
	/*$query6 = "UPDATE miau_posts SET post_status = 'wc-cancelled' WHERE ID = '$venta'";
	mysqli_query($sandycat, $query6); */

	$query6 = "UPDATE miau_postmeta SET meta_value = '$vatotal' WHERE post_id = '$elid' AND meta_key = '_order_total'";
	mysqli_query($miau, $query6);


	$query8 = "UPDATE miau_postmeta SET meta_value = '$vrdesc' WHERE post_id = '$elid' AND meta_key = '_cart_discount'";
	mysqli_query($miau, $query8);

    $query_listavr = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$elid' AND meta_key = '_order_total'");
	$listavr = mysqli_query($miau, $query_listavr) or die(mysqli_error($miau));
	$row_listavr = mysqli_fetch_assoc($listavr);
	$totalRows_listavr = mysqli_num_rows($listavr);
    $vtotal = $row_listavr['meta_value'];

    $query_cantotal = sprintf("SELECT SUM(product_qty) AS num_items_sold FROM miau_wc_order_product_lookup WHERE order_id = '$elid'");
	$cantotal = mysqli_query($miau, $query_cantotal) or die(mysqli_error($miau));
	$row_cantotal = mysqli_fetch_assoc($cantotal);
	$totalRows_cantotal = mysqli_num_rows($cantotal);
    $num_items_sold = $row_cantotal['num_items_sold'];

    $net_sales = $vtotal - $elenvio;

    $query_consulorderstats = sprintf("SELECT order_id FROM miau_wc_order_stats WHERE order_id = '$elid'");
	$consulorderstats = mysqli_query($miau, $query_consulorderstats) or die(mysqli_error($miau));
	$row_consulorderstats = mysqli_fetch_assoc($consulorderstats);
	$totalRows_consulorderstats = mysqli_num_rows($consulorderstats);
    $order_id = $row_consulorderstats['order_id'];

    if(isset($row_consulorderstats['order_id'])) {
        $query7 = "UPDATE miau_wc_order_stats SET num_items_sold = '$num_items_sold', total_sales = '$vtotal', net_total = '$net_sales' WHERE order_id = '$elid'";
        mysqli_query($miau, $query7);
    } else {    
    $query2 = "INSERT INTO miau_wc_order_stats (order_id, date_created, date_created_gmt, num_items_sold, total_sales, tax_total, shipping_total, net_total, returning_customer, status, customer_id) VALUES ('$elid', '$hoy', '$hoy', '$num_items_sold', '$vatotal', '0', '$_order_shipping', '$vtotal', '1', 'wc-processing', '$customer_id')";
	mysqli_query($miau, $query2);
    }


   //cuadrar esto
	//$query = "INSERT INTO miau_posts (post_author, post_status, comment_status, ping_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_excerpt) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy', '$post_expcerpt')";
	//mysqli_query($sandycat, $query);    

}

// Inicializar variables para evitar warnings
$elid = $elid ?? '';
$_shipping_first_name = $_shipping_first_name ?? '';
$_shipping_last_name = $_shipping_last_name ?? '';
$billing_id = $billing_id ?? '';
$_shipping_address_1 = $_shipping_address_1 ?? '';
$_shipping_address_2 = $_shipping_address_2 ?? '';
$_billing_neighborhood = $_billing_neighborhood ?? '';
$_shipping_city = $_shipping_city ?? '';
$_shipping_state = $_shipping_state ?? '';
$_billing_phone = $_billing_phone ?? '';
$_billing_email = $_billing_email ?? '';
$metodo = $metodo ?? '';
$_order_shipping = $_order_shipping ?? 0;
$_cart_discount = $_cart_discount ?? 0;
$vatotal = $vatotal ?? 0;

// Solo ejecutar consultas si tenemos un ID válido
if (!empty($elid)) {
    $query_productos = sprintf("SELECT I.order_item_id, order_item_name, I.order_id, L.order_id, order_item_type, product_qty, product_net_revenue, coupon_amount, shipping_amount, L.order_item_id FROM miau_woocommerce_order_items I RIGHT JOIN miau_wc_order_product_lookup L ON I.order_item_id = L.order_item_id WHERE I.order_id = '$elid' AND order_item_type='line_item'");
    $productos = mysqli_query($miau, $query_productos) or die(mysqli_error($miau));
    $row_productos = mysqli_fetch_assoc($productos);
    $totalRows_productos = mysqli_num_rows($productos);

    $query_obser = sprintf("SELECT ID, post_excerpt, post_date FROM miau_posts WHERE ID = '$elid'");
    $obser = mysqli_query($miau, $query_obser) or die(mysqli_error($miau));
    $row_obser = mysqli_fetch_assoc($obser);
    $totalRows_obser = mysqli_num_rows($obser);
    $post_excerpt = $row_obser['post_excerpt'] ?? '';
} else {
    // Inicializar variables por defecto si no hay elid
    $productos = null;
    $row_productos = [];
    $totalRows_productos = 0;
    $row_obser = ['post_date' => '', 'post_excerpt' => ''];
    $post_excerpt = '';
}

?>
<?php include("parts/header.php"); ?>
<body>
    <div class="container">
            <?php include("parts/menf.php"); ?>
        <section class=""><br />
            <br />
            <br />
            <br />
			<div class="row justify-content-center">
				<div class="col-md-6 text-center mb-5">
					<h2 class="heading-section">Detalle venta Woocomerce</h2>
			    </div>
	        </div>
			<div class="row justify-content-center" style="margin-top: -30px">
			  <div class="col-md-7 col-lg-5">
					<div class="login-wrap p-4 p-md-5 justify-content-center">
		      	<!-- <div class="icon d-flex align-items-center justify-content-center">	
	      		  <span class="fa fa-user-o"></span>
		      	</div> -->
						<div class="container p-3 my-3 bg-primary text-white">
							<?php
                                /* if(isset($_POST['proceso'])) {					 
							        include("postventa.php");
                                } */
							    
							?>
							Pedido: <?php echo $elid; ?><br />
							Nombre: <?php echo strtoupper($_shipping_first_name)." ".strtoupper($_shipping_last_name); ?><br />
							Documento: <?php echo $billing_id; ?><br />
							Dirección: <?php echo $_shipping_address_1." ".$_shipping_address_2." ".$_billing_neighborhood; ?><br />
							Ciudad: <?php echo $_shipping_city." (".$_shipping_state.")"; ?><br />
							<?php echo "Teléfono: ".$_billing_phone; ?><br />
							<?php echo "Email: ".$_billing_email; ?><br />
							<?php   ?>
                            <?php if(isset($_POST['proceso'])) {
							    $sindesc = $vatotal + $_cart_discount;
                                echo "Subtotal: ".number_format($sindesc)."<br />Descuento: ".number_format($_cart_discount)."<br />Total: ".number_format($sindesc - $_cart_discount)."<br />";
                                 }  ?>
							<?php echo $metodo; ?><br />
							<?php 
							if($_order_shipping > 0) {
							echo "Valor envio: ".number_format($_order_shipping)."<br />";
							}  ?>
							<?php echo "Fecha: ".$row_obser['post_date']; ?><br />
						</div>
							<?php if(!empty($post_excerpt)) { ?>
						<div class="container p-3 my-3 bg-success text-white">
							Observaciones:<br />
							<?php echo $post_excerpt; ?>
						</div>
						  <?php } ?> 
                            <?php
                                if(isset($row_productos['order_item_name'])) {
                                    ?>	
                          <div class="row">
							<div class="col text-center">
						        <form action="adminf.php" class="login-form" method="post" id="fin" >
						    	    <input type="hidden" id="fin_pedido" name="fin_pedido" value="<?php echo $elid; ?>" />
                                <button type="submit" class="btn btn-success btn-block">Finalizar pedido</button></br>
                                </form>
							</div>
                        </div>
                            <?php
                                }                            
					        ?>			
						<div class="row">
							<div class="col text-center">
                            <form action="bproducto.php" method="post" id="product" >
                             <input type="hidden" class="form-control" id="_order_id" name="_order_id" value="<?php echo $elid; ?>">
                               <button type="submit" class="btn btn-primary btn-block">Ingresar producto</button></br>
                            </form>
							</div>
                        </div>
						<div class="row">
							<div class="col text-center">
						        <form action="adminventas.php" class="login-form" method="post" id="cancelar" >
                                    <input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $elid; ?>" />
								    <input type="hidden" id="cancela" name="cancela" value="si" />
                                    <button type="submit" class="btn btn-danger btn-block">Cancelar pedido</button></br>
                                </form>
							</div>
                        </div>	

                            
						  <?php 
                             if(isset($row_productos['order_item_name'])) {
						        do {
					        ?>	
						<div class="container p-3 my-3 border">
						  Producto: <?php echo $row_productos['order_item_name']."<br /> Cantidad: ".$row_productos['product_qty']."<br /> Subtotal: ".number_format($row_productos['coupon_amount']+$row_productos['product_net_revenue'])."<br />Descuento: ".number_format($row_productos['coupon_amount'])."<br /> Total: ".number_format($row_productos['product_net_revenue']); ?><br />
						</div>							
    	 				 <?php } while ($row_productos = mysqli_fetch_assoc($productos)); 
                          } 
					  ?>
                    </div>
			    </div>
	        </div>
	                    <?php include("parts/foot.php"); ?>
    </section>
  </div>   
</body>
</html>
<?php
mysqli_free_result($usuario);
?>