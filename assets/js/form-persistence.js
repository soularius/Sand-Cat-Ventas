/**
 * Sistema de Persistencia de Formularios
 * Versión: 1.0
 * Funcionalidades:
 * - Auto-guardado en localStorage
 * - Recuperación automática al cargar página
 * - Limpieza selectiva de datos
 * - Compatibilidad con todos los campos del formulario
 */

class FormPersistence {
    constructor(formId, storageKey = 'ventas_form_data') {
        this.formId = formId;
        this.storageKey = storageKey;
        this.form = document.getElementById(formId);
        this.excludeFields = ['_token', 'csrf_token']; // Campos a excluir
        
        if (this.form) {
            this.init();
        }
    }

    init() {
        // Cargar datos guardados al inicializar
        this.loadFormData();
        
        // Guardar datos automáticamente en cambios
        this.attachAutoSave();
        
        // Limpiar al enviar formulario
        this.attachFormSubmit();
        
        console.log('FormPersistence inicializado para:', this.formId);
    }
    
    /**
     * Guardar datos del formulario en localStorage
     */
    saveFormData() {
        if (!this.form) return;
        
        const formData = {};
        const elements = this.form.elements;
        
        for (let element of elements) {
            if (this.shouldSaveField(element)) {
                const value = this.getFieldValue(element);
                if (value !== null && value !== '') {
                    formData[element.name] = value;
                }
            }
        }
        
        // Agregar timestamp para control de expiración
        formData._timestamp = Date.now();
        formData._step = this.getCurrentStep();
        
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(formData));
            console.log('Datos guardados:', Object.keys(formData).length, 'campos');
        } catch (e) {
            console.warn('Error guardando en localStorage:', e);
        }
    }
    
    /**
     * Cargar datos del formulario desde localStorage
     */
    loadFormData() {
        console.log('Cargando datos desde localStorage para:', this.formId);
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Verificar si los datos no han expirado (24 horas)
            const maxAge = 24 * 60 * 60 * 1000; // 24 horas
            if (formData._timestamp && (Date.now() - formData._timestamp) > maxAge) {
                this.clearFormData();
                return;
            }
            
            let fieldsLoaded = 0;
            
            // Restaurar valores en los campos
            for (const [fieldName, value] of Object.entries(formData)) {
                if (fieldName.startsWith('_')) continue; // Skip metadata
                
                const element = this.form.elements[fieldName];
                if (element && this.shouldSaveField(element)) {
                    console.log('Restaurando campo:', fieldName, 'valor:', value);
                    this.setFieldValue(element, value);
                    fieldsLoaded++;
                }
            }
            
            if (fieldsLoaded > 0) {
                console.log('Datos restaurados:', fieldsLoaded, 'campos');
                // Obtener DNI para la notificación
                const dni = formData.billing_id || '';
                this.showRestoreNotification(dni);
            }
            
        } catch (e) {
            console.warn('Error cargando desde localStorage:', e);
            this.clearFormData();
        }
    }
    
    /**
     * Determinar si un campo debe guardarse
     */
    shouldSaveField(element) {
        // Excluir campos específicos
        if (this.excludeFields.includes(element.name)) return false;
        
        // Excluir campos sin nombre
        if (!element.name) return false;
        
        // Excluir botones y submits
        if (['button', 'submit', 'reset'].includes(element.type)) return false;
        
        // Excluir campos readonly (como país fijo)
        if (element.readOnly) return false;
        
        return true;
    }
    
    /**
     * Obtener valor del campo según su tipo
     */
    getFieldValue(element) {
        switch (element.type) {
            case 'checkbox':
                return element.checked;
            case 'radio':
                return element.checked ? element.value : null;
            case 'select-multiple':
                return Array.from(element.selectedOptions).map(opt => opt.value);
            default:
                return element.value;
        }
    }
    
    /**
     * Establecer valor del campo según su tipo
     */
    setFieldValue(element, value) {
        switch (element.type) {
            case 'checkbox':
                element.checked = Boolean(value);
                break;
            case 'radio':
                if (element.value === value) {
                    element.checked = true;
                }
                break;
            case 'select-multiple':
                if (Array.isArray(value)) {
                    Array.from(element.options).forEach(opt => {
                        opt.selected = value.includes(opt.value);
                    });
                }
                break;
            default:
                element.value = value;
                
                // Trigger events para campos especiales
                if (element.id === '_order_shipping') {
                    // Formatear moneda si es el campo de envío
                    if (typeof formatCurrency === 'function') {
                        formatCurrency(element);
                    }
                }
                
                // Manejo especial para selects dependientes (departamento/ciudad)
                if (element.tagName === 'SELECT') {
                    if (element.id === '_shipping_state') {
                        // Para el departamento, trigger change después de un delay
                        // para permitir que se carguen las ciudades
                        setTimeout(() => {
                            element.dispatchEvent(new Event('change', { bubbles: true }));
                        }, 50);
                    } else if (element.id === '_shipping_city') {
                        // Para la ciudad, no trigger inmediato, se manejará después de cargar opciones
                        console.log('Ciudad restaurada desde persistencia:', value);
                    } else {
                        // Para otros selects, trigger normal
                        element.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                break;
        }
    }
    
    /**
     * Obtener paso actual desde el DOM
     */
    getCurrentStep() {
        const activeStep = document.querySelector('.step-wizard .step.active');
        if (activeStep) {
            const steps = document.querySelectorAll('.step-wizard .step');
            return Array.from(steps).indexOf(activeStep) + 1;
        }
        return 1;
    }
    
    /**
     * Adjuntar eventos de auto-guardado
     */
    attachAutoSave() {
        // Eventos para auto-guardado
        const events = ['input', 'change', 'blur'];
        
        events.forEach(eventType => {
            this.form.addEventListener(eventType, (e) => {
                if (this.shouldSaveField(e.target)) {
                    // Debounce para evitar guardado excesivo
                    clearTimeout(this.saveTimeout);
                    this.saveTimeout = setTimeout(() => {
                        this.saveFormData();
                    }, 500);
                }
            });
        });
        
        // Guardado al cambiar de página (beforeunload)
        window.addEventListener('beforeunload', () => {
            this.saveFormData();
        });
    }
    
    /**
     * Adjuntar evento de envío de formulario
     */
    attachFormSubmit() {
        this.form.addEventListener('submit', () => {
            // No limpiar inmediatamente, esperar confirmación de éxito
            console.log('Formulario enviado, datos mantenidos para recuperación');
        });
    }
    
    /**
     * Limpiar datos guardados
     */
    clearFormData() {
        try {
            localStorage.removeItem(this.storageKey);
            console.log('Datos de formulario limpiados');
        } catch (e) {
            console.warn('Error limpiando localStorage:', e);
        }
    }
    
    /**
     * Limpiar datos específicos (por ejemplo, solo datos de cliente)
     */
    clearCustomerData() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Campos de cliente a limpiar
            const customerFields = [
                '_billing_id', '_billing_email', '_billing_phone',
                '_shipping_first_name', '_shipping_last_name',
                '_shipping_address_1', '_shipping_address_2',
                '_shipping_city', '_shipping_state', '_shipping_barrio'
            ];
            
            customerFields.forEach(field => {
                delete formData[field];
            });
            
            localStorage.setItem(this.storageKey, JSON.stringify(formData));
            console.log('Datos de cliente limpiados, otros datos mantenidos');
            
        } catch (e) {
            console.warn('Error limpiando datos de cliente:', e);
        }
    }
    
    /**
     * Mostrar notificación de datos restaurados
     */
    showRestoreNotification(dni) {
        // Crear notificación
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px;';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-server me-2"></i>
                <div>
                    <strong>Datos restaurados desde sesión</strong><br>
                    <small>Cliente con DNI ${dni} cargado automáticamente</small>
                </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remover después de 4 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 4000);
    }
    
    /**
     * Obtener estadísticas de datos guardados
     */
    getStorageStats() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (!savedData) return null;
            
            const formData = JSON.parse(savedData);
            const fieldCount = Object.keys(formData).filter(key => !key.startsWith('_')).length;
            const timestamp = formData._timestamp ? new Date(formData._timestamp) : null;
            const step = formData._step || 'Desconocido';
            
            return {
                fieldCount,
                timestamp,
                step,
                size: new Blob([savedData]).size
            };
        } catch (e) {
            return null;
        }
    }
}

// Funciones globales para integración con el sistema existente

/**
 * Inicializar persistencia para formulario específico
 */
window.initFormPersistence = function(formId) {
    const desiredKey = arguments[1] || 'ventas_form_data';
    if (!window.formPersistenceInstance) {
        window.formPersistenceInstance = new FormPersistence(formId, desiredKey);
        return window.formPersistenceInstance;
    }

    // Si ya existe pero con otra key, recrear para evitar colisiones (ej. wizard vs otros módulos)
    if (window.formPersistenceInstance.storageKey !== desiredKey || window.formPersistenceInstance.formId !== formId) {
        window.formPersistenceInstance = new FormPersistence(formId, desiredKey);
    }

    return window.formPersistenceInstance;
};

/**
 * Limpiar datos al buscar cliente (llamar desde adminventas.php)
 */
window.clearCustomerDataOnSearch = function() {
    if (window.formPersistenceInstance) {
        window.formPersistenceInstance.clearCustomerData();
    }
};

/**
 * Limpiar todos los datos (llamar al completar venta)
 */
window.clearAllFormData = function() {
    if (window.formPersistenceInstance) {
        window.formPersistenceInstance.clearFormData();
    }
};

/**
 * Obtener estadísticas de almacenamiento
 */
window.getFormStorageStats = function() {
    if (window.formPersistenceInstance) {
        return window.formPersistenceInstance.getStorageStats();
    }
    return null;
};

// Auto-inicialización para formularios comunes
document.addEventListener('DOMContentLoaded', function() {
    // Buscar formularios principales
    const commonFormIds = ['form_venta', 'datos_cliente_form', 'productos_form'];
    
    for (const formId of commonFormIds) {
        const form = document.getElementById(formId);
        if (form) {
            initFormPersistence(formId);
            break; // Solo inicializar el primer formulario encontrado
        }
    }
    
    // Si no se encuentra formulario específico, buscar el primer formulario de la página
    if (!window.formPersistenceInstance) {
        const firstForm = document.querySelector('form');
        if (firstForm && firstForm.id) {
            initFormPersistence(firstForm.id);
        }
    }
});

console.log('Sistema de Persistencia de Formularios cargado');
