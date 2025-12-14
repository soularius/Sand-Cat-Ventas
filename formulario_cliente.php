<?php
/* ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL); */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

// *** Restrict Access To Page: Grant or deny access to this page
// La función isAuthorized() ahora está disponible desde tools.php



$MM_restrictGoTo = "http://localhost/ventas";


if (!((isset($_SESSION['MM_Username'])))) { 
  $MM_qsChar = "?";
  $MM_referrer = $_SERVER['PHP_SELF'];
  if (strpos($MM_restrictGoTo, "?")) $MM_qsChar = "&";
  if (isset($QUERY_STRING) && strlen($QUERY_STRING) > 0) 
  $MM_referrer .= "?" . $QUERY_STRING;
  $MM_restrictGoTo = $MM_restrictGoTo. $MM_qsChar . "accesscheck=" . urlencode($MM_referrer);
  header("Location: ". $MM_restrictGoTo); 
  exit;
}
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? '';
$hoy = date("Y-m-d");

// Inicializar variables por defecto
$billing_id = '';
$post_id = '';
$row_lista = null;
$lista = null;
$totalRows_lista = 0;
$customer_found = false;
$customer_data = [];

// Si se navega desde pasos posteriores (3/4), puede venir un order_id para recargar datos
$existing_order_id = Utils::captureValue('_order_id', 'POST', '');
if (!empty($existing_order_id)) {
    $post_id = $existing_order_id;
    $query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$post_id'");
    $lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
    $row_lista = mysqli_fetch_assoc($lista);
    $totalRows_lista = mysqli_num_rows($lista);
}

if(isset($_POST['billing_id'])) {
    $billing_id = $_POST['billing_id'];
    $customer_found = $_POST['customer_found'] === 'true';
    
    // Si se encontró el cliente en WooCommerce, usar esos datos
    if ($customer_found && !empty($_POST['customer_data'])) {
        $customer_data = json_decode($_POST['customer_data'], true);
        
        // Usar los datos del cliente encontrado
        if ($customer_data) {
            $post_id = $customer_data['order_id'] ?? '';
            
            // Simular el resultado de la consulta con los datos del cliente
            $lista = null;
            $row_lista = null;
            $totalRows_lista = 1; // Indicar que se encontraron datos
        }
    } else {
        // Búsqueda tradicional (mantener por compatibilidad)
        $query_idlast = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE meta_value = '$billing_id' AND meta_key = '_billing_id' ORDER BY post_id DESC LIMIT 1");
        $idlast = mysqli_query($miau, $query_idlast) or die(mysqli_error($miau));
        $row_idlast = mysqli_fetch_assoc($idlast);
        $totalRows_idlast = mysqli_num_rows($idlast);
        $post_id = $row_idlast['post_id'] ?? '';
        
        // Solo ejecutar la segunda consulta si se encontró un post_id válido
        if (!empty($post_id)) {
            $query_lista = sprintf("SELECT post_id, meta_key, meta_value FROM miau_postmeta WHERE post_id = '$post_id'");
            $lista = mysqli_query($miau, $query_lista) or die(mysqli_error($miau));
            $row_lista = mysqli_fetch_assoc($lista);
            $totalRows_lista = mysqli_num_rows($lista);
        } else {
            // Inicializar variables si no se encontró post_id
            $lista = null;
            $row_lista = null;
            $totalRows_lista = 0;
        }
    }
}


// 3. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<link rel="stylesheet" href="assets/css/wizard-form.css">

<body>
<div class="container">
<?php include("parts/menf.php"); ?>
<?php 
// Configurar el paso actual para el wizard
$current_step = 1; // Paso 1: Datos del Cliente
include('parts/step_wizard.php'); 
?>

        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-user-edit me-2"></i>Datos del Cliente
                </h2>
                <p class="text-muted">Complete los datos del cliente para continuar</p>
            </div>
        </div>

        <?php
            // Verificar si hay datos de cliente en sesión antes de procesar postmeta
            $session_customer_data = [];
            $session_billing_id = '';
            
            // Iniciar sesión si no está iniciada
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            // Obtener datos de sesión si existen (sin incluir archivo externo)
            if (isset($_SESSION['last_customer_data'])) {
                $sessionData = $_SESSION['last_customer_data'];
                
                // Verificar que los datos no sean muy antiguos (1 hora)
                $maxAge = 3600; // 1 hora
                if ((time() - $sessionData['timestamp']) <= $maxAge) {
                    $session_customer_data = $sessionData['customer'];
                    $session_billing_id = $sessionData['billing_id'];
                    
                    echo "<!-- SESIÓN: Cliente encontrado en sesión - DNI: $session_billing_id -->";
                    
                    // Simular que se encontró el cliente para que postmeta.php use estos datos
                    $customer_found = true;
                    $customer_data = $session_customer_data;
                    $billing_id = $session_billing_id;
                } else {
                    // Datos muy antiguos, limpiar
                    unset($_SESSION['last_customer_data']);
                    echo "<!-- SESIÓN: Datos expirados, limpiados -->";
                }
            } else {
                // Verificar si al menos hay un DNI guardado
                if (isset($_SESSION['last_billing_id']) && empty($billing_id)) {
                    $billing_id = $_SESSION['last_billing_id'];
                    echo "<!-- SESIÓN: DNI recuperado de sesión: $billing_id -->";
                }
            }
            
            // Procesar datos normalmente
            include("postmeta.php");
            $documento = !empty($billing_id) ? $billing_id : $documento;
            
            // Debug: Mostrar valores cargados
            if (!empty($ciudad) || !empty($departamento)) {
                echo "<!-- DEBUG: Ciudad='$ciudad', Departamento='$departamento' -->";
            }
            if (!empty($session_billing_id)) {
                echo "<!-- SESIÓN: Datos cargados desde sesión PHP -->";
            }
        ?>

        <form action="resumen_cliente.php" method="post" id="d_usuario">
            <input type="hidden" id="_order_id" name="_order_id" value="<?php echo htmlspecialchars($post_id); ?>">
            <div class="row">
                <!-- Columna Izquierda -->
                <div class="col-lg-6">
                    <!-- Información Personal -->
                    <div class="form-section">
                        <h5><i class="fas fa-user-circle"></i> Información Personal</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nombre1" class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" id="nombre1" name="nombre1" 
                                           value="<?php echo strtoupper($nombre1); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="nombre2" class="form-label">Apellido *</label>
                                    <input type="text" class="form-control" id="nombre2" name="nombre2" 
                                           value="<?php echo strtoupper($nombre2); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="billing_id" class="form-label">Documento de Identidad *</label>
                            <input type="number" class="form-control" id="billing_id" name="billing_id" 
                                   value="<?php echo strtoupper($documento); ?>" required>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="form-section">
                        <h5><i class="fas fa-phone"></i> Información de Contacto</h5>
                        <div class="form-group mb-3">
                            <label for="_billing_email" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="_billing_email" name="_billing_email" 
                                   value="<?php echo strtoupper($correo); ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="_billing_phone" class="form-label">Número de Celular *</label>
                            <input type="text" class="form-control" id="_billing_phone" name="_billing_phone" 
                                   value="<?php echo strtoupper($celular); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="col-lg-6">
                    <!-- Dirección de Envío -->
                    <div class="form-section">
                        <h5><i class="fas fa-map-marker-alt"></i> Dirección de Envío</h5>
                        <div class="form-group mb-3">
                            <label for="_shipping_address_1" class="form-label">Dirección Principal *</label>
                            <input type="text" class="form-control" id="_shipping_address_1" name="_shipping_address_1" 
                                   value="<?php echo strtoupper($dir1); ?>" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="_shipping_address_2" class="form-label">Complemento de Dirección</label>
                            <input type="text" class="form-control" id="_shipping_address_2" name="_shipping_address_2" 
                                   value="<?php echo strtoupper($dir2); ?>" placeholder="Apartamento, suite, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_billing_neighborhood" class="form-label">Barrio *</label>
                                    <input type="text" class="form-control" id="_billing_neighborhood" name="_billing_neighborhood" 
                                           value="<?php echo strtoupper($barrio); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_shipping_country" class="form-label">País</label>
                                    <input type="text" class="form-control" id="_shipping_country" name="_shipping_country" 
                                           value="COLOMBIA" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_shipping_state" class="form-label">Departamento *</label>
                                    <select class="form-control" id="_shipping_state" name="_shipping_state" required>
                                        <option value="">Seleccione un departamento</option>
                                        <?php
                                        // Obtener departamentos desde archivo de datos del plugin
                                        $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
                                        if (file_exists($states_file)) {
                                            $colombia_states = include($states_file);
                                            
                                            foreach ($colombia_states as $code => $name) {
                                                $selected = (strtoupper($departamento) == strtoupper($code)) ? 'selected' : '';
                                                echo "<option value=\"$code\" data-code=\"$code\" $selected>$name</option>";
                                            }
                                        } else {
                                            echo "<option value=\"\">Error: No se encontraron departamentos</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_shipping_city" class="form-label">Ciudad *</label>
                                    <select class="form-control" id="_shipping_city" name="_shipping_city" required>
                                        <option value="">Primero seleccione un departamento</option>
                                        <?php if (!empty($ciudad)): ?>
                                            <option value="<?php echo strtoupper($ciudad); ?>" selected><?php echo strtoupper($ciudad); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Pago y Envío -->
                    <div class="form-section">
                        <h5><i class="fas fa-credit-card"></i> Pago y Envío</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_order_shipping" class="form-label">Costo de Envío *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-brand bg-custom text-white border-0">$</span>
                                        <input type="text" class="form-control" id="_order_shipping" name="_order_shipping" 
                                               value="10,000" required placeholder="0"
                                               data-value="10000"
                                               oninput="formatCurrency(this)"
                                               onblur="validateCurrency(this)">
                                        <input type="hidden" id="_order_shipping_value" name="_order_shipping_value" value="10000">
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Formato: $10,000 COP
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="_payment_method_title" class="form-label">Forma de Pago *</label>
                                    <select class="form-control" id="_payment_method_title" name="_payment_method_title">
                                        <option value="Pago Contra Entrega Aplica solo para Bogotá" selected>
                                            Pago Contra Entrega (Solo Bogotá)
                                        </option>
                                        <option value="Paga con PSE y tarjetas de crédito">
                                            PSE y Tarjetas de Crédito
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="post_expcerpt" class="form-label">Observaciones Adicionales</label>
                            <textarea class="form-control" id="post_expcerpt" name="post_expcerpt" rows="3" 
                                      placeholder="Instrucciones especiales de entrega, comentarios, etc."></textarea>
                        </div>
                        <input type="hidden" id="_cart_discount" name="_cart_discount" value="0">
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="adminventas.php" class="btn btn-danger btn-custom">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-custom">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
<?php include("parts/foot.php"); ?>

<script>
// Funciones para formato de moneda
function formatCurrency(input) {
    // Obtener solo números
    let value = input.value.replace(/[^\d]/g, '');
    
    // Si está vacío, limpiar
    if (value === '') {
        input.value = '';
        input.setAttribute('data-value', '0');
        document.getElementById('_order_shipping_value').value = '0';
        return;
    }
    
    // Convertir a número y formatear con comas
    let numericValue = parseInt(value);
    let formattedValue = numericValue.toLocaleString('es-CO');
    
    // Actualizar el input visual y el valor real
    input.value = formattedValue;
    input.setAttribute('data-value', numericValue);
    document.getElementById('_order_shipping_value').value = numericValue;
}

function validateCurrency(input) {
    // Validar que tenga un valor mínimo
    let numericValue = parseInt(input.getAttribute('data-value') || '0');
    
    if (numericValue < 0) {
        input.value = '0';
        input.setAttribute('data-value', '0');
        document.getElementById('_order_shipping_value').value = '0';
        
        // Mostrar mensaje de error
        input.classList.add('is-invalid');
        setTimeout(() => {
            input.classList.remove('is-invalid');
        }, 3000);
    } else {
        input.classList.remove('is-invalid');
    }
}

// Manejo de departamentos y ciudades de Colombia
$(document).ready(function() {
    // Inicializar formato de moneda al cargar la página
    const shippingInput = document.getElementById('_order_shipping');
    if (shippingInput && shippingInput.value) {
        formatCurrency(shippingInput);
    }
    
    // Cuando cambia el departamento, cargar las ciudades
    $('#_shipping_state').on('change', function() {
        const departamento = $(this).find('option:selected').data('code');
        const citySelect = $('#_shipping_city');
        
        // Guardar la ciudad actualmente seleccionada para preservarla
        const currentCity = citySelect.val();
        
        // Limpiar ciudades
        citySelect.html('<option value="">Cargando ciudades...</option>');
        citySelect.prop('disabled', true);
        
        if (departamento) {
            // Obtener ciudades del departamento desde la base de datos
            $.ajax({
                url: 'get_colombia_data.php',
                method: 'GET',
                data: {
                    action: 'ciudades',
                    departamento: departamento
                },
                dataType: 'json',
                success: function(response) {
                    citySelect.html('<option value="">Seleccione una ciudad</option>');
                    
                    if (response.success && response.data) {
                        let cityFound = false;
                        
                        response.data.forEach(function(ciudad) {
                            const isSelected = (currentCity && 
                                (ciudad.name.toLowerCase() === currentCity.toLowerCase() ||
                                 ciudad.name.toUpperCase() === currentCity.toUpperCase()));
                            
                            if (isSelected) {
                                cityFound = true;
                                citySelect.append(`<option value="${ciudad.name}" selected>${ciudad.name}</option>`);
                            } else {
                                citySelect.append(`<option value="${ciudad.name}">${ciudad.name}</option>`);
                            }
                        });
                        
                        // Si no se encontró la ciudad en las opciones, pero hay una ciudad preseleccionada
                        // (por ejemplo, desde datos del cliente), agregarla como opción
                        if (!cityFound && currentCity && currentCity !== '') {
                            citySelect.append(`<option value="${currentCity}" selected>${currentCity}</option>`);
                            console.log('Ciudad del cliente agregada:', currentCity);
                        }
                        
                    } else {
                        citySelect.append('<option value="">No se encontraron ciudades</option>');
                    }
                    
                    citySelect.prop('disabled', false);
                    
                    // Trigger change event para que la persistencia guarde el valor
                    if (citySelect.val()) {
                        citySelect.trigger('change');
                    }
                },
                error: function() {
                    citySelect.html('<option value="">Error cargando ciudades</option>');
                    citySelect.prop('disabled', false);
                }
            });
        } else {
            citySelect.html('<option value="">Primero seleccione un departamento</option>');
            citySelect.prop('disabled', false);
        }
    });
    
    // Función para cargar ciudades con ciudad preseleccionada
    function loadCitiesWithPreselection() {
        const departamento = $('#_shipping_state').val();
        const ciudadPreseleccionada = $('#_shipping_city option:selected').val();
        
        console.log('Función loadCitiesWithPreselection:', {
            departamento: departamento,
            ciudadPreseleccionada: ciudadPreseleccionada
        });
        
        if (departamento && ciudadPreseleccionada) {
            console.log('Cargando ciudades con preselección:', ciudadPreseleccionada);
            $('#_shipping_state').trigger('change');
        } else if (departamento) {
            $('#_shipping_state').trigger('change');
        }
    }
    
    // Función para inicializar datos del cliente
    function initializeClientData() {
        // Verificar si hay datos del cliente cargados desde PHP
        <?php if (!empty($ciudad) && !empty($departamento)): ?>
        const clientCity = '<?php echo addslashes($ciudad); ?>';
        const clientState = '<?php echo addslashes($departamento); ?>';
        
        console.log('Datos del cliente detectados:', {
            ciudad: clientCity,
            departamento: clientState
        });
        
        // Si hay datos del cliente, asegurar que estén seleccionados
        if (clientState) {
            $('#_shipping_state').val(clientState);
        }
        
        if (clientCity) {
            // Agregar la ciudad como opción temporal si no existe
            const citySelect = $('#_shipping_city');
            if (citySelect.find(`option[value="${clientCity}"]`).length === 0) {
                citySelect.append(`<option value="${clientCity}" selected>${clientCity}</option>`);
                console.log('Ciudad del cliente agregada temporalmente:', clientCity);
            } else {
                citySelect.val(clientCity);
            }
        }
        <?php endif; ?>
    }
    
    // Inicializar datos del cliente primero
    initializeClientData();
    
    // Si ya hay un departamento seleccionado al cargar la página, cargar sus ciudades
    if ($('#_shipping_state').val()) {
        // Usar setTimeout para asegurar que el DOM esté completamente cargado
        setTimeout(function() {
            loadCitiesWithPreselection();
        }, 200);
    }
});
</script>

<!-- Sistema de Persistencia de Formularios -->
<script src="assets/js/form-persistence.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ✅ Tomar el formulario correcto del paso 1
    const form = document.getElementById('d_usuario');
    if (!form) return;

    // ✅ Inicializar persistencia en la misma key del wizard
    initFormPersistence(form.id, 'ventas_wizard_form_data');

    // ✅ Guardar al enviar por si navegan rápido
    form.addEventListener('submit', function() {
        try {
            window.formPersistenceInstance?.saveFormData?.();
        } catch (e) {}
    });

    console.log('Persistencia inicializada para:', form.id);
    
    // Verificar si hay datos cargados desde sesión PHP
    <?php if (!empty($session_billing_id)): ?>
    // Mostrar notificación de datos cargados desde sesión
    setTimeout(function() {
        showSessionDataNotification('<?php echo addslashes($session_billing_id); ?>');
    }, 1000);
    <?php endif; ?>
});

/**
 * Función para mostrar notificación usando form-persistence.js
 */
function showSessionDataNotification(dni) {
    // Usar la función de form-persistence.js si está disponible
    if (window.formPersistenceInstance && typeof window.formPersistenceInstance.showRestoreNotification === 'function') {
        window.formPersistenceInstance.showRestoreNotification(dni);
    } else {
        console.warn('FormPersistence no está disponible para mostrar notificación');
    }
}
</script>

</div>
</body>
</html>
<?php
mysqli_free_result($usuario);
?>