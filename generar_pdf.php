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

// Obtener datos de la orden desde postmeta (más confiable)
$query_orden = "
    SELECT 
        p.ID as order_id,
        p.post_date as fecha_orden,
        p.post_status as estado,
        COALESCE(pm_total.meta_value, 0) as total,
        COALESCE(pm_email.meta_value, '') as email_cliente,
        COALESCE(pm_fname.meta_value, '') as nombre_cliente,
        COALESCE(pm_lname.meta_value, '') as apellido_cliente,
        COALESCE(pm_phone.meta_value, '') as telefono_cliente,
        COALESCE(pm_method.meta_value, '') as titulo_metodo_pago,
        COALESCE(pm_address1.meta_value, '') as direccion_1,
        COALESCE(pm_address2.meta_value, '') as direccion_2,
        COALESCE(pm_city.meta_value, '') as ciudad,
        COALESCE(pm_state.meta_value, '') as departamento,
        COALESCE(pm_country.meta_value, '') as pais,
        COALESCE(pm_barrio.meta_value, '') as barrio,
        COALESCE(pm_dni.meta_value, '') as dni
    FROM miau_posts p
    LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    LEFT JOIN miau_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
    LEFT JOIN miau_postmeta pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
    LEFT JOIN miau_postmeta pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
    LEFT JOIN miau_postmeta pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
    LEFT JOIN miau_postmeta pm_method ON p.ID = pm_method.post_id AND pm_method.meta_key = '_payment_method_title'
    LEFT JOIN miau_postmeta pm_address1 ON p.ID = pm_address1.post_id AND pm_address1.meta_key = '_billing_address_1'
    LEFT JOIN miau_postmeta pm_address2 ON p.ID = pm_address2.post_id AND pm_address2.meta_key = '_billing_address_2'
    LEFT JOIN miau_postmeta pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = '_billing_city'
    LEFT JOIN miau_postmeta pm_state ON p.ID = pm_state.post_id AND pm_state.meta_key = '_billing_state'
    LEFT JOIN miau_postmeta pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = '_billing_country'
    LEFT JOIN miau_postmeta pm_barrio ON p.ID = pm_barrio.post_id AND pm_barrio.meta_key = '_billing_barrio'
    LEFT JOIN miau_postmeta pm_dni ON p.ID = pm_dni.post_id AND pm_dni.meta_key = '_billing_dni'
    WHERE p.ID = '$orden_id' AND p.post_type = 'shop_order'
";

$result_orden = mysqli_query($miau, $query_orden);
if (mysqli_num_rows($result_orden) == 0) {
    die("Orden no encontrada");
}

$orden = mysqli_fetch_assoc($result_orden);

// Obtener envío y descuento desde postmeta (no están en HPOS)
$query_meta = "
    SELECT meta_key, meta_value 
    FROM miau_postmeta 
    WHERE post_id = '$orden_id' 
    AND meta_key IN ('_order_shipping', '_cart_discount')
";
$result_meta = mysqli_query($miau, $query_meta);
$envio = 0;
$descuento = 0;

while ($meta = mysqli_fetch_assoc($result_meta)) {
    if ($meta['meta_key'] == '_order_shipping') {
        $envio = (float)$meta['meta_value'];
    } elseif ($meta['meta_key'] == '_cart_discount') {
        $descuento = (float)$meta['meta_value'];
    }
}

$query_productos = "
    SELECT
        I.order_item_id,
        I.order_item_name,
        I.order_id,

        /* Cantidad y totales desde el pedido (lo que realmente se facturó) */
        COALESCE(CAST(IM.qty AS UNSIGNED), 1) AS product_qty,

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
        COALESCE(PM_sku_var.sku_var, PM_sku_prod.sku_prod, '') AS product_sku,

        /* IDs */
        CAST(IM.product_id AS UNSIGNED)   AS product_id,
        CAST(IM.variation_id AS UNSIGNED) AS variation_id,

        /* (Opcional) precios actuales del catálogo, por si quieres comparar */
        COALESCE(CAST(NULLIF(PM_regular.regular_price, '') AS DECIMAL(18,2)), 0) AS catalog_regular_price,
        COALESCE(CAST(NULLIF(PM_sale.sale_price, '') AS DECIMAL(18,2)), 0)    AS catalog_sale_price,
        COALESCE(CAST(NULLIF(PM_price.price, '') AS DECIMAL(18,2)), 0)   AS catalog_effective_price

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

    /* Precios de catálogo usando subconsultas para evitar duplicados */
    LEFT JOIN (
        SELECT post_id, meta_value as regular_price
        FROM miau_postmeta 
        WHERE meta_key = '_regular_price'
    ) PM_regular
        ON PM_regular.post_id = CAST(
            CASE
                WHEN IM.variation_id IS NOT NULL AND IM.variation_id <> '' AND IM.variation_id <> '0'
                THEN IM.variation_id
                ELSE IM.product_id
            END AS UNSIGNED
        )

    LEFT JOIN (
        SELECT post_id, meta_value as sale_price
        FROM miau_postmeta 
        WHERE meta_key = '_sale_price'
    ) PM_sale
        ON PM_sale.post_id = CAST(
            CASE
                WHEN IM.variation_id IS NOT NULL AND IM.variation_id <> '' AND IM.variation_id <> '0'
                THEN IM.variation_id
                ELSE IM.product_id
            END AS UNSIGNED
        )

    LEFT JOIN (
        SELECT post_id, meta_value as price
        FROM miau_postmeta 
        WHERE meta_key = '_price'
    ) PM_price
        ON PM_price.post_id = CAST(
            CASE
                WHEN IM.variation_id IS NOT NULL AND IM.variation_id <> '' AND IM.variation_id <> '0'
                THEN IM.variation_id
                ELSE IM.product_id
            END AS UNSIGNED
        )

    WHERE
        I.order_id = '$orden_id'
        AND I.order_item_type = 'line_item'
    GROUP BY I.order_item_id;

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
    'envio' => $envio,
    'descuento' => $descuento,
    'metodo' => (string)($orden['titulo_metodo_pago'] ?? ''),
    'direccion_1' => $orden['direccion_1'],
    'direccion_2' => $orden['direccion_2'],
    'ciudad' => $orden['ciudad'],
    'departamento' => $orden['departamento'],
    'pais' => $orden['pais'],
    'barrio' => $orden['barrio'],
    'dni' => $orden['dni']
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
