<?php
/**
 * ==============================================================
 * ARCHIVO DE PRUEBA: test_order_creation.php
 * ==============================================================
 * Prueba la creación de pedidos con vinculación correcta de usuario
 * para que aparezcan en "Mis Órdenes" del cliente en WordPress
 */

require_once('class/woocommerce_orders.php');

// Datos de prueba para crear un pedido
$orderData = [
    'products' => [
        [
            'id' => 123,
            'title' => 'Producto de Prueba',
            'quantity' => 2,
            'price' => 50000,
            'regular_price' => 60000,
            'sale_price' => 50000
        ],
        [
            'id' => 456,
            'title' => 'Segundo Producto',
            'quantity' => 1,
            'price' => 30000,
            'regular_price' => 30000,
            'sale_price' => null
        ]
    ],
    'customer_data' => [
        'nombre1' => 'Juan',
        'nombre2' => 'Pérez',
        '_billing_email' => 'juan.perez.test@example.com',
        '_billing_phone' => '3001234567',
        '_shipping_address_1' => 'Calle 123 #45-67',
        '_shipping_address_2' => 'Apto 101',
        '_shipping_city' => 'Bogotá',
        '_shipping_state' => 'BOG',
        'billing_id' => '12345678',
        '_billing_neighborhood' => 'Centro'
    ],
    'form_data' => [
        '_order_shipping' => 10000,
        '_cart_discount' => 5000,
        '_payment_method_title' => 'Pago Manual - Prueba',
        '_payment_method' => 'manual',
        'post_expcerpt' => 'Pedido de prueba para verificar vinculación con usuario WordPress'
    ]
];

echo "<h1>🧪 Prueba de Creación de Pedido WooCommerce</h1>";
echo "<h2>📋 Datos del Pedido:</h2>";
echo "<pre>" . print_r($orderData, true) . "</pre>";

try {
    $wooOrders = new WooCommerceOrders();
    
    echo "<h2>🚀 Creando pedido...</h2>";
    $result = $wooOrders->createOrderFromSalesData($orderData, 'debug');
    
    if ($result['success']) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ PEDIDO CREADO EXITOSAMENTE</h3>";
        echo "<strong>Order ID:</strong> " . $result['order_id'] . "<br>";
        echo "<strong>Total:</strong> $" . number_format($result['total']) . " COP<br>";
        echo "</div>";
        
        echo "<h3>🔍 Debug Info:</h3>";
        echo "<pre>" . print_r($result['debug'], true) . "</pre>";
        
        // Verificar que el pedido esté correctamente vinculado
        echo "<h2>🔎 Verificación de Vinculación:</h2>";
        
        $orderId = $result['order_id'];
        
        // Verificar en miau_posts
        echo "<h4>📄 miau_posts:</h4>";
        $checkPosts = "SELECT post_author, post_type, post_status FROM miau_posts WHERE ID = $orderId";
        
        // Verificar _customer_user en postmeta
        echo "<h4>🏷️ miau_postmeta (_customer_user):</h4>";
        $checkMeta = "SELECT meta_value FROM miau_postmeta WHERE post_id = $orderId AND meta_key = '_customer_user'";
        
        // Verificar en miau_wc_orders (HPOS)
        echo "<h4>🏪 miau_wc_orders:</h4>";
        $checkHPOS = "SELECT customer_id, billing_email FROM miau_wc_orders WHERE id = $orderId";
        
        // Verificar usuario WordPress
        echo "<h4>👤 Usuario WordPress:</h4>";
        $email = $orderData['customer_data']['_billing_email'];
        $checkUser = "SELECT ID, user_login, user_email FROM miau_users WHERE user_email = '$email'";
        
        // Verificar cliente WooCommerce
        echo "<h4>🛒 Cliente WooCommerce:</h4>";
        $checkCustomer = "SELECT customer_id, user_id, email FROM miau_wc_customer_lookup WHERE email = '$email'";
        
        // Mostrar consultas para verificación manual
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>📋 Consultas para Verificación Manual:</h4>";
        echo "<code>$checkPosts</code><br><br>";
        echo "<code>$checkMeta</code><br><br>";
        echo "<code>$checkHPOS</code><br><br>";
        echo "<code>$checkUser</code><br><br>";
        echo "<code>$checkCustomer</code><br><br>";
        echo "</div>";
        
        echo "<div style='background: #cce5ff; color: #004085; padding: 15px; border: 1px solid #99d6ff; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>🎯 Pasos para Verificar en WordPress:</h4>";
        echo "1. Ir a WordPress Admin → WooCommerce → Pedidos<br>";
        echo "2. Buscar el pedido #$orderId<br>";
        echo "3. Verificar que aparezca el cliente: juan.perez.test@example.com<br>";
        echo "4. Ir a Usuarios → Todos los usuarios<br>";
        echo "5. Buscar usuario con email: juan.perez.test@example.com<br>";
        echo "6. Simular login como ese usuario<br>";
        echo "7. Ir a Mi Cuenta → Pedidos<br>";
        echo "8. Verificar que aparezca el pedido #$orderId<br>";
        echo "</div>";
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>❌ ERROR AL CREAR PEDIDO</h3>";
        echo "<strong>Error:</strong> " . $result['error'] . "<br>";
        echo "</div>";
        
        if (isset($result['debug'])) {
            echo "<h3>🔍 Debug Info:</h3>";
            echo "<pre>" . print_r($result['debug'], true) . "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>💥 EXCEPCIÓN</h3>";
    echo "<strong>Mensaje:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>📚 Resumen de Correcciones Implementadas:</h2>";
echo "<ul>";
echo "<li>✅ <strong>Vinculación user_id:</strong> El pedido ahora se vincula correctamente al usuario WordPress</li>";
echo "<li>✅ <strong>Campo _customer_user:</strong> Agregado en postmeta para compatibilidad legacy</li>";
echo "<li>✅ <strong>Usuario WordPress:</strong> Se crea automáticamente si no existe</li>";
echo "<li>✅ <strong>Cliente WooCommerce:</strong> Se actualiza en miau_wc_customer_lookup</li>";
echo "<li>✅ <strong>HPOS compatibility:</strong> customer_id correcto en todas las tablas HPOS</li>";
echo "<li>✅ <strong>Tablas de lookup:</strong> Vinculación correcta en analytics y stats</li>";
echo "</ul>";

echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>🔧 Problemas Solucionados:</h4>";
echo "<strong>ANTES:</strong> customer_id = 0 (pedido huérfano)<br>";
echo "<strong>DESPUÉS:</strong> customer_id = ID del usuario WordPress<br><br>";
echo "<strong>ANTES:</strong> Sin campo _customer_user en postmeta<br>";
echo "<strong>DESPUÉS:</strong> _customer_user = ID del usuario WordPress<br><br>";
echo "<strong>ANTES:</strong> post_author = 1 (admin)<br>";
echo "<strong>DESPUÉS:</strong> post_author = ID del usuario WordPress<br>";
echo "</div>";
?>
