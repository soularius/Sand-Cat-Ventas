<?php
/**
 * ==============================================================
 * ARCHIVO DE PRUEBA: test_pros_venta_original.php
 * ==============================================================
 * Prueba la implementación refactorizada directamente en pros_venta.php
 * Simula el POST del formulario para verificar que funciona
 */

echo "<h1>🧪 Prueba de pros_venta.php Refactorizado</h1>";

// Simular datos POST del formulario
$_POST = [
    'nombre1' => 'Ana',
    'nombre2' => 'Martínez',
    'billing_id' => '11223344',
    '_billing_email' => 'ana.martinez.original@example.com',
    '_billing_phone' => '3001112233',
    '_shipping_address_1' => 'Calle 50 #12-34',
    '_shipping_address_2' => 'Apto 201',
    '_billing_neighborhood' => 'La Candelaria',
    '_shipping_city' => 'Cartagena',
    '_shipping_state' => 'BOL',
    'post_expcerpt' => 'Pedido de prueba desde pros_venta.php refactorizado',
    '_order_shipping' => '15000',
    '_cart_discount' => '1500',
    '_payment_method_title' => 'Efectivo'
];

echo "<h2>📋 Datos POST Simulados:</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

echo "<h2>🚀 Ejecutando pros_venta.php...</h2>";

// Capturar output
ob_start();

try {
    // Incluir el archivo original que ahora tiene la lógica refactorizada
    include('pros_venta.php');
    
    $output = ob_get_contents();
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>✅ pros_venta.php Ejecutado Exitosamente</h4>";
    echo "<p>El archivo se ejecutó sin errores fatales.</p>";
    echo "</div>";
    
    // Mostrar output capturado si hay mensajes
    if (!empty(trim($output))) {
        echo "<h3>📄 Output del Archivo:</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>";
        echo $output;
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h4>💥 Error en pros_venta.php</h4>";
    echo "<strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
} catch (Error $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h4>💥 Error Fatal en pros_venta.php</h4>";
    echo "<strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
} finally {
    ob_end_clean();
}

echo "<hr>";
echo "<h2>📊 Verificaciones Recomendadas:</h2>";

echo "<div style='background: #e2f3ff; color: #004085; padding: 15px; border: 1px solid #b8daff; border-radius: 5px;'>";
echo "<h4>🔍 Consultas SQL para Verificar:</h4>";
echo "<ol>";
echo "<li><strong>Último pedido creado:</strong><br>";
echo "<code>SELECT ID, post_author, post_type, post_status FROM miau_posts WHERE post_type = 'shop_order' ORDER BY ID DESC LIMIT 1;</code></li>";
echo "<br>";
echo "<li><strong>Metadatos del pedido:</strong><br>";
echo "<code>SELECT meta_key, meta_value FROM miau_postmeta WHERE post_id = (SELECT MAX(ID) FROM miau_posts WHERE post_type = 'shop_order') AND meta_key IN ('_customer_user', '_billing_email', '_shipping_first_name');</code></li>";
echo "<br>";
echo "<li><strong>Usuario WordPress creado:</strong><br>";
echo "<code>SELECT ID, user_login, user_email, display_name FROM miau_users WHERE user_email = 'ana.martinez.original@example.com';</code></li>";
echo "<br>";
echo "<li><strong>Cliente WooCommerce:</strong><br>";
echo "<code>SELECT customer_id, user_id, email, first_name, last_name FROM miau_wc_customer_lookup WHERE email = 'ana.martinez.original@example.com';</code></li>";
echo "<br>";
echo "<li><strong>Direcciones del pedido:</strong><br>";
echo "<code>SELECT address_type, first_name, last_name, city, state FROM miau_wc_order_addresses WHERE order_id = (SELECT MAX(ID) FROM miau_posts WHERE post_type = 'shop_order');</code></li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>🎯 Qué Verificar en WordPress:</h4>";
echo "<ul>";
echo "<li>📋 <strong>WooCommerce → Pedidos:</strong> Debe aparecer el pedido con el cliente correcto</li>";
echo "<li>👤 <strong>Usuarios → Todos los usuarios:</strong> Debe aparecer ana.martinez.original@example.com</li>";
echo "<li>🛒 <strong>Simular login del cliente:</strong> El pedido debe aparecer en \"Mis Órdenes\"</li>";
echo "<li>🔗 <strong>URL del pedido:</strong> Debe ser accesible y no mostrar \"inválido\"</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>✅ Cambios Implementados en pros_venta.php:</h4>";
echo "<ul>";
echo "<li>🔧 <strong>Require agregado:</strong> <code>require_once('class/woocommerce_customer.php');</code></li>";
echo "<li>🚀 <strong>Lógica reemplazada:</strong> ~200 líneas de SQL → 1 llamada a clase</li>";
echo "<li>👤 <strong>Vinculación correcta:</strong> post_author = customer_id (no más 1)</li>";
echo "<li>🏷️ <strong>_customer_user:</strong> Campo crítico ahora se crea automáticamente</li>";
echo "<li>🛒 <strong>Cliente WooCommerce:</strong> Gestión en miau_wc_customer_lookup</li>";
echo "<li>🏠 <strong>Direcciones:</strong> Inserción en miau_wc_order_addresses</li>";
echo "<li>🐛 <strong>Manejo de errores:</strong> Try/catch completo con logging</li>";
echo "<li>📝 <strong>Logging mejorado:</strong> Información detallada en logs</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #f0fff4; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>🎉 Resultado Esperado:</h4>";
echo "<p><strong>ANTES:</strong> Pedidos no aparecían en \"Mis Órdenes\" del cliente</p>";
echo "<p><strong>DESPUÉS:</strong> Pedidos aparecen correctamente vinculados al cliente</p>";
echo "<br>";
echo "<p><strong>Problema Original:</strong> post_author = 1, sin _customer_user, pedidos huérfanos</p>";
echo "<p><strong>Solución Implementada:</strong> post_author = customer_id, _customer_user correcto, vinculación completa</p>";
echo "</div>";
?>
