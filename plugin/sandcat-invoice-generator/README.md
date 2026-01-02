# Sand y Cat Invoice Generator

Plugin de WordPress para generar facturas PDF para pedidos de WooCommerce usando el sistema de ventas SandCat.

## Características

- **Integración con WooCommerce**: Se integra directamente en la página de detalles del pedido
- **Generación de PDF**: Usa mPDF para crear facturas profesionales
- **Conexión a base de datos externa**: Se conecta al sistema de ventas SandCat
- **Numeración automática**: Obtiene e incrementa automáticamente el número de factura
- **Template personalizado**: Usa el mismo template del sistema existente

## Instalación

### Requisitos

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Acceso a base de datos MySQL del sistema de ventas

### Pasos de instalación

1. **Copiar archivos del plugin**:
   ```bash
   # Copiar la carpeta plugin/ a wp-content/plugins/
   cp -r plugin/ /path/to/wordpress/wp-content/plugins/sandycat-invoice-generator/
   ```

2. **Instalar dependencias**:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/sandycat-invoice-generator/
   composer install
   ```

3. **Activar el plugin**:
   - Ir al admin de WordPress
   - Plugins > Plugins instalados
   - Activar "Sand y Cat Invoice Generator"

### Configuración de base de datos

El plugin se conecta automáticamente a la base de datos de ventas usando estas credenciales:

```php
Host: localhost
Database: ventassc
User: root
Password: (vacío)
```

Si necesitas cambiar estas credenciales, edita las líneas 72-76 en `sandycat-invoice-generator.php`.

## Uso

### Generar factura desde pedido

1. Ir a **WooCommerce > Pedidos**
2. Abrir cualquier pedido
3. En la barra lateral derecha, buscar el metabox **"Generar Factura SandCat"**
4. Hacer clic en **"Generar Factura"**

### Estados de factura

- **Sin factura**: Muestra botón "Generar Factura"
- **Con factura**: Muestra información de la factura existente y botón "Regenerar Factura PDF"

### Archivos generados

Los PDF se guardan en:
```
/wp-content/uploads/sandcat-invoices/factura_[NUMERO]_[ORDER_ID].pdf
```

## Funcionalidades técnicas

### Conexión a base de datos

El plugin se conecta a la tabla `configuracion` para:
- Obtener el `SERIE_NUMERO_FACTURA` actual
- Incrementar el número después de generar la factura

También usa la tabla `facturas` para:
- Verificar si ya existe factura para el pedido
- Guardar información de la nueva factura

### Extracción de datos del pedido

Usa las funciones nativas de WooCommerce:
- `$order->get_billing_first_name()`
- `$order->get_billing_last_name()`
- `$order->get_billing_email()`
- `$order->get_items()` para productos
- `$order->get_meta('_billing_dni')` para campos personalizados

### Generación de PDF

- **Motor**: mPDF 8.2+
- **Formato**: Letter
- **Codificación**: UTF-8
- **Template**: Basado en el sistema existente de facturación

## Estructura de archivos

```
plugin/
├── sandycat-invoice-generator.php    # Archivo principal del plugin
├── assets/
│   └── admin.js                     # JavaScript para admin
├── composer.json                    # Dependencias de Composer
└── README.md                        # Este archivo
```

## Hooks y filtros disponibles

### Actions

- `sandcat_invoice_before_generate`: Se ejecuta antes de generar la factura
- `sandcat_invoice_after_generate`: Se ejecuta después de generar la factura
- `sandcat_invoice_pdf_created`: Se ejecuta cuando se crea el PDF

### Filters

- `sandcat_invoice_pdf_html`: Permite modificar el HTML del PDF
- `sandcat_invoice_filename`: Permite cambiar el nombre del archivo PDF
- `sandcat_invoice_mpdf_config`: Permite modificar la configuración de mPDF

## Personalización

### Modificar template del PDF

Para personalizar el template, edita los métodos en la clase `SandCatInvoiceGenerator`:

- `get_pdf_css()`: Estilos CSS del PDF
- `get_company_header()`: Header de la empresa
- `get_customer_info()`: Información del cliente
- `get_products_table()`: Tabla de productos
- `get_totals_section()`: Sección de totales

### Agregar campos personalizados

Para agregar campos del pedido al PDF:

```php
// En extract_order_data()
'campo_personalizado' => $order->get_meta('_campo_personalizado'),

// En get_customer_info()
if (!empty($data['campo_personalizado'])) {
    $html .= '<tr><td><strong>Campo:</strong></td><td>' . htmlspecialchars($data['campo_personalizado']) . '</td></tr>';
}
```

## Troubleshooting

### Error: "mPDF no está disponible"

Ejecutar:
```bash
composer install
```

### Error: "Error conectando a base de datos de ventas"

Verificar:
- Credenciales de base de datos
- Que MySQL esté ejecutándose
- Que la base de datos `ventassc` exista

### Error: "Permisos insuficientes"

Verificar que el usuario tenga el capability `edit_shop_orders`.

### PDF no se genera

Verificar:
- Permisos de escritura en `/wp-content/uploads/`
- Que la carpeta `sandcat-invoices/` se pueda crear
- Logs de error de WordPress

## Logs

El plugin registra eventos en:
- Error log de WordPress
- Error log de PHP

Para habilitar logs detallados, agregar en `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Soporte

Para soporte técnico:
- Revisar logs de error
- Verificar requisitos del sistema
- Contactar al equipo de desarrollo SandCat

## Changelog

### v1.0.0
- Versión inicial
- Integración con WooCommerce
- Generación de PDF con mPDF
- Conexión a base de datos de ventas
- Numeración automática de facturas
