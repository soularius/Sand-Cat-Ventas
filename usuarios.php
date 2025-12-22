<?php
/**
 * ==============================================================
 * FILE: usuarios.php
 * ==============================================================
 * ✅ Gestión de usuarios WordPress/WooCommerce modernizada
 * ✅ Usa sistema de login centralizado
 * ✅ Integración con WooCommerceCustomer class
 * ✅ Solo listado y datos básicos de usuarios
 */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Cargar login handler centralizado
require_once('parts/login_handler.php');

// 3. Cargar clases específicas
require_once('class/woocommerce_customer.php');

// 4. Verificar autenticación usando sistema moderno
requireLogin();

// 5. Obtener datos del usuario actual
$row_usuario = getCurrentUserFromDB();
if (!$row_usuario) {
    Header("Location: index.php");
    exit();
}

// 6. Inicializar clase WooCommerceCustomer
$wooCustomer = new WooCommerceCustomer();

// 7. Procesar acciones CRUD usando WooCommerceCustomer
$message = '';
$messageType = '';

try {
    // Crear nuevo usuario usando WooCommerceCustomer
    if (Utils::isPostRequest() && isset($_POST['n_usuarios'])) {
        $userData = Utils::capturePostData(['nombre', 'apellido', 'documento', 'email'], true);
        
        if (!empty($userData['nombre']) && !empty($userData['apellido']) && !empty($userData['documento'])) {
            // Preparar datos para WooCommerceCustomer
            $customerData = [
                '_shipping_first_name' => Utils::sanitizeInput($userData['nombre']),
                '_shipping_last_name' => Utils::sanitizeInput($userData['apellido']),
                '_billing_email' => filter_var($userData['email'], FILTER_SANITIZE_EMAIL),
                'billing_id' => Utils::sanitizeInput($userData['documento'])
            ];
            
            $result = $wooCustomer->createWordPressUser($customerData);
            
            if ($result > 0) {
                $message = 'Usuario creado correctamente con ID: ' . $result;
                $messageType = 'success';
                Utils::logError("Usuario WordPress creado: ID=$result, DNI={$userData['documento']}", 'INFO', 'usuarios.php');
            } else {
                $message = 'Error al crear usuario';
                $messageType = 'error';
            }
        } else {
            $message = 'Nombre, apellido y documento son requeridos';
            $messageType = 'error';
        }
    }
    
    // Nota: La edición y cambio de estado se implementarán usando consultas directas
    // ya que la clase WooCommerceCustomer está enfocada en creación de usuarios
    
} catch (Exception $e) {
    $message = 'Error en operación: ' . $e->getMessage();
    $messageType = 'error';
    Utils::logError("Error en usuarios.php: " . $e->getMessage(), 'ERROR', 'usuarios.php');
}

// 8. Configuración de paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(100, intval($_GET['per_page']))) : 10;
$offset = ($page - 1) * $perPage;

// 9. Obtener lista de usuarios WordPress usando WooCommerceCustomer con paginación
$usuarios_list = [];
$totalUsers = 0;
$totalPages = 0;

try {
    $roles = ['guest_customer', 'customer', 'administrator'];
    
    // Obtener total de usuarios para paginación
    $totalUsers = $wooCustomer->countWordPressUsers($roles);
    $totalPages = ceil($totalUsers / $perPage);
    
    // Obtener usuarios de la página actual
    $usuarios_list = $wooCustomer->getAllWordPressUsers($roles, $perPage, $offset);
    
    Utils::logError("Usuarios WordPress cargados - Página: $page, Total: $totalUsers, Por página: $perPage", 'INFO', 'usuarios.php');
} catch (Exception $e) {
    Utils::logError("Error cargando usuarios WordPress: " . $e->getMessage(), 'ERROR', 'usuarios.php');
}

// 9. Variables para compatibilidad con el menú
$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

?>
<?php include("parts/header.php"); ?>
<body style="padding-top: 70px">
<div class="container">
<?php include("parts/men.php"); ?><br />
<br />

<!-- Mostrar mensajes de éxito/error -->
<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
    <i class="fa fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Usuarios WordPress/WooCommerce</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-custom" data-bs-toggle="modal" data-bs-target="#creausu">
                <i class="fas fa-user-plus me-1"></i>Nuevo Usuario
            </button>
            <button class="btn btn-secondary btn-custom" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Actualizar
            </button>
        </div>
    </div>

<div class="tab-content">
    <br />
    <?php if (!empty($usuarios_list)): ?>
        <!-- Barra de búsqueda -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" id="busca" placeholder="Buscar por nombre, email o documento...">
                    <button type="button" class="btn btn-success btn-custom">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Información de resultados (estilo ventas.php) -->
        <div class="alert alert-success d-flex justify-content-between align-items-center mb-4">
            <div>
                <i class="fas fa-info-circle me-2"></i>
                Total usuarios: <strong><?php echo $totalUsers; ?></strong>
                | Página actual: <strong><?php echo $page; ?> de <?php echo $totalPages; ?></strong>
                | Mostrando: <strong><?php echo (($page - 1) * $perPage) + 1; ?> - <?php echo min($page * $perPage, $totalUsers); ?></strong>
            </div>
            <div class="d-flex align-items-center gap-3 form-group">
                <label for="per_page" class="form-label mb-0 text-primary">Por página:</label>
                <select id="per_page" class="form-control form-select form-select-sm pe-4" style="width: auto;" onchange="changePerPage(this.value)">
                    <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th><i class="fa fa-user"></i> Usuario</th>
                        <th><i class="fa fa-envelope"></i> Email</th>
                        <th><i class="fa fa-id-card"></i> DNI/Documento</th>
                        <th><i class="fa fa-calendar"></i> Registro</th>
                        <th><i class="fa fa-shield"></i> Rol</th>
                        <th class="text-center"><i class="fa fa-toggle-on"></i> Estado</th>
                        <th class="text-center"><i class="fa fa-cogs"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody id="donde">
                    <?php foreach ($usuarios_list as $usuario): 
                        // Procesar estado
                        $estado_texto = ($usuario['estado'] == 0) ? 'Activo' : 'Inactivo';
                        $estado_class = ($usuario['estado'] == 0) ? 'success' : 'secondary';
                        
                        // Procesar rol
                        $rol_texto = 'Usuario';
                        if (strpos($usuario['rol'], 'administrator') !== false) {
                            $rol_texto = 'Administrador';
                        } elseif (strpos($usuario['rol'], 'customer') !== false) {
                            $rol_texto = 'Cliente';
                        } elseif (strpos($usuario['rol'], 'guest_customer') !== false) {
                            $rol_texto = 'Cliente Invitado';
                        }
                        
                        // Formatear fecha
                        $fecha_registro = date('d/m/Y', strtotime($usuario['fecha_registro']));
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary bg-custom text-white rounded-circle d-flex align-items-center justify-content-center mr-2">
                                    <?php echo strtoupper(substr($usuario['nombre'] ?: $usuario['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                    <br><small class="text-muted">@<?php echo htmlspecialchars($usuario['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($usuario['email']); ?>" class="text-decoration-none text-primary text-custom">
                                <?php echo htmlspecialchars($usuario['email']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-warning bg-custom px-3 py-2">
                                <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($usuario['documento'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo $fecha_registro; ?></small>
                        </td>
                        <td>
                            <span class="badge bg-danger bg-custom px-3 py-2">
                                <i class="fas fa-user-tag me-1"></i><?php echo $rol_texto; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $estado_class; ?> bg-custom px-3 py-2">
                                <i class="fas fa-<?php echo $estado_texto === 'Activo' ? 'check-circle' : 'times-circle'; ?> me-1"></i><?php echo $estado_texto; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-primary btn-custom px-3" 
                                        data-bs-toggle="modal" data-bs-target="#verUsuario"
                                        data-id="<?php echo $usuario['id_usuarios']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                        data-apellido="<?php echo htmlspecialchars($usuario['apellido']); ?>"
                                        data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                        data-documento="<?php echo htmlspecialchars($usuario['documento']); ?>"
                                        data-barrio="<?php echo htmlspecialchars($usuario['barrio']); ?>"
                                        data-telefono="<?php echo htmlspecialchars($usuario['telefono']); ?>"
                                        data-direccion="<?php echo htmlspecialchars($usuario['direccion']); ?>"
                                        data-ciudad="<?php echo htmlspecialchars($usuario['ciudad']); ?>"
                                        data-departamento="<?php echo htmlspecialchars($usuario['departamento']); ?>"
                                        data-username="<?php echo htmlspecialchars($usuario['username']); ?>"
                                        data-rol="<?php echo htmlspecialchars($rol_texto); ?>"
                                        data-estado="<?php echo $estado_texto; ?>"
                                        data-fecha="<?php echo $fecha_registro; ?>"
                                        title="Ver detalles completos">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Controles de paginación (estilo ventas.php) -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="d-flex align-items-center">
                <small class="text-muted">
                    Mostrando <?php echo (($page - 1) * $perPage) + 1; ?> a 
                    <?php echo min($page * $perPage, $totalUsers); ?> de 
                    <?php echo $totalUsers; ?> usuarios
                </small>
            </div>
        </div>
        
        <nav aria-label="Paginación de usuarios" class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Botón Anterior -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $perPage; ?>">
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
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                // Mostrar primera página si no está en el rango
                if ($startPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page=1&per_page=<?php echo $perPage; ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Páginas en el rango -->
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link text-white btn btn-sm btn-custom px-3 py-2 mx-1 <?php echo ($i == $page) ? 'btn-success' : 'btn-danger'; ?>" href="?page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Mostrar última página si no está en el rango -->
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1 opacity-50">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page=<?php echo $totalPages; ?>&per_page=<?php echo $perPage; ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>

                <!-- Botón Siguiente -->
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link btn btn-sm btn-custom btn-danger text-white px-3 py-2 mx-1" href="?page=<?php echo min($totalPages, $page + 1); ?>&per_page=<?php echo $perPage; ?>">
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
        <?php endif; ?>
        
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fa fa-users fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No se encontraron usuarios WordPress</h4>
            <p class="text-muted">Los usuarios se crearán automáticamente cuando se registren clientes en el sistema.</p>
        </div>
    <?php endif; ?>
</div>
</div>
	<?php include("parts/foot.php"); ?>

	
<!-- Modal Ver Usuario -->
<div class="modal fade" id="verUsuario" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary bg-custom text-white">
                <h4 class="modal-title">
                    <i class="fa fa-user"></i> Detalles del Usuario WordPress
                </h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 table-responsive">
                        <hr>
                        <h6><i class="fas fa-user me-2"></i> Información del Cliente</h6>
                        <hr>
                        <table class="table table-sm table-striped">
                            <tr>
                                <td class="px-3 py-2"><strong>Nombre:</strong></td>
                                <td class="px-3 py-2" id="modal-nombre-completo">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Email:</strong></td>
                                <td class="px-3 py-2">
                                    <a href="#" id="modal-email-link" class="text-decoration-none text-primary text-custom">
                                        <span id="modal-email">-</span>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>DNI:</strong></td>
                                <td class="px-3 py-2" id="modal-documento">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Teléfono:</strong></td>
                                <td class="px-3 py-2" id="modal-telefono" class="text-primary text-custom">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Dirección:</strong></td>
                                <td class="px-3 py-2" id="modal-direccion">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Ciudad:</strong></td>
                                <td class="px-3 py-2" id="modal-ubicacion">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 table-responsive">
                        <hr>
                        <h6><i class="fas fa-info-circle me-2"></i> Información del Sistema</h6>
                        <hr>
                        <table class="table table-sm table-striped">
                            <tr>
                                <td class="px-3 py-2"><strong>ID Usuario:</strong></td>
                                <td class="px-3 py-2" id="modal-id">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Username:</strong></td>
                                <td class="px-3 py-2"><span id="modal-username" class="badge bg-danger bg-custom px-3 py-2">-</span></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Rol:</strong></td>
                                <td class="px-3 py-2">
                                    <span class="badge badge-info bg-success bg-custom px-3 py-2" id="modal-rol">-</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Estado:</strong></td>
                                <td class="px-3 py-2">
                                    <span class="badge" id="modal-estado-badge">-</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Fecha Registro:</strong></td>
                                <td class="px-3 py-2" id="modal-fecha">-</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Barrio:</strong></td>
                                <td class="px-3 py-2" id="modal-barrio">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-info bg-success bg-custom mt-3 text-white border-0">
                    <i class="fa fa-info-circle"></i>
                    <strong>Nota:</strong> Este usuario está gestionado por WordPress/WooCommerce. 
                    Los cambios deben realizarse desde el panel de administración de WordPress o desde la creación de la orden.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
		
<!-- Modal Crear Usuario -->
<div class="modal fade" id="creausu" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h4 class="modal-title">
                    <i class="fa fa-user-plus"></i> Crear Usuario WordPress
                </h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="usuarios.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="n_usuarios" value="1"/>
                    
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Información:</strong> Se creará un usuario WordPress con rol de cliente invitado.
                        La contraseña se generará automáticamente.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre">
                                    <i class="fa fa-user"></i> Nombre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       placeholder="Ej: Juan" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="apellido">
                                    <i class="fa fa-user"></i> Apellido <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="apellido" name="apellido" 
                                       placeholder="Ej: Pérez" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fa fa-envelope"></i> Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="ejemplo@correo.com" required>
                        <small class="form-text text-muted">
                            Se usará como identificador único del usuario
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="documento">
                            <i class="fa fa-id-card"></i> DNI/Documento <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="documento" name="documento" 
                               placeholder="Ej: 12345678" required>
                        <small class="form-text text-muted">
                            Número de identificación del cliente
                        </small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Nota:</strong> El username se generará automáticamente en formato nombre.apellido
                        y la contraseña será aleatoria por seguridad.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-success btn-custom">
                        <i class="fas fa-user-plus me-2"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Generar mapeo de estados desde datos centralizados (igual que ventas.php)
const colombiaStates = <?php
    $states_file = 'data/data-plugin-departamentos-y-ciudades-de-colombia-para-woocommerce/states/CO.php';
    if (file_exists($states_file)) {
        $colombia_states = include($states_file);
        echo json_encode($colombia_states);
    } else {
        echo '{}';
    }
?>;

function convertStateCode(stateCode) {
    // Usar datos centralizados del plugin de Colombia
    return colombiaStates[stateCode] || stateCode;
}

function buildLocationString(userData) {
    let location = '';
    if (userData.ciudad) {
        location = userData.ciudad;
    }
    if (userData.departamento) {
        location += (location ? ', ' : '') + convertStateCode(userData.departamento);
    }
    // Agregar Colombia por defecto
    location += (location ? ', ' : '') + 'Colombia';
    return location || 'N/A';
}

// Función para cambiar registros por página
function changePerPage(perPage) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', perPage);
    urlParams.set('page', '1'); // Resetear a página 1
    window.location.search = urlParams.toString();
}

$(document).ready(function(){
    // Búsqueda en tiempo real
    $("#busca").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#donde tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Modal Ver Usuario - Cargar datos (Bootstrap 5 compatible)
    document.getElementById('verUsuario').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        
        // Obtener datos del botón
        var id = button.getAttribute('data-id');
        var nombre = button.getAttribute('data-nombre');
        var apellido = button.getAttribute('data-apellido');
        var email = button.getAttribute('data-email');
        var documento = button.getAttribute('data-documento');
        var barrio = button.getAttribute('data-barrio');
        var telefono = button.getAttribute('data-telefono');
        var direccion = button.getAttribute('data-direccion');
        var ciudad = button.getAttribute('data-ciudad');
        var departamento = button.getAttribute('data-departamento');
        var username = button.getAttribute('data-username');
        var rol = button.getAttribute('data-rol');
        var estado = button.getAttribute('data-estado');
        var fecha = button.getAttribute('data-fecha');
        
        // Información del Cliente (formato tabla como ventas.php)
        document.getElementById('modal-nombre-completo').textContent = (nombre || '') + ' ' + (apellido || '') || 'N/A';
        document.getElementById('modal-email').textContent = email || 'N/A';
        document.getElementById('modal-email-link').href = 'mailto:' + (email || '');
        document.getElementById('modal-documento').textContent = documento || 'N/A';
        
        // Teléfono con enlace si existe
        var telefonoElement = document.getElementById('modal-telefono');
        if (telefono && telefono !== 'N/A' && telefono !== '') {
            telefonoElement.innerHTML = '<a href="tel:' + telefono + '" class="text-decoration-none text-primary text-custom">' + telefono + '</a>';
        } else {
            telefonoElement.textContent = 'N/A';
        }
        
        // Dirección completa (igual que buildFullAddress en ventas.php)
        var direccionCompleta = direccion || '';
        if (barrio) {
            if (direccionCompleta) direccionCompleta += ', ';
            direccionCompleta += barrio;
        }
        document.getElementById('modal-direccion').textContent = direccionCompleta || 'N/A';
        
        // Ubicación usando buildLocationString (igual que ventas.php)
        var userData = {
            ciudad: ciudad,
            departamento: departamento
        };
        var ubicacion = buildLocationString(userData);
        document.getElementById('modal-ubicacion').textContent = ubicacion;
        
        // Información del Sistema
        document.getElementById('modal-id').textContent = id;
        document.getElementById('modal-username').textContent = username;
        document.getElementById('modal-username').classList.add('badge', 'bg-danger', 'bg-custom', 'px-3', 'py-2');
        document.getElementById('modal-rol').textContent = rol;
        document.getElementById('modal-fecha').textContent = fecha;
        document.getElementById('modal-barrio').textContent = barrio || 'N/A';
        
        // Configurar badge de estado
        var estadoBadge = document.getElementById('modal-estado-badge');
        estadoBadge.textContent = estado;
        estadoBadge.className = 'badge'; // Reset classes
        if (estado === 'Activo') {
            estadoBadge.classList.add('badge-success');
        } else {
            estadoBadge.classList.add('badge-secondary');
        }
    });
    
    // Validación del formulario de crear usuario
    $('#creausu form').on('submit', function(e) {
        var nombre = $('#nombre').val().trim();
        var apellido = $('#apellido').val().trim();
        var email = $('#email').val().trim();
        var documento = $('#documento').val().trim();
        
        if (!nombre || !apellido || !email || !documento) {
            e.preventDefault();
            alert('Por favor complete todos los campos requeridos.');
            return false;
        }
        
        // Validar formato de email
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Por favor ingrese un email válido.');
            return false;
        }
        
        return true;
    });
    
    // Auto-cerrar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert-dismissible').fadeOut();
    }, 5000);
});

// Función para actualizar contador de usuarios
function updateUserCount() {
    var visibleRows = $("#donde tr:visible").length;
    $('.badge-info').text('Usuarios visibles: ' + visibleRows);
}

</script>

</body>
</html>