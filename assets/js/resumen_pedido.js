// Sistema de resumen de pedido
class OrderSummary {
    constructor() {
        this.orderData = null;
        this.customerData = null;
        this.formData = null;
        this.init();
    }
    
    init() {
        // Importante: cargar primero formData, ya que renderOrderDetails depende de esto
        this.loadFormData();
        this.loadOrderData();
        this.loadCustomerData();
    }

    // Cargar datos del formulario (persistencia) desde localStorage
    loadFormData() {
        try {
            this.formData = (window.VentasUtils && window.VentasUtils.getFormData)
                ? window.VentasUtils.getFormData()
                : null;
        } catch (e) {
            this.formData = null;
        }

        // Si ya se renderizó el resumen (orderData existe), re-renderizar detalles del pedido
        // para asegurar que se pinten los valores cuando formData ya esté disponible.
        try {
            if (this.orderData) {
                this.renderOrderDetails();
            }
        } catch (e) {
            // no-op
        }
    }
    
    // Cargar datos del pedido desde localStorage
    loadOrderData() {
        try {
            const storedData = (window.VentasUtils && window.VentasUtils.getOrderSummary)
                ? window.VentasUtils.getOrderSummary()
                : null;
            if (storedData) {
                this.orderData = storedData;
                const orderIdField = document.getElementById('_order_id');
                if (orderIdField && this.orderData && this.orderData.order_id) {
                    orderIdField.value = this.orderData.order_id;
                }
                this.renderOrderSummary();
            } else {
                this.showError('No se encontraron datos del pedido. Redirigiendo...');
                setTimeout(() => {
                    window.location.href = 'bproducto.php';
                }, 2000);
            }
        } catch (e) {
            console.error('Error cargando datos del pedido:', e);
            this.showError('Error cargando datos del pedido');
        }
    }
    
    // Cargar datos del cliente desde localStorage
    loadCustomerData() {
        try {
            const storedData = (window.VentasUtils && window.VentasUtils.getCustomerData)
                ? window.VentasUtils.getCustomerData()
                : null;
            if (storedData) {
                this.customerData = storedData;
                this.renderCustomerInfo();
            } else {
                // Si no hay datos del cliente, mostrar información básica
                this.renderBasicCustomerInfo();
            }
        } catch (e) {
            console.error('Error cargando datos del cliente:', e);
            this.renderBasicCustomerInfo();
        }
    }
    
    // Renderizar resumen del pedido
    renderOrderSummary() {
        if (!this.orderData || !this.orderData.products) {
            this.showError('No hay productos en el pedido');
            return;
        }
        
        // Actualizar contador de productos
        document.getElementById('products-count').textContent = this.orderData.total_items;
        
        // Renderizar productos
        this.renderProducts();
        
        // Renderizar total
        this.renderOrderTotal();
        
        // Renderizar detalles del pedido
        this.renderOrderDetails();
        
        // Habilitar botón de crear pedido
        document.getElementById('create-order-btn').disabled = false;
    }
    
    // Renderizar productos
    renderProducts() {
        const container = document.getElementById('products-summary');
        const products = this.orderData.products;
        
        if (!products || products.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-box-open text-muted"></i>
                    <p>No hay productos seleccionados</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="row">';
        
        products.forEach((product) => {
            const qty = parseInt(product.quantity || 0, 10);
            const regularPrice = (product.regular_price !== null && product.regular_price !== undefined) ? Number(product.regular_price) : 0;
            const salePrice = (product.sale_price !== null && product.sale_price !== undefined) ? Number(product.sale_price) : null;
            const hasDiscount = (salePrice !== null) && (regularPrice > 0) && (salePrice < regularPrice);
            const finalUnitPrice = hasDiscount ? salePrice : Number(product.price || 0);
            const subtotal = finalUnitPrice * qty;
            const imageUrl = product.image_url || '';

            const safeTitle = (product.title || '').toString().replace(/'/g, "\\'").replace(/\"/g, '\\"');
            const safePermalink = (product.permalink || '#').toString().replace(/'/g, "\\'").replace(/\"/g, '\\"');

            html += `
                <div class="col-md-6 col-lg-4 mb-6">
                    <div class="card product-card position-relative" data-product-id="${product.id}">
                        <div class="position-relative overflow-hidden">
                            ${imageUrl ? `
                                <img src="${imageUrl}" class="card-img-top" alt="${safeTitle}" style="height: 220px; object-fit: cover; transition: transform 0.3s ease;" loading="lazy">
                            ` : `
                                <div class="d-flex align-items-center justify-content-center bg-light" style="height: 220px;">
                                    <i class="fas fa-image text-muted fa-3x"></i>
                                </div>
                            `}

                            ${hasDiscount ? '<div class="position-absolute top-0 end-0 m-3"><span class="badge bg-danger fs-6 px-3 py-2 rounded-pill shadow">¡Oferta!</span></div>' : ''}
                        </div>

                        <div class="card-body d-flex flex-column p-3 card-body-product">
                            <h6 class="card-title fw-bold mb-2 product-title-clickable" 
                                onclick="window.open('${safePermalink}', '_blank')"
                                title="Click para ver detalles del producto"
                                style="color: #2c3e50; line-height: 1.3; cursor: pointer; transition: all 0.3s ease;">${product.title}</h6>

                            ${product.sku ? `
                                <div class="mb-2">
                                    <span class="product-sku badge bg-light text-dark">SKU: ${product.sku}</span>
                                </div>
                            ` : ''}

                            ${hasDiscount ? `
                                <div class="price mb-3">
                                    <div class="d-flex align-items-center justify-content-between box-prices">
                                        <div>
                                            <span class="sale-price text-danger fw-bold fs-5">$${finalUnitPrice.toLocaleString('es-CO')}</span>
                                            <span class="regular-price text-muted text-decoration-line-through ms-2 small">$${regularPrice.toLocaleString('es-CO')}</span>
                                        </div>
                                        <span class="badge bg-danger rounded-pill">Oferta</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="price mb-3">
                                    <div class="d-flex align-items-center justify-content-between box-prices">
                                        <span class="current-price text-primary fw-bold fs-5">$${finalUnitPrice.toLocaleString('es-CO')}</span>
                                    </div>
                                </div>
                            `}

                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Subtotal: <strong>$${subtotal.toLocaleString('es-CO')}</strong></small>
                            </div>

                            <div class="product-actions d-flex justify-content-between align-items-center mt-auto">
                                <button class="btn btn-secondary btn-custom btn-circular btn-sm position-absolute text-white" 
                                        onclick="shareProductLink('${safePermalink}', '${safeTitle}')" 
                                        title="Compartir enlace">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                                <span class="badge badge-qty bg-success bg-custom rounded-pill position-absolute" title="Cantidad">${qty}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    // Renderizar total del pedido
    renderOrderTotal() {
        const container = document.getElementById('order-total');
        const products = this.orderData.products;
        
        // Calcular totales
        let subtotal = 0;
        let totalDiscount = 0;
        
        products.forEach(product => {
            const price = product.sale_price || product.price;
            const originalPrice = product.regular_price;
            const quantity = product.quantity;
            
            subtotal += originalPrice * quantity;
            
            if (product.sale_price && product.sale_price < originalPrice) {
                totalDiscount += (originalPrice - product.sale_price) * quantity;
            }
        });
        
        const finalTotal = subtotal - totalDiscount;
        
        let html = `
            <div class="order-totals">
                <div class="total-line">
                    <span class="label">Subtotal:</span>
                    <span class="value">$${subtotal.toLocaleString('es-CO')}</span>
                </div>
        `;
        
        if (totalDiscount > 0) {
            html += `
                <div class="total-line discount-line">
                    <span class="label text-success">
                        <i class="fas fa-tag me-1"></i>Descuentos:
                    </span>
                    <span class="value text-success">-$${totalDiscount.toLocaleString('es-CO')}</span>
                </div>
            `;
        }
        
        html += `
                <hr class="total-separator">
                <div class="total-line final-total">
                    <span class="label">Total Final:</span>
                    <span class="value">$${finalTotal.toLocaleString('es-CO')}</span>
                </div>
                
                <div class="total-summary mt-3">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <i class="fas fa-box text-primary"></i>
                            <span>${this.orderData.total_items} productos</span>
                        </div>
                        ${totalDiscount > 0 ? `
                        <div class="stat-item">
                            <i class="fas fa-percentage text-success"></i>
                            <span>$${totalDiscount.toLocaleString('es-CO')} ahorrados</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    // Renderizar información del cliente
    renderCustomerInfo() {
        const container = document.getElementById('customer-info');
        
        if (!this.customerData) {
            this.renderBasicCustomerInfo();
            return;
        }
        
        const customer = this.customerData;

        const stateName = (window.VentasUtils && window.VentasUtils.getColombiaStateName)
            ? window.VentasUtils.getColombiaStateName(customer._shipping_state)
            : (customer._shipping_state || '');
        
        const html = `
            <div class="customer-details">
                <div class="customer-name">
                    <h6><i class="fas fa-user me-2"></i>${customer.nombre1 || ''} ${customer.nombre2 || ''}</h6>
                </div>
                
                <div class="customer-contact">
                    <div class="contact-item">
                        <i class="fas fa-id-card text-muted me-2"></i>
                        <span>${customer.dni || customer.billing_id || 'No especificado'}</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope text-muted me-2"></i>
                        <span>${customer._billing_email || 'No especificado'}</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone text-muted me-2"></i>
                        <span>${customer._billing_phone || 'No especificado'}</span>
                    </div>
                </div>
                
                <div class="customer-address">
                    <h6 class="address-title"><i class="fas fa-map-marker-alt me-2"></i>Dirección de Envío</h6>
                    <div class="address-details">
                        <p class="mb-1 text-uppercase">${customer._shipping_address_1 || ''}</p>
                        ${customer._shipping_address_2 ? `<p class="mb-1 text-uppercase">${customer._shipping_address_2}</p>` : ''}
                        ${customer._billing_neighborhood ? `<p class="mb-1 text-uppercase">Barrio: ${customer._billing_neighborhood}</p>` : ''}
                        <p class="mb-0 text-uppercase">
                            ${customer._shipping_city || ''}, ${stateName || ''}
                        </p>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    // Renderizar información básica del cliente
    renderBasicCustomerInfo() {
        const container = document.getElementById('customer-info');
        
        container.innerHTML = `
            <div class="customer-placeholder">
                <div class="placeholder-icon">
                    <i class="fas fa-user-circle text-muted"></i>
                </div>
                <p class="text-muted">Información del cliente no disponible</p>
                <small class="text-muted">Los datos se completarán al crear el pedido</small>
            </div>
        `;
    }
    
    // Renderizar detalles del pedido
    renderOrderDetails() {
        const container = document.getElementById('order-details');

        const orderDate = new Date().toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const paymentMethodTitle = (this.formData && this.formData._payment_method_title)
            ? this.formData._payment_method_title
            : ((this.orderData && this.orderData._payment_method_title)
                ? this.orderData._payment_method_title
                : ((this.customerData && this.customerData._payment_method_title) ? this.customerData._payment_method_title : ''));

        const orderShippingRaw = (this.formData && this.formData._order_shipping)
            ? this.formData._order_shipping
            : ((this.orderData && this.orderData._order_shipping) ? this.orderData._order_shipping : '');
        const cartDiscountRaw = (this.formData && this.formData._cart_discount)
            ? this.formData._cart_discount
            : ((this.orderData && this.orderData._cart_discount) ? this.orderData._cart_discount : '');
        const orderNotes = (this.formData && this.formData.post_expcerpt)
            ? this.formData.post_expcerpt
            : ((this.orderData && this.orderData.post_expcerpt) ? this.orderData.post_expcerpt : '');

        console.log('[OrderSummary] order details sources:', {
            hasFormData: !!this.formData,
            hasOrderData: !!this.orderData,
            hasCustomerData: !!this.customerData,
            orderShippingRaw,
            cartDiscountRaw,
            paymentMethodTitle,
            orderNotes
        });

        const parseMoney = (value) => {
            if (value === null || value === undefined) return null;
            const cleaned = value.toString().replace(/[^\d]/g, '');
            if (!cleaned) return null;
            const n = parseInt(cleaned, 10);
            return Number.isFinite(n) ? n : null;
        };

        const computeDiscountFromProducts = () => {
            const products = this.orderData && Array.isArray(this.orderData.products)
                ? this.orderData.products
                : [];

            return products.reduce((acc, p) => {
                const qty = parseInt(p.quantity || 0, 10);
                if (!qty) return acc;

                const regular = parseMoney(p.regular_price ?? p.price);
                const sale = parseMoney(p.sale_price);
                if (regular === null || sale === null) return acc;
                if (sale >= regular) return acc;

                return acc + ((regular - sale) * qty);
            }, 0);
        };

        const orderShipping = parseMoney(orderShippingRaw);
        const cartDiscount = parseMoney(cartDiscountRaw);
        const cartDiscountFromProducts = computeDiscountFromProducts();
        const discountToShow = (cartDiscountFromProducts && cartDiscountFromProducts > 0)
            ? cartDiscountFromProducts
            : cartDiscount;

        const orderId = (this.orderData && this.orderData.order_id)
            ? this.orderData.order_id
            : (this.customerData && this.customerData.order_id ? this.customerData.order_id : '');

        const html = `
            <div class="order-info">
                <div class="info-item">
                    <i class="fas fa-hashtag text-muted me-2"></i>
                    <div>
                        <strong>Pedido: </strong>
                        <span class="text-muted">${orderId ? '#' + orderId : 'No disponible'}</span>
                    </div>
                </div>
                <hr>
                <div class="info-item">
                    <i class="fas fa-calendar text-muted me-2"></i>
                    <div>
                        <strong>Fecha: </strong>
                        <span class="text-muted">${orderDate}</span>
                    </div>
                </div>
                <hr>
                <div class="info-item">
                    <i class="fas fa-user-tie text-muted me-2"></i>
                    <div>
                        <strong>Vendedor: </strong>
                        <span class="text-muted">${window.currentUser || 'Sistema'}</span>
                    </div>
                </div>
                <hr>
                <div class="info-item">
                    <i class="fas fa-truck text-muted me-2"></i>
                    <div>
                        <strong>Costo de Envío: </strong>
                        <span class="text-muted">${(orderShipping !== null) ? ('$' + orderShipping.toLocaleString('es-CO')) : 'No especificado'}</span>
                    </div>
                </div>
                <hr>
                <div class="info-item">
                    <i class="fas fa-tags text-muted me-2"></i>
                    <div>
                        <strong>Descuento: </strong>
                        <span class="text-muted">${(discountToShow !== null && discountToShow !== undefined) ? ('$' + Number(discountToShow).toLocaleString('es-CO')) : 'No especificado'}</span>
                    </div>
                </div>
                <hr>
                <div class="info-item">
                    <i class="fas fa-credit-card text-muted me-2"></i>
                    <div>
                        <strong>Método de Pago: </strong>
                        <span class="text-muted">${paymentMethodTitle || 'No especificado'}</span>
                    </div>
                </div>
                ${orderNotes ? `
                <hr>
                <div class="info-item">
                    <i class="fas fa-comment-alt text-muted me-2"></i>
                    <div>
                        <strong>Notas:</strong>
                        <span class="text-muted">${orderNotes}</span>
                    </div>
                </div>
                ` : ''}
            </div>
        `;

        container.innerHTML = html;
    }
    
    // Mostrar error
    showError(message) {
        const containers = ['customer-info', 'products-summary', 'order-total', 'order-details'];
        
        containers.forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <p class="text-muted">${message}</p>
                    </div>
                `;
            }
        });
    }
    
    // Mostrar notificación
    showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
}

function shareProductLink(productUrl, productTitle) {
    try {
        if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(productUrl || '').then(() => {
                if (window.orderSummary && typeof window.orderSummary.showNotification === 'function') {
                    window.orderSummary.showNotification('Enlace copiado al portapapeles', 'success');
                }
            }).catch(() => {
                prompt('Copia el enlace:', productUrl || '');
            });
            return;
        }

        prompt('Copia el enlace:', productUrl || '');
    } catch (e) {
        prompt('Copia el enlace:', productUrl || '');
    }
}

// Instancia global
let orderSummary;

$(document).ready(function() {
    // Inicializar resumen de pedido
    orderSummary = new OrderSummary();
});

// Volver a productos
function goBackToProducts() {
    if (confirm('¿Estás seguro de que quieres volver? Los cambios no guardados se perderán.')) {
        const orderId = orderSummary && orderSummary.orderData ? orderSummary.orderData.order_id : '';
        if (orderId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'bproducto.php';
            form.style.display = 'none';

            const orderIdInput = document.createElement('input');
            orderIdInput.type = 'hidden';
            orderIdInput.name = '_order_id';
            orderIdInput.value = orderId;
            form.appendChild(orderIdInput);

            document.body.appendChild(form);
            form.submit();
            return;
        }

        window.location.href = 'bproducto.php';
    }
}

// Crear pedido final
function createFinalOrder() {
    if (!orderSummary.orderData || !orderSummary.orderData.products || orderSummary.orderData.products.length === 0) {
        orderSummary.showNotification('No hay productos para crear el pedido', 'warning');
        return;
    }
    
    // Mostrar modal de carga
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
    
    // Preparar datos para envío
    const orderData = {
        order_id: orderSummary.orderData.order_id,
        products: orderSummary.orderData.products,
        total_items: orderSummary.orderData.total_items,
        total_price: orderSummary.orderData.total_price,
        customer_data: orderSummary.customerData,
        timestamp: new Date().toISOString()
    };
    
    // Enviar datos al servidor
    $.ajax({
        type: 'POST',
        url: 'create_final_order.php',
        data: {
            order_data: JSON.stringify(orderData)
        },
        dataType: 'json',
        timeout: 30000
    })
    .done(function(response) {
        loadingModal.hide();
        
        if (response.success) {
            // Limpiar localStorage
            localStorage.removeItem('ventas_cart_products');
            localStorage.removeItem('ventas_order_summary');
            
            // Mostrar modal de éxito
            document.getElementById('order-number').textContent = response.order_id;
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            orderSummary.showNotification(response.message || 'Error creando el pedido', 'danger');
        }
    })
    .fail(function(xhr, status, error) {
        loadingModal.hide();
        console.error('Error creando pedido:', status, error);
        orderSummary.showNotification('Error de conexión. Por favor intenta de nuevo.', 'danger');
    });
}

// Ir a lista de pedidos
function goToOrderList() {
    window.location.href = 'adminventas.php';
}

// Crear nuevo pedido
function createNewOrder() {
    // Limpiar cualquier dato residual
    localStorage.removeItem('ventas_cart_products');
    localStorage.removeItem('ventas_order_summary');
    localStorage.removeItem('ventas_customer_data');
    
    window.location.href = 'adminventas.php';
}
