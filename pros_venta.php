<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el manejador de login común
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Requerir autenticación - redirige a index.php si no está logueado
requireLogin("index.php");

// Obtener datos del usuario actual usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    // Si no se pueden obtener los datos del usuario, redirigir al login
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;
$hoy = date("Y-m-d");



// Capturar datos del formulario usando la nueva función optimizada
$formFields = [
    'nombre1',
    'nombre2',
    'billing_id',
    '_billing_email',
    '_billing_phone',
    '_shipping_address_1',
    '_shipping_address_2',
    '_billing_neighborhood',
    '_shipping_city',
    '_shipping_state',
    'post_expcerpt',
    '_order_shipping',
    '_cart_discount',
    '_payment_method_title'
];

$formData = Utils::capturePostData($formFields);

// Asignar variables para compatibilidad con código existente
$_shipping_first_name = $formData['nombre1'];
$_shipping_last_name = $formData['nombre2'];
$billing_id = $formData['billing_id'];
$_billing_email = $formData['_billing_email'];
$_billing_phone = $formData['_billing_phone'];
$_shipping_address_1 = $formData['_shipping_address_1'];
$_shipping_address_2 = $formData['_shipping_address_2'];
$_billing_neighborhood = $formData['_billing_neighborhood'];
$_shipping_city = $formData['_shipping_city'];
$_shipping_state = $formData['_shipping_state'];

// Procesar códigos de departamento y ciudad para diferentes tablas
$original_state = $_shipping_state;
$original_city = $_shipping_city;

// Ahora $_shipping_state siempre viene con la clave del departamento (ej: "SAN", "ANT")
// porque se corrigió el select del formulario

// Para miau_usermeta: state = solo clave, city = valor
$state_for_usermeta = $_shipping_state;  // Ya viene como clave (ej: "SAN")
$city_for_usermeta = $_shipping_city;    // Valor completo (ej: "El carmen de chucuri")

// Para miau_wc_order_addresses: state = CO-clave, city = valor
$state_for_addresses = 'CO-' . $_shipping_state;  // Agregar prefijo CO- (ej: "CO-SAN")
$city_for_addresses = $_shipping_city;             // Valor completo

// Para miau_postmeta: usar solo la clave del departamento
$state_for_postmeta = $_shipping_state;  // Ya viene como clave (ej: "SAN")

// Log para debugging
Utils::logError("Procesamiento de ubicación - Estado original: $original_state, Para usermeta: $state_for_usermeta, Para addresses: $state_for_addresses, Ciudad: $_shipping_city", 'INFO', 'pros_venta.php');

$post_expcerpt = $formData['post_expcerpt'];
$_order_shipping = $formData['_order_shipping'];
$_cart_discount = $formData['_cart_discount'];
$_payment_method_title = $formData['_payment_method_title'];
$metodo = $formData['_payment_method_title'];
date_default_timezone_set('America/Bogota');
$hoy = date('Y-m-d H:i:s');

if (Utils::captureValue('nombre1', 'POST')) {

    $query = "INSERT INTO miau_posts (post_author, post_status, comment_status, ping_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_excerpt) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy', '$post_expcerpt')";
    mysqli_query($miau, $query);
    // $query = "INSERT INTO miau_posts (post_author, post_status, comment_status, ping_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_expcerpt) VALUES ('1', 'wc_pendient', 'closed', 'closed', 'shop_order', '$hoy', '$hoy', '$hoy', '$hoy', '$post_expcerpt')";
    // mysqli_query($sandycat, $query);


    if ($query) {
        $elid = mysqli_insert_id($miau);
    } else {
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
            '_billing_dni',
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
            '$state_for_postmeta'
        ),
        (
            '$elid',
            '_billing_state',
            '$state_for_postmeta'
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

    // Insertar direcciones en miau_wc_order_addresses
    $query_addresses = "INSERT INTO miau_wc_order_addresses (
        order_id,
        address_type,
        first_name,
        last_name,
        company,
        address_1,
        address_2,
        city,
        state,
        postcode,
        country,
        email,
        phone
    )
    VALUES
        (
            '$elid',
            'billing',
            '$_shipping_first_name',
            '$_shipping_last_name',
            NULL,
            '$_shipping_address_1',
            '$_shipping_address_2',
            '$city_for_addresses',
            '$state_for_addresses',
            NULL,
            'CO',
            '$_billing_email',
            '$_billing_phone'
        ),
        (
            '$elid',
            'shipping',
            '$_shipping_first_name',
            '$_shipping_last_name',
            NULL,
            '$_shipping_address_1',
            '$_shipping_address_2',
            '$city_for_addresses',
            '$state_for_addresses',
            NULL,
            'CO',
            NULL,
            NULL
        )";
    mysqli_query($miau, $query_addresses);

    // Verificar si el cliente ya existe en WooCommerce
    $query_cliente = sprintf("SELECT customer_id, email FROM miau_wc_customer_lookup WHERE email = '$_billing_email'");
    $cliente = mysqli_query($miau, $query_cliente) or die(mysqli_error($miau));
    $row_cliente = mysqli_fetch_assoc($cliente);
    $totalRows_cliente = mysqli_num_rows($cliente);

    // Verificar si el usuario ya existe en WordPress
    $query_usuario_wp = sprintf("SELECT ID, user_email FROM miau_users WHERE user_email = '$_billing_email'");
    $usuario_wp = mysqli_query($miau, $query_usuario_wp) or die(mysqli_error($miau));
    $row_usuario_wp = mysqli_fetch_assoc($usuario_wp);
    $totalRows_usuario_wp = mysqli_num_rows($usuario_wp);

    $user_id = null;

    // Si no existe el usuario en WordPress, crearlo
    if (!isset($row_usuario_wp['user_email'])) {
        // Generar username único basado en email
        $username = strtolower($_shipping_first_name . '_' . $_shipping_last_name);
        $username = preg_replace('/[^a-z0-9_]/', '', $username);

        // Verificar que el username sea único
        $counter = 1;
        $original_username = $username;
        while (true) {
            $query_check_username = sprintf("SELECT ID FROM miau_users WHERE user_login = '$username'");
            $check_username = mysqli_query($miau, $query_check_username);
            if (mysqli_num_rows($check_username) == 0) {
                break;
            }
            $username = $original_username . '_' . $counter;
            $counter++;
        }

        // Generar contraseña temporal
        $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12);
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Crear usuario en WordPress
        $display_name = $_shipping_first_name . ' ' . $_shipping_last_name;
        $user_nicename = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $display_name));

        $query_create_user = "INSERT INTO miau_users (
            user_login, 
            user_pass, 
            user_nicename, 
            user_email, 
            user_registered, 
            user_status, 
            display_name
        ) VALUES (
            '$username', 
            '$hashed_password', 
            '$user_nicename', 
            '$_billing_email', 
            '$hoy', 
            0, 
            '$display_name'
        )";

        if (mysqli_query($miau, $query_create_user)) {
            $user_id = mysqli_insert_id($miau);

            // Asignar rol guest_customer al usuario
            $query_user_role = "INSERT INTO miau_usermeta (user_id, meta_key, meta_value) VALUES 
                ('$user_id', 'miau_capabilities', 'a:1:{s:14:\"guest_customer\";b:1;}'),
                ('$user_id', 'miau_user_level', '0'),
                ('$user_id', 'first_name', '$_shipping_first_name'),
                ('$user_id', 'last_name', '$_shipping_last_name'),
                ('$user_id', 'billing_first_name', '$_shipping_first_name'),
                ('$user_id', 'billing_last_name', '$_shipping_last_name'),
                ('$user_id', 'billing_email', '$_billing_email'),
                ('$user_id', 'billing_phone', '$_billing_phone'),
                ('$user_id', 'billing_address_1', '$_shipping_address_1'),
                ('$user_id', 'billing_address_2', '$_shipping_address_2'),
                ('$user_id', 'billing_city', '$city_for_usermeta'),
                ('$user_id', 'billing_state', '$state_for_usermeta'),
                ('$user_id', 'billing_country', 'CO'),
                ('$user_id', 'billing_barrio', '$_billing_neighborhood'),
                ('$user_id', 'billing_dni', '$billing_id'),
                ('$user_id', 'shipping_first_name', '$_shipping_first_name'),
                ('$user_id', 'shipping_last_name', '$_shipping_last_name'),
                ('$user_id', 'shipping_address_1', '$_shipping_address_1'),
                ('$user_id', 'shipping_address_2', '$_shipping_address_2'),
                ('$user_id', 'shipping_city', '$city_for_usermeta'),
                ('$user_id', 'shipping_state', '$state_for_usermeta'),
                ('$user_id', 'shipping_country', 'CO'),
                ('$user_id', 'shipping_barrio', '$_billing_neighborhood'),
                ('$user_id', 'shipping_dni', '$billing_id')";

            mysqli_query($miau, $query_user_role);

            // Log de creación de usuario
            error_log("Usuario WordPress creado: ID=$user_id, Username=$username, Email=$_billing_email");
        } else {
            error_log("Error creando usuario WordPress: " . mysqli_error($miau));
        }
    } else {
        $user_id = $row_usuario_wp['ID'];
        error_log("Usuario WordPress ya existe: ID=$user_id, Email=$_billing_email");
    }

    // Si no existe el cliente en WooCommerce, crearlo
    if (!isset($row_cliente['email'])) {
        $query3 = "INSERT INTO miau_wc_customer_lookup (
            user_id,
            first_name, 
            last_name, 
            email, 
            date_last_active, 
            country, 
            city, 
            state
        ) VALUES (
            '$user_id',
            '$_shipping_first_name', 
            '$_shipping_last_name', 
            '$_billing_email', 
            '$hoy', 
            'CO', 
            '$city_for_usermeta', 
            '$state_for_usermeta'
        )";

        if (mysqli_query($miau, $query3)) {
            $customer_id = mysqli_insert_id($miau);
            error_log("Cliente WooCommerce creado: ID=$customer_id, User_ID=$user_id, Email=$_billing_email");
        } else {
            error_log("Error creando cliente WooCommerce: " . mysqli_error($miau));
        }
    }
}

if (Utils::captureValue('proceso', 'POST')) {
    // Capturar datos del proceso usando la función optimizada
    $processFields = ['order_id', 'product_id', 'product_qty', 'order_idbn'];
    $processData = Utils::capturePostData($processFields);

    $elid = $processData['order_id'];
    $product_id = $processData['product_id'];
    $product_qty = $processData['product_qty'];
    $order_item_name = $processData['order_idbn'];

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
    if ($_stock2 < 1) {
        $query10 = "UPDATE miau_postmeta SET meta_value = 'outofstock' WHERE post_id = '$product_id' AND meta_key = '_stock_status'";
        mysqli_query($miau, $query10);
    }

    include("postventa.php");


    $elenvio == 0;
    if ($_order_shipping > 0) {
        $elenvio = $_order_shipping;
    }

    $query4 = "INSERT INTO miau_woocommerce_order_items (order_item_name, order_item_type, order_id) VALUES ('$order_item_name', 'line_item', '$elid')";
    mysqli_query($miau, $query4);

    if ($query4) {
        $order_item_id = mysqli_insert_id($miau);
    } else {
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

    if ($row_lookprod['post_type'] == 'product_variation') {
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

    if (isset($row_consulorderstats['order_id'])) {
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

// 3. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<style>
</style>

<?php include("parts/menf.php"); ?>
<?php
// Configurar el paso actual para el wizard
$current_step = 2; // Paso 2: Productos
include('parts/step_wizard.php');
?>
        <!-- Order Header -->
        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Detalle de Venta WooCommerce
                </h2>
                <?php if (isset($elid) && $elid): ?>
                    <p class="text-muted">Pedido #<?php echo $elid; ?> - Revise y confirme los detalles</p>
                <?php else: ?>
                    <p class="text-muted">Revise los detalles de la venta antes de continuar</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="order-card">
            <div class="customer-info">
                <!-- Two Column Layout -->
                <div class="row">
                    <!-- Left Column: Customer & Shipping Info -->
                    <div class="col-lg-6 col-md-12">
                        <!-- Customer Information -->
                        <?php if ($_shipping_first_name || $_shipping_last_name): ?>
                            <div class="info-section">
                                <h5><i class="fas fa-user"></i>Información del Cliente</h5>
                                <div class="info-row">
                                    <span class="info-label">Nombre Completo:</span>
                                    <span class="info-value"><?php echo strtoupper($_shipping_first_name) . " " . strtoupper($_shipping_last_name); ?></span>
                                </div>
                                <?php if ($billing_id): ?>
                                    <div class="info-row">
                                        <span class="info-label">Documento:</span>
                                        <span class="info-value"><?php echo $billing_id; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_billing_email): ?>
                                    <div class="info-row">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo $_billing_email; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_billing_phone): ?>
                                    <div class="info-row">
                                        <span class="info-label">Teléfono:</span>
                                        <span class="info-value"><?php echo $_billing_phone; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Payment & Order Info -->
                    <div class="col-lg-6 col-md-12">
                        <!-- Shipping Information -->
                        <?php if ($_shipping_address_1 || $_shipping_city): ?>
                            <div class="info-section">
                                <h5><i class="fas fa-map-marker-alt"></i>Dirección de Envío</h5>
                                <?php if ($_shipping_address_1): ?>
                                    <div class="info-row">
                                        <span class="info-label">Dirección:</span>
                                        <span class="info-value"><?php echo $_shipping_address_1 . ($_shipping_address_2 ? " " . $_shipping_address_2 : "") . ($_billing_neighborhood ? " - " . $_billing_neighborhood : ""); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_shipping_city): ?>
                                    <div class="info-row">
                                        <span class="info-label">Ciudad:</span>
                                        <span class="info-value"><?php echo $_shipping_city; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_shipping_state): ?>
                                    <div class="info-row">
                                        <span class="info-label">Departamento:</span>
                                        <span class="info-value"><?php 
                                            // Obtener el nombre completo del departamento
                                            $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
                                            if (file_exists($states_file)) {
                                                $colombia_states = include($states_file);
                                                echo isset($colombia_states[$_shipping_state]) ? $colombia_states[$_shipping_state] : $_shipping_state;
                                            } else {
                                                echo $_shipping_state;
                                            }
                                        ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <span class="info-label">País:</span>
                                    <span class="info-value">Colombia</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Payment & Order Info -->
                    <div class="col-12">
                        <!-- Payment Information -->
                        <div class="info-section">
                            <h5><i class="fas fa-credit-card"></i>Información de Pago</h5>
                            <?php if ($metodo): ?>
                                <div class="info-row">
                                    <span class="info-label">Método de Pago:</span>
                                    <span class="info-value"><?php echo $metodo; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($row_obser['post_date']) && $row_obser['post_date']): ?>
                                <div class="info-row">
                                    <span class="info-label">Fecha del Pedido:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($row_obser['post_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Estado:</span>
                                <span class="info-value">
                                    <span class="status-badge status-pending">Pendiente</span>
                                </span>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <?php if (Utils::captureValue('proceso', 'POST') && isset($vatotal)): ?>
                            <div class="order-summary">
                                <h5><i class="fas fa-calculator me-2"></i>Resumen del Pedido</h5>
                                <?php
                                $sindesc = $vatotal + $_cart_discount;
                                ?>
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($sindesc); ?></span>
                                </div>
                                <?php if ($_cart_discount > 0): ?>
                                    <div class="summary-row">
                                        <span>Descuento:</span>
                                        <span>-$<?php echo number_format($_cart_discount); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($_order_shipping > 0): ?>
                                    <div class="summary-row">
                                        <span>Envío:</span>
                                        <span>$<?php echo number_format($_order_shipping); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span>$<?php echo number_format($sindesc - $_cart_discount + ($_order_shipping ?? 0)); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($post_excerpt)): ?>
            <div class="order-card">
                <div class="info-section">
                    <h5><i class="fas fa-sticky-note"></i>Observaciones</h5>
                    <div class="alert alert-info mb-0">
                        <?php echo nl2br(htmlspecialchars($post_excerpt)); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <?php if (isset($elid) && $elid): ?>
            <div class="order-card">
                <div class="customer-info">
                    <h5 class="text-center mb-4"><i class="fas fa-cogs me-2"></i>Acciones del Pedido</h5>

                    <div class="row justify-content-between g-3">
                        <!-- Finalizar Pedido -->
                        <?php if (isset($row_productos['order_item_name'])): ?>
                            <div class="col-md-4">
                                <form action="adminf.php" method="post" class="h-100">
                                    <input type="hidden" name="fin_pedido" value="<?php echo $elid; ?>">
                                    <button type="submit" class="btn btn-success btn-custom action-button w-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-check-circle fa-2x mx-2"></i>
                                        <span>Finalizar Pedido</span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Cancelar Pedido -->
                        <div class="col-md-4">
                            <form action="adminventas.php" method="post" class="h-100"
                                onsubmit="return confirm('¿Está seguro de que desea cancelar este pedido?')">
                                <input type="hidden" name="id_ventas" value="<?php echo $elid; ?>">
                                <input type="hidden" name="cancela" value="si">
                                <button type="submit" class="btn btn-danger btn-custom action-button w-100 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-times-circle fa-2x mx-2"></i>
                                    <span>Cancelar Pedido</span>
                                </button>
                            </form>
                        </div>

                        <!-- Agregar Producto -->
                        <div class="col-md-4">
                            <form action="bproducto.php" method="post" class="h-100">
                                <input type="hidden" name="_order_id" value="<?php echo $elid; ?>">
                                <button type="submit" class="btn btn-primary btn-custom action-button w-100 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-plus-circle fa-2x mx-2"></i>
                                    <span>Agregar Producto</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Products List -->
        <?php if (isset($row_productos['order_item_name'])): ?>
            <div class="order-card">
                <div class="order-header">
                    <h2><i class="fas fa-box me-2"></i>Productos del Pedido</h2>
                </div>
                <div class="customer-info">
                    <?php do { ?>
                        <div class="info-section">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-2 text-primary">
                                        <i class="fas fa-cube me-2"></i>
                                        <?php echo htmlspecialchars($row_productos['order_item_name']); ?>
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="row text-end">
                                        <div class="col-4">
                                            <small class="text-muted">Cantidad</small>
                                            <div class="fw-bold"><?php echo $row_productos['product_qty']; ?></div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Subtotal</small>
                                            <div class="fw-bold">$<?php echo number_format($row_productos['coupon_amount'] + $row_productos['product_net_revenue']); ?></div>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Total</small>
                                            <div class="fw-bold text-success">$<?php echo number_format($row_productos['product_net_revenue']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($row_productos['coupon_amount'] > 0): ?>
                                <div class="mt-2">
                                    <span class="badge bg-warning">
                                        <i class="fas fa-tag me-1"></i>
                                        Descuento: $<?php echo number_format($row_productos['coupon_amount']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php } while ($row_productos = mysqli_fetch_assoc($productos)); ?>
                </div>
            </div>
        <?php endif; ?>

<?php include("parts/foot.php"); ?>
</body>

</html>