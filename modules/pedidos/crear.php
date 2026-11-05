<?php
// modules/pedidos/crear.php

$db = Database::conectar();

try {
    // 1. Obtener tasa de cambio
    $stmt_tasa = $db->query("SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
    $empresa = $stmt_tasa->fetch();
    $tasa_actual = $empresa ? (float)$empresa['tasa_cambio_usd'] : 244.00;

    // 2. Obtener productos activos con stock disponible real para la venta
    $query_prod = "SELECT p.id, p.nombre, p.precio_usd, i.stock_disponible 
                   FROM productos p
                   INNER JOIN inventario i ON i.producto_id = p.id
                   WHERE p.activo = 1 AND i.stock_disponible > 0";
    $productos = $db->query($query_prod)->fetchAll();

    // 3. Obtener clientes registrados
    $query_clientes = "SELECT id, nombre, apellido, cedula, telefono, direccion, rol 
                       FROM usuarios 
                       WHERE activo = 1 
                       ORDER BY (rol = 'cliente') DESC, nombre ASC";
    $clientes = $db->query($query_clientes)->fetchAll();

    // 4. Obtener zonas de entrega activas
    $query_zonas = "SELECT id, nombre_zona, codigo_postal FROM zonas WHERE activo = 1 ORDER BY nombre_zona ASC";
    $zonas = $db->query($query_zonas)->fetchAll();

} catch (PDOException $e) {
    error_log("Error en formulario crear pedido: " . $e->getMessage());
    $productos = [];
    $clientes  = [];
    $zonas     = [];
    $tasa_actual = 244.00;
}
?>

<div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-gray-800 dark:text-white">Registrar Venta / Pedido Nuevo</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Genera la orden con destino de entrega para la cola de despacho.</p>
        </div>
        <i data-lucide="shopping-cart" class="w-5 h-5 text-blue-500"></i>
    </div>

    <form id="form-crear-pedido" class="p-6 space-y-6">
        <input type="hidden" id="tasa_cambio" value="<?php echo $tasa_actual; ?>">

        <!-- SECCIÓN 1: DATOS DEL CLIENTE Y ZONA DE ENTREGA -->
        <div class="bg-gray-50/70 dark:bg-gray-700/20 p-4 rounded-xl border border-gray-200 dark:border-gray-700 space-y-4">
            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                <i data-lucide="user-check" class="w-4 h-4 text-blue-500"></i>
                <span>Destinatario y Cobertura de Despacho</span>
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Selector de Cliente -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Cliente Solicitante <span class="text-red-500">*</span></label>
                    <select id="cliente_id" name="cliente_id" onchange="actualizarDatosCliente()" required class="w-full p-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-direccion="<?php echo htmlspecialchars($c['direccion'] ?? ''); ?>" data-telefono="<?php echo htmlspecialchars($c['telefono'] ?? ''); ?>">
                                <?php echo htmlspecialchars($c['nombre'] . ' ' . $c['apellido']); ?> (<?php echo ucfirst($c['rol']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Selector de Zona de Entrega -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Zona de Entrega <span class="text-red-500">*</span></label>
                    <select id="zona_id" name="zona_id" required class="w-full p-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <option value="">-- Seleccionar Zona de Reparto --</option>
                        <?php foreach ($zonas as $z): ?>
                            <option value="<?php echo $z['id']; ?>">
                                <?php echo htmlspecialchars($z['nombre_zona']); ?> <?php echo $z['codigo_postal'] ? '('.htmlspecialchars($z['codigo_postal']).')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Dirección de Entrega -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Dirección Específica de Entrega <span class="text-red-500">*</span></label>
                <input type="text" id="direccion_entrega" name="direccion_entrega" required placeholder="Ej. Av. Francisco de Miranda, Edif. Centro Plaza, Torre B, Piso 5, Apto 502" class="w-full p-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>

            <!-- Observaciones -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Instrucciones / Observaciones para el Chofer (Opcional)</label>
                <input type="text" id="observaciones" name="observaciones" placeholder="Ej. Dejar en conserjería, llevar cambio de $20 o botellones vacíos listos" class="w-full p-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
            </div>
        </div>

        <!-- SECCIÓN 2: SELECCIÓN DE PRODUCTOS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Seleccionar Producto</label>
                <select id="select-producto" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    <option value="">-- Seleccione un artículo --</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-precio="<?php echo $p['precio_usd']; ?>" data-max="<?php echo $p['stock_disponible']; ?>">
                            <?php echo htmlspecialchars($p['nombre']); ?> (Disp: <?php echo $p['stock_disponible']; ?>) - $<?php echo number_format($p['precio_usd'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Cantidad</label>
                <div class="flex space-x-2">
                    <input type="number" id="input-cantidad" min="1" value="1" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none text-center">
                    <button type="button" onclick="agregarItem()" class="px-4 py-2.5 bg-gray-800 dark:bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-medium transition-all">
                        Añadir
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de Detalles (Simetría Visual Dinámica) -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">
                    <tr>
                        <th class="p-3">Descripción</th>
                        <th class="p-3 text-center">Cant.</th>
                        <th class="p-3 text-right">P. Unit ($)</th>
                        <th class="p-3 text-right">Subtotal ($)</th>
                        <th class="p-3 text-right">Subtotal (Bs)</th>
                        <th class="p-3 text-center"></th>
                    </tr>
                </thead>
                <tbody id="tabla-items" class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr id="fila-vacia">
                        <td colspan="6" class="p-4 text-center text-xs text-gray-400">Ningún producto añadido a la orden todavía.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bloque de Totales Simétricos Bimoneda -->
        <div class="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-gray-700/20 p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-right">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">TOTAL REF. USD</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">$<span id="total-usd">0.00</span></p>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">TOTAL EN BOLÍVARES</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><span id="total-bs">0.00</span> Bs</p>
            </div>
        </div>

        <!-- SECCIÓN 3: PAGO Y CONCILIACIÓN -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3 flex items-center gap-1">
                <i data-lucide="credit-card" class="w-4 h-4 text-blue-500"></i> Método de Pago y Conciliación
            </h4>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Forma de Pago</label>
                    <select id="metodo_pago" name="metodo_pago" onchange="evaluarMetodoPago()" required class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <option value="EFECTIVO_USD">Efectivo Dólares ($)</option>
                        <option value="PAGO_MOVIL">Pago Móvil (Bs.)</option>
                        <option value="TRANSFERENCIA_BS">Transferencia Bancaria (Bs.)</option>
                        <option value="EFECTIVO_BS">Efectivo Bolívares (Bs.)</option>
                    </select>
                </div>

                <div id="bloque-referencia" class="hidden">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Nro. de Referencia (Últimos dígitos)</label>
                    <input type="text" id="referencia_pago" name="referencia_pago" placeholder="Ej. 123456" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
            </div>
        </div>

        <!-- Botones de Envío Formulario -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="index.php?page=pedidos" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">Cancelar</a>
            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm">Guardar Orden</button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    const tasa = parseFloat(document.getElementById('tasa_cambio').value);
    let itemsCart = [];

    function actualizarDatosCliente() {
        const select = document.getElementById('cliente_id');
        const dir = select.options[select.selectedIndex].getAttribute('data-direccion');
        if (dir && dir.trim() !== '') {
            document.getElementById('direccion_entrega').value = dir;
        }
    }

    function evaluarMetodoPago() {
        const metodo = document.getElementById('metodo_pago').value;
        const bloqueRef = document.getElementById('bloque-referencia');
        const inputRef = document.getElementById('referencia_pago');

        if (metodo === 'PAGO_MOVIL' || metodo === 'TRANSFERENCIA_BS') {
            bloqueRef.classList.remove('hidden');
            inputRef.setAttribute('required', 'required');
        } else {
            bloqueRef.classList.add('hidden');
            inputRef.removeAttribute('required');
            inputRef.value = '';
        }
    }

    function agregarItem() {
        const select = document.getElementById('select-producto');
        const cantidadInput = document.getElementById('input-cantidad');
        
        if (!select.value) return alert('Por favor, elija un producto válido.');
        
        const id = select.value;
        const nombre = select.options[select.selectedIndex].text.split(' (')[0];
        const precio = parseFloat(select.options[select.selectedIndex].getAttribute('data-precio'));
        const max = parseInt(select.options[select.selectedIndex].getAttribute('data-max'));
        const cantidad = parseInt(cantidadInput.value);

        if (cantidad <= 0 || cantidad > max) {
            return alert(`Cantidad no permitida. El stock disponible actual de este producto es de ${max} unidades.`);
        }

        const existe = itemsCart.find(item => item.id === id);
        if (existe) {
            if ((existe.cantidad + cantidad) > max) return alert(`No puedes exceder el stock máximo disponible (${max}) para este ítem.`);
            existe.cantidad += cantidad;
        } else {
            itemsCart.push({ id, nombre, precio, cantidad });
        }

        renderTabla();
    }

    function eliminarItem(id) {
        itemsCart = itemsCart.filter(item => item.id !== id);
        renderTabla();
    }

    function renderTabla() {
        const tbody = document.getElementById('tabla-items');
        tbody.innerHTML = '';
        
        if (itemsCart.length === 0) {
            tbody.innerHTML = `<tr id="fila-vacia"><td colspan="6" class="p-4 text-center text-xs text-gray-400">Ningún producto añadido a la orden todavía.</td></tr>`;
            document.getElementById('total-usd').innerText = '0.00';
            document.getElementById('total-bs').innerText = '0.00';
            return;
        }

        let totalUSD = 0;

        itemsCart.forEach(item => {
            const subUSD = item.precio * item.cantidad;
            const subBS = subUSD * tasa;
            totalUSD += subUSD;

            tbody.innerHTML += `
                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10">
                    <td class="p-3 font-medium text-gray-900 dark:text-white">${item.nombre}</td>
                    <td class="p-3 text-center font-semibold text-gray-800 dark:text-gray-200">${item.cantidad}</td>
                    <td class="p-3 text-right text-gray-700 dark:text-gray-300">$${item.precio.toFixed(2)}</td>
                    <td class="p-3 text-right font-medium text-blue-600 dark:text-blue-400">$${subUSD.toFixed(2)}</td>
                    <td class="p-3 text-right text-gray-500 dark:text-gray-400">${subBS.toLocaleString('es-VE', {minimumFractionDigits: 2})}</td>
                    <td class="p-3 text-center">
                        <button type="button" onclick="eliminarItem('${item.id}')" class="text-red-500 p-1 hover:bg-red-50 dark:hover:bg-red-950/20 rounded">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        document.getElementById('total-usd').innerText = totalUSD.toFixed(2);
        document.getElementById('total-bs').innerText = (totalUSD * tasa).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Inicializar dirección del primer cliente seleccionado
    actualizarDatosCliente();

    // Envío seguro de datos mediante POST asíncrono
    document.getElementById('form-crear-pedido').addEventListener('submit', function(e) {
        e.preventDefault();
        if (itemsCart.length === 0) return alert('Debe añadir al menos un producto a la tabla antes de guardar.');

        const clienteId = document.getElementById('cliente_id').value;
        const zonaId = document.getElementById('zona_id').value;
        const direccion = document.getElementById('direccion_entrega').value.trim();
        const observaciones = document.getElementById('observaciones').value.trim();
        const metodoPago = document.getElementById('metodo_pago').value;
        const referenciaPago = document.getElementById('referencia_pago').value;

        if (!zonaId) {
            return alert('Debe seleccionar una zona de entrega para la orden.');
        }

        if (!direccion) {
            return alert('Debe indicar la dirección de entrega.');
        }

        fetch('modules/pedidos/acciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'crear',
                cliente_id: clienteId,
                zona_id: zonaId,
                direccion_entrega: direccion,
                observaciones: observaciones,
                metodo_pago: metodoPago,
                referencia_pago: referenciaPago,
                items: itemsCart
            })
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') window.location.href = 'index.php?page=despacho';
        })
        .catch(err => {
            console.error(err);
            alert("Error al procesar la venta.");
        });
    });
</script>
