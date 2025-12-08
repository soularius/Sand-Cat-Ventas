<?php
/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */
require_once('conectar.php');
require_once('woocommerce_products.php'); 

if (!isset($_SESSION)) {
  session_start();
}
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

$query_usuario = sprintf("SELECT * FROM usuarios WHERE documento = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

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

// Obtener productos de WooCommerce
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

if (!empty($search_term)) {
    $productos_woo = $wooProducts->searchProducts($search_term);
} elseif ($category_id > 0) {
    $productos_woo = $wooProducts->getProductsByCategory($category_id);
} else {
    $productos_woo = $wooProducts->getAllProducts(100);
}

// Obtener categorías para el filtro
$categorias_woo = $wooProducts->getProductCategories();

$totalRows_articulos = count($productos_woo);

$ellogin = '';
$ellogin = isset($row_usuario['documento']) ? $row_usuario['documento'] : '';
$id_usuarios = isset($row_usuario['id_usuarios']) ? $row_usuario['id_usuarios'] : 0;


if(isset($_POST['iniciando']) && $_POST['iniciando'] = "si") {
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

?>
<?php include("header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("men.php"); ?><br />
<br />
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-box me-2"></i>Productos WooCommerce</h2>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filtrosModal">
        <i class="fas fa-filter me-1"></i>Filtros
      </button>
      <a href="productos.php" class="btn btn-secondary">
        <i class="fas fa-sync-alt me-1"></i>Actualizar
      </a>
    </div>
  </div>

  <!-- Filtros y búsqueda -->
  <div class="row mb-4">
    <div class="col-md-6">
      <form method="GET" action="productos.php" class="d-flex">
        <input type="text" class="form-control me-2" name="search" 
               placeholder="Buscar por nombre, SKU o descripción..." 
               value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i>
        </button>
      </form>
    </div>
    <div class="col-md-6">
      <form method="GET" action="productos.php">
        <select name="category" class="form-select" onchange="this.form.submit()">
          <option value="0">Todas las categorías</option>
          <?php foreach ($categorias_woo as $categoria): ?>
            <option value="<?php echo $categoria['id_categoria']; ?>" 
                    <?php echo ($category_id == $categoria['id_categoria']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($categoria['nombre']); ?> 
              (<?php echo $categoria['total_productos']; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <!-- Información de resultados -->
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    Mostrando <?php echo $totalRows_articulos; ?> productos
    <?php if (!empty($search_term)): ?>
      para la búsqueda: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
    <?php endif; ?>
    <?php if ($category_id > 0): ?>
      en la categoría seleccionada
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
      <tr>
        <td>
          <div class="d-flex flex-column">
            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
            <?php if (!empty($producto['descripcion_corta'])): ?>
              <small class="text-muted"><?php echo substr(strip_tags($producto['descripcion_corta']), 0, 100); ?>...</small>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-center">
          <code><?php echo htmlspecialchars($producto['sku'] ?: 'N/A'); ?></code>
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
          <span class="badge <?php echo $producto['en_stock'] ? 'bg-success' : 'bg-danger'; ?>">
            <?php echo $producto['stock']; ?> unidades
          </span>
        </td>
        <td class="text-center">
          <span class="badge <?php echo $producto['en_stock'] ? 'bg-success' : 'bg-warning'; ?>">
            <?php echo $producto['en_stock'] ? 'Disponible' : 'Agotado'; ?>
          </span>
        </td>
        <td class="text-center">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-info" 
                    data-bs-toggle="modal" data-bs-target="#verProducto"
                    data-id="<?php echo $producto['id_producto']; ?>"
                    data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                    data-precio="<?php echo $producto['precio']; ?>"
                    data-stock="<?php echo $producto['stock']; ?>"
                    data-sku="<?php echo htmlspecialchars($producto['sku']); ?>"
                    data-descripcion="<?php echo htmlspecialchars($producto['descripcion_corta']); ?>"
                    title="Ver detalles">
              <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" 
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
    	<?php } else { ?>
		<div class="text-center py-5">
			<i class="fas fa-box-open fa-3x text-muted mb-3"></i>
			<h4 class="text-muted">No se encontraron productos</h4>
			<p class="text-muted">
				<?php if (!empty($search_term)): ?>
					No hay productos que coincidan con tu búsqueda.
				<?php elseif ($category_id > 0): ?>
					No hay productos en esta categoría.
				<?php else: ?>
					No hay productos disponibles en WooCommerce.
				<?php endif; ?>
			</p>
			<a href="productos.php" class="btn btn-primary">
				<i class="fas fa-sync-alt me-1"></i>Actualizar lista
			</a>
		</div>
	  <?php } ?>
</div>
</div>

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

// Función para agregar producto a venta
function agregarAVenta(productId) {
    // Aquí puedes implementar la lógica para agregar el producto a una venta
    alert('Funcionalidad de agregar a venta será implementada próximamente.\nProducto ID: ' + productId);
}

// Búsqueda en tiempo real (opcional)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                // Implementar búsqueda AJAX si se desea
            }, 500);
        });
    }
});
</script>

	<?php include("foot.php"); ?>


</body>
</html>
<?php
mysqli_free_result($usuario);
?>