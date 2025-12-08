<?php
// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
requireLogin('facturacion.php');

// Obtener datos del usuario actual
$colname_usuario = Utils::captureValue('MM_Username', 'SESSION', '');
if ($colname_usuario) {
    $colname_usuario = mysqli_real_escape_string($sandycat, $colname_usuario);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

// Variables para el diseño
$acti1 = 'active';
$acti2 = 'fade';
$pes1 = 'active';
$pes2 = '';

// Capturar order_id del formulario
$_order_id = Utils::captureValue('_order_id', 'POST', '');

?>
<?php include("parts/header.php"); ?>

<body class="product-selector-container">
    <div class="container-fluid">
        <?php include("parts/menf.php"); ?>
        
        <!-- Header Section -->
        <div class="product-page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="page-title">
                            <h1><i class="fas fa-shopping-cart me-3"></i>Seleccionar Productos</h1>
                            <p class="page-subtitle">Busca y agrega productos a tu pedido de manera rápida y eficiente</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if($_order_id): ?>
                        <div class="order-badge">
                            <i class="fas fa-receipt me-2"></i>
                            <span>Pedido #<?php echo htmlspecialchars($_order_id); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="container">
            <div class="row">
                <!-- Search Panel -->
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="search-panel">
                        <div class="panel-header">
                            <h5><i class="fas fa-search me-2"></i>Búsqueda de Productos</h5>
                        </div>
                        
                        <div class="panel-body">
                            <!-- Search Input -->
                            <div class="search-input-wrapper">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control search-input-modern" 
                                           id="search" 
                                           name="search" 
                                           placeholder="Nombre, código o categoría...">
                                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="_order_id" name="_order_id" value="<?php echo htmlspecialchars($_order_id); ?>">
                            </div>
                            
                            <!-- Search Stats -->
                            <div class="search-stats" id="searchStats" style="display: none;">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <span id="resultsCount">0</span> productos encontrados
                                </small>
                            </div>
                            
                            <!-- Quick Filters -->
                            <div class="quick-filters">
                                <h6>Filtros Rápidos</h6>
                                <div class="filter-buttons">
                                    <button class="btn btn-outline-primary btn-sm filter-btn" data-filter="all">
                                        <i class="fas fa-list me-1"></i>Todos
                                    </button>
                                    <button class="btn btn-outline-success btn-sm filter-btn" data-filter="available">
                                        <i class="fas fa-check-circle me-1"></i>Disponibles
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm filter-btn" data-filter="featured">
                                        <i class="fas fa-star me-1"></i>Destacados
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Search Tips -->
                            <div class="search-tips">
                                <h6>Consejos de Búsqueda</h6>
                                <ul class="tips-list">
                                    <li><i class="fas fa-lightbulb me-1"></i>Usa al menos 3 caracteres</li>
                                    <li><i class="fas fa-lightbulb me-1"></i>Busca por nombre o código</li>
                                    <li><i class="fas fa-lightbulb me-1"></i>Los filtros ayudan a refinar</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Results Panel -->
                <div class="col-lg-8 col-md-12">
                    <div class="results-panel">
                        <div class="panel-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-box me-2"></i>Productos</h5>
                                <div class="view-controls">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm view-toggle active" data-view="grid">
                                            <i class="fas fa-th"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm view-toggle" data-view="list">
                                            <i class="fas fa-list"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="panel-body">
                            <div class="results-container" id="result">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <h4>Busca productos</h4>
                                    <p>Utiliza el panel de búsqueda para encontrar productos disponibles</p>
                                    <div class="empty-state-actions">
                                        <button class="btn btn-primary" onclick="$('#search').focus()">
                                            <i class="fas fa-search me-2"></i>Comenzar Búsqueda
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include("parts/foot.php"); ?>
    
    <!-- Product Selection Modal -->
    <div class="modal fade" id="nuevoprod" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header" style="background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white;">
                    <h4 class="modal-title" id="productModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Agregar Producto al Pedido
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <!-- Product Info Display -->
                <div class="modal-product-info" style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6;">
                    <textarea id="order_idb" name="order_idb" 
                              style="font-size: 16px; color: #495057; padding: 10px; text-align: center; width: 100%; border: none; background: transparent; resize: none; font-weight: 600;" 
                              rows="2" disabled readonly></textarea>
                </div>
                
                <!-- Modal Body -->
                <div class="modal-body" style="padding: 30px;">
                    <form action="pros_venta.php" method="post" id="newproduct" class="product-form">
                        <!-- Hidden Fields -->
                        <input id="proceso" type="hidden" name="proceso" value="s">
                        <input id="order_id" type="hidden" name="order_id" value="">
                        <input id="order_idbn" type="hidden" name="order_idbn" value="">
                        <input id="product_id" type="hidden" name="product_id" value="">
                        
                        <!-- Quantity Input -->
                        <div class="form-group mb-4">
                            <label for="product_qty" class="form-label" style="font-weight: 600; color: var(--primary-color); margin-bottom: 10px;">
                                <i class="fas fa-calculator me-2"></i>Cantidad
                            </label>
                            <div class="quantity-input-group" style="position: relative;">
                                <input id="product_qty" 
                                       name="product_qty" 
                                       type="number" 
                                       class="form-control form-control-lg" 
                                       value="1" 
                                       min="1" 
                                       max="999"
                                       required
                                       style="border-radius: 10px; border: 2px solid #e9ecef; padding: 15px 20px; font-size: 1.1rem; text-align: center;">
                                <div class="quantity-buttons" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column;">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="incrementQuantity()" style="border: none; padding: 2px 8px; margin-bottom: 2px;">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="decrementQuantity()" style="border: none; padding: 2px 8px;">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Especifica la cantidad de productos que deseas agregar
                            </small>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-lg btn-custom" type="submit" name="venta" id="venta" style="border-radius: 10px; padding: 15px;">
                                <i class="fas fa-cart-plus me-2"></i>Agregar al Pedido
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Modal Footer -->
                <div class="modal-footer" style="background: #f8f9fa; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bproducto.js"></script>
    
    <script>
        // Quantity control functions
        function incrementQuantity() {
            const qtyInput = document.getElementById('product_qty');
            const currentValue = parseInt(qtyInput.value) || 1;
            const maxValue = parseInt(qtyInput.getAttribute('max')) || 999;
            
            if (currentValue < maxValue) {
                qtyInput.value = currentValue + 1;
            }
        }
        
        function decrementQuantity() {
            const qtyInput = document.getElementById('product_qty');
            const currentValue = parseInt(qtyInput.value) || 1;
            const minValue = parseInt(qtyInput.getAttribute('min')) || 1;
            
            if (currentValue > minValue) {
                qtyInput.value = currentValue - 1;
            }
        }
        
        // Enhanced functionality for new design
        $(document).ready(function() {
            // Clear search functionality
            $('#clearSearch').on('click', function() {
                $('#search').val('').trigger('keyup').focus();
                $('#searchStats').hide();
            });
            
            // Filter buttons functionality
            $('.filter-btn').on('click', function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                
                const filter = $(this).data('filter');
                // Here you can add filter logic based on the filter value
                console.log('Filter selected:', filter);
            });
            
            // View toggle functionality
            $('.view-toggle').on('click', function() {
                $('.view-toggle').removeClass('active');
                $(this).addClass('active');
                
                const view = $(this).data('view');
                const resultsContainer = $('#result');
                
                if (view === 'grid') {
                    resultsContainer.removeClass('list-view').addClass('grid-view');
                } else {
                    resultsContainer.removeClass('grid-view').addClass('list-view');
                }
            });
            
            // Enhanced search with stats
            let searchTimeout;
            $('#search').on('input', function() {
                const searchTerm = $(this).val().trim();
                const resultsContainer = $('#result');
                const searchStats = $('#searchStats');
                
                clearTimeout(searchTimeout);
                
                if (searchTerm.length >= 3) {
                    resultsContainer.html(`
                        <div class="loading-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Buscando...</span>
                            </div>
                            <h5 class="mt-3">Buscando productos...</h5>
                            <p>Buscando "${searchTerm}" en el catálogo</p>
                        </div>
                    `);
                    
                    searchStats.show();
                    
                    searchTimeout = setTimeout(() => {
                        if (typeof performSearch === 'function') {
                            performSearch(searchTerm);
                        }
                    }, 300);
                } else if (searchTerm.length === 0) {
                    resultsContainer.html(`
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4>Busca productos</h4>
                            <p>Utiliza el panel de búsqueda para encontrar productos disponibles</p>
                            <div class="empty-state-actions">
                                <button class="btn btn-primary" onclick="$('#search').focus()">
                                    <i class="fas fa-search me-2"></i>Comenzar Búsqueda
                                </button>
                            </div>
                        </div>
                    `);
                    searchStats.hide();
                } else {
                    resultsContainer.html(`
                        <div class="loading-state">
                            <i class="fas fa-keyboard text-warning"></i>
                            <h5>Continúa escribiendo...</h5>
                            <p>Necesitas al menos 3 caracteres para buscar</p>
                        </div>
                    `);
                    searchStats.hide();
                }
            });
            
            // Modal functionality
            $('#nuevoprod').on('show.bs.modal', function(e) {
                const trigger = $(e.relatedTarget);
                const modal = $(this);
                
                const productName = trigger.data('nombre-id') || '';
                const productId = trigger.data('paquete-id') || '';
                const orderId = trigger.data('order-id') || '';
                const orderIdBn = trigger.data('order-idb') || '';
                const orderInfo = trigger.data('order-idb') || '';
                
                modal.find('input[name="product_id"]').val(productId);
                modal.find('input[name="order_id"]').val(orderId);
                modal.find('input[name="order_idbn"]').val(orderIdBn);
                modal.find('textarea[name="order_idb"]').val(orderInfo);
                modal.find('input[name="product_qty"]').val(1);
                
                setTimeout(() => {
                    modal.find('input[name="product_qty"]').focus().select();
                }, 500);
            });
            
            // Form validation
            $('#newproduct').on('submit', function(e) {
                const qty = parseInt($('#product_qty').val());
                const productId = $('#product_id').val();
                
                if (!productId) {
                    e.preventDefault();
                    alert('Error: No se ha seleccionado ningún producto.');
                    return false;
                }
                
                if (!qty || qty < 1) {
                    e.preventDefault();
                    alert('Error: La cantidad debe ser mayor a 0.');
                    $('#product_qty').focus();
                    return false;
                }
                
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Agregando...').prop('disabled', true);
                
                setTimeout(() => {
                    submitBtn.html(originalText).prop('disabled', false);
                }, 5000);
            });
            
            // Update results count function
            window.updateResultsCount = function(count) {
                $('#resultsCount').text(count);
                if (count > 0) {
                    $('#searchStats').show();
                } else {
                    $('#searchStats').hide();
                }
            };
            
            // Focus on search input when page loads
            setTimeout(() => {
                $('#search').focus();
            }, 500);
        });
    </script>

</body>
</html>