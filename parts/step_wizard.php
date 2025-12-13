<?php
/**
 * Step Wizard Component
 * Sistema de pasos dinámico para el flujo de trabajo
 * 
 * USAGE:
 * include('parts/step_wizard.php');
 * 
 * PARÁMETROS:
 * $current_step - Número del paso actual (1-4)
 * $steps_config - Array con configuración de pasos (opcional)
 */

// Configuración por defecto de los pasos
$default_steps = [
    1 => [
        'label' => 'Datos del Cliente',
        'icon' => 'fas fa-user',
        'page' => 'datos_venta.php'
    ],
    2 => [
        'label' => 'Resumen Cliente',
        'icon' => 'fas fa-user-check',
        'page' => 'pros_venta.php'
    ],
    3 => [
        'label' => 'Productos',
        'icon' => 'fas fa-shopping-cart',
        'page' => 'bproducto.php'
    ],
    4 => [
        'label' => 'Resumen del Pedido',
        'icon' => 'fas fa-clipboard-check',
        'page' => 'resumen_pedido.php'
    ],
    5 => [
        'label' => 'Detalle del Pedido',
        'icon' => 'fas fa-check-circle',
        'page' => 'detalle_pedido.php'
    ]
];

// Usar configuración personalizada si se proporciona, sino usar la por defecto
$steps = isset($steps_config) ? $steps_config : $default_steps;

// Determinar el paso actual si no se especifica
if (!isset($current_step)) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_step = 1; // Por defecto
    
    foreach ($steps as $step_num => $step_data) {
        if ($step_data['page'] === $current_page) {
            $current_step = $step_num;
            break;
        }
    }
}

// Función para determinar la clase CSS del paso
function getStepClass($step_number, $current_step) {
    if ($step_number < $current_step) {
        return 'step completed';
    } elseif ($step_number == $current_step) {
        return 'step active';
    } else {
        return 'step';
    }
}

// Función para obtener el contenido del círculo
function getStepCircleContent($step_number, $current_step) {
    if ($step_number < $current_step) {
        return ''; // El CSS agregará el ✓ con ::before
    } else {
        return $step_number;
    }
}
?>

<div class="wizard-container"></div>
<section class="py-5">
    <div class="container">
        <!-- Progress Steps -->
        <div class="step-wizard mb-5">
            <?php foreach ($steps as $step_num => $step_data): ?>
                <div class="<?php echo getStepClass($step_num, $current_step); ?>" 
                     <?php if (isset($step_data['page'])): ?>
                         data-page="<?php echo htmlspecialchars($step_data['page']); ?>"
                     <?php endif; ?>>
                    <div class="step-circle">
                        <?php echo getStepCircleContent($step_num, $current_step); ?>
                    </div>
                    <div class="step-label">
                        <?php echo htmlspecialchars($step_data['label']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
// JavaScript para hacer los pasos clicables y navegables
document.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.step-wizard .step');
    
    steps.forEach(function(step, index) {
        const page = step.getAttribute('data-page');
        const stepNumber = index + 1;
        
        // Solo hacer clicables los pasos completados
        if (step.classList.contains('completed')) {
            step.style.cursor = 'pointer';
            step.title = 'Ir a ' + step.querySelector('.step-label').textContent;
            
            step.addEventListener('click', function() {
                if (page) {
                    // Navegación con confirmación para pasos completados
                    showStepNavigationModal(step.querySelector('.step-label').textContent, page, stepNumber);
                }
            });
            
            // Agregar efecto hover
            step.addEventListener('mouseenter', function() {
                step.style.opacity = '0.8';
            });
            
            step.addEventListener('mouseleave', function() {
                step.style.opacity = '1';
            });
        }
        
        // Paso actual - no clicable pero con indicador
        if (step.classList.contains('active')) {
            step.title = 'Paso actual: ' + step.querySelector('.step-label').textContent;
        }
        
        // Pasos futuros - no clicables
        if (!step.classList.contains('completed') && !step.classList.contains('active')) {
            step.style.cursor = 'not-allowed';
            step.title = 'Complete los pasos anteriores primero';
        }
    });
    
    // Función para actualizar el progreso dinámicamente (opcional)
    window.updateStepProgress = function(newCurrentStep) {
        steps.forEach(function(step, index) {
            const stepNumber = index + 1;
            step.className = 'step'; // Reset classes
            
            if (stepNumber < newCurrentStep) {
                step.classList.add('completed');
            } else if (stepNumber === newCurrentStep) {
                step.classList.add('active');
            }
            
            // Actualizar contenido del círculo
            const circle = step.querySelector('.step-circle');
            if (stepNumber < newCurrentStep) {
                circle.textContent = ''; // El CSS agregará el ✓
            } else {
                circle.textContent = stepNumber;
            }
        });
    };
    
    // Función para mostrar modal de confirmación de navegación
    window.showStepNavigationModal = function(stepName, targetPage, targetStepNumber) {
        // Crear modal dinámicamente si no existe
        let modal = document.getElementById('stepNavigationModal');
        if (!modal) {
            const modalHTML = `
                <div class="modal fade" id="stepNavigationModal" tabindex="-1" aria-labelledby="stepNavigationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-cuertar bg-custom text-white">
                                <h5 class="modal-title" id="stepNavigationModalLabel">
                                    <i class="fas fa-route me-2"></i>Confirmar Navegación
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-question-circle text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <h6 class="mb-3">¿Desea regresar al paso anterior?</h6>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Ir a: <strong id="stepNameTarget"></strong>
                                </p>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <small>Los datos del paso actual se guardarán automáticamente</small>
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-primary btn-custom" id="confirmNavigationBtn">
                                    <i class="fas fa-arrow-left me-2"></i>Regresar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('stepNavigationModal');
        }
        
        // Actualizar contenido del modal
        document.getElementById('stepNameTarget').textContent = stepName;
        
        // Configurar botón de confirmación
        const confirmBtn = document.getElementById('confirmNavigationBtn');
        confirmBtn.onclick = function() {
            // Cerrar modal
            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance.hide();
            
            // Navegar después de cerrar el modal
            setTimeout(() => {
                navigateToStep(targetPage, targetStepNumber);
            }, 300);
        };
        
        // Mostrar modal
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
    };
    
    // Función para navegar entre pasos con datos
    window.navigateToStep = function(targetPage, targetStepNumber) {
        // Obtener el paso actual desde la página
        const currentPage = window.location.pathname.split('/').pop();
        const orderIdField = document.getElementById('_order_id');
        const orderId = orderIdField ? orderIdField.value : '';

        // Si estamos en resumen_pedido.php (paso 4) y vamos a cualquier paso anterior,
        // enviar order_id via POST para que se recarguen los datos y no se pierdan.
        if (currentPage === 'resumen_pedido.php' && orderId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = targetPage;
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
        
        // Si estamos en bproducto.php (paso 3) y vamos a pros_venta.php (paso 2)
        if (currentPage === 'bproducto.php' && targetPage === 'pros_venta.php') {
            // Obtener el order_id del campo hidden
            
            
            if (orderId) {
                // Crear un formulario temporal para enviar el order_id via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = targetPage;
                form.style.display = 'none';
                
                // Agregar el order_id como campo hidden
                const orderIdInput = document.createElement('input');
                orderIdInput.type = 'hidden';
                orderIdInput.name = '_order_id';
                orderIdInput.value = orderId;
                form.appendChild(orderIdInput);
                
                // Agregar el formulario al DOM y enviarlo
                document.body.appendChild(form);
                form.submit();
                return;
            }
        }
        
        // Navegación normal para otros casos
        window.location.href = targetPage;
    };
});
</script>
