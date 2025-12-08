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
    <div class="container">
        <?php include("parts/menf.php"); ?>
        <div class="py-5 my-5"></div>
        <section class="row justify-content-center mt-4">
            <div class="col-lg-10 col-md-12">
                <!-- Product Selection Card -->
                <div class="product-card">
                    <div class="product-header">
                        <h2><i class="fas fa-shopping-cart me-3"></i>Seleccionar Producto</h2>
                        <div class="subtitle">Busca y agrega productos a tu pedido</div>
                    </div>
                    
                    <div class="search-section">
                        <?php if($_order_id): ?>
                        <div class="order-info">
                            <i class="fas fa-receipt me-2"></i>
                            <strong>Pedido ID: <?php echo htmlspecialchars($_order_id); ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <div class="search-container">
                            <div class="search-input-group">
                                <input type="text" 
                                       class="search-input" 
                                       id="search" 
                                       name="search" 
                                       placeholder="Buscar productos por nombre o código...">
                                <i class="fas fa-search search-icon"></i>
                                <input type="hidden" id="_order_id" name="_order_id" value="<?php echo htmlspecialchars($_order_id); ?>">
                            </div>
                            <div class="search-help">
                                <i class="fas fa-info-circle me-1"></i>
                                Ingresa al menos 3 caracteres para comenzar la búsqueda
                            </div>
                        </div>
                        
                        <div class="results-container" id="result">
                            <div class="loading-state">
                                <i class="fas fa-search"></i>
                                <h5>Busca productos</h5>
                                <p>Utiliza el campo de búsqueda para encontrar productos disponibles</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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

    <!-- Scripts -->
    <script src="js/jquery-3.4.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap-4.4.1.js"></script>
    <script src="js/bproducto.js"></script>
    
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
        
        // Enhanced modal functionality
        $(document).ready(function() {
            // Modal event handler
            $('#nuevoprod').on('show.bs.modal', function(e) {
                const trigger = $(e.relatedTarget);
                const modal = $(this);
                
                // Extract data from trigger element
                const productName = trigger.data('nombre-id') || '';
                const productId = trigger.data('paquete-id') || '';
                const orderId = trigger.data('order-id') || '';
                const orderIdBn = trigger.data('order-idb') || '';
                const orderInfo = trigger.data('order-idb') || '';
                
                // Populate modal fields
                modal.find('input[name="product_id"]').val(productId);
                modal.find('input[name="order_id"]').val(orderId);
                modal.find('input[name="order_idbn"]').val(orderIdBn);
                modal.find('textarea[name="order_idb"]').val(orderInfo);
                
                // Reset quantity to 1
                modal.find('input[name="product_qty"]').val(1);
                
                // Focus on quantity input
                setTimeout(() => {
                    modal.find('input[name="product_qty"]').focus().select();
                }, 500);
            });
            
            // Enhanced search functionality
            let searchTimeout;
            $('#search').on('input', function() {
                const searchTerm = $(this).val().trim();
                const resultsContainer = $('#result');
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                if (searchTerm.length >= 3) {
                    // Show loading state
                    resultsContainer.html(`
                        <div class="loading-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Buscando...</span>
                            </div>
                            <h5 class="mt-3">Buscando productos...</h5>
                            <p>Por favor espera mientras buscamos "${searchTerm}"</p>
                        </div>
                    `);
                    
                    // Debounce search
                    searchTimeout = setTimeout(() => {
                        // Trigger search via existing bproducto.js functionality
                        if (typeof performSearch === 'function') {
                            performSearch(searchTerm);
                        }
                    }, 300);
                } else if (searchTerm.length === 0) {
                    // Reset to initial state
                    resultsContainer.html(`
                        <div class="loading-state">
                            <i class="fas fa-search"></i>
                            <h5>Busca productos</h5>
                            <p>Utiliza el campo de búsqueda para encontrar productos disponibles</p>
                        </div>
                    `);
                } else {
                    // Show help message for short searches
                    resultsContainer.html(`
                        <div class="loading-state">
                            <i class="fas fa-keyboard text-warning"></i>
                            <h5>Continúa escribiendo...</h5>
                            <p>Necesitas al menos 3 caracteres para buscar</p>
                        </div>
                    `);
                }
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
                
                // Show loading state on submit button
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Agregando...').prop('disabled', true);
                
                // Re-enable after a delay (in case of errors)
                setTimeout(() => {
                    submitBtn.html(originalText).prop('disabled', false);
                }, 5000);
            });
        });
    </script>

</body>
</html>