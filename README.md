# Sistema de Ventas Sand&Cat

Sistema de gestión de ventas integrado con WooCommerce.

## Configuración

### 1. Configuración de Base de Datos

El sistema utiliza un archivo `.env` para la configuración de las bases de datos:

1. Copia el archivo `.env.example` como `.env`
2. Configura las credenciales de tus bases de datos:

```bash
cp .env.example .env
```

### 2. Estructura de Base de Datos

El sistema requiere dos bases de datos:

#### Base de Datos Principal (ventassc)
- **usuarios**: Usuarios del sistema
- **ventas**: Registro de ventas
- **detalle**: Detalles de las ventas
- **productos**: Catálogo de productos
- **facturas**: Control de facturación

#### Base de Datos WordPress (miau)
- Tablas estándar de WordPress/WooCommerce con prefijo `miau_`

### 3. Archivos de Configuración

- **config.php**: Configuración centralizada de conexiones
- **.env**: Variables de entorno (NO incluir en Git)
- **.env.example**: Plantilla de configuración
- **.gitignore**: Archivos excluidos del control de versiones

### 4. Estructura del Proyecto

```
ventas/
├── config.php          # Configuración centralizada
├── .env                 # Variables de entorno (local)
├── .env.example         # Plantilla de configuración
├── .gitignore          # Archivos excluidos
├── index.php           # Login principal
├── admin.php           # Panel de administración
├── ventas.php          # Panel de facturación
├── facturacion.php     # Login de facturación
└── ...                 # Otros archivos del sistema
```

### 5. Seguridad

- El archivo `.env` contiene información sensible y NO debe incluirse en el control de versiones
- Todas las conexiones de base de datos están centralizadas en `config.php`
- Se utiliza UTF-8 para el manejo de caracteres especiales

### 6. Instalación

1. Clona el repositorio
2. Copia `.env.example` como `.env`
3. Configura las credenciales de base de datos en `.env`
4. Crea las bases de datos necesarias
5. Importa la estructura de tablas (si existe un archivo SQL)

### 7. Uso

- **Login Principal**: `index.php` - Acceso al sistema de ventas
- **Login Facturación**: `facturacion.php` - Acceso al módulo de facturación WooCommerce
- **Panel Admin**: `admin.php` - Gestión de ventas locales
- **Panel Facturación**: `ventas.php` - Integración con WooCommerce

## Soporte

Para soporte técnico, contacta al equipo de desarrollo.
