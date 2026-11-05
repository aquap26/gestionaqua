<div class="max-w-xl mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Registrar Nueva Zona de Entrega</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Define los sectores geográficos para asignación de pedidos y transportistas.</p>
        </div>
        <i data-lucide="map-pin" class="w-5 h-5 text-blue-500"></i>
    </div>

    <form id="form-crear-zona" class="p-6 space-y-5">
        <input type="hidden" name="accion" value="crear">

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Nombre de la Zona <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nombre_zona" required placeholder="Ej. Zona Norte - Altamira / Los Palos Grandes" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Código Postal (Opcional)
            </label>
            <input type="text" name="codigo_postal" placeholder="Ej. 1060" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Descripción / Sectores Cubiertos
            </label>
            <textarea name="descripcion" rows="3" placeholder="Ej. Abarca Av. Francisco de Miranda hasta la Cota Mil. Horario de reparto matutino." class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all resize-none"></textarea>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="index.php?page=zonas" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">Cancelar</a>
            <button type="submit" class="inline-flex items-center space-x-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>Guardar Zona</span>
            </button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    document.getElementById('form-crear-zona').addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        fetch('modules/zonas/acciones.php', {
            method: 'POST',
            body: new URLSearchParams(new FormData(this))
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                window.location.href = 'index.php?page=zonas';
            } else {
                submitBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error al procesar el registro de zona.");
            submitBtn.disabled = false;
        });
    });
</script>
