
<?php
// Inicializar variables por defecto
$nombre1 = $nombre2 = $documento = $ciudad = $departamento = '';
$dir1 = $dir2 = $barrio = $celular = $correo = $fecha = '';
$metodo = '';
$descuento = 0;
$vtotal = 0;
$envio = 0;

// Si se encontró el cliente en WooCommerce, usar esos datos
if ($customer_found && !empty($customer_data)) {
    // Usar datos del cliente encontrado en WooCommerce
    $nombre1 = $customer_data['first_name'] ?? $customer_data['shipping_first_name'] ?? '';
    $nombre2 = $customer_data['last_name'] ?? $customer_data['shipping_last_name'] ?? '';
    $documento = $customer_data['billing_id'] ?? $billing_id;
    $ciudad = $customer_data['city'] ?? $customer_data['shipping_city'] ?? '';
    $departamento = $customer_data['state'] ?? $customer_data['shipping_state'] ?? '';
    $dir1 = $customer_data['address_1'] ?? $customer_data['shipping_address_1'] ?? '';
    $dir2 = $customer_data['address_2'] ?? $customer_data['shipping_address_2'] ?? '';
    $celular = $customer_data['phone'] ?? '';
    $correo = $customer_data['email'] ?? '';
    $fecha = date("Y-m-d");
    
} elseif (isset($lista) && isset($row_lista) && $row_lista !== null) {
    // Método tradicional - procesar desde base de datos
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
            $descuento = floatval($row_lista['meta_value']);
        }
        if($row_lista['meta_key']=='_order_total') {
            $vtotal = floatval($row_lista['meta_value']);
        }
        if($row_lista['meta_key']=='_order_shipping') {
            $envio = floatval($row_lista['meta_value']);
        }
    } while ($row_lista = mysqli_fetch_assoc($lista));
}
?>