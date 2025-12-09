<?php
/**
 * Visor de Logs de Depuración
 * Muestra los logs más recientes para depurar la búsqueda de clientes
 */

// Cargar autoloader del sistema
require_once('class/autoload.php');

// Requerir autenticación
require_once('parts/login_handler.php');
requireLogin('index.php');

// Obtener logs recientes
$log_file = 'logs/system.log';
$logs = [];

if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // Obtener las últimas 50 líneas
    $logs = array_slice($lines, -50);
    $logs = array_reverse($logs); // Mostrar más recientes primero
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Logs - Búsqueda de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-bottom: 5px;
            padding: 5px;
            border-left: 3px solid #ddd;
        }
        .log-debug { border-left-color: #17a2b8; background-color: #f8f9fa; }
        .log-info { border-left-color: #28a745; background-color: #f8fff9; }
        .log-error { border-left-color: #dc3545; background-color: #fff5f5; }
        .log-warning { border-left-color: #ffc107; background-color: #fffbf0; }
        .sql-query {
            background-color: #f1f3f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-bug me-2"></i>Debug Logs - Búsqueda de Clientes</h2>
                    <div>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Actualizar
                        </button>
                        <a href="adminventas.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Volver
                        </a>
                    </div>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay logs disponibles. Realiza una búsqueda de cliente para generar logs de depuración.
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Últimos 50 Logs (<?php echo count($logs); ?> entradas)
                            </h5>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $log_class = 'log-entry';
                                if (strpos($log, '[DEBUG]') !== false) $log_class .= ' log-debug';
                                elseif (strpos($log, '[INFO]') !== false) $log_class .= ' log-info';
                                elseif (strpos($log, '[ERROR]') !== false) $log_class .= ' log-error';
                                elseif (strpos($log, '[WARNING]') !== false) $log_class .= ' log-warning';
                                
                                // Resaltar consultas SQL
                                if (strpos($log, 'SQL Query') !== false) {
                                    $parts = explode('SQL Query', $log, 2);
                                    if (count($parts) == 2) {
                                        echo '<div class="' . $log_class . '">';
                                        echo htmlspecialchars($parts[0]) . '<strong>SQL Query</strong>';
                                        echo '<div class="sql-query mt-2">' . htmlspecialchars(trim($parts[1], ': ')) . '</div>';
                                        echo '</div>';
                                        continue;
                                    }
                                }
                                ?>
                                <div class="<?php echo $log_class; ?>">
                                    <?php echo htmlspecialchars($log); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-question-circle me-2"></i>
                                Cómo usar este depurador
                            </h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Ve a <a href="adminventas.php" target="_blank">Generar Pedido</a></li>
                                <li>Ingresa el DNI/Cédula del cliente que existe</li>
                                <li>Haz clic en "Buscar"</li>
                                <li>Regresa aquí y actualiza la página para ver los logs</li>
                                <li>Revisa las consultas SQL y los resultados</li>
                            </ol>
                            
                            <div class="alert alert-warning mt-3">
                                <strong>Tipos de logs:</strong><br>
                                <span class="badge bg-info">DEBUG</span> Consultas SQL y detalles técnicos<br>
                                <span class="badge bg-success">INFO</span> Información general del proceso<br>
                                <span class="badge bg-danger">ERROR</span> Errores en la búsqueda<br>
                                <span class="badge bg-warning">WARNING</span> Advertencias
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
