<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar login handler centralizado
require_once('parts/login_handler.php');
// 2. Cargar clases específicas
require_once('class/woocommerce_orders.php');

// 4. Obtener datos del usuario usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    Utils::logError("No se pudieron obtener datos del usuario en pedidos.php", 'ERROR', 'pedidos.php');
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';

// 5. Inicializar clase de órdenes WooCommerce
$wc_orders = new WooCommerceOrders();

$acti1 = 'active';
$acti2 = 'fade';
$pes1 = 'active';
$pes2 = '';
if(isset($_GET['df']) && !empty($_GET['df'])){
	$diasfin = $_GET['df'];
	$acti1 = 'fade';
	$acti2 = 'active';
	$pes1 = '';
	$pes2 = 'active';
}
// 6. Configurar fechas para filtros
$hoy = date("Y-m-d");
$inifact = date("Y-m-d", strtotime('+1 day', strtotime($hoy)));
if(isset($diasfin)) {
	$dias = $diasfin;
	$finfact = date("Y-m-d", strtotime('-'.$dias.' day', strtotime($hoy)));
} else {
	$dias = 30;	
	$finfact = date("Y-m-d", strtotime('-30 day', strtotime($hoy)));
}

// 7. Procesar acciones de pedidos usando clase WooCommerce
$carga = '';
if(isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) {
	$venta = Utils::sanitizeInput($_POST['id_ventas']);
	$factu = Utils::sanitizeInput($_POST['num']);
	
	// Usar método de la clase para completar pedido
	if($wc_orders->completeOrder($venta, $factu)) {
		Utils::logError("Pedido completado: ID=$venta, Factura=$factu", 'INFO', 'pedidos.php');
	} else {
		Utils::logError("Error completando pedido: ID=$venta", 'ERROR', 'pedidos.php');
	}
}
if(isset($_POST['id_ventas']) && isset($_POST['cancela'])) {
	$venta = Utils::sanitizeInput($_POST['id_ventas']);
	$factu = Utils::sanitizeInput($_POST['num']);
	
	// Usar método de la clase para cancelar pedido
	if($wc_orders->cancelOrder($venta)) {
		Utils::logError("Pedido cancelado: ID=$venta", 'INFO', 'pedidos.php');
	} else {
		Utils::logError("Error cancelando pedido: ID=$venta", 'ERROR', 'pedidos.php');
	}
}
if(isset($_POST['fin_pedido'])) { 
	$venta = Utils::sanitizeInput($_POST['fin_pedido']);
	
	// Usar método de la clase para procesar pedido
	if($wc_orders->processOrder($venta)) {
		Utils::logError("Pedido procesado: ID=$venta", 'INFO', 'pedidos.php');
	} else {
		Utils::logError("Error procesando pedido: ID=$venta", 'ERROR', 'pedidos.php');
	}
}


// 8. Obtener pedidos pendientes usando clase WooCommerce
// Incluye pedidos en processing y on-hold con método de pago cheque
$pendientes_data = $wc_orders->getPendingOrders();
$totalRows_pendientes = count($pendientes_data);
$row_pendientes = $totalRows_pendientes > 0 ? $pendientes_data[0] : null;

// 9. Obtener pedidos facturados usando clase WooCommerce
$pendientesf_data = $wc_orders->getInvoicedOrders($finfact, $inifact);
$totalRows_pendientesf = count($pendientesf_data);
$row_pendientesf = $totalRows_pendientesf > 0 ? $pendientesf_data[0] : null;

// 3. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<body style="padding-top: 70px">
<div class="container">
<?php include("parts/menf.php"); ?><br />
<br />
  <h2>Ventas Woocommerce</h2>
	<ul class="nav nav-tabs">
  <li class="nav-item">
    <a class="nav-link <?php echo $pes1; ?>" data-toggle="tab" href="#pendiente">Pendientes</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $pes2; ?>" data-toggle="tab" href="#terminada">Facturados</a>
  </li>
</ul>
  <!-- <p>The .table-hover class enables a hover state (grey background on mouse over) on table rows:</p>  -->	
	
		<?php if(isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) { ?>
		<form action="fact.php" class="login-form" method="post" target="_blank" id="impr" name="impr">
			<input type="hidden" id="id_ventas" name="id_ventas" value="<?php echo $venta; ?>" />
			<input type="hidden" id="factura" name="factura" value="si" />
			<div class="row">
				<div class="input-group mb-3 text-center">
					<input type="hidden" class="form-control" id="num" name="num" value="<?php echo $fact; ?>" readonly>
					<div class="input-group-append">
						<button class="btn btn-outline-primary" type="submit" name="ingfact" id="ingfact" style="visibility: hidden"></button>
									  </div>
									</div>
									</div>
					 		 </form>
	  <?php } ?>
<div class="tab-content">
  <div class="tab-pane container <?php echo $acti1; ?>" id="pendiente"><br />
		<?php if($totalRows_pendientes > 0) { ?>
	  <input class="form-control" id="busca" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Fecha</th>
        <th>Cliente</th>
        <th style="text-align: center">Valor</th>
        <th style="text-align: center">Estado</th>
        <th style="text-align: center">Factura</th>
        <th style="text-align: center">Acciones</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php foreach ($pendientes_data as $row_pendientes) { 
			// Obtener estado y verificar factura para validar acciones
			$order_status = $wc_orders->getOrderStatus($row_pendientes['ID']);
			$has_invoice = $wc_orders->hasInvoice($row_pendientes['ID']);
			$can_edit = !$has_invoice; // Solo editar si no tiene factura
			$can_invoice = ($order_status === 'wc-processing' || $order_status === 'wc-on-hold') || ($order_status === 'wc-completed' && !$has_invoice);
		?>
      <tr>
        <td style="text-align: center"><?php echo $row_pendientes['ID']; ?></td>
        <td style="text-align: center"><?php echo $row_pendientes['post_date']; ?></td>
        <td style="text-align: left"><?php echo strtoupper($row_pendientes['nombre1']." ".$row_pendientes['nombre2']); ?></td>
        <td style="text-align: right"><?php echo number_format($row_pendientes['valor']); ?></td>
        <td style="text-align: center">
          <?php 
          // Mostrar badge de estado
          $status_text = '';
          $status_class = '';
          switch($order_status) {
            case 'wc-processing':
              $status_text = 'Procesando';
              $status_class = 'badge-primary';
              break;
            case 'wc-completed':
              $status_text = 'Completado';
              $status_class = 'badge-success';
              break;
            case 'wc-on-hold':
              $status_text = 'En Espera';
              $status_class = 'badge-warning';
              break;
            case 'wc-pending':
              $status_text = 'Pendiente';
              $status_class = 'badge-secondary';
              break;
            case 'wc-cancelled':
              $status_text = 'Cancelado';
              $status_class = 'badge-danger';
              break;
            default:
              $status_text = 'Desconocido';
              $status_class = 'badge-light';
          }
          ?>
          <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
        </td>
        <td style="text-align: center">
          <?php echo $has_invoice ? '<span class="badge badge-success"><i class="fas fa-check"></i> SI</span>' : '<span class="badge badge-warning"><i class="fas fa-clock"></i> NO</span>'; ?>
        </td>
        <td style="text-align: center">
          <div class="btn-group" role="group">
            <!-- Botón Editar -->
            <?php if ($can_edit) { ?>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="editOrder(<?php echo $row_pendientes['ID']; ?>)" title="Editar Pedido">
                <i class="fas fa-edit"></i>
              </button>
            <?php } else { ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="No se puede editar (ya facturado)">
                <i class="fas fa-edit"></i>
              </button>
            <?php } ?>
            
            <!-- Botón Ver Detalle -->
            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewOrderDetails(<?php echo $row_pendientes['ID']; ?>)" title="Ver Detalle">
              <i class="fas fa-eye"></i>
            </button>
            
            <!-- Botón Facturar -->
            <?php if ($can_invoice) { ?>
              <button type="button" class="btn btn-sm btn-outline-success" onclick="invoiceOrder(<?php echo $row_pendientes['ID']; ?>)" title="Facturar Pedido">
                <i class="fas fa-file-invoice"></i>
              </button>
            <?php } else { ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="<?php echo $has_invoice ? 'Ya facturado' : 'Estado no válido para facturar'; ?>">
                <i class="fas fa-file-invoice"></i>
              </button>
            <?php } ?>
          </div>
        </td>
      </tr>
    	<?php } // end foreach ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra pedidos pendientes para facturar.</h3>
	  <?php } ?>
	</div>
  <div class="tab-pane container <?php echo $acti2; ?>" id="terminada">
	<br />
		
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">Ultimos <?php echo $dias; ?> dias </a>
    <div class="dropdown-menu">
      <a class="dropdown-item" href="pedidos.php?df=30">30</a>
      <a class="dropdown-item" href="pedidos.php?df=60">60</a>
      <a class="dropdown-item" href="pedidos.php?df=90">90</a>
    </div>
		<?php if ($totalRows_pendientesf > 0) { // Show the record if the recordset is not empty ?>
	  <input class="form-control" id="buscac" type="text" placeholder="Busqueda..">
  <table class="table table-hover">
    <thead>
      <tr>
        <th style="text-align: center">Código</th>
        <th style="text-align: center">Fecha</th>
        <th>Cliente</th>
        <th style="text-align: center">Valor</th>
        <th style="text-align: center">Factura</th>
        <th style="text-align: center">Acciones</th>
      </tr>
    </thead>
    <tbody id="dondec">
		<?php foreach ($pendientesf_data as $row_pendientesf) { 
			// Pedidos facturados - solo permitir ver detalle
		?>
      <tr>
        <td style="text-align: center"><?php echo $row_pendientesf['ID']; ?></td>
        <td style="text-align: center"><?php echo $row_pendientesf['post_date']; ?></td>
        <td style="text-align: left"><?php echo strtoupper($row_pendientesf['nombre1']." ".$row_pendientesf['nombre2']); ?></td>
        <td style="text-align: right"><?php echo number_format($row_pendientesf['valor']); ?></td>
        <td style="text-align: center">
          <span class="badge badge-success"><i class="fas fa-check"></i> Facturado</span>
        </td>
        <td style="text-align: center">
          <div class="btn-group" role="group">
            <!-- Botón Editar - Deshabilitado para facturados -->
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="No se puede editar (ya facturado)">
              <i class="fas fa-edit"></i>
            </button>
            
            <!-- Botón Ver Detalle -->
            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewOrderDetails(<?php echo $row_pendientesf['ID']; ?>)" title="Ver Detalle">
              <i class="fas fa-eye"></i>
            </button>
            
            <!-- Botón Facturar - Deshabilitado para facturados -->
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Ya facturado">
              <i class="fas fa-file-invoice"></i>
            </button>
          </div>
        </td>
      </tr>
    	<?php } // end foreach ?>
    </tbody>
  </table>
    	<?php } else { ?>
		      	<h4 class="text-center mb-4">El sistema no encuentra pedidos facturados en el periodo seleccionado.</h3>
	  <?php } ?>	  
	</div>
  <div class="tab-pane container fade" id="menu2">...</div>
</div>
</div>
<!-- Modal para Ver Detalles del Pedido -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailsModalLabel">
          <i class="fas fa-eye"></i> Detalles del Pedido #<span id="modal-order-id"></span>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeOrderModal()">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="order-details-content">
          <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Cargando detalles del pedido...</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeOrderModal()">
          <i class="fas fa-times"></i> Cerrar
        </button>
        <button type="button" class="btn btn-primary" id="btn-print-order" onclick="printOrderDetails()">
          <i class="fas fa-print"></i> Imprimir
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Función para editar pedido (placeholder por ahora)
function editOrder(orderId) {
    alert('Función de edición en desarrollo. Pedido ID: ' + orderId);
    // TODO: Implementar redirección a página de edición
    // window.location.href = 'edit_order.php?id=' + orderId;
}

// Función para cerrar el modal
function closeOrderModal() {
    $('#orderDetailsModal').modal('hide');
}

// Eventos para el modal
$(document).ready(function() {
    // Asegurar que el modal se cierre correctamente
    $('#orderDetailsModal').on('hidden.bs.modal', function () {
        // Limpiar contenido cuando se cierre
        $('#order-details-content').html(`
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p class="mt-2">Cargando detalles del pedido...</p>
            </div>
        `);
    });
    
    // Cerrar modal con tecla Escape
    $(document).keyup(function(e) {
        if (e.keyCode === 27) { // Escape key
            closeOrderModal();
        }
    });
    
    // Cerrar modal al hacer clic fuera de él
    $('#orderDetailsModal').on('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });
});

// Función para ver detalles del pedido en modal
function viewOrderDetails(orderId) {
    $('#orderDetailsModal').modal('show');
    $('#modal-order-id').text(orderId);
    
    // Mostrar loading
    $('#order-details-content').html(`
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Cargando detalles del pedido...</p>
        </div>
    `);
    
    // Cargar detalles via AJAX
    $.ajax({
        url: 'get_order_details.php',
        method: 'POST',
        data: { order_id: orderId },
        dataType: 'json',
        timeout: 10000, // 10 segundos timeout
        success: function(response) {
            console.log('AJAX Success:', response);
            if (response.success) {
                displayOrderDetails(response.data);
            } else {
                $('#order-details-content').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error: ${response.message || 'No se pudieron cargar los detalles'}
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', {xhr: xhr, status: status, error: error});
            console.log('Response Text:', xhr.responseText);
            
            let errorMessage = 'Error de conexión desconocido';
            
            if (status === 'timeout') {
                errorMessage = 'Timeout: La petición tardó demasiado';
            } else if (status === 'error') {
                errorMessage = 'Error del servidor: ' + (xhr.status || 'Sin código de estado');
            } else if (status === 'parsererror') {
                errorMessage = 'Error parsing JSON. Respuesta del servidor: ' + xhr.responseText.substring(0, 200);
            }
            
            $('#order-details-content').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error de conexión:</strong><br>
                    Status: ${status}<br>
                    Error: ${error}<br>
                    Código HTTP: ${xhr.status}<br>
                    Mensaje: ${errorMessage}<br>
                    <small class="text-muted">Respuesta: ${xhr.responseText.substring(0, 100)}...</small>
                </div>
            `);
        }
    });
}

// Funciones auxiliares para formatear información del cliente
function buildFullAddress(orderData) {
    let address = orderData.billing_address_1 || '';
    if (orderData.billing_address_2) {
        address += (address ? ', ' : '') + orderData.billing_address_2;
    }
    if (orderData.billing_barrio) {
        address += (address ? ', ' : '') + orderData.billing_barrio;
    }
    return address || 'N/A';
}

function buildLocationString(orderData) {
    let location = '';
    if (orderData.billing_city) {
        location = orderData.billing_city;
    }
    if (orderData.billing_state) {
        location += (location ? ', ' : '') + convertStateCode(orderData.billing_state);
    }
    if (orderData.billing_country) {
        const country = orderData.billing_country === 'CO' ? 'Colombia' : orderData.billing_country;
        location += (location ? ', ' : '') + country;
    }
    return location || 'N/A';
}

// Generar mapeo de estados desde datos centralizados
const colombiaStates = <?php 
$states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
if (file_exists($states_file)) {
    $colombia_states = include($states_file);
    echo json_encode($colombia_states);
} else {
    echo '{}';
}
?>;

function convertStateCode(stateCode) {
    // Usar datos centralizados del plugin de Colombia
    return colombiaStates[stateCode] || stateCode;
}

function getStatusText(status) {
    const statusMap = {
        'wc-processing': 'Procesando',
        'wc-completed': 'Completado',
        'wc-on-hold': 'En Espera',
        'wc-pending': 'Pendiente',
        'wc-cancelled': 'Cancelado',
        'wc-refunded': 'Reembolsado',
        'wc-failed': 'Fallido'
    };
    
    return statusMap[status] || status;
}

// Función para mostrar detalles del pedido en el modal
function displayOrderDetails(orderData) {
    console.log('Order Data received:', orderData);
    console.log('Post Status:', orderData.post_status);
    console.log('Has Invoice:', orderData.has_invoice);
    
    // Procesar estado del pedido
    const status = orderData.post_status || 'wc-processing';
    const statusBadge = getStatusBadge(status);
    
    // Procesar estado de facturación
    const hasInvoice = orderData.has_invoice === true || orderData.has_invoice === 1 || orderData.has_invoice === '1' || orderData.has_invoice === 'true';
    const invoiceBadge = hasInvoice ? 
        '<span class="badge badge-success"><i class="fas fa-check"></i> Facturado</span>' : 
        '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pendiente</span>';
        
    console.log('Final Status:', status);
    console.log('Final HasInvoice:', hasInvoice);
    console.log('Status Badge HTML:', statusBadge);
    console.log('Invoice Badge HTML:', invoiceBadge);
    
    let itemsHtml = '';
    if (orderData.items && orderData.items.length > 0) {
        orderData.items.forEach(item => {
            itemsHtml += `
                <tr>
                    <td>${item.product_qty || 1}</td>
                    <td>
                        ${item.sku ? `<small class="text-muted">SKU: ${item.sku}</small><br>` : ''}
                        ${item.order_item_name}
                    </td>
                    <td class="text-right">$${parseFloat(item.line_total || 0).toLocaleString('es-CO')}</td>
                </tr>
            `;
        });
    }
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-info-circle"></i> Información del Pedido</h6>
                <table class="table table-sm">
                    <tr><td><strong>Estado:</strong></td><td>${statusBadge || '<span class="badge badge-primary">Procesando</span>'}</td></tr>
                    <tr><td><strong>Fecha:</strong></td><td>${orderData.post_date || 'N/A'}</td></tr>
                    <tr><td><strong>Método de Pago:</strong></td><td>${orderData.payment_method_title || orderData.payment_method || 'N/A'}</td></tr>
                    <tr><td><strong>Facturación:</strong></td><td>${invoiceBadge || '<span class="badge badge-warning">Pendiente</span>'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-user"></i> Información del Cliente</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nombre:</strong></td><td>${orderData.billing_first_name} ${orderData.billing_last_name}</td></tr>
                    <tr><td><strong>Email:</strong></td><td>${orderData.billing_email || 'N/A'}</td></tr>
                    <tr><td><strong>Teléfono:</strong></td><td>${orderData.billing_phone || 'N/A'}</td></tr>
                    <tr><td><strong>Dirección:</strong></td><td>${buildFullAddress(orderData)}</td></tr>
                    <tr><td><strong>Ciudad:</strong></td><td>${buildLocationString(orderData)}</td></tr>
                </table>
            </div>
        </div>
        
        <hr>
        
        <h6><i class="fas fa-shopping-cart"></i> Productos del Pedido</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Cant.</th>
                        <th>Producto</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
                <tfoot>
                    <tr class="table-info">
                        <th colspan="2">Total del Pedido</th>
                        <th class="text-right">$${parseFloat(orderData.total || 0).toLocaleString('es-CO')}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
    
    $('#order-details-content').html(html);
}

// Función para obtener badge de estado
function getStatusBadge(status) {
    const statusText = getStatusText(status);
    const statusMap = {
        'wc-processing': `<span class="badge badge-primary"><i class="fas fa-cog"></i> ${statusText}</span>`,
        'wc-completed': `<span class="badge badge-success"><i class="fas fa-check"></i> ${statusText}</span>`,
        'wc-on-hold': `<span class="badge badge-warning"><i class="fas fa-pause"></i> ${statusText}</span>`,
        'wc-cancelled': `<span class="badge badge-danger"><i class="fas fa-times"></i> ${statusText}</span>`,
        'wc-pending': `<span class="badge badge-secondary"><i class="fas fa-clock"></i> ${statusText}</span>`,
        'wc-refunded': `<span class="badge badge-info"><i class="fas fa-undo"></i> ${statusText}</span>`,
        'wc-failed': `<span class="badge badge-danger"><i class="fas fa-exclamation"></i> ${statusText}</span>`
    };
    return statusMap[status] || `<span class="badge badge-light"><i class="fas fa-question"></i> ${statusText || status}</span>`;
}

// Función para facturar pedido
function invoiceOrder(orderId) {
    if (confirm('¿Está seguro de que desea facturar este pedido?')) {
        // Crear formulario dinámico para enviar a detventafact.php
        const form = $('<form>', {
            'method': 'POST',
            'action': 'detventafact.php'
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'id_ventas',
            'value': orderId
        }));
        
        $('body').append(form);
        form.submit();
    }
}

// Función para imprimir detalles del pedido
function printOrderDetails() {
    const printContent = $('#order-details-content').html();
    const orderId = $('#modal-order-id').text();
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Detalles del Pedido #${orderId}</title>
                <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                <style>
                    @media print {
                        .no-print { display: none; }
                        body { font-size: 12px; }
                    }
                </style>
            </head>
            <body>
                <div class="container-fluid">
                    <h3 class="text-center mb-4">Detalles del Pedido #${orderId}</h3>
                    ${printContent}
                </div>
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}
</script>

	<?php include("parts/foot.php"); ?>

<script>
$(document).ready(function(){
  $("#busca").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#donde tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
$(document).ready(function(){
  $("#buscac").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dondec tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
</script>

</body>
</html>
<?php
?>