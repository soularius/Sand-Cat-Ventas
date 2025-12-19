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
  Utils::logError("No se pudieron obtener datos del usuario en ventas.php", 'ERROR', 'ventas.php');
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
if (isset($_GET['df']) && !empty($_GET['df'])) {
  $diasfin = $_GET['df'];
  $acti1 = 'fade';
  $acti2 = 'active';
  $pes1 = '';
  $pes2 = 'active';
}
// 6. Configurar fechas para filtros
$hoy = date("Y-m-d");
$inifact = date("Y-m-d", strtotime('+1 day', strtotime($hoy)));
if (isset($diasfin)) {
  $dias = $diasfin;
  $finfact = date("Y-m-d", strtotime('-' . $dias . ' day', strtotime($hoy)));
} else {
  $dias = 30;
  $finfact = date("Y-m-d", strtotime('-30 day', strtotime($hoy)));
}

// 6.1. Configurar parámetros de paginación
$page_pendientes = isset($_GET['page_pendientes']) ? max(1, (int)$_GET['page_pendientes']) : 1;
$page_facturados = isset($_GET['page_facturados']) ? max(1, (int)$_GET['page_facturados']) : 1;
$per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 20;

// 7. Procesar acciones de pedidos usando clase WooCommerce
$carga = '';
if (isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) {
  $venta = Utils::sanitizeInput($_POST['id_ventas']);
  $factu = Utils::sanitizeInput($_POST['num']);

  // Usar método de la clase para completar pedido
  if ($wc_orders->completeOrder($venta, $factu)) {
    Utils::logError("Pedido completado: ID=$venta, Factura=$factu", 'INFO', 'ventas.php');
  } else {
    Utils::logError("Error completando pedido: ID=$venta", 'ERROR', 'ventas.php');
  }
}
if (isset($_POST['id_ventas']) && isset($_POST['cancela'])) {
  $venta = Utils::sanitizeInput($_POST['id_ventas']);
  $factu = Utils::sanitizeInput($_POST['num']);

  // Usar método de la clase para cancelar pedido
  if ($wc_orders->cancelOrder($venta)) {
    Utils::logError("Pedido cancelado: ID=$venta", 'INFO', 'ventas.php');
  } else {
    Utils::logError("Error cancelando pedido: ID=$venta", 'ERROR', 'ventas.php');
  }
}
if (isset($_POST['fin_pedido'])) {
  $venta = Utils::sanitizeInput($_POST['fin_pedido']);

  // Usar método de la clase para procesar pedido
  if ($wc_orders->processOrder($venta)) {
    Utils::logError("Pedido procesado: ID=$venta", 'INFO', 'ventas.php');
  } else {
    Utils::logError("Error procesando pedido: ID=$venta", 'ERROR', 'ventas.php');
  }
}


// 8. Obtener pedidos pendientes usando clase WooCommerce con paginación
// Incluye pedidos en processing y on-hold con método de pago cheque
$pendientes_result = $wc_orders->getPendingOrders($page_pendientes, $per_page);
$pendientes_data = $pendientes_result['data'] ?? [];
$pendientes_pagination = $pendientes_result['pagination'] ?? [];
$totalRows_pendientes = $pendientes_pagination['total_records'] ?? count($pendientes_data);
$row_pendientes = $totalRows_pendientes > 0 ? $pendientes_data[0] : null;

// 9. Obtener pedidos facturados usando clase WooCommerce con paginación
$facturados_result = $wc_orders->getInvoicedOrders($finfact, $inifact, $page_facturados, $per_page);
$pendientesf_data = $facturados_result['data'] ?? [];
$facturados_pagination = $facturados_result['pagination'] ?? [];
$totalRows_pendientesf = $facturados_pagination['total_records'] ?? count($pendientesf_data);
$row_pendientesf = $totalRows_pendientesf > 0 ? $pendientesf_data[0] : null;

// 3. DESPUÉS: Cargar presentación
include("parts/header.php");
?>

<body style="padding-top: 70px" class="product-selector-container">
  <div class="container">
    <?php include("parts/menf.php"); ?><br />
    <br />
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2><i class="fas fa-shopping-cart me-2"></i>Ventas WooCommerce</h2>
      <div class="d-flex gap-2">
        <button class="btn btn-secondary btn-custom" onclick="location.reload()">
          <i class="fas fa-sync-alt me-1"></i>Actualizar
        </button>
      </div>
    </div>

    <!-- Nav tabs modernos -->
    <ul class="nav nav-pills nav-fill mb-4" id="orderTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $pes1; ?> d-flex align-items-center justify-content-center btn btn-success btn-custom text-white <?php echo $pes1 === 'active' ? '' : 'opacity-50'; ?>"
          id="pendiente-tab"
          data-toggle="pill"
          href="#pendiente"
          role="tab"
          aria-controls="pendiente"
          aria-selected="<?php echo $pes1 === 'active' ? 'true' : 'false'; ?>">
          <i class="fas fa-clock me-2"></i>
          <span>Pendientes</span>
          <?php if ($totalRows_pendientes > 0) { ?>
            <span class="badge bg-primary bg-custom ms-2"><?php echo $totalRows_pendientes; ?></span>
          <?php } ?>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $pes2; ?> d-flex align-items-center justify-content-center btn btn-primary btn-custom text-white <?php echo $pes2 === 'active' ? '' : 'opacity-50'; ?>"
          id="terminada-tab"
          data-toggle="pill"
          href="#terminada"
          role="tab"
          aria-controls="terminada"
          aria-selected="<?php echo $pes2 === 'active' ? 'true' : 'false'; ?>">
          <i class="fas fa-check-circle me-2"></i>
          <span>Facturados</span>
          <?php if ($totalRows_pendientesf > 0) { ?>
            <span class="badge bg-success bg-custom ms-2"><?php echo $totalRows_pendientesf; ?></span>
          <?php } ?>
        </a>
      </li>
    </ul>

    <!-- Información de resultados -->
    <div class="alert alert-success d-flex justify-content-between align-items-center mb-4">
      <div>
        <i class="fas fa-info-circle me-2"></i>
        Total de pedidos: <strong><?php echo ($totalRows_pendientes + $totalRows_pendientesf); ?></strong>
      </div>
      <div class="d-flex align-items-center gap-3 form-group">
        <label for="per_page" class="form-label mb-0 text-primary">Por página:</label>
        <select id="per_page" class="form-control form-select form-select-sm pe-4" style="width: auto;" onchange="changePerPage(this.value)">
          <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
          <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
          <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
          <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
        </select>
      </div>
    </div>

    <?php if (isset($_POST['id_ventas']) && isset($_POST['imprimiendo'])) { ?>
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
    <!-- Tab content -->
    <div class="tab-content" id="orderTabsContent">
      <div class="tab-pane fade <?php echo $acti1; ?> <?php echo $acti1 === 'active' ? 'show' : ''; ?>"
        id="pendiente"
        role="tabpanel"
        aria-labelledby="pendiente-tab">
        <div class="search-panel">
          <?php if ($totalRows_pendientes > 0) { ?>
            <div class="panel-body">
              <div class="row mb-4">
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" class="form-control" id="busca"
                      placeholder="Buscar por código, cliente o fecha...">
                    <button type="button" class="btn btn-success btn-custom">
                      <i class="fas fa-search"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="d-flex justify-content-end">
                    <span class="badge bg-primary bg-custom px-3 py-2 text-white">
                      <i class="fas fa-list me-1"></i><?php echo $totalRows_pendientes; ?> pedidos
                    </span>
                  </div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover table-striped">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center"><i class="fas fa-hashtag me-1"></i>Código</th>
                      <th class="text-center"><i class="fas fa-calendar me-1"></i>Fecha</th>
                      <th><i class="fas fa-user me-1"></i>Cliente</th>
                      <th class="text-center"><i class="fas fa-dollar-sign me-1"></i>Valor</th>
                      <th class="text-center"><i class="fas fa-info-circle me-1"></i>Estado</th>
                      <th class="text-center"><i class="fas fa-file-invoice me-1"></i>Factura</th>
                      <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
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
                        <td style="text-align: left"><?php echo strtoupper($row_pendientes['nombre1'] . " " . $row_pendientes['nombre2']); ?></td>
                        <td style="text-align: right"><?php echo number_format($row_pendientes['valor']); ?></td>
                        <td class="text-center">
                          <?php
                          // Mostrar badge de estado con estilo moderno
                          $status_text = '';
                          $status_class = '';
                          $status_icon = '';
                          switch ($order_status) {
                            case 'wc-processing':
                              $status_text = 'Procesando';
                              $status_class = 'bg-primary';
                              $status_icon = 'fas fa-spinner';
                              break;
                            case 'wc-completed':
                              $status_text = 'Completado';
                              $status_class = 'bg-success';
                              $status_icon = 'fas fa-check-circle';
                              break;
                            case 'wc-on-hold':
                              $status_text = 'En Espera';
                              $status_class = 'bg-warning';
                              $status_icon = 'fas fa-pause-circle';
                              break;
                            case 'wc-pending':
                              $status_text = 'Pendiente';
                              $status_class = 'bg-secondary';
                              $status_icon = 'fas fa-clock';
                              break;
                            case 'wc-cancelled':
                              $status_text = 'Cancelado';
                              $status_class = 'bg-danger';
                              $status_icon = 'fas fa-times-circle';
                              break;
                            default:
                              $status_text = 'Desconocido';
                              $status_class = 'bg-light';
                              $status_icon = 'fas fa-question-circle';
                          }
                          ?>
                          <span class="badge <?php echo $status_class; ?> bg-custom px-3 py-2">
                            <i class="<?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                          </span>
                        </td>
                        <td class="text-center">
                          <?php if ($has_invoice): ?>
                            <span class="badge bg-success bg-custom px-3 py-2">
                              <i class="fas fa-check-circle me-1"></i>Facturado
                            </span>
                          <?php else: ?>
                            <span class="badge bg-warning bg-custom px-3 py-2">
                              <i class="fas fa-clock me-1"></i>Pendiente
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center">
                          <div class="btn-group" role="group">
                            <!-- Botón Editar -->
                            <?php if ($can_edit) { ?>
                              <button type="button" class="btn btn-sm btn-danger btn-custom px-3" onclick="editOrder(<?php echo $row_pendientes['ID']; ?>)" title="Editar Pedido">
                                <i class="fas fa-edit"></i>
                              </button>
                            <?php } else { ?>
                              <button type="button" class="btn btn-sm btn-danger btn-custom px-3" disabled title="No se puede editar (ya facturado)">
                                <i class="fas fa-edit"></i>
                              </button>
                            <?php } ?>

                            <!-- Botón Ver Detalle -->
                            <button type="button" class="btn btn-sm btn-success btn-custom px-3" onclick="viewOrderDetails(<?php echo $row_pendientes['ID']; ?>)" title="Ver Detalle">
                              <i class="fas fa-eye"></i>
                            </button>

                            <!-- Botón Facturar -->
                            <?php if ($has_invoice) { ?>
                              <button type="button" class="btn btn-sm btn-warning btn-custom px-3" disabled title="<?php echo $has_invoice ? 'Ya facturado' : 'Estado no válido para facturar'; ?>">
                                <i class="fas fa-file-invoice"></i>
                              </button>
                            <?php } else { ?>
                              <button type="button" class="btn btn-sm btn-warning btn-custom px-3" onclick="invoiceOrder(<?php echo $row_pendientes['ID']; ?>)" title="Facturar Pedido">
                                <i class="fas fa-file-invoice"></i>
                              </button>
                            <?php } ?>
                          </div>
                        </td>
                      </tr>
                    <?php } // end foreach 
                    ?>
                  </tbody>
                </table>
              </div>
              
              <!-- Paginación Pendientes -->
              <?php if (!empty($pendientes_pagination) && $pendientes_pagination['total_pages'] > 1): ?>
              <nav aria-label="Paginación pedidos pendientes" class="mt-4">
                <ul class="pagination justify-content-center">
                  <!-- Botón Anterior -->
                  <?php if ($pendientes_pagination['has_previous']): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_pendientes=<?php echo $pendientes_pagination['previous_page']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">
                        <i class="fas fa-chevron-left me-1"></i>Anterior
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">
                        <i class="fas fa-chevron-left me-1"></i>Anterior
                      </span>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Números de página -->
                  <?php
                  $start_page = max(1, $pendientes_pagination['current_page'] - 2);
                  $end_page = min($pendientes_pagination['total_pages'], $pendientes_pagination['current_page'] + 2);
                  
                  // Mostrar primera página si no está en el rango
                  if ($start_page > 1): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_pendientes=1&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                      <li class="page-item disabled">
                        <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                      </li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <!-- Páginas en el rango -->
                  <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo ($i == $pendientes_pagination['current_page']) ? 'active' : ''; ?>">
                      <a class="page-link text-white btn btn-sm btn-custom px-3 py-2 mx-1 <?php echo ($i == $pendientes_pagination['current_page']) ? 'btn-success' : 'btn-danger'; ?>" href="?page_pendientes=<?php echo $i; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <!-- Mostrar última página si no está en el rango -->
                  <?php if ($end_page < $pendientes_pagination['total_pages']): ?>
                    <?php if ($end_page < $pendientes_pagination['total_pages'] - 1): ?>
                      <li class="page-item disabled">
                        <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                      </li>
                    <?php endif; ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_pendientes=<?php echo $pendientes_pagination['total_pages']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>"><?php echo $pendientes_pagination['total_pages']; ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Botón Siguiente -->
                  <?php if ($pendientes_pagination['has_next']): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_pendientes=<?php echo $pendientes_pagination['next_page']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">
                        Siguiente<i class="fas fa-chevron-right ms-1"></i>
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 opacity-50 mx-1">
                        Siguiente<i class="fas fa-chevron-right ms-1"></i>
                      </span>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
              
              <!-- Información adicional de paginación -->
              <div class="text-center text-muted mt-2">
                <small>
                  Mostrando <?php echo $pendientes_pagination['start_record']; ?>-<?php echo $pendientes_pagination['end_record']; ?> de <?php echo $pendientes_pagination['total_records']; ?> pedidos
                </small>
              </div>
              <?php endif; ?>
            </div>
          <?php } else { ?>
            <div class="panel-body">
              <h4 class="text-center mb-4">El sistema no encuentra pedidos pendientes para facturar.</h4>
            </div>
          <?php } ?>
        </div>
      </div>
      <div class="tab-pane fade <?php echo $acti2; ?> <?php echo $acti2 === 'active' ? 'show' : ''; ?>"
        id="terminada"
        role="tabpanel"
        aria-labelledby="terminada-tab">
        <div class="search-panel">
          <div class="panel-header bg-success bg-custom">
            <h5><i class="fas fa-check-circle"></i> Pedidos Facturados
              <?php if ($totalRows_pendientesf > 0) { ?>
                <span class="badge bg-light bg-custom"><?php echo $totalRows_pendientesf; ?></span>
              <?php } ?>
            </h5>
            <div class="dropdown">
              <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">Últimos <?php echo $dias; ?> días</a>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="ventas.php?df=30">30</a>
                <a class="dropdown-item" href="ventas.php?df=60">60</a>
                <a class="dropdown-item" href="ventas.php?df=90">90</a>
              </div>
            </div>
          </div>
          <?php if ($totalRows_pendientesf > 0) { ?>
            <div class="panel-body">
              <div class="row mb-4">
                <div class="col-md-8">
                  <div class="input-group">
                    <input type="text" class="form-control" id="buscac"
                      placeholder="Buscar por código, cliente o fecha...">
                    <button type="button" class="btn btn-success btn-custom">
                      <i class="fas fa-search"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="d-flex justify-content-end">
                    <span class="badge bg-success bg-custom px-3 py-2 text-white">
                      <i class="fas fa-check-circle me-1"></i><?php echo $totalRows_pendientesf; ?> facturados
                    </span>
                  </div>
                </div>
              </div>
              
              <!-- Paginación Facturados -->
              <?php if (!empty($facturados_pagination) && $facturados_pagination['total_pages'] > 1): ?>
              <nav aria-label="Paginación pedidos facturados" class="mt-4">
                <ul class="pagination justify-content-center">
                  <!-- Botón Anterior -->
                  <?php if ($facturados_pagination['has_previous']): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_facturados=<?php echo $facturados_pagination['previous_page']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">
                        <i class="fas fa-chevron-left me-1"></i>Anterior
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">
                        <i class="fas fa-chevron-left me-1"></i>Anterior
                      </span>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Números de página -->
                  <?php
                  $start_page = max(1, $facturados_pagination['current_page'] - 2);
                  $end_page = min($facturados_pagination['total_pages'], $facturados_pagination['current_page'] + 2);
                  
                  // Mostrar primera página si no está en el rango
                  if ($start_page > 1): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_facturados=1&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                      <li class="page-item disabled">
                        <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                      </li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <!-- Páginas en el rango -->
                  <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo ($i == $facturados_pagination['current_page']) ? 'active' : ''; ?>">
                      <a class="page-link text-white btn btn-sm btn-custom px-3 py-2 mx-1 <?php echo ($i == $facturados_pagination['current_page']) ? 'btn-success' : 'btn-danger'; ?>" href="?page_facturados=<?php echo $i; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <!-- Mostrar última página si no está en el rango -->
                  <?php if ($end_page < $facturados_pagination['total_pages']): ?>
                    <?php if ($end_page < $facturados_pagination['total_pages'] - 1): ?>
                      <li class="page-item disabled">
                        <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                      </li>
                    <?php endif; ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_facturados=<?php echo $facturados_pagination['total_pages']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>"><?php echo $facturados_pagination['total_pages']; ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Botón Siguiente -->
                  <?php if ($facturados_pagination['has_next']): ?>
                    <li class="page-item">
                      <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page_facturados=<?php echo $facturados_pagination['next_page']; ?>&per_page=<?php echo $per_page; ?><?php echo isset($diasfin) ? '&df='.$diasfin : ''; ?>">
                        Siguiente<i class="fas fa-chevron-right ms-1"></i>
                      </a>
                    </li>
                  <?php else: ?>
                    <li class="page-item disabled">
                      <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 opacity-50 mx-1">
                        Siguiente<i class="fas fa-chevron-right ms-1"></i>
                      </span>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
              
              <!-- Información adicional de paginación -->
              <div class="text-center text-muted mt-2">
                <small>
                  Mostrando <?php echo $facturados_pagination['start_record']; ?>-<?php echo $facturados_pagination['end_record']; ?> de <?php echo $facturados_pagination['total_records']; ?> pedidos
                </small>
              </div>
              <?php endif; ?>
              <div class="table-responsive">
                <table class="table table-hover table-striped">
                  <thead class="table-dark">
                    <tr>
                      <th class="text-center"><i class="fas fa-hashtag me-1"></i>Código</th>
                      <th class="text-center"><i class="fas fa-calendar me-1"></i>Fecha</th>
                      <th><i class="fas fa-user me-1"></i>Cliente</th>
                      <th class="text-center"><i class="fas fa-dollar-sign me-1"></i>Valor</th>
                      <th class="text-center"><i class="fas fa-file-invoice me-1"></i>Factura</th>
                      <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="dondec">
                    <?php foreach ($pendientesf_data as $row_pendientesf) {
                      // Pedidos facturados - solo permitir ver detalle
                    ?>
                      <tr>
                        <td style="text-align: center"><?php echo $row_pendientesf['ID']; ?></td>
                        <td style="text-align: center"><?php echo $row_pendientesf['post_date']; ?></td>
                        <td style="text-align: left"><?php echo strtoupper($row_pendientesf['nombre1'] . " " . $row_pendientesf['nombre2']); ?></td>
                        <td style="text-align: right"><?php echo number_format($row_pendientesf['valor']); ?></td>
                        <td class="text-center">
                          <span class="badge bg-success bg-custom px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i>Facturado
                          </span>
                        </td>
                        <td class="text-center">
                          <div class="btn-group" role="group">
                            <!-- Botón Editar - Deshabilitado para facturados -->
                            <button type="button" class="btn btn-sm btn-secondary" disabled title="No se puede editar (ya facturado)">
                              <i class="fas fa-edit"></i>
                            </button>

                            <!-- Botón Ver Detalle -->
                            <button type="button" class="btn btn-sm btn-danger btn-custom" onclick="viewOrderDetails(<?php echo $row_pendientesf['ID']; ?>)" title="Ver Detalle">
                              <i class="fas fa-eye"></i>
                            </button>

                            <!-- Botón Facturar - Deshabilitado para facturados -->
                            <button type="button" class="btn btn-sm btn-secondary" disabled title="Ya facturado">
                              <i class="fas fa-file-invoice"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php } // end foreach 
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php } else { ?>
            <div class="panel-body">
              <h4 class="text-center mb-4">El sistema no encuentra pedidos facturados en el periodo seleccionado.</h4>
            </div>
          <?php } ?>
        </div>
      </div>
      <div class="tab-pane container fade" id="menu2">...</div>
    </div>
  </div>
  <!-- Modal para Ver Detalles del Pedido -->
  <div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header bg-cuertar bg-custom text-white">
          <h5 class="modal-title" id="orderDetailsModalLabel">
            <i class="fas fa-eye me-2"></i> Detalles del Pedido #<span id="modal-order-id"></span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close" onclick="closeOrderModal()"></button>
        </div>
        <div class="modal-body">
          <div id="order-details-content">
            <div class="table-responsive">
              <table class="table table-hover table-striped">
                <tbody>
                  <tr>
                    <td colspan="6" class="text-center">
                      <i class="fas fa-spinner fa-spin fa-2x"></i>
                      <p class="mt-2">Cargando detalles del pedido...</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-custom" data-dismiss="modal" onclick="closeOrderModal()">
            <i class="fas fa-times"></i> Cerrar
          </button>
          <a class="btn btn-success btn-custom" target="_blank" href="#" id="btn-view-detail">
            <i class="fas fa-eye"></i> Ver Detalle
          </a>
          <button type="button" class="btn btn-primary btn-custom" id="btn-print-order" onclick="printOrderDetails()">
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

    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('btn-view-detail');

      if (!btn) return;

      btn.addEventListener('click', (e) => {
        e.preventDefault(); // ✅ evita que agregue #
        openOrderDetail();  // ✅ abre la url real
      });
    });

    function openOrderDetail() {
      const orderId = $('#modal-order-id').text().trim();
      console.log('Order ID from modal:', orderId);

      if (!orderId) {
        console.error('No order ID found in modal');
        alert('Error: No se pudo obtener el ID del pedido');
        return;
      }

      // ✅ Usa el ENV correcto. Si env() no existe en PHP puro, usa $_ENV o getenv.
      const baseUrl = <?= json_encode($_ENV['VENTAS_URL'] ?? 'http://localhost/ventas'); ?>;

      // ✅ Construye query correcto
      const url = `${baseUrl}/detalle_pedido.php?id-orden=${encodeURIComponent(orderId)}&common=true`;

      console.log('Constructed URL:', url);
      window.open(url, '_blank', 'noopener'); // noopener por seguridad
    }

    // Eventos para el modal
    $(document).ready(function() {
      // Asegurar que el modal se cierre correctamente
      $('#orderDetailsModal').on('hidden.bs.modal', function() {
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
        data: {
          order_id: orderId
        },
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
          console.log('AJAX Error:', {
            xhr: xhr,
            status: status,
            error: error
          });
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
        '<span class="badge bg-success bg-custom"><i class="fas fa-check"></i> Facturado</span>' :
        '<span class="badge bg-warning bg-custom"><i class="fas fa-clock"></i> Pendiente</span>';

      console.log('Final Status:', status);
      console.log('Final HasInvoice:', hasInvoice);
      console.log('Status Badge HTML:', statusBadge);
      console.log('Invoice Badge HTML:', invoiceBadge);

      let itemsHtml = '';
      if (orderData.items && orderData.items.length > 0) {
        orderData.items.forEach(item => {
          // Calcular precios para detectar descuentos
          const lineTotal = parseFloat(item.line_total || 0);
          const quantity = parseInt(item.product_qty || 1);
          const unitPrice = quantity > 0 ? lineTotal / quantity : 0;
          
          // Obtener precios del producto si están disponibles
          const regularPrice = parseFloat(item.regular_price || item._regular_price || 0);
          const salePrice = parseFloat(item.sale_price || item._sale_price || 0);
          
          // Determinar si hay descuento - usar flag del backend o detectar por precios
          const hasDiscount = item.has_discount === true || item.has_discount === 1 || 
                             (salePrice > 0 && salePrice < regularPrice) ||
                             (parseFloat(item.subtotal_linea || 0) > lineTotal);
          
          
          // Construir HTML del precio
          let priceHtml = '';
          if (hasDiscount) {
            priceHtml = `
              <div class="d-flex align-items-center justify-content-end">
                <div class="text-end">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-danger fw-bold me-2">$${salePrice.toLocaleString('es-CO')}</span>
                    <span class="text-muted text-decoration-line-through small">$${regularPrice.toLocaleString('es-CO')}</span>
                  </div>
                  <div class="mt-1">
                    <span class="badge bg-danger bg-custom rounded-pill small">Oferta</span>
                  </div>
                </div>
              </div>
              <div class="text-end mt-1">
                <strong>Total: $${lineTotal.toLocaleString('es-CO')}</strong>
              </div>
            `;
          } else {
            priceHtml = `
              <div class="text-end">
                ${quantity > 1 ? `<div class="small text-muted">$${unitPrice.toLocaleString('es-CO')} c/u</div>` : ''}
                <strong>$${lineTotal.toLocaleString('es-CO')}</strong>
              </div>
            `;
          }
          
          itemsHtml += `
                <tr>
                    <td class="px-3 py-2">${quantity}</td>
                    <td class="px-3 py-2">
                        ${item.sku ? `<small class="text-muted">SKU: ${item.sku}</small><br>` : ''}
                        ${item.order_item_name}
                        ${hasDiscount ? '<br><small class="text-success"><i class="fas fa-tag me-1"></i>Producto con descuento</small>' : ''}
                    </td>
                    <td class="px-3 py-2">
                        ${priceHtml}
                    </td>
                </tr>
            `;
        });
      }

      const html = `
        <div class="row">
            <div class="col-md-6 table-responsive">
                <hr>
                <h6><i class="fas fa-info-circle me-2"></i> Información del Pedido</h6>
                <hr>
                <table class="table table-sm table-striped">
                    <tr>
                      <td class="px-3 py-2"><strong>Estado:</strong></td>
                      <td class="px-3 py-2">${statusBadge || '<span class="badge bg-primary bg-custom">Procesando</span>'}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Fecha:</strong></td>
                      <td class="px-3 py-2">${orderData.post_date || 'N/A'}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Método de Pago:</strong></td>
                      <td class="px-3 py-2">${orderData.payment_method_title || orderData.payment_method || 'N/A'}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Facturación:</strong></td>
                      <td class="px-3 py-2">${invoiceBadge || '<span class="badge bg-warning bg-custom">Pendiente</span>'}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6 table-responsive">
                <hr>
                <h6><i class="fas fa-user me-2"></i> Información del Cliente</h6>
                <hr>
                <table class="table table-sm table-striped">
                    <tr>
                      <td class="px-3 py-2"><strong>Nombre:</strong></td>
                      <td class="px-3 py-2">${orderData.billing_first_name} ${orderData.billing_last_name}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Email:</strong></td>
                      <td class="px-3 py-2">${orderData.billing_email || 'N/A'}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Teléfono:</strong></td>
                      <td class="px-3 py-2">${orderData.billing_phone || 'N/A'}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Dirección:</strong></td>
                      <td class="px-3 py-2">${buildFullAddress(orderData)}</td>
                    </tr>
                    <tr>
                      <td class="px-3 py-2"><strong>Ciudad:</strong></td>
                      <td class="px-3 py-2">${buildLocationString(orderData)}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <hr>
        <h6><i class="fas fa-shopping-cart me-2"></i> Productos del Pedido</h6>
        <hr>

        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="thead-light">
                    <tr>
                        <th class="px-3 py-2">Cant.</th>
                        <th class="px-3 py-2">Producto</th>
                        <th class="text-right px-3 py-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
                <tfoot>
                    <tr class="table-success">
                        <th colspan="2" class="px-3 py-2">Total del Pedido</th>
                        <th class="text-right px-3 py-2">$${parseFloat(orderData.total || 0).toLocaleString('es-CO')}</th>
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
        'wc-processing': `<span class="badge bg-primary bg-custom"><i class="fas fa-cog"></i> ${statusText}</span>`,
        'wc-completed': `<span class="badge bg-success bg-custom"><i class="fas fa-check"></i> ${statusText}</span>`,
        'wc-on-hold': `<span class="badge bg-warning bg-custom"><i class="fas fa-pause"></i> ${statusText}</span>`,
        'wc-cancelled': `<span class="badge bg-danger bg-custom"><i class="fas fa-times"></i> ${statusText}</span>`,
        'wc-pending': `<span class="badge bg-secondary bg-custom"><i class="fas fa-clock"></i> ${statusText}</span>`,
        'wc-refunded': `<span class="badge bg-info bg-custom"><i class="fas fa-undo"></i> ${statusText}</span>`,
        'wc-failed': `<span class="badge bg-danger bg-custom"><i class="fas fa-exclamation"></i> ${statusText}</span>`
      };
      return statusMap[status] || `<span class="badge bg-light bg-custom"><i class="fas fa-question"></i> ${statusText || status}</span>`;
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
    $(document).ready(function() {
      $("#busca").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#donde tr").filter(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
      });
    });
    $(document).ready(function() {
      $("#buscac").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#dondec tr").filter(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
      });
    });
    
    // Función para cambiar el número de elementos por página
    function changePerPage(perPage) {
      const urlParams = new URLSearchParams(window.location.search);
      urlParams.set('per_page', perPage);
      
      // Resetear páginas a 1 cuando se cambia el tamaño de página
      urlParams.delete('page_pendientes');
      urlParams.delete('page_facturados');
      
      window.location.search = urlParams.toString();
    }
    
    // Función para ir a una página específica
    function goToPage(page, type) {
      const urlParams = new URLSearchParams(window.location.search);
      
      if (type === 'pendientes') {
        urlParams.set('page_pendientes', page);
      } else if (type === 'facturados') {
        urlParams.set('page_facturados', page);
      }
      
      window.location.search = urlParams.toString();
    }
    
    // Mejorar la experiencia de usuario con indicadores de carga
    $(document).on('click', '.pagination .page-link', function(e) {
      const $this = $(this);
      if (!$this.parent().hasClass('active')) {
        $this.html('<i class="fas fa-spinner fa-spin"></i>');
      }
    });
    
    // Función para actualizar la página manteniendo filtros
    function refreshPage() {
      window.location.reload();
    }
    
    // Agregar tooltips a los controles de paginación
    $(document).ready(function() {
      $('[data-bs-toggle="tooltip"]').tooltip();
      
      // Agregar tooltips dinámicos a los enlaces de paginación
      $('.pagination .page-link').each(function() {
        const $link = $(this);
        const href = $link.attr('href');
        
        if (href && href.includes('page_')) {
          const pageMatch = href.match(/page_(?:pendientes|facturados)=(\d+)/);
          if (pageMatch) {
            $link.attr('title', 'Ir a la página ' + pageMatch[1]);
            $link.tooltip();
          }
        }
      });
    });
  </script>

</body>

</html>
<?php
?>