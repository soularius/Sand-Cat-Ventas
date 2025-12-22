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

// 8. Obtener lista de usuarios WordPress usando WooCommerceCustomer
$usuarios_list = [];
try {
    // Usar método de la clase WooCommerceCustomer en lugar de consulta directa
    $usuarios_list = $wooCustomer->getAllWordPressUsers(['guest_customer', 'customer', 'administrator']);
    
    Utils::logError("Usuarios WordPress cargados usando WooCommerceCustomer: " . count($usuarios_list), 'INFO', 'usuarios.php');
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

<h2>Usuarios WordPress/WooCommerce 
    <a class="btn btn-success" href="#" title="Agregar Usuario" data-bs-toggle="modal" data-bs-target="#creausu">
        <i class="fa fa-plus-circle fa-lg"></i> Nuevo Usuario
    </a>
</h2>

<div class="tab-content">
    <br />
    <?php if (!empty($usuarios_list)): ?>
        <div class="row mb-3">
            <div class="col-md-6">
                <input class="form-control" id="busca" type="text" placeholder="Buscar por nombre, email o documento...">
            </div>
            <div class="col-md-6 text-right">
                <span class="badge badge-info">Total usuarios: <?php echo count($usuarios_list); ?></span>
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
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2">
                                    <?php echo strtoupper(substr($usuario['nombre'] ?: $usuario['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                    <br><small class="text-muted">@<?php echo htmlspecialchars($usuario['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($usuario['email']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($usuario['email']); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge badge-outline-primary">
                                <?php echo htmlspecialchars($usuario['documento'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo $fecha_registro; ?></small>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo $rol_texto; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-<?php echo $estado_class; ?>">
                                <?php echo $estado_texto; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
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
                                    <i class="fa fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
            <div class="modal-header bg-primary text-white">
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
                                    <a href="#" id="modal-email-link" class="text-decoration-none">
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
                                <td class="px-3 py-2" id="modal-telefono">-</td>
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
                                <td class="px-3 py-2"><code id="modal-username">-</code></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2"><strong>Rol:</strong></td>
                                <td class="px-3 py-2">
                                    <span class="badge badge-info" id="modal-rol">-</span>
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
                
                <div class="alert alert-info mt-3">
                    <i class="fa fa-info-circle"></i>
                    <strong>Nota:</strong> Este usuario está gestionado por WordPress/WooCommerce. 
                    Los cambios deben realizarse desde el panel de administración de WordPress.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cerrar
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-plus-circle"></i> Crear Usuario
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
            telefonoElement.innerHTML = '<a href="tel:' + telefono + '" class="text-decoration-none">' + telefono + '</a>';
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