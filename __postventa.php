
<?php
// Definir las claves meta que necesitamos extraer
$metaKeys = [
    '_shipping_first_name',
    '_shipping_last_name', 
    'billing_id',
    '_shipping_city',
    '_shipping_state',
    '_shipping_address_1',
    '_shipping_address_2',
    '_billing_neighborhood',
    '_billing_phone',
    '_billing_email',
    '_paid_date',
    '_payment_method_title',
    '_cart_discount',
    '_order_total',
    '_order_shipping'
];

// Definir procesadores específicos para ciertos campos
$processors = [
    '_paid_date' => function($value) {
        return date("d m Y", strtotime($value));
    }
];

// Procesar los datos meta usando la función optimizada
$metaData = Utils::processMetaFromResult($lista, $metaKeys, 'meta_key', 'meta_value', $processors);

// Extraer los valores a variables individuales para compatibilidad
$_shipping_first_name = $metaData['_shipping_first_name'];
$_shipping_last_name = $metaData['_shipping_last_name'];
$billing_id = $metaData['billing_id'];
$_shipping_city = $metaData['_shipping_city'];
$_shipping_state = $metaData['_shipping_state'];
$_shipping_address_1 = $metaData['_shipping_address_1'];
$_shipping_address_2 = $metaData['_shipping_address_2'];
$_billing_neighborhood = $metaData['_billing_neighborhood'];
$_billing_phone = $metaData['_billing_phone'];
$_billing_email = $metaData['_billing_email'];
$_paid_date = $metaData['_paid_date'];
$metodo = $metaData['_payment_method_title'];
$_cart_discount = $metaData['_cart_discount'];
$vtotal = $metaData['_order_total'];
$_order_shipping = $metaData['_order_shipping'];
?>