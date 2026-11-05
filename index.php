<?php
// 1. Iniciar sesión y seguridad de acceso
session_start();

// Si el usuario no ha iniciado sesión, redirigir inmediatamente al login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// 2. Conexión a la Base de Datos
require_once 'config/conexion.php';

// Capturar el rol del usuario (Soporta distintas variables de sesión)
$rol_usuario = strtolower($_SESSION['usuario_rol'] ?? $_SESSION['rol'] ?? '');

// 3. Capturar la página solicitada
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 4. Lista blanca de módulos permitidos
$allowed_pages = [
    'dashboard'        => 'modules/inventario/listar.php', 
    'inventario'       => 'modules/inventario/listar.php',
    'inventario_crear' => 'modules/inventario/crear_producto.php',
    'empresa'          => 'modules/empresa/listar.php',
    'pedidos'          => 'modules/pedidos/listar.php',
    'pedidos_crear'    => 'modules/pedidos/crear.php',
    'despacho'         => 'modules/despacho/listar.php',
    'despacho_asignar' => 'modules/despacho/asignar.php',
    'usuarios'         => 'modules/usuarios/listar.php',
    'usuarios_crear'   => 'modules/usuarios/crear.php',
    'usuarios_editar'  => 'modules/usuarios/editar.php',
    'zonas'            => 'modules/zonas/listar.php',
    'zonas_crear'      => 'modules/zonas/crear.php',
    'zonas_editar'     => 'modules/zonas/editar.php',
    'chat'             => 'modules/mensajeria/chat.php',
    'auditoria'        => 'modules/auditoria/listar.php'
];

// 5. Mapeo simétrico de títulos legibles para el Header/Layout
$page_titles = [
    'dashboard'        => 'Panel / Inventario',
    'inventario'       => 'Panel / Inventario',
    'inventario_crear' => 'Registrar Nuevo Producto',
    'empresa'          => 'Configuración de la Empresa',
    'pedidos'          => 'Lista de Pedidos',
    'pedidos_crear'    => 'Crear Nuevo Pedido',
    'despacho'         => 'Tablero de Despacho Logístico',
    'despacho_asignar' => 'Asignar Rutas a Choferes',
    'usuarios'         => 'Gestión de Usuarios',
    'usuarios_crear'   => 'Registrar Usuario',
    'usuarios_editar'  => 'Editar Usuario',
    'zonas'            => 'Zonas de Entrega',
    'zonas_crear'      => 'Registrar Nueva Zona',
    'zonas_editar'     => 'Editar Zona de Entrega',
    'chat'             => 'Chat de Ruta Logístico',
    'auditoria'        => 'Bitácora de Auditoría'
];

// 6. Validar ruta solicitada y permisos
if (!array_key_exists($page, $allowed_pages)) {
    $content_view = 'modules/inventario/listar.php';
    $page_title   = 'Panel / Inventario';
} else {
    // 🛡️ CONTROL DE ACCESO EXCLUSIVO PARA ADMIN EN 'EMPRESA'
    if ($page === 'empresa' && $rol_usuario !== 'administrador') {
        $content_view = 'modules/inventario/listar.php'; // Redirige al inventario si no es admin
        $page_title   = 'Panel / Inventario';
    } else {
        $content_view = $allowed_pages[$page];
        $page_title   = $page_titles[$page];
    }
}

// 7. Cargar el layout simétrico inyectando la vista
include 'includes/layout.php';
?>
