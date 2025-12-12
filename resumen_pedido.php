<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
requireLogin('facturacion.php');

// Obtener datos del usuario actual usando función centralizada
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    // Si no se pueden obtener los datos del usuario, redirigir al login
    Header("Location: index.php");
    exit();
}

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

// Variables para el diseño
$acti1 = 'active';
$acti2 = 'fade';
$pes1 = 'active';
$pes2 = '';

?>
<?php include("parts/header.php"); ?>

<body class="order-summary-container">
    <div class="container-fluid">
        <?php include("parts/menf.php"); ?>

        <input type="hidden" id="_order_id" name="_order_id" value="">
        
        <?php
        // Configurar el paso actual para el wizard
        $current_step = 4; // Paso 4: Resumen del Pedido
        include('parts/step_wizard.php');
        ?>
        
        <!-- Order Summary Header -->
        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-clipboard-check me-2"></i>Resumen del Pedido
                </h2>
                <p class="text-muted">Revisa todos los detalles antes de crear el pedido final</p>
            </div>
        </div>
          
        <!-- Main Content -->
        <div class="container">
            <div class="row">
                <!-- Customer Info Panel -->
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="summary-panel">
                        <div class="panel-header bg-info bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-user me-2"></i>Información del Cliente
                            </h5>
                        </div>
                        
                        <div class="panel-body">
                            <div id="customer-info">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando información del cliente...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Details Panel -->
                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-warning bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-info-circle me-2"></i>Detalles del Pedido
                            </h5>
                        </div>
                        
                        <div class="panel-body">
                            <div id="order-details">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando detalles del pedido...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Products Summary Panel -->
                <div class="col-lg-8 col-md-12">
                    <div class="summary-panel">
                        <div class="panel-header bg-success bg-custom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="text-white">
                                    <i class="fas fa-shopping-cart me-2"></i>Productos Seleccionados
                                </h5>
                                <span class="badge bg-light text-dark" id="products-count">0</span>
                            </div>
                        </div>
                        
                        <div class="panel-body">
                            <div id="products-summary">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Cargando productos seleccionados...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Total Panel -->
                    <div class="summary-panel mt-4">
                        <div class="panel-header bg-primary bg-custom">
                            <h5 class="text-white">
                                <i class="fas fa-calculator me-2"></i>Total del Pedido
                            </h5>
                        </div>
                        
                        <div class="panel-body">
                            <div id="order-total">
                                <div class="info-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Calculando total...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-outline-secondary btn-lg w-100" onclick="goBackToProducts()">
                                    <i class="fas fa-arrow-left me-2"></i>Volver a Productos
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-success btn-lg w-100" id="create-order-btn" onclick="createFinalOrder()" disabled>
                                    <i class="fas fa-check me-2"></i>Crear Pedido
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5>Creando pedido...</h5>
                    <p class="text-muted mb-0">Por favor espera mientras procesamos tu pedido</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Pedido Creado Exitosamente
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="success-icon mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h4>¡Pedido #<span id="order-number"></span> creado!</h4>
                    <p class="text-muted">El pedido ha sido creado exitosamente en el sistema</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="goToOrderList()">
                        <i class="fas fa-list me-2"></i>Ver Pedidos
                    </button>
                    <button type="button" class="btn btn-success" onclick="createNewOrder()">
                        <i class="fas fa-plus me-2"></i>Nuevo Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include("parts/foot.php"); ?>
    <!-- Order Summary JavaScript -->
    <script src="assets/js/resumen_pedido.js"></script>
</body>
</html>
