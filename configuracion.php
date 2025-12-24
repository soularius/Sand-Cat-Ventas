<?php

/**
 * Página de Configuración del Sistema
 * Permite gestionar configuraciones y actualizar contraseñas de usuarios
 */

// 1. Cargar autoloader del sistema
require_once('class/autoload.php');

// 2. Incluir el sistema de login dinámico
require_once('parts/login_handler.php');

// 3. Lógica de autenticación y procesamiento
// Requerir autenticación - redirige a index.php si no está logueado
requireLogin('index.php');

// Obtener datos del usuario actual
$colname_usuario = Utils::captureValue('MM_Username', 'SESSION', '');
if ($colname_usuario) {
    $colname_usuario = mysqli_real_escape_string($sandycat, $colname_usuario);
}

$query_usuario = sprintf("SELECT * FROM ingreso WHERE elnombre = '$colname_usuario'");
$usuario = mysqli_query($sandycat, $query_usuario) or die(mysqli_error($sandycat));
$row_usuario = mysqli_fetch_assoc($usuario);
$totalRows_usuario = mysqli_num_rows($usuario);

$ellogin = $row_usuario['elnombre'] ?? '';
$id_usuarios = $row_usuario['id_ingreso'] ?? 0;

// Crear variable compatible para el menú
if (!isset($row_usuario['nombre']) && isset($row_usuario['elnombre'])) {
    $row_usuario['nombre'] = $row_usuario['elnombre'];
}

// Variables para mensajes
$success_message = '';
$error_message = '';

// Procesar formularios
if (Utils::isPostRequest()) {
    $postData = Utils::capturePostData(['action', 'config_key', 'config_value', 'config_tipo', 'delete_key', 'username', 'new_password', 'confirm_password'], true);

    switch ($postData['action']) {
        case 'upload_file':
            // Manejar subida de archivos
            header('Content-Type: application/json');
            
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la subida.']);
                exit;
            }
            
            $file = $_FILES['file'];
            $upload_dir = 'assets/img/uploads/';
            
            // Crear directorio si no existe
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    echo json_encode(['success' => false, 'error' => 'No se pudo crear el directorio de subida.']);
                    exit;
                }
            }
            
            // Validar tipo de archivo y tamaño
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Extensiones permitidas: ' . implode(', ', $allowed_extensions)]);
                exit;
            }
            
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'error' => 'El archivo es demasiado grande. Tamaño máximo: 5MB.']);
                exit;
            }
            
            // Generar nombre único para evitar conflictos
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $file_path = $upload_dir . $file_name;
            
            // Mover archivo al directorio de destino
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                echo json_encode([
                    'success' => true, 
                    'file_path' => $file_path,
                    'file_name' => $file['name']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al mover el archivo al directorio de destino.']);
            }
            exit;
            
        case 'add_config':
            if (!empty($postData['config_key']) && !empty($postData['config_value'])) {
                $key = Utils::sanitizeInput($postData['config_key']);
                $value = Utils::sanitizeInput($postData['config_value']);
                $tipo = Utils::sanitizeInput($postData['config_tipo']) ?: 'TEXT';

                // Validación específica por tipo
                $validation_passed = true;
                
                if ($tipo === 'NUMBER' && !is_numeric($value)) {
                    $error_message = "El valor debe ser numérico para el tipo NUMBER.";
                    $validation_passed = false;
                } elseif ($tipo === 'FILE') {
                    // Validación mejorada para archivos
                    $is_valid_url = filter_var($value, FILTER_VALIDATE_URL) !== false;
                    $is_valid_path = false;

                    if (!$is_valid_url) {
                        // Verificar si es una ruta válida (relativa o absoluta)
                        $is_valid_path = (
                            // Ruta relativa válida
                            (strpos($value, '/') !== false || strpos($value, '\\') !== false || strpos($value, '.') !== false) &&
                            // No contiene caracteres peligrosos
                            !preg_match('/[<>:"|?*]/', $value) &&
                            // Tiene extensión válida
                            preg_match('/\.[a-zA-Z0-9]{1,10}$/', $value)
                        );

                        // Si es ruta absoluta, verificar que existe
                        if ($is_valid_path && (strpos($value, '/') === 0 || preg_match('/^[A-Za-z]:\\\\/', $value))) {
                            $is_valid_path = file_exists($value);
                        }
                    }

                    if (!$is_valid_url && !$is_valid_path) {
                        $validation_passed = false;
                        if (empty($value)) {
                            $error_message = "El campo archivo es requerido.";
                        } elseif (!preg_match('/\.[a-zA-Z0-9]{1,10}$/', $value)) {
                            $error_message = "El archivo debe tener una extensión válida (ej: .jpg, .pdf, .txt).";
                        } elseif (preg_match('/[<>:"|?*]/', $value)) {
                            $error_message = "La ruta del archivo contiene caracteres no válidos.";
                        } else {
                            $error_message = "Debe ser una URL válida (ej: https://ejemplo.com/archivo.pdf) o una ruta de archivo válida (ej: uploads/documento.pdf).";
                        }
                    }
                }
                
                // Guardar configuración si pasó la validación
                if ($validation_passed) {
                    if (DatabaseConfig::setConfigValue($key, $value, $tipo)) {
                        $success_message = "Configuración '$key' guardada correctamente (Tipo: $tipo).";
                    } else {
                        $error_message = "Error al guardar la configuración.";
                    }
                }
            } else {
                $error_message = "Debe completar todos los campos de configuración.";
            }
            break;

        case 'delete_config':
            if (!empty($postData['delete_key'])) {
                $key = Utils::sanitizeInput($postData['delete_key']);

                if (DatabaseConfig::deleteConfig($key)) {
                    $success_message = "Configuración '$key' eliminada correctamente.";
                } else {
                    $error_message = "Error al eliminar la configuración.";
                }
            }
            break;

        case 'update_password':
            if (!empty($postData['username']) && !empty($postData['new_password']) && !empty($postData['confirm_password'])) {
                $username = Utils::sanitizeInput($postData['username']);
                $new_password = $postData['new_password'];
                $confirm_password = $postData['confirm_password'];

                // Validar que las contraseñas coincidan
                if ($new_password !== $confirm_password) {
                    $error_message = "Las contraseñas no coinciden.";
                } elseif (strlen($new_password) < 6) {
                    $error_message = "La contraseña debe tener al menos 6 caracteres.";
                } else {
                    if (DatabaseConfig::updateUserPassword($username, $new_password)) {
                        $success_message = "Contraseña actualizada correctamente para el usuario '$username'.";
                    } else {
                        $error_message = "Error al actualizar la contraseña.";
                    }
                }
            } else {
                $error_message = "Debe completar todos los campos de contraseña.";
            }
            break;
    }
}

// Obtener configuraciones existentes
$all_configs = DatabaseConfig::getAllConfig();

// Obtener usuarios del sistema
$system_users = DatabaseConfig::getSystemUsers();

// 4. DESPUÉS: Cargar presentación
include("parts/header.php");
?>

<body>
    <div class="container">
        <?php include("parts/menu.php"); ?>
        <div class="py-5"></div>
        <section class="">
            <div class="row justify-content-center">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-cogs me-2"></i>Configuración del Sistema
                    </h2>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Formulario de Agregar Configuración (Prioridad) -->
                <div class="col-12 mb-4">
                    <div class="card config-card">
                        <div class="config-header bg-success bg-custom">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>
                                Agregar Nueva Configuración
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="configForm">
                                <input type="hidden" name="action" value="add_config">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="config_key" class="form-label">
                                            <i class="fas fa-key me-1"></i>Clave
                                        </label>
                                        <input type="text" class="form-control" id="config_key" name="config_key"
                                            placeholder="ej: empresa_nombre" required>
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label for="config_tipo" class="form-label">
                                            <i class="fas fa-tags me-1"></i>Tipo
                                        </label>
                                        <select class="form-select form-control" id="config_tipo" name="config_tipo" required onchange="updateValueField()">
                                            <option value="TEXT">Texto</option>
                                            <option value="NUMBER">Número</option>
                                            <option value="FILE">Archivo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="config_value" class="form-label">
                                            <i class="fas fa-edit me-1"></i>Valor
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="config_value" name="config_value"
                                                placeholder="ej: Sand Y Cat" required>
                                            <button type="button" class="btn btn-outline-secondary" id="fileBtn" style="display: none;" onclick="selectFile()">
                                                <i class="fas fa-folder-open"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted" id="valueHelp">Ingrese el valor de configuración</small>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center">
                                        <button type="submit" class="btn btn-success btn-custom py-3 px-4">
                                            <i class="fas fa-save me-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Configuraciones Existentes -->
                <div class="col-lg-8">
                    <div class="card config-card">
                        <div class="config-header bg-primary bg-custom">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-list me-2"></i>
                                    Configuraciones del Sistema
                                </span>
                                <span class="badge bg-light text-dark ms-2" id="configCount">
                                    <?php echo count($all_configs); ?> elementos
                                </span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="configTableContainer">

                                <?php if (!empty($all_configs)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover config-table mb-0" id="configTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th><i class="fas fa-key me-1"></i>Clave</th>
                                                    <th><i class="fas fa-tags me-1"></i>Tipo</th>
                                                    <th><i class="fas fa-edit me-1"></i>Valor</th>
                                                    <th width="120"><i class="fas fa-cogs me-1"></i>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="configTableBody">
                                                <?php foreach ($all_configs as $config): ?>
                                                    <tr data-config-key="<?php echo htmlspecialchars($config['clave']); ?>">
                                                        <td>
                                                            <code class="bg-primary bg-custom text-white px-2 py-1 rounded">
                                                                <?php echo htmlspecialchars($config['clave']); ?>
                                                            </code>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-custom bg-<?php
                                                                                    echo $config['tipo'] === 'FILE' ? 'info' : ($config['tipo'] === 'NUMBER' ? 'warning' : 'secondary');
                                                                                    ?>">
                                                                <i class="fas fa-<?php
                                                                                    echo $config['tipo'] === 'FILE' ? 'file' : ($config['tipo'] === 'NUMBER' ? 'hashtag' : 'font');
                                                                                    ?> me-1"></i>
                                                                <?php echo htmlspecialchars($config['tipo']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($config['tipo'] === 'FILE'): ?>
                                                                <?php 
                                                                // Verificar si es una imagen
                                                                $file_path = $config['valor'];
                                                                $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png']);
                                                                ?>
                                                                
                                                                <?php if ($is_image && file_exists($file_path)): ?>
                                                                    <!-- Preview de imagen -->
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="me-3">
                                                                            <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                                                                 alt="Preview" 
                                                                                 class="img-thumbnail" 
                                                                                 style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                                                                 onclick="showImageModal('<?php echo htmlspecialchars($file_path); ?>', '<?php echo htmlspecialchars($config['clave']); ?>')">
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-bold text-info">
                                                                                <i class="fas fa-image me-1"></i>
                                                                                Imagen
                                                                            </div>
                                                                            <small class="text-muted text-truncate d-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($config['valor']); ?>">
                                                                                <?php echo htmlspecialchars(basename($config['valor'])); ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <!-- Archivo normal -->
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="fas fa-file me-2 text-info"></i>
                                                                        <span class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($config['valor']); ?>">
                                                                            <?php echo htmlspecialchars($config['valor']); ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php elseif ($config['tipo'] === 'NUMBER'): ?>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="fas fa-hashtag me-2 text-warning"></i>
                                                                    <span class="font-monospace fw-bold"><?php echo htmlspecialchars($config['valor']); ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="fas fa-font me-2 text-secondary"></i>
                                                                    <span><?php echo htmlspecialchars($config['valor']); ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-primary btn-custom py-2 px-3"
                                                                    onclick="editConfig('<?php echo htmlspecialchars($config['clave']); ?>', '<?php echo htmlspecialchars($config['valor']); ?>', '<?php echo htmlspecialchars($config['tipo']); ?>')"
                                                                    title="Editar configuración">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger btn-custom py-2 px-3"
                                                                    onclick="deleteConfig('<?php echo htmlspecialchars($config['clave']); ?>')"
                                                                    title="Eliminar configuración">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5" id="emptyState">
                                        <i class="fas fa-inbox fa-4x mb-3 opacity-50"></i>
                                        <h5>No hay configuraciones definidas</h5>
                                        <p class="mb-0">Utiliza el formulario de arriba para agregar tu primera configuración</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestión de Contraseñas -->
                <div class="col-lg-4">
                    <div class="card config-card">
                        <div class="config-header bg-success bg-custom">
                            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="update_password">

                                <div class="mb-3">
                                    <label for="username" class="form-label">Usuario</label>
                                    <select class="form-select" id="username" name="username" required>
                                        <option value="">Seleccionar usuario</option>
                                        <?php foreach ($system_users as $user): ?>
                                            <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password"
                                            placeholder="Mínimo 6 caracteres" required>
                                        <button type="button" class="btn btn-secondary btn-custom py-2 px-3" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye" id="new_password_icon"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="password_strength"></div>
                                    <small class="form-text text-muted">Mínimo 6 caracteres</small>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                            placeholder="Repetir contraseña" required>
                                        <button type="button" class="btn btn-secondary btn-custom py-2 px-3" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye" id="confirm_password_icon"></i>
                                        </button>
                                    </div>
                                    <div id="password_match" class="form-text"></div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-custom w-100" id="updatePasswordBtn" disabled>
                                    <i class="fas fa-lock me-2"></i>Actualizar Contraseña
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal de confirmación para eliminar configuración -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger bg-custom text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-center mb-3">
                        ¿Está seguro de que desea eliminar la configuración?
                    </p>
                    <div class="bg-warning bg-custom text-white d-flex align-items-center py-3 px-2 me-2" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>
                            <strong>Clave:</strong> <span id="deleteConfigKey" class="bg-light bg-custom px-2 py-1 rounded"></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 py-3 px-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-custom py-2 px-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger btn-custom py-2 px-3" onclick="confirmDelete()">
                        <i class="fas fa-trash me-1"></i>
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar imagen en tamaño completo -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-cuertar bg-custom text-white">
                    <h5 class="modal-title" id="imageModalTitle">Vista previa de imagen</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" alt="Preview" class="img-fluid" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-custom py-2 px-3" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para editar configuración
        function editConfig(key, value, tipo = 'TEXT') {
            const keyField = document.getElementById('config_key');
            const valueField = document.getElementById('config_value');
            const tipoField = document.getElementById('config_tipo');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            // Llenar los campos
            keyField.value = key;
            valueField.value = value;
            tipoField.value = tipo;
            
            // Deshabilitar key y tipo durante la edición
            keyField.disabled = true;
            tipoField.disabled = true;
            
            // Agregar clases visuales para indicar campos deshabilitados
            keyField.classList.add('bg-light', 'text-muted');
            tipoField.classList.add('bg-light', 'text-muted');
            
            // Cambiar el texto del botón para indicar modo edición
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-save"></i>';
                submitBtn.classList.remove('btn-success');
                submitBtn.classList.add('btn-warning');
            }
            
            // Agregar botón para cancelar edición
            addCancelEditButton();
            
            updateValueField();
            valueField.focus();
        }

        // Función para agregar botón de cancelar edición
        function addCancelEditButton() {
            const submitBtn = document.querySelector('button[type="submit"]');
            const existingCancelBtn = document.getElementById('cancelEditBtn');
            
            // Si ya existe el botón, no agregarlo de nuevo
            if (existingCancelBtn) {
                return;
            }
            
            // Crear botón de cancelar
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.id = 'cancelEditBtn';
            cancelBtn.className = 'btn btn-secondary btn-custom ms-2 py-3 px-4 ';
            cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
            cancelBtn.onclick = resetFormToAddMode;
            
            // Insertar después del botón de submit
            if (submitBtn && submitBtn.parentNode) {
                submitBtn.parentNode.insertBefore(cancelBtn, submitBtn.nextSibling);
            }
        }

        // Función para resetear el formulario al modo "agregar"
        function resetFormToAddMode() {
            const keyField = document.getElementById('config_key');
            const valueField = document.getElementById('config_value');
            const tipoField = document.getElementById('config_tipo');
            const submitBtn = document.querySelector('button[type="submit"]');
            const cancelBtn = document.getElementById('cancelEditBtn');
            
            // Habilitar campos key y tipo
            keyField.disabled = false;
            tipoField.disabled = false;
            
            // Remover clases visuales de campos deshabilitados
            keyField.classList.remove('bg-light', 'text-muted');
            tipoField.classList.remove('bg-light', 'text-muted');
            
            // Limpiar formulario
            keyField.value = '';
            valueField.value = '';
            tipoField.value = 'TEXT';
            
            // Restaurar botón de submit al modo "agregar"
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>';
                submitBtn.classList.remove('btn-warning');
                submitBtn.classList.add('btn-success');
            }
            
            // Remover botón de cancelar
            if (cancelBtn) {
                cancelBtn.remove();
            }
            
            // Actualizar campo de valor
            updateValueField();
            
            // Enfocar en el campo key
            keyField.focus();
        }

        // Función para actualizar el campo de valor según el tipo
        function updateValueField() {
            const tipo = document.getElementById('config_tipo').value;
            const valueField = document.getElementById('config_value');
            const fileBtn = document.getElementById('fileBtn');
            const helpText = document.getElementById('valueHelp');

            // Resetear clases y atributos
            valueField.type = 'text';
            valueField.step = '';
            valueField.min = '';
            fileBtn.style.display = 'none';

            switch (tipo) {
                case 'NUMBER':
                    valueField.type = 'number';
                    valueField.step = 'any';
                    valueField.placeholder = 'ej: 123.45';
                    helpText.textContent = 'Ingrese un valor numérico';
                    break;

                case 'FILE':
                    valueField.placeholder = 'ej: /ruta/archivo.jpg o https://ejemplo.com/archivo.pdf';
                    fileBtn.style.display = 'block';
                    helpText.textContent = 'Ingrese una ruta de archivo o URL';
                    break;

                case 'TEXT':
                default:
                    valueField.placeholder = 'ej: Sand Y Cat';
                    helpText.textContent = 'Ingrese el valor de configuración';
                    break;
            }
        }

        // Función para seleccionar archivo y subirlo a assets/img/uploads
        function selectFile() {
            // Crear input file temporal
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '*/*';

            fileInput.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Subir archivo a assets/img/uploads
                    uploadFile(file);
                }
            };

            fileInput.click();
        }

        // Función para subir archivo al servidor
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload_file');

            // Mostrar indicador de carga
            const valueField = document.getElementById('config_value');
            const originalValue = valueField.value;
            valueField.value = 'Subiendo archivo...';
            valueField.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar la ruta relativa del archivo subido
                    valueField.value = data.file_path;
                    showAlert('success', 'Archivo subido correctamente: ' + data.file_name);
                } else {
                    valueField.value = originalValue;
                    showAlert('danger', 'Error al subir archivo: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                valueField.value = originalValue;
                showAlert('danger', 'Error al subir el archivo.');
            })
            .finally(() => {
                valueField.disabled = false;
            });
        }

        // Función para mostrar/ocultar contraseña
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Validación de contraseña en tiempo real
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password_strength');

            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength';
            } else if (password.length < 6) {
                strengthBar.style.width = '33%';
                strengthBar.className = 'password-strength strength-weak';
            } else if (password.length < 10) {
                strengthBar.style.width = '66%';
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.className = 'password-strength strength-strong';
            }

            checkPasswordMatch();
        });

        // Validación de coincidencia de contraseñas
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password_match');
            const submitBtn = document.getElementById('updatePasswordBtn');
            const username = document.getElementById('username').value;

            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
                matchDiv.className = 'form-text';
            } else if (password === confirmPassword) {
                matchDiv.textContent = 'Las contraseñas coinciden';
                matchDiv.className = 'form-text text-success';
            } else {
                matchDiv.textContent = 'Las contraseñas no coinciden';
                matchDiv.className = 'form-text text-danger';
            }

            // Habilitar/deshabilitar botón de envío
            if (username && password.length >= 6 && password === confirmPassword) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Validar cuando se selecciona usuario
        document.getElementById('username').addEventListener('change', checkPasswordMatch);

        // Función para mostrar modal de imagen
        function showImageModal(imagePath, configKey) {
            document.getElementById('imageModalImg').src = imagePath;
            document.getElementById('imageModalTitle').textContent = 'Vista previa - ' + configKey;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // Validación y envío del formulario de configuración
        document.getElementById('configForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir envío normal del formulario

            const keyField = document.getElementById('config_key');
            const tipoField = document.getElementById('config_tipo');
            const valueField = document.getElementById('config_value');
            
            const tipo = tipoField.value;
            const valor = valueField.value;
            const clave = keyField.value;

            // Validación específica por tipo
            if (tipo === 'NUMBER' && valor && !isNumeric(valor)) {
                alert('El valor debe ser numérico para el tipo NUMBER.');
                return false;
            }

            if (tipo === 'FILE') {
                const fileValidation = isValidFile(valor);
                if (!fileValidation.valid) {
                    alert(fileValidation.message);
                    return false;
                }
            }

            // Temporalmente habilitar campos deshabilitados para que se envíen
            const wasKeyDisabled = keyField.disabled;
            const wasTipoDisabled = tipoField.disabled;
            
            if (wasKeyDisabled) keyField.disabled = false;
            if (wasTipoDisabled) tipoField.disabled = false;

            // Enviar formulario via AJAX
            const formData = new FormData(this);
            
            // Restaurar estado deshabilitado
            if (wasKeyDisabled) keyField.disabled = true;
            if (wasTipoDisabled) tipoField.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const successAlert = doc.querySelector('.alert-success');
                const errorAlert = doc.querySelector('.alert-danger');

                if (successAlert) {
                    showAlert('success', successAlert.textContent.trim());
                    const isEditMode = keyField.disabled;
                    
                    if (!isEditMode) {
                        addConfigToTable(clave, tipo, valor);
                    } else {
                        updateConfigInTable(clave, tipo, valor);
                    }
                    resetFormToAddMode();
                    updateConfigCount();
                } else if (errorAlert) {
                    showAlert('danger', errorAlert.textContent.trim());
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error al procesar la solicitud.');
            });
        });

        // Función para validar archivo mejorada
        function isValidFile(value) {
            if (!value || value.trim() === '') {
                return {
                    valid: false,
                    message: 'El campo archivo es requerido.'
                };
            }

            // Validar URL
            try {
                new URL(value);
                return {
                    valid: true,
                    message: ''
                };
            } catch (e) {
                // Si no es URL, validar como ruta de archivo

                // Verificar extensión
                if (!/\.[a-zA-Z0-9]{1,10}$/.test(value)) {
                    return {
                        valid: false,
                        message: 'El archivo debe tener una extensión válida (ej: .jpg, .pdf, .txt).'
                    };
                }

                // Verificar caracteres peligrosos
                if (/[<>:"|?*]/.test(value)) {
                    return {
                        valid: false,
                        message: 'La ruta del archivo contiene caracteres no válidos.'
                    };
                }

                // Verificar que tenga estructura de ruta
                if (!(value.includes('/') || value.includes('\\') || value.includes('.'))) {
                    return {
                        valid: false,
                        message: 'Debe ser una ruta de archivo válida (ej: uploads/documento.pdf).'
                    };
                }

                return {
                    valid: true,
                    message: ''
                };
            }
        }

        // Validación en tiempo real para campos
        document.getElementById('config_value').addEventListener('input', function() {
            const tipo = document.getElementById('config_tipo').value;
            const valor = this.value;
            const helpText = document.getElementById('valueHelp');

            if (tipo === 'FILE' && valor) {
                const fileValidation = isValidFile(valor);
                if (!fileValidation.valid) {
                    helpText.textContent = fileValidation.message;
                    helpText.className = 'form-text text-danger';
                    this.classList.add('is-invalid');
                } else {
                    helpText.textContent = 'Archivo válido';
                    helpText.className = 'form-text text-success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else if (tipo === 'NUMBER' && valor) {
                if (!isNumeric(valor)) {
                    helpText.textContent = 'Debe ser un valor numérico';
                    helpText.className = 'form-text text-danger';
                    this.classList.add('is-invalid');
                } else {
                    helpText.textContent = 'Número válido';
                    helpText.className = 'form-text text-success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            } else {
                // Resetear estilos para TEXT o campos vacíos
                this.classList.remove('is-invalid', 'is-valid');
                updateValueField(); // Restaurar texto de ayuda original
            }
        });

        // Función para mostrar alertas dinámicas (v2.0 - corregida)
        function showAlert(type, message) {
            // Remover solo alertas que NO estén dentro de un modal
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => {
                if (!alert.closest('.modal')) {
                    alert.remove();
                }
            });

            // Crear nueva alerta
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');

            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alertDiv.innerHTML = `
                <i class="fas fa-${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Insertar después del título de la página
            const titleSection = document.querySelector('h2');
            if (titleSection && titleSection.parentNode) {
                titleSection.parentNode.insertBefore(alertDiv, titleSection.nextSibling);
            } else {
                // Fallback: insertar al inicio del contenedor principal
                const container = document.querySelector('.container section');
                if (container) {
                    container.insertBefore(alertDiv, container.firstChild);
                }
            }

            // Auto-dismiss después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Función para agregar configuración a la tabla
        function addConfigToTable(clave, tipo, valor) {
            const tableBody = document.getElementById('configTableBody');
            const emptyState = document.getElementById('emptyState');

            // Si existe el estado vacío, removerlo y crear la tabla
            if (emptyState) {
                const tableContainer = document.getElementById('configTableContainer');
                tableContainer.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover config-table mb-0" id="configTable">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-key me-1"></i>Clave</th>
                                    <th><i class="fas fa-tags me-1"></i>Tipo</th>
                                    <th><i class="fas fa-edit me-1"></i>Valor</th>
                                    <th width="120"><i class="fas fa-cogs me-1"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="configTableBody"></tbody>
                        </table>
                    </div>
                `;
            }

            // Crear nueva fila
            const newRow = document.createElement('tr');
            newRow.setAttribute('data-config-key', clave);

            // Determinar colores y iconos según el tipo
            let badgeColor, iconType;
            switch (tipo) {
                case 'FILE':
                    badgeColor = 'info';
                    iconType = 'file';
                    break;
                case 'NUMBER':
                    badgeColor = 'warning';
                    iconType = 'hashtag';
                    break;
                default:
                    badgeColor = 'secondary';
                    iconType = 'font';
            }

            // Crear contenido de la celda de valor
            let valorContent;
            if (tipo === 'FILE') {
                // Verificar si es una imagen
                const fileExtension = valor.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png'].includes(fileExtension);
                
                if (isImage) {
                    valorContent = `
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <img src="${valor}" 
                                     alt="Preview" 
                                     class="img-thumbnail" 
                                     style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                     onclick="showImageModal('${valor}', '${clave}')">
                            </div>
                            <div>
                                <div class="fw-bold text-info">
                                    <i class="fas fa-image me-1"></i>
                                    Imagen
                                </div>
                                <small class="text-muted text-truncate d-block" style="max-width: 200px;" title="${valor}">
                                    ${valor.split('/').pop()}
                                </small>
                            </div>
                        </div>
                    `;
                } else {
                    valorContent = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file me-2 text-info"></i>
                            <span class="text-truncate" style="max-width: 250px;" title="${valor}">
                                ${valor}
                            </span>
                        </div>
                    `;
                }
            } else if (tipo === 'NUMBER') {
                valorContent = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-hashtag me-2 text-warning"></i>
                        <span class="font-monospace fw-bold">${valor}</span>
                    </div>
                `;
            } else {
                valorContent = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-font me-2 text-secondary"></i>
                        <span>${valor}</span>
                    </div>
                `;
            }

            newRow.innerHTML = `
                <td>
                    <span class="bg-primary bg-custom text-white px-2 py-1 rounded">${clave}</span>
                </td>
                <td>
                    <span class="badge bg-${badgeColor}">
                        <i class="fas fa-${iconType} me-1"></i>
                        ${tipo}
                    </span>
                </td>
                <td>${valorContent}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary btn-custom py-2 px-3" 
                                onclick="editConfig('${clave}', '${valor}', '${tipo}')"
                                title="Editar configuración">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-custom py-2 px-3" 
                                onclick="deleteConfig('${clave}')"
                                title="Eliminar configuración">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;

            // Agregar la fila a la tabla
            const tbody = document.getElementById('configTableBody');
            tbody.appendChild(newRow);

            // Animar la nueva fila
            newRow.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                newRow.style.backgroundColor = '';
            }, 2000);
        }

        // Función para actualizar configuración existente en la tabla
        function updateConfigInTable(clave, tipo, valor) {
            const existingRow = document.querySelector(`tr[data-config-key="${clave}"]`);
            if (!existingRow) {
                console.error('No se encontró la fila para actualizar:', clave);
                return;
            }

            // Determinar colores y iconos según el tipo
            let badgeColor, iconType;
            switch (tipo) {
                case 'FILE':
                    badgeColor = 'info';
                    iconType = 'file';
                    break;
                case 'NUMBER':
                    badgeColor = 'warning';
                    iconType = 'hashtag';
                    break;
                default:
                    badgeColor = 'secondary';
                    iconType = 'font';
            }

            // Crear contenido de la celda de valor
            let valorContent;
            if (tipo === 'FILE') {
                // Verificar si es una imagen
                const fileExtension = valor.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png'].includes(fileExtension);
                
                if (isImage) {
                    valorContent = `
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <img src="${valor}" 
                                     alt="Preview" 
                                     class="img-thumbnail" 
                                     style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                     onclick="showImageModal('${valor}', '${clave}')">
                            </div>
                            <div>
                                <div class="fw-bold text-info">
                                    <i class="fas fa-image me-1"></i>
                                    Imagen
                                </div>
                                <small class="text-muted text-truncate d-block" style="max-width: 200px;" title="${valor}">
                                    ${valor.split('/').pop()}
                                </small>
                            </div>
                        </div>
                    `;
                } else {
                    valorContent = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file me-2 text-info"></i>
                            <span class="text-truncate" style="max-width: 250px;" title="${valor}">
                                ${valor}
                            </span>
                        </div>
                    `;
                }
            } else if (tipo === 'NUMBER') {
                valorContent = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-hashtag me-2 text-warning"></i>
                        <span class="font-monospace fw-bold">${valor}</span>
                    </div>
                `;
            } else {
                valorContent = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-font me-2 text-secondary"></i>
                        <span>${valor}</span>
                    </div>
                `;
            }

            // Actualizar el contenido de la fila existente
            existingRow.innerHTML = `
                <td>
                    <span class="bg-primary bg-custom text-white px-2 py-1 rounded">${clave}</span>
                </td>
                <td>
                    <span class="badge bg-${badgeColor}">
                        <i class="fas fa-${iconType} me-1"></i>
                        ${tipo}
                    </span>
                </td>
                <td>${valorContent}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary btn-custom py-2 px-3" 
                                onclick="editConfig('${clave}', '${valor}', '${tipo}')"
                                title="Editar configuración">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-custom py-2 px-3" 
                                onclick="deleteConfig('${clave}')"
                                title="Eliminar configuración">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;

            // Animar la fila actualizada
            existingRow.style.backgroundColor = '#fff3cd';
            setTimeout(() => {
                existingRow.style.backgroundColor = '';
            }, 2000);
        }

        // Función para actualizar el contador de configuraciones
        function updateConfigCount() {
            const tableBody = document.getElementById('configTableBody');
            const count = tableBody ? tableBody.children.length : 0;
            const countElement = document.getElementById('configCount');
            if (countElement) {
                countElement.textContent = `${count} elementos`;
            }
        }

        // Función para eliminar configuración de la tabla
        function removeConfigFromTable(clave) {
            const row = document.querySelector(`tr[data-config-key="${clave}"]`);
            if (row) {
                row.style.backgroundColor = '#f8d7da';
                setTimeout(() => {
                    row.remove();
                    updateConfigCount();

                    // Si no quedan filas, mostrar estado vacío
                    const tableBody = document.getElementById('configTableBody');
                    if (tableBody && tableBody.children.length === 0) {
                        const tableContainer = document.getElementById('configTableContainer');
                        tableContainer.innerHTML = `
                            <div class="text-center text-muted py-5" id="emptyState">
                                <i class="fas fa-inbox fa-4x mb-3 opacity-50"></i>
                                <h5>No hay configuraciones definidas</h5>
                                <p class="mb-0">Utiliza el formulario de arriba para agregar tu primera configuración</p>
                            </div>
                        `;
                    }
                }, 300);
            }
        }

        // Inicializar el formulario
        document.addEventListener('DOMContentLoaded', function() {
            updateValueField();
        });

        // Variable global para almacenar la clave a eliminar
        let configKeyToDelete = null;

        // Función para mostrar modal de confirmación de eliminación
        function deleteConfig(key) {
            configKeyToDelete = key;
            
            // Asignar la clave directamente y simple
            document.getElementById('deleteConfigKey').textContent = key;
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();
        }

        // Función para confirmar eliminación desde el modal
        function confirmDelete() {
            if (!configKeyToDelete) return;
            
            // Crear FormData para eliminar
            const formData = new FormData();
            formData.append('action', 'delete_config');
            formData.append('delete_key', configKeyToDelete);

            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
            modal.hide();

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Crear un documento temporal para parsear la respuesta
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Verificar si hay mensaje de éxito o error
                    const successAlert = doc.querySelector('.alert-success');
                    const errorAlert = doc.querySelector('.alert-danger');

                    if (successAlert) {
                        // Mostrar mensaje de éxito
                        showAlert('success', successAlert.textContent.trim());

                        // Remover fila de la tabla
                        removeConfigFromTable(configKeyToDelete);

                    } else if (errorAlert) {
                        // Mostrar mensaje de error
                        showAlert('danger', errorAlert.textContent.trim());
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Error al eliminar la configuración.');
                })
                .finally(() => {
                    // Limpiar variable global
                    configKeyToDelete = null;
                });
        }

        // Función para manejar el envío del formulario
        function handleSubmit(event) {
            event.preventDefault();

            // Obtener los campos del formulario
            const keyField = document.getElementById('config_key');
            const tipoField = document.getElementById('config_tipo');
            const valueField = document.getElementById('config_value');

            // Verificar si el campo clave está deshabilitado (modo edición)
            const isEditMode = keyField.disabled;

            // Capturar valores antes de cualquier manipulación
            const clave = keyField.value;
            const tipo = tipoField.value;
            const valor = valueField.value;

            // Temporalmente habilitar campos deshabilitados para que se envíen
            const wasKeyDisabled = keyField.disabled;
            const wasTipoDisabled = tipoField.disabled;
            
            if (wasKeyDisabled) keyField.disabled = false;
            if (wasTipoDisabled) tipoField.disabled = false;

            // Crear FormData para enviar
            const formData = new FormData(event.target);

            // Restaurar estado deshabilitado
            if (wasKeyDisabled) keyField.disabled = true;
            if (wasTipoDisabled) tipoField.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Crear un documento temporal para parsear la respuesta
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Verificar si hay mensaje de éxito o error
                const successAlert = doc.querySelector('.alert-success');
                const errorAlert = doc.querySelector('.alert-danger');

                if (successAlert) {
                    // Mostrar mensaje de éxito
                    showAlert('success', successAlert.textContent.trim());

                    // Usar los valores capturados anteriormente
                    
                    if (!isEditMode) {
                        addConfigToTable(clave, tipo, valor);
                    } else {
                        // En modo edición, actualizar la fila existente en la tabla
                        updateConfigInTable(clave, tipo, valor);
                    }

                    // Resetear formulario al modo "agregar"
                    resetFormToAddMode();

                    // Actualizar contador
                    updateConfigCount();

                } else if (errorAlert) {
                    // Mostrar mensaje de error
                    showAlert('danger', errorAlert.textContent.trim());
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error al procesar la solicitud.');
            });
        }

        // Agregar evento de envío al formulario
        const form = document.getElementById('configForm');
        form.addEventListener('submit', handleSubmit);

        // Función para validar si es numérico
        function isNumeric(value) {
            return !isNaN(parseFloat(value)) && isFinite(value);
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>

    <?php include("parts/foot.php"); ?>