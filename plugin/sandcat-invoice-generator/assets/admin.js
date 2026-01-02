jQuery(document).ready(function($) {
    
    // Manejar clic en botón generar factura
    $('#generate-invoice').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        var isRegenerate = false;
        
        // Mostrar loading
        $('#invoice-loading').show();
        $('#invoice-result').empty();
        $button.prop('disabled', true);
        
        // Datos para AJAX
        var ajaxData = {
            action: 'generate_sandcat_invoice',
            nonce: sandcat_invoice_ajax.nonce,
            order_id: orderId,
            regenerate: isRegenerate ? 'true' : 'false'
        };
        
        // Realizar petición AJAX
        $.ajax({
            url: sandcat_invoice_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                $('#invoice-loading').hide();
                $button.prop('disabled', false);
                
                if (response.success) {
                    // Mostrar mensaje de éxito
                    var successHtml = '<div class="notice notice-success inline">' +
                        '<p><strong>' + sandcat_invoice_ajax.messages.success + '</strong></p>' +
                        '<p>Número de factura: ' + response.data.invoice_number + '</p>' +
                        '<p><a href="' + response.data.pdf_url + '" target="_blank" class="button button-secondary">' +
                        'Descargar PDF</a></p>' +
                        '</div>';
                    
                    $('#invoice-result').html(successHtml);
                    
                    // Si era generación nueva, actualizar el contenido del metabox
                    if (!isRegenerate) {
                        updateMetaboxContent(orderId, response.data.invoice_number);
                    }
                    
                } else {
                    // Mostrar mensaje de error
                    var errorHtml = '<div class="notice notice-error inline">' +
                        '<p><strong>' + sandcat_invoice_ajax.messages.error + '</strong></p>' +
                        '<p>' + (response.data.message || 'Error desconocido') + '</p>' +
                        '</div>';
                    
                    $('#invoice-result').html(errorHtml);
                }
            },
            error: function(xhr, status, error) {
                $('#invoice-loading').hide();
                $button.prop('disabled', false);
                
                var errorHtml = '<div class="notice notice-error inline">' +
                    '<p><strong>' + sandcat_invoice_ajax.messages.error + '</strong></p>' +
                    '<p>Error de conexión: ' + error + '</p>' +
                    '</div>';
                
                $('#invoice-result').html(errorHtml);
                
                console.error('Error AJAX:', xhr.responseText);
            }
        });
    });
    
    /**
     * Actualizar contenido del metabox después de generar factura
     */
    function updateMetaboxContent(orderId, invoiceNumber) {
        var currentDate = new Date().toLocaleDateString('es-ES');
        
        var newContent = '<div class="notice notice-info inline">' +
            '<p><strong>Factura existente:</strong></p>' +
            '<p>Número: ' + invoiceNumber + '</p>' +
            '<p>Fecha: ' + currentDate + '</p>' +
            '</div>' +
            '<button type="button" class="button button-secondary" id="view-invoice" data-order-id="' + orderId + '">' +
            'Ver Factura' +
            '</button>' +
            '<div id="invoice-loading" style="display:none;">' +
            '<p><span class="spinner is-active"></span> Generando factura...</p>' +
            '</div>' +
            '<div id="invoice-result"></div>';
        
        $('#sandcat-invoice-container').html(newContent);
    }
    
    /**
     * Mostrar/ocultar detalles adicionales
     */
    $(document).on('click', '.toggle-invoice-details', function(e) {
        e.preventDefault();
        $('.invoice-details-extra').slideToggle();
        
        var $link = $(this);
        var currentText = $link.text();
        var newText = currentText === 'Ver más detalles' ? 'Ver menos detalles' : 'Ver más detalles';
        $link.text(newText);
    });
    
    /**
     * Manejar clic en botón ver factura
     */
    $(document).on('click', '#view-invoice', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        
        // Mostrar loading
        $button.prop('disabled', true);
        $button.text('Cargando...');
        
        // Obtener URL del PDF
        $.ajax({
            url: sandcat_invoice_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_invoice_pdf_url',
                nonce: sandcat_invoice_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                $button.prop('disabled', false);
                $button.text('Ver Factura');
                
                if (response.success && response.data.pdf_url) {
                    // Abrir PDF en nueva pestaña
                    window.open(response.data.pdf_url, '_blank');
                } else {
                    alert('Error: No se pudo obtener la URL del PDF. ' + (response.data.message || ''));
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $button.text('Ver Factura');
                alert('Error de conexión al obtener el PDF.');
            }
        });
    });
    
    /**
     * Auto-refresh del metabox cada 30 segundos para verificar cambios
     */
    function refreshInvoiceStatus() {
        var orderId = $('#generate-invoice, #view-invoice').data('order-id');
        if (!orderId) return;
        
        $.ajax({
            url: sandcat_invoice_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_invoice_status',
                nonce: sandcat_invoice_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success && response.data.has_invoice) {
                    // Actualizar si hay cambios
                    var currentNumber = $('.notice-info p:contains("Número:")').text();
                    if (currentNumber.indexOf(response.data.invoice_number) === -1) {
                        location.reload(); // Recargar página si hay cambios
                    }
                }
            },
            error: function() {
                // Silenciar errores de auto-refresh
            }
        });
    }
    
    // Auto-refresh cada 30 segundos
    setInterval(refreshInvoiceStatus, 30000);
    
    /**
     * Validar formulario antes de generar factura
     */
    function validateOrderForInvoice(orderId) {
        // Verificar que el pedido tenga productos
        var hasProducts = $('.woocommerce_order_items_wrapper .woocommerce_order_items tbody tr').length > 0;
        if (!hasProducts) {
            alert('El pedido debe tener al menos un producto para generar la factura.');
            return false;
        }
        
        // Verificar que tenga información de cliente
        var hasCustomer = $('#_billing_first_name').val() || $('#_billing_last_name').val();
        if (!hasCustomer) {
            alert('El pedido debe tener información del cliente para generar la factura.');
            return false;
        }
        
        return true;
    }
    
    /**
     * Agregar validación antes de generar factura
     */
    $(document).on('click', '#generate-invoice', function(e) {
        var orderId = $(this).data('order-id');
        if (!validateOrderForInvoice(orderId)) {
            e.preventDefault();
            return false;
        }
    });
    
    /**
     * Mostrar progreso de generación
     */
    function showProgress(message) {
        $('#invoice-loading p').html('<span class="spinner is-active"></span> ' + message);
    }
    
    /**
     * Manejar estados de carga con mensajes dinámicos
     */
    var loadingMessages = [
        'Conectando a base de datos...',
        'Obteniendo datos del pedido...',
        'Generando número de factura...',
        'Creando documento PDF...',
        'Guardando factura...',
        'Finalizando proceso...'
    ];
    
    var messageIndex = 0;
    var progressInterval;
    
    function startProgressMessages() {
        messageIndex = 0;
        showProgress(loadingMessages[messageIndex]);
        
        progressInterval = setInterval(function() {
            messageIndex++;
            if (messageIndex < loadingMessages.length) {
                showProgress(loadingMessages[messageIndex]);
            } else {
                clearInterval(progressInterval);
            }
        }, 1000);
    }
    
    function stopProgressMessages() {
        if (progressInterval) {
            clearInterval(progressInterval);
        }
    }
    
    // Integrar mensajes de progreso en el AJAX
    $('#generate-invoice, #regenerate-invoice').on('click', function(e) {
        startProgressMessages();
    });
    
    $(document).ajaxComplete(function() {
        stopProgressMessages();
    });
});
