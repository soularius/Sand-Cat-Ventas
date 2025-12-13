<?php
/**
 * ==============================================================
 * ARCHIVO DE PRUEBA: test_customer_class.php
 * ==============================================================
 * Prueba la nueva clase WooCommerceCustomer refactorizada
 * y la integración con WooCommerceOrders
 */

require_once('class/woocommerce_customer.php');
require_once('class/woocommerce_orders.php');

echo "<h1>🧪 Prueba de Refactorización - Clase WooCommerceCustomer</h1>";

// Datos de prueba
$customerData = [
    'nombre1' => 'María',
    'nombre2' => 'González',
    '_billing_email' => 'maria.gonzalez.refactor@example.com',
    '_billing_phone' => '3009876543',
    '_shipping_address_1' => 'Carrera 15 #32-45',
    '_shipping_address_2' => 'Torre B Apto 502',
    '_shipping_city' => 'Medellín',
    '_shipping_state' => 'ANT',
    'billing_id' => '87654321',
    '_billing_neighborhood' => 'El Poblado'
];

echo "<h2>📋 Datos del Cliente:</h2>";
echo "<pre>" . print_r($customerData, true) . "</pre>";

try {
    echo "<h2>🔧 Prueba 1: Clase WooCommerceCustomer Independiente</h2>";
    
    $customerManager = new WooCommerceCustomer();
    
    // Buscar usuario existente
    echo "<h4>🔍 Buscando usuario existente...</h4>";
    $existingUserId = $customerManager->findWordPressUserByEmail($customerData['_billing_email']);
    
    if ($existingUserId) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
        echo "⚠️ Usuario ya existe con ID: $existingUserId";
        echo "</div>";
    } else {
        echo "<div style='background: #d1ecf1; color: #0c5460; padding: 10px; border: 1px solid #bee5eb; border-radius: 5px;'>";
        echo "ℹ️ Usuario no existe, se creará uno nuevo";
        echo "</div>";
    }
    
    // Procesar cliente completo
    echo "<h4>⚙️ Procesando cliente completo...</h4>";
    $result = $customerManager->processCustomer($customerData);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h5>✅ Cliente Procesado Exitosamente</h5>";
    echo "<strong>User ID:</strong> " . $result['user_id'] . "<br>";
    echo "<strong>Usuario Creado:</strong> " . ($result['created'] ? 'Sí' : 'No') . "<br>";
    echo "</div>";
    
    // Obtener información completa del cliente
    echo "<h4>📊 Información Completa del Cliente:</h4>";
    $customerInfo = $customerManager->getCustomerByEmail($customerData['_billing_email']);
    echo "<pre>" . print_r($customerInfo, true) . "</pre>";
    
    echo "<hr>";
    echo "<h2>🚀 Prueba 2: Integración con WooCommerceOrders</h2>";
    
    $orderData = [
        'products' => [
            [
                'id' => 789,
                'title' => 'Producto Refactorizado',
                'quantity' => 1,
                'price' => 75000,
                'regular_price' => 85000,
                'sale_price' => 75000
            ]
        ],
        'customer_data' => $customerData,
        'form_data' => [
            '_order_shipping' => 8000,
            '_cart_discount' => 3000,
            '_payment_method_title' => 'Pago Manual - Refactor Test',
            '_payment_method' => 'manual',
            'post_expcerpt' => 'Pedido de prueba con clase WooCommerceCustomer refactorizada'
        ]
    ];
    
    $wooOrders = new WooCommerceOrders();
    $orderResult = $wooOrders->createOrderFromSalesData($orderData, 'debug');
    
    if ($orderResult['success']) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<h5>✅ PEDIDO CREADO CON CLASE REFACTORIZADA</h5>";
        echo "<strong>Order ID:</strong> " . $orderResult['order_id'] . "<br>";
        echo "<strong>Total:</strong> $" . number_format($orderResult['total']) . " COP<br>";
        echo "</div>";
        
        echo "<h4>🔍 Debug de Integración:</h4>";
        echo "<pre>" . print_r($orderResult['debug'], true) . "</pre>";
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h5>❌ ERROR EN INTEGRACIÓN</h5>";
        echo "<strong>Error:</strong> " . $orderResult['error'] . "<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>💥 EXCEPCIÓN</h3>";
    echo "<strong>Mensaje:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>📚 Beneficios de la Refactorización:</h2>";
echo "<div style='background: #e2f3ff; color: #004085; padding: 15px; border: 1px solid #b8daff; border-radius: 5px;'>";
echo "<h4>🎯 Separación de Responsabilidades:</h4>";
echo "<ul>";
echo "<li><strong>WooCommerceCustomer:</strong> Manejo exclusivo de usuarios y clientes</li>";
echo "<li><strong>WooCommerceOrders:</strong> Enfocado solo en creación de pedidos</li>";
echo "<li><strong>Código más limpio:</strong> Cada clase tiene una responsabilidad específica</li>";
echo "<li><strong>Reutilizable:</strong> WooCommerceCustomer puede usarse independientemente</li>";
echo "<li><strong>Mantenible:</strong> Cambios en lógica de clientes no afectan pedidos</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>🔧 Funcionalidades de WooCommerceCustomer:</h4>";
echo "<ul>";
echo "<li><code>findWordPressUserByEmail()</code> - Buscar usuario por email</li>";
echo "<li><code>generateUniqueUsername()</code> - Generar username único</li>";
echo "<li><code>createWordPressUser()</code> - Crear usuario WordPress</li>";
echo "<li><code>upsertWooCommerceCustomer()</code> - Gestionar cliente WooCommerce</li>";
echo "<li><code>processCustomer()</code> - Proceso completo de cliente</li>";
echo "<li><code>getCustomerByEmail()</code> - Obtener información completa</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>📋 Consultas de Verificación:</h4>";
echo "<code>SELECT * FROM miau_users WHERE user_email = 'maria.gonzalez.refactor@example.com'</code><br><br>";
echo "<code>SELECT * FROM miau_wc_customer_lookup WHERE email = 'maria.gonzalez.refactor@example.com'</code><br><br>";
if (isset($orderResult['order_id'])) {
    $orderId = $orderResult['order_id'];
    echo "<code>SELECT post_author, post_type FROM miau_posts WHERE ID = $orderId</code><br><br>";
    echo "<code>SELECT meta_value FROM miau_postmeta WHERE post_id = $orderId AND meta_key = '_customer_user'</code><br>";
}
echo "</div>";
?>
