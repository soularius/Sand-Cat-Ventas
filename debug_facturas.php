<?php
/**
 * Script de debugging temporal para verificar datos de facturas
 */

// Incluir configuración
require_once 'class/autoload.php';

echo "<h2>DEBUG: Verificación de Facturas vs Órdenes</h2>";

try {
    // 1. Verificar facturas en base de ventas
    echo "<h3>1. Facturas en base ventassc:</h3>";
    $ventas_connection = DatabaseConfig::getVentasConnection();
    $query_facturas = "SELECT id_order, factura, estado, fecha FROM facturas ORDER BY id_order";
    $result = mysqli_query($ventas_connection, $query_facturas);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID Order</th><th>Factura</th><th>Estado</th><th>Fecha</th></tr>";
    
    $facturas_activas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $color = $row['estado'] === 'a' ? 'lightgreen' : 'lightcoral';
        echo "<tr style='background-color: {$color}'>";
        echo "<td>{$row['id_order']}</td>";
        echo "<td>{$row['factura']}</td>";
        echo "<td>{$row['estado']}</td>";
        echo "<td>{$row['fecha']}</td>";
        echo "</tr>";
        
        if ($row['estado'] === 'a') {
            $facturas_activas[] = (int)$row['id_order'];
        }
    }
    echo "</table>";
    mysqli_close($ventas_connection);
    
    echo "<p><strong>IDs de facturas activas: [" . implode(', ', $facturas_activas) . "]</strong></p>";
    echo "<p><strong>Total facturas activas: " . count($facturas_activas) . "</strong></p>";
    
    // 2. Verificar órdenes en WordPress
    echo "<h3>2. Órdenes en WordPress (primeras 25):</h3>";
    $wc_orders = new WooCommerceOrders();
    $wp_connection = DatabaseConfig::getWordPressConnection();
    
    $query_orders = "SELECT 
        p.ID,
        p.post_date,
        p.post_status,
        COALESCE(pm_fname.meta_value, '') as nombre1,
        COALESCE(pm_lname.meta_value, '') as nombre2,
        COALESCE(os.total_sales, pm_total.meta_value, 0) as valor
    FROM miau_posts p
    LEFT JOIN miau_postmeta pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
    LEFT JOIN miau_postmeta pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
    LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
    LEFT JOIN miau_wc_order_stats os ON p.ID = os.order_id
    WHERE p.post_type = 'shop_order'
    AND p.post_status NOT IN ('wc-trash', 'trash', 'auto-draft')
    ORDER BY p.ID DESC
    LIMIT 25";
    
    $result = mysqli_query($wp_connection, $query_orders);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Cliente</th><th>Estado</th><th>Valor</th><th>Fecha</th><th>¿Facturado?</th></tr>";
    
    $total_orders = 0;
    $facturadas_encontradas = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $total_orders++;
        $order_id = (int)$row['ID'];
        $has_invoice = in_array($order_id, $facturas_activas);
        
        if ($has_invoice) {
            $facturadas_encontradas++;
        }
        
        $color = $has_invoice ? 'lightgreen' : 'white';
        $cliente = trim(($row['nombre1'] ?? '') . ' ' . ($row['nombre2'] ?? ''));
        
        echo "<tr style='background-color: {$color}'>";
        echo "<td>{$order_id}</td>";
        echo "<td>{$cliente}</td>";
        echo "<td>{$row['post_status']}</td>";
        echo "<td>" . number_format($row['valor']) . "</td>";
        echo "<td>{$row['post_date']}</td>";
        echo "<td>" . ($has_invoice ? 'SÍ' : 'NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    mysqli_close($wp_connection);
    
    echo "<h3>3. Resumen:</h3>";
    echo "<p><strong>Total órdenes mostradas: {$total_orders}</strong></p>";
    echo "<p><strong>Órdenes marcadas como facturadas: {$facturadas_encontradas}</strong></p>";
    echo "<p><strong>Facturas activas en BD: " . count($facturas_activas) . "</strong></p>";
    
    // 3. Verificar coincidencias
    echo "<h3>4. Análisis de coincidencias:</h3>";
    if (count($facturas_activas) > 0) {
        echo "<p>IDs en tabla facturas: [" . implode(', ', $facturas_activas) . "]</p>";
        
        // Verificar si los IDs de facturas existen en WordPress
        $facturas_str = implode(',', $facturas_activas);
        $query_check = "SELECT ID FROM miau_posts WHERE ID IN ($facturas_str) AND post_type = 'shop_order'";
        $result_check = mysqli_query(DatabaseConfig::getWordPressConnection(), $query_check);
        
        $orders_found = [];
        while ($row = mysqli_fetch_assoc($result_check)) {
            $orders_found[] = $row['ID'];
        }
        
        echo "<p>IDs que existen en WordPress: [" . implode(', ', $orders_found) . "]</p>";
        
        $missing = array_diff($facturas_activas, $orders_found);
        if (!empty($missing)) {
            echo "<p style='color: red;'><strong>IDs de facturas que NO existen en WordPress: [" . implode(', ', $missing) . "]</strong></p>";
        }
    } else {
        echo "<p style='color: orange;'><strong>No hay facturas activas en la base de datos</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
