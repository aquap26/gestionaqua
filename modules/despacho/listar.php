<?php
// modules/despacho/listar.php
$db = Database::conectar();

$filtro_estado = filter_input(INPUT_GET, 'estado', FILTER_DEFAULT) ?? 'todos';
$filtro_zona   = filter_input(INPUT_GET, 'zona_id', FILTER_VALIDATE_INT) ?? 0;

try {
    // 1. Métricas clave de despacho
    $stmt_stats = $db->query("SELECT 
        COUNT(CASE WHEN estado IN ('pendiente', 'confirmado') THEN 1 END) as por_despachar,
        COUNT(CASE WHEN estado = 'preparando' THEN 1 END) as en_preparacion,
        COUNT(CASE WHEN estado = 'en_transito' THEN 1 END) as en_transito,
        COUNT(CASE WHEN estado = 'entregado' AND DATE(COALESCE(fecha_entrega_real, fecha_pedido)) = CURDATE() THEN 1 END) as entregados_hoy,
        (SELECT COUNT(DISTINCT transportista_id) FROM asignacion_transportistas WHERE activo = 1) as choferes_activos
    FROM pedidos");
    $stats = $stmt_stats->fetch() ?: [
        'por_despachar' => 0, 'en_preparacion' => 0, 'en_transito' => 0, 'entregados_hoy' => 0, 'choferes_activos' => 0
    ];

    // 2. Obtener lista de zonas activas para el filtro
    $zonas = $db->query("SELECT id, nombre_zona FROM zonas WHERE activo = 1 ORDER BY nombre_zona ASC")->fetchAll();

    // 3. Obtener transportistas disponibles
    $transportistas = $db->query("SELECT id, nombre, apellido, rol FROM usuarios WHERE activo = 1 AND rol IN ('transportista', 'administrador') ORDER BY nombre ASC")->fetchAll();

    // 4. Mapeo de asignaciones chofer-zona para recomendaciones inteligentes
    $asignaciones_raw = $db->query("SELECT transportista_id, zona_id FROM asignacion_transportistas WHERE activo = 1")->fetchAll();
    $choferes_por_zona = [];
    foreach ($asignaciones_raw as $asig) {
        $choferes_por_zona[$asig['zona_id']][] = $asig['transportista_id'];
    }

    // 5. Consulta de pedidos con cliente, zona, transportista y detalle de productos
    $sql_pedidos = "SELECT 
        p.id,
        p.numero_pedido,
        p.cliente_id,
        CONCAT(u.nombre, ' ', u.apellido) AS cliente_nombre,
        u.telefono AS cliente_telefono,
        p.transportista_id,
        CONCAT(t.nombre, ' ', t.apellido) AS transportista_nombre,
        p.zona_id,
        z.nombre_zona,
        p.estado,
        p.fecha_pedido,
        p.fecha_entrega_estimada,
        p.fecha_entrega_real,
        p.direccion_entrega,
        p.total_usd,
        p.total_bs,
        p.metodo_pago,
        p.observaciones,
        (
            SELECT GROUP_CONCAT(CONCAT(dp.cantidad, 'x ', pr.nombre) SEPARATOR ' | ')
            FROM detalle_pedidos dp
            JOIN productos pr ON dp.producto_id = pr.id
            WHERE dp.pedido_id = p.id
        ) as resumen_productos
    FROM pedidos p
    JOIN usuarios u ON p.cliente_id = u.id
    LEFT JOIN usuarios t ON p.transportista_id = t.id
    LEFT JOIN zonas z ON p.zona_id = z.id
    WHERE 1=1";

    $params = [];
    if ($filtro_estado !== 'todos') {
        if ($filtro_estado === 'pendientes') {
            $sql_pedidos .= " AND p.estado IN ('pendiente', 'confirmado')";
        } else {
            $sql_pedidos .= " AND p.estado = :estado";
            $params[':estado'] = $filtro_estado;
        }
    }
    if ($filtro_zona > 0) {
        $sql_pedidos .= " AND p.zona_id = :zona_id";
        $params[':zona_id'] = $filtro_zona;
    }

    $sql_pedidos .= " ORDER BY 
        CASE 
            WHEN p.estado = 'en_transito' THEN 1
            WHEN p.estado = 'preparando' THEN 2
            WHEN p.estado IN ('pendiente', 'confirmado') THEN 3
            ELSE 4
        END, p.id DESC LIMIT 100";

    $stmt_pedidos = $db->prepare($sql_pedidos);
    $stmt_pedidos->execute($params);
    $pedidos = $stmt_pedidos->fetchAll();

} catch (PDOException $e) {
    error_log("Error en módulo despacho listar: " . $e->getMessage());
    $pedidos = [];
    $zonas = [];
    $transportistas = [];
    $stats = ['por_despachar' => 0, 'en_preparacion' => 0, 'en_transito' => 0, 'entregados_hoy' => 0, 'choferes_activos' => 0];
}
?>

<div class="space-y-6">

    <!-- KPI Header Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Por Despachar -->
        <a href="index.php?page=despacho&estado=pendientes" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3 hover:border-amber-400 dark:hover:border-amber-500 transition-all">
            <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                <i data-lucide="package" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Por Despachar</p>
                <p id="kpi-por-despachar" class="text-2xl font-bold text-amber-600 dark:text-amber-400"><?= (int)$stats['por_despachar']; ?></p>
            </div>
        </a>

        <!-- En Preparación -->
        <a href="index.php?page=despacho&estado=preparando" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3 hover:border-blue-400 dark:hover:border-blue-500 transition-all">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                <i data-lucide="boxes" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">En Preparación</p>
                <p id="kpi-en-preparacion" class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= (int)$stats['en_preparacion']; ?></p>
            </div>
        </a>

        <!-- En Tránsito / En Calle -->
        <a href="index.php?page=despacho&estado=en_transito" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3 hover:border-purple-400 dark:hover:border-purple-500 transition-all">
            <div class="p-3 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg">
                <i data-lucide="truck" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">En Ruta / Calle</p>
                <p id="kpi-en-transito" class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?= (int)$stats['en_transito']; ?></p>
            </div>
        </a>

        <!-- Entregados Hoy -->
        <a href="index.php?page=despacho&estado=entregado" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3 hover:border-emerald-400 dark:hover:border-emerald-500 transition-all">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Entregados Hoy</p>
                <p id="kpi-entregados-hoy" class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= (int)$stats['entregados_hoy']; ?></p>
            </div>
        </a>
    </div>

    <!-- Barra de Navegación del Módulo y Filtros -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        
        <!-- Pestañas y Enlaces Directos -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="index.php?page=despacho" class="px-3.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold shadow-sm flex items-center space-x-1.5">
                <i data-lucide="truck" class="w-4 h-4"></i>
                <span>Tablero de Despacho</span>
            </a>
            <a href="index.php?page=despacho_asignar" class="px-3.5 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium transition-all flex items-center space-x-1.5">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span>Asignar Rutas a Choferes</span>
            </a>
            <a href="index.php?page=zonas" class="px-3.5 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium transition-all flex items-center space-x-1.5">
                <i data-lucide="map-pin" class="w-4 h-4"></i>
                <span>Zonas de Reparto</span>
            </a>
            <a href="index.php?page=chat" class="px-3.5 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-medium transition-all flex items-center space-x-1.5">
                <i data-lucide="message-square" class="w-4 h-4"></i>
                <span>Chat de Ruta</span>
            </a>

            <!-- Indicador en Vivo -->
            <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg text-xs font-semibold border border-emerald-200 dark:border-emerald-800/60 shadow-sm ml-1" title="Sincronización en tiempo real cada 3 segundos">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>En Vivo</span>
            </div>
        </div>

        <!-- Filtros Rápidos -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Estado -->
            <select onchange="aplicarFiltros(this.value, '<?= $filtro_zona; ?>')" class="p-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : ''; ?>>Estado: Todos</option>
                <option value="pendientes" <?= $filtro_estado === 'pendientes' ? 'selected' : ''; ?>>Por Despachar</option>
                <option value="preparando" <?= $filtro_estado === 'preparando' ? 'selected' : ''; ?>>En Preparación</option>
                <option value="en_transito" <?= $filtro_estado === 'en_transito' ? 'selected' : ''; ?>>En Tránsito / Calle</option>
                <option value="entregado" <?= $filtro_estado === 'entregado' ? 'selected' : ''; ?>>Entregados</option>
                <option value="cancelado" <?= $filtro_estado === 'cancelado' ? 'selected' : ''; ?>>Cancelados</option>
            </select>

            <!-- Selector de Zona -->
            <select onchange="aplicarFiltros('<?= $filtro_estado; ?>', this.value)" class="p-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">Todas las Zonas</option>
                <?php foreach ($zonas as $z): ?>
                    <option value="<?= $z['id']; ?>" <?= $filtro_zona == $z['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($z['nombre_zona']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Búsqueda en vivo -->
            <div class="relative">
                <i data-lucide="search" class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5"></i>
                <input type="text" id="buscar-despacho" onkeyup="filtrarDespachos()" placeholder="Buscar pedido, cliente..." class="pl-8 pr-3 py-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 w-44">
            </div>
        </div>
    </div>

    <!-- TABLA DE DESPACHOS -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabla-despacho">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Pedido / Fecha</th>
                        <th class="p-4">Cliente y Dirección</th>
                        <th class="p-4">Carga / Productos</th>
                        <th class="p-4 text-right">Total ($ / Bs)</th>
                        <th class="p-4">Transportista Asignado</th>
                        <th class="p-4 text-center">Estado</th>
                        <th class="p-4 text-center">Acciones Logísticas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm" id="tbody-despacho">
                    <?php if (empty($pedidos)): ?>
                        <tr id="fila-vacia">
                            <td colspan="7" class="p-12 text-center text-gray-500 dark:text-gray-400">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <i data-lucide="package-check" class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600"></i>
                                    <p class="font-medium text-gray-700 dark:text-gray-300">No hay pedidos que coincidan con los filtros.</p>
                                    <p class="text-xs text-gray-400">Las nuevas ventas aparecerán automáticamente aquí para asignación y entrega.</p>
                                    <a href="index.php?page=pedidos_crear" class="inline-block mt-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg transition-all">Crear Venta / Pedido</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                            <tr id="fila-pedido-<?= $p['id']; ?>" data-pedido-id="<?= $p['id']; ?>" data-estado="<?= $p['estado']; ?>" data-transportista="<?= $p['transportista_id'] ?? ''; ?>" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors fila-despacho" data-texto="<?= htmlspecialchars(strtolower($p['numero_pedido'] . ' ' . $p['cliente_nombre'] . ' ' . ($p['nombre_zona'] ?? '') . ' ' . $p['direccion_entrega'])); ?>">
                                
                                <!-- Pedido y Fecha -->
                                <td class="p-4 align-top">
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs block">
                                        <?= htmlspecialchars($p['numero_pedido']); ?>
                                    </span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5">
                                        <?= date('d/m/Y h:i A', strtotime($p['fecha_pedido'])); ?>
                                    </span>
                                    <?php if (!empty($p['observaciones'])): ?>
                                        <span class="inline-block mt-1 text-[10px] bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-900 max-w-[150px] truncate" title="<?= htmlspecialchars($p['observaciones']); ?>">
                                            Obs: <?= htmlspecialchars($p['observaciones']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Cliente, Zona y Dirección -->
                                <td class="p-4 align-top max-w-xs">
                                    <p class="font-semibold text-gray-900 dark:text-white text-xs">
                                        <?= htmlspecialchars($p['cliente_nombre']); ?>
                                    </p>
                                    <?php if (!empty($p['cliente_telefono'])): ?>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                            <?= htmlspecialchars($p['cliente_telefono']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-1 flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 font-medium">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($p['nombre_zona'] ?? 'Sin zona asignada'); ?></span>
                                    </div>

                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5 line-clamp-2" title="<?= htmlspecialchars($p['direccion_entrega']); ?>">
                                        <?= htmlspecialchars($p['direccion_entrega']); ?>
                                    </p>
                                </td>

                                <!-- Carga / Productos -->
                                <td class="p-4 align-top max-w-xs">
                                    <p class="text-xs text-gray-800 dark:text-gray-200 font-medium line-clamp-3">
                                        <?= htmlspecialchars($p['resumen_productos'] ?? 'Sin detalles de carga'); ?>
                                    </p>
                                </td>

                                <!-- Total -->
                                <td class="p-4 align-top text-right font-mono">
                                    <p class="font-bold text-gray-900 dark:text-white text-xs">
                                        $<?= number_format($p['total_usd'], 2); ?>
                                    </p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <?= number_format($p['total_bs'], 2); ?> Bs
                                    </p>
                                    <span class="inline-block mt-0.5 text-[9px] uppercase px-1 py-0.2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                                        <?= htmlspecialchars($p['metodo_pago']); ?>
                                    </span>
                                </td>

                                <!-- Transportista Asignado -->
                                <td class="p-4 align-top celda-transportista">
                                    <div class="space-y-1.5">
                                        <select onchange="asignarTransportista(<?= $p['id']; ?>, this.value, <?= (int)($p['zona_id'] ?? 0); ?>)" class="w-full p-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                                            <option value="">-- Sin Chofer --</option>
                                            <?php 
                                             $choferes_recomendados = $choferes_por_zona[$p['zona_id']] ?? [];
                                            foreach ($transportistas as $t): 
                                                $es_recomendado = in_array($t['id'], $choferes_recomendados);
                                                $selected = ($p['transportista_id'] == $t['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $t['id']; ?>" <?= $selected; ?>>
                                                    <?= $es_recomendado ? '★ ' : ''; ?><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellido']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php if (!empty($p['transportista_id'])): ?>
                                            <a href="index.php?page=chat&contacto_id=<?= $p['transportista_id']; ?>" class="inline-flex items-center space-x-1 text-[11px] text-purple-600 hover:text-purple-700 dark:text-purple-400 hover:underline">
                                                <i data-lucide="message-square" class="w-3 h-3"></i>
                                                <span>Abrir Chat con Chofer</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="p-4 align-top text-center celda-estado">
                                    <?php 
                                    $estado = $p['estado'];
                                    $badgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                    $label = ucfirst($estado);

                                    if ($estado === 'pendiente') {
                                        $badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
                                        $label = 'Pendiente';
                                    } elseif ($estado === 'confirmado') {
                                        $badgeClass = 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400';
                                        $label = 'Confirmado';
                                    } elseif ($estado === 'preparando') {
                                        $badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
                                        $label = 'Preparando';
                                    } elseif ($estado === 'en_transito') {
                                        $badgeClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
                                        $label = 'En Tránsito';
                                    } elseif ($estado === 'entregado') {
                                        $badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
                                        $label = 'Entregado';
                                    } elseif ($estado === 'cancelado') {
                                        $badgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                                        $label = 'Cancelado';
                                    }
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass; ?>">
                                        <?= $label; ?>
                                    </span>

                                    <?php if ($estado === 'entregado' && !empty($p['fecha_entrega_real'])): ?>
                                        <span class="block text-[10px] text-gray-400 mt-1 font-mono">
                                            <?= date('d/m h:i A', strtotime($p['fecha_entrega_real'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones Logísticas de Flujo -->
                                <td class="p-4 align-top text-center celda-acciones">
                                    <div class="flex flex-col items-center space-y-1.5">
                                        <?php if (in_array($estado, ['pendiente', 'confirmado'])): ?>
                                            <button onclick="cambiarEstadoPedido(<?= $p['id']; ?>, 'preparando')" class="w-full px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300 rounded text-xs font-semibold transition-all">
                                                Preparar
                                            </button>
                                        <?php elseif ($estado === 'preparando'): ?>
                                            <button onclick="cambiarEstadoPedido(<?= $p['id']; ?>, 'en_transito')" class="w-full px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1">
                                                <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                                                <span>A Ruta</span>
                                            </button>
                                        <?php elseif ($estado === 'en_transito'): ?>
                                            <button onclick="cambiarEstadoPedido(<?= $p['id']; ?>, 'entregado')" class="w-full px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                <span>Entregar</span>
                                            </button>
                                        <?php elseif ($estado === 'entregado'): ?>
                                            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center justify-center space-x-1">
                                                <i data-lucide="check-check" class="w-4 h-4"></i>
                                                <span>Completado</span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!in_array($estado, ['entregado', 'cancelado'])): ?>
                                            <button onclick="cambiarEstadoPedido(<?= $p['id']; ?>, 'cancelado')" class="text-[11px] text-gray-400 hover:text-red-500 transition-all">
                                                Cancelar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    const filtroEstadoActual = '<?= $filtro_estado; ?>';
    const filtroZonaActual   = <?= (int)$filtro_zona; ?>;
    const transportistasData = <?= json_encode($transportistas, JSON_UNESCAPED_UNICODE); ?>;
    const choferesPorZonaData = <?= json_encode($choferes_por_zona, JSON_UNESCAPED_UNICODE); ?>;

    let pollingTablero = null;
    let enProcesoSync = false;

    document.addEventListener('DOMContentLoaded', () => {
        // Iniciar polling constante en vivo cada 3.5 segundos
        pollingTablero = setInterval(actualizarTableroEnVivo, 3500);
    });

    function aplicarFiltros(estado, zonaId) {
        window.location.href = `index.php?page=despacho&estado=${estado}&zona_id=${zonaId}`;
    }

    function filtrarDespachos() {
        const query = document.getElementById('buscar-despacho').value.toLowerCase();
        const filas = document.querySelectorAll('.fila-despacho');
        filas.forEach(f => {
            const texto = f.getAttribute('data-texto') || '';
            f.style.display = texto.includes(query) ? '' : 'none';
        });
    }

    function animarContador(id, nuevoValor) {
        const el = document.getElementById(id);
        if (!el) return;
        const actual = parseInt(el.innerText) || 0;
        const val = parseInt(nuevoValor) || 0;
        if (actual !== val) {
            el.innerText = val;
            el.classList.add('scale-125', 'transition-transform', 'duration-300');
            setTimeout(() => el.classList.remove('scale-125'), 350);
        }
    }

    function actualizarTableroEnVivo() {
        if (enProcesoSync) return;
        enProcesoSync = true;

        fetch(`modules/despacho/acciones.php?accion=obtener_tablero&estado=${encodeURIComponent(filtroEstadoActual)}&zona_id=${filtroZonaActual}`)
        .then(res => res.json())
        .then(data => {
            enProcesoSync = false;
            if (data.status !== 'success') return;

            // 1. Sincronizar contadores KPI
            if (data.stats) {
                animarContador('kpi-por-despachar', data.stats.por_despachar);
                animarContador('kpi-en-preparacion', data.stats.en_preparacion);
                animarContador('kpi-en-transito', data.stats.en_transito);
                animarContador('kpi-entregados-hoy', data.stats.entregados_hoy);
            }

            // 2. Sincronizar filas de la tabla
            if (data.pedidos) {
                sincronizarFilasPedidos(data.pedidos);
            }
        })
        .catch(err => {
            enProcesoSync = false;
            console.error("Error en sincronización en vivo:", err);
        });
    }

    function sincronizarFilasPedidos(pedidos) {
        const tbody = document.getElementById('tbody-despacho');
        if (!tbody) return;

        const searchQuery = (document.getElementById('buscar-despacho')?.value || '').toLowerCase();

        pedidos.forEach(p => {
            let fila = document.getElementById(`fila-pedido-${p.id}`);
            if (fila) {
                const estadoActual = fila.getAttribute('data-estado');
                if (estadoActual !== p.estado) {
                    // Actualizar estado del elemento
                    fila.setAttribute('data-estado', p.estado);
                    
                    // Actualizar celda de estado
                    const celdaEstado = fila.querySelector('.celda-estado');
                    if (celdaEstado) {
                        celdaEstado.innerHTML = generarHtmlEstado(p.estado, p.fecha_entrega_real);
                    }

                    // Actualizar celda de acciones
                    const celdaAcciones = fila.querySelector('.celda-acciones');
                    if (celdaAcciones) {
                        celdaAcciones.innerHTML = generarHtmlAcciones(p.id, p.estado);
                    }

                    // Flash sutil para indicar actualización en vivo
                    fila.classList.add('bg-emerald-50', 'dark:bg-emerald-950/40');
                    setTimeout(() => {
                        fila.classList.remove('bg-emerald-50', 'dark:bg-emerald-950/40');
                    }, 2500);

                    // Si hay un filtro estricto y ya no coincide, desvanecerlo
                    if (filtroEstadoActual !== 'todos') {
                        let coincide = false;
                        if (filtroEstadoActual === 'pendientes' && (p.estado === 'pendiente' || p.estado === 'confirmado')) coincide = true;
                        else if (filtroEstadoActual === p.estado) coincide = true;

                        if (!coincide) {
                            setTimeout(() => {
                                fila.style.transition = 'opacity 0.6s ease';
                                fila.style.opacity = '0';
                                setTimeout(() => fila.remove(), 600);
                            }, 1800);
                        }
                    }
                }
            } else {
                // Si es un pedido nuevo y cumple los filtros activos, agregarlo al inicio
                if (verificarFiltros(p)) {
                    const filaVacia = document.getElementById('fila-vacia');
                    if (filaVacia) filaVacia.remove();

                    const nuevaFilaHtml = generarFilaCompleta(p);
                    const temp = document.createElement('tbody');
                    temp.innerHTML = nuevaFilaHtml;
                    const nuevaFila = temp.firstElementChild;

                    const texto = (nuevaFila.getAttribute('data-texto') || '');
                    if (searchQuery && !texto.includes(searchQuery)) {
                        nuevaFila.style.display = 'none';
                    }

                    tbody.insertBefore(nuevaFila, tbody.firstChild);
                    nuevaFila.classList.add('bg-blue-50', 'dark:bg-blue-950/40');
                    setTimeout(() => nuevaFila.classList.remove('bg-blue-50', 'dark:bg-blue-950/40'), 3000);
                }
            }
        });

        if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    }

    function verificarFiltros(p) {
        if (filtroEstadoActual !== 'todos') {
            if (filtroEstadoActual === 'pendientes') {
                if (p.estado !== 'pendiente' && p.estado !== 'confirmado') return false;
            } else if (filtroEstadoActual !== p.estado) {
                return false;
            }
        }
        if (filtroZonaActual > 0 && parseInt(p.zona_id) !== filtroZonaActual) {
            return false;
        }
        return true;
    }

    function generarHtmlEstado(estado, fechaEntregaReal) {
        let badgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        let label = estado.charAt(0).toUpperCase() + estado.slice(1);

        if (estado === 'pendiente') {
            badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
            label = 'Pendiente';
        } else if (estado === 'confirmado') {
            badgeClass = 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400';
            label = 'Confirmado';
        } else if (estado === 'preparando') {
            badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
            label = 'Preparando';
        } else if (estado === 'en_transito') {
            badgeClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
            label = 'En Tránsito';
        } else if (estado === 'entregado') {
            badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
            label = 'Entregado';
        } else if (estado === 'cancelado') {
            badgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
            label = 'Cancelado';
        }

        let fechaHtml = '';
        if (estado === 'entregado' && fechaEntregaReal) {
            const f = new Date(fechaEntregaReal.replace(/-/g, '/'));
            const fStr = isNaN(f) ? fechaEntregaReal : f.toLocaleDateString([], {day:'2-digit', month:'2-digit'}) + ' ' + f.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            fechaHtml = `<span class="block text-[10px] text-gray-400 mt-1 font-mono">${fStr}</span>`;
        }

        return `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
                ${label}
            </span>
            ${fechaHtml}
        `;
    }

    function generarHtmlAcciones(pedidoId, estado) {
        let btnPrincipal = '';
        if (estado === 'pendiente' || estado === 'confirmado') {
            btnPrincipal = `<button onclick="cambiarEstadoPedido(${pedidoId}, 'preparando')" class="w-full px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300 rounded text-xs font-semibold transition-all">Preparar</button>`;
        } else if (estado === 'preparando') {
            btnPrincipal = `<button onclick="cambiarEstadoPedido(${pedidoId}, 'en_transito')" class="w-full px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1"><i data-lucide="truck" class="w-3.5 h-3.5"></i><span>A Ruta</span></button>`;
        } else if (estado === 'en_transito') {
            btnPrincipal = `<button onclick="cambiarEstadoPedido(${pedidoId}, 'entregado')" class="w-full px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1"><i data-lucide="check" class="w-3.5 h-3.5"></i><span>Entregar</span></button>`;
        } else if (estado === 'entregado') {
            btnPrincipal = `<span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center justify-center space-x-1"><i data-lucide="check-check" class="w-4 h-4"></i><span>Completado</span></span>`;
        }

        let btnCancelar = '';
        if (estado !== 'entregado' && estado !== 'cancelado') {
            btnCancelar = `<button onclick="cambiarEstadoPedido(${pedidoId}, 'cancelado')" class="text-[11px] text-gray-400 hover:text-red-500 transition-all">Cancelar</button>`;
        }

        return `
            <div class="flex flex-col items-center space-y-1.5">
                ${btnPrincipal}
                ${btnCancelar}
            </div>
        `;
    }

    function generarFilaCompleta(p) {
        const fechaObj = new Date(p.fecha_pedido.replace(/-/g, '/'));
        const fechaStr = isNaN(fechaObj) ? p.fecha_pedido : fechaObj.toLocaleDateString([], {day:'2-digit', month:'2-digit', year:'numeric'}) + ' ' + fechaObj.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        
        let selectOptions = '<option value="">-- Sin Chofer --</option>';
        const recomendados = choferesPorZonaData[p.zona_id] || [];
        transportistasData.forEach(t => {
            const esRec = recomendados.includes(parseInt(t.id));
            const sel = (p.transportista_id == t.id) ? 'selected' : '';
            selectOptions += `<option value="${t.id}" ${sel}>${esRec ? '★ ' : ''}${escapeHtml(t.nombre + ' ' + t.apellido)}</option>`;
        });

        const chatLink = p.transportista_id ? `
            <a href="index.php?page=chat&contacto_id=${p.transportista_id}" class="inline-flex items-center space-x-1 text-[11px] text-purple-600 hover:text-purple-700 dark:text-purple-400 hover:underline">
                <i data-lucide="message-square" class="w-3 h-3"></i>
                <span>Abrir Chat con Chofer</span>
            </a>
        ` : '';

        const obsHtml = p.observaciones ? `
            <span class="inline-block mt-1 text-[10px] bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-900 max-w-[150px] truncate" title="${escapeHtml(p.observaciones)}">
                Obs: ${escapeHtml(p.observaciones)}
            </span>
        ` : '';

        const textoBusqueda = escapeHtml((p.numero_pedido + ' ' + p.cliente_nombre + ' ' + (p.nombre_zona || '') + ' ' + (p.direccion_entrega || '')).toLowerCase());

        return `
            <tr id="fila-pedido-${p.id}" data-pedido-id="${p.id}" data-estado="${p.estado}" data-transportista="${p.transportista_id || ''}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors fila-despacho" data-texto="${textoBusqueda}">
                <td class="p-4 align-top">
                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs block">${escapeHtml(p.numero_pedido)}</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400 block mt-0.5">${fechaStr}</span>
                    ${obsHtml}
                </td>
                <td class="p-4 align-top max-w-xs">
                    <p class="font-semibold text-gray-900 dark:text-white text-xs">${escapeHtml(p.cliente_nombre)}</p>
                    ${p.cliente_telefono ? `<p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono">${escapeHtml(p.cliente_telefono)}</p>` : ''}
                    <div class="mt-1 flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 font-medium">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span class="truncate">${escapeHtml(p.nombre_zona || 'Sin zona asignada')}</span>
                    </div>
                    <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5 line-clamp-2" title="${escapeHtml(p.direccion_entrega)}">
                        ${escapeHtml(p.direccion_entrega || '')}
                    </p>
                </td>
                <td class="p-4 align-top max-w-xs">
                    <p class="text-xs text-gray-800 dark:text-gray-200 font-medium line-clamp-3">${escapeHtml(p.resumen_productos || 'Sin detalles de carga')}</p>
                </td>
                <td class="p-4 align-top text-right font-mono">
                    <p class="font-bold text-gray-900 dark:text-white text-xs">$${parseFloat(p.total_usd).toFixed(2)}</p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">${parseFloat(p.total_bs).toFixed(2)} Bs</p>
                    <span class="inline-block mt-0.5 text-[9px] uppercase px-1 py-0.2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                        ${escapeHtml(p.metodo_pago)}
                    </span>
                </td>
                <td class="p-4 align-top celda-transportista">
                    <div class="space-y-1.5">
                        <select onchange="asignarTransportista(${p.id}, this.value, ${parseInt(p.zona_id) || 0})" class="w-full p-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                            ${selectOptions}
                        </select>
                        ${chatLink}
                    </div>
                </td>
                <td class="p-4 align-top text-center celda-estado">
                    ${generarHtmlEstado(p.estado, p.fecha_entrega_real)}
                </td>
                <td class="p-4 align-top text-center celda-acciones">
                    ${generarHtmlAcciones(p.id, p.estado)}
                </td>
            </tr>
        `;
    }

    function asignarTransportista(pedidoId, transportistaId, zonaId) {
        if (!transportistaId) return;

        fetch('modules/despacho/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=asignar_pedido&pedido_id=${pedidoId}&transportista_id=${transportistaId}&zona_id=${zonaId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                actualizarTableroEnVivo();
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error al asignar transportista.");
        });
    }

    function cambiarEstadoPedido(pedidoId, nuevoEstado) {
        const confirmMsg = nuevoEstado === 'entregado'
            ? '¿Confirmar entrega del pedido? Se registrará la fecha y hora de entrega real.'
            : (nuevoEstado === 'cancelado' ? '¿Está seguro de anular este despacho?' : null);

        if (confirmMsg && !confirm(confirmMsg)) return;

        fetch('modules/despacho/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=cambiar_estado&pedido_id=${pedidoId}&nuevo_estado=${nuevoEstado}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Actualizar de inmediato en el DOM
                const fila = document.getElementById(`fila-pedido-${pedidoId}`);
                if (fila) {
                    fila.setAttribute('data-estado', nuevoEstado);
                    const celdaEstado = fila.querySelector('.celda-estado');
                    if (celdaEstado) {
                        const ahora = new Date();
                        const fStr = ahora.toLocaleDateString([], {day:'2-digit', month:'2-digit'}) + ' ' + ahora.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                        celdaEstado.innerHTML = generarHtmlEstado(nuevoEstado, fStr);
                    }
                    const celdaAcciones = fila.querySelector('.celda-acciones');
                    if (celdaAcciones) {
                        celdaAcciones.innerHTML = generarHtmlAcciones(pedidoId, nuevoEstado);
                    }
                    fila.classList.add('bg-emerald-50', 'dark:bg-emerald-950/40');
                    setTimeout(() => fila.classList.remove('bg-emerald-50', 'dark:bg-emerald-950/40'), 2500);
                }
                // Sincronizar tablero y contadores en vivo
                actualizarTableroEnVivo();
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error al actualizar estado del pedido.");
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
</script>
