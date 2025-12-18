<?php
// Archivo de prueba para verificar el endpoint get_order_details.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Order Details</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Test Order Details Endpoint</h1>
    
    <div>
        <label>Order ID:</label>
        <input type="number" id="test-order-id" value="23066" placeholder="Ingrese ID del pedido">
        <button onclick="testOrderDetails()">Test AJAX</button>
    </div>
    
    <div id="result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;">
        <h3>Resultado:</h3>
        <pre id="response-content">Haga clic en "Test AJAX" para probar</pre>
    </div>

    <script>
    function testOrderDetails() {
        const orderId = $('#test-order-id').val();
        
        if (!orderId) {
            $('#response-content').text('Por favor ingrese un Order ID');
            return;
        }
        
        $('#response-content').text('Enviando petición...');
        
        $.ajax({
            url: 'get_order_details.php',
            method: 'POST',
            data: { order_id: orderId },
            dataType: 'json',
            success: function(response) {
                $('#response-content').text('SUCCESS:\n' + JSON.stringify(response, null, 2));
            },
            error: function(xhr, status, error) {
                $('#response-content').text('ERROR:\n' + 
                    'Status: ' + status + '\n' +
                    'Error: ' + error + '\n' +
                    'Response: ' + xhr.responseText);
            }
        });
    }
    </script>
</body>
</html>
