<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Establecer codificación UTF-8
header('Content-Type: text/html; charset=utf-8');
mysqli_set_charset($sandycat, "utf8mb4");

// 3. Cargar clases específicas
require_once('class/woocommerce_products.php');
$MM_authorizedUsers = "a,v";
$MM_donotCheckaccess = "false";

$MM_restrictGoTo = "http://localhost/ventas";


if (!((isset($_SESSION['MM_Username'])))) { 
  $MM_qsChar = "?";
  $MM_referrer = $_SERVER['PHP_SELF'];
  if (strpos($MM_restrictGoTo, "?")) $MM_qsChar = "&";
  if (isset($QUERY_STRING) && strlen($QUERY_STRING) > 0) 
  $MM_referrer .= "?" . $QUERY_STRING;
  $MM_restrictGoTo = $MM_restrictGoTo. $MM_qsChar . "accesscheck=" . urlencode($MM_referrer);
  header("Location: ". $MM_restrictGoTo); 
  exit;
}
$colname_usuario = '';
if (isset($_SESSION['MM_Username'])) {
$colname_usuario=mysqli_real_escape_string($sandycat,$_SESSION['MM_Username']);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

// Crear variable compatible para el menú
if (!isset($row_usuario['nombre']) && isset($row_usuario['elnombre'])) {
    $row_usuario['nombre'] = $row_usuario['elnombre'];
}

if(isset($_POST['id_articulos']) && isset($_POST['m_producto'])) {
	$_POST['id_articulos'];
	$_POST['codigo'];
	$_POST['nombre'];
	$_POST['valor'];
	$_POST['descuento'];
	$id_articulos = $_POST['id_articulos'];
	$codigo = $_POST['codigo'];
	$nombre = $_POST['nombre'];
	$valor = $_POST['valor'];
	$descuento = $_POST['descuento'];
	$query = "UPDATE articulos SET nombre = '$nombre', codigo ='$codigo', valor='$valor', descuento='$descuento' WHERE id_articulos = '$id_articulos'";
	mysqli_query($sandycat, $query);
}

if(isset($_POST['id_articulos']) && isset($_POST['m_estado'])) {
	$estadoac = "";
	$_POST['id_articulos'];
	$_POST['estado'];
	$id_articulos = $_POST['id_articulos'];
	$estadoac = $_POST['estado'];
	if($estadoac == "a") {
		$estadoc = "i";
	} else {
		$estadoc = "a";		
	}
	$query = "UPDATE articulos SET estado = '$estadoc' WHERE id_articulos = '$id_articulos'";
	mysqli_query($sandycat, $query);
}


if(isset($_POST['n_productos']) && isset($_POST['n_productos'])) {
	$_POST['nombre'];
	$_POST['codigo'];
	$_POST['medida'];
	$_POST['valor'];
	$_POST['descuento'];
	$_POST['iva'];
	$codigo = $_POST['codigo'];
	$nombre = $_POST['nombre'];
	$valor = $_POST['valor'];
	$descuento = $_POST['descuento'];
	$medida = "";
	$query = "INSERT INTO articulos (nombre, codigo, medida, valor, estado, descuento, iva) VALUES ('$nombre', '$codigo', '$medida', '$valor', 'a', '$descuento', '0')";
	mysqli_query($sandycat, $query);
}

// Inicializar clase de productos WooCommerce
$wooProducts = new WooCommerceProducts();

// Configuración de paginación
$products_per_page = 20;
// Capturar page parameter de forma segura antes de cualquier modificación de $_GET
$page_param = isset($_GET['page']) ? $_GET['page'] : '1';
$current_page = max(1, intval($page_param));
$current_page = (int)$current_page; // Asegurar que sea entero
$offset = ($current_page - 1) * $products_per_page;

// Obtener parámetros de búsqueda y filtros
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) && $_GET['category'] !== '' ? intval($_GET['category']) : 0;

// Debug: verificar parámetros recibidos
Utils::logError("PRODUCTOS.PHP DEBUG - Page param: '$page_param', Current page: $current_page, Search: '$search_term', Category ID from URL: $category_id");

// Si tenemos category_id, verificar si es term_id y convertir a term_taxonomy_id
if ($category_id > 0) {
    // Verificar si el category_id es term_id y convertir a term_taxonomy_id
    $query_convert = "SELECT tt.term_taxonomy_id FROM miau_terms t 
                     INNER JOIN miau_term_taxonomy tt ON t.term_id = tt.term_id 
                     WHERE tt.taxonomy = 'product_cat' AND (t.term_id = $category_id OR tt.term_taxonomy_id = $category_id)";
    $result_convert = mysqli_query($miau, $query_convert);
    if ($result_convert && mysqli_num_rows($result_convert) > 0) {
        $row_convert = mysqli_fetch_assoc($result_convert);
        $original_category_id = $category_id;
        $category_id = intval($row_convert['term_taxonomy_id']);
        Utils::logError("CATEGORY CONVERSION - Original ID: $original_category_id, Converted to term_taxonomy_id: $category_id");
    } else {
        Utils::logError("CATEGORY NOT FOUND - ID: $category_id not found in database");
    }
}

try {
    // Obtener total de productos para paginación
    $total_products = (int)$wooProducts->getTotalProductsCount($search_term, $category_id);
    $total_pages = max(1, (int)ceil($total_products / $products_per_page));
    
    // Asegurar que la página actual no exceda el total de páginas
    if ($current_page > $total_pages) {
        $current_page = (int)$total_pages;
        $offset = ($current_page - 1) * $products_per_page;
    }
    
    // Debug info detallado
    Utils::logError("PAGINATION DEBUG - Total products: $total_products, Total pages: $total_pages, Current page: $current_page, Products per page: $products_per_page");
    Utils::logError("PAGINATION CONDITION - Will show pagination? " . ($total_pages > 1 ? 'YES' : 'NO') . " (total_pages: $total_pages)");
    
    // Asegurar que tenemos productos para mostrar paginación
    if ($total_products == 0) {
        Utils::logError("NO PRODUCTS FOUND - Search: '$search_term', Category: $category_id");
    } else {
        Utils::logError("PRODUCTS FOUND - Total: $total_products, Will fetch: " . min($products_per_page, $total_products - $offset));
    }
    
    // Obtener productos con paginación usando método optimizado unificado
    if (!empty($search_term) || $category_id > 0) {
        // Usar método optimizado que maneja búsqueda Y categoría (igual que selector_productos.php)
        Utils::logError("Using searchProductsWithVariations method - Search: '$search_term', Category: $category_id");
        $productos_woo = $wooProducts->searchProductsWithVariations($search_term, $products_per_page, $offset, $category_id);
    } else {
        // Usar método general con paginación
        Utils::logError("Using getAllProducts method");
        $productos_woo = $wooProducts->getAllProducts($products_per_page, $offset, $search_term, $category_id);
    }
    
    // Debug: verificar cuántos productos se obtuvieron y si hay variaciones
    Utils::logError("Products fetched: " . count($productos_woo));
    foreach ($productos_woo as $index => $producto) {
        if (isset($producto['es_variacion']) && $producto['es_variacion']) {
            Utils::logError("Variation found at index $index: ID=" . $producto['id_producto'] . ", Name=" . $producto['nombre'] . ", Variation_ID=" . ($producto['variation_id'] ?? 'null'));
        }
    }
    
    // Obtener categorías para el filtro
    $categorias_woo = $wooProducts->getProductCategories();
    
} catch (Exception $e) {
    // Manejo de errores
    Utils::logError("Error en productos.php: " . $e->getMessage());
    $productos_woo = [];
    $categorias_woo = [];
    $total_products = 0;
    $total_pages = 1;
    $current_page = 1;
}

$totalRows_articulos = count($productos_woo);

// Definir variables seguras de paginación para uso global
$current_page_int = (int)$current_page;
$total_pages_int = (int)$total_pages;

$ellogin = '';
$ellogin = isset($row_usuario['elnombre']) ? $row_usuario['elnombre'] : '';
$id_usuarios = isset($row_usuario['id']) ? $row_usuario['id'] : 0;
$hoy = date("Y-m-d");


if(isset($_POST['iniciando']) && $_POST['iniciando'] == "si") {
	$_POST['doc_cliente'];
	$_POST['nom_cliente'];
	$doc_cliente = $_POST['doc_cliente'];
	$nom_cliente = strtoupper($_POST['nom_cliente']);
	$estado = "i";
	$query = "INSERT INTO ventas (id_usuarios, doc_cliente, nom_cliente, estado, fecha) VALUES ('$id_usuarios', '$doc_cliente', '$nom_cliente', '$estado', '$hoy')";
	mysqli_query($sandycat, $query);

	$id_creado = mysqli_insert_id($sandycat);
  	$elnuevo = "v_producto.php?i=$id_creado";
    header("Location: $elnuevo");
}

// 4. DESPUÉS: Cargar presentación
include("parts/header.php");
?>
<body style="padding-top: 70px">
<div class="container">
<?php include("parts/menu.php"); ?><br />
<br />
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-box me-2"></i>Productos WooCommerce</h2>
    <div class="d-flex gap-2">
      <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#filtrosModal">
        <i class="fas fa-filter me-1"></i>Filtros
      </button>
      <a href="productos.php" class="btn btn-secondary btn-custom">
        <i class="fas fa-sync-alt me-1"></i>Actualizar
      </a>
    </div>
  </div>

  <!-- Filtros y búsqueda -->
  <div class="row mb-4">
    <div class="col-md-6">
      <form method="GET" action="productos.php" class="d-flex">
        <div class="input-group">
          <input type="text" class="form-control" name="search" 
                placeholder="Buscar por nombre, SKU o descripción..." 
                value="<?php echo htmlspecialchars($search_term); ?>">
          <?php if ($category_id > 0): ?>
            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
          <?php endif; ?>
          <button type="submit" class="btn btn-success btn-custom">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
    <div class="col-md-6">
        <form method="GET" action="productos.php" class="form-group">
        <select name="category" class="form-control form-select" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias_woo as $categoria): ?>
            <option value="<?php echo $categoria['id_taxonomy']; ?>" 
                    <?php echo ($category_id == $categoria['id_taxonomy']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($categoria['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($search_term)): ?>
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Información de resultados -->
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <div>
      <i class="fas fa-info-circle me-2"></i>
      <?php if ($total_products > 0): ?>
        Mostrando <?php echo $totalRows_articulos; ?> de <?php echo $total_products; ?> productos
        <?php if (!empty($search_term)): ?>
          para la búsqueda: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
        <?php endif; ?>
        <?php if ($category_id > 0): ?>
          <?php 
          $categoria_nombre = '';
          foreach ($categorias_woo as $cat) {
              if ($cat['id_taxonomy'] == $category_id) {
                  $categoria_nombre = $cat['nombre'];
                  break;
              }
          }
          ?>
          en la categoría: <strong><?php echo htmlspecialchars($categoria_nombre); ?></strong>
        <?php endif; ?>
      <?php else: ?>
        No se encontraron productos
        <?php if (!empty($search_term) || $category_id > 0): ?>
          con los filtros aplicados
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if ($total_products > 0): ?>
    <div>
      <small class="text-muted">
        Página <?php echo $current_page_int; ?> de <?php echo $total_pages_int; ?>
      </small>
    </div>
    <?php endif; ?>
  </div>

<div class="tab-content">
  <br />
		<?php if($totalRows_articulos > 0) { ?>
  <div class="table-responsive">
  <table class="table table-hover table-striped">
    <thead class="table-dark">
      <tr>
        <th><i class="fas fa-tag me-1"></i>Producto</th>
        <th class="text-center"><i class="fas fa-barcode me-1"></i>SKU</th>
        <th class="text-end"><i class="fas fa-dollar-sign me-1"></i>Precio</th>
        <th class="text-center"><i class="fas fa-boxes me-1"></i>Stock</th>
        <th class="text-center"><i class="fas fa-toggle-on me-1"></i>Estado</th>
        <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
      </tr>
    </thead>
    <tbody id="donde">
		<?php foreach ($productos_woo as $producto): ?>
      <tr data-product-id="<?php echo $producto['id_producto']; ?>">
        <td>
          <div class="d-flex flex-column">
            <strong>
              <a href="<?php echo ($_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU') . '/?p=' . ($producto['product_id'] ?? $producto['id_producto']); ?>" 
                 target="_blank" 
                 class="text-decoration-none text-primary"
                 title="Ver en WooCommerce">
                <?php echo $producto['title'] ?? $producto['nombre']; ?>
                <i class="fas fa-external-link-alt ms-1 small"></i>
              </a>
            </strong>
            <?php if (!empty($producto['variation_label'])): ?>
              <small class="text-primary ps-2"><?php echo str_ireplace('-', ' ', $producto['variation_label']); ?></small>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-center">
          <code><?php echo htmlspecialchars($producto['sku'] ?: ($producto['parent_sku'] ?? 'N/A')); ?></code>
        </td>
        <td class="text-end">
          <div class="d-flex flex-column align-items-end">
            <?php if ($producto['precio_oferta'] > 0 && $producto['precio_oferta'] < $producto['precio_regular']): ?>
              <span class="text-danger fw-bold">$<?php echo number_format($producto['precio_oferta'], 0, ',', '.'); ?></span>
              <small class="text-muted text-decoration-line-through">$<?php echo number_format($producto['precio_regular'], 0, ',', '.'); ?></small>
            <?php else: ?>
              <span class="fw-bold">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></span>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-center">
          <span class="badge bg-custom px-3 py-2 <?php echo $producto['en_stock'] ? 'bg-success' : 'bg-danger'; ?>">
            <?php echo $producto['stock']; ?> unidades
          </span>
        </td>
        <td class="text-center">
          <span class="badge bg-custom px-3 py-2 <?php echo $producto['en_stock'] ? 'bg-success' : 'bg-warning'; ?>">
            <?php echo $producto['en_stock'] ? 'Disponible' : 'Agotado'; ?>
          </span>
        </td>
        <td class="text-center">
          <div class="btn-group" role="group">
            <a href="<?php echo ($_ENV['WOOCOMMERCE_BASE_URL'] ?? 'http://localhost/MIAU') . '/?p=' . $producto['id_producto']; ?>" 
               target="_blank" 
               class="btn btn-sm btn-danger btn-custom px-3"
               title="Ver en WooCommerce">
              <i class="fas fa-external-link-alt"></i>
            </a>
            <button type="button" class="btn btn-sm btn-success btn-custom px-3" 
                    onclick="agregarAVenta(<?php echo $producto['id_producto']; ?>)"
                    title="Agregar a venta">
              <i class="fas fa-cart-plus"></i>
            </button>
          </div>
        </td>
      </tr>
    	<?php endforeach; ?>
    </tbody>
  </table>
  </div>
  
  <!-- Paginación -->
  <?php if ($total_pages > 1): ?>
  <nav aria-label="Paginación de productos" class="mt-4">
    <ul class="pagination justify-content-center">
      <!-- Botón Anterior -->
      <?php if ($current_page_int > 1): ?>
        <li class="page-item">
          <?php 
          $prev_params = $_GET;
          $prev_params['page'] = $current_page_int - 1;
          ?>
          <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="productos.php?<?php echo http_build_query($prev_params); ?>">
            <i class="fas fa-chevron-left me-1"></i>Anterior
          </a>
        </li>
      <?php else: ?>
        <li class="page-item disabled">
          <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">
            <i class="fas fa-chevron-left me-1"></i>Anterior
          </span>
        </li>
      <?php endif; ?>
      
      <!-- Números de página -->
      <?php
      $start_page = max(1, $current_page_int - 2);
      $end_page = min($total_pages_int, $current_page_int + 2);
      
      // Mostrar primera página si no está en el rango
      if ($start_page > 1): ?>
        <li class="page-item">
          <?php 
          $first_params = $_GET;
          $first_params['page'] = 1;
          ?>
          <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="productos.php?<?php echo http_build_query($first_params); ?>">1</a>
        </li>
        <?php if ($start_page > 2): ?>
          <li class="page-item disabled">
            <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
          </li>
        <?php endif; ?>
      <?php endif; ?>
      
      <!-- Páginas en el rango -->
      <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <li class="page-item <?php echo ($i == $current_page_int) ? 'active' : ''; ?>">
          <?php 
          $page_params = $_GET;
          $page_params['page'] = (int)$i;
          ?>
          <a class="page-link text-white btn btn-sm btn-custom px-3 py-2 mx-1 <?php echo ($i == $current_page_int) ? 'btn-success' : 'btn-danger'; ?>" href="productos.php?<?php echo http_build_query($page_params); ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>
      
      <!-- Mostrar última página si no está en el rango -->
      <?php if ($end_page < $total_pages_int): ?>
        <?php if ($end_page < $total_pages_int - 1): ?>
          <li class="page-item disabled">
            <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
          </li>
        <?php endif; ?>
        <li class="page-item">
          <?php 
          $last_params = $_GET;
          $last_params['page'] = $total_pages_int;
          ?>
          <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="productos.php?<?php echo http_build_query($last_params); ?>"><?php echo $total_pages_int; ?></a>
        </li>
      <?php endif; ?>
      
      <!-- Botón Siguiente -->
      <?php if ($current_page_int < $total_pages_int): ?>
        <li class="page-item">
          <?php 
          $next_params = $_GET;
          $next_params['page'] = $current_page_int + 1;
          ?>
          <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="productos.php?<?php echo http_build_query($next_params); ?>">
            Siguiente<i class="fas fa-chevron-right ms-1"></i>
          </a>
        </li>
      <?php else: ?>
        <li class="page-item disabled">
          <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 opacity-50 mx-1">
            Siguiente<i class="fas fa-chevron-right ms-1"></i>
          </span>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
  
  <!-- Información adicional de paginación -->
  <?php if ($total_products > 0): ?>
  <div class="text-center text-muted mt-2">
    <small>
      Mostrando <?php 
      $start_item = ($current_page_int - 1) * $products_per_page + 1;
      $end_item = min($current_page_int * $products_per_page, $total_products);
      echo $start_item . '-' . $end_item; 
      ?> de <?php echo $total_products; ?> productos
    </small>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  
		<?php } else { ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    No se encontraron productos.
    <?php if (!empty($search_term) || $category_id > 0): ?>
      <br><small>Intenta cambiar los filtros de búsqueda.</small>
    <?php endif; ?>
  </div>
		<?php } ?>


<!-- Modal para ver detalles del producto -->
<div class="modal fade" id="verProducto" tabindex="-1" aria-labelledby="verProductoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="verProductoLabel">
                    <i class="fas fa-eye me-2"></i>Detalles del Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4 id="producto-nombre" class="mb-3"></h4>
                        <p id="producto-descripcion" class="text-muted"></p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <strong>SKU:</strong> <code id="producto-sku"></code>
                            </div>
                            <div class="col-md-6">
                                <strong>Stock:</strong> <span id="producto-stock" class="badge"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h3 id="producto-precio" class="text-primary mb-0"></h3>
                                <small class="text-muted">Precio</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-primary" id="btn-agregar-venta">
                    <i class="fas fa-cart-plus me-1"></i>Agregar a Venta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Carrito de Compras JavaScript -->
<script src="assets/js/carrito_compras.js"></script>

<script>
// Manejar modal de detalles del producto
document.addEventListener('DOMContentLoaded', function() {
    const verProductoModal = document.getElementById('verProducto');
    
    verProductoModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nombre = button.getAttribute('data-nombre');
        const precio = button.getAttribute('data-precio');
        const stock = button.getAttribute('data-stock');
        const sku = button.getAttribute('data-sku');
        const descripcion = button.getAttribute('data-descripcion');
        
        // Actualizar contenido del modal
        document.getElementById('producto-nombre').textContent = nombre;
        document.getElementById('producto-descripcion').textContent = descripcion || 'Sin descripción disponible';
        document.getElementById('producto-sku').textContent = sku || 'N/A';
        document.getElementById('producto-precio').textContent = '$' + new Intl.NumberFormat('es-CO').format(precio);
        
        const stockBadge = document.getElementById('producto-stock');
        stockBadge.textContent = stock + ' unidades';
        stockBadge.className = 'badge ' + (stock > 0 ? 'bg-success' : 'bg-danger');
        
        // Configurar botón de agregar a venta
        document.getElementById('btn-agregar-venta').onclick = function() {
            agregarAVenta(id);
        };
    });
});

// Función para agregar producto a venta usando localStorage
function agregarAVenta(productId) {
    console.log('agregarAVenta called with productId:', productId);
    
    // Verificar que el carrito esté inicializado
    if (typeof cart === 'undefined' || !cart) {
        console.error('Cart not initialized');
        showNotification('Error: Sistema de carrito no inicializado', 'error');
        return;
    }
    
    // Buscar el producto en la tabla actual
    const productRow = document.querySelector(`tr[data-product-id="${productId}"]`);
    if (!productRow) {
        console.error('Product row not found for ID:', productId);
        showNotification('Error: Producto no encontrado', 'error');
        return;
    }
    
    // Extraer datos del producto desde la fila de la tabla
    const productData = extractProductDataFromRow(productRow, productId);
    if (!productData) {
        console.error('Could not extract product data');
        showNotification('Error: No se pudieron obtener los datos del producto', 'error');
        return;
    }
    
    console.log('Product data extracted:', productData);
    
    // Agregar al carrito
    const cartKey = cart.addProduct(productData, 1);
    
    if (cartKey) {
        console.log('Product added to cart with key:', cartKey);
        // Actualizar el botón para mostrar que fue agregado
        const button = productRow.querySelector('.btn-success');
        if (button) {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Agregado';
            button.classList.remove('btn-success');
            button.classList.add('btn-info');
            
            // Restaurar el botón después de 2 segundos
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
            }, 2000);
        }
    }
}

// Función para extraer datos del producto desde la fila de la tabla
function extractProductDataFromRow(row, productId) {
    try {
        // Extraer datos desde las celdas de la tabla
        const cells = row.querySelectorAll('td');
        
        if (cells.length < 6) {
            console.error('Not enough cells in product row');
            return null;
        }
        
        // Estructura real de las columnas: Producto, SKU, Precio, Stock, Estado, Acciones
        const productoCell = cells[0]; // Contiene nombre y variación
        const skuCell = cells[1];      // SKU
        const precioCell = cells[2];   // Precio
        const stockCell = cells[3];    // Stock
        const estadoCell = cells[4];   // Estado
        
        // Extraer nombre del producto (primer enlace en la celda)
        const nombreLink = productoCell.querySelector('a');
        const nombre = nombreLink ? nombreLink.textContent.trim() : productoCell.textContent.trim();
        
        // Extraer variación si existe
        const variacionElement = productoCell.querySelector('small');
        const variacion = variacionElement ? variacionElement.textContent.trim() : '';
        
        // Extraer SKU (dentro de <code>)
        const skuElement = skuCell.querySelector('code');
        const sku = skuElement ? skuElement.textContent.trim() : skuCell.textContent.trim();
        
        // Extraer precio (puede tener precio regular y de oferta)
        const precioOferta = precioCell.querySelector('.text-danger');
        const precioRegular = precioCell.querySelector('.text-muted.text-decoration-line-through');
        const precioNormal = precioCell.querySelector('.fw-bold:not(.text-danger)');
        
        let precio = 0;
        let precioRegularValue = 0;
        let precioOfertaValue = null;
        
        if (precioOferta && precioRegular) {
            // Hay precio de oferta
            const cleanOferta = precioOferta.textContent.replace(/[^\d]/g, '') || '0';
            const cleanRegular = precioRegular.textContent.replace(/[^\d]/g, '') || '0';
            precioOfertaValue = parseInt(cleanOferta, 10) || 0;
            precioRegularValue = parseInt(cleanRegular, 10) || 0;
            precio = precioOfertaValue;
        } else if (precioNormal) {
            // Precio normal sin oferta
            const cleanPrecio = precioNormal.textContent.replace(/[^\d]/g, '') || '0';
            precio = parseInt(cleanPrecio, 10) || 0;
            precioRegularValue = precio;
        }
        
        // Extraer stock (dentro de badge)
        const stockBadge = stockCell.querySelector('.badge');
        const stockText = stockBadge ? stockBadge.textContent.trim() : stockCell.textContent.trim();
        const numericStock = parseInt(stockText.replace(/[^\d]/g, ''), 10) || 0;
        
        // Determinar disponibilidad desde el estado
        const estadoBadge = estadoCell.querySelector('.badge');
        const estadoText = estadoBadge ? estadoBadge.textContent.trim().toLowerCase() : '';
        const available = estadoText.includes('disponible');
        
        return {
            id: productId,
            product_id: productId,
            title: nombre || `Producto ${productId}`,
            sku: sku === 'N/A' ? '' : sku,
            price: precio,
            regular_price: precioRegularValue || precio,
            sale_price: precioOfertaValue,
            stock: numericStock,
            available: available,
            variation_id: null,
            variation_label: variacion,
            variation_attributes: null,
            image_url: '', // No hay imagen en esta vista
            permalink: nombreLink ? nombreLink.href : '#'
        };
    } catch (error) {
        console.error('Error extracting product data:', error);
        return null;
    }
}

// Función para mostrar notificaciones
function showNotification(message, type = 'success') {
    // Si el carrito tiene su propio método de notificación, usarlo
    if (typeof cart !== 'undefined' && cart && typeof cart.showNotification === 'function') {
        cart.showNotification(message, type);
        return;
    }
    
    // Fallback: crear notificación simple
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

// Funcionalidad de carga para paginación (similar a ventas.php)
$(document).ready(function() {
    // Mejorar la experiencia de usuario con indicadores de carga en paginación
    $(document).on('click', '.pagination .page-link', function(e) {
        const $this = $(this);
        const originalText = $this.html();
        
        // No aplicar loading a elementos deshabilitados o activos
        if (!$this.parent().hasClass('active') && !$this.parent().hasClass('disabled')) {
            // Mostrar spinner de carga
            $this.html('<i class="fas fa-spinner fa-spin"></i>');
            
            // Restaurar texto original si hay error (fallback)
            setTimeout(() => {
                if ($this.html().includes('fa-spinner')) {
                    $this.html(originalText);
                }
            }, 5000);
        }
    });
    
    // Agregar tooltips a los controles de paginación
    $('.pagination .page-link').each(function() {
        const $link = $(this);
        const href = $link.attr('href');
        
        if (href && href.includes('page=')) {
            const pageMatch = href.match(/page=(\d+)/);
            if (pageMatch) {
                $link.attr('title', 'Ir a la página ' + pageMatch[1]);
                $link.tooltip();
            }
        }
    });
    
    // Tooltip para botones anterior/siguiente
    $('.pagination .page-link').each(function() {
        const $link = $(this);
        const text = $link.text().trim();
        
        if (text.includes('Anterior')) {
            $link.attr('title', 'Página anterior');
        } else if (text.includes('Siguiente')) {
            $link.attr('title', 'Página siguiente');
        }
    });
});

</script>

	<?php include("parts/foot.php"); ?>


</body>
</html>
<?php
mysqli_free_result($usuario);
?>