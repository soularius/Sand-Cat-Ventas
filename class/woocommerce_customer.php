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

    /* ==============================================================
     *  FUNCIONES REUTILIZABLES PARA PROCESAMIENTO DE FORMULARIOS
     * ============================================================ */

    /**
     * Captura y valida datos del formulario de pros_venta.php
     * Centraliza la lógica de captura de datos POST
     */
    public function captureFormData(array $formFields): array
    {
        return Utils::capturePostData($formFields);
    }

    /**
     * Carga datos de un pedido existente desde la base de datos
     * Útil para editar pedidos o continuar desde pasos anteriores
     */
    public function loadExistingOrderData(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $query = "SELECT pm.meta_key, pm.meta_value 
                  FROM miau_postmeta pm 
                  WHERE pm.post_id = ? AND pm.meta_key IN (
                      '_shipping_first_name', '_shipping_last_name', 'billing_id',
                      '_billing_email', '_billing_phone', '_shipping_address_1',
                      '_shipping_address_2', '_billing_neighborhood', '_shipping_city',
                      '_shipping_state', 'post_excerpt', '_order_shipping',
                      '_cart_discount', '_payment_method_title'
                  )";
        
        $stmt = mysqli_prepare($this->wp_connection, $query);
        if (!$stmt) {
            Utils::logError("Error preparando consulta de pedido existente: " . mysqli_error($this->wp_connection), 'ERROR', 'WooCommerceCustomer');
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $existingData = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $existingData[$row['meta_key']] = $row['meta_value'];
        }
        
        mysqli_stmt_close($stmt);
        
        if (!empty($existingData)) {
            Utils::logError("Datos cargados para order_id: $orderId", 'INFO', 'WooCommerceCustomer');
        }
        
        return $existingData;
    }

    /**
     * Convierte datos de metadatos a formato de formulario
     * Mapea los meta_keys a los nombres de campos del formulario
     */
    public function convertMetadataToFormData(array $metadata): array
    {
        return [
            'nombre1' => $metadata['_shipping_first_name'] ?? '',
            'nombre2' => $metadata['_shipping_last_name'] ?? '',
            'billing_id' => $metadata['billing_id'] ?? '',
            '_billing_email' => $metadata['_billing_email'] ?? '',
            '_billing_phone' => $metadata['_billing_phone'] ?? '',
            '_shipping_address_1' => $metadata['_shipping_address_1'] ?? '',
            '_shipping_address_2' => $metadata['_shipping_address_2'] ?? '',
            '_billing_neighborhood' => $metadata['_billing_neighborhood'] ?? '',
            '_shipping_city' => $metadata['_shipping_city'] ?? '',
            '_shipping_state' => $metadata['_shipping_state'] ?? '',
            'post_expcerpt' => $metadata['post_excerpt'] ?? '',
            '_order_shipping' => $metadata['_order_shipping'] ?? '',
            '_cart_discount' => $metadata['_cart_discount'] ?? '',
            '_payment_method_title' => $metadata['_payment_method_title'] ?? ''
        ];
    }

    /**
     * Procesa un pedido existente y combina con nuevos datos del formulario
     * Útil para flujos de edición de pedidos
     */
    public function processExistingOrder(int $orderId, array $newFormData): array
    {
        // Si no hay datos nuevos del formulario, cargar los existentes
        if ($orderId > 0 && empty($newFormData['nombre1'])) {
            $existingMetadata = $this->loadExistingOrderData($orderId);
            if (!empty($existingMetadata)) {
                $formData = $this->convertMetadataToFormData($existingMetadata);
                $formData['_order_id'] = $orderId; // Mantener referencia al order_id
                return $formData;
            }
        }
        
        return $newFormData;
    }

    /**
     * Asigna variables de compatibilidad desde formData
     * Centraliza la lógica de asignación de variables legacy
     */
    public function assignCompatibilityVariables(array $formData): array
    {
        return [
            '_shipping_first_name' => $formData['nombre1'] ?? '',
            '_shipping_last_name' => $formData['nombre2'] ?? '',
            'billing_id' => $formData['billing_id'] ?? '',
            '_billing_email' => $formData['_billing_email'] ?? '',
            '_billing_phone' => $formData['_billing_phone'] ?? '',
            '_shipping_address_1' => $formData['_shipping_address_1'] ?? '',
            '_shipping_address_2' => $formData['_shipping_address_2'] ?? '',
            '_billing_neighborhood' => $formData['_billing_neighborhood'] ?? '',
            '_shipping_city' => $formData['_shipping_city'] ?? '',
            '_shipping_state' => $formData['_shipping_state'] ?? '',
            'post_expcerpt' => $formData['post_expcerpt'] ?? '',
            '_order_shipping' => $formData['_order_shipping'] ?? '',
            '_cart_discount' => $formData['_cart_discount'] ?? '',
            '_payment_method_title' => $formData['_payment_method_title'] ?? '',
            'metodo' => $formData['_payment_method_title'] ?? ''
        ];
    }

    /**
     * Procesa completamente un formulario de pros_venta.php
     * Función principal que combina toda la lógica de procesamiento
     */
    public function processCompleteForm(array $formFields, int $existingOrderId = 0): array
    {
        // 1. Capturar datos del formulario
        $formData = $this->captureFormData($formFields);
        
        // 2. Procesar pedido existente si aplica
        $formData = $this->processExistingOrder($existingOrderId, $formData);
        
        // 3. Asignar variables de compatibilidad
        $compatibilityVars = $this->assignCompatibilityVariables($formData);
        
        // 4. Procesar códigos de ubicación
        $locationData = $this->processLocationCodes(
            $formData['_shipping_state'] ?? '', 
            $formData['_shipping_city'] ?? ''
        );
        
        return [
            'form_data' => $formData,
            'compatibility_vars' => $compatibilityVars,
            'location_data' => $locationData,
            'existing_order_id' => $existingOrderId
        ];
    }

    /**
     * Función optimizada para pros_venta.php - Solo gestiona clientes
     * No crea pedidos, solo procesa y crea/actualiza clientes
     */
    public function processCustomerOnly(array $formFields, int $existingOrderId = 0): array
    {
        // 1. Procesar formulario completo
        $processedForm = $this->processCompleteForm($formFields, $existingOrderId);
        
        // 2. Solo procesar cliente si hay datos del formulario
        if (!empty($processedForm['form_data']['nombre1'])) {
            try {
                $customerResult = $this->processCustomer($processedForm['form_data']);
                
                return [
                    'success' => true,
                    'customer_id' => $customerResult['user_id'],
                    'customer_created' => $customerResult['created'],
                    'customer_data' => $customerResult,
                    'form_data' => $processedForm['form_data'],
                    'compatibility_vars' => $processedForm['compatibility_vars'],
                    'location_data' => $processedForm['location_data'],
                    'existing_order_id' => $processedForm['existing_order_id']
                ];
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'form_data' => $processedForm['form_data'],
                    'compatibility_vars' => $processedForm['compatibility_vars'],
                    'location_data' => $processedForm['location_data']
                ];
            }
        }
        
        // Si no hay datos de formulario, solo retornar datos procesados
        return [
            'success' => true,
            'customer_processed' => false,
            'form_data' => $processedForm['form_data'],
            'compatibility_vars' => $processedForm['compatibility_vars'],
            'location_data' => $processedForm['location_data'],
            'existing_order_id' => $processedForm['existing_order_id']
        ];
    }

    /* ==============================================================
     *  FUNCIONES ESPECÍFICAS PARA PROS_VENTA.PHP
     * ============================================================ */

    /**
     * Función ultra-optimizada para pros_venta.php
     * Solo procesa formulario y gestiona cliente - NO crea pedidos
     */
    public function handleProsVentaForm(): array
    {
        // Campos específicos de pros_venta.php
        $formFields = [
            'nombre1', 'nombre2', 'billing_id', '_billing_email', '_billing_phone',
            '_shipping_address_1', '_shipping_address_2', '_billing_neighborhood',
            '_shipping_city', '_shipping_state', 'post_expcerpt', '_order_shipping',
            '_cart_discount', '_payment_method_title', '_order_id'
        ];
        
        // Procesar solo cliente
        return $this->processCustomerOnly($formFields, (int)($_POST['_order_id'] ?? 0));
    }

    /**
     * Procesa códigos de ubicación para diferentes tablas de WooCommerce
     * Maneja la lógica específica de departamentos y ciudades colombianas
     * Mejorado para manejar códigos que ya tienen prefijo CO-
     */
    public function processLocationCodes(string $stateCode, string $cityName): array
    {
        // Procesar el código del departamento - extraer solo la parte después del guión si existe
        $originalState = $stateCode;
        if (strpos($stateCode, '-') !== false) {
            $stateCode = substr($stateCode, strpos($stateCode, '-') + 1);
        }
        
        return [
            'original_state' => $originalState,
            'original_city' => $cityName,
            'state_for_usermeta' => $stateCode,  // Solo clave (ej: "SAN")
            'city_for_usermeta' => $cityName,    // Valor completo
            'state_for_addresses' => 'CO-' . $stateCode,  // Con prefijo CO- (ej: "CO-SAN")
            'city_for_addresses' => $cityName,   // Valor completo
            'state_for_postmeta' => $stateCode   // Solo clave
        ];
    }

    /**
     * Crea un pedido básico en miau_posts (compatible con pros_venta.php)
     * Retorna el ID del pedido creado
     */
    public function createBasicOrder(array $orderData): int
    {
        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');
        
        $postExcerpt = trim((string)($orderData['post_excerpt'] ?? ''));
        $customerId = (int)($orderData['customer_id'] ?? 1); // Default a admin si no se especifica
        
        $orderRow = [
            'post_author' => $customerId,
            'post_status' => 'wc-pending',  // Corregir status
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_type' => 'shop_order',
            'post_date' => $now,
            'post_date_gmt' => $now,
            'post_modified' => $now,
            'post_modified_gmt' => $now,
            'post_excerpt' => $postExcerpt
        ];
        
        return $this->insertRow('miau_posts', $orderRow);
    }

    /**
     * Inserta metadatos del pedido en miau_postmeta (compatible con pros_venta.php)
     */
    public function insertOrderMetadata(int $orderId, array $customerData, array $locationData): void
    {
        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');
        
        // Extraer datos del cliente
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        $email = trim((string)($customerData['_billing_email'] ?? ''));
        $phone = trim((string)($customerData['_billing_phone'] ?? ''));
        $billingId = trim((string)($customerData['billing_id'] ?? ''));
        $address1 = trim((string)($customerData['_shipping_address_1'] ?? ''));
        $address2 = trim((string)($customerData['_shipping_address_2'] ?? ''));
        $neighborhood = trim((string)($customerData['_billing_neighborhood'] ?? ''));
        $city = trim((string)($customerData['_shipping_city'] ?? ''));
        $orderShipping = trim((string)($customerData['_order_shipping'] ?? '0'));
        $paymentMethod = trim((string)($customerData['_payment_method_title'] ?? 'Manual'));
        
        // Metadatos del pedido
        $metaData = [
            '_shipping_first_name' => $firstName,
            '_shipping_last_name' => $lastName,
            '_billing_first_name' => $firstName,
            '_billing_last_name' => $lastName,
            '_order_shipping' => $orderShipping,
            '_paid_date' => $now,
            '_recorded_sales' => 'yes',
            'billing_id' => $billingId,
            '_billing_id' => $billingId,
            '_billing_dni' => $billingId,
            '_billing_email' => $email,
            'Card number' => $email, // Campo legacy
            '_billing_phone' => $phone,
            '_shipping_address_1' => $address1,
            '_billing_address_1' => $address1,
            '_cart_discount' => '0',
            '_billing_neighborhood' => $neighborhood,
            'billing_neighborhood' => $neighborhood,
            '_shipping_city' => $city,
            '_billing_city' => $city,
            '_shipping_state' => $locationData['state_for_postmeta'],
            '_billing_state' => $locationData['state_for_postmeta'],
            '_payment_method_title' => $paymentMethod,
            '_billing_country' => 'CO',
            '_shipping_country' => 'CO',
            '_order_stock_reduced' => 'yes',
            '_billing_address_2' => $address2,
            '_order_total' => '0', // Se actualizará después
            '_shipping_address_2' => $address2,
            // Campos críticos para vinculación
            '_customer_user' => (string)($customerData['customer_id'] ?? '0'),
            '_order_key' => 'wc_order_' . bin2hex(random_bytes(8)),
            '_prices_include_tax' => 'no',
            '_order_version' => '8.0.0'
        ];
        
        // Insertar cada metadato
        foreach ($metaData as $metaKey => $metaValue) {
            $this->insertRow('miau_postmeta', [
                'post_id' => $orderId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue
            ]);
        }
    }

    /**
     * Inserta direcciones en miau_wc_order_addresses
     */
    public function insertOrderAddresses(int $orderId, array $customerData, array $locationData): void
    {
        if (!$this->tableExists('miau_wc_order_addresses')) {
            return; // Tabla no existe
        }
        
        $firstName = trim((string)($customerData['nombre1'] ?? $customerData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($customerData['nombre2'] ?? $customerData['_shipping_last_name'] ?? ''));
        $email = trim((string)($customerData['_billing_email'] ?? ''));
        $phone = trim((string)($customerData['_billing_phone'] ?? ''));
        $address1 = trim((string)($customerData['_shipping_address_1'] ?? ''));
        $address2 = trim((string)($customerData['_shipping_address_2'] ?? ''));
        
        // Dirección de facturación
        $this->insertRow('miau_wc_order_addresses', [
            'order_id' => $orderId,
            'address_type' => 'billing',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => null,
            'address_1' => $address1,
            'address_2' => $address2,
            'city' => $locationData['city_for_addresses'],
            'state' => $locationData['state_for_addresses'],
            'postcode' => null,
            'country' => 'CO',
            'email' => $email,
            'phone' => $phone
        ]);
        
        // Dirección de envío
        $this->insertRow('miau_wc_order_addresses', [
            'order_id' => $orderId,
            'address_type' => 'shipping',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => null,
            'address_1' => $address1,
            'address_2' => $address2,
            'city' => $locationData['city_for_addresses'],
            'state' => $locationData['state_for_addresses'],
            'postcode' => null,
            'country' => 'CO',
            'email' => null,
            'phone' => null
        ]);
    }

    /**
     * Proceso completo para crear pedido desde pros_venta.php
     * Combina creación de usuario, pedido y metadatos
     */
    public function createOrderFromProsVenta(array $formData): array
    {
        try {
            // 1. Procesar cliente
            $customerResult = $this->processCustomer($formData);
            $customerId = $customerResult['user_id'];
            
            // 2. Procesar ubicación
            $stateCode = trim((string)($formData['_shipping_state'] ?? ''));
            $cityName = trim((string)($formData['_shipping_city'] ?? ''));
            $locationData = $this->processLocationCodes($stateCode, $cityName);
            
            // 3. Crear pedido básico
            $orderData = [
                'post_excerpt' => trim((string)($formData['post_expcerpt'] ?? '')),
                'customer_id' => $customerId
            ];
            $orderId = $this->createBasicOrder($orderData);
            
            if ($orderId === 0) {
                throw new Exception('No se pudo crear el pedido en miau_posts');
            }
            
            // 4. Agregar customer_id a formData para metadatos
            $formData['customer_id'] = $customerId;
            
            // 5. Insertar metadatos
            $this->insertOrderMetadata($orderId, $formData, $locationData);
            
            // 6. Insertar direcciones
            $this->insertOrderAddresses($orderId, $formData, $locationData);
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'customer_created' => $customerResult['created'],
                'location_data' => $locationData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Función específica para pros_venta.php cuando solo necesita crear pedidos
     * Separada de la gestión de clientes para mayor claridad
     */
    public function createOrderOnly(array $formData, int $customerId): array
    {
        try {
            // 1. Procesar códigos de ubicación
            $locationData = $this->processLocationCodes(
                $formData['_shipping_state'] ?? '', 
                $formData['_shipping_city'] ?? ''
            );
            
            // 2. Crear pedido básico
            $orderId = $this->createBasicOrder([
                'post_excerpt' => $formData['post_expcerpt'] ?? '',
                'customer_id' => $customerId
            ]);
            
            // 3. Insertar metadatos del pedido
            $formData['customer_id'] = $customerId;
            $this->insertOrderMetadata($orderId, $formData, $locationData);
            
            // 4. Insertar direcciones del pedido
            $this->insertOrderAddresses($orderId, $formData, $locationData);
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'location_data' => $locationData
            ];
            
        } catch (Exception $e) {
            Utils::logError("Error en createOrderOnly: " . $e->getMessage(), 'ERROR', 'WooCommerceCustomer');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Función específica para solo gestionar clientes sin crear pedidos
     * Optimizada para el flujo de pros_venta.php
     */
    public function manageCustomerOnly(array $formData): array
    {
        try {
            // 1. Procesar cliente
            $customerResult = $this->processCustomer($formData);
            $customerId = $customerResult['user_id'];
            
            // 2. Procesar ubicación
            $stateCode = trim((string)($formData['_shipping_state'] ?? ''));
            $cityName = trim((string)($formData['_shipping_city'] ?? ''));
            $locationData = $this->processLocationCodes($stateCode, $cityName);
            
            return [
                'success' => true,
                'customer_id' => $customerId,
                'customer_created' => $customerResult['created'],
                'location_data' => $locationData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * FUNCIÓN PRINCIPAL PARA PROS_VENTA.PHP
     * Procesa formulario completo de pros_venta.php SOLO para clientes
     * Maneja todos los campos requeridos: DNI, barrio, ciudad, departamento, país
     */
    public function processCustomerFromProsVenta(): array
    {
        try {
            // 1. Campos específicos de pros_venta.php
            $formFields = [
                'nombre1', 'nombre2', 'billing_id', '_billing_email', '_billing_phone',
                '_shipping_address_1', '_shipping_address_2', '_billing_neighborhood',
                '_shipping_city', '_shipping_state', 'post_expcerpt', '_order_shipping',
                '_cart_discount', '_payment_method_title', '_order_id'
            ];

            // 2. Capturar datos del formulario
            $formData = Utils::capturePostData($formFields);
            
            // 3. Procesar pedido existente si viene del step wizard
            $existingOrderId = (int)($formData['_order_id'] ?? 0);
            if ($existingOrderId > 0 && !$formData['nombre1']) {
                $formData = $this->processExistingOrder($existingOrderId, $formData);
            }

            // 4. Procesar códigos de ubicación para diferentes tablas
            $locationData = $this->processLocationCodes(
                $formData['_shipping_state'] ?? '', 
                $formData['_shipping_city'] ?? ''
            );

            // 5. Asignar variables de compatibilidad
            $compatibilityVars = $this->assignCompatibilityVariables($formData);

            // 6. Solo procesar cliente si hay datos del formulario
            $customerResult = null;
            if (!empty($formData['nombre1'])) {
                $customerResult = $this->processCustomer($formData);
                
                // 7. Insertar en todas las tablas de cliente con formatos específicos
                $this->insertAllCustomerTables($customerResult['user_id'], $formData, $locationData);
            }

            return [
                'success' => true,
                'customer_processed' => $customerResult !== null,
                'customer_id' => $customerResult['user_id'] ?? 0,
                'customer_created' => $customerResult['created'] ?? false,
                'form_data' => $formData,
                'compatibility_vars' => $compatibilityVars,
                'location_data' => $locationData,
                'existing_order_id' => $existingOrderId
            ];

        } catch (Exception $e) {
            Utils::logError("Error en processCustomerFromProsVenta: " . $e->getMessage(), 'ERROR', 'WooCommerceCustomer');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'customer_processed' => false
            ];
        }
    }

    /**
     * Inserta/actualiza cliente en TODAS las tablas requeridas con formatos específicos
     * Maneja: miau_usermeta, miau_wc_customer_lookup, miau_wc_order_addresses (si aplica)
     */
    private function insertAllCustomerTables(int $userId, array $formData, array $locationData): void
    {
        // 1. Actualizar miau_usermeta con formato específico
        $this->updateUserMetaWithLocation($userId, $formData, $locationData);
        
        // 2. Actualizar miau_wc_customer_lookup con formato específico
        $this->updateCustomerLookupWithLocation($userId, $formData, $locationData);
        
        Utils::logError("Cliente actualizado en todas las tablas - User ID: $userId", 'INFO', 'WooCommerceCustomer');
    }

    /**
     * Actualiza miau_usermeta con campos específicos y formatos correctos
     * Formato: state = solo clave (SAN), city = valor completo
     */
    private function updateUserMetaWithLocation(int $userId, array $formData, array $locationData): void
    {
        $firstName = trim((string)($formData['nombre1'] ?? $formData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($formData['nombre2'] ?? $formData['_shipping_last_name'] ?? ''));
        $email = trim((string)($formData['_billing_email'] ?? ''));
        $phone = trim((string)($formData['_billing_phone'] ?? ''));
        $address1 = trim((string)($formData['_shipping_address_1'] ?? ''));
        $address2 = trim((string)($formData['_shipping_address_2'] ?? ''));
        $billingId = trim((string)($formData['billing_id'] ?? ''));
        $neighborhood = trim((string)($formData['_billing_neighborhood'] ?? ''));

        // Metadatos actualizados con formatos específicos
        $userMeta = [
            // Datos básicos
            'first_name' => $firstName,
            'last_name' => $lastName,
            
            // Billing data con formato específico para usermeta
            'billing_first_name' => $firstName,
            'billing_last_name' => $lastName,
            'billing_email' => $email,
            'billing_phone' => $phone,
            'billing_address_1' => $address1,
            'billing_address_2' => $address2,
            'billing_city' => $locationData['city_for_usermeta'], // Valor completo
            'billing_state' => $locationData['state_for_usermeta'], // Solo clave (SAN)
            'billing_country' => 'CO',
            'billing_dni' => $billingId,
            'billing_barrio' => $neighborhood,
            
            // Shipping data con formato específico para usermeta
            'shipping_first_name' => $firstName,
            'shipping_last_name' => $lastName,
            'shipping_address_1' => $address1,
            'shipping_address_2' => $address2,
            'shipping_city' => $locationData['city_for_usermeta'], // Valor completo
            'shipping_state' => $locationData['state_for_usermeta'], // Solo clave (SAN)
            'shipping_country' => 'CO',
        ];

        foreach ($userMeta as $metaKey => $metaValue) {
            if (!empty($metaValue)) {
                // Verificar si el metadato ya existe
                $checkQuery = "SELECT umeta_id FROM miau_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1";
                $stmt = mysqli_prepare($this->wp_connection, $checkQuery);
                mysqli_stmt_bind_param($stmt, 'is', $userId, $metaKey);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    // Actualizar metadato existente
                    $this->updateRowByWhere('miau_usermeta', 
                        ['meta_value' => $metaValue], 
                        "user_id = $userId AND meta_key = '$metaKey'"
                    );
                } else {
                    // Insertar nuevo metadato
                    $this->insertRow('miau_usermeta', [
                        'user_id' => $userId,
                        'meta_key' => $metaKey,
                        'meta_value' => $metaValue,
                    ]);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    /**
     * Actualiza miau_wc_customer_lookup con formato específico
     * Formato: state = solo clave (SAN), city = valor completo
     */
    private function updateCustomerLookupWithLocation(int $userId, array $formData, array $locationData): void
    {
        if (!$this->tableExists('miau_wc_customer_lookup')) {
            return;
        }

        $email = trim((string)($formData['_billing_email'] ?? ''));
        $firstName = trim((string)($formData['nombre1'] ?? $formData['_shipping_first_name'] ?? ''));
        $lastName = trim((string)($formData['nombre2'] ?? $formData['_shipping_last_name'] ?? ''));

        date_default_timezone_set('America/Bogota');
        $now = date('Y-m-d H:i:s');

        $customerData = [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'date_last_active' => $now,
            'country' => 'CO',
            'city' => $locationData['city_for_usermeta'], // Valor completo
            'state' => $locationData['state_for_usermeta'], // Solo clave (SAN)
        ];

        // Verificar si el cliente ya existe
        $emailEscaped = mysqli_real_escape_string($this->wp_connection, $email);
        $checkQuery = "SELECT customer_id FROM miau_wc_customer_lookup WHERE email = '$emailEscaped' LIMIT 1";
        $checkResult = mysqli_query($this->wp_connection, $checkQuery);

        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            // Cliente existe, actualizar
            $row = mysqli_fetch_assoc($checkResult);
            $existingCustomerId = (int)$row['customer_id'];
            
            $this->updateRowByWhere('miau_wc_customer_lookup', $customerData, "customer_id = $existingCustomerId");
            Utils::logError("Cliente actualizado en customer_lookup - Customer ID: $existingCustomerId", 'INFO', 'WooCommerceCustomer');
        } else {
            // Cliente no existe, crear nuevo
            $newCustomerId = $this->insertRow('miau_wc_customer_lookup', $customerData);
            Utils::logError("Cliente creado en customer_lookup - Customer ID: $newCustomerId", 'INFO', 'WooCommerceCustomer');
        }
    }
}
?>
