<?php
// modules/zonas/acciones.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método de comunicación no permitido."]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = $_SESSION['usuario_id'] ?? 1;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

$accion = filter_input(INPUT_POST, 'accion', FILTER_DEFAULT);

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."]);
    exit;
}

// ==========================================
// ACCIÓN 1: CREAR NUEVA ZONA
// ==========================================
if ($accion === 'crear') {
    $nombre_zona   = trim(filter_input(INPUT_POST, 'nombre_zona', FILTER_DEFAULT) ?? '');
    $codigo_postal = trim(filter_input(INPUT_POST, 'codigo_postal', FILTER_DEFAULT) ?? '');
    $descripcion   = trim(filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT) ?? '');

    if (empty($nombre_zona)) {
        echo json_encode(["status" => "error", "message" => "El nombre de la zona es obligatorio."]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Validar unicidad del nombre
        $stmt_check = $db->prepare("SELECT id FROM zonas WHERE LOWER(nombre_zona) = LOWER(:nombre) LIMIT 1");
        $stmt_check->execute([':nombre' => $nombre_zona]);
        if ($stmt_check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Ya existe una zona registrada con ese nombre."]);
            $db->rollBack();
            exit;
        }

        $stmt_insert = $db->prepare("INSERT INTO zonas (nombre_zona, codigo_postal, descripcion, activo, created_at, updated_at) VALUES (:nom, :cp, :desc, 1, NOW(), NOW())");
        $stmt_insert->execute([
            ':nom'  => $nombre_zona,
            ':cp'   => !empty($codigo_postal) ? $codigo_postal : null,
            ':desc' => !empty($descripcion) ? $descripcion : null
        ]);
        $zona_id = $db->lastInsertId();

        // Auditoría inmutable
        $datos_nuevos = json_encode([
            "zona_id"       => $zona_id,
            "nombre_zona"   => $nombre_zona,
            "codigo_postal" => $codigo_postal,
            "descripcion"   => $descripcion,
            "activo"        => 1
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'CREACION_ZONA', 'zonas', :r_id, NULL, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $zona_id,
            ':d_nuevos' => $datos_nuevos,
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Zona de reparto registrada exitosamente!"]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error en crear zona: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al registrar la zona."]);
    }
    exit;
}

// ==========================================
// ACCIÓN 2: EDITAR ZONA EXISTENTE
// ==========================================
if ($accion === 'editar') {
    $id            = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nombre_zona   = trim(filter_input(INPUT_POST, 'nombre_zona', FILTER_DEFAULT) ?? '');
    $codigo_postal = trim(filter_input(INPUT_POST, 'codigo_postal', FILTER_DEFAULT) ?? '');
    $descripcion   = trim(filter_input(INPUT_POST, 'descripcion', FILTER_DEFAULT) ?? '');
    $activo        = isset($_POST['activo']) ? 1 : 0;

    if (!$id || empty($nombre_zona)) {
        echo json_encode(["status" => "error", "message" => "Datos de zona insuficientes o inválidos."]);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_prev = $db->prepare("SELECT * FROM zonas WHERE id = :id LIMIT 1");
        $stmt_prev->execute([':id' => $id]);
        $zona_anterior = $stmt_prev->fetch();

        if (!$zona_anterior) {
            echo json_encode(["status" => "error", "message" => "La zona especificada no existe."]);
            $db->rollBack();
            exit;
        }

        // Validar unicidad de nombre excluyendo el actual
        $stmt_check = $db->prepare("SELECT id FROM zonas WHERE LOWER(nombre_zona) = LOWER(:nombre) AND id != :id LIMIT 1");
        $stmt_check->execute([':nombre' => $nombre_zona, ':id' => $id]);
        if ($stmt_check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Ya existe otra zona con este nombre."]);
            $db->rollBack();
            exit;
        }

        $stmt_upd = $db->prepare("UPDATE zonas SET nombre_zona = :nom, codigo_postal = :cp, descripcion = :desc, activo = :act, updated_at = NOW() WHERE id = :id");
        $stmt_upd->execute([
            ':nom'  => $nombre_zona,
            ':cp'   => !empty($codigo_postal) ? $codigo_postal : null,
            ':desc' => !empty($descripcion) ? $descripcion : null,
            ':act'  => $activo,
            ':id'   => $id
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'EDICION_ZONA', 'zonas', :r_id, :d_ant, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $id,
            ':d_ant'    => json_encode($zona_anterior),
            ':d_nuevos' => json_encode(['nombre_zona' => $nombre_zona, 'codigo_postal' => $codigo_postal, 'descripcion' => $descripcion, 'activo' => $activo]),
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Zona actualizada correctamente!"]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error en editar zona: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al actualizar la zona."]);
    }
    exit;
}

// ==========================================
// ACCIÓN 3: TOGGLE ACTIVAR/DESACTIVAR ZONA
// ==========================================
if ($accion === 'toggle_activo') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        echo json_encode(["status" => "error", "message" => "ID de zona inválido."]);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_prev = $db->prepare("SELECT id, nombre_zona, activo FROM zonas WHERE id = :id LIMIT 1");
        $stmt_prev->execute([':id' => $id]);
        $zona = $stmt_prev->fetch();

        if (!$zona) {
            echo json_encode(["status" => "error", "message" => "La zona no existe."]);
            $db->rollBack();
            exit;
        }

        $nuevo_estado = (int)$zona['activo'] === 1 ? 0 : 1;

        // Si se va a desactivar, verificar si hay pedidos pendientes o en tránsito
        if ($nuevo_estado === 0) {
            $stmt_pedidos = $db->prepare("SELECT COUNT(*) as activos FROM pedidos WHERE zona_id = :id AND estado IN ('pendiente', 'confirmado', 'preparando', 'en_transito')");
            $stmt_pedidos->execute([':id' => $id]);
            $activos = (int)$stmt_pedidos->fetch()['activos'];
            if ($activos > 0) {
                echo json_encode(["status" => "error", "message" => "No se puede desactivar la zona: tiene {$activos} pedidos en curso."]);
                $db->rollBack();
                exit;
            }
        }

        $stmt_upd = $db->prepare("UPDATE zonas SET activo = :act, updated_at = NOW() WHERE id = :id");
        $stmt_upd->execute([':act' => $nuevo_estado, ':id' => $id]);

        $accion_nombre = $nuevo_estado === 1 ? 'ACTIVACION_ZONA' : 'DESACTIVACION_ZONA';
        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, :accion, 'zonas', :r_id, :d_ant, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':accion'   => $accion_nombre,
            ':r_id'     => $id,
            ':d_ant'    => json_encode(["activo" => $zona['activo']]),
            ':d_nuevos' => json_encode(["activo" => $nuevo_estado]),
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        $msg = $nuevo_estado === 1 ? "Zona reactivada para distribución." : "Zona dada de baja lógicamente.";
        echo json_encode(["status" => "success", "message" => $msg]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error en toggle_activo zona: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al cambiar estado de la zona."]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción desconocida."]);
