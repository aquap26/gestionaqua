<?php
// modules/despacho/acciones.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = $_SESSION['usuario_id'] ?? 1; 
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

$accion = filter_input(INPUT_POST, 'accion', FILTER_DEFAULT) ?? filter_input(INPUT_GET, 'accion', FILTER_DEFAULT);

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción logística no especificada."], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// ACCIÓN A: VINCULAR TRANSPORTE A UNA ZONA
// ==========================================
if ($accion === 'asignar') {
    $transportista_id = filter_input(INPUT_POST, 'transportista_id', FILTER_VALIDATE_INT);
    $zona_id = filter_input(INPUT_POST, 'zona_id', FILTER_VALIDATE_INT);

    if (!$transportista_id || !$zona_id) {
        echo json_encode(["status" => "error", "message" => "Los datos seleccionados son inválidos."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_check = $db->prepare("SELECT id, activo FROM asignacion_transportistas WHERE transportista_id = :t_id AND zona_id = :z_id");
        $stmt_check->execute([':t_id' => $transportista_id, ':z_id' => $zona_id]);
        $existe = $stmt_check->fetch();

        if ($existe) {
            if ((int)$existe['activo'] === 1) {
                echo json_encode(["status" => "error", "message" => "Este transportista ya se encuentra asignado y activo en la zona elegida."], JSON_UNESCAPED_UNICODE);
                $db->rollBack();
                exit;
            } else {
                $stmt_reactivar = $db->prepare("UPDATE asignacion_transportistas SET activo = 1, fecha_asignacion = NOW() WHERE id = :id");
                $stmt_reactivar->execute([':id' => $existe['id']]);
                $asignacion_id = $existe['id'];
                $audit_type = "REACTIVACION_RUTA_DESPACHO";
            }
        } else {
            $stmt_insert = $db->prepare("INSERT INTO asignacion_transportistas (transportista_id, zona_id, activo, fecha_asignacion) VALUES (:t_id, :z_id, 1, NOW())");
            $stmt_insert->execute([':t_id' => $transportista_id, ':z_id' => $zona_id]);
            $asignacion_id = $db->lastInsertId();
            $audit_type = "CREACION_RUTA_DESPACHO";
        }

        $datos_nuevos_json = json_encode([
            "asignacion_id"    => $asignacion_id,
            "transportista_id" => $transportista_id,
            "zona_id"          => $zona_id,
            "estado"           => "Activo"
        ], JSON_UNESCAPED_UNICODE);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, :accion, 'asignacion_transportistas', :r_id, NULL, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':accion'   => $audit_type,
            ':r_id'     => $asignacion_id,
            ':d_nuevos' => $datos_nuevos_json,
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Transportista asignado a la zona correctamente!"], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        if ($e->getCode() == 23000) {
            echo json_encode(["status" => "error", "message" => "Error de integridad: Ya existe este emparejamiento activo en el sistema."], JSON_UNESCAPED_UNICODE);
        } else {
            error_log("Error crítico en asignación de despacho: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Error interno de servidor al procesar la ruta."], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}

// ==========================================
// ACCIÓN B: REVOCAR ASIGNACIÓN DE ZONA
// ==========================================
if ($accion === 'revocar') {
    $asignacion_id = filter_input(INPUT_POST, 'asignacion_id', FILTER_VALIDATE_INT);

    if (!$asignacion_id) {
        echo json_encode(["status" => "error", "message" => "ID de asignación inválido o corrupto."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_get = $db->prepare("SELECT transportista_id, zona_id, activo FROM asignacion_transportistas WHERE id = :id");
        $stmt_get->execute([':id' => $asignacion_id]);
        $ruta_actual = $stmt_get->fetch();

        if (!$ruta_actual || (int)$ruta_actual['activo'] === 0) {
            echo json_encode(["status" => "error", "message" => "La ruta seleccionada ya no se encuentra activa o no existe."], JSON_UNESCAPED_UNICODE);
            $db->rollBack();
            exit;
        }

        $stmt_baja = $db->prepare("UPDATE asignacion_transportistas SET activo = 0 WHERE id = :id");
        $stmt_baja->execute([':id' => $asignacion_id]);

        $datos_anteriores_json = json_encode([
            "asignacion_id"    => $asignacion_id,
            "transportista_id" => $ruta_actual['transportista_id'],
            "zona_id"          => $ruta_actual['zona_id'],
            "estado_anterior"  => "Activo"
        ], JSON_UNESCAPED_UNICODE);
        
        $datos_nuevos_json = json_encode(["estado_nuevo" => "Inactivo / Revocado"], JSON_UNESCAPED_UNICODE);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'REVOCACION_RUTA_DESPACHO', 'asignacion_transportistas', :r_id, :d_anteriores, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'         => $usuario_sesion_id,
            ':r_id'         => $asignacion_id,
            ':d_anteriores' => $datos_anteriores_json,
            ':d_nuevos'     => $datos_nuevos_json,
            ':ip'           => $ip_address,
            ':ua'           => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "La ruta ha sido revocada de forma segura. El despachador fue desvinculado."], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al revocar despacho: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al dar de baja la asignación."], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// ACCIÓN C: ASIGNAR CHOFER A UN PEDIDO
// ==========================================
if ($accion === 'asignar_pedido') {
    $pedido_id        = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $transportista_id = filter_input(INPUT_POST, 'transportista_id', FILTER_VALIDATE_INT);
    $zona_id          = filter_input(INPUT_POST, 'zona_id', FILTER_VALIDATE_INT);

    if (!$pedido_id || !$transportista_id) {
        echo json_encode(["status" => "error", "message" => "Debe indicar el pedido y el transportista a asignar."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_p = $db->prepare("SELECT id, numero_pedido, estado, transportista_id, zona_id FROM pedidos WHERE id = :id LIMIT 1");
        $stmt_p->execute([':id' => $pedido_id]);
        $ped = $stmt_p->fetch();

        if (!$ped) {
            echo json_encode(["status" => "error", "message" => "El pedido indicado no existe."], JSON_UNESCAPED_UNICODE);
            $db->rollBack();
            exit;
        }

        $final_zona = $zona_id ?: $ped['zona_id'];

        $stmt_upd = $db->prepare("UPDATE pedidos SET transportista_id = :t_id, zona_id = :z_id, estado = CASE WHEN estado = 'pendiente' THEN 'confirmado' ELSE estado END WHERE id = :p_id");
        $stmt_upd->execute([
            ':t_id' => $transportista_id,
            ':z_id' => $final_zona,
            ':p_id' => $pedido_id
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'DESPACHO_ASIGNAR_PEDIDO', 'pedidos', :r_id, :d_ant, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $pedido_id,
            ':d_ant'    => json_encode(["transportista_id" => $ped['transportista_id'], "zona_id" => $ped['zona_id']], JSON_UNESCAPED_UNICODE),
            ':d_nuevos' => json_encode(["transportista_id" => $transportista_id, "zona_id" => $final_zona], JSON_UNESCAPED_UNICODE),
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "Pedido #{$ped['numero_pedido']} asignado al transportista con éxito."], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al asignar pedido a transportista: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al asignar pedido: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// ACCIÓN D: CAMBIAR ESTADO DE DESPACHO
// ==========================================
if ($accion === 'cambiar_estado') {
    $pedido_id    = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $nuevo_estado = filter_input(INPUT_POST, 'nuevo_estado', FILTER_DEFAULT);

    $estados_validos = ['pendiente', 'confirmado', 'preparando', 'en_transito', 'entregado', 'cancelado'];

    if (!$pedido_id || !in_array($nuevo_estado, $estados_validos)) {
        echo json_encode(["status" => "error", "message" => "Datos o estado de despacho inválidos."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_p = $db->prepare("SELECT id, numero_pedido, estado, cliente_id, transportista_id FROM pedidos WHERE id = :id LIMIT 1");
        $stmt_p->execute([':id' => $pedido_id]);
        $ped = $stmt_p->fetch();

        if (!$ped) {
            echo json_encode(["status" => "error", "message" => "El pedido indicado no existe."], JSON_UNESCAPED_UNICODE);
            $db->rollBack();
            exit;
        }

        if ($nuevo_estado === 'en_transito' && empty($ped['transportista_id'])) {
            echo json_encode(["status" => "error", "message" => "Debe asignar un transportista antes de despachar el pedido a ruta."], JSON_UNESCAPED_UNICODE);
            $db->rollBack();
            exit;
        }

        if ($nuevo_estado === 'entregado') {
            $stmt_upd = $db->prepare("UPDATE pedidos SET estado = :est, fecha_entrega_real = NOW() WHERE id = :id");
            $stmt_upd->execute([':est' => $nuevo_estado, ':id' => $pedido_id]);

            $stmt_notif = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_id, fecha_creacion) VALUES (:u_id, 'pedido_entregado', 'Pedido Entregado', :msg, :ref, NOW())");
            $stmt_notif->execute([
                ':u_id' => $ped['cliente_id'],
                ':msg'  => "El pedido {$ped['numero_pedido']} ha sido entregado exitosamente por el transportista.",
                ':ref'  => $pedido_id
            ]);
        } else {
            $stmt_upd = $db->prepare("UPDATE pedidos SET estado = :est WHERE id = :id");
            $stmt_upd->execute([':est' => $nuevo_estado, ':id' => $pedido_id]);
        }

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, fecha_auditoria) VALUES (:u_id, 'CAMBIO_ESTADO_DESPACHO', 'pedidos', :r_id, :d_ant, :d_nuevos, :ip, :ua, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $pedido_id,
            ':d_ant'    => json_encode(["estado_anterior" => $ped['estado']], JSON_UNESCAPED_UNICODE),
            ':d_nuevos' => json_encode(["estado_nuevo" => $nuevo_estado], JSON_UNESCAPED_UNICODE),
            ':ip'       => $ip_address,
            ':ua'       => $user_agent
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "Estado de {$ped['numero_pedido']} actualizado a '{$nuevo_estado}'."], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al cambiar estado de despacho: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al cambiar estado: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// ACCIÓN E: OBTENER ESTADO EN VIVO DEL TABLERO
// ==========================================
if ($accion === 'obtener_tablero') {
    $filtro_estado = filter_input(INPUT_POST, 'estado', FILTER_DEFAULT) ?? filter_input(INPUT_GET, 'estado', FILTER_DEFAULT) ?? 'todos';
    $filtro_zona   = filter_input(INPUT_POST, 'zona_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'zona_id', FILTER_VALIDATE_INT) ?? 0;

    try {
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

        echo json_encode(["status" => "success", "stats" => $stats, "pedidos" => $pedidos], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en obtener_tablero: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al obtener estado del tablero: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción logística desconocida."], JSON_UNESCAPED_UNICODE);
