<?php
// modules/zonas/listar.php
$db = Database::conectar();

try {
    // 1. Estadísticas generales de zonas
    $stats_query = "SELECT 
        COUNT(*) as total_zonas,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as zonas_activas,
        (SELECT COUNT(DISTINCT transportista_id) FROM asignacion_transportistas WHERE activo = 1) as choferes_asignados,
        (SELECT COUNT(*) FROM pedidos WHERE estado IN ('pendiente', 'confirmado', 'preparando', 'en_transito')) as pedidos_en_curso
    FROM zonas";
    $stats = $db->query($stats_query)->fetch() ?: [
        'total_zonas' => 0, 'zonas_activas' => 0, 'choferes_asignados' => 0, 'pedidos_en_curso' => 0
    ];

    // 2. Consulta de zonas con conteo de transportistas y pedidos activos
    $query_zonas = "SELECT 
        z.id,
        z.nombre_zona,
        z.codigo_postal,
        z.descripcion,
        z.activo,
        z.created_at,
        COUNT(DISTINCT CASE WHEN a.activo = 1 THEN a.transportista_id END) as transportistas_count,
        COUNT(DISTINCT CASE WHEN p.estado IN ('pendiente', 'confirmado', 'preparando', 'en_transito') THEN p.id END) as pedidos_activos_count
    FROM zonas z
    LEFT JOIN asignacion_transportistas a ON a.zona_id = z.id
    LEFT JOIN pedidos p ON p.zona_id = z.id
    GROUP BY z.id, z.nombre_zona, z.codigo_postal, z.descripcion, z.activo, z.created_at
    ORDER BY z.activo DESC, z.nombre_zona ASC";
    $zonas = $db->query($query_zonas)->fetchAll();

} catch (PDOException $e) {
    error_log("Error al listar zonas: " . $e->getMessage());
    $zonas = [];
    $stats = ['total_zonas' => 0, 'zonas_activas' => 0, 'choferes_asignados' => 0, 'pedidos_en_curso' => 0];
}
?>

<div class="space-y-6">

    <!-- KPI Header Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg">
                <i data-lucide="map-pin" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Total Zonas</p>
                <p class="text-xl font-bold text-gray-800 dark:text-white"><?= (int)$stats['total_zonas']; ?></p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Zonas Activas</p>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?= (int)$stats['zonas_activas']; ?></p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3">
            <div class="p-3 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg">
                <i data-lucide="truck" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Choferes Asignados</p>
                <p class="text-xl font-bold text-purple-600 dark:text-purple-400"><?= (int)$stats['choferes_asignados']; ?></p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-3">
            <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                <i data-lucide="package-search" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Pedidos en Curso</p>
                <p class="text-xl font-bold text-amber-600 dark:text-amber-400"><?= (int)$stats['pedidos_en_curso']; ?></p>
            </div>
        </div>
    </div>

    <!-- Barra de acciones y búsqueda -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="flex items-center space-x-3 flex-1 max-w-md">
            <div class="relative w-full">
                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-3"></i>
                <input type="text" id="buscar-zona" placeholder="Buscar zona o código postal..." onkeyup="filtrarZonas()" class="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 transition-all">
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <a href="index.php?page=despacho_asignar" class="inline-flex items-center space-x-2 px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-medium rounded-lg transition-all" title="Gestionar asignaciones chofer-zona">
                <i data-lucide="link-2" class="w-4 h-4"></i>
                <span>Asignar Choferes</span>
            </a>
            <a href="index.php?page=zonas_crear" class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-all shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Nueva Zona</span>
            </a>
        </div>
    </div>

    <!-- Tabla Simétrica de Zonas -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabla-zonas">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Nombre de Zona</th>
                        <th class="p-4">Código Postal</th>
                        <th class="p-4">Descripción y Cobertura</th>
                        <th class="p-4 text-center">Choferes</th>
                        <th class="p-4 text-center">Pedidos Activos</th>
                        <th class="p-4 text-center">Estado</th>
                        <th class="p-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($zonas)): ?>
                        <tr id="fila-sin-zonas">
                            <td colspan="7" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <i data-lucide="map" class="w-8 h-8 mx-auto text-gray-400"></i>
                                    <p class="font-medium text-gray-700 dark:text-gray-300">No hay zonas registradas todavía.</p>
                                    <p class="text-xs text-gray-400">Crea zonas de reparto para organizar los despachos de agua potable.</p>
                                    <a href="index.php?page=zonas_crear" class="inline-block mt-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg transition-all">Crear Primera Zona</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($zonas as $z): ?>
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors fila-zona" data-texto="<?= htmlspecialchars(strtolower($z['nombre_zona'] . ' ' . ($z['codigo_postal'] ?? '') . ' ' . ($z['descripcion'] ?? ''))); ?>">
                                <td class="p-4 font-semibold text-gray-900 dark:text-white">
                                    <div class="flex items-center space-x-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs">
                                            <i data-lucide="map-pin" class="w-4 h-4"></i>
                                        </div>
                                        <span><?= htmlspecialchars($z['nombre_zona']); ?></span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-xs text-gray-600 dark:text-gray-300">
                                    <?= !empty($z['codigo_postal']) ? htmlspecialchars($z['codigo_postal']) : '<span class="text-gray-400 italic">N/A</span>'; ?>
                                </td>
                                <td class="p-4 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate" title="<?= htmlspecialchars($z['descripcion'] ?? ''); ?>">
                                    <?= !empty($z['descripcion']) ? htmlspecialchars($z['descripcion']) : '<span class="text-gray-400 italic">Sin descripción</span>'; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $z['transportistas_count'] > 0 ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'; ?>">
                                        <i data-lucide="truck" class="w-3 h-3"></i>
                                        <span><?= (int)$z['transportistas_count']; ?></span>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $z['pedidos_activos_count'] > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-400'; ?>">
                                        <span><?= (int)$z['pedidos_activos_count']; ?></span>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if ((int)$z['activo'] === 1): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Activa</span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-1.5">
                                        <!-- Vincular chofer a esta zona -->
                                        <a href="index.php?page=despacho_asignar&zona_id=<?= $z['id']; ?>" class="p-1.5 text-gray-500 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/30 rounded transition-all" title="Asignar chofer a esta zona">
                                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                                        </a>
                                        <!-- Editar Zona -->
                                        <a href="index.php?page=zonas_editar&id=<?= $z['id']; ?>" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-all" title="Editar Zona">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </a>
                                        <!-- Toggle Activar/Desactivar -->
                                        <button onclick="toggleZona(<?= $z['id']; ?>, '<?= htmlspecialchars(addslashes($z['nombre_zona'])); ?>', <?= (int)$z['activo']; ?>)" class="p-1.5 <?= (int)$z['activo'] === 1 ? 'text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30' : 'text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'; ?> rounded transition-all" title="<?= (int)$z['activo'] === 1 ? 'Desactivar Zona' : 'Reactivar Zona'; ?>">
                                            <i data-lucide="<?= (int)$z['activo'] === 1 ? 'power-off' : 'power'; ?>" class="w-4 h-4"></i>
                                        </button>
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

    function filtrarZonas() {
        const query = document.getElementById('buscar-zona').value.toLowerCase();
        const filas = document.querySelectorAll('.fila-zona');
        filas.forEach(fila => {
            const texto = fila.getAttribute('data-texto');
            fila.style.display = texto.includes(query) ? '' : 'none';
        });
    }

    function toggleZona(id, nombre, estadoActual) {
        const accionTexto = estadoActual === 1 ? 'desactivar' : 'reactivar';
        if (!confirm(`¿Está seguro de que desea ${accionTexto} la zona "${nombre}"?`)) return;

        fetch('modules/zonas/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=toggle_activo&id=${id}`
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') { location.reload(); }
        })
        .catch(err => {
            console.error(err);
            alert("Error de conexión al cambiar estado de la zona.");
        });
    }
</script>
