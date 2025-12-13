<?php

/**
 * ==============================================================
 * FILE: class/woocommerce_customer.php
 * ==============================================================
 * ✅ Clase dedicada al manejo de clientes WordPress/WooCommerce
 * ✅ Separación de responsabilidades limpia
 * ✅ Reutilizable en diferentes contextos
 * 
 * Funcionalidades:
 * - Buscar/crear usuarios WordPress
 * - Gestionar clientes WooCommerce
 * - Sincronización entre sistemas
 * - Generación de usernames únicos
 */

require_once('config.php');

class WooCommerceCustomer
{
    private mysqli $wp_connection;
    private array $table_cache = [];
    private array $columns_cache = [];

    public function __construct()
    {
        $this->wp_connection = DatabaseConfig::getWordPressConnection();
    }

    /* ==============================================================
     *  UTILIDADES DB
     * ============================================================ */

    private function tableExists(string $table): bool
    {
        if (isset($this->table_cache[$table])) return (bool)$this->table_cache[$table];
        $safe = mysqli_real_escape_string($this->wp_connection, $table);
        $sql = "SHOW TABLES LIKE '{$safe}'";
        $res = mysqli_query($this->wp_connection, $sql);
        $exists = ($res && mysqli_num_rows($res) > 0);
        $this->table_cache[$table] = $exists;
        return $exists;
    }

    private function getTableColumns(string $table): array
    {
        if (isset($this->columns_cache[$table])) return $this->columns_cache[$table];
        if (!$this->tableExists($table)) return [];

        $sql = "DESCRIBE {$table}";
        $res = mysqli_query($this->wp_connection, $sql);
        if (!$res) return [];

        $cols = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $cols[$row['Field']] = $row;
        }
        $this->columns_cache[$table] = $cols;
        return $cols;
    }

    /**
     * Inserta una fila filtrando SOLO columnas existentes.
     */
    private function insertRow(string $table, array $data, bool $returnInsertId = true): int
    {
        $cols = $this->getTableColumns($table);
        if (!$cols) throw new Exception("Tabla no existe o no se pudo describir: {$table}");

        // Filtrar datos: solo columnas existentes
        $filtered = [];
        foreach ($data as $k => $v) {
            if (isset($cols[$k])) $filtered[$k] = $v;
        }
        if (!$filtered) throw new Exception("No hay columnas válidas para insertar en {$table}");

        $fields = array_keys($filtered);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $fieldList = implode(',', array_map(fn($f) => "`{$f}`", $fields));

        $sql = "INSERT INTO {$table} ({$fieldList}) VALUES ({$placeholders})";
        $stmt = mysqli_prepare($this->wp_connection, $sql);
        if (!$stmt) throw new Exception("Prepare failed ({$table}): " . mysqli_error($this->wp_connection));

        // Tipos dinámicos
        $types = '';
        $values = [];
        foreach ($fields as $f) {
            $val = $filtered[$f];
            if (is_int($val)) {
                $types .= 'i';
            } elseif (is_float($val)) {
                $types .= 'd';
            } else {
                $types .= 's';
                if ($val === null) $val = null;
                else $val = (string)$val;
            }
            $values[] = $val;
        }

        // bind_param requiere referencias
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($values as $i => $v) {
            $bindArgs[] = &$values[$i];
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
            throw new Exception("bind_param failed ({$table}): " . mysqli_error($this->wp_connection));
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed ({$table}): " . mysqli_stmt_error($stmt));
        }

        $insertId = mysqli_insert_id($this->wp_connection);
        mysqli_stmt_close($stmt);

        return $returnInsertId ? (int)$insertId : 0;
    }

    /**
     * Actualiza una fila filtrando SOLO columnas existentes.
     */
    private function updateRowByWhere(string $table, array $data, string $whereClause): bool
    {
        $cols = $this->getTableColumns($table);
        if (!$cols) throw new Exception("Tabla no existe o no se pudo describir: {$table}");

        // Filtrar datos: solo columnas existentes
        $filtered = [];
        foreach ($data as $k => $v) {
            if (isset($cols[$k])) $filtered[$k] = $v;
        }
        if (!$filtered) throw new Exception("No hay columnas válidas para actualizar en {$table}");

        $sets = [];
        $values = [];
        $types = '';
        
        foreach ($filtered as $field => $value) {
            $sets[] = "`{$field}` = ?";
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
                if ($value === null) $value = null;
                else $value = (string)$value;
            }
            $values[] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$whereClause}";
        $stmt = mysqli_prepare($this->wp_connection, $sql);
        if (!$stmt) throw new Exception("Prepare failed ({$table}): " . mysqli_error($this->wp_connection));

        // bind_param requiere referencias
        if (!empty($values)) {
            $bindArgs = [];
            $bindArgs[] = $types;
            foreach ($values as $i => $v) {
                $bindArgs[] = &$values[$i];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
                throw new Exception("bind_param failed ({$table}): " . mysqli_error($this->wp_connection));
            }
        }

        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$result) {
            throw new Exception("Execute failed ({$table}): " . mysqli_error($this->wp_connection));
        }

        return true;
    }

    /* ==============================================================
     *  FUNCIONES PRINCIPALES DE CLIENTE
     * ============================================================ */

    /**
     * Busca un usuario WordPress por email
     * @param string $email Email del usuario
     * @return int|null ID del usuario o null si no existe
     */
    public function findWordPressUserByEmail(string $email): ?int
    {
        $email = trim($email);
        if (empty($email)) return null;

        $emailEscaped = mysqli_real_escape_string($this->wp_connection, $email);
        $query = "SELECT ID FROM miau_users WHERE user_email = '$emailEscaped' LIMIT 1";
        $result = mysqli_query($this->wp_connection, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return (int)$row['ID'];
        }

        return null;
    }

    /**
     * Genera un username único basado en nombre y apellido
     * Formato: nombre.apellido (con dígitos aleatorios si hay conflicto)
     */
    public function generateUniqueUsername(string $firstName, string $lastName): string
    {
        $firstNameClean = strtolower(preg_replace('/[^a-z0-9]/', '', $firstName));
        $lastNameClean = strtolower(preg_replace('/[^a-z0-9]/', '', $lastName));
        $username = $firstNameClean . '.' . $lastNameClean;
        
        // Verificar unicidad del username
        $originalUsername = $username;
        $usernameEscaped = mysqli_real_escape_string($this->wp_connection, $username);
        $checkQuery = "SELECT ID FROM miau_users WHERE user_login = '$usernameEscaped' LIMIT 1";
        $checkResult = mysqli_query($this->wp_connection, $checkQuery);
        
        // Si existe, agregar dígitos aleatorios
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            do {
                $randomDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $username = $originalUsername . '.' . $randomDigits;
                $usernameEscaped = mysqli_real_escape_string($this->wp_connection, $username);
                $checkQuery = "SELECT ID FROM miau_users WHERE user_login = '$usernameEscaped' LIMIT 1";
                $checkResult = mysqli_query($this->wp_connection, $checkQuery);
            } while ($checkResult && mysqli_num_rows($checkResult) > 0);
        }

        return $username;
    }

    /**
     * Crea un nuevo usuario WordPress con rol guest_customer
     * @param array $customerData Datos del cliente
     * @return int ID del usuario creado
     */
    public function createWordPressUser(array $customerData): int
    {
        $email = trim((string)($customerData['_billing_email'] ?? ''));
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        
        if (empty($email) || empty($firstName) || empty($lastName)) {
            throw new Exception('Email, nombre y apellido son requeridos para crear usuario');
        }

        // Generar username único
        $username = $this->generateUniqueUsername($firstName, $lastName);

        // Generar contraseña aleatoria
        $tempPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12);
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        $displayName = $firstName . ' ' . $lastName;
        $userNicename = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', $displayName));
        
        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');
        
        // Crear usuario en miau_users
        $userId = $this->insertRow('miau_users', [
            'user_login' => $username,
            'user_pass' => $hashedPassword,
            'user_nicename' => $userNicename,
            'user_email' => $email,
            'user_registered' => $now,
            'user_status' => 0,
            'display_name' => $displayName,
        ]);
        
        if ($userId > 0) {
            $this->createUserMetadata($userId, $customerData);
        }
        
        return $userId;
    }

    /**
     * Crea los metadatos del usuario (rol, datos personales, etc.)
     */
    private function createUserMetadata(int $userId, array $customerData): void
    {
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        $email = trim((string)($customerData['_billing_email'] ?? ''));

        // Metadatos básicos del usuario (rol guest_customer)
        $userMeta = [
            'miau_capabilities' => 'a:1:{s:14:"guest_customer";b:1;}',
            'miau_user_level' => '0',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'billing_first_name' => $firstName,
            'billing_last_name' => $lastName,
            'billing_email' => $email,
            'shipping_first_name' => $firstName,
            'shipping_last_name' => $lastName,
        ];
        
        // Agregar campos adicionales si existen
        if (!empty($customerData['_billing_phone'])) {
            $userMeta['billing_phone'] = (string)$customerData['_billing_phone'];
        }
        if (!empty($customerData['_shipping_address_1'])) {
            $userMeta['billing_address_1'] = (string)$customerData['_shipping_address_1'];
            $userMeta['shipping_address_1'] = (string)$customerData['_shipping_address_1'];
        }
        if (!empty($customerData['_shipping_city'])) {
            $userMeta['billing_city'] = (string)$customerData['_shipping_city'];
            $userMeta['shipping_city'] = (string)$customerData['_shipping_city'];
        }
        if (!empty($customerData['_shipping_state'])) {
            $userMeta['billing_state'] = (string)$customerData['_shipping_state'];
            $userMeta['shipping_state'] = (string)$customerData['_shipping_state'];
        }
        if (!empty($customerData['billing_id'])) {
            $userMeta['billing_dni'] = (string)$customerData['billing_id'];
        }
        if (!empty($customerData['_billing_neighborhood'])) {
            $userMeta['billing_barrio'] = (string)$customerData['_billing_neighborhood'];
        }
        
        foreach ($userMeta as $metaKey => $metaValue) {
            $this->insertRow('miau_usermeta', [
                'user_id' => $userId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
            ]);
        }
    }

    /**
     * Busca un usuario WordPress por email, o lo crea si no existe
     * @param array $customerData Datos del cliente
     * @return int ID del usuario (existente o creado)
     */
    public function findOrCreateWordPressUser(array $customerData): int
    {
        $email = trim((string)($customerData['_billing_email'] ?? ''));
        
        if (empty($email)) {
            return 0; // Sin email no podemos crear usuario
        }

        // 1. Buscar usuario existente por email
        $existingUserId = $this->findWordPressUserByEmail($email);
        if ($existingUserId !== null) {
            return $existingUserId;
        }

        // 2. Crear nuevo usuario WordPress
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        
        if (empty($firstName) || empty($lastName)) {
            return 0; // Necesitamos nombre completo para crear usuario
        }

        return $this->createWordPressUser($customerData);
    }

    /**
     * Crea o actualiza el cliente en miau_wc_customer_lookup
     * Esta tabla es crítica para que WooCommerce reconozca al cliente
     */
    public function upsertWooCommerceCustomer(int $userId, array $customerData): void
    {
        if (!$this->tableExists('miau_wc_customer_lookup')) {
            return; // Tabla no existe, skip
        }

        $email = trim((string)($customerData['_billing_email'] ?? ''));
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        $city = trim((string)($customerData['_shipping_city'] ?? ''));
        $state = trim((string)($customerData['_shipping_state'] ?? ''));
        
        if (empty($email)) {
            return; // Sin email no podemos crear cliente
        }

        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');

        // Verificar si el cliente ya existe
        $emailEscaped = mysqli_real_escape_string($this->wp_connection, $email);
        $checkQuery = "SELECT customer_id FROM miau_wc_customer_lookup WHERE email = '$emailEscaped' LIMIT 1";
        $checkResult = mysqli_query($this->wp_connection, $checkQuery);

        $customerLookupData = [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'date_last_active' => $now,
            'country' => 'CO',
            'city' => $city,
            'state' => $state,
        ];

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            // Cliente existe, actualizar
            $row = mysqli_fetch_assoc($checkResult);
            $existingCustomerId = (int)$row['customer_id'];
            
            $this->updateRowByWhere('miau_wc_customer_lookup', $customerLookupData, "customer_id = $existingCustomerId");
        } else {
            // Cliente no existe, crear nuevo
            $this->insertRow('miau_wc_customer_lookup', $customerLookupData);
        }
    }

    /**
     * Proceso completo: buscar/crear usuario WordPress + actualizar cliente WooCommerce
     * @param array $customerData Datos del cliente
     * @return array ['user_id' => int, 'created' => bool]
     */
    public function processCustomer(array $customerData): array
    {
        $email = trim((string)($customerData['_billing_email'] ?? ''));
        
        if (empty($email)) {
            throw new Exception('Email es requerido para procesar cliente');
        }

        // Verificar si usuario ya existe
        $existingUserId = $this->findWordPressUserByEmail($email);
        $wasCreated = false;

        if ($existingUserId === null) {
            // Crear nuevo usuario
            $userId = $this->findOrCreateWordPressUser($customerData);
            $wasCreated = true;
        } else {
            $userId = $existingUserId;
        }

        if ($userId === 0) {
            throw new Exception('No se pudo crear o encontrar usuario WordPress');
        }

        // Actualizar cliente WooCommerce
        $this->upsertWooCommerceCustomer($userId, $customerData);

        return [
            'user_id' => $userId,
            'created' => $wasCreated
        ];
    }

    /**
     * Obtener información completa del cliente por email
     */
    public function getCustomerByEmail(string $email): ?array
    {
        $email = trim($email);
        if (empty($email)) return null;

        $emailEscaped = mysqli_real_escape_string($this->wp_connection, $email);
        
        // Obtener datos del usuario WordPress
        $userQuery = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered 
                      FROM miau_users u 
                      WHERE u.user_email = '$emailEscaped' LIMIT 1";
        $userResult = mysqli_query($this->wp_connection, $userQuery);
        
        if (!$userResult || mysqli_num_rows($userResult) === 0) {
            return null;
        }

        $userData = mysqli_fetch_assoc($userResult);
        
        // Obtener datos del cliente WooCommerce
        $customerQuery = "SELECT customer_id, first_name, last_name, city, state, country, date_last_active 
                          FROM miau_wc_customer_lookup 
                          WHERE email = '$emailEscaped' LIMIT 1";
        $customerResult = mysqli_query($this->wp_connection, $customerQuery);
        
        $customerData = null;
        if ($customerResult && mysqli_num_rows($customerResult) > 0) {
            $customerData = mysqli_fetch_assoc($customerResult);
        }

        return [
            'wordpress_user' => $userData,
            'woocommerce_customer' => $customerData
        ];
    }
}
?>
