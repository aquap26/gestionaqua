<?php
// layout.php - Componente Maestro de Interfaz Gráfica Simétrica
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Consulta en caliente de la empresa (Tasa, Nombre Comercial y Logo) desde la BD
$tasa_del_dia   = 244.00; // Valor por defecto
$nombre_empresa = 'AQUA_DB'; 
$logo_empresa   = ''; // Ruta a la imagen del logo

try {
    if (class_exists('Database')) {
        $db_layout = Database::conectar();
        $stmt_empresa = $db_layout->query("SELECT nombre_comercial, logo_url, tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
        $empresa_layout = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa_layout) {
            if (!empty($empresa_layout['tasa_cambio_usd'])) {
                $tasa_del_dia = (float)$empresa_layout['tasa_cambio_usd'];
            }
            if (!empty($empresa_layout['nombre_comercial'])) {
                $nombre_empresa = $empresa_layout['nombre_comercial'];
            }
            if (!empty($empresa_layout['logo_url'])) {
                $logo_empresa = $empresa_layout['logo_url'];
            }
        }
    }
} catch (Exception $e) {
    // Si la conexión falla, se conservan los valores por defecto
}

// Datos de sesión activa
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador Sistema';
$usuario_rol    = strtolower($_SESSION['usuario_rol'] ?? $_SESSION['rol'] ?? 'administrador');
$inicial_user   = strtoupper(substr($usuario_nombre, 0, 1));
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Sistema'); ?> - <?= htmlspecialchars($nombre_empresa); ?></title>

    <!-- Tailwind CSS y CDN Lucide Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Script Anti-Parpadeo (Dark Mode) -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="h-full bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 font-sans transition-colors duration-200">

<div class="flex h-screen overflow-hidden">

    <!-- ==================== SIDEBAR LATERAL ==================== -->
    <aside class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col z-20 transition-all duration-300">
        
        <!-- Logo Corporativo Dinámico -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200 dark:border-gray-700 bg-blue-600 dark:bg-blue-800 text-white">
            <a href="index.php?page=dashboard" class="flex items-center space-x-2.5 font-bold tracking-wide">
                <?php if (!empty($logo_empresa) && file_exists($logo_empresa)): ?>
                    <img src="<?= htmlspecialchars($logo_empresa); ?>" alt="Logo" class="w-8 h-8 object-contain rounded-lg bg-white/10 p-1">
                <?php else: ?>
                    <i data-lucide="droplet" class="w-6 h-6"></i>
                <?php endif; ?>
                
                <span class="truncate text-sm font-extrabold uppercase tracking-wider max-w-[170px]" title="<?= htmlspecialchars($nombre_empresa); ?>">
                    <?= htmlspecialchars($nombre_empresa); ?>
                </span>
            </a>
        </div>

        <!-- Menú de Navegación -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-2">
            
            <!-- Inventario -->
            <a href="index.php?page=inventario" class="flex items-center space-x-3 p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                <i data-lucide="package" class="w-5 h-5 text-gray-400"></i>
                <span class="text-sm font-medium">Panel / Inventario</span>
            </a>

            <!-- Pedidos y Ventas -->
            <div>
                <button onclick="toggleSubmenu('sub-ventas')" class="w-full flex items-center justify-between p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group transition-all">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="shopping-cart" class="w-5 h-5 text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm font-medium">Pedidos y Ventas</span>
                    </div>
                    <i data-lucide="chevron-down" id="arrow-sub-ventas" class="w-4 h-4 transition-transform duration-200"></i>
                </button>
                <div id="sub-ventas" class="hidden pl-9 mt-1 space-y-1">
                    <a href="index.php?page=pedidos" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Ver Pedidos</a>
                    <a href="index.php?page=pedidos_crear" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Crear Nuevo Pedido</a>
                </div>
            </div>

            <!-- Despacho Logístico -->
            <div>
                <button onclick="toggleSubmenu('sub-despacho')" class="w-full flex items-center justify-between p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group transition-all">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="truck" class="w-5 h-5 text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm font-medium">Despacho</span>
                    </div>
                    <i data-lucide="chevron-down" id="arrow-sub-despacho" class="w-4 h-4 transition-transform duration-200"></i>
                </button>
                <div id="sub-despacho" class="hidden pl-9 mt-1 space-y-1">
                    <a href="index.php?page=despacho" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Tablero de Envíos</a>
                    <a href="index.php?page=despacho_asignar" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Asignar Choferes</a>
                </div>
            </div>

            <!-- Zonas de Entrega -->
            <div>
                <button onclick="toggleSubmenu('sub-zonas')" class="w-full flex items-center justify-between p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group transition-all">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="map-pin" class="w-5 h-5 text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm font-medium">Zonas de Entrega</span>
                    </div>
                    <i data-lucide="chevron-down" id="arrow-sub-zonas" class="w-4 h-4 transition-transform duration-200"></i>
                </button>
                <div id="sub-zonas" class="hidden pl-9 mt-1 space-y-1">
                    <a href="index.php?page=zonas" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Ver Zonas</a>
                    <a href="index.php?page=zonas_crear" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Nueva Zona</a>
                </div>
            </div>

            <!-- Chat de Ruta -->
            <a href="index.php?page=chat" class="flex items-center space-x-3 p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                <i data-lucide="message-square" class="w-5 h-5 text-gray-400"></i>
                <span class="text-sm font-medium">Chat de Ruta</span>
            </a>

            <!-- Gestión de Usuarios -->
            <div>
                <button onclick="toggleSubmenu('sub-usuarios')" class="w-full flex items-center justify-between p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 group transition-all">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="users" class="w-5 h-5 text-gray-400 group-hover:text-blue-500"></i>
                        <span class="text-sm font-medium">Gestión Usuarios</span>
                    </div>
                    <i data-lucide="chevron-down" id="arrow-sub-usuarios" class="w-4 h-4 transition-transform duration-200"></i>
                </button>
                <div id="sub-usuarios" class="hidden pl-9 mt-1 space-y-1">
                    <a href="index.php?page=usuarios" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Lista de Personal</a>
                    <a href="index.php?page=usuarios_crear" class="block p-2 text-xs rounded-md text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400">Registrar Nuevo</a>
                </div>
            </div>

            <!-- Auditoría -->
            <a href="index.php?page=auditoria" class="flex items-center space-x-3 p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                <i data-lucide="shield-check" class="w-5 h-5 text-gray-400"></i>
                <span class="text-sm font-medium">Bitácora Auditoría</span>
            </a>

            <!-- 🛡️ MÓDULO CONFIGURACIÓN EMPRESA (SÓLO ADMIN) -->
            <?php if ($usuario_rol === 'administrador'): ?>
                <a href="index.php?page=empresa" class="flex items-center space-x-3 p-2 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-all font-semibold">
                    <i data-lucide="building-2" class="w-5 h-5 text-amber-500"></i>
                    <span class="text-sm">Configuración Empresa</span>
                </a>
            <?php endif; ?>

        </nav>

        <!-- Pie de Sidebar -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm uppercase shadow-sm">
                    <?= $inicial_user; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate text-gray-800 dark:text-white"><?= htmlspecialchars($usuario_nombre); ?></p>
                    <p class="text-[10px] text-gray-400 truncate capitalize"><?= htmlspecialchars($usuario_rol); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- ==================== CONTENEDOR PRINCIPAL ==================== -->
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        
        <!-- Header Superior -->
        <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-6 z-10">
            <div class="flex items-center space-x-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
                    <?= htmlspecialchars($page_title ?? 'Dashboard'); ?>
                </h2>
                
                <button onclick="abrirModalTasa(<?= $tasa_del_dia; ?>)" class="inline-flex items-center space-x-1.5 px-3 py-1 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-amber-700 dark:text-amber-400 rounded-lg text-xs font-mono font-bold hover:scale-105 transition-all shadow-sm" title="Clic para modificar tasa">
                    <i data-lucide="trending-up" class="w-3.5 h-3.5"></i>
                    <span>Tasa: <?= number_format($tasa_del_dia, 2); ?> Bs</span>
                </button>
            </div>

            <!-- Botones de Tema y Logout -->
            <div class="flex items-center space-x-3">
                <button id="theme-toggle" class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all" title="Cambiar Tema">
                    <i data-lucide="sun" id="theme-toggle-light-icon" class="w-5 h-5 hidden"></i>
                    <i data-lucide="moon" id="theme-toggle-dark-icon" class="w-5 h-5 hidden"></i>
                </button>
                <a href="logout.php" class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all" title="Cerrar Sesión">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </header>

        <!-- CONTENIDO DINÁMICO -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto p-6 bg-gray-50 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto">
                <?php 
                if (isset($content_view) && file_exists($content_view)) {
                    include $content_view;
                } else {
                    echo '<div class="p-4 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg">Error: La vista requerida no existe.</div>';
                }
                ?>
            </div>
        </main>

    </div>
</div>

<!-- ==================== MODAL DE TASA CAMBIARIA ==================== -->
<div id="modal-tasa" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-all">
    <div class="bg-white dark:bg-gray-800 w-full max-w-xs rounded-xl border border-gray-200 dark:border-gray-700 shadow-2xl p-5 space-y-4">
        <div class="flex justify-between items-center border-b pb-3 border-gray-100 dark:border-gray-700">
            <h4 class="font-bold text-gray-800 dark:text-white text-xs uppercase tracking-wider">Tasa Oficial Diaria</h4>
            <button onclick="cerrarModalTasa()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="form-actualizar-tasa" class="space-y-3">
            <div>
                <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Valor en Bolívares (1 USD)</label>
                <input type="number" step="0.0001" min="0.01" id="input_tasa_cambio" name="tasa_cambio" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-center font-mono font-bold text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs rounded-lg transition-all shadow-md">
                Actualizar Moneda
            </button>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT GLOBAL ==================== -->
<script>
    lucide.createIcons();

    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        const arrow = document.getElementById('arrow-' + id);
        if (submenu) {
            submenu.classList.toggle('hidden');
            if (arrow) {
                arrow.style.transform = submenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        }
    }

    const themeToggleBtn = document.getElementById('theme-toggle');
    const lightIcon = document.getElementById('theme-toggle-light-icon');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');

    function updateThemeIcons() {
        if (document.documentElement.classList.contains('dark')) {
            lightIcon.classList.remove('hidden');
            darkIcon.classList.add('hidden');
        } else {
            lightIcon.classList.add('hidden');
            darkIcon.classList.remove('hidden');
        }
    }
    updateThemeIcons();

    themeToggleBtn.addEventListener('click', () => {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
        updateThemeIcons();
    });

    const modalTasa = document.getElementById('modal-tasa');
    function abrirModalTasa(valorActual) {
        document.getElementById('input_tasa_cambio').value = valorActual;
        modalTasa.classList.remove('hidden');
    }
    function cerrarModalTasa() { 
        modalTasa.classList.add('hidden'); 
    }

    document.getElementById('form-actualizar-tasa').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('modules/configuracion/actualizar_tasa.php', {
            method: 'POST',
            body: new URLSearchParams(new FormData(this))
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') { 
                location.reload(); 
            }
        })
        .catch(err => alert('Error al procesar la solicitud: ' + err));
    });
</script>

</body>
</html>
