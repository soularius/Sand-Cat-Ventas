// ============================================================
// Carrito (localStorage) soportando productos con VARIACIONES
// - La llave del item ya NO es solo product_id
// - Ahora es cart_key = product_id + variation_id (o hash de attrs)
// ============================================================

class ProductCart {
    constructor() {
        console.log('ProductCart constructor called');
        this.storageKey = 'ventas_cart_products';
        this.cart = this.loadCart();
        console.log('Cart loaded in constructor:', this.cart);
        this.init();
    }

    // Normalizar valores monetarios (COP) que pueden venir como "20.400" / "10,000" / "$20.400"
    parseCOP(value) {
        if (value === null || value === undefined) return 0;
        if (typeof value === 'number' && Number.isFinite(value)) return value;

        const cleaned = value.toString().replace(/[^\d]/g, '');
        if (!cleaned) return 0;

        const n = parseInt(cleaned, 10);
        return Number.isFinite(n) ? n : 0;
    }

    // Hash simple para attrs cuando NO hay variation_id (por seguridad)
    // (No es criptográfico, solo evita colisiones "obvias" en UI)
    simpleHash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = ((h << 5) - h) + str.charCodeAt(i);
            h |= 0;
        }
        return Math.abs(h);
    }

    // Construye la llave única del item del carrito
    // - Si hay variation_id: productId:v:variationId
    // - Si no hay variation_id pero hay atributos: productId:a:hash(attrs)
    // - Si no hay nada: productId
    buildCartKey(product) {
        const productId = String(product?.id ?? product?.product_id ?? '').trim();
        const variationId = (product?.variation_id !== null && product?.variation_id !== undefined && product?.variation_id !== '')
            ? String(product.variation_id).trim()
            : '';

        if (!productId) return '';

        if (variationId) {
            return `${productId}:v:${variationId}`;
        }

        const attrs = product?.variation_attributes ?? product?.variation_attrs ?? null;
        if (attrs && typeof attrs === 'object') {
            const raw = JSON.stringify(attrs);
            return `${productId}:a:${this.simpleHash(raw)}`;
        }

        if (typeof product?.variation_label === 'string' && product.variation_label.trim() !== '') {
            return `${productId}:l:${this.simpleHash(product.variation_label.trim())}`;
        }

        return productId;
    }
    
    init() {
        console.log('ProductCart init called');
        this.updateCartDisplay();
        this.bindEvents();
    }
    
    // Cargar carrito desde localStorage
    loadCart() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            const cart = stored ? JSON.parse(stored) : {};

            // Normalizar y asegurar campos nuevos (cart_key, product_id, variation_id)
            Object.keys(cart).forEach((cartKey) => {
                const item = cart[cartKey];
                if (!item) return;

                // Si vienes de la versión anterior (key = productId), garantizamos cart_key
                item.cart_key = item.cart_key || cartKey;

                // Compatibilidad: antes guardabas item.id como id del producto
                // Ahora guardamos explícito product_id
                item.product_id = item.product_id || item.id || null;

                // Variación (si existía en versiones nuevas)
                item.variation_id = (item.variation_id !== undefined) ? item.variation_id : null;
                item.variation_label = item.variation_label || '';
                item.variation_attributes = item.variation_attributes || null;

                // Normalizar precios
                item.price = this.parseCOP(item.price);
                item.regular_price = this.parseCOP(item.regular_price);
                item.sale_price = (item.sale_price !== null && item.sale_price !== undefined && item.sale_price !== '')
                    ? this.parseCOP(item.sale_price)
                    : null;

                // Si no hay regular_price, usar price
                if ((!item.regular_price || item.regular_price <= 0) && item.price > 0) {
                    item.regular_price = item.price;
                }

                // Si sale inválido, descartarlo
                if (item.sale_price !== null && item.sale_price >= item.regular_price) {
                    item.sale_price = null;
                }

                item.stock = parseInt(item.stock || 0, 10);
                item.quantity = parseInt(item.quantity || 0, 10);
            });

            return cart;
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
    // IMPORTANTE: retorna la cart_key para que el botón pueda "recordar"
    // qué item exacto (variación) debe eliminar.
    addProduct(product, quantity = 1) {
        console.log('ProductCart.addProduct called with:', product, 'quantity:', quantity);

        const cartKey = this.buildCartKey(product);

        if (!cartKey) {
            console.error('No se pudo construir cart_key (producto inválido):', product);
            this.showNotification('No se pudo agregar: producto inválido', 'warning');
            return '';
        }

        if (this.cart[cartKey]) {
            console.log('Item existe en carrito (misma variación), incrementando qty');
            this.cart[cartKey].quantity += quantity;
        } else {
            console.log('Agregando item nuevo al carrito con cart_key:', cartKey);

            const normalizedPrice = this.parseCOP(product.price || 0);
            const normalizedRegular = this.parseCOP(
                (product.regular_price !== null && product.regular_price !== undefined && product.regular_price !== '')
                    ? product.regular_price
                    : normalizedPrice
            );
            const normalizedSale = (product.sale_price !== null && product.sale_price !== undefined && product.sale_price !== '')
                ? this.parseCOP(product.sale_price)
                : null;

            this.cart[cartKey] = {
                // Llaves
                cart_key: cartKey,
                product_id: product.id ?? product.product_id ?? null, // id del producto padre
                variation_id: (product.variation_id !== null && product.variation_id !== undefined && product.variation_id !== '')
                    ? String(product.variation_id)
                    : null,

                // Visual / info
                title: product.title || 'Producto',
                variation_label: product.variation_label || '',
                variation_attributes: product.variation_attributes || null,

                // Precios
                price: normalizedPrice,
                regular_price: (normalizedRegular > 0 ? normalizedRegular : normalizedPrice),
                sale_price: (normalizedSale !== null && normalizedSale < (normalizedRegular > 0 ? normalizedRegular : normalizedPrice))
                    ? normalizedSale
                    : null,

                // Stock / control
                stock: parseInt(product.stock || 0, 10),
                sku: product.sku || '',
                image_url: product.image_url || '',
                permalink: product.permalink || '#',
                quantity: quantity,
                available: product.available
            };
        }

        console.log('Cart after add:', this.cart);
        this.saveCart();

        const label = (product.variation_label && String(product.variation_label).trim() !== '')
            ? ` (${product.variation_label})` 
            : '';

        this.showNotification(`${product.title}${label} agregado al carrito`, 'success');

        return cartKey;
    }
    
    // Actualizar cantidad por cart_key
    updateQuantity(cartKey, quantity) {
        cartKey = cartKey.toString();

        if (this.cart[cartKey]) {
            if (quantity <= 0) {
                delete this.cart[cartKey];
            } else {
                this.cart[cartKey].quantity = quantity;
            }
            this.saveCart();
        }
    }

    // Remover por cart_key
    removeProduct(cartKey) {
        cartKey = cartKey.toString();

        if (this.cart[cartKey]) {
            const productName = this.cart[cartKey].title;
            const label = this.cart[cartKey].variation_label ? ` (${this.cart[cartKey].variation_label})` : '';
            delete this.cart[cartKey];
            this.saveCart();
            this.showNotification(`${productName}${label} removido del carrito`, 'info');
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
            const unitPrice = (item.sale_price !== null && item.sale_price !== undefined) ? item.sale_price : item.price;
            return total + (this.parseCOP(unitPrice) * (parseInt(item.quantity || 0, 10)));
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
        
        console.log('updateCartDisplay called - Total items:', totalItems, 'Total price:', totalPrice);
        
        // Actualizar contador en el header si existe
        const cartCounter = document.getElementById('cart-counter');
        if (cartCounter) {
            cartCounter.textContent = "×" + totalItems + " Item" + (totalItems > 1 ? 's' : '');
            cartCounter.style.display = totalItems > 0 ? 'inline' : 'none';
            console.log('Cart counter updated:', totalItems);
        } else {
            console.warn('Cart counter element not found');
        }
        
        // Actualizar panel de carrito
        this.renderCartPanel();
    }
    
    // Renderizar panel de carrito
    renderCartPanel() {
        const cartContainer = document.getElementById('cart-container');
        if (!cartContainer) {
            console.error('Cart container element not found!');
            return;
        }
        
        const items = this.getCartItems();
        console.log('renderCartPanel called with items:', items);
        
        if (items.length === 0) {
            console.log('No items in cart, showing empty state');
            cartContainer.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart text-muted"></i>
                    <p class="text-muted">No hay productos seleccionados</p>
                </div>
            `;
            
            // Ocultar botones de acción externos
            const cartActions = document.getElementById('cart-actions');
            if (cartActions) {
                cartActions.style.display = 'none';
            }
            return;
        }
        
        let html = '<div class="cart-items">';

        // Totales para mostrar descuentos
        let subtotalRegular = 0;
        let totalDiscount = 0;
        
        items.forEach(item => {
            const regularPrice = this.parseCOP(item.regular_price || item.price || 0);
            const salePrice = (item.sale_price !== null && item.sale_price !== undefined) ? this.parseCOP(item.sale_price) : null;
            const finalUnitPrice = (salePrice !== null) ? salePrice : this.parseCOP(item.price || 0);
            const hasDiscount = (salePrice !== null) && (salePrice < regularPrice);

            const subtotalFinal = finalUnitPrice * item.quantity;
            const subtotalReg = regularPrice * item.quantity;
            const itemDiscount = hasDiscount ? ((regularPrice - salePrice) * item.quantity) : 0;

            subtotalRegular += subtotalReg;
            totalDiscount += itemDiscount;
            
            const escapedTitle = (item.title || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
            const escapedPermalink = (item.permalink || '#').replace(/'/g, "\\'").replace(/"/g, '\\"');

            const variationLine = (item.variation_label && String(item.variation_label).trim() !== '')
                ? `<div class="text-muted small"><strong>Variación: </strong><br><span>${String(item.variation_label)}</span></div>` 
                : '';

            html += `
                <div class="cart-item" data-cart-key="${item.cart_key}">
                    <div class="item-info">
                        <h6 class="product-title-clickable"
                            onclick="viewProductDetails('${item.product_id || ''}', '${escapedTitle}', '${escapedPermalink}')"
                            title="Click para ver detalles del producto"
                            style="cursor: pointer; color: var(--primary-color); text-decoration: underline;">
                            ${item.title}
                        </h6>

                        ${variationLine}

                        <small class="text-muted text-uppercase fw-bold">SKU: ${item.sku || 'N/A'}</small>

                        <div class="d-flex align-items-center justify-content-between position-relative">
                            ${hasDiscount ? `
                                <div class="d-flex align-items-center justify-content-between box-prices">
                                    <div>
                                        <span class="sale-price text-danger fw-bold">$${finalUnitPrice.toLocaleString('es-CO')}</span>
                                        <span class="regular-price text-muted text-decoration-line-through ms-2 small">$${regularPrice.toLocaleString('es-CO')}</span>
                                    </div>
                                    <span class="badge bg-danger rounded-pill position-absolute badge-OFF">Oferta</span>
                                </div>
                                <div class="text-success small mt-1">Ahorra $${(regularPrice - salePrice).toLocaleString('es-CO')}</div>
                            ` : ` 
                                <span class="current-price text-primary fw-bold">$${finalUnitPrice.toLocaleString('es-CO')}</span>
                            `}
                        </div>
                    </div>

                    <div class="item-controls">
                        <div class="quantity-controls">
                            <button class="btn btn-sm btn-outline-secondary" onclick="cart.updateQuantity('${item.cart_key}', ${item.quantity - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm quantity-input"
                                   value="${item.quantity}" min="1" max="${item.stock}"
                                   onchange="cart.updateQuantity('${item.cart_key}', parseInt(this.value || '1', 10))">
                            <button class="btn btn-sm btn-outline-secondary" onclick="cart.updateQuantity('${item.cart_key}', ${item.quantity + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <div class="item-subtotal">
                            <strong>$${subtotalFinal.toLocaleString('es-CO')}</strong>
                            ${itemDiscount > 0 ? `<div class="text-success small">-$${itemDiscount.toLocaleString('es-CO')}</div>` : ''}
                        </div>

                        <button class="btn btn-sm btn-outline-danger" onclick="cart.removeProduct('${item.cart_key}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        const totalFinal = subtotalRegular - totalDiscount;

        html += `
            </div>
            <div class="cart-summary">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Subtotal:</span>
                    <strong>$${subtotalRegular.toLocaleString('es-CO')}</strong>
                </div>
                <div class="d-flex justify-content-between ${totalDiscount > 0 ? '' : 'd-none'}">
                    <span class="text-success">Descuento:</span>
                    <strong class="text-success">-$${totalDiscount.toLocaleString('es-CO')}</strong>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <strong>Total:</strong>
                    <strong>$${totalFinal.toLocaleString('es-CO')}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">${this.getTotalItems()} productos</span>
                    ${totalDiscount > 0 ? `<span class="text-danger font-weight-bold">Ahorras $${totalDiscount.toLocaleString('es-CO')}</span>` : ''}
                </div>
                <div class="cart-actions mt-3">
                    <button class="btn btn-danger btn-custom btn-sm" onclick="cart.clearCart()">
                        <i class="fas fa-trash me-1"></i>Limpiar
                    </button>
                    <button class="btn btn-success btn-custom" onclick="proceedToSummary()" ${items.length === 0 ? 'disabled' : ''}>
                        <i class="fas fa-arrow-right me-1"></i> Continuar
                    </button>
                </div>
            </div>
        `;
        
        console.log('Setting cart container HTML:', html);
        cartContainer.innerHTML = html;
        
        // Mostrar botones de acción externos cuando hay productos
        const cartActions = document.getElementById('cart-actions');
        if (cartActions) {
            cartActions.style.display = 'block';
        }
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
    console.log('Document ready - Initializing cart system');
    
    // Inicializar carrito
    cart = new ProductCart();
    console.log('Cart initialized:', cart);
    
    // Hacer funciones y carrito disponibles globalmente
    window.cart = cart;
    window.addToCartById = addToCartById;
    window.addToCart = addToCart;
    window.adjustQuantity = adjustQuantity;
    window.proceedToSummary = proceedToSummary;
    console.log('Global functions and cart assigned');
    
    // Event listener para botones con data-attributes se maneja en carrito_compras.php
    // para evitar conflictos y permitir la funcionalidad de eliminar
    
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
                    // renderProducts(response.products); // COMENTADO: Conflicto con displayProducts en carrito_compras.php
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
                $('#searchStats').hide();
            });
        } else {
            $('#result').html('');
            $('#searchStats').hide();
        }
    });
    
    // Function to be called by inline JavaScript if needed
    window.performSearch = function(searchTerm) {
        $('#search').val(searchTerm).trigger('keyup');
    };
});

// Cache de productos para acceso por ID
let productsCache = {};

// Renderizar productos en la interfaz
function renderProducts(products) {
    console.log('renderProducts called with:', products);
    
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
    
    // Limpiar cache y agregar productos
    productsCache = {};
    
    let html = '<div class="products-grid">';
    
    products.forEach(product => {
        // Agregar al cache
        productsCache[product.id] = product;
        console.log('Product added to cache:', product.id, product.title);
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
                            <input type="text" class="form-control text-center quantity-input" 
                                   id="qty-${product.id}" value="1" min="1" max="${product.stock}" 
                                   ${!product.available ? 'disabled' : ''}>
                            <button class="btn btn-outline-secondary" type="button" onclick="adjustQuantity('${product.id}', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button class="btn btn-success btn-sm add-to-cart-btn" 
                            onclick="console.log('Button clicked for product:', '${product.id}'); addToCartById('${product.id}');"
                            ${!product.available ? 'disabled' : ''}>
                        <i class="fas fa-cart-plus me-1"></i>
                        ${product.available ? 'Agregar' : 'Sin Stock'}
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    console.log('Generated HTML for products:', html.substring(0, 500) + '...');
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

// Agregar producto al carrito por ID
function addToCartById(productId) {
    console.log('addToCartById called with ID:', productId);
    console.log('Products cache:', productsCache);
    console.log('Cart instance:', cart);
    
    const product = productsCache[productId];
    if (!product) {
        console.error('Product not found in cache:', productId);
        return;
    }
    
    console.log('Product found:', product);
    
    const quantityInput = document.getElementById(`qty-${product.id}`);
    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
    
    console.log('Quantity to add:', quantity);
    
    if (quantity > product.stock) {
        console.warn('Quantity exceeds stock:', quantity, 'vs', product.stock);
        cart.showNotification(`Solo hay ${product.stock} unidades disponibles`, 'warning');
        return;
    }
    
    cart.addProduct(product, quantity);
    
    // Reset quantity to 1
    if (quantityInput) {
        quantityInput.value = 1;
    }
}

// Mantener función original para compatibilidad
function addToCart(product) {
    console.log('addToCart (legacy) called with:', product);
    addToCartById(product.id);
}

// Limpiar carrito
function clearCart() {
    if (confirm('¿Estás seguro de que quieres limpiar el carrito? Se perderán todos los productos seleccionados.')) {
        cart.clearCart();
        cart.showNotification('Carrito limpiado correctamente', 'success');
    }
}

// Proceder al resumen (paso 4)
function proceedToSummary() {
    const items = cart.getCartItems();
    
    if (items.length === 0) {
        cart.showNotification('Debes agregar al menos un producto', 'warning');
        return;
    }

    // Recuperar datos persistidos del formulario (pasos 1/2)
    const persistedFormData = (window.VentasUtils && window.VentasUtils.getFormData)
        ? window.VentasUtils.getFormData()
        : (() => {
            try {
                const raw = localStorage.getItem('ventas_wizard_form_data') || localStorage.getItem('ventas_form_data');
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        })();
    
    // Obtener datos del cliente desde la sesión/formulario si están disponibles
    const customerData = {
        order_id: $('#_order_id').val(),
        nombre1: window.customerInfo?.nombre1 || '',
        nombre2: window.customerInfo?.nombre2 || '',
        dni: window.customerInfo?.dni || window.customerInfo?.billing_id || '',
        _billing_email: window.customerInfo?._billing_email || '',
        _billing_phone: window.customerInfo?._billing_phone || '',
        _shipping_address_1: window.customerInfo?._shipping_address_1 || '',
        _shipping_address_2: window.customerInfo?._shipping_address_2 || '',
        _shipping_city: window.customerInfo?._shipping_city || '',
        _shipping_state: window.customerInfo?._shipping_state || '',
        _billing_neighborhood: window.customerInfo?._billing_neighborhood || '',
        timestamp: new Date().toISOString()
    };
    
    // Guardar datos del carrito para el paso 4
    const orderData = {
        order_id: $('#_order_id').val(),
        products: items,
        total_items: cart.getTotalItems(),
        total_price: cart.getTotalPrice(),
        _order_shipping: persistedFormData?._order_shipping || '',
        _cart_discount: persistedFormData?._cart_discount || '',
        _payment_method_title: persistedFormData?._payment_method_title || '',
        post_expcerpt: persistedFormData?.post_expcerpt || '',
        timestamp: new Date().toISOString()
    };
    
    // Guardar ambos conjuntos de datos
    localStorage.setItem('ventas_order_summary', JSON.stringify(orderData));
    localStorage.setItem('ventas_customer_data', JSON.stringify(customerData));
    
    // Redirigir al paso 4 (resumen)
    window.location.href = 'resumen_pedido.php';
}

// Función global para ver detalles del producto (abre en nueva pestaña)
function viewProductDetails(productId, productTitle, productUrl) {
    console.log('viewProductDetails called with:', {
        productId: productId,
        productTitle: productTitle,
        productUrl: productUrl
    });
    
    if (productUrl && productUrl !== '#' && productUrl !== '') {
        console.log('Opening URL in new tab:', productUrl);
        window.open(productUrl, '_blank');
    } else {
        console.warn('No valid URL provided for product:', productTitle);
        alert(`No se puede abrir el producto "${productTitle}" porque no tiene un enlace válido.`);
    }
}
