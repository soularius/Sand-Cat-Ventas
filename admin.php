<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar clases específicas
require_once('class/woocommerce_orders.php');
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
$colname_usuario = '';
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM usuarios WHERE documento = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = '';
$ellogin = isset($row_usuario['documento']) ? $row_usuario['documento'] : '';
$id_usuarios = isset($row_usuario['id_usuarios']) ? $row_usuario['id_usuarios'] : 0;
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
$hoy = date("Y-m-d");
$inifact = date("Y-m-d",strtotime ( '+1 day' , strtotime ( $hoy ) ) );
if(isset($diasfin)) {
	$dias = $diasfin;
	$finfact = date("Y-m-d",strtotime ( '-'.$dias.' day' , strtotime ( $hoy ) ) );
} else {
	$dias = 30;	
	$finfact = date("Y-m-d",strtotime ( '-30 day' , strtotime ( $hoy ) ) );
}

if(isset($_POST['id_ventas']) && isset($_POST['ingfact'])) {
	$_POST['id_ventas'];
	$_POST['num'];
	$id_ventas = $_POST['id_ventas'];
	$num = $_POST['num'];
	$query = "UPDATE ventas SET factura = '$num' WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
}
if(isset($_POST['id_ventas']) && isset($_POST['cancelar'])) {
	$_POST['id_ventas'];
	$id_ventas = $_POST['id_ventas'];
	$query = "DELETE FROM ventas WHERE id_ventas = '$id_ventas'";
	mysqli_query($sandycat, $query);
}

// Procesar facturación de órdenes WooCommerce
if(isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) {
	$id_ventas = $_POST['id_ventas'];
	$num_factura = $_POST['num'];
	
	// Insertar en tabla facturas
	$query = "INSERT INTO facturas (id_order, factura, estado) VALUES ('$id_ventas', '$num_factura', 'a')";
	mysqli_query($sandycat, $query);
	
	// Actualizar estado en WooCommerce (opcional - ya está completada)
	// $query = "UPDATE miau_wc_orders SET status = 'wc-completed' WHERE id = '$id_ventas'";
	// mysqli_query($miau, $query);
	
	// Redireccionar para evitar reenvío del formulario
	header("Location: admin.php?facturada=1&orden=" . $id_ventas);
	exit;
}

// API para obtener siguiente número de factura
if(isset($_GET['get_next_factura'])) {
	$query = "SELECT COUNT(id_facturas) AS numero FROM facturas";
	$result = mysqli_query($sandycat, $query);
	$row = mysqli_fetch_assoc($result);
	$numero = $row['numero'] + 1;
	
	header('Content-Type: application/json');
	echo json_encode(['numero' => $numero]);
	exit;
}


// Inicializar clase de órdenes WooCommerce
$wooOrders = new WooCommerceOrders();

// Verificar estructura de la tabla (solo para debug)
if (isset($_GET['debug'])) {
    echo "<pre>";
    if ($_GET['debug'] == 'table') {
        echo "=== ESTRUCTURA DE TABLAS ===\n";
        print_r($wooOrders->checkTableStructure());
    } elseif ($_GET['debug'] == 'simple') {
        echo "=== PRUEBA DE ÓRDENES SIMPLES ===\n";
        print_r($wooOrders->getSimpleOrders(3));
    } elseif ($_GET['debug'] == 'queries') {
        echo "=== CONSULTAS SQL PARA PROBAR MANUALMENTE ===\n";
        $queries = $wooOrders->showQueries();
        foreach ($queries as $name => $query) {
            echo "\n--- $name ---\n";
            echo $query . "\n";
        }
    } elseif ($_GET['debug'] == 'all') {
        echo "=== ESTRUCTURA DE TABLAS ===\n";
        print_r($wooOrders->checkTableStructure());
        echo "\n=== PRUEBA DE ÓRDENES SIMPLES ===\n";
        print_r($wooOrders->getSimpleOrders(3));
        echo "\n=== CONSULTAS SQL ===\n";
        $queries = $wooOrders->showQueries();
        foreach ($queries as $name => $query) {
            echo "\n--- $name ---\n";
            echo $query . "\n";
        }
    }
    echo "</pre>";
    exit;
}

// Obtener parámetros de búsqueda y filtros
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Obtener todos los estados disponibles
$todos_estados = $wooOrders->getAllOrderStatuses();

// DEBUG: Mostrar información de estados
if (isset($_GET['debug_orders'])) {
    echo "<pre>=== TODOS LOS ESTADOS EN LA BASE DE DATOS ===\n";
    foreach ($todos_estados as $estado_info) {
        echo "Estado: '{$estado_info['estado']}' - Cantidad: {$estado_info['cantidad']} - Etiqueta: '{$estado_info['etiqueta']}'\n";
    }
    echo "</pre>";
}

// Obtener órdenes por cada estado
$ordenes_por_estado = [];
foreach ($todos_estados as $estado_info) {
    $estado = $estado_info['estado'];
    
    if (!empty($search_term)) {
        $ordenes = $wooOrders->searchOrders($search_term, $estado, 50);
    } else {
        $ordenes = $wooOrders->getAllOrders($estado, 50);
    }
    
    $ordenes_por_estado[$estado] = [
        'ordenes' => $ordenes,
        'cantidad' => count($ordenes),
        'etiqueta' => $estado_info['etiqueta'],
        'total_db' => $estado_info['cantidad']
    ];
}

// DEBUG: Mostrar clasificación por estado
if (isset($_GET['debug_orders'])) {
    echo "<pre>CLASIFICACIÓN POR ESTADO:\n";
    foreach ($ordenes_por_estado as $estado => $info) {
        echo "Estado '$estado': {$info['cantidad']} órdenes obtenidas de {$info['total_db']} en DB\n";
    }
    echo "</pre>";
}

// Obtener estadísticas
$estadisticas = $wooOrders->getOrderStats($dias);

// Calcular totales para estadísticas rápidas
$total_ordenes = array_sum(array_column($ordenes_por_estado, 'cantidad'));
$total_ordenes_db = array_sum(array_column($todos_estados, 'cantidad'));


if(isset($_POST['iniciando']) && $_POST['iniciando'] = "si") {
	$_POST['doc_cliente'];
	$_POST['nom_cliente'];
	$doc_cliente = $_POST['doc_cliente'];
	$nom_cliente = strtoupper($_POST['nom_cliente']);
	$estado = "i";
	$query = "INSERT INTO ventas (id_usuarios, doc_cliente, nom_cliente, estado, fecha) VALUES ('$id_usuarios', '$doc_cliente', '$nom_cliente', '$estado', '$hoy')";
	mysqli_query($sandycat, $query);

	$id_creado = mysqli_insert_id($sandycat);
  	$elnuevo = "v_producto.php?i=$id_creado";
    header("Location: $elnuevo");
}

?>
<?php include("parts/header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("parts/men.php"); ?><br />
<br />
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2><i class="fas fa-shopping-cart me-2"></i>Órdenes WooCommerce</h2>
      <small class="text-muted">
        <i class="fas fa-eye me-1"></i>Sistema de consulta - Solo lectura
      </small>
    </div>
    <div class="d-flex gap-2">
      <a href="admin.php" class="btn btn-secondary">
        <i class="fas fa-sync-alt me-1"></i>Actualizar
      </a>
    </div>
  </div>

  <!-- Buscador y filtros -->
  <div class="row mb-4">
    <div class="col-md-8">
      <form method="GET" action="admin.php" class="d-flex">
        <input type="text" class="form-control me-2" name="search" 
               placeholder="Buscar por ID, cliente, email o teléfono..." 
               value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i>
        </button>
      </form>
    </div>
    <div class="col-md-4">
      <!-- Estadísticas rápidas -->
      <div class="d-flex justify-content-end">
        <small class="text-muted">
          Total órdenes: <strong><?php echo $total_ordenes; ?></strong> de <strong><?php echo $total_ordenes_db; ?></strong>
          <br><em>(<?php echo count($todos_estados); ?> estados diferentes)</em>
        </small>
      </div>
    </div>
  </div>

  <!-- Información de búsqueda -->
  <?php if (!empty($search_term)): ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    Mostrando resultados para: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
    <a href="admin.php" class="btn btn-sm btn-outline-primary ms-2">
      <i class="fas fa-times me-1"></i>Limpiar búsqueda
    </a>
  </div>
  <?php endif; ?>

  <!-- Mensaje de éxito de facturación -->
  <?php if (isset($_GET['facturada']) && $_GET['facturada'] == '1'): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>
    <strong>¡Factura creada exitosamente!</strong> La orden #<?php echo htmlspecialchars($_GET['orden']); ?> ha sido facturada.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Pestañas dinámicas por estado -->
  <ul class="nav nav-tabs" role="tablist">
    <?php foreach ($ordenes_por_estado as $estado => $info): ?>
      <?php 
        $tab_id = 'tab-' . str_replace(['wc-', '-'], ['', '_'], $estado);
        $is_active = ($estado === array_keys($ordenes_por_estado)[0]) ? 'active' : '';
        
        // Iconos por estado
        $iconos = [
          'wc-checkout-draft' => 'fas fa-edit',
          'wc-pending' => 'fas fa-clock',
          'wc-processing' => 'fas fa-cog',
          'wc-on-hold' => 'fas fa-pause',
          'wc-completed' => 'fas fa-check-circle',
          'wc-cancelled' => 'fas fa-times-circle',
          'wc-refunded' => 'fas fa-undo',
          'wc-failed' => 'fas fa-exclamation-triangle'
        ];
        $icono = $iconos[$estado] ?? 'fas fa-circle';
      ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $is_active; ?>" 
                id="<?php echo $tab_id; ?>-tab" 
                data-bs-toggle="tab" 
                data-bs-target="#<?php echo $tab_id; ?>" 
                type="button" 
                role="tab">
          <i class="<?php echo $icono; ?> me-1"></i><?php echo $info['etiqueta']; ?> (<?php echo $info['cantidad']; ?>)
        </button>
      </li>
    <?php endforeach; ?>
  </ul>
  <!-- Contenido de las pestañas dinámicas -->
  <div class="tab-content">
    <?php foreach ($ordenes_por_estado as $estado => $info): ?>
      <?php 
        $tab_id = 'tab-' . str_replace(['wc-', '-'], ['', '_'], $estado);
        $is_active = ($estado === array_keys($ordenes_por_estado)[0]) ? 'active' : '';
        
        // Color del badge según el estado
        $badge_colors = [
          'wc-checkout-draft' => 'bg-secondary',
          'wc-pending' => 'bg-warning',
          'wc-processing' => 'bg-info',
          'wc-on-hold' => 'bg-warning',
          'wc-completed' => 'bg-success',
          'wc-cancelled' => 'bg-danger',
          'wc-refunded' => 'bg-dark',
          'wc-failed' => 'bg-danger'
        ];
        $badge_color = $badge_colors[$estado] ?? 'bg-primary';
      ?>
      <div class="tab-pane container <?php echo $is_active; ?>" id="<?php echo $tab_id; ?>">
        <br />
        <?php if($info['cantidad'] > 0): ?>
          <div class="table-responsive">
            <table class="table table-hover table-striped">
              <thead class="table-dark">
                <tr>
                  <th><i class="fas fa-hashtag me-1"></i>Orden</th>
                  <th><i class="fas fa-user me-1"></i>Cliente</th>
                  <th><i class="fas fa-envelope me-1"></i>Email</th>
                  <th><i class="fas fa-phone me-1"></i>Teléfono</th>
                  <th class="text-center"><i class="fas fa-calendar me-1"></i>Fecha</th>
                  <th class="text-end"><i class="fas fa-dollar-sign me-1"></i>Total</th>
                  <th class="text-center"><i class="fas fa-tag me-1"></i>Estado</th>
                  <th class="text-center"><i class="fas fa-tag me-1"></i>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($info['ordenes'] as $orden): ?>
                  <tr>
                    <td>
                      <strong>#<?php echo $orden['order_id']; ?></strong>
                    </td>
                    <td>
                      <div class="d-flex flex-column">
                        <strong><?php echo htmlspecialchars($orden['nombre_completo']); ?></strong>
                      </div>
                    </td>
                    <td>
                      <small><?php echo htmlspecialchars($orden['email_cliente']); ?></small>
                    </td>
                    <td>
                      <small><?php echo htmlspecialchars($orden['telefono_cliente']); ?></small>
                    </td>
                    <td class="text-center">
                      <small><?php echo $orden['fecha_formateada']; ?></small>
                    </td>
                    <td class="text-end">
                      <strong>$<?php echo number_format($orden['total'], 0, ',', '.'); ?></strong>
                    </td>
                    <td class="text-center">
                      <span class="badge <?php echo $badge_color; ?>"><?php echo $orden['estado_legible']; ?></span>
                    </td>
                    <?php if ($estado === 'wc-completed'): ?>
                    <td class="text-center">
                      <?php
                      // Verificar si ya está facturada
                      $order_id = $orden['order_id'];
                      $query_factura = "SELECT factura FROM facturas WHERE id_order = '$order_id' AND estado = 'a'";
                      $result_factura = mysqli_query($sandycat, $query_factura);
                      $ya_facturada = mysqli_num_rows($result_factura) > 0;
                      
                      if ($ya_facturada):
                        $row_factura = mysqli_fetch_assoc($result_factura);
                      ?>
                        <div class="d-flex flex-column gap-1">
                          <span class="badge bg-info">
                            <i class="fas fa-check me-1"></i>Facturada #<?php echo $row_factura['factura']; ?>
                          </span>
                          <button type="button" class="btn btn-sm btn-outline-danger" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalPDF"
                                  data-order-id="<?php echo $orden['order_id']; ?>"
                                  data-factura="<?php echo $row_factura['factura']; ?>"
                                  data-cliente="<?php echo htmlspecialchars($orden['nombre_completo']); ?>"
                                  data-telefono="<?php echo htmlspecialchars($orden['telefono'] ?? 'No registrado'); ?>"
                                  data-email="<?php echo htmlspecialchars($orden['email'] ?? 'No registrado'); ?>">
                            <i class="fas fa-file-pdf me-1"></i>PDF
                          </button>
                        </div>
                      <?php else: ?>
                        <button type="button" class="btn btn-sm btn-success" 
                                data-bs-toggle="modal" 
                                data-bs-target="#modalFacturar"
                                data-order-id="<?php echo $orden['order_id']; ?>"
                                data-cliente="<?php echo htmlspecialchars($orden['nombre_completo']); ?>"
                                data-total="<?php echo $orden['total']; ?>">
                          <i class="fas fa-file-invoice me-1"></i>Facturar
                        </button>
                      <?php endif; ?>
                    </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No hay órdenes en estado "<?php echo $info['etiqueta']; ?>"</h4>
            <p class="text-muted">
              <?php if (!empty($search_term)): ?>
                No se encontraron órdenes en este estado que coincidan con tu búsqueda.
              <?php else: ?>
                No hay órdenes en este estado actualmente.
              <?php endif; ?>
            </p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal para Facturar -->
<div class="modal fade" id="modalFacturar" tabindex="-1" aria-labelledby="modalFacturarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="modalFacturarLabel">
          <i class="fas fa-file-invoice me-2"></i>Crear Factura
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="admin.php" id="formFacturar">
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Orden:</strong> #<span id="modalOrderId"></span><br>
            <strong>Cliente:</strong> <span id="modalCliente"></span><br>
            <strong>Total:</strong> $<span id="modalTotal"></span>
          </div>
          
          <div class="mb-3">
            <label for="numeroFactura" class="form-label">
              <i class="fas fa-hashtag me-1"></i>Número de Factura
            </label>
            <div class="input-group">
              <span class="input-group-text">POS</span>
              <input type="text" class="form-control" id="numeroFactura" name="num" readonly>
            </div>
            <div class="form-text">El número se genera automáticamente</div>
          </div>
          
          <input type="hidden" id="hiddenOrderId" name="id_ventas">
          <input type="hidden" name="imprimiendo" value="1">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Cancelar
          </button>
          <button type="submit" class="btn btn-success" name="ingfact">
            <i class="fas fa-file-invoice me-1"></i>Crear Factura
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para PDF de Factura -->
<div class="modal fade" id="modalPDF" tabindex="-1" aria-labelledby="modalPDFLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title text-white" id="modalPDFLabel">
          <i class="fas fa-file-pdf me-2"></i>Factura PDF
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-2"></i>
          <strong>Orden:</strong> #<span id="pdfOrderId"></span><br>
          <strong>Cliente:</strong> <span id="pdfCliente"></span><br>
          <strong>Teléfono:</strong> <span id="pdfTelefono"></span><br>
          <strong>Email:</strong> <span id="pdfEmail"></span><br>
          <strong>Factura:</strong> #<span id="pdfFactura"></span>
        </div>
        
        <div class="row">
          <div class="col-md-6">
            <div class="d-grid">
              <button type="button" class="btn btn-primary btn-lg" id="btnGenerarPDF">
                <i class="fas fa-file-pdf me-2"></i>Generar PDF
              </button>
            </div>
            <small class="text-muted mt-2 d-block">Genera un nuevo PDF de la factura</small>
          </div>
          <div class="col-md-6">
            <div class="d-grid">
              <button type="button" class="btn btn-success btn-lg" id="btnAbrirPDF">
                <i class="fas fa-external-link-alt me-2"></i>Abrir PDF
              </button>
            </div>
            <small class="text-muted mt-2 d-block">Abre el PDF en una nueva pestaña</small>
          </div>
        </div>
        
        <hr>
        
        <div class="row">
          <div class="col-md-12">
            <h6><i class="fas fa-cogs me-1"></i>Opciones adicionales:</h6>
            <div class="btn-group w-100" role="group">
              <button type="button" class="btn btn-outline-secondary" id="btnDescargarPDF">
                <i class="fas fa-download me-1"></i>Descargar
              </button>
              <button type="button" class="btn btn-outline-info" id="btnEnviarEmail">
                <i class="fas fa-envelope me-1"></i>Enviar por Email
              </button>
              <button type="button" class="btn btn-outline-warning" id="btnImprimir">
                <i class="fas fa-print me-1"></i>Imprimir
              </button>
            </div>
          </div>
        </div>
        
        <!-- Área para mostrar el PDF -->
        <div id="pdfViewer" class="mt-3" style="display: none;">
          <iframe id="pdfFrame" width="100%" height="500px" frameborder="0"></iframe>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cerrar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Manejar modal de facturación
document.addEventListener('DOMContentLoaded', function() {
    const modalFacturar = document.getElementById('modalFacturar');
    
    modalFacturar.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const orderId = button.getAttribute('data-order-id');
        const cliente = button.getAttribute('data-cliente');
        const total = button.getAttribute('data-total');
        
        // Actualizar contenido del modal
        document.getElementById('modalOrderId').textContent = orderId;
        document.getElementById('modalCliente').textContent = cliente;
        document.getElementById('modalTotal').textContent = new Intl.NumberFormat('es-CO').format(total);
        document.getElementById('hiddenOrderId').value = orderId;
        
        // Generar número de factura (simulado - en producción vendría del servidor)
        fetch('admin.php?get_next_factura=1')
            .then(response => response.json())
            .then(data => {
                document.getElementById('numeroFactura').value = data.numero;
            })
            .catch(() => {
                // Fallback: generar número basado en timestamp
                const numero = Date.now().toString().slice(-6);
                document.getElementById('numeroFactura').value = numero;
            });
    });
    
    // Manejar modal de PDF
    const modalPDF = document.getElementById('modalPDF');
    
    modalPDF.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const orderId = button.getAttribute('data-order-id');
        const cliente = button.getAttribute('data-cliente');
        const telefono = button.getAttribute('data-telefono');
        const email = button.getAttribute('data-email');
        const factura = button.getAttribute('data-factura');
        
        // Formatear número de factura con 10 dígitos
        const facturaFormateada = factura.toString().padStart(10, '0');
        
        // Actualizar contenido del modal
        document.getElementById('pdfOrderId').textContent = orderId;
        document.getElementById('pdfCliente').textContent = cliente;
        document.getElementById('pdfTelefono').textContent = telefono;
        document.getElementById('pdfEmail').textContent = email;
        document.getElementById('pdfFactura').textContent = facturaFormateada;
        
        // Ocultar viewer al abrir
        document.getElementById('pdfViewer').style.display = 'none';
        
        // Configurar botones (usar número original para URLs)
        setupPDFButtons(orderId, factura);
    });
    
    function setupPDFButtons(orderId, factura) {
        // Generar PDF
        document.getElementById('btnGenerarPDF').onclick = function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generando...';
            btn.disabled = true;
            
            // Simular generación (aquí iría la llamada real)
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showPDFInModal(orderId, factura);
            }, 2000);
        };
        
        // Abrir PDF en nueva pestaña
        document.getElementById('btnAbrirPDF').onclick = function() {
            const url = `generar_pdf.php?orden=${orderId}&factura=${factura}`;
            window.open(url, '_blank');
        };
        
        // Descargar PDF
        document.getElementById('btnDescargarPDF').onclick = function() {
            const url = `generar_pdf.php?orden=${orderId}&factura=${factura}&download=1`;
            const link = document.createElement('a');
            link.href = url;
            link.download = `factura_${factura}.pdf`;
            link.click();
        };
        
        // Enviar por email
        document.getElementById('btnEnviarEmail').onclick = function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
            btn.disabled = true;
            
            // Crear FormData para envío POST (el email se obtiene automáticamente del cliente)
            const formData = new FormData();
            formData.append('orden_id', orderId);
            formData.append('factura_id', factura);
            
            fetch('enviar_factura_email.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (data.success) {
                        alert(`Factura enviada exitosamente a: ${data.email}`);
                    } else {
                        alert('Error al enviar factura: ' + data.error);
                    }
                })
                .catch(error => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    alert('Error al enviar factura');
                });
        };
        
        // Imprimir
        document.getElementById('btnImprimir').onclick = function() {
            const url = `generar_pdf.php?orden=${orderId}&factura=${factura}&print=1`;
            const printWindow = window.open(url, '_blank');
            printWindow.onload = function() {
                printWindow.print();
            };
        };
    }
    
    function showPDFInModal(orderId, factura) {
        const viewer = document.getElementById('pdfViewer');
        const frame = document.getElementById('pdfFrame');
        
        frame.src = `generar_pdf.php?orden=${orderId}&factura=${factura}&embed=1`;
        viewer.style.display = 'block';
    }
});
</script>

<?php include("parts/foot.php"); ?>

</body>
</html>
<?php
mysqli_free_result($usuario);
?>