<?php


/**
 * ==============================================================
 * FILE: test_create_order_db.php
 * ==============================================================
 * Script standalone para probar creación de orden 100% DB
 */

// require_once('class/autoload.php'); // si tu autoload ya carga config y tools
require_once('class/autoload.php');
require_once('class/woocommerce_orders.php');


header('Content-Type: text/html; charset=utf-8');

echo "<h3>Test: Crear Orden WooCommerce (DB-only)</h3>";

try {
    $ordersService = new WooCommerceOrders();

    echo "<h4>Chequeo de tablas</h4>";
    echo '<pre>' . htmlspecialchars(print_r($ordersService->checkTableStructure(), true)) . '</pre>';

    // Payload mínimo de prueba (ajústalo a tus valores reales)
    $orderData = [
        'products' => [
            [
                'id' => 0, // se reemplaza abajo si encontramos un producto real
                'title' => 'Producto de prueba',
                'quantity' => 1,
                'price' => 10000,
                'regular_price' => 10000,
                'sale_price' => null
            ]
        ],
        'customer_data' => [
            'nombre1' => 'Cliente',
            'nombre2' => 'Prueba',
            '_billing_email' => 'cliente.prueba@example.com',
            '_billing_phone' => '3000000000',
            '_shipping_address_1' => 'Calle 1 # 2-3',
            '_shipping_address_2' => 'Apto 101',
            '_shipping_city' => 'Bogotá D.C.',
            '_shipping_state' => 'BOG',
            'dni' => '123456789',
            '_billing_neighborhood' => 'Centro'
        ],
        'form_data' => [
            // OJO: en tu JS se usa _order_shipping y _cart_discount
            '_order_shipping' => 5000,
            '_cart_discount' => 0,
            '_payment_method_title' => 'Pago Manual',
            // opcional: slug del gateway
            '_payment_method' => 'bacs',
            'post_expcerpt' => 'Orden creada desde test_create_order_db.php'
        ]
    ];

    // Intentar tomar el último producto publicado
    $wp = DatabaseConfig::getWordPressConnection();
    $q = "SELECT ID, post_title FROM miau_posts WHERE post_type='product' AND post_status IN ('publish','private') ORDER BY ID DESC LIMIT 1";
    $r = mysqli_query($wp, $q);
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        $orderData['products'][0]['id'] = (int)$row['ID'];
        $orderData['products'][0]['title'] = (string)$row['post_title'];
        echo "<p><strong>Producto real usado:</strong> #" . (int)$row['ID'] . " - " . htmlspecialchars((string)$row['post_title']) . "</p>";
    } else {
        echo "<p><strong>Advertencia:</strong> No se encontró producto real. product_id=0 puede romper lookups/reportes.</p>";
    }

    echo "<h4>Payload</h4>";
    echo '<pre>' . htmlspecialchars(print_r($orderData, true)) . '</pre>';

    echo "<h4>Creando orden (DB-only)...</h4>";
    $result = $ordersService->createOrderFromSalesData($orderData, 'debug');

    echo "<h4>Resultado</h4>";
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';

    $orderId = (int)($result['order_id'] ?? 0);
    if ($orderId > 0) {
        echo "<h4>Verificación rápida</h4>";
        echo "<p>Orden creada con ID: <strong>#{$orderId}</strong></p>";

        // Items
        $items = $ordersService->getOrderItems($orderId);
        echo "<h4>Items creados</h4>";
        echo '<pre>' . htmlspecialchars(print_r($items, true)) . '</pre>';

        // Detalle
        $order = $ordersService->getOrderById($orderId);
        echo "<h4>Detalle (unificado)</h4>";
        echo '<pre>' . htmlspecialchars(print_r($order, true)) . '</pre>';
    }

} catch (Exception $e) {
    echo "<h4 style='color:#b00'>ERROR</h4>";
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
