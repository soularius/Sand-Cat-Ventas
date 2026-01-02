<?php
/**
 * SandCat Invoice Logger Class
 * 
 * Maneja el sistema de logs personalizado para el plugin SandCat Invoice Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SandCat_Logger {
    
    private $log_dir;
    private $log_file;
    private $max_file_size;
    private $max_files;
    
    public function __construct() {
        $this->log_dir = SANDCAT_INVOICE_PLUGIN_PATH . 'logs/';
        $this->log_file = $this->log_dir . 'sandcat-invoice-' . date('Y-m-d') . '.log';
        $this->max_file_size = 5 * 1024 * 1024; // 5MB
        $this->max_files = 10; // Mantener máximo 10 archivos de log
        
        $this->ensure_log_directory();
    }
    
    /**
     * Asegurar que el directorio de logs existe
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        
        // Crear archivo .htaccess si no existe
        $htaccess_file = $this->log_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all");
        }
    }
    
    /**
     * Escribir mensaje al log
     */
    public function log($level, $message, $context = array()) {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        // Formatear el mensaje
        $formatted_message = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );
        
        // Agregar contexto si existe
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . json_encode($context);
        }
        
        $formatted_message .= PHP_EOL;
        
        // Verificar rotación de logs
        $this->rotate_logs_if_needed();
        
        // Escribir al archivo
        file_put_contents($this->log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log de error
     */
    public function error($message, $context = array()) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Log de warning
     */
    public function warning($message, $context = array()) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log de info
     */
    public function info($message, $context = array()) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = array()) {
        // Solo registrar debug si WP_DEBUG está activo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Rotar logs si es necesario
     */
    private function rotate_logs_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        // Verificar tamaño del archivo
        if (filesize($this->log_file) > $this->max_file_size) {
            $this->rotate_logs();
        }
        
        // Limpiar archivos antiguos
        $this->cleanup_old_logs();
    }
    
    /**
     * Rotar archivos de log
     */
    private function rotate_logs() {
        $timestamp = date('Y-m-d_H-i-s');
        $rotated_file = $this->log_dir . 'sandcat-invoice-' . $timestamp . '.log';
        
        if (file_exists($this->log_file)) {
            rename($this->log_file, $rotated_file);
        }
    }
    
    /**
     * Limpiar archivos de log antiguos
     */
    private function cleanup_old_logs() {
        $files = glob($this->log_dir . 'sandcat-invoice-*.log');
        
        if (count($files) > $this->max_files) {
            // Ordenar por fecha de modificación
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Eliminar archivos más antiguos
            $files_to_delete = array_slice($files, 0, count($files) - $this->max_files);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtener logs recientes
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $file_lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($file_lines === false) {
            return array();
        }
        
        return array_slice($file_lines, -$lines);
    }
    
    /**
     * Limpiar todos los logs
     */
    public function clear_logs() {
        $files = glob($this->log_dir . 'sandcat-invoice-*.log');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Obtener tamaño total de logs
     */
    public function get_logs_size() {
        $files = glob($this->log_dir . 'sandcat-invoice-*.log');
        $total_size = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
        
        return $total_size;
    }
}
