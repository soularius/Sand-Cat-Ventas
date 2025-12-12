// Sistema de resumen de pedido
class OrderSummary {
    constructor() {
        this.orderData = null;
        this.customerData = null;
        this.init();
    }
    
    init() {
        this.loadOrderData();
        this.loadCustomerData();
    }
    
    // Cargar datos del pedido desde localStorage
    loadOrderData() {
        try {
            const stored = localStorage.getItem('ventas_order_summary');
            if (stored) {
                this.orderData = JSON.parse(stored);
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
            const stored = localStorage.getItem('ventas_customer_data');
            if (stored) {
                this.customerData = JSON.parse(stored);
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
        
        let html = '<div class="products-list">';
        
        products.forEach((product, index) => {
            const price = product.sale_price || product.price;
            const subtotal = price * product.quantity;
            const hasDiscount = product.sale_price && product.sale_price < product.regular_price;
            
            html += `
                <div class="product-summary-item" data-product-id="${product.id}">
                    <div class="product-summary-header">
                        <div class="product-info">
                            <h6 class="product-name">${product.title}</h6>
                            ${product.sku ? `<small class="text-muted">SKU: ${product.sku}</small>` : ''}
                        </div>
                        <div class="product-quantity">
                            <span class="quantity-badge">${product.quantity}</span>
                        </div>
                    </div>
                    
                    <div class="product-summary-details">
                        <div class="price-breakdown">
                            <div class="unit-price">
                                <span class="label">Precio unitario:</span>
                                <span class="value">$${price.toLocaleString('es-CO')}</span>
                                ${hasDiscount ? `<span class="original-price">$${product.regular_price.toLocaleString('es-CO')}</span>` : ''}
                            </div>
                            <div class="subtotal">
                                <span class="label">Subtotal:</span>
                                <span class="value font-weight-bold">$${subtotal.toLocaleString('es-CO')}</span>
                            </div>
                        </div>
                        
                        ${hasDiscount ? '<div class="discount-indicator"><i class="fas fa-tag text-success me-1"></i>Producto en oferta</div>' : ''}
                    </div>
                </div>
            `;
            
            // Agregar separador si no es el último producto
            if (index < products.length - 1) {
                html += '<hr class="product-separator">';
            }
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
                        <p class="mb-1">${customer._shipping_address_1 || ''}</p>
                        ${customer._shipping_address_2 ? `<p class="mb-1">${customer._shipping_address_2}</p>` : ''}
                        ${customer._billing_neighborhood ? `<p class="mb-1">Barrio: ${customer._billing_neighborhood}</p>` : ''}
                        <p class="mb-0">
                            ${customer._shipping_city || ''}, ${customer._shipping_state || ''}
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
        
        const html = `
            <div class="order-info">
                <div class="info-item">
                    <i class="fas fa-calendar text-muted me-2"></i>
                    <div>
                        <strong>Fecha:</strong><br>
                        <span class="text-muted">${orderDate}</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-user-tie text-muted me-2"></i>
                    <div>
                        <strong>Vendedor:</strong><br>
                        <span class="text-muted">${window.currentUser || 'Sistema'}</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-truck text-muted me-2"></i>
                    <div>
                        <strong>Método de Envío:</strong><br>
                        <span class="text-muted">Por definir</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-credit-card text-muted me-2"></i>
                    <div>
                        <strong>Método de Pago:</strong><br>
                        <span class="text-muted">Por definir</span>
                    </div>
                </div>
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
