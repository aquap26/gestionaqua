<?php
// modules/pedidos/listar.php
$db = Database::conectar();

try {
    // 1. Obtener la tasa de cambio actual
    $stmt_tasa = $db->query("SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
    $empresa = $stmt_tasa->fetch();
    $tasa_actual = $empresa ? (float)$empresa['tasa_cambio_usd'] : 244.00;

    // 2. Consulta enriquecida de pedidos con cliente, zona y transportista
    $query = "SELECT 
                p.id, 
                p.numero_pedido, 
                p.fecha_pedido, 
                p.total_usd, 
                p.total_bs, 
                p.estado,
                p.metodo_pago,
                CONCAT(u.nombre, ' ', u.apellido) AS cliente_nombre,
                z.nombre_zona,
                CONCAT(t.nombre, ' ', t.apellido) AS transportista_nombre
              FROM pedidos p
              JOIN usuarios u ON p.cliente_id = u.id
              LEFT JOIN zonas z ON p.zona_id = z.id
              LEFT JOIN usuarios t ON p.transportista_id = t.id
              ORDER BY p.id DESC";
    $stmt_pedidos = $db->query($query);
    $pedidos = $stmt_pedidos->fetchAll();

} catch (PDOException $e) {
    error_log("Error en listar pedidos: " . $e->getMessage());
    $pedidos = [];
    $tasa_actual = 244.00;
}
?>

<div class="space-y-6">
    <!-- Encabezado de sección con Botón de Acción Principal -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white">Historial de Pedidos y Ventas</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tasa de cambio monitoreada: <span class="font-semibold text-blue-600 dark:text-blue-400">1 USD = <?php echo number_format($tasa_actual, 2); ?> Bs.</span></p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="index.php?page=despacho" class="inline-flex items-center justify-center space-x-2 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold rounded-lg transition-all">
                <i data-lucide="truck" class="w-4 h-4"></i>
                <span>Tablero Despacho</span>
            </a>
            <a href="index.php?page=pedidos_crear" class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-all shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Nuevo Pedido</span>
            </a>
        </div>
    </div>

    <!-- Tabla Simétrica de Pedidos -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-100/50 dark:bg-gray-700/30 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="p-4">Pedido / Fecha</th>
                        <th class="p-4">Cliente</th>
                        <th class="p-4">Zona de Entrega</th>
                        <th class="p-4">Transportista</th>
                        <th class="p-4 text-center">Estado</th>
                        <th class="p-4 text-right">Total ($ / Bs)</th>
                        <th class="p-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <?php if (empty($pedidos)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                No se han registrado pedidos en el sistema actualmente.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): 
                            $estado = $pedido['estado'];
                            $badgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                            if ($estado === 'pendiente') $badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
                            elseif ($estado === 'confirmado') $badgeClass = 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400';
                            elseif ($estado === 'preparando') $badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
                            elseif ($estado === 'en_transito') $badgeClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
                            elseif ($estado === 'entregado') $badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
                            elseif ($estado === 'cancelado') $badgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                        ?>
                            <tr id="fila-pedido-<?= $pedido['id']; ?>" data-estado="<?= $pedido['estado']; ?>" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/20 transition-colors">
                                <td class="p-4">
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs block">
                                        <?= htmlspecialchars($pedido['numero_pedido']); ?>
                                    </span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <?= date('d/m/Y h:i A', strtotime($pedido['fecha_pedido'])); ?>
                                    </span>
                                </td>
                                <td class="p-4 font-medium text-gray-900 dark:text-white text-xs">
                                    <?= htmlspecialchars($pedido['cliente_nombre']); ?>
                                </td>
                                <td class="p-4 text-xs text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center space-x-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-400"></i>
                                        <span><?= htmlspecialchars($pedido['nombre_zona'] ?? 'Sin zona'); ?></span>
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-gray-600 dark:text-gray-300 celda-transportista">
                                    <?= !empty($pedido['transportista_nombre']) ? htmlspecialchars($pedido['transportista_nombre']) : '<span class="text-gray-400 italic">No asignado</span>'; ?>
                                </td>
                                <td class="p-4 text-center celda-estado">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badgeClass; ?> capitalize">
                                        <?= $estado === 'en_transito' ? 'En Tránsito' : $estado; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right font-mono">
                                    <p class="font-bold text-gray-900 dark:text-white text-xs">$<?= number_format($pedido['total_usd'], 2); ?></p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400"><?= number_format($pedido['total_bs'], 2); ?> Bs</p>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-1.5">
                                        <a href="index.php?page=despacho" class="p-1.5 text-gray-500 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/30 rounded transition-all" title="Gestionar Despacho">
                                            <i data-lucide="truck" class="w-4 h-4"></i>
                                        </a>
                                        <button onclick="anularPedido(<?= $pedido['id']; ?>)" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 rounded transition-all" title="Anular Pedido">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
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

    // Sincronización en tiempo real de estados de pedidos cada 4 segundos
    setInterval(() => {
        fetch('modules/despacho/acciones.php?accion=obtener_tablero&estado=todos&zona_id=0')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.pedidos) {
                data.pedidos.forEach(p => {
                    const fila = document.getElementById(`fila-pedido-${p.id}`);
                    if (fila) {
                        const estadoActual = fila.getAttribute('data-estado');
                        if (estadoActual !== p.estado) {
                            fila.setAttribute('data-estado', p.estado);
                            const celdaEstado = fila.querySelector('.celda-estado');
                            if (celdaEstado) {
                                let badgeClass = 'bg-gray-100 text-gray-800';
                                if (p.estado === 'pendiente') badgeClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
                                else if (p.estado === 'confirmado') badgeClass = 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400';
                                else if (p.estado === 'preparando') badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
                                else if (p.estado === 'en_transito') badgeClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
                                else if (p.estado === 'entregado') badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400';
                                else if (p.estado === 'cancelado') badgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                                
                                const label = p.estado === 'en_transito' ? 'En Tránsito' : (p.estado.charAt(0).toUpperCase() + p.estado.slice(1));
                                celdaEstado.innerHTML = `<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">${label}</span>`;
                                
                                fila.classList.add('bg-emerald-50', 'dark:bg-emerald-950/40');
                                setTimeout(() => fila.classList.remove('bg-emerald-50', 'dark:bg-emerald-950/40'), 2500);
                            }
                        }
                    }
                });
            }
        })
        .catch(err => console.error("Error al sincronizar pedidos:", err));
    }, 4000);

    function anularPedido(id) {
        if (confirm(`¿Está seguro de que desea anular el pedido #${id}? Esta acción devolverá los productos al inventario disponible de forma automática y auditará el proceso.`)) {
            fetch('modules/pedidos/acciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=anular&pedido_id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') { location.reload(); }
            });
        }
    }
</script>
