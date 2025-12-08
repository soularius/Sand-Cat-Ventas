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
        'label' => 'Productos',
        'icon' => 'fas fa-shopping-cart',
        'page' => 'pros_venta.php'
    ],
    3 => [
        'label' => 'Pago',
        'icon' => 'fas fa-credit-card',
        'page' => 'pago_venta.php'
    ],
    4 => [
        'label' => 'Confirmación',
        'icon' => 'fas fa-check-circle',
        'page' => 'confirmar_venta.php'
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
                    if (confirm('¿Desea regresar a: ' + step.querySelector('.step-label').textContent + '?')) {
                        window.location.href = page;
                    }
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
});
</script>
