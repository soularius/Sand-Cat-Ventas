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

// Capturar order_id del formulario
$_order_id = Utils::captureValue('_order_id', 'POST', '');

?>
<?php include("parts/header.php"); ?>

<body class="product-selector-container">
    <div class="container-fluid">
        <?php include("parts/menf.php"); ?>
        
        <?php
        // Configurar el paso actual para el wizard
        $current_step = 3; // Paso 3: Productos
        include('parts/step_wizard.php');
        ?>
        
        <!-- Product Header -->
        <div class="row justify-content-center">
            <div class="col-md-10 text-center mb-4">
                <h2 class="heading-section text-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Selección de Productos
                </h2>
                <?php if($_order_id): ?>
                    <p class="text-muted">Pedido #<?php echo htmlspecialchars($_order_id); ?> - Busca y agrega productos a tu pedido</p>
                <?php else: ?>
                    <p class="text-muted">Busca y agrega productos de manera rápida y eficiente</p>
                <?php endif; ?>
            </div>
        </div>
          
          <!-- Main Content -->
          <div class="container">
              <div class="row">
                  <!-- Search Panel -->
                  <div class="col-lg-4 col-md-12 mb-4">
                      <div class="search-panel">
                          <div class="panel-header bg-success bg-custom">
                                <h5 class="text-white">
                                    <i class="fas fa-search me-2"></i> Búsqueda de Productos
                                </h5>
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
                                      <button class="btn btn-outline-primary btn-sm filter-btn active" data-filter="all">
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
                      
                      <!-- Cart Panel -->
                      <div class="cart-panel mt-4">
                          <div class="panel-header bg-primary bg-custom">
                              <h5 class="text-white">
                                  <i class="fas fa-shopping-cart me-2"></i>Carrito 
                                  <span class="badge bg-light text-dark ms-2" id="cart-counter" style="display: none;">0</span>
                              </h5>
                          </div>
                          
                          <div class="panel-body">
                              <div id="cart-container">
                                  <div class="empty-cart">
                                      <i class="fas fa-shopping-cart text-muted"></i>
                                      <p class="text-muted">No hay productos seleccionados</p>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  
                  <!-- Results Panel -->
                  <div class="col-lg-8 col-md-12">
                      <div class="results-panel">
                          <div class="panel-header bg-success bg-custom">
                              <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="text-white">
                                        <i class="fas fa-box me-2"></i>Productos
                                    </h5>
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
                                          <button class="btn btn-danger" onclick="$('#search').focus()">
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
        </section>
    </div>
    <?php include("parts/foot.php"); ?>
    
    <!-- Modal para agregar producto -->
    <div class="modal fade product-modal" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                
                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title fw-bold" id="productModalLabel">
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
                <div class="modal-body">
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
                            <div class="quantity-input-container">
                                <input type="text" 
                                       class="form-control text-center" 
                                       id="product_qty" 
                                       name="product_qty" 
                                       value="1" 
                                       min="1" 
                                       max="999"
                                       required>
                                <div class="quantity-buttons">
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="incrementQuantity()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="decrementQuantity()">
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
                        <div class="d-flex justify-content-center gap-3 align-items-center">
                            <div class="text-center">
                                <button class="btn btn-success btn-circular" type="submit" name="venta" id="venta" title="Agregar al Pedido">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted fw-medium">Agregar al Pedido</small>
                                </div>
                            </div>
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

        $(document).ready(function() {
            let searchTimeout;
            let currentFilter = 'all';
            
            // Función para realizar búsqueda de productos
            function searchProducts() {
                const searchTerm = $('#search').val().trim();
                
                // Mostrar loading
                $('#result').html(`
                    <div class="loading-state text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <h5>Buscando productos...</h5>
                        <p class="text-muted">Por favor espere</p>
                    </div>
                `);
                
                // Realizar petición AJAX
                $.ajax({
                    url: 'search_products_ajax.php',
                    method: 'POST',
                    data: {
                        search: searchTerm,
                        filter: currentFilter,
                        limit: 20
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayProducts(response.products);
                            updateResultsCount(response.total);
                        } else {
                            showError('Error al buscar productos: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', error);
                        showError('Error de conexión al buscar productos');
                    }
                });
            }
            
            // Función para mostrar productos
            function displayProducts(products) {
                if (products.length === 0) {
                    $('#result').html(`
                        <div class="empty-state text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-box-open fa-3x text-muted"></i>
                            </div>
                            <h4>No se encontraron productos</h4>
                            <p class="text-muted">Intenta con otros términos de búsqueda</p>
                        </div>
                    `);
                    return;
                }
                
                let html = '<div class="row">';
                
                products.forEach(function(product) {
                    const priceHtml = product.has_sale ? 
                        `<div class="price mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="sale-price text-danger fw-bold fs-5">$${product.sale_price}</span>
                                    <span class="regular-price text-muted text-decoration-line-through ms-2 small">$${product.regular_price}</span>
                                </div>
                                <span class="badge ${product.price_badge_class} rounded-pill">Oferta</span>
                            </div>
                        </div>` :
                        `<div class="price mb-3">
                            <span class="current-price text-primary fw-bold fs-5">$${product.price}</span>
                        </div>`;
                    
                    const stockHtml = product.is_available ?
                        `<div class="stock-info d-flex align-items-center justify-content-between mb-2">
                            <span class="stock-status ${product.stock_status_class} d-flex align-items-center">
                                <i class="${product.availability_icon} me-1"></i>
                                <span class="fw-medium">${product.availability_text}</span>
                            </span>
                            ${product.stock_quantity > 0 ? `<small class="text-muted">${product.stock_quantity} unidades</small>` : ''}
                        </div>` :
                        `<div class="stock-info mb-2">
                            <span class="stock-status ${product.stock_status_class} d-flex align-items-center">
                                <i class="${product.availability_icon} me-1"></i>
                                <span class="fw-medium">${product.availability_text}</span>
                            </span>
                        </div>`;
                    
                    const skuBadge = product.sku ? 
                        `<div class="mb-2">
                            <span class="product-sku badge bg-light text-dark">SKU: ${product.sku}</span>
                        </div>` : '';                    
                    html += `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card product-card shadow-sm ${product.card_border_class}" data-product-id="${product.id}">
                                <div class="position-relative overflow-hidden">
                                    <img src="${product.image_url}" 
                                         class="card-img-top" 
                                         alt="${product.title}" 
                                         style="height: 220px; object-fit: cover; transition: transform 0.3s ease;"
                                         loading="lazy">
                                    ${product.has_sale ? 
                                        '<div class="position-absolute top-0 end-0 m-3"><span class="badge bg-danger fs-6 px-3 py-2 rounded-pill shadow">¡Oferta!</span></div>' : 
                                        ''}
                                    ${!product.is_available ? 
                                        '<div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.6);"><span class="badge bg-warning text-dark fs-6 px-3 py-2">Agotado</span></div>' : 
                                        ''}
                                </div>
                                <div class="card-body d-flex flex-column p-3 card-body-product">
                                    <h6 class="card-title fw-bold mb-2" style="color: #2c3e50; line-height: 1.3;">${product.title}</h6>
                                    ${skuBadge}
                                    ${priceHtml}
                                    ${stockHtml}
                                    <div class="d-flex justify-content-center mt-auto">
                                        <button class="btn btn-custom btn-circular ${product.is_available ? 'btn-success' : 'btn-secondary'} add-product-btn position-absolute text-white" 
                                                data-product-id="${product.id}" 
                                                data-product-name="${product.title}"
                                                data-product-price="${product.price.replace(/[.,]/g, '')}"
                                                ${!product.is_available ? 'disabled' : ''}
                                                title="${product.is_available ? 'Agregar al Pedido' : 'No Disponible'}">
                                            <i class="text-white ${product.is_available ? 'fas fa-plus' : 'fas fa-xmark'}"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                $('#result').html(html);
            }
            
            // Función para mostrar errores
            function showError(message) {
                $('#result').html(`
                    <div class="error-state text-center py-5">
                        <div class="error-icon text-danger mb-4">
                            <i class="fas fa-exclamation-triangle fa-4x opacity-75"></i>
                        </div>
                        <h4 class="text-danger mb-3">¡Oops! Algo salió mal</h4>
                        <p class="text-muted mb-4 mx-auto" style="max-width: 400px;">${message}</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-outline-primary" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>Reintentar
                            </button>
                            <button class="btn btn-outline-secondary" onclick="$('#search').val('').focus()">
                                <i class="fas fa-search me-2"></i>Nueva Búsqueda
                            </button>
                        </div>
                    </div>
                `);
            }
            
            // Event listeners
            $('#search').on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length >= 3) {
                    searchTimeout = setTimeout(searchProducts, 500);
                } else if (searchTerm.length === 0) {
                    $('#result').html(`
                        <div class="empty-state text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="fas fa-search fa-3x text-muted"></i>
                            </div>
                            <h4>Busca productos</h4>
                            <p class="text-muted">Utiliza el panel de búsqueda para encontrar productos disponibles</p>
                        </div>
                    `);
                    updateResultsCount(0);
                }
            });
            
            // Filtros rápidos
            $('.filter-btn').on('click', function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                
                const searchTerm = $('#search').val().trim();
                if (searchTerm.length >= 3) {
                    searchProducts();
                }
            });
            
            // Clear search functionality
            $('#clearSearch').on('click', function() {
                $('#search').val('').focus();
                $('#result').html(`
                    <div class="empty-state text-center py-5">
                        <div class="empty-state-icon mb-3">
                            <i class="fas fa-search fa-3x text-muted"></i>
                        </div>
                        <h4>Busca productos</h4>
                        <p class="text-muted">Utiliza el panel de búsqueda para encontrar productos disponibles</p>
                    </div>
                `);
                updateResultsCount(0);
            });
            
            // Manejar click en agregar/eliminar producto
            $(document).on('click', '.add-product-btn', function() {
                const btn = $(this);
                const prodId = btn.data('product-id');
                const prodName = btn.data('product-name');
                const prodPrice = btn.data('product-price');
                
                // Verificar si el botón está en estado "eliminar" (tiene X)
                const isRemoveState = btn.find('.fa-xmark').length > 0;
                
                if (isRemoveState) {
                    // Eliminar producto del carrito
                    console.log('Removing product from cart:', prodId);
                    if (window.cart) {
                        window.cart.removeProduct(prodId);
                        // Cambiar botón de vuelta al estado inicial
                        btn.html('<i class="text-white fas fa-plus"></i>')
                           .removeClass('btn-danger btn-success')
                           .addClass('btn-success')
                           .prop('disabled', false);
                        console.log('Product removed and button reset');
                    }
                    return;
                }
                
                // Estado normal: agregar producto
                console.log('Adding product to cart:', prodId, prodName);
                
                // Cambiar estado del botón a "cargando"
                const origText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin me-1"></i>').prop('disabled', true);
                
                // Agregar al carrito usando el sistema existente
                if (window.cart) {
                    const product = {
                        id: prodId,
                        title: prodName,
                        price: prodPrice,
                        available: true,
                        stock: 999
                    };
                    window.cart.addProduct(product, 1);
                }
                
                // Cambiar a estado "agregado" (check)
                setTimeout(() => {
                    btn.html('<i class="fas fa-check me-1"></i>').removeClass('btn-success').addClass('btn-success');
                    
                    // Cambiar a estado "eliminar" (X) después de 2 segundos
                    setTimeout(() => {
                        btn.html('<i class="fas fa-xmark me-1"></i>')
                           .removeClass('btn-success')
                           .addClass('btn-danger')
                           .prop('disabled', false)
                           .attr('title', 'Eliminar del carrito');
                    }, 2000);
                }, 1000);
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