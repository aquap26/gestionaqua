<?php
// modules/mensajeria/chat.php
$db = Database::conectar();

$usuario_actual_id = $_SESSION['usuario_id'] ?? 1;
$usuario_rol = strtolower($_SESSION['usuario_rol'] ?? $_SESSION['rol'] ?? 'administrador');

$contacto_inicial_id = filter_input(INPUT_GET, 'contacto_id', FILTER_VALIDATE_INT) 
                    ?? filter_input(INPUT_GET, 'transportista_id', FILTER_VALIDATE_INT) 
                    ?? 0;

$contactos = [];
$contacto_activo_info = null;

try {
    // Lista de contactos (usando parámetros únicos por restricción ATTR_EMULATE_PREPARES=false)
    $query = "SELECT 
        u.id,
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        u.rol,
        (
            SELECT z.nombre_zona 
            FROM asignacion_transportistas at 
            JOIN zonas z ON at.zona_id = z.id 
            WHERE at.transportista_id = u.id AND at.activo = 1 
            LIMIT 1
        ) as zona_nombre,
        (
            SELECT COUNT(*) 
            FROM pedidos p 
            WHERE p.transportista_id = u.id AND p.estado IN ('preparando', 'en_transito')
        ) as pedidos_en_ruta,
        (
            SELECT COUNT(*) 
            FROM mensajes_chat mc 
            WHERE mc.remitente_id = u.id AND mc.destinatario_id = :mi_id1 AND mc.leido = 0
        ) as no_leidos
    FROM usuarios u
    WHERE u.activo = 1 AND u.id != :mi_id2 AND u.rol IN ('transportista', 'administrador')
    ORDER BY (u.rol = 'administrador') DESC, no_leidos DESC, pedidos_en_ruta DESC, u.nombre ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([':mi_id1' => $usuario_actual_id, ':mi_id2' => $usuario_actual_id]);
    $contactos = $stmt->fetchAll();

    // Si no se pasó un ID por URL, tomar el primer contacto disponible
    if ($contacto_inicial_id === 0 && !empty($contactos)) {
        $contacto_inicial_id = (int)$contactos[0]['id'];
    }

    // Cargar información del contacto activo para precargar el encabezado directamente en PHP
    if ($contacto_inicial_id > 0) {
        $stmt_info = $db->prepare("SELECT 
            u.id, 
            u.nombre, 
            u.apellido, 
            u.telefono, 
            u.rol,
            (
                SELECT z.nombre_zona 
                FROM asignacion_transportistas at 
                JOIN zonas z ON at.zona_id = z.id 
                WHERE at.transportista_id = u.id AND at.activo = 1 
                LIMIT 1
            ) as zona_nombre 
            FROM usuarios u 
            WHERE u.id = :id LIMIT 1");
        $stmt_info->execute([':id' => $contacto_inicial_id]);
        $contacto_activo_info = $stmt_info->fetch();
    }

} catch (PDOException $e) {
    error_log("Error al cargar chat en chat.php: " . $e->getMessage());
    $contactos = [];
}
?>

<div class="h-[calc(100vh-140px)] flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    
    <div class="flex-1 flex overflow-hidden">

        <!-- ==================== COLUMNA 1: LISTA DE CONTACTOS ==================== -->
        <div class="w-72 sm:w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50/50 dark:bg-gray-800/50">
            <!-- Cabecera de contactos -->
            <div class="p-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800 dark:text-white text-xs uppercase tracking-wider">
                        <?= $usuario_rol === 'transportista' ? 'Central y Despacho' : 'Choferes en Ruta'; ?>
                    </h3>
                    <p class="text-[11px] text-gray-400">Canal directo de logística</p>
                </div>
                <span class="text-[10px] bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-mono font-bold px-2 py-0.5 rounded-full" id="total-choferes-badge">
                    <?= count($contactos); ?>
                </span>
            </div>

            <!-- Buscador de contactos -->
            <div class="p-2.5 border-b border-gray-200 dark:border-gray-700">
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5"></i>
                    <input type="text" id="buscar-contacto" onkeyup="filtrarContactos()" placeholder="Buscar contacto..." class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-xs text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Lista scrolleable de contactos -->
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/50" id="lista-contactos">
                <?php if (empty($contactos)): ?>
                    <div class="p-6 text-center text-xs text-gray-400">
                        <i data-lucide="users" class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2"></i>
                        No hay contactos disponibles para chatear.
                    </div>
                <?php else: ?>
                    <?php foreach ($contactos as $c): 
                        $isSelected = ($c['id'] == $contacto_inicial_id);
                        $iniciales = strtoupper(substr($c['nombre'], 0, 1) . substr($c['apellido'], 0, 1));
                    ?>
                        <button onclick="seleccionarContacto(<?= $c['id']; ?>, '<?= htmlspecialchars(addslashes($c['nombre'] . ' ' . $c['apellido'])); ?>', '<?= htmlspecialchars(addslashes($c['zona_nombre'] ?? 'Sin zona')); ?>', '<?= htmlspecialchars(addslashes($c['telefono'] ?? 'Sin teléfono')); ?>')" id="contacto-btn-<?= $c['id']; ?>" class="w-full p-3 text-left flex items-start space-x-3 transition-colors <?= $isSelected ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-600' : 'hover:bg-gray-100/70 dark:hover:bg-gray-700/30'; ?> item-contacto" data-nombre="<?= htmlspecialchars(strtolower($c['nombre'] . ' ' . $c['apellido'] . ' ' . ($c['zona_nombre'] ?? '') . ' ' . $c['rol'])); ?>">
                            
                            <!-- Avatar con iniciales y badge de rol -->
                            <div class="relative flex-shrink-0">
                                <div class="w-9 h-9 rounded-full <?= $c['rol'] === 'administrador' ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300' : 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300'; ?> flex items-center justify-center font-bold text-xs uppercase">
                                    <?= $iniciales; ?>
                                </div>
                                <?php if ($c['pedidos_en_ruta'] > 0): ?>
                                    <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-gray-800 rounded-full" title="Con pedidos en ruta"></span>
                                <?php endif; ?>
                            </div>

                            <!-- Info del contacto -->
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline">
                                    <p class="text-xs font-semibold truncate text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']); ?>
                                    </p>
                                    <?php if ($c['no_leidos'] > 0): ?>
                                        <span class="text-[10px] bg-blue-600 text-white font-bold px-1.5 py-0.2 rounded-full badge-no-leidos">
                                            <?= $c['no_leidos']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center space-x-1 text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    <?php if ($c['rol'] === 'administrador'): ?>
                                        <span class="text-red-600 dark:text-red-400 font-semibold text-[10px] uppercase">Central / Despacho</span>
                                    <?php else: ?>
                                        <i data-lucide="map-pin" class="w-3 h-3 text-gray-400 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($c['zona_nombre'] ?? 'Sin zona fija'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($c['pedidos_en_ruta'] > 0): ?>
                                    <span class="inline-flex items-center space-x-1 mt-1 text-[10px] text-purple-700 dark:text-purple-300 font-semibold bg-purple-50 dark:bg-purple-950/40 px-1.5 py-0.2 rounded en-ruta-badge">
                                        <i data-lucide="truck" class="w-2.5 h-2.5"></i>
                                        <span><?= $c['pedidos_en_ruta']; ?> en ruta</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== COLUMNA 2: CONVERSACIÓN ACTIVA ==================== -->
        <div class="flex-1 flex flex-col bg-white dark:bg-gray-800 overflow-hidden">
            
            <!-- Encabezado de la conversación activa -->
            <div class="p-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-white dark:bg-gray-800 z-10" id="chat-header">
                <div class="flex items-center space-x-3">
                    <?php 
                    $header_nombre = $contacto_activo_info ? htmlspecialchars($contacto_activo_info['nombre'] . ' ' . $contacto_activo_info['apellido']) : 'Sin Chofer Seleccionado';
                    $header_zona = $contacto_activo_info ? htmlspecialchars($contacto_activo_info['zona_nombre'] ?? ($contacto_activo_info['rol'] === 'administrador' ? 'Central' : 'Sin zona')) : '--';
                    $header_tel = $contacto_activo_info ? htmlspecialchars($contacto_activo_info['telefono'] ?? 'Sin teléfono') : '--';
                    $header_iniciales = $contacto_activo_info ? strtoupper(substr($contacto_activo_info['nombre'], 0, 1) . substr($contacto_activo_info['apellido'], 0, 1)) : '--';
                    ?>
                    <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-300 flex items-center justify-center font-bold text-xs" id="chat-header-avatar">
                        <?= $header_iniciales; ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white text-xs uppercase tracking-tight" id="chat-header-nombre">
                            <?= $header_nombre; ?>
                        </h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center space-x-1 mt-0.5">
                            <span id="chat-header-zona"><?= $header_zona; ?></span>
                            <span class="text-gray-300 dark:text-gray-600">•</span>
                            <span id="chat-header-telefono" class="font-mono"><?= $header_tel; ?></span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button onclick="toggleContextoRuta()" class="px-2.5 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-all flex items-center space-x-1" title="Ver / Ocultar pedidos asignados al chofer">
                        <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline"><?= $usuario_rol === 'transportista' ? 'Mis Pedidos' : 'Ruta Activa'; ?></span>
                    </button>
                    <a href="index.php?page=despacho" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg transition-all" title="Ir al Tablero de Despacho">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            <!-- Contenedor del stream de mensajes -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50/30 dark:bg-gray-900/20" id="mensajes-container">
                <?php if ($contacto_inicial_id <= 0): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center text-xs text-gray-400 py-12">
                        <i data-lucide="users" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2"></i>
                        <p class="font-semibold text-gray-700 dark:text-gray-300">No hay ningún contacto seleccionado.</p>
                        <p class="text-[11px] text-gray-400 mt-1">Selecciona un usuario de la lista izquierda para iniciar la conversación.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-xs text-gray-400 py-8">Cargando mensajes...</div>
                <?php endif; ?>
            </div>

            <!-- Chips de respuestas rápidas de ruta (Adaptadas según el rol del usuario) -->
            <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700/60 bg-gray-50/50 dark:bg-gray-800/50 flex items-center space-x-2 overflow-x-auto text-xs whitespace-nowrap">
                <span class="text-[10px] font-semibold text-gray-400 uppercase flex-shrink-0">Respuestas rápidas:</span>
                <?php if ($usuario_rol === 'transportista'): ?>
                    <button type="button" onclick="insertarTexto('📍 Ya llegué a la dirección del cliente')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        📍 Llegué a la dirección
                    </button>
                    <button type="button" onclick="insertarTexto('🚚 Saliendo hacia el siguiente punto de entrega')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        🚚 En camino a la siguiente
                    </button>
                    <button type="button" onclick="insertarTexto('✅ Pedido entregado y cobro recibido conforme')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        ✅ Entregado y cobrado
                    </button>
                    <button type="button" onclick="insertarTexto('⚠️ El cliente no atiende la puerta / timbre')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        ⚠️ No atienden el timbre
                    </button>
                <?php else: ?>
                    <button type="button" onclick="insertarTexto('📍 ¿Cuál es tu ubicación actual en la ruta?')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        📍 ¿Ubicación actual?
                    </button>
                    <button type="button" onclick="insertarTexto('🚚 El cliente me indicó que ya está en la dirección esperándote.')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        🚚 Cliente esperando
                    </button>
                    <button type="button" onclick="insertarTexto('⚠️ Favor confirmar si pudiste concretar el cobro.')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        ⚠️ Confirmar cobro
                    </button>
                    <button type="button" onclick="insertarTexto('✅ Pedido entregado y verificado. Sigue con la siguiente parada.')" class="px-2.5 py-1 bg-white dark:bg-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-300 rounded-full border border-gray-200 dark:border-gray-600 text-[11px] transition-all">
                        ✅ Siguiente parada
                    </button>
                <?php endif; ?>
            </div>

            <!-- Barra de entrada de mensaje -->
            <form id="form-enviar-mensaje" class="p-3 border-t border-gray-200 dark:border-gray-700 flex items-center space-x-2 bg-white dark:bg-gray-800">
                <input type="text" id="input-mensaje" placeholder="Escribe un mensaje de ruta..." autocomplete="off" class="flex-1 p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center space-x-1.5 flex-shrink-0">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Enviar</span>
                </button>
            </form>
        </div>

        <!-- ==================== COLUMNA 3: CONTEXTO DE RUTA EN VIVO ==================== -->
        <div id="columna-contexto" class="w-72 lg:w-80 border-l border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50/50 dark:bg-gray-800/50 transition-all duration-300">
            <div class="p-3.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800 dark:text-white text-xs uppercase tracking-wider">
                        <?= $usuario_rol === 'transportista' ? 'Mis Pedidos en Calle' : 'Carga en Calle'; ?>
                    </h3>
                    <p class="text-[11px] text-gray-400">Entregas activas de la ruta</p>
                </div>
                <button onclick="toggleContextoRuta()" class="text-gray-400 hover:text-gray-600 lg:hidden">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-3" id="lista-pedidos-contexto">
                <div class="text-center text-xs text-gray-400 py-6">Consultando pedidos en curso...</div>
            </div>
        </div>

    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    let contactoActivoId = <?= (int)$contacto_inicial_id; ?>;
    let pollingInterval = null;
    let ultimoHashContexto = '';

    document.addEventListener('DOMContentLoaded', () => {
        if (contactoActivoId > 0) {
            cargarConversacion(contactoActivoId);
            cargarContextoRuta(contactoActivoId);
        }
        // Polling constante en tiempo real cada 3 segundos
        pollingInterval = setInterval(() => {
            if (contactoActivoId > 0) {
                cargarConversacion(contactoActivoId, false);
                cargarContextoRuta(contactoActivoId);
            }
            refrescarListaContactos();
        }, 3000);
    });

    function filtrarContactos() {
        const query = document.getElementById('buscar-contacto').value.toLowerCase();
        const items = document.querySelectorAll('.item-contacto');
        items.forEach(it => {
            const nom = it.getAttribute('data-nombre');
            it.style.display = nom.includes(query) ? '' : 'none';
        });
    }

    function seleccionarContacto(id, nombre, zona, telefono) {
        if (contactoActivoId === id) return;
        contactoActivoId = id;
        ultimoHashContexto = ''; // Resetear para forzar carga limpia

        // Actualizar visualmente la selección en la lista
        document.querySelectorAll('.item-contacto').forEach(it => {
            it.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'border-l-4', 'border-blue-600');
        });
        const btn = document.getElementById(`contacto-btn-${id}`);
        if (btn) {
            btn.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-l-4', 'border-blue-600');
            const badge = btn.querySelector('.badge-no-leidos');
            if (badge) badge.remove();
        }

        // Actualizar encabezado inmediatamente
        document.getElementById('chat-header-nombre').innerText = nombre;
        document.getElementById('chat-header-zona').innerText = zona || 'Sin zona asignada';
        document.getElementById('chat-header-telefono').innerText = telefono || 'Sin teléfono';
        document.getElementById('chat-header-avatar').innerText = nombre.split(' ').map(n=>n[0]).join('').substring(0,2);

        document.getElementById('mensajes-container').innerHTML = '<div class="text-center text-xs text-gray-400 py-8">Cargando mensajes...</div>';

        cargarConversacion(id, true);
        cargarContextoRuta(id);
    }

    function cargarConversacion(contactoId, autoScroll = true) {
        if (!contactoId || contactoId <= 0) return;

        fetch(`modules/mensajeria/acciones.php?accion=obtener_mensajes&contacto_id=${contactoId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderMensajes(data.mensajes, autoScroll);
            }
        })
        .catch(err => console.error("Error al obtener mensajes:", err));
    }

    function renderMensajes(mensajes, autoScroll) {
        const container = document.getElementById('mensajes-container');
        
        if (!mensajes || mensajes.length === 0) {
            container.innerHTML = `
                <div class="h-full flex flex-col items-center justify-center text-center text-xs text-gray-400 py-12">
                    <i data-lucide="message-square" class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2"></i>
                    <p class="font-medium text-gray-600 dark:text-gray-300">No hay mensajes previos en esta conversación.</p>
                    <p class="text-[11px] text-gray-400 mt-1">Escribe una instrucción de ruta o usa las respuestas rápidas.</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        let html = '';
        mensajes.forEach(m => {
            const fechaObj = new Date(m.fecha_envio.replace(/-/g, '/'));
            const hora = isNaN(fechaObj) ? m.fecha_envio : fechaObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            if (m.es_mio == 1) {
                html += `
                    <div class="flex justify-end">
                        <div class="max-w-xs sm:max-w-md bg-blue-600 text-white rounded-2xl rounded-tr-none px-4 py-2.5 shadow-sm space-y-1">
                            <p class="text-xs break-words">${escapeHtml(m.mensaje)}</p>
                            <div class="flex items-center justify-end space-x-1 text-[10px] text-blue-200">
                                <span>${hora}</span>
                                <i data-lucide="${m.leido == 1 ? 'check-check' : 'check'}" class="w-3 h-3 text-blue-200"></i>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="flex justify-start">
                        <div class="max-w-xs sm:max-w-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-2xl rounded-tl-none px-4 py-2.5 border border-gray-200 dark:border-gray-600 shadow-sm space-y-1">
                            <p class="text-xs break-words">${escapeHtml(m.mensaje)}</p>
                            <div class="flex items-center justify-start text-[10px] text-gray-400 dark:text-gray-300">
                                <span>${hora}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        const prevCount = container.querySelectorAll('.break-words').length;
        container.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();

        if (autoScroll || prevCount !== mensajes.length) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function cargarContextoRuta(transportistaId) {
        if (!transportistaId || transportistaId <= 0) return;

        fetch(`modules/mensajeria/acciones.php?accion=contexto_ruta&transportista_id=${transportistaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const nuevoHash = JSON.stringify(data.pedidos);
                if (nuevoHash !== ultimoHashContexto) {
                    ultimoHashContexto = nuevoHash;
                    renderContexto(data.pedidos, data.es_chofer);
                }
            }
        })
        .catch(err => console.error("Error al cargar contexto de ruta:", err));
    }

    function renderContexto(pedidos, esChofer) {
        const cont = document.getElementById('lista-pedidos-contexto');
        if (!pedidos || pedidos.length === 0) {
            cont.innerHTML = `
                <div class="text-center text-xs text-gray-400 py-8">
                    <i data-lucide="package-check" class="w-8 h-8 mx-auto text-emerald-500 mb-2"></i>
                    <p class="font-medium text-gray-700 dark:text-gray-300">Ruta al día</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">No hay entregas pendientes en tránsito.</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        let html = '';
        pedidos.forEach(p => {
            const esTransito = (p.estado === 'en_transito');
            const esEntregado = (p.estado === 'entregado');
            let badgeClass = 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
            if (esTransito) badgeClass = 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300';
            else if (esEntregado) badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';

            let horaEntrega = '';
            if (esEntregado && p.fecha_entrega_real) {
                const f = new Date(p.fecha_entrega_real.replace(/-/g, '/'));
                horaEntrega = isNaN(f) ? p.fecha_entrega_real : f.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            html += `
                <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border ${esEntregado ? 'border-emerald-300 dark:border-emerald-800/60 bg-emerald-50/20' : 'border-gray-200 dark:border-gray-700'} shadow-sm space-y-2 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="font-mono font-bold text-blue-600 dark:text-blue-400">${p.numero_pedido}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ${badgeClass}">
                            ${p.estado}
                        </span>
                    </div>
                    <p class="font-semibold text-gray-800 dark:text-white truncate">${escapeHtml(p.cliente_nombre)}</p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2">${escapeHtml(p.direccion_entrega)}</p>
                    
                    <div class="flex justify-between items-center pt-1.5 border-t border-gray-100 dark:border-gray-700 font-mono text-[11px]">
                        <span class="text-gray-400">Total:</span>
                        <span class="font-bold text-gray-800 dark:text-white">$${parseFloat(p.total_usd).toFixed(2)} (${parseFloat(p.total_bs).toFixed(2)} Bs)</span>
                    </div>

                    ${esTransito ? `
                        <button onclick="entregarDesdeChat(${p.id}, '${p.numero_pedido}')" class="w-full mt-1.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Marcar Entregado</span>
                        </button>
                    ` : ''}

                    ${esEntregado ? `
                        <div class="flex items-center justify-center space-x-1 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] pt-1">
                            <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                            <span>Entregado hoy ${horaEntrega ? '(' + horaEntrega + ')' : ''}</span>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        cont.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function refrescarListaContactos() {
        fetch('modules/mensajeria/acciones.php?accion=listar_contactos')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.contactos) {
                data.contactos.forEach(c => {
                    const btn = document.getElementById(`contacto-btn-${c.id}`);
                    if (btn) {
                        // 1. Badge de mensajes no leídos
                        let badge = btn.querySelector('.badge-no-leidos');
                        if (c.no_leidos > 0 && c.id !== contactoActivoId) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'text-[10px] bg-blue-600 text-white font-bold px-1.5 py-0.2 rounded-full badge-no-leidos';
                                btn.querySelector('.justify-between').appendChild(badge);
                            }
                            badge.innerText = c.no_leidos;
                        } else if (badge) {
                            badge.remove();
                        }

                        // 2. Badge de pedidos en ruta
                        let badgeRuta = btn.querySelector('.en-ruta-badge');
                        if (c.pedidos_en_ruta > 0) {
                            if (!badgeRuta) {
                                badgeRuta = document.createElement('span');
                                badgeRuta.className = 'inline-flex items-center space-x-1 mt-1 text-[10px] text-purple-700 dark:text-purple-300 font-semibold bg-purple-50 dark:bg-purple-950/40 px-1.5 py-0.2 rounded en-ruta-badge';
                                badgeRuta.innerHTML = `<i data-lucide="truck" class="w-2.5 h-2.5"></i> <span>${c.pedidos_en_ruta} en ruta</span>`;
                                btn.querySelector('.min-w-0').appendChild(badgeRuta);
                                if (typeof lucide !== 'undefined') lucide.createIcons();
                            } else {
                                badgeRuta.querySelector('span').innerText = `${c.pedidos_en_ruta} en ruta`;
                            }
                        } else if (badgeRuta) {
                            badgeRuta.remove();
                        }
                    }
                });
            }
        })
        .catch(err => console.error("Error al refrescar lista de contactos:", err));
    }

    function entregarDesdeChat(pedidoId, numeroPedido) {
        if (!confirm(`¿Confirmar entrega y cobro del pedido ${numeroPedido}?`)) return;

        fetch('modules/despacho/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=cambiar_estado&pedido_id=${pedidoId}&nuevo_estado=entregado`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                ultimoHashContexto = ''; // Forzar actualización de UI en caliente
                cargarContextoRuta(contactoActivoId);
                refrescarListaContactos();

                // Notificar automáticamente en el hilo del chat
                fetch('modules/mensajeria/acciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `accion=enviar_mensaje&destinatario_id=${contactoActivoId}&mensaje=${encodeURIComponent('✅ Entrega completada: Pedido ' + numeroPedido + ' marcado como entregado y cobrado.')}`
                })
                .then(() => cargarConversacion(contactoActivoId, true));
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error al registrar entrega.");
        });
    }

    document.getElementById('form-enviar-mensaje').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('input-mensaje');
        const msg = input.value.trim();
        if (!msg || contactoActivoId <= 0) return;

        input.value = '';
        fetch('modules/mensajeria/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `accion=enviar_mensaje&destinatario_id=${contactoActivoId}&mensaje=${encodeURIComponent(msg)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                cargarConversacion(contactoActivoId, true);
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error al enviar el mensaje.");
        });
    });

    function insertarTexto(txt) {
        const input = document.getElementById('input-mensaje');
        input.value = txt;
        input.focus();
    }

    function toggleContextoRuta() {
        const col = document.getElementById('columna-contexto');
        col.classList.toggle('hidden');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
</script>
