<?php
// Obtener el nombre del archivo actual para marcar el elemento activo
$current_page = basename($_SERVER['PHP_SELF']);

// Definir las páginas y sus elementos de menú correspondientes
$menu_items = [
    'inicio.php' => 'inicio',
    'admin.php' => 'ventas',
    'productos.php' => 'productos', 
    'usuarios.php' => 'usuarios',
    'pedidos.php' => 'facturacion',
    'venta.php' => 'ventas',
    'v_producto.php' => 'ventas',
    'v_producto_f.php' => 'ventas',
    'detventa.php' => 'ventas',
    'selector_productos.php' => 'facturacion'
];

$active_menu = isset($menu_items[$current_page]) ? $menu_items[$current_page] : '';
?>

<nav class="navbar fixed-top navbar-expand-lg navbar-dark shadow-sm bg-menu">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="http://localhost/ventas/" title="Ir al inicio">
      <img src="assets/img/logo-white.png" 
           class="img-fluid me-2" alt="SAND&CAT" style="height: 45px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));" />
    </a>
    
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent1" 
            aria-controls="navbarSupportedContent1" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarSupportedContent1">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_menu == 'inicio') ? 'active fw-bold' : ''; ?> px-3 py-2 rounded-pill mx-1" 
             href="inicio.php" title="Página de inicio">
            <i class="fas fa-home me-2"></i>
            <span class="d-none d-lg-inline">Inicio</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_menu == 'ventas') ? 'active fw-bold' : ''; ?> px-3 py-2 rounded-pill mx-1" 
             href="admin.php" title="Gestión de ventas">
            <i class="fas fa-shopping-cart me-2"></i>
            <span class="d-none d-lg-inline">Ventas</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_menu == 'productos') ? 'active fw-bold' : ''; ?> px-3 py-2 rounded-pill mx-1" 
             href="productos.php" title="Gestión de productos">
            <i class="fas fa-box me-2"></i>
            <span class="d-none d-lg-inline">Productos</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_menu == 'usuarios') ? 'active fw-bold' : ''; ?> px-3 py-2 rounded-pill mx-1" 
             href="usuarios.php" title="Gestión de usuarios">
            <i class="fas fa-users me-2"></i>
            <span class="d-none d-lg-inline">Usuarios</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_menu == 'facturacion') ? 'active fw-bold' : ''; ?> px-3 py-2 rounded-pill mx-1" 
             href="pedidos.php" title="Sistema de facturación">
            <i class="fas fa-file-invoice me-2"></i>
            <span class="d-none d-lg-inline">Facturación</span>
          </a>
        </li>
      </ul>
      
      <div class="navbar-nav">
        <div class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-light px-3 py-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-2"></i>
            <span class="d-none d-md-inline"><?php echo isset($row_usuario['nombre']) ? $row_usuario['nombre'] : 'Usuario'; ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configuración</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="http://localhost/ventas/?logout=login3Et" title="Cerrar sesión">
              <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
            </a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>