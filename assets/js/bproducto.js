// Sistema de carrito en localStorage para productos
class ProductCart {
    constructor() {
        this.storageKey = 'ventas_cart_products';
        this.cart = this.loadCart();
        this.init();
    }
    
    init() {
        this.updateCartDisplay();
        this.bindEvents();
    }
    
    // Cargar carrito desde localStorage
    loadCart() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            console.error('Error cargando carrito:', e);
            return {};
        }
    }
    
    // Guardar carrito en localStorage
    saveCart() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.cart));
            this.updateCartDisplay();
        } catch (e) {
            console.error('Error guardando carrito:', e);
        }
    }
    
    // Agregar producto al carrito
    addProduct(product, quantity = 1) {
        const productId = product.id.toString();
        
        if (this.cart[productId]) {
            this.cart[productId].quantity += quantity;
        } else {
            this.cart[productId] = {
                id: product.id,
                title: product.title,
                price: parseFloat(product.price || 0),
                regular_price: parseFloat(product.regular_price || 0),
                sale_price: product.sale_price ? parseFloat(product.sale_price) : null,
                stock: parseInt(product.stock || 0),
                sku: product.sku || '',
                quantity: quantity,
                available: product.available
            };
        }
        
        this.saveCart();
        this.showNotification(`${product.title} agregado al carrito`, 'success');
    }
    
    // Actualizar cantidad de producto
    updateQuantity(productId, quantity) {
        productId = productId.toString();
        
        if (this.cart[productId]) {
            if (quantity <= 0) {
                delete this.cart[productId];
            } else {
                this.cart[productId].quantity = quantity;
            }
            this.saveCart();
        }
    }
    
    // Remover producto del carrito
    removeProduct(productId) {
        productId = productId.toString();
        
        if (this.cart[productId]) {
            const productName = this.cart[productId].title;
            delete this.cart[productId];
            this.saveCart();
            this.showNotification(`${productName} removido del carrito`, 'info');
        }
    }
    
    // Obtener productos del carrito
    getCartItems() {
        return Object.values(this.cart);
    }
    
    // Obtener total de productos
    getTotalItems() {
        return Object.values(this.cart).reduce((total, item) => total + item.quantity, 0);
    }
    
    // Obtener total del carrito
    getTotalPrice() {
        return Object.values(this.cart).reduce((total, item) => {
            const price = item.sale_price || item.price;
            return total + (price * item.quantity);
        }, 0);
    }
    
    // Limpiar carrito
    clearCart() {
        this.cart = {};
        this.saveCart();
        this.showNotification('Carrito limpiado', 'info');
    }
    
    // Actualizar display del carrito
    updateCartDisplay() {
        const totalItems = this.getTotalItems();
        const totalPrice = this.getTotalPrice();
        
        // Actualizar contador en el header si existe
        const cartCounter = document.getElementById('cart-counter');
        if (cartCounter) {
            cartCounter.textContent = totalItems;
            cartCounter.style.display = totalItems > 0 ? 'inline' : 'none';
        }
        
        // Actualizar panel de carrito
        this.renderCartPanel();
    }
    
    // Renderizar panel de carrito
    renderCartPanel() {
        const cartContainer = document.getElementById('cart-container');
        if (!cartContainer) return;
        
        const items = this.getCartItems();
        
        if (items.length === 0) {
            cartContainer.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart text-muted"></i>
                    <p class="text-muted">No hay productos seleccionados</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="cart-items">';
        
        items.forEach(item => {
            const price = item.sale_price || item.price;
            const subtotal = price * item.quantity;
            
            html += `
                <div class="cart-item" data-product-id="${item.id}">
                    <div class="item-info">
                        <h6>${item.title}</h6>
                        <small class="text-muted">SKU: ${item.sku || 'N/A'}</small>
                        <div class="price-info">
                            <span class="price">$${price.toLocaleString('es-CO')}</span>
                            ${item.sale_price ? `<span class="regular-price">$${item.regular_price.toLocaleString('es-CO')}</span>` : ''}
                        </div>
                    </div>
                    <div class="item-controls">
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline-secondary" onclick="cart.updateQuantity('${item.id}', ${item.quantity - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control form-control-sm quantity-input" 
                                   value="${item.quantity}" min="1" max="${item.stock}"
                                   onchange="cart.updateQuantity('${item.id}', parseInt(this.value))">
                            <button class="btn btn-sm btn-outline-secondary" onclick="cart.updateQuantity('${item.id}', ${item.quantity + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="item-subtotal">
                            <strong>$${subtotal.toLocaleString('es-CO')}</strong>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="cart.removeProduct('${item.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += `
            </div>
            <div class="cart-summary">
                <div class="d-flex justify-content-between">
                    <strong>Total: $${this.getTotalPrice().toLocaleString('es-CO')}</strong>
                    <span class="text-muted">${this.getTotalItems()} productos</span>
                </div>
                <div class="cart-actions mt-3">
                    <button class="btn btn-outline-danger btn-sm" onclick="cart.clearCart()">
                        <i class="fas fa-trash me-1"></i>Limpiar
                    </button>
                    <button class="btn btn-success" onclick="proceedToSummary()" ${items.length === 0 ? 'disabled' : ''}>
                        <i class="fas fa-arrow-right me-1"></i>Continuar al Resumen
                    </button>
                </div>
            </div>
        `;
        
        cartContainer.innerHTML = html;
    }
    
    // Mostrar notificación
    showNotification(message, type = 'info') {
        // Crear notificación toast
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remover después de 3 segundos
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }
    
    // Bind eventos
    bindEvents() {
        // Limpiar búsqueda
        $('#clearSearch').on('click', () => {
            $('#search').val('').trigger('keyup').focus();
        });
        
        // Filtros rápidos
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            
            const filter = $(this).data('filter');
            // Implementar lógica de filtros si es necesario
        });
    }
}

// Instancia global del carrito
let cart;

$(document).ready(function(){
    // Inicializar carrito
    cart = new ProductCart();
    
    // Focus on search input when page loads
    $('#search').focus();
    
    // Search functionality with improved UX
    $('#search').on('keyup', function(){
        var search = $(this).val().trim();
        var _order_id = $('#_order_id').val();
        
        // Only search if we have at least 3 characters
        if(search.length >= 3) {
            var dataString = '_order_id=' + _order_id + '&search=' + encodeURIComponent(search);
            
            $.ajax({
                type: 'POST',
                url: 'search.php',
                data: dataString,
                dataType: 'json',
                beforeSend: function(){
                    $('#result').html(`
                        <div class="loading-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Buscando...</span>
                            </div>
                            <h5 class="mt-3">Buscando productos...</h5>
                            <p>Por favor espera mientras buscamos "${search}"</p>
                        </div>
                    `);
                    
                    // Actualizar stats
                    $('#searchStats').hide();
                },
                timeout: 10000 // 10 second timeout
            })
            .done(function(response){
                if (response.success && response.products) {
                    renderProducts(response.products);
                    updateSearchStats(response.count, search);
                } else {
                    $('#result').html(`
                        <div class="empty-state">
                            <i class="fas fa-search text-muted"></i>
                            <h5>No se encontraron productos</h5>
                            <p>No hay productos que coincidan con "${search}"</p>
                        </div>
                    `);
                    updateSearchStats(0, search);
                }
            })
            .fail(function(xhr, status, error){
                console.error('Search error:', status, error);
                $('#result').html(`
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <h5 class="mt-3">Error en la búsqueda</h5>
                        <p>No se pudo completar la búsqueda. Por favor intenta de nuevo.</p>
                        <button class="btn btn-outline-primary" onclick="$('#search').trigger('keyup')">
                            <i class="fas fa-redo me-1"></i>Reintentar
                        </button>
                    </div>
                `);
            });
        } else if(search.length === 0) {
            // Reset to initial state when search is empty
            $('#result').html(`
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
            $('#searchStats').hide();
        } else {
            // Show help message for short searches
            $('#result').html(`
                <div class="info-state">
                    <i class="fas fa-keyboard text-warning"></i>
                    <h5>Continúa escribiendo...</h5>
                    <p>Necesitas al menos 3 caracteres para buscar</p>
                </div>
            `);
            $('#searchStats').hide();
        }
    });
    
    // Function to be called by inline JavaScript if needed
    window.performSearch = function(searchTerm) {
        $('#search').val(searchTerm).trigger('keyup');
    };
});

// Renderizar productos en la interfaz
function renderProducts(products) {
    if (!products || products.length === 0) {
        $('#result').html(`
            <div class="empty-state">
                <i class="fas fa-box-open text-muted"></i>
                <h5>No hay productos disponibles</h5>
                <p>No se encontraron productos para mostrar</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="products-grid">';
    
    products.forEach(product => {
        const price = product.sale_price || product.price;
        const hasDiscount = product.sale_price && product.sale_price < product.regular_price;
        
        html += `
            <div class="product-card ${!product.available ? 'out-of-stock' : ''}" data-product-id="${product.id}">
                <div class="product-header">
                    <h6 class="product-title">${product.title}</h6>
                    ${product.sku ? `<small class="text-muted">SKU: ${product.sku}</small>` : ''}
                </div>
                
                <div class="product-info">
                    <div class="price-section">
                        <span class="current-price">$${parseFloat(price).toLocaleString('es-CO')}</span>
                        ${hasDiscount ? `<span class="regular-price">$${parseFloat(product.regular_price).toLocaleString('es-CO')}</span>` : ''}
                        ${hasDiscount ? `<span class="discount-badge">¡Oferta!</span>` : ''}
                    </div>
                    
                    <div class="stock-info">
                        <span class="stock-badge ${product.available ? 'in-stock' : 'out-of-stock'}">
                            <i class="fas ${product.available ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                            ${product.available ? `${product.stock} disponibles` : 'Sin stock'}
                        </span>
                    </div>
                </div>
                
                <div class="product-actions">
                    <div class="quantity-selector">
                        <label class="form-label">Cantidad:</label>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary" type="button" onclick="adjustQuantity('${product.id}', -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center quantity-input" 
                                   id="qty-${product.id}" value="1" min="1" max="${product.stock}" 
                                   ${!product.available ? 'disabled' : ''}>
                            <button class="btn btn-outline-secondary" type="button" onclick="adjustQuantity('${product.id}', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button class="btn btn-success btn-sm add-to-cart-btn" 
                            onclick="addToCart(${JSON.stringify(product).replace(/"/g, '&quot;')})"
                            ${!product.available ? 'disabled' : ''}>
                        <i class="fas fa-cart-plus me-1"></i>
                        ${product.available ? 'Agregar' : 'Sin Stock'}
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    $('#result').html(html);
}

// Actualizar estadísticas de búsqueda
function updateSearchStats(count, searchTerm) {
    $('#resultsCount').text(count);
    $('#searchStats').show();
}

// Ajustar cantidad en selector
function adjustQuantity(productId, delta) {
    const input = document.getElementById(`qty-${productId}`);
    if (input) {
        const currentValue = parseInt(input.value) || 1;
        const newValue = Math.max(1, Math.min(parseInt(input.max), currentValue + delta));
        input.value = newValue;
    }
}

// Agregar producto al carrito
function addToCart(product) {
    const quantityInput = document.getElementById(`qty-${product.id}`);
    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
    
    if (quantity > product.stock) {
        cart.showNotification(`Solo hay ${product.stock} unidades disponibles`, 'warning');
        return;
    }
    
    cart.addProduct(product, quantity);
    
    // Reset quantity to 1
    if (quantityInput) {
        quantityInput.value = 1;
    }
}

// Proceder al resumen (paso 4)
function proceedToSummary() {
    const items = cart.getCartItems();
    
    if (items.length === 0) {
        cart.showNotification('Debes agregar al menos un producto', 'warning');
        return;
    }
    
    // Guardar datos del carrito para el paso 4
    const orderData = {
        order_id: $('#_order_id').val(),
        products: items,
        total_items: cart.getTotalItems(),
        total_price: cart.getTotalPrice(),
        timestamp: new Date().toISOString()
    };
    
    localStorage.setItem('ventas_order_summary', JSON.stringify(orderData));
    
    // Redirigir al paso 4 (resumen)
    window.location.href = 'resumen_pedido.php';
}
