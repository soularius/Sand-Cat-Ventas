<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sand&Cat - Sistema de Ventas</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="https://sandycat.com.co/wp-content/uploads/2020/05/favicon.jpg" type="image/x-icon" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" 
            integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/wizard-form.css">
    <link rel="stylesheet" href="assets/css/login.css">

    <script>
        window.colombiaStates = <?php
            $states_file = __DIR__ . '/../data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
            $colombia_states = [];
            if (file_exists($states_file)) {
                $colombia_states = include($states_file);
            }
            echo json_encode($colombia_states, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;
        window.VentasUtils = window.VentasUtils || {};
        window.VentasUtils.getColombiaStateName = function(stateCode) {
            const code = (stateCode || '').toString().trim();
            if (!code) return '';
            return (window.colombiaStates && window.colombiaStates[code]) ? window.colombiaStates[code] : code;
        };
        window.VentasUtils.getLocalStorageJSON = function(key, fallbackValue) {
            try {
                const raw = localStorage.getItem(key);
                return raw ? JSON.parse(raw) : fallbackValue;
            } catch (e) {
                return fallbackValue;
            }
        };
        window.VentasUtils.getCustomerData = function() {
            return window.VentasUtils.getLocalStorageJSON('ventas_customer_data', null);
        };
        window.VentasUtils.getOrderSummary = function() {
            return window.VentasUtils.getLocalStorageJSON('ventas_order_summary', null);
        };
    </script>
</head>
