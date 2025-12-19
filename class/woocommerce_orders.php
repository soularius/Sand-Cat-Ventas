<?php

/**
 * ==============================================================
 * FILE: class/woocommerce_orders.php
 * ==============================================================
 * ✅ 100% PHP puro (sin wp-load.php, sin wc_create_order)
 * ✅ Crea el pedido escribiendo DIRECTO en la DB
 * ✅ Mantiene compatibilidad: escribe en Legacy (posts/postmeta) + HPOS (wc_orders)
 * ✅ Usa transacción: si algo falla, revierte TODO
 *
 * Importante:
 * - WooCommerce usa MUCHAS tablas derivadas (stats/lookups). Aquí las llenamos
 *   de forma "best effort" si existen, sin romper la creación del pedido.
 * - Si tu instalación tiene HPOS activo, NO basta con miau_posts/miau_postmeta.
 *   Debes crear también en miau_wc_orders para que aparezca en listados HPOS.
 */

require_once('config.php');
require_once('woocommerce_customer.php');

class WooCommerceOrders
{

    private mysqli $wp_connection;
    private array $table_cache = [];
    private array $columns_cache = [];
    private WooCommerceCustomer $customerManager;

    public function __construct()
    {
        $this->wp_connection = DatabaseConfig::getWordPressConnection();
        $this->customerManager = new WooCommerceCustomer();

        if (isset($_GET['debug_sql']) || isset($_GET['debug_orders'])) {
            echo $this->wp_connection ? "<pre>CONEXIÓN A DB: OK</pre>" : "<pre>ERROR: No se pudo conectar a la base de datos</pre>";
        }
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
            $cols[$row['Field']] = $row; // Type, Null, Key, Default, Extra
        }
        $this->columns_cache[$table] = $cols;
        return $cols;
    }

    /**
     * Inserta una fila filtrando SOLO columnas existentes.
     * - $returnInsertId: true si quieres el insert_id del autoincrement.
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

    private function execRaw(string $sql): void
    {
        $res = mysqli_query($this->wp_connection, $sql);
        if (!$res) {
            throw new Exception("SQL error: " . mysqli_error($this->wp_connection) . "\nSQL: {$sql}");
        }
    }

    private function serializeEmptyTaxes(): string
    {
        // WooCommerce guarda esto como PHP-serialized array
        return 'a:2:{s:5:"total";a:0:{}s:8:"subtotal";a:0:{}}';
    }

    private function parseMoney($value): int
    {
        if ($value === null || $value === '') return 0;
        $clean = preg_replace('/[^0-9]/', '', (string)$value);
        return $clean === '' ? 0 : (int)$clean;
    }

    private function detectHPOSStatusFormat(): string
    {
        // Detecta si miau_wc_orders.status usa 'wc-processing' o 'processing'
        if (!$this->tableExists('miau_wc_orders')) return 'wc-processing';
        $q = "SELECT status FROM miau_wc_orders WHERE type='shop_order' ORDER BY id DESC LIMIT 1";
        $r = mysqli_query($this->wp_connection, $q);
        if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['status'])) {
            $s = (string)$row['status'];
            return (str_starts_with($s, 'wc-')) ? 'wc-processing' : 'processing';
        }
        return 'wc-processing';
    }

    private function normalizeStatus(string $desired, string $reference): string
    {
        // reference: 'wc-processing' o 'processing'
        $desired = trim($desired);
        if ($desired === '') $desired = $reference;

        $refHasPrefix = str_starts_with($reference, 'wc-');
        $desHasPrefix = str_starts_with($desired, 'wc-');

        if ($refHasPrefix && !$desHasPrefix) return 'wc-' . $desired;
        if (!$refHasPrefix && $desHasPrefix) return str_replace('wc-', '', $desired);
        return $desired;
    }


    /* ==============================================================
     *  ✅ CREAR ORDEN SOLO DB
     * ============================================================ */

    /**
     * Crea una orden WooCommerce escribiendo DIRECTO en la DB.
     *
     * Espera un payload tipo:
     * $orderData = [
     *   'products' => [ ['id'=>123,'title'=>'X','quantity'=>1,'price'=>10000,'regular_price'=>12000,'sale_price'=>10000], ... ],
     *   'customer_data' => [ 'nombre1'=>'...','nombre2'=>'...','_billing_email'=>'...','_billing_phone'=>'...', '_shipping_address_1'=>'...' ... ],
     *   'form_data' => [ '_order_shipping'=>5000,'_cart_discount'=>0,'_payment_method_title'=>'Pago Manual','post_expcerpt'=>'...' ]
     * ];
     */
    public function createOrderFromSalesData(array $orderData, string $mode = 'prod'): array
    {
        $debug = [
            'mode' => $mode,
            'steps' => [],
            'warnings' => [],
        ];

        // Validaciones mínimas
        if (empty($orderData['products']) || !is_array($orderData['products'])) {
            return ['success' => false, 'error' => 'No hay productos para crear la orden', 'debug' => $debug];
        }

        $customer = $orderData['customer_data'] ?? [];
        $form = $orderData['form_data'] ?? [];

        // 🔥 CRÍTICO: Procesar cliente usando clase dedicada
        try {
            $customerResult = $this->customerManager->processCustomer($customer);
            $customerId = $customerResult['user_id'];
            $wasCreated = $customerResult['created'];
            
            $debug['steps'][] = [
                'customer_processed' => true,
                'user_id' => $customerId,
                'user_created' => $wasCreated
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error procesando cliente: ' . $e->getMessage(), 'debug' => $debug];
        }

        // Normalizar valores
        $firstName = (string)($customer['nombre1'] ?? $customer['_shipping_first_name'] ?? '');
        $lastName  = (string)($customer['nombre2'] ?? $customer['_shipping_last_name'] ?? '');
        $email     = (string)($customer['_billing_email'] ?? '');
        $phone     = (string)($customer['_billing_phone'] ?? '');

        $addr1     = (string)($customer['_shipping_address_1'] ?? '');
        $addr2     = (string)($customer['_shipping_address_2'] ?? '');
        $city      = (string)($customer['_shipping_city'] ?? '');
        $state     = (string)($customer['_shipping_state'] ?? '');
        $dni       = (string)($customer['dni'] ?? $customer['billing_id'] ?? '');
        $barrio    = (string)($customer['_billing_neighborhood'] ?? '');

        // Soportar nombres de keys distintos (tu test usa _order_shipping_value)
        $shippingCost = $this->parseMoney($form['_order_shipping'] ?? $form['_order_shipping_value'] ?? $orderData['_order_shipping'] ?? 0);
        $cartDiscount = $this->parseMoney($form['_cart_discount'] ?? $orderData['_cart_discount'] ?? 0);
        $paymentTitle = (string)($form['_payment_method_title'] ?? $orderData['_payment_method_title'] ?? '');
        $paymentMethod = (string)($form['_payment_method'] ?? ''); // opcional (slug del gateway)
        $orderNotes = (string)($form['post_expcerpt'] ?? '');

        // Tiempos
        date_default_timezone_set('America/Bogota');
        $nowLocal = date('Y-m-d H:i:s');
        $nowGmt   = gmdate('Y-m-d H:i:s');

        // Totales por items
        $itemsSubtotal = 0; // pre-descuento de producto (regular)
        $itemsTotal    = 0; // después de descuento de producto (sale)

        foreach ($orderData['products'] as $p) {
            $qty = (int)($p['quantity'] ?? 0);
            if ($qty < 1) continue;

            $regular = (int)($p['regular_price'] ?? $p['price'] ?? 0);
            $price   = (int)(($p['sale_price'] !== null && $p['sale_price'] !== '') ? $p['sale_price'] : ($p['price'] ?? $regular));

            $itemsSubtotal += ($regular * $qty);
            $itemsTotal    += ($price * $qty);
        }

        $finalTotal = max(0, ($itemsTotal + $shippingCost - $cartDiscount));

        // Si HPOS existe, usaremos su formato de status
        $hposStatusReference = $this->detectHPOSStatusFormat();
        $statusPosts = 'wc-completed';
        $statusHPOS  = $this->normalizeStatus('completed', $hposStatusReference);

        if ($mode === 'debug') {
            $debug['steps'][] = [
                'calc' => [
                    'items_subtotal' => $itemsSubtotal,
                    'items_total' => $itemsTotal,
                    'shipping' => $shippingCost,
                    'cart_discount' => $cartDiscount,
                    'final_total' => $finalTotal,
                    'status_posts' => $statusPosts,
                    'status_hpos' => $statusHPOS,
                ]
            ];
        }

        // Transacción
        mysqli_begin_transaction($this->wp_connection);

        try {
            /* --------------------------------------------------------------
             * 1) Crear el ID de la orden desde miau_posts (Legacy)
             *    (Así el order_id queda alineado con WordPress/WooCommerce)
             * ------------------------------------------------------------ */
            $postId = $this->insertRow('miau_posts', [
                'post_author' => $customerId, // 🔥 VINCULACIÓN CRÍTICA
                'post_status' => $statusPosts,
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_type' => 'shop_order',
                'post_date' => $nowLocal,
                'post_date_gmt' => $nowGmt,
                'post_modified' => $nowLocal,
                'post_modified_gmt' => $nowGmt,
                'post_excerpt' => $orderNotes,
            ]);

            if ($postId <= 0) {
                throw new Exception('No se pudo crear el post (shop_order)');
            }

            $debug['steps'][] = ['created_post_order_id' => $postId];

            /* --------------------------------------------------------------
             * 2) Insertar meta básica (Legacy)
             * ------------------------------------------------------------ */
            $metaPairs = [
                // 🔥 CRÍTICO: Campo que vincula pedido con usuario
                '_customer_user'       => (string)$customerId,
                
                '_shipping_first_name' => $firstName,
                '_shipping_last_name'  => $lastName,
                '_billing_first_name'  => $firstName,
                '_billing_last_name'   => $lastName,
                'billing_id'           => $dni,
                '_billing_id'          => $dni,
                '_billing_dni'         => $dni,
                '_billing_email'       => $email,
                '_billing_phone'       => $phone,
                '_billing_neighborhood'=> $barrio,
                'billing_neighborhood' => $barrio,
                '_shipping_address_1'  => $addr1,
                '_shipping_address_2'  => $addr2,
                '_billing_address_1'   => $addr1,
                '_billing_address_2'   => $addr2,
                '_shipping_city'       => $city,
                '_billing_city'        => $city,
                '_shipping_state'      => $state,
                '_billing_state'       => $state,
                '_shipping_country'    => 'CO',
                '_billing_country'     => 'CO',

                '_order_shipping'      => (string)$shippingCost,
                '_cart_discount'       => (string)$cartDiscount,
                '_payment_method_title'=> $paymentTitle,

                // Si lo tienes, guarda también el slug
                '_payment_method'      => $paymentMethod,

                '_order_total'         => (string)$finalTotal,
                '_order_subtotal'      => (string)$itemsSubtotal,
                '_order_currency'      => 'COP',

                '_paid_date'           => $nowLocal,
                '_recorded_sales'      => 'yes',
                '_order_stock_reduced' => 'yes',
                '_created_via'         => 'external_db',
                
                // Campos adicionales para compatibilidad WooCommerce
                '_order_key'           => 'wc_order_' . bin2hex(random_bytes(8)),
                '_prices_include_tax'  => 'no',
                '_order_version'       => '8.0.0',
            ];

            foreach ($metaPairs as $k => $v) {
                // Evitar guardar meta_key vacío
                if (trim((string)$k) === '') continue;
                $this->insertRow('miau_postmeta', [
                    'post_id' => $postId,
                    'meta_key' => $k,
                    'meta_value' => ($v === null ? '' : (string)$v),
                ], true);
            }
            $debug['steps'][] = ['legacy_postmeta_inserted' => count($metaPairs)];

            /* --------------------------------------------------------------
             * 3) Insertar direcciones HPOS (miau_wc_order_addresses)
             * ------------------------------------------------------------ */
            if ($this->tableExists('miau_wc_order_addresses')) {
                // Woo espera state como 'CO-XXX' en muchos casos (si tú ya lo guardas así, no lo dupliques)
                $stateHPOS = str_starts_with($state, 'CO-') ? $state : ('CO-' . $state);

                // billing
                $this->insertRow('miau_wc_order_addresses', [
                    'order_id' => $postId,
                    'address_type' => 'billing',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company' => null,
                    'address_1' => $addr1,
                    'address_2' => $addr2,
                    'city' => $city,
                    'state' => $stateHPOS,
                    'postcode' => null,
                    'country' => 'CO',
                    'email' => $email,
                    'phone' => $phone,
                ]);

                // shipping
                $this->insertRow('miau_wc_order_addresses', [
                    'order_id' => $postId,
                    'address_type' => 'shipping',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company' => null,
                    'address_1' => $addr1,
                    'address_2' => $addr2,
                    'city' => $city,
                    'state' => $stateHPOS,
                    'postcode' => null,
                    'country' => 'CO',
                    'email' => null,
                    'phone' => null,
                ]);

                $debug['steps'][] = ['hpos_addresses_inserted' => true];
            } else {
                $debug['warnings'][] = 'miau_wc_order_addresses no existe: se omiten direcciones HPOS';
            }

            /* --------------------------------------------------------------
             * 4) Insertar items (legacy) + itemmeta
             *    (WooCommerce sigue usando woocommerce_order_items / itemmeta)
             * ------------------------------------------------------------ */
            if (!$this->tableExists('miau_woocommerce_order_items') || !$this->tableExists('miau_woocommerce_order_itemmeta')) {
                throw new Exception('Faltan tablas de items: miau_woocommerce_order_items o miau_woocommerce_order_itemmeta');
            }

            $createdLineItemIds = [];

            foreach ($orderData['products'] as $p) {
                // ✅ Usar el resolver robusto para obtener IDs correctos
                [$parentProductId, $variationId] = $this->resolveWooProductIds($p);

                $title = (string)($p['title'] ?? 'Producto');
                $qty = (int)($p['quantity'] ?? 0);
                if ($qty < 1) continue;

                $regular = (int)($p['regular_price'] ?? $p['price'] ?? 0);
                $price   = (int)(($p['sale_price'] !== null && $p['sale_price'] !== '') ? $p['sale_price'] : ($p['price'] ?? $regular));

                $lineSubtotal = $regular * $qty;
                $lineTotal    = $price * $qty;

                $itemId = $this->insertRow('miau_woocommerce_order_items', [
                    'order_item_name' => $title,
                    'order_item_type' => 'line_item',
                    'order_id' => $postId,
                ]);

                $createdLineItemIds[] = $itemId;

                $itemMeta = [
                    '_product_id' => (string)$parentProductId,
                    '_variation_id' => (string)$variationId,

                    '_qty' => (string)$qty,
                    '_tax_class' => '',
                    '_line_subtotal' => (string)$lineSubtotal,
                    '_line_total' => (string)$lineTotal,
                    '_line_subtotal_tax' => '0',
                    '_line_tax' => '0',
                    '_line_tax_data' => $this->serializeEmptyTaxes(),
                ];

                foreach ($itemMeta as $mk => $mv) {
                    $this->insertRow('miau_woocommerce_order_itemmeta', [
                        'order_item_id' => $itemId,
                        'meta_key' => $mk,
                        'meta_value' => $mv,
                    ]);
                }

                // ✅ Insertar correctamente miau_wc_order_product_lookup (con order_item_id y variaciones)
                if ($this->tableExists('miau_wc_order_product_lookup')) {
                    try {
                        // Calcular descuentos a nivel de producto
                        $productDiscount = ($lineSubtotal - $lineTotal); // Diferencia entre precio regular y precio final
                        $gross = $lineTotal; // revenue por item después de descuentos
                        
                        // Consultar si el producto tiene IVA configurado
                        $taxAmount = 0;
                        $taxStatusQuery = "SELECT meta_value FROM miau_postmeta WHERE post_id = {$parentProductId} AND meta_key = '_tax_status' LIMIT 1";
                        $taxStatusResult = mysqli_query($this->wp_connection, $taxStatusQuery);
                        
                        if ($taxStatusResult && ($taxRow = mysqli_fetch_assoc($taxStatusResult))) {
                            $taxStatus = $taxRow['meta_value'] ?? 'none';
                            
                            // Si el producto es taxable, calcular IVA
                            if ($taxStatus === 'taxable') {
                                $taxRate = (float)($_ENV['TAX_RATE'] ?? 19); // Default 19% si no está en .env
                                $taxAmount = ($gross * $taxRate) / 100;
                            }
                        }
                        
                        // Calcular shipping amount proporcional por producto
                        $totalItemsValue = $itemsTotal; // Total de todos los productos
                        $productShippingAmount = $totalItemsValue > 0 ? ($shippingCost * $gross) / $totalItemsValue : 0;
                        
                        $this->insertRow('miau_wc_order_product_lookup', [
                            'order_item_id' => $itemId,            // ✅ CRÍTICO
                            'order_id'      => $postId,
                            'product_id'    => $parentProductId,   // ✅ padre
                            'variation_id'  => (int)$variationId,  // ✅ variación o 0
                            'customer_id'   => $customerId,
                            'date_created'  => $nowLocal,
                            'product_qty'   => $qty,
                            'product_net_revenue'   => (float)$gross,
                            'product_gross_revenue' => (float)$lineSubtotal, // Precio antes de descuentos
                            'coupon_amount' => (float)$productDiscount, // ✅ Descuentos reales del producto
                            'tax_amount'    => (float)$taxAmount, // ✅ Tax basado en configuración del producto
                            'shipping_amount' => (float)$productShippingAmount, // ✅ Shipping proporcional del form
                            'shipping_tax_amount' => 0,
                        ]);
                    } catch (Exception $e) {
                        $debug['warnings'][] = 'No se pudo insertar wc_order_product_lookup: ' . $e->getMessage();
                    }
                }
            }

            /* --------------------------------------------------------------
             * 4.1) Shipping item (si hay costo)
             * ------------------------------------------------------------ */
            if ($shippingCost > 0) {
                $shipItemId = $this->insertRow('miau_woocommerce_order_items', [
                    'order_item_name' => 'Envío',
                    'order_item_type' => 'shipping',
                    'order_id' => $postId,
                ]);

                $shipMeta = [
                    'method_id' => 'flat_rate',
                    'instance_id' => '0',
                    'cost' => (string)$shippingCost,
                    'total' => (string)$shippingCost,
                    'taxes' => $this->serializeEmptyTaxes(),
                ];

                foreach ($shipMeta as $mk => $mv) {
                    $this->insertRow('miau_woocommerce_order_itemmeta', [
                        'order_item_id' => $shipItemId,
                        'meta_key' => $mk,
                        'meta_value' => $mv,
                    ]);
                }
            }

            /* --------------------------------------------------------------
             * 4.2) Descuento como fee negativo (si aplica)
             * ------------------------------------------------------------ */
            if ($cartDiscount > 0) {
                $feeItemId = $this->insertRow('miau_woocommerce_order_items', [
                    'order_item_name' => 'Descuento',
                    'order_item_type' => 'fee',
                    'order_id' => $postId,
                ]);

                $feeMeta = [
                    '_tax_class' => '',
                    '_line_subtotal' => '-' . (string)$cartDiscount,
                    '_line_total' => '-' . (string)$cartDiscount,
                    '_line_subtotal_tax' => '0',
                    '_line_tax' => '0',
                    '_line_tax_data' => $this->serializeEmptyTaxes(),
                ];

                foreach ($feeMeta as $mk => $mv) {
                    $this->insertRow('miau_woocommerce_order_itemmeta', [
                        'order_item_id' => $feeItemId,
                        'meta_key' => $mk,
                        'meta_value' => $mv,
                    ]);
                }
            }

            $debug['steps'][] = [
                'items_created' => [
                    'line_items' => count($createdLineItemIds),
                    'shipping' => ($shippingCost > 0),
                    'fee_discount' => ($cartDiscount > 0),
                ]
            ];

            /* --------------------------------------------------------------
             * 5) Insertar HPOS order row (miau_wc_orders)
             * ------------------------------------------------------------ */
            if ($this->tableExists('miau_wc_orders')) {
                // Importante: insertamos con el mismo ID que el post
                $hposData = [
                    'id' => $postId,
                    'status' => $statusHPOS,
                    'type' => 'shop_order',
                    'currency' => 'COP',
                    'tax_amount' => 0,
                    'total_amount' => (float)$finalTotal,
                    'shipping_amount' => (float)$shippingCost,
                    'discount_amount' => (float)$cartDiscount,
                    'customer_id' => $customerId, // 🔥 VINCULACIÓN CRÍTICA
                    'billing_email' => $email,
                    'billing_phone' => $phone,
                    'payment_method' => $paymentMethod,
                    'payment_method_title' => $paymentTitle,
                    'date_created_gmt' => $nowGmt,
                    'date_updated_gmt' => $nowGmt,
                    'parent_order_id' => 0,
                    'ip_address' => null,
                    'user_agent' => 'external_db',
                    'transaction_id' => null,
                ];

                // Si ya existe (por algún sync), no insertamos de nuevo
                $check = mysqli_query($this->wp_connection, "SELECT id FROM miau_wc_orders WHERE id=" . (int)$postId . " LIMIT 1");
                if ($check && mysqli_num_rows($check) > 0) {
                    // Mejor hacer update
                    $cols = $this->getTableColumns('miau_wc_orders');
                    $sets = [];
                    foreach ($hposData as $k => $v) {
                        if ($k === 'id') continue;
                        if (!isset($cols[$k])) continue;
                        $val = mysqli_real_escape_string($this->wp_connection, (string)($v ?? ''));
                        $sets[] = "`{$k}`='{$val}'";
                    }
                    if ($sets) {
                        $sqlUp = "UPDATE miau_wc_orders SET " . implode(',', $sets) . " WHERE id=" . (int)$postId;
                        $this->execRaw($sqlUp);
                    }
                    $debug['steps'][] = ['hpos_order_updated' => $postId];
                } else {
                    // Insert normal
                    $this->insertRow('miau_wc_orders', $hposData);
                    $debug['steps'][] = ['hpos_order_inserted' => $postId];
                }

                // Tabla opcional: miau_wc_orders_meta (si existe)
                if ($this->tableExists('miau_wc_orders_meta')) {
                    $meta = [
                        '_created_via' => 'external_db',
                        '_order_currency' => 'COP',
                        '_payment_method' => $paymentMethod,
                        '_payment_method_title' => $paymentTitle,
                    ];

                    foreach ($meta as $k => $v) {
                        try {
                            $this->insertRow('miau_wc_orders_meta', [
                                'order_id' => $postId,
                                'meta_key' => $k,
                                'meta_value' => (string)$v,
                            ]);
                        } catch (Exception $e) {
                            $debug['warnings'][] = 'No se pudo insertar en miau_wc_orders_meta: ' . $e->getMessage();
                        }
                    }
                }

                // Tabla opcional: miau_wc_order_operational_data
                if ($this->tableExists('miau_wc_order_operational_data')) {
                    try {
                        $this->insertRow('miau_wc_order_operational_data', [
                            'order_id' => $postId,
                            'created_via' => 'external_db',
                            'woocommerce_version' => null,
                            'prices_include_tax' => 0,
                            'coupon_usage_counts' => null,
                            'download_permissions_granted' => 0,
                            'cart_hash' => null,
                            'new_order_email_sent' => 0,
                            'order_key' => 'wc_order_' . bin2hex(random_bytes(8)),
                        ]);
                    } catch (Exception $e) {
                        $debug['warnings'][] = 'No se pudo insertar miau_wc_order_operational_data: ' . $e->getMessage();
                    }
                }

            } else {
                $debug['warnings'][] = 'miau_wc_orders no existe: el pedido NO aparecerá en listados HPOS';
            }

            /* --------------------------------------------------------------
             * 6) Tablas derivadas (best effort)
             * ------------------------------------------------------------ */
            $itemsQty = array_sum(array_map(fn($p) => (int)($p['quantity'] ?? 0), $orderData['products'] ?? []));

            $this->bestEffortInsertStatsAndLookups($postId, $statusHPOS, $nowLocal, $nowGmt, $itemsTotal, $itemsSubtotal, $shippingCost, $cartDiscount, $finalTotal, $orderData['products'] ?? [], $customerId);
            // ✅ FIX sumatoria envío en Woo
            $this->upsertOperationalData($postId, $nowGmt, $shippingCost, $cartDiscount, $customerId);
            $this->upsertOrderStats($postId, $nowLocal, $nowGmt, $statusHPOS, $itemsQty, $shippingCost, $finalTotal, $customerId);

            // ✅ Establecer atribución del pedido para tracking en WooCommerce
            $this->setOrderAttribution($postId, 'Sistema de Facturacion');

            mysqli_commit($this->wp_connection);

            return [
                'success' => true,
                'order_id' => $postId,
                'total' => $finalTotal,
                'debug' => $debug,
            ];

        } catch (Exception $e) {
            mysqli_rollback($this->wp_connection);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => $debug,
            ];
        }
    }

    /**
     * Resuelve IDs WooCommerce para line_item:
     * - product_id = ID del producto PADRE
     * - variation_id = ID de la variación (0 si no aplica)
     */
    private function resolveWooProductIds(array $p): array
    {
        // Soportar múltiples nombres de keys (porque el frontend suele variar)
        $variationId = (int)($p['variation_id'] ?? $p['variationId'] ?? $p['id_variation'] ?? 0);
        $productId   = (int)($p['product_id'] ?? $p['productId'] ?? $p['producto_padre_id'] ?? $p['parent_id'] ?? $p['id_producto'] ?? 0);

        $id = (int)($p['id'] ?? 0);
        $postType = (string)($p['post_type'] ?? '');

        // Si el payload dice que es variación y no trajo variation_id, asumimos que "id" es la variación
        if ($variationId <= 0 && $postType === 'product_variation' && $id > 0) {
            $variationId = $id;
        }

        // Si tenemos variationId pero no productId, buscamos el padre en WP
        if ($variationId > 0 && $productId <= 0) {
            $q = "SELECT post_parent FROM miau_posts WHERE ID = {$variationId} AND post_type = 'product_variation' LIMIT 1";
            $r = mysqli_query($this->wp_connection, $q);
            if ($r && ($row = mysqli_fetch_assoc($r))) {
                $productId = (int)($row['post_parent'] ?? 0);
            }
        }

        // Si no es variación y no trajo productId, usamos id como producto
        if ($variationId <= 0 && $productId <= 0 && $id > 0) {
            $productId = $id;
        }

        // Validación dura: si productId queda en 0, algo está mal en el payload/mapeo
        if ($productId <= 0) {
            throw new Exception("Producto inválido: no se pudo resolver product_id. Payload keys=" . implode(',', array_keys($p)));
        }

        return [$productId, $variationId]; // variationId puede ser 0
    }

    /**
     * Inserta tablas de analytics / lookups si existen.
     * OJO: no rompe creación si fallan.
     */
    private function bestEffortInsertStatsAndLookups(
        int $orderId,
        string $status,
        string $nowLocal,
        string $nowGmt,
        int $itemsTotal,
        int $itemsSubtotal,
        int $shippingCost,
        int $cartDiscount,
        int $finalTotal,
        array $products,
        int $customerId = 0
    ): void {
        // 1) wc_order_stats
        if ($this->tableExists('miau_wc_order_stats')) {
            try {
                $this->insertRow('miau_wc_order_stats', [
                    'order_id' => $orderId,
                    'parent_id' => 0,
                    'date_created' => $nowLocal,
                    'date_created_gmt' => $nowGmt,
                    'num_items_sold' => array_sum(array_map(fn($p) => (int)($p['quantity'] ?? 0), $products)),
                    'total_sales' => (float)$finalTotal,
                    'tax_total' => 0,
                    'shipping_total' => (float)$shippingCost,
                    'net_total' => (float)$finalTotal,
                    'returning_customer' => 0,
                    'status' => $status,
                    'customer_id' => $customerId, // 🔥 VINCULACIÓN CRÍTICA
                ]);
            } catch (Exception $e) {
                // no-op
            }
        }

        // 2) wc_order_product_lookup - ELIMINADO porque ahora se inserta correctamente en el foreach de productos
    }

    /* ==============================================================
     *  LECTURAS (mantengo tus métodos, pero mejoro getOrderById)
     * ============================================================ */

    /**
     * Obtener una orden específica por ID
     * - Primero intenta HPOS
     * - Si no existe, cae a posts/postmeta (legacy)
     */
    public function getOrderById($order_id)
    {
        $order_id = (int)$order_id;

        // 1) Intentar HPOS
        if ($this->tableExists('miau_wc_orders')) {

            // ✅ Traemos TODO lo que podamos desde addresses (billing)
            $q = "
                SELECT
                    o.id AS order_id,
                    o.date_created_gmt AS fecha_orden,
                    o.status AS estado,
                    COALESCE(o.total_amount, 0) AS total,

                    COALESCE(NULLIF(o.billing_email,''), NULLIF(ba.email,''), '') AS email_cliente,
                    COALESCE(NULLIF(ba.phone,''), '') AS telefono_cliente,

                    COALESCE(ba.first_name, '') AS nombre_cliente,
                    COALESCE(ba.last_name, '')  AS apellido_cliente,
                    COALESCE(ba.address_1, '')  AS direccion_cliente,
                    COALESCE(ba.address_2, '')  AS direccion_2,
                    COALESCE(ba.city, '')       AS ciudad_cliente,

                    -- En HPOS addresses sí existen (si los insertaste)
                    COALESCE(ba.state, '')      AS departamento_hpos,
                    COALESCE(ba.country, '')    AS pais_hpos

                FROM miau_wc_orders o
                LEFT JOIN miau_wc_order_addresses ba
                    ON o.id = ba.order_id AND ba.address_type = 'billing'
                WHERE o.id = {$order_id}
                LIMIT 1
            ";

            $r = mysqli_query($this->wp_connection, $q);
            if ($r && ($row = mysqli_fetch_assoc($r))) {

                // � Complemento desde postmeta (legacy) porque ahí guardas DNI/Barrio/etc.
                $row['envio']     = (float)$this->getLegacyMetaValue($order_id, '_order_shipping');
                $row['descuento'] = (float)$this->getLegacyMetaValue($order_id, '_cart_discount');
                $row['subtotal']  = (float)$this->getLegacyMetaValue($order_id, '_order_subtotal');

                $row['metodo_pago']        = $this->getLegacyMetaValue($order_id, '_payment_method');
                $row['titulo_metodo_pago'] = $this->getLegacyMetaValue($order_id, '_payment_method_title');

                // ✅ DNI: tú guardas varios keys, devolvemos el primero que exista
                $row['dni_cliente'] = $this->getLegacyMetaFirst($order_id, [
                    '_billing_dni', 'billing_id', '_billing_id'
                ]);

                // ✅ Barrio: tu sistema guarda _billing_neighborhood (NO _billing_barrio)
                $row['barrio'] = $this->getLegacyMetaFirst($order_id, [
                    '_billing_neighborhood', 'billing_neighborhood',
                    '_billing_barrio', 'billing_barrio' // por si algún pedido viejo lo tiene así
                ]);

                // ✅ Departamento: preferimos el legacy (más "humano"), si no, el HPOS
                $row['departamento'] = $this->getLegacyMetaFirst($order_id, [
                    '_billing_state', '_shipping_state'
                ]);
                if (trim((string)$row['departamento']) === '') {
                    $row['departamento'] = (string)($row['departamento_hpos'] ?? '');
                }

                // ✅ País: preferimos legacy, si no, HPOS
                $row['pais'] = $this->getLegacyMetaFirst($order_id, [
                    '_billing_country', '_shipping_country'
                ]);
                if (trim((string)$row['pais']) === '') {
                    $row['pais'] = (string)($row['pais_hpos'] ?? '');
                }

                // Normalizaciones para tu UI
                $row['total'] = (float)($row['total'] ?? 0);
                $row['nombre_completo'] = trim(($row['nombre_cliente'] ?? '') . ' ' . ($row['apellido_cliente'] ?? ''));
                $row['estado_legible'] = $this->getStatusLabel($row['estado']);
                $row['fecha_formateada'] = date('d/m/Y H:i', strtotime($row['fecha_orden']));

                return $row;
            }
        }

        // 2) Fallback legacy
        return $this->getOrderByIdLegacy($order_id);
    }

    private function getLegacyMetaValue(int $postId, string $key): string
    {
        $key = mysqli_real_escape_string($this->wp_connection, $key);
        $query = "SELECT meta_value FROM miau_postmeta WHERE post_id = {$postId} AND meta_key = '{$key}' LIMIT 1";
        $result = mysqli_query($this->wp_connection, $query);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            return $row['meta_value'] ?? '';
        }
        return '';
    }

    /**
     * Devuelve el primer meta_value NO vacío encontrado en el orden indicado.
     * Sirve para soportar múltiples keys (porque en tu sistema guardas varias).
     */
    private function getLegacyMetaFirst(int $postId, array $keys): string
    {
        foreach ($keys as $k) {
            $v = trim((string)$this->getLegacyMetaValue($postId, (string)$k));
            if ($v !== '') return $v;
        }
        return '';
    }

    private function getOrderByIdLegacy(int $order_id): ?array
    {
        $query = "
            SELECT 
                p.ID as order_id,
                p.post_date as fecha_orden,
                p.post_status as estado,
                p.post_modified as fecha_modificacion,

                COALESCE(pm_total.meta_value, '0') as total,
                COALESCE(pm_subtotal.meta_value, '0') as subtotal,
                COALESCE(pm_tax_total.meta_value, '0') as impuestos,
                COALESCE(pm_shipping_total.meta_value, '0') as envio,
                COALESCE(pm_cart_discount.meta_value, '0') as descuento,

                COALESCE(pm_billing_first_name.meta_value, '') as nombre_cliente,
                COALESCE(pm_billing_last_name.meta_value, '') as apellido_cliente,
                COALESCE(pm_billing_email.meta_value, '') as email_cliente,
                COALESCE(pm_billing_phone.meta_value, '') as telefono_cliente,
                COALESCE(pm_billing_address_1.meta_value, '') as direccion_cliente,
                COALESCE(pm_billing_address_2.meta_value, '') as direccion_2,
                COALESCE(pm_billing_city.meta_value, '') as ciudad_cliente,
                COALESCE(pm_billing_state.meta_value, '') as departamento,
                COALESCE(pm_billing_country.meta_value, '') as pais,

                -- ✅ Barrio: tu sistema guarda _billing_neighborhood (y billing_neighborhood)
                COALESCE(pm_billing_neighborhood.meta_value, pm_billing_neighborhood2.meta_value, pm_billing_barrio.meta_value, '') as barrio,

                -- ✅ DNI: fallback porque guardas billing_id/_billing_id además de _billing_dni
                COALESCE(pm_billing_dni.meta_value, pm_billing_id.meta_value, pm_billing_id2.meta_value, '') as dni_cliente,

                COALESCE(pm_payment_method.meta_value, '') as metodo_pago,
                COALESCE(pm_payment_method_title.meta_value, '') as titulo_metodo_pago

            FROM miau_posts p

            LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            LEFT JOIN miau_postmeta pm_subtotal ON p.ID = pm_subtotal.post_id AND pm_subtotal.meta_key = '_order_subtotal'
            LEFT JOIN miau_postmeta pm_tax_total ON p.ID = pm_tax_total.post_id AND pm_tax_total.meta_key = '_order_tax'
            LEFT JOIN miau_postmeta pm_shipping_total ON p.ID = pm_shipping_total.post_id AND pm_shipping_total.meta_key = '_order_shipping'
            LEFT JOIN miau_postmeta pm_cart_discount ON p.ID = pm_cart_discount.post_id AND pm_cart_discount.meta_key = '_cart_discount'

            LEFT JOIN miau_postmeta pm_billing_first_name ON p.ID = pm_billing_first_name.post_id AND pm_billing_first_name.meta_key = '_billing_first_name'
            LEFT JOIN miau_postmeta pm_billing_last_name ON p.ID = pm_billing_last_name.post_id AND pm_billing_last_name.meta_key = '_billing_last_name'
            LEFT JOIN miau_postmeta pm_billing_email ON p.ID = pm_billing_email.post_id AND pm_billing_email.meta_key = '_billing_email'
            LEFT JOIN miau_postmeta pm_billing_phone ON p.ID = pm_billing_phone.post_id AND pm_billing_phone.meta_key = '_billing_phone'
            LEFT JOIN miau_postmeta pm_billing_address_1 ON p.ID = pm_billing_address_1.post_id AND pm_billing_address_1.meta_key = '_billing_address_1'
            LEFT JOIN miau_postmeta pm_billing_address_2 ON p.ID = pm_billing_address_2.post_id AND pm_billing_address_2.meta_key = '_billing_address_2'
            LEFT JOIN miau_postmeta pm_billing_city ON p.ID = pm_billing_city.post_id AND pm_billing_city.meta_key = '_billing_city'
            LEFT JOIN miau_postmeta pm_billing_state ON p.ID = pm_billing_state.post_id AND pm_billing_state.meta_key = '_billing_state'
            LEFT JOIN miau_postmeta pm_billing_country ON p.ID = pm_billing_country.post_id AND pm_billing_country.meta_key = '_billing_country'

            -- ✅ Barrio keys reales
            LEFT JOIN miau_postmeta pm_billing_neighborhood ON p.ID = pm_billing_neighborhood.post_id AND pm_billing_neighborhood.meta_key = '_billing_neighborhood'
            LEFT JOIN miau_postmeta pm_billing_neighborhood2 ON p.ID = pm_billing_neighborhood2.post_id AND pm_billing_neighborhood2.meta_key = 'billing_neighborhood'
            LEFT JOIN miau_postmeta pm_billing_barrio ON p.ID = pm_billing_barrio.post_id AND pm_billing_barrio.meta_key = '_billing_barrio'

            -- ✅ DNI keys reales
            LEFT JOIN miau_postmeta pm_billing_dni ON p.ID = pm_billing_dni.post_id AND pm_billing_dni.meta_key = '_billing_dni'
            LEFT JOIN miau_postmeta pm_billing_id ON p.ID = pm_billing_id.post_id AND pm_billing_id.meta_key = 'billing_id'
            LEFT JOIN miau_postmeta pm_billing_id2 ON p.ID = pm_billing_id2.post_id AND pm_billing_id2.meta_key = '_billing_id'

            LEFT JOIN miau_postmeta pm_payment_method ON p.ID = pm_payment_method.post_id AND pm_payment_method.meta_key = '_payment_method'
            LEFT JOIN miau_postmeta pm_payment_method_title ON p.ID = pm_payment_method_title.post_id AND pm_payment_method_title.meta_key = '_payment_method_title'

            WHERE p.ID = {$order_id}
            AND p.post_type = 'shop_order'
            LIMIT 1
        ";

        $result = mysqli_query($this->wp_connection, $query);
        if (!$result) {
            throw new Exception("Error al obtener orden (legacy): " . mysqli_error($this->wp_connection));
        }

        $order = mysqli_fetch_assoc($result);
        if (!$order) return null;

        $order['total'] = (float)($order['total'] ?? 0);
        $order['subtotal'] = (float)($order['subtotal'] ?? 0);
        $order['impuestos'] = (float)($order['impuestos'] ?? 0);
        $order['envio'] = (float)($order['envio'] ?? 0);
        $order['nombre_completo'] = trim(($order['nombre_cliente'] ?? '') . ' ' . ($order['apellido_cliente'] ?? ''));
        $order['estado_legible'] = $this->getStatusLabel($order['estado']);
        $order['fecha_formateada'] = date('d/m/Y H:i', strtotime($order['fecha_orden']));

        return $order;
    }

    /**
     * Obtener productos de una orden (incluye variation_id)
     */
    public function getOrderItems($order_id)
    {
        $order_id = (int)$order_id;

        // DEBUG: Verificar si existen items para este pedido
        $debug_query = "SELECT COUNT(*) as count FROM miau_woocommerce_order_items WHERE order_id = {$order_id}";
        $debug_result = mysqli_query($this->wp_connection, $debug_query);
        if ($debug_result) {
            $debug_row = mysqli_fetch_assoc($debug_result);
            Utils::logError("DEBUG getOrderItems: Pedido {$order_id} tiene {$debug_row['count']} items en miau_woocommerce_order_items");
        }

        $query = "
            SELECT 
                oi.order_item_id,
                oi.order_item_name as nombre_producto,
                oim_qty.meta_value as cantidad,
                oim_total.meta_value as total_linea,
                oim_subtotal.meta_value as subtotal_linea,
                oim_product_id.meta_value as product_id,
                oim_variation_id.meta_value as variation_id,
                
                -- SKU del producto padre
                pm_sku_parent.meta_value as parent_sku,
                
                -- SKU de la variación (si existe)
                pm_sku_variation.meta_value as variation_sku,
                
                -- Nombre del producto padre
                p_parent.post_title as parent_name,
                
                -- Nombre de la variación (si existe)
                p_variation.post_title as variation_name,
                
                -- PRECIOS PARA DETECTAR DESCUENTOS
                -- Precios del producto padre
                pm_regular_parent.meta_value as parent_regular_price,
                pm_sale_parent.meta_value as parent_sale_price,
                
                -- Precios de la variación (si existe)
                pm_regular_variation.meta_value as variation_regular_price,
                pm_sale_variation.meta_value as variation_sale_price

            FROM miau_woocommerce_order_items oi

            LEFT JOIN miau_woocommerce_order_itemmeta oim_qty
                ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'

            LEFT JOIN miau_woocommerce_order_itemmeta oim_total
                ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
                
            LEFT JOIN miau_woocommerce_order_itemmeta oim_subtotal
                ON oi.order_item_id = oim_subtotal.order_item_id AND oim_subtotal.meta_key = '_line_subtotal'

            LEFT JOIN miau_woocommerce_order_itemmeta oim_product_id
                ON oi.order_item_id = oim_product_id.order_item_id AND oim_product_id.meta_key = '_product_id'

            LEFT JOIN miau_woocommerce_order_itemmeta oim_variation_id
                ON oi.order_item_id = oim_variation_id.order_item_id AND oim_variation_id.meta_key = '_variation_id'
                
            -- Datos del producto padre
            LEFT JOIN miau_posts p_parent 
                ON p_parent.ID = CAST(oim_product_id.meta_value AS UNSIGNED)
                
            LEFT JOIN miau_postmeta pm_sku_parent 
                ON pm_sku_parent.post_id = CAST(oim_product_id.meta_value AS UNSIGNED) 
                AND pm_sku_parent.meta_key = '_sku'
                
            -- Precios del producto padre
            LEFT JOIN miau_postmeta pm_regular_parent 
                ON pm_regular_parent.post_id = CAST(oim_product_id.meta_value AS UNSIGNED) 
                AND pm_regular_parent.meta_key = '_regular_price'
                
            LEFT JOIN miau_postmeta pm_sale_parent 
                ON pm_sale_parent.post_id = CAST(oim_product_id.meta_value AS UNSIGNED) 
                AND pm_sale_parent.meta_key = '_sale_price'
                
            -- Datos de la variación (si existe)
            LEFT JOIN miau_posts p_variation 
                ON p_variation.ID = CAST(oim_variation_id.meta_value AS UNSIGNED) 
                AND oim_variation_id.meta_value > 0
                
            LEFT JOIN miau_postmeta pm_sku_variation 
                ON pm_sku_variation.post_id = CAST(oim_variation_id.meta_value AS UNSIGNED) 
                AND pm_sku_variation.meta_key = '_sku'
                AND oim_variation_id.meta_value > 0
                
            -- Precios de la variación (si existe)
            LEFT JOIN miau_postmeta pm_regular_variation 
                ON pm_regular_variation.post_id = CAST(oim_variation_id.meta_value AS UNSIGNED) 
                AND pm_regular_variation.meta_key = '_regular_price'
                AND oim_variation_id.meta_value > 0
                
            LEFT JOIN miau_postmeta pm_sale_variation 
                ON pm_sale_variation.post_id = CAST(oim_variation_id.meta_value AS UNSIGNED) 
                AND pm_sale_variation.meta_key = '_sale_price'
                AND oim_variation_id.meta_value > 0

            WHERE oi.order_id = {$order_id} 
              AND oi.order_item_type = 'line_item'
            ORDER BY oi.order_item_id
        ";

        $result = mysqli_query($this->wp_connection, $query);
        if (!$result) {
            throw new Exception("Error al obtener items de la orden: " . mysqli_error($this->wp_connection));
        }

        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Procesar datos básicos
            $row['cantidad'] = (int)($row['cantidad'] ?? 0);
            $row['total_linea'] = (float)($row['total_linea'] ?? 0);
            $row['subtotal_linea'] = (float)($row['subtotal_linea'] ?? 0);
            $row['product_id'] = (int)($row['product_id'] ?? 0);
            $row['variation_id'] = (int)($row['variation_id'] ?? 0);
            
            // Determinar el nombre del producto a mostrar
            if (!empty($row['variation_id']) && $row['variation_id'] > 0) {
                // Es una variación - usar nombre de la variación o del item
                $row['product_name'] = $row['variation_name'] ?: $row['nombre_producto'];
                $row['sku'] = $row['variation_sku'] ?: $row['parent_sku'] ?: '';
                
                // Usar precios de la variación si están disponibles
                $row['regular_price'] = (float)($row['variation_regular_price'] ?: $row['parent_regular_price'] ?: 0);
                $row['sale_price'] = (float)($row['variation_sale_price'] ?: $row['parent_sale_price'] ?: 0);
            } else {
                // Es un producto simple - usar nombre del producto padre
                $row['product_name'] = $row['parent_name'] ?: $row['nombre_producto'];
                $row['sku'] = $row['parent_sku'] ?: '';
                
                // Usar precios del producto padre
                $row['regular_price'] = (float)($row['parent_regular_price'] ?: 0);
                $row['sale_price'] = (float)($row['parent_sale_price'] ?: 0);
            }
            
            // Calcular precio unitario actual del pedido
            $row['unit_price'] = $row['cantidad'] > 0 ? $row['total_linea'] / $row['cantidad'] : 0;
            
            // Determinar si hay descuento
            // Un producto tiene descuento si:
            // 1. Tiene sale_price > 0 Y sale_price < regular_price
            // 2. O si line_subtotal > line_total (descuento aplicado en el pedido)
            $has_product_discount = ($row['sale_price'] > 0 && $row['sale_price'] < $row['regular_price']);
            $has_order_discount = ($row['subtotal_linea'] > $row['total_linea']);
            $row['has_discount'] = $has_product_discount || $has_order_discount;
            
            
            // Agregar campos adicionales para compatibilidad con ventas.php
            $row['_regular_price'] = $row['regular_price'];
            $row['_sale_price'] = $row['sale_price'];
            
            // Asegurar que tenemos un nombre de producto
            if (empty($row['product_name'])) {
                $row['product_name'] = $row['nombre_producto'] ?: 'Producto sin nombre';
            }
            
            // Mapear campos para compatibilidad con get_order_details.php
            $row['order_item_name'] = $row['product_name'];
            $row['product_qty'] = $row['cantidad'];
            $row['line_total'] = $row['total_linea'];
            $row['product_sku'] = $row['sku'];

            $items[] = $row;
        }

        mysqli_free_result($result);
        return $items;
    }

    /**
     * Verificar qué tablas de WooCommerce existen (ampliada)
     */
    public function checkTableStructure()
    {
        $query = "SHOW TABLES LIKE 'miau_%'";
        $result = mysqli_query($this->wp_connection, $query);

        $available_tables = [];
        while ($row = mysqli_fetch_array($result)) {
            $available_tables[] = $row[0];
        }

        $structure = ['available_tables' => $available_tables];

        $tables_to_check = [
            'miau_wc_orders',
            'miau_wc_orders_meta',
            'miau_wc_order_operational_data',
            'miau_wc_order_addresses',
            'miau_wc_order_stats',
            'miau_wc_order_product_lookup',
            'miau_posts',
            'miau_postmeta',
            'miau_woocommerce_order_items',
            'miau_woocommerce_order_itemmeta',
        ];

        foreach ($tables_to_check as $table) {
            if (in_array($table, $available_tables)) {
                $desc = mysqli_query($this->wp_connection, "DESCRIBE {$table}");
                if ($desc) {
                    $columns = [];
                    while ($r = mysqli_fetch_assoc($desc)) {
                        $columns[] = $r['Field'] . ' (' . $r['Type'] . ')';
                    }
                    $structure[$table] = $columns;
                }
            } else {
                $structure[$table] = 'Tabla no existe';
            }
        }

        return $structure;
    }


    private function rowExists(string $table, string $whereSql): bool {
        if (!$this->tableExists($table)) return false;
        $r = mysqli_query($this->wp_connection, "SELECT 1 FROM {$table} WHERE {$whereSql} LIMIT 1");
        return ($r && mysqli_num_rows($r) > 0);
    }

    /**
     * UPSERT operacional HPOS
     */
    private function upsertOperationalData(int $orderId, string $nowGmt, int $shippingCost, int $cartDiscount, int $customerId = 0): void {
        if (!$this->tableExists('miau_wc_order_operational_data')) return;

        // ⚠️ OJO: usar EXACTAMENTE los nombres de columnas que tu DESCRIBE mostró
        $opData = [
            'order_id' => $orderId,
            'created_via' => 'external_db',
            'woocommerce_version' => null,
            'prices_include_tax' => 0,
            'coupon_usages_are_counted' => 0,
            'download_permission_granted' => 0,
            'cart_hash' => null,
            'new_order_email_sent' => 0,
            'order_key' => 'wc_order_' . bin2hex(random_bytes(8)),
            'order_stock_reduced' => 1,
            'date_paid_gmt' => $nowGmt,
            'date_completed_gmt' => null,
            'shipping_tax_amount' => 0,
            'shipping_total_amount' => (float)$shippingCost,
            'discount_tax_amount' => 0,
            'discount_total_amount' => (float)$cartDiscount,
            'recorded_sales' => 1,
        ];

        // Si existe, update; si no, insert
        if ($this->rowExists('miau_wc_order_operational_data', 'order_id=' . (int)$orderId)) {
            $this->updateRowByWhere('miau_wc_order_operational_data', $opData, 'order_id=' . (int)$orderId);
        } else {
            $this->insertRow('miau_wc_order_operational_data', $opData);
        }
    }

    /**
     * UPSERT stats HPOS
     */
    private function upsertOrderStats(int $orderId, string $nowLocal, string $nowGmt, string $status, int $itemsQty, int $shippingCost, int $finalTotal, int $customerId = 0): void {
        if (!$this->tableExists('miau_wc_order_stats')) return;

        $stats = [
            'order_id' => $orderId,
            'parent_id' => 0,
            'date_created' => $nowLocal,
            'date_created_gmt' => $nowGmt,
            'date_paid' => $nowLocal,
            'date_completed' => null,
            'num_items_sold' => $itemsQty,
            'total_sales' => (float)$finalTotal,
            'tax_total' => 0,
            'shipping_total' => (float)$shippingCost,
            'net_total' => (float)$finalTotal,
            'returning_customer' => 0,
            'status' => $status,
            'customer_id' => 0,
        ];

        if ($this->rowExists('miau_wc_order_stats', 'order_id=' . (int)$orderId)) {
            $this->updateRowByWhere('miau_wc_order_stats', $stats, 'order_id=' . (int)$orderId);
        } else {
            $this->insertRow('miau_wc_order_stats', $stats, false);
        }
    }

    /**
     * Obtener etiqueta legible del estado
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'wc-pending' => 'Pendiente',
            'wc-processing' => 'Procesando',
            'wc-on-hold' => 'En espera',
            'wc-completed' => 'Completada',
            'wc-cancelled' => 'Cancelada',
            'wc-refunded' => 'Reembolsada',
            'wc-failed' => 'Fallida',
            'wc-checkout-draft' => 'Borrador',
            // Sin prefijo también
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'on-hold' => 'En espera',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'refunded' => 'Reembolsada',
            'failed' => 'Fallida',
            'checkout-draft' => 'Borrador'
        ];

        return $labels[$status] ?? ucfirst(str_replace(['wc-', '_'], ['', ' '], (string)$status));
    }

    /**
     * Método simple para probar conexión y obtener órdenes básicas
     */
    public function getSimpleOrders($limit = 5) {
        // Primero intentar con HPOS
        $query = "SELECT * FROM miau_wc_orders LIMIT $limit";
        $result = mysqli_query($this->wp_connection, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            return ['source' => 'HPOS', 'data' => $orders];
        }
        
        // Si no funciona HPOS, intentar con posts tradicional
        $query = "SELECT ID, post_date, post_status, post_type FROM miau_posts WHERE post_type = 'shop_order' LIMIT $limit";
        $result = mysqli_query($this->wp_connection, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            return ['source' => 'Posts', 'data' => $orders];
        }
        
        return ['source' => 'None', 'data' => [], 'error' => mysqli_error($this->wp_connection)];
    }

    
    /**
     * Mostrar las consultas SQL para probar manualmente
     */
    public function showQueries() {
        $queries = [];
        
        // Consulta para verificar tablas
        $queries['verificar_tablas'] = "SHOW TABLES LIKE 'miau_%';";
        
        // Consulta HPOS principal (todas las órdenes)
        $queries['hpos_orders'] = "
                                SELECT 
                                    o.id AS order_id,
                                    o.date_created_gmt AS fecha_orden,
                                    o.status AS estado,
                                    COALESCE(o.total_amount, 0) AS total,
                                    COALESCE(o.billing_email, '') AS email_cliente,
                                    COALESCE(o.customer_id, 0) AS customer_id,
                                    COALESCE(ba.first_name, '') AS nombre_cliente,
                                    COALESCE(ba.last_name, '') AS apellido_cliente,
                                    COALESCE(ba.phone, '') AS telefono_cliente,
                                    COALESCE(ba.email, o.billing_email) AS email_completo
                                FROM miau_wc_orders o
                                LEFT JOIN miau_wc_order_addresses ba 
                                    ON o.id = ba.order_id 
                                    AND ba.address_type = 'billing'
                                WHERE o.type = 'shop_order'
                                ORDER BY o.date_created_gmt DESC
                                LIMIT 10;";
        
        // Consulta simple HPOS
        $queries['hpos_simple'] = "SELECT * FROM miau_wc_orders WHERE type = 'shop_order' LIMIT 5;";
        
        // Consulta para obtener todos los estados disponibles
        $queries['estados_disponibles'] = "
                                SELECT DISTINCT 
                                    o.status AS estado
                                FROM miau_wc_orders o
                                WHERE o.type = 'shop_order'
                                ORDER BY o.status;";
        
        // Consulta posts tradicional
        $queries['posts_orders'] = "
                                SELECT 
                                    p.ID as order_id,
                                    p.post_date as fecha_orden,
                                    p.post_status as estado,
                                    pm_total.meta_value as total,
                                    pm_email.meta_value as email_cliente
                                FROM miau_posts p
                                LEFT JOIN miau_postmeta pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                                LEFT JOIN miau_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                                WHERE p.post_type = 'shop_order'
                                AND p.post_status IN ('wc-processing', 'wc-on-hold', 'wc-completed')
                                ORDER BY p.post_date DESC
                                LIMIT 5;";
        
        // Consulta simple posts
        $queries['posts_simple'] = "SELECT ID, post_date, post_status, post_type FROM miau_posts WHERE post_type = 'shop_order' LIMIT 5;";
        
        // Verificar estructura de tablas
        $queries['describe_hpos'] = "DESCRIBE miau_wc_orders;";
        $queries['describe_addresses'] = "DESCRIBE miau_wc_order_addresses;";
        $queries['describe_posts'] = "DESCRIBE miau_posts;";
        
        return $queries;
    }

    /**
     * Obtener todos los estados disponibles en las órdenes
     */
    public function getAllOrderStatuses() {
        $query = "
            SELECT DISTINCT 
                o.status as estado,
                COUNT(*) as cantidad
            FROM miau_wc_orders o
            WHERE o.type = 'shop_order'
            GROUP BY o.status
            ORDER BY cantidad DESC
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            return ['error' => mysqli_error($this->wp_connection)];
        }
        
        $estados = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $estados[] = [
                'estado' => $row['estado'],
                'cantidad' => $row['cantidad'],
                'etiqueta' => $this->getStatusLabel($row['estado'])
            ];
        }
        
        return $estados;
    }
    
     
    /**
     * Buscar órdenes por cliente, email o ID
     */
    public function searchOrders($search_term, $status = 'all', $limit = 50) {
        $search_term = mysqli_real_escape_string($this->wp_connection, $search_term);
        
        $status_condition = '';
        if ($status !== 'all') {
            $status_condition = "AND o.status = '$status'";
        }
        
        $query = "
            SELECT 
                o.id AS order_id,
                o.date_created_gmt AS fecha_orden,
                o.status AS estado,
                COALESCE(o.total_amount, 0) AS total,
                COALESCE(o.billing_email, '') AS email_cliente,
                COALESCE(o.customer_id, 0) AS customer_id,
                COALESCE(ba.first_name, '') AS nombre_cliente,
                COALESCE(ba.last_name, '') AS apellido_cliente,
                COALESCE(ba.phone, '') AS telefono_cliente,
                COALESCE(ba.email, o.billing_email) AS email_completo
            FROM miau_wc_orders o
            LEFT JOIN miau_wc_order_addresses ba 
                ON o.id = ba.order_id 
                AND ba.address_type = 'billing'
            WHERE o.type = 'shop_order'
            $status_condition
            AND (
                o.id LIKE '%$search_term%' 
                OR ba.first_name LIKE '%$search_term%'
                OR ba.last_name LIKE '%$search_term%'
                OR o.billing_email LIKE '%$search_term%'
                OR ba.phone LIKE '%$search_term%'
            )
            ORDER BY o.date_created_gmt DESC
            LIMIT $limit
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en búsqueda de órdenes: " . mysqli_error($this->wp_connection));
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['total'] = floatval($row['total'] ?? 0);
            $row['nombre_completo'] = trim(($row['nombre_cliente'] ?? '') . ' ' . ($row['apellido_cliente'] ?? ''));
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            $row['fecha_formateada'] = date('d/m/Y H:i', strtotime($row['fecha_orden']));
            
            // Asegurar que todos los campos existan y usar el email más completo
            $row['email_cliente'] = $row['email_completo'] ?: $row['email_cliente'];
            $row['telefono_cliente'] = $row['telefono_cliente'] ?? '';
            
            $orders[] = $row;
        }
        
        return $orders;
    }
    
    /**
     * Obtener todas las órdenes de WooCommerce
     */
    public function getAllOrders($status = 'all', $limit = 100, $offset = 0) {
        $status_condition = '';
        if ($status !== 'all') {
            $status_condition = "AND o.status = '$status'";
        }
        
        $query = "
            SELECT 
                o.id AS order_id,
                o.date_created_gmt AS fecha_orden,
                o.status AS estado,
                COALESCE(o.total_amount, 0) AS total,
                COALESCE(o.billing_email, '') AS email_cliente,
                COALESCE(o.customer_id, 0) AS customer_id,
                COALESCE(ba.first_name, '') AS nombre_cliente,
                COALESCE(ba.last_name, '') AS apellido_cliente,
                COALESCE(ba.phone, '') AS telefono_cliente,
                COALESCE(ba.email, o.billing_email) AS email_completo
            FROM miau_wc_orders o
            LEFT JOIN miau_wc_order_addresses ba 
                ON o.id = ba.order_id 
                AND ba.address_type = 'billing'
            WHERE o.type = 'shop_order'
            $status_condition
            ORDER BY o.date_created_gmt DESC
            LIMIT $limit OFFSET $offset
        ";
        
        // DEBUG: Mostrar la consulta que se va a ejecutar
        if (isset($_GET['debug_sql'])) {
            echo "<pre>CONSULTA SQL:\n" . $query . "\n</pre>";
        }
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error en consulta de órdenes: " . mysqli_error($this->wp_connection));
        }
        
        // DEBUG: Verificar si hay resultados
        $num_rows = mysqli_num_rows($result);
        if (isset($_GET['debug_sql'])) {
            echo "<pre>NÚMERO DE FILAS ENCONTRADAS: " . $num_rows . "\n</pre>";
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // DEBUG: Mostrar datos crudos de la primera fila
            if (isset($_GET['debug_sql']) && count($orders) == 0) {
            }
            
            // Formatear datos para compatibilidad
            $row['total'] = floatval($row['total'] ?? 0);
            $row['nombre_completo'] = trim(($row['nombre_cliente'] ?? '') . ' ' . ($row['apellido_cliente'] ?? ''));
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            $row['fecha_formateada'] = date('d/m/Y H:i', strtotime($row['fecha_orden']));
            
            // Asegurar que todos los campos existan y usar el email más completo
            $row['email_cliente'] = $row['email_completo'] ?: $row['email_cliente'];
            $row['telefono_cliente'] = $row['telefono_cliente'] ?? '';
            $row['direccion_cliente'] = '';
            $row['ciudad_cliente'] = '';
            
            $orders[] = $row;
        }       
        
        return $orders;
    }

    /**
     * Obtener estadísticas de órdenes
     */
    public function getOrderStats($days = 30) {
        $date_from = date('Y-m-d', strtotime("-$days days"));
        
        $query = "
            SELECT 
                o.status as estado,
                COUNT(*) as cantidad,
                SUM(COALESCE(o.total_amount, 0)) as total_ventas
            FROM miau_wc_orders o
            WHERE o.type = 'shop_order'
            AND o.date_created_gmt >= '$date_from'
            GROUP BY o.status
            ORDER BY cantidad DESC
        ";
        
        $result = mysqli_query($this->wp_connection, $query);
        
        if (!$result) {
            die("Error al obtener estadísticas: " . mysqli_error($this->wp_connection));
        }
        
        $stats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['cantidad'] = intval($row['cantidad']);
            $row['total_ventas'] = floatval($row['total_ventas']);
            $row['estado_legible'] = $this->getStatusLabel($row['estado']);
            
            $stats[] = $row;
        }
        
        return $stats;
    }

    /**
     * ✅ Obtiene descuentos totales de un pedido desde múltiples fuentes
     * 
     * @param int $order_id ID del pedido
     * @return array Array con descuentos detallados
     */
    public function getOrderDiscounts(int $order_id): array
    {
        $discounts = [
            'cart_discount' => 0,
            'coupons' => [],
            'fees' => [],
            'total_discount' => 0
        ];
        
        // 1) Descuento desde _cart_discount en postmeta
        $query_cart = "
            SELECT meta_value as cart_discount
            FROM miau_postmeta 
            WHERE post_id = $order_id 
            AND meta_key = '_cart_discount' 
            AND meta_value != '0' 
            AND meta_value != ''
            LIMIT 1
        ";
        
        $result = mysqli_query($this->wp_connection, $query_cart);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $discounts['cart_discount'] = abs((float)$row['cart_discount']);
        }
        
        // 2) Descuentos desde ítems (cupones y fees negativos)
        if ($this->tableExists('miau_woocommerce_order_items')) {
            $query_items = "
                SELECT 
                    oi.order_item_name,
                    oi.order_item_type,
                    COALESCE(SUM(CAST(oim.meta_value AS DECIMAL(10,2))), 0) as item_total
                FROM miau_woocommerce_order_items oi
                LEFT JOIN miau_woocommerce_order_itemmeta oim 
                    ON oi.order_item_id = oim.order_item_id 
                    AND oim.meta_key IN ('_line_total', '_fee_amount')
                WHERE oi.order_id = $order_id
                AND oi.order_item_type IN ('coupon', 'fee')
                GROUP BY oi.order_item_id, oi.order_item_name, oi.order_item_type
            ";
            
            $result = mysqli_query($this->wp_connection, $query_items);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $amount = abs((float)$row['item_total']);
                    
                    if ($row['order_item_type'] === 'coupon') {
                        $discounts['coupons'][] = [
                            'name' => $row['order_item_name'],
                            'amount' => $amount
                        ];
                    } elseif ($row['order_item_type'] === 'fee' && $amount > 0) {
                        $discounts['fees'][] = [
                            'name' => $row['order_item_name'],
                            'amount' => $amount
                        ];
                    }
                }
            }
        }
        
        // 3) Calcular descuento total
        $total = $discounts['cart_discount'];
        
        foreach ($discounts['coupons'] as $coupon) {
            $total += $coupon['amount'];
        }
        
        foreach ($discounts['fees'] as $fee) {
            $total += $fee['amount'];
        }
        
        $discounts['total_discount'] = $total;
        
        return $discounts;
    }

    private function upsertOrderMeta(string $table, string $idColumn, int $orderId, string $metaKey, string $metaValue): void
    {
        if (!$this->tableExists($table)) {
            return; // Si la tabla no existe, no rompemos la creación del pedido
        }

        // 1) Borramos posibles duplicados (postmeta y wc_orders_meta NO tienen UNIQUE por (id, meta_key))
        $sqlDel = "DELETE FROM {$table} WHERE {$idColumn} = ? AND meta_key = ?";
        $stmtDel = mysqli_prepare($this->wp_connection, $sqlDel);
        if (!$stmtDel) {
            throw new Exception("Prepare delete meta failed ({$table}): " . mysqli_error($this->wp_connection));
        }
        mysqli_stmt_bind_param($stmtDel, 'is', $orderId, $metaKey);
        if (!mysqli_stmt_execute($stmtDel)) {
            throw new Exception("Execute delete meta failed ({$table}): " . mysqli_stmt_error($stmtDel));
        }
        mysqli_stmt_close($stmtDel);

        // 2) Insertamos el meta limpio
        $this->insertRow($table, [
            $idColumn   => $orderId,
            'meta_key'  => $metaKey,
            'meta_value'=> $metaValue,
        ], false);
    }

    /**
     * Setea el origen/atribución del pedido para que WP lo muestre en "Order attribution".
     *
     * Truco correcto:
     * - Usamos source_type = 'utm' porque es el que permite mostrar una "fuente" (utm_source) con texto libre.
     */
    public function setOrderAttribution(int $orderId, string $originLabel = 'Sistema de Facturacion'): void
    {
        // Fuente “custom” usando el modelo UTM:
        $sourceType = 'utm';

        // HPOS meta (si existe)
        $this->upsertOrderMeta('miau_wc_orders_meta', 'order_id', $orderId, '_wc_order_attribution_source_type', $sourceType);
        $this->upsertOrderMeta('miau_wc_orders_meta', 'order_id', $orderId, '_wc_order_attribution_utm_source', $originLabel);

        // Legacy meta (si existe) para compatibilidad total
        $this->upsertOrderMeta('miau_postmeta', 'post_id', $orderId, '_wc_order_attribution_source_type', $sourceType);
        $this->upsertOrderMeta('miau_postmeta', 'post_id', $orderId, '_wc_order_attribution_utm_source', $originLabel);
    }

    /**
     * Completar pedido y crear factura
     * @param int $order_id ID del pedido
     * @param string $invoice_number Número de factura
     * @return bool True si se completó exitosamente
     */
    public function completeOrder(int $order_id, string $invoice_number): bool
    {
        try {
            // Sanitizar datos
            $order_id = (int)$order_id;
            $invoice_number = mysqli_real_escape_string($this->wp_connection, $invoice_number);
            
            // Iniciar transacción
            mysqli_autocommit($this->wp_connection, false);
            
            // 1. Insertar en tabla facturas (sistema local)
            $query_factura = "INSERT INTO facturas (id_order, factura, estado) VALUES ('$order_id', '$invoice_number', 'a')";
            if (!mysqli_query($this->wp_connection, $query_factura)) {
                throw new Exception("Error insertando factura: " . mysqli_error($this->wp_connection));
            }
            
            // 2. Actualizar estado del pedido a completado
            $query_post = "UPDATE miau_posts SET post_status = 'wc-completed' WHERE ID = '$order_id'";
            if (!mysqli_query($this->wp_connection, $query_post)) {
                throw new Exception("Error actualizando post: " . mysqli_error($this->wp_connection));
            }
            
            // 3. Actualizar estadísticas de WooCommerce
            $query_stats = "UPDATE miau_wc_order_stats SET status = 'wc-completed' WHERE order_id = '$order_id'";
            mysqli_query($this->wp_connection, $query_stats); // No crítico si falla
            
            // 4. Actualizar HPOS si existe
            $query_hpos = "UPDATE miau_wc_orders SET status = 'wc-completed' WHERE id = '$order_id'";
            mysqli_query($this->wp_connection, $query_hpos); // No crítico si falla
            
            // Confirmar transacción
            mysqli_commit($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            return true;
            
        } catch (Exception $e) {
            // Revertir transacción
            mysqli_rollback($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            Utils::logError("Error en completeOrder: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return false;
        }
    }
    
    /**
     * Cancelar pedido y restaurar stock
     * @param int $order_id ID del pedido
     * @return bool True si se canceló exitosamente
     */
    public function cancelOrder(int $order_id): bool
    {
        try {
            $order_id = (int)$order_id;
            
            // Iniciar transacción
            mysqli_autocommit($this->wp_connection, false);
            
            // 1. Actualizar estado del pedido a cancelado
            $query_post = "UPDATE miau_posts SET post_status = 'wc-cancelled' WHERE ID = '$order_id'";
            if (!mysqli_query($this->wp_connection, $query_post)) {
                throw new Exception("Error actualizando post: " . mysqli_error($this->wp_connection));
            }
            
            // 2. Actualizar estadísticas de WooCommerce
            $query_stats = "UPDATE miau_wc_order_stats SET status = 'wc-cancelled' WHERE order_id = '$order_id'";
            mysqli_query($this->wp_connection, $query_stats);
            
            // 3. Actualizar HPOS si existe
            $query_hpos = "UPDATE miau_wc_orders SET status = 'wc-cancelled' WHERE id = '$order_id'";
            mysqli_query($this->wp_connection, $query_hpos);
            
            // 4. Marcar factura como inactiva si existe
            $query_factura = "UPDATE facturas SET estado = 'i' WHERE id_order = '$order_id'";
            mysqli_query($this->wp_connection, $query_factura);
            
            // 5. Restaurar stock de productos
            $this->restoreOrderStock($order_id);
            
            // Confirmar transacción
            mysqli_commit($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            return true;
            
        } catch (Exception $e) {
            // Revertir transacción
            mysqli_rollback($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            Utils::logError("Error en cancelOrder: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return false;
        }
    }
    
    /**
     * Procesar pedido (cambiar a processing)
     * @param int $order_id ID del pedido
     * @return bool True si se procesó exitosamente
     */
    public function processOrder(int $order_id): bool
    {
        try {
            $order_id = (int)$order_id;
            
            // Iniciar transacción
            mysqli_autocommit($this->wp_connection, false);
            
            // 1. Actualizar estado del pedido a processing
            $query_post = "UPDATE miau_posts SET post_status = 'wc-processing' WHERE ID = '$order_id'";
            if (!mysqli_query($this->wp_connection, $query_post)) {
                throw new Exception("Error actualizando post: " . mysqli_error($this->wp_connection));
            }
            
            // 2. Actualizar estadísticas de WooCommerce
            $query_stats = "UPDATE miau_wc_order_stats SET status = 'wc-processing' WHERE order_id = '$order_id'";
            mysqli_query($this->wp_connection, $query_stats);
            
            // 3. Actualizar HPOS si existe
            $query_hpos = "UPDATE miau_wc_orders SET status = 'wc-processing' WHERE id = '$order_id'";
            mysqli_query($this->wp_connection, $query_hpos);
            
            // Confirmar transacción
            mysqli_commit($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            return true;
            
        } catch (Exception $e) {
            // Revertir transacción
            mysqli_rollback($this->wp_connection);
            mysqli_autocommit($this->wp_connection, true);
            
            Utils::logError("Error en processOrder: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return false;
        }
    }
    
    /**
     * Restaurar stock de productos de un pedido cancelado
     * @param int $order_id ID del pedido
     */
    private function restoreOrderStock(int $order_id): void
    {
        try {
            // Obtener productos del pedido
            $query_products = "SELECT 
                IM.product_id,
                CAST(IM.qty AS UNSIGNED) as product_qty
            FROM miau_woocommerce_order_items I
            LEFT JOIN (
                SELECT order_item_id,
                    MAX(CASE WHEN meta_key = '_product_id' THEN meta_value END) AS product_id,
                    MAX(CASE WHEN meta_key = '_qty' THEN meta_value END) AS qty
                FROM miau_woocommerce_order_itemmeta
                WHERE meta_key IN ('_product_id','_qty')
                GROUP BY order_item_id
            ) IM ON IM.order_item_id = I.order_item_id
            WHERE I.order_id = '$order_id' AND I.order_item_type = 'line_item'";
            
            $result = mysqli_query($this->wp_connection, $query_products);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $product_id = (int)$row['product_id'];
                $product_qty = (int)$row['product_qty'];
                
                if ($product_id > 0 && $product_qty > 0) {
                    // Obtener stock actual
                    $query_stock = "SELECT meta_value FROM miau_postmeta WHERE post_id = '$product_id' AND meta_key = '_stock'";
                    $stock_result = mysqli_query($this->wp_connection, $query_stock);
                    $stock_row = mysqli_fetch_assoc($stock_result);
                    
                    if ($stock_row) {
                        $current_stock = (int)$stock_row['meta_value'];
                        $new_stock = $current_stock + $product_qty;
                        
                        // Actualizar stock
                        $query_update_stock = "UPDATE miau_postmeta SET meta_value = '$new_stock' WHERE post_id = '$product_id' AND meta_key = '_stock'";
                        mysqli_query($this->wp_connection, $query_update_stock);
                        
                        // Actualizar estado del stock si es mayor a 0
                        if ($new_stock > 0) {
                            $query_stock_status = "UPDATE miau_postmeta SET meta_value = 'instock' WHERE post_id = '$product_id' AND meta_key = '_stock_status'";
                            mysqli_query($this->wp_connection, $query_stock_status);
                        }
                        
                        Utils::logError("Stock restaurado - Producto: $product_id, Cantidad: +$product_qty, Nuevo stock: $new_stock", 'INFO', 'WooCommerceOrders');
                    }
                }
            }
            
        } catch (Exception $e) {
            Utils::logError("Error restaurando stock: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
        }
    }

    /**
     * Obtener pedidos pendientes (processing y on-hold con cheque)
     * @param int $page Página actual (empezando en 1)
     * @param int $per_page Pedidos por página
     * @return array Array con 'data' (pedidos) y 'pagination' (info de paginación)
     */
    public function getPendingOrders(int $page = 1, int $per_page = 20): array
    {
        try {
            // Validar parámetros de paginación
            $page = max(1, (int)$page);
            $per_page = max(1, min(100, (int)$per_page)); // Máximo 100 por página
            $offset = ($page - 1) * $per_page;
            
            // Consulta para contar total de registros
            $count_query = "SELECT COUNT(*) as total
            FROM miau_posts p
            WHERE (
                p.post_status = 'wc-processing' 
                OR (
                    p.post_status = 'wc-on-hold' 
                    AND p.ID IN (
                        SELECT pm_payment.post_id 
                        FROM miau_postmeta pm_payment 
                        WHERE pm_payment.meta_key = '_payment_method' 
                        AND pm_payment.meta_value = 'cheque'
                    )
                )
            )";
            
            $count_result = mysqli_query($this->wp_connection, $count_query);
            $total_records = 0;
            if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
                $total_records = (int)$count_row['total'];
            }
            
            // Consulta principal con paginación
            $query = "SELECT 
                p.ID,
                p.post_date,
                COALESCE(pm_fname.meta_value, '') as nombre1,
                COALESCE(pm_lname.meta_value, '') as nombre2,
                COALESCE(os.total_sales, 0) as valor
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
            LEFT JOIN miau_postmeta pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
            LEFT JOIN miau_wc_order_stats os ON p.ID = os.order_id
            WHERE (
                p.post_status = 'wc-processing' 
                OR (
                    p.post_status = 'wc-on-hold' 
                    AND p.ID IN (
                        SELECT pm_payment.post_id 
                        FROM miau_postmeta pm_payment 
                        WHERE pm_payment.meta_key = '_payment_method' 
                        AND pm_payment.meta_value = 'cheque'
                    )
                )
            )
            ORDER BY p.ID DESC
            LIMIT $per_page OFFSET $offset";
            
            $result = mysqli_query($this->wp_connection, $query);
            
            if (!$result) {
                Utils::logError("Error en getPendingOrders: " . mysqli_error($this->wp_connection), 'ERROR', 'WooCommerceOrders');
                return ['data' => [], 'pagination' => []];
            }
            
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            
            // Calcular información de paginación
            $total_pages = ceil($total_records / $per_page);
            $pagination = [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_records' => $total_records,
                'total_pages' => $total_pages,
                'has_previous' => $page > 1,
                'has_next' => $page < $total_pages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $total_pages ? $page + 1 : null,
                'start_record' => $total_records > 0 ? $offset + 1 : 0,
                'end_record' => min($offset + $per_page, $total_records)
            ];
            
            Utils::logError("Pedidos pendientes obtenidos: " . count($orders) . " de $total_records (página $page de $total_pages)", 'INFO', 'WooCommerceOrders');
            
            return [
                'data' => $orders,
                'pagination' => $pagination
            ];
            
        } catch (Exception $e) {
            Utils::logError("Error en getPendingOrders: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return [];
        }
    }
    
    /**
     * Obtener pedidos facturados en un rango de fechas
     * @param string $date_from Fecha desde (Y-m-d)
     * @param string $date_to Fecha hasta (Y-m-d)
     * @param int $page Página actual (empezando en 1)
     * @param int $per_page Pedidos por página
     * @return array Array con 'data' (pedidos) y 'pagination' (info de paginación)
     */
    public function getInvoicedOrders(string $date_from, string $date_to, int $page = 1, int $per_page = 20): array
    {
        try {
            // Primero obtener IDs de órdenes facturadas del sistema local
            $query_facturas = "SELECT id_order FROM facturas WHERE estado = 'a'";
            $facturas_result = mysqli_query($this->wp_connection, $query_facturas);
            
            if (!$facturas_result) {
                Utils::logError("Error obteniendo facturas: " . mysqli_error($this->wp_connection), 'ERROR', 'WooCommerceOrders');
                return [];
            }
            
            $facturas_ids = [];
            while ($row_fact = mysqli_fetch_assoc($facturas_result)) {
                $facturas_ids[] = (int)$row_fact['id_order'];
            }
            
            // Si no hay facturas, retornar array vacío
            if (empty($facturas_ids)) {
                Utils::logError("No hay facturas activas", 'INFO', 'WooCommerceOrders');
                return ['data' => [], 'pagination' => []];
            }
            
            // Validar parámetros de paginación
            $page = max(1, (int)$page);
            $per_page = max(1, min(100, (int)$per_page)); // Máximo 100 por página
            $offset = ($page - 1) * $per_page;
            
            // Sanitizar fechas
            $date_from = mysqli_real_escape_string($this->wp_connection, $date_from);
            $date_to = mysqli_real_escape_string($this->wp_connection, $date_to);
            
            // Crear string de IDs para la consulta
            $ids_string = implode(',', $facturas_ids);
            
            // Consulta para contar total de registros
            $count_query = "SELECT COUNT(*) as total
            FROM miau_posts p
            WHERE p.ID IN ($ids_string) 
            AND p.post_date >= '$date_from' 
            AND p.post_date <= '$date_to'";
            
            $count_result = mysqli_query($this->wp_connection, $count_query);
            $total_records = 0;
            if ($count_result && $count_row = mysqli_fetch_assoc($count_result)) {
                $total_records = (int)$count_row['total'];
            }
            
            // Consulta principal con paginación
            $query = "SELECT 
                p.ID,
                p.post_date,
                COALESCE(pm_fname.meta_value, '') as nombre1,
                COALESCE(pm_lname.meta_value, '') as nombre2,
                COALESCE(os.total_sales, 0) as valor
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
            LEFT JOIN miau_postmeta pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
            LEFT JOIN miau_wc_order_stats os ON p.ID = os.order_id
            WHERE p.ID IN ($ids_string) 
            AND p.post_date >= '$date_from' 
            AND p.post_date <= '$date_to'
            ORDER BY p.ID DESC
            LIMIT $per_page OFFSET $offset";
            
            $result = mysqli_query($this->wp_connection, $query);
            
            if (!$result) {
                Utils::logError("Error en getInvoicedOrders: " . mysqli_error($this->wp_connection), 'ERROR', 'WooCommerceOrders');
                return ['data' => [], 'pagination' => []];
            }
            
            $orders = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            
            // Calcular información de paginación
            $total_pages = ceil($total_records / $per_page);
            $pagination = [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_records' => $total_records,
                'total_pages' => $total_pages,
                'has_previous' => $page > 1,
                'has_next' => $page < $total_pages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $total_pages ? $page + 1 : null,
                'start_record' => $total_records > 0 ? $offset + 1 : 0,
                'end_record' => min($offset + $per_page, $total_records)
            ];
            
            Utils::logError("Pedidos facturados obtenidos: " . count($orders) . " de $total_records (página $page de $total_pages, desde $date_from hasta $date_to)", 'INFO', 'WooCommerceOrders');
            
            return [
                'data' => $orders,
                'pagination' => $pagination
            ];
            
        } catch (Exception $e) {
            Utils::logError("Error en getInvoicedOrders: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return ['data' => [], 'pagination' => []];
        }
    }
    
    /**
     * Verificar si una orden tiene factura activa
     * @param int $order_id ID del pedido
     * @return bool True si tiene factura activa
     */
    public function hasInvoice(int $order_id): bool
    {
        try {
            $order_id = (int)$order_id;
            
            // Usar conexión de ventassc para tabla facturas
            $ventas_connection = DatabaseConfig::getVentasConnection();
            $query = "SELECT COUNT(*) as count FROM facturas WHERE id_order = '$order_id' AND estado = 'a'";
            $result = mysqli_query($ventas_connection, $query);
            
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $has_invoice = (int)$row['count'] > 0;
                mysqli_close($ventas_connection);
                return $has_invoice;
            }
            
            mysqli_close($ventas_connection);
            return false;
            
        } catch (Exception $e) {
            Utils::logError("Error en hasInvoice: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return false;
        }
    }
    
    /**
     * Obtener estado actual de una orden
     * @param int $order_id ID del pedido
     * @return string Estado de la orden (wc-processing, wc-completed, etc.)
     */
    public function getOrderStatus(int $order_id): string
    {
        try {
            $order_id = (int)$order_id;
            $query = "SELECT post_status FROM miau_posts WHERE ID = '$order_id' AND post_type = 'shop_order'";
            $result = mysqli_query($this->wp_connection, $query);
            
            if ($result && $row = mysqli_fetch_assoc($result)) {
                return $row['post_status'];
            }
            
            return '';
            
        } catch (Exception $e) {
            Utils::logError("Error en getOrderStatus: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return '';
        }
    }
    
    /**
     * Obtener detalles completos de una orden para modal
     * @param int $order_id ID del pedido
     * @return array Detalles de la orden
     */
    public function getOrderDetails(int $order_id): array
    {
        try {
            $order_id = (int)$order_id;
            
            // Obtener datos básicos de la orden
            $query = "SELECT 
                p.ID,
                p.post_date,
                p.post_status,
                COALESCE(pm_fname.meta_value, '') as billing_first_name,
                COALESCE(pm_lname.meta_value, '') as billing_last_name,
                COALESCE(pm_email.meta_value, '') as billing_email,
                COALESCE(pm_phone.meta_value, '') as billing_phone,
                COALESCE(pm_address1.meta_value, '') as billing_address_1,
                COALESCE(pm_address2.meta_value, '') as billing_address_2,
                COALESCE(pm_city.meta_value, '') as billing_city,
                COALESCE(pm_state.meta_value, '') as billing_state,
                COALESCE(pm_country.meta_value, '') as billing_country,
                COALESCE(pm_barrio.meta_value, '') as billing_barrio,
                COALESCE(pm_dni.meta_value, '') as dni_cliente,
                COALESCE(pm_payment.meta_value, '') as payment_method,
                COALESCE(pm_payment_title.meta_value, '') as payment_method_title,
                COALESCE(pm_shipping.meta_value, '0') as shipping_cost,
                COALESCE(os.total_sales, 0) as total
            FROM miau_posts p
            LEFT JOIN miau_postmeta pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
            LEFT JOIN miau_postmeta pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
            LEFT JOIN miau_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN miau_postmeta pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
            LEFT JOIN miau_postmeta pm_address1 ON p.ID = pm_address1.post_id AND pm_address1.meta_key = '_billing_address_1'
            LEFT JOIN miau_postmeta pm_address2 ON p.ID = pm_address2.post_id AND pm_address2.meta_key = '_billing_address_2'
            LEFT JOIN miau_postmeta pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = '_billing_city'
            LEFT JOIN miau_postmeta pm_state ON p.ID = pm_state.post_id AND pm_state.meta_key = '_billing_state'
            LEFT JOIN miau_postmeta pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = '_billing_country'
            LEFT JOIN miau_postmeta pm_barrio ON p.ID = pm_barrio.post_id AND pm_barrio.meta_key = '_billing_neighborhood'
            LEFT JOIN miau_postmeta pm_dni ON p.ID = pm_dni.post_id AND pm_dni.meta_key = '_billing_id'
            LEFT JOIN miau_postmeta pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_payment_method'
            LEFT JOIN miau_postmeta pm_payment_title ON p.ID = pm_payment_title.post_id AND pm_payment_title.meta_key = '_payment_method_title'
            LEFT JOIN miau_postmeta pm_shipping ON p.ID = pm_shipping.post_id AND pm_shipping.meta_key = '_order_shipping'
            LEFT JOIN miau_wc_order_stats os ON p.ID = os.order_id
            WHERE p.ID = '$order_id'";
            
            $result = mysqli_query($this->wp_connection, $query);
            
            if (!$result || !($order_data = mysqli_fetch_assoc($result))) {
                return [];
            }
            
            // Obtener productos de la orden
            $order_data['items'] = $this->getOrderItems($order_id);
            
            // Verificar si tiene factura
            $order_data['has_invoice'] = $this->hasInvoice($order_id);
            
            return $order_data;
            
        } catch (Exception $e) {
            Utils::logError("Error en getOrderDetails: " . $e->getMessage(), 'ERROR', 'WooCommerceOrders');
            return [];
        }
    }

    public function __destruct()
    {
        if (!empty($this->wp_connection)) {
            mysqli_close($this->wp_connection);
        }
    }
}
