<?php
// Obtener el nombre del archivo actual para excluir selector_productos.php
$current_page = basename($_SERVER['PHP_SELF']);
$exclude_cart_pages = ['selector_productos.php'];

// Debug: verificar estado de sesión y login
$is_logged_in = Utils::isLoggedIn();
$session_username = $_SESSION['MM_Username'] ?? 'NO_SET';
$session_logueado = $_SESSION['logueado'] ?? 'NO_SET';

// Solo mostrar carrito flotante si el usuario está logueado y no está en páginas excluidas
$show_floating_cart = $is_logged_in && !in_array($current_page, $exclude_cart_pages);

// Si no está logueado, limpiar cache del carrito
$clear_cart_cache = !$is_logged_in;
?>

<footer class="page-footer font-small blue pt-4">
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <div class="footer-copyright text-center py-3">© <?php echo date('Y'); ?> Copyright:
          <a class="a-custom" href="https://sandycat.com.co/" target="_blank">Sand&Cat</a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="footer-copyright text-center py-3">Desarrollado y Diseñado por:
          <a class="a-custom" href="https://www.linkedin.com/in/yined-milanyela-molina-barrios/" target="_blank">Ing. Yined Molina</a>
        </div>
      </div>
    </div>
  </div>
</footer>

<?php if ($show_floating_cart): ?>
<!-- Floating Cart Button -->
<div class="floating-cart-btn" id="floating-cart-btn" style="display: none;">
    <button type="button" class="btn bg-primary bg-custom btn-lg rounded-circle shadow-lg" data-bs-toggle="modal" data-bs-target="#cartModal" title="Ver Carrito">
        <i class="fas fa-shopping-cart text-white"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-accent bg-custom" id="cart-badge" style="display: none;">
            0
        </span>
    </button>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success bg-custom text-white">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="fas fa-shopping-cart me-2"></i>Carrito de Compras
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cart-container">
                    <div class="empty-cart text-center py-4">
                        <i class="fas fa-shopping-cart text-muted fa-3x mb-3"></i>
                        <p class="text-muted">No hay productos seleccionados</p>
                        <small class="text-muted">Usa los botones <i class="fas fa-cart-plus text-success"></i> para agregar productos al carrito</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning btn-custom" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-danger btn-custom" onclick="cart.clearCart()" id="clear-cart-btn" style="display: none;">
                    <i class="fas fa-trash me-1"></i>Limpiar Carrito
                </button>
                <button type="button" class="btn btn-success btn-custom" onclick="proceedToSummary()" id="proceed-btn" style="display: none;">
                    <i class="fas fa-arrow-right me-1"></i>Continuar con Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Carrito de Compras JavaScript -->
<script src="assets/js/carrito_compras.js"></script>

<script>
// Funciones para manejar el carrito flotante
function updateFloatingCartButton() {
    const floatingBtn = document.getElementById('floating-cart-btn');
    const cartBadge = document.getElementById('cart-badge');
    const clearBtn = document.getElementById('clear-cart-btn');
    const proceedBtn = document.getElementById('proceed-btn');
    
    if (!cart || !floatingBtn) return;
    
    const totalItems = cart.getTotalItems();
    
    if (totalItems > 0) {
        // Mostrar botón flotante
        floatingBtn.style.display = 'block';
        floatingBtn.classList.add('has-items');
        
        // Actualizar badge
        cartBadge.textContent = totalItems;
        cartBadge.style.display = 'block';
        
        // Mostrar botones en modal
        if (clearBtn) clearBtn.style.display = 'inline-block';
        if (proceedBtn) proceedBtn.style.display = 'inline-block';
    } else {
        // Ocultar botón flotante
        floatingBtn.style.display = 'none';
        floatingBtn.classList.remove('has-items');
        
        // Ocultar badge
        cartBadge.style.display = 'none';
        
        // Ocultar botones en modal
        if (clearBtn) clearBtn.style.display = 'none';
        if (proceedBtn) proceedBtn.style.display = 'none';
    }
}

// Función para proceder al resumen
function proceedToSummary() {
    const items = cart.getCartItems();
    
    if (items.length === 0) {
        cart.showNotification('Debes agregar al menos un producto', 'warning');
        return;
    }
    
    // Si estamos en inicio.php, solo cerrar la modal
    if (window.location.pathname.indexOf('inicio.php') !== -1) {
        const cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
        if (cartModal) {
            cartModal.hide();
        }
        return;
    }
    
    // Redirigir a inicio.php si no estamos ahí
    if (window.location.pathname.indexOf('inicio.php') === -1) {
        window.location.href = 'inicio.php';
        return;
    }
    
    // Si ya estamos en selector_productos.php, usar la función existente
    if (typeof proceedToSummary !== 'undefined') {
        proceedToSummary();
    } else {
        cart.showNotification('Funcionalidad de continuar será implementada próximamente', 'info');
    }
    
    // Cerrar modal
    const cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
    if (cartModal) {
        cartModal.hide();
    }
}

// Inicializar carrito flotante cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {    
    // Esperar a que el carrito se inicialice
    setTimeout(() => {
        if (typeof cart !== 'undefined' && cart) {
            // Guardar la función original si existe
            const originalUpdateCartDisplay = cart.updateCartDisplay;
            
            // Override con funcionalidad adicional
            cart.updateCartDisplay = function() {
                // Llamar función original
                if (originalUpdateCartDisplay) {
                    originalUpdateCartDisplay.call(this);
                }
                
                // Actualizar botón flotante
                updateFloatingCartButton();
            };
            
            // Actualizar inicialmente
            updateFloatingCartButton();
        }
    }, 100);
});
</script>
<?php endif; ?>

<script>
// Inicializar carrito flotante cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($clear_cart_cache): ?>    
    // Limpiar TODA la cache - localStorage completo
    console.log('Limpiando toda la cache del navegador - usuario no logueado');
    
    // Método 1: Limpiar todo localStorage
    if (typeof(Storage) !== "undefined" && localStorage) {
        localStorage.clear();
        console.log('localStorage completamente limpiado');
    }
    
    // Método 2: Limpiar todo sessionStorage
    if (typeof(Storage) !== "undefined" && sessionStorage) {
        sessionStorage.clear();
        console.log('sessionStorage completamente limpiado');
    }
    
    // Método 3: Limpiar cookies relacionadas al carrito
    document.cookie.split(";").forEach(function(c) { 
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
    });
    console.log('Cookies limpiadas');
    
    // Si el objeto cart ya existe, limpiarlo también
    setTimeout(() => {
        if (typeof cart !== 'undefined' && cart && typeof cart.clearCart === 'function') {
            cart.cart = {}; // Limpiar directamente el objeto
            cart.updateCartDisplay(); // Actualizar la visualización
            console.log('Cache del carrito limpiado completamente');
        }
    }, 50);
    <?php endif; ?>
});
</script>