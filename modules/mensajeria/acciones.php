<?php
// modules/mensajeria/acciones.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = $_SESSION['usuario_id'] ?? 1;

$accion = filter_input(INPUT_POST, 'accion', FILTER_DEFAULT) ?? filter_input(INPUT_GET, 'accion', FILTER_DEFAULT);

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 1. LISTAR CONTACTOS / CHOFERES DE RUTA
// ==========================================
if ($accion === 'listar_contactos') {
    try {
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
            ) as no_leidos,
            (
                SELECT mc2.mensaje 
                FROM mensajes_chat mc2 
                WHERE (mc2.remitente_id = u.id AND mc2.destinatario_id = :mi_id2)
                   OR (mc2.remitente_id = :mi_id3 AND mc2.destinatario_id = u.id)
                ORDER BY mc2.id DESC LIMIT 1
            ) as ultimo_mensaje,
            (
                SELECT mc2.fecha_envio 
                FROM mensajes_chat mc2 
                WHERE (mc2.remitente_id = u.id AND mc2.destinatario_id = :mi_id4)
                   OR (mc2.remitente_id = :mi_id5 AND mc2.destinatario_id = u.id)
                ORDER BY mc2.id DESC LIMIT 1
            ) as ultima_fecha
        FROM usuarios u
        WHERE u.activo = 1 AND u.id != :mi_id6 AND u.rol IN ('transportista', 'administrador')
        ORDER BY no_leidos DESC, pedidos_en_ruta DESC, u.nombre ASC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':mi_id1' => $usuario_sesion_id,
            ':mi_id2' => $usuario_sesion_id,
            ':mi_id3' => $usuario_sesion_id,
            ':mi_id4' => $usuario_sesion_id,
            ':mi_id5' => $usuario_sesion_id,
            ':mi_id6' => $usuario_sesion_id
        ]);
        $contactos = $stmt->fetchAll();

        echo json_encode(["status" => "success", "contactos" => $contactos], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en listar_contactos: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al cargar contactos: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// 2. OBTENER MENSAJES CON UN CONTACTO
// ==========================================
if ($accion === 'obtener_mensajes') {
    $contacto_id = filter_input(INPUT_POST, 'contacto_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'contacto_id', FILTER_VALIDATE_INT);

    if (!$contacto_id) {
        echo json_encode(["status" => "error", "message" => "Contacto no especificado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // 1. Marcar como leídos los mensajes que me envió este contacto
        $stmt_read = $db->prepare("UPDATE mensajes_chat SET leido = 1 WHERE remitente_id = :c_id AND destinatario_id = :mi_id AND leido = 0");
        $stmt_read->execute([':c_id' => $contacto_id, ':mi_id' => $usuario_sesion_id]);

        // 2. Consultar historial de conversación (con parámetros únicos por restricción ATTR_EMULATE_PREPARES=false)
        $query = "SELECT 
            id,
            remitente_id,
            destinatario_id,
            mensaje,
            leido,
            fecha_envio,
            (CASE WHEN remitente_id = :mi_id1 THEN 1 ELSE 0 END) as es_mio
        FROM mensajes_chat
        WHERE (remitente_id = :mi_id2 AND destinatario_id = :c_id1)
           OR (remitente_id = :c_id2 AND destinatario_id = :mi_id3)
        ORDER BY fecha_envio ASC, id ASC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':mi_id1' => $usuario_sesion_id,
            ':mi_id2' => $usuario_sesion_id,
            ':c_id1'  => $contacto_id,
            ':c_id2'  => $contacto_id,
            ':mi_id3' => $usuario_sesion_id
        ]);
        $mensajes = $stmt->fetchAll();

        echo json_encode(["status" => "success", "mensajes" => $mensajes], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en obtener_mensajes: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al obtener mensajes: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// 3. ENVIAR MENSAJE
// ==========================================
if ($accion === 'enviar_mensaje') {
    $destinatario_id = filter_input(INPUT_POST, 'destinatario_id', FILTER_VALIDATE_INT);
    $mensaje         = trim(filter_input(INPUT_POST, 'mensaje', FILTER_DEFAULT) ?? '');

    if (!$destinatario_id || empty($mensaje)) {
        echo json_encode(["status" => "error", "message" => "Destinatario o mensaje inválido."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_insert = $db->prepare("INSERT INTO mensajes_chat (remitente_id, destinatario_id, mensaje, leido, fecha_envio) VALUES (:rem, :dest, :msg, 0, NOW())");
        $stmt_insert->execute([
            ':rem'  => $usuario_sesion_id,
            ':dest' => $destinatario_id,
            ':msg'  => $mensaje
        ]);
        $mensaje_id = $db->lastInsertId();

        // Notificación para el destinatario
        $stmt_notif = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, leida, fecha_creacion, referencia_id) VALUES (:u_id, 'mensaje_chat', 'Nuevo Mensaje de Ruta', :msg, 0, NOW(), :ref)");
        $stmt_notif->execute([
            ':u_id' => $destinatario_id,
            ':msg'  => mb_substr($mensaje, 0, 95),
            ':ref'  => $usuario_sesion_id
        ]);

        $db->commit();
        echo json_encode([
            "status"     => "success",
            "mensaje_id" => $mensaje_id,
            "mensaje"    => [
                "id"              => $mensaje_id,
                "remitente_id"    => $usuario_sesion_id,
                "destinatario_id" => $destinatario_id,
                "mensaje"         => $mensaje,
                "leido"           => 0,
                "fecha_envio"     => date('Y-m-d H:i:s'),
                "es_mio"          => 1
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al enviar mensaje: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de base de datos al enviar mensaje: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==========================================
// 4. CONTEXTO DE RUTA DEL CHOFER
// ==========================================
if ($accion === 'contexto_ruta') {
    $transportista_id = filter_input(INPUT_POST, 'transportista_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'transportista_id', FILTER_VALIDATE_INT);

    try {
        // Detectar si el usuario en sesión es transportista para mostrar sus propios pedidos
        $stmt_rol = $db->prepare("SELECT rol FROM usuarios WHERE id = :u_id LIMIT 1");
        $stmt_rol->execute([':u_id' => $usuario_sesion_id]);
        $rol_sesion = $stmt_rol->fetchColumn();

        $id_chofer_a_consultar = ($rol_sesion === 'transportista') ? $usuario_sesion_id : $transportista_id;

        if (!$id_chofer_a_consultar) {
            echo json_encode(["status" => "error", "message" => "Transportista no especificado."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $query = "SELECT 
            p.id,
            p.numero_pedido,
            CONCAT(u.nombre, ' ', u.apellido) AS cliente_nombre,
            u.telefono AS cliente_telefono,
            p.direccion_entrega,
            z.nombre_zona,
            p.total_usd,
            p.total_bs,
            p.estado,
            p.observaciones,
            p.fecha_entrega_real
        FROM pedidos p
        JOIN usuarios u ON p.cliente_id = u.id
        LEFT JOIN zonas z ON p.zona_id = z.id
        WHERE p.transportista_id = :t_id 
          AND (
              p.estado IN ('pendiente', 'confirmado', 'preparando', 'en_transito')
              OR (p.estado = 'entregado' AND DATE(COALESCE(p.fecha_entrega_real, p.fecha_pedido)) = CURDATE())
          )
        ORDER BY 
            CASE 
                WHEN p.estado = 'en_transito' THEN 1
                WHEN p.estado = 'preparando' THEN 2
                WHEN p.estado IN ('pendiente', 'confirmado') THEN 3
                ELSE 4
            END, p.id DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([':t_id' => $id_chofer_a_consultar]);
        $pedidos_ruta = $stmt->fetchAll();

        echo json_encode(["status" => "success", "pedidos" => $pedidos_ruta, "es_chofer" => ($rol_sesion === 'transportista')], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en contexto_ruta: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al obtener contexto de ruta: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción desconocida."], JSON_UNESCAPED_UNICODE);
