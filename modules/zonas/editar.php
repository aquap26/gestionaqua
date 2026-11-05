<?php
// modules/zonas/editar.php
$db = Database::conectar();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>ID de zona inválido.</div>";
    return;
}

try {
    $stmt = $db->prepare("SELECT * FROM zonas WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $zona = $stmt->fetch();

    if (!$zona) {
        echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>La zona solicitada no existe.</div>";
        return;
    }
} catch (PDOException $e) {
    error_log("Error al cargar zona: " . $e->getMessage());
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Error de base de datos al recuperar la zona.</div>";
    return;
}
?>

<div class="max-w-xl mx-auto bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-gray-800 dark:text-white text-sm uppercase tracking-wider">Modificar Zona de Entrega</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Editando: <?= htmlspecialchars($zona['nombre_zona']); ?></p>
        </div>
        <i data-lucide="edit-3" class="w-5 h-5 text-blue-500"></i>
    </div>

    <form id="form-editar-zona" class="p-6 space-y-5">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" value="<?= $zona['id']; ?>">

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Nombre de la Zona <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nombre_zona" required value="<?= htmlspecialchars($zona['nombre_zona']); ?>" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Código Postal (Opcional)
            </label>
            <input type="text" name="codigo_postal" value="<?= htmlspecialchars($zona['codigo_postal'] ?? ''); ?>" placeholder="Ej. 1060" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-mono text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                Descripción / Sectores Cubiertos
            </label>
            <textarea name="descripcion" rows="3" class="w-full p-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 transition-all resize-none"><?= htmlspecialchars($zona['descripcion'] ?? ''); ?></textarea>
        </div>

        <div class="flex items-center space-x-2 pt-2">
            <input type="checkbox" id="activo" name="activo" value="1" <?= (int)$zona['activo'] === 1 ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
            <label for="activo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Zona Habilitada / Activa para pedidos</label>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="index.php?page=zonas" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">Cancelar</a>
            <button type="submit" class="inline-flex items-center space-x-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-all shadow-sm">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Actualizar Zona</span>
            </button>
        </div>
    </form>
</div>

<script>
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    document.getElementById('form-editar-zona').addEventListener('submit', function(e) {
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
            alert("Error al actualizar la zona.");
            submitBtn.disabled = false;
        });
    });
</script>
