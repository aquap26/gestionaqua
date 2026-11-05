<?php
// modules/pedidos/acciones.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/conexion.php';
$db = Database::conectar();

$usuario_sesion_id = $_SESSION['usuario_id'] ?? 1; 
$cliente_id_defecto = 1;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$input_raw = file_get_contents('php://input');
$data_json = json_decode($input_raw, true);

$accion = '';
if ($data_json && isset($data_json['accion'])) {
    $accion = $data_json['accion'];
} elseif (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
}

if (empty($accion)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada."]);
    exit;
}

// ==========================================
// ACCIÓN A: CREAR PEDIDO CON DISPARADORES
// ==========================================
if ($accion === 'crear') {
    $items = $data_json['items'] ?? [];
    $metodo_pago_raw = $data_json['metodo_pago'] ?? 'EFECTIVO_USD';
    $referencia_pago = filter_var($data_json['referencia_pago'] ?? '', FILTER_DEFAULT);

    $cliente_id_input = filter_var($data_json['cliente_id'] ?? null, FILTER_VALIDATE_INT);
    $zona_id_input    = filter_var($data_json['zona_id'] ?? null, FILTER_VALIDATE_INT);
    $direccion_input  = trim($data_json['direccion_entrega'] ?? '');
    $observaciones_in = trim($data_json['observaciones'] ?? '');

    if (empty($items)) {
        echo json_encode(["status" => "error", "message" => "El pedido no contiene productos."]);
        exit;
    }

    $cliente_id = $cliente_id_input ?: $cliente_id_defecto;
    $zona_id = $zona_id_input ?: null;
    $direccion_entrega = !empty($direccion_input) ? $direccion_input : 'Despacho de Almacén Central';
    $observaciones = !empty($observaciones_in) ? $observaciones_in : null;

    // Convertir el método de pago al ENUM exacto en minúsculas exigido por la base de datos
    $metodo_pago = strtolower($metodo_pago_raw);

    try {
        // Obtener tasa de cambio oficial
        $stmt_tasa = $db->query("SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1");
        $empresa = $stmt_tasa->fetch();
        $tasa_actual = $empresa ? (float)$empresa['tasa_cambio_usd'] : 244.00;

        $db->beginTransaction();

        // 1. Calcular totales preliminares
        $total_general_usd = 0;
        foreach ($items as $item) {
            $total_general_usd += (float)$item['precio'] * (int)$item['cantidad'];
        }
        $total_general_bs = $total_general_usd * $tasa_actual;

        // 2. Insertar cabecera del pedido (el trigger autogenera 'numero_pedido')
        $query_pedido = "INSERT INTO pedidos 
            (cliente_id, zona_id, estado, direccion_entrega, subtotal_usd, subtotal_bs, total_usd, total_bs, metodo_pago, monto_recibido_bs, observaciones, fecha_pedido) 
            VALUES 
            (:cliente_id, :zona_id, 'pendiente', :dir_entrega, :sub_usd, :sub_bs, :tot_usd, :tot_bs, :metodo, :recibido_bs, :obs, NOW())";
            
        $stmt_pedido = $db->prepare($query_pedido);
        $stmt_pedido->execute([
            ':cliente_id'   => $cliente_id,
            ':zona_id'      => $zona_id,
            ':dir_entrega'  => $direccion_entrega,
            ':sub_usd'      => $total_general_usd,
            ':sub_bs'       => $total_general_bs,
            ':tot_usd'      => $total_general_usd,
            ':tot_bs'       => $total_general_bs,
            ':metodo'       => $metodo_pago,
            ':recibido_bs'  => ($metodo_pago === 'pago_movil' || $metodo_pago === 'transferencia') ? $total_general_bs : 0.00,
            ':obs'          => $observaciones
        ]);
        $pedido_id = $db->lastInsertId();

        // Sentencias preparadas reutilizables para el kárdex
        $stmt_get_saldo = $db->prepare("SELECT saldo_nuevo FROM movimientos_inventario WHERE producto_id = :p_id ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt_detalle = $db->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario_usd, precio_unitario_bs, subtotal_usd, subtotal_bs) VALUES (:pedido_id, :producto_id, :cantidad, :pu_usd, 0, 0, 0)");
        $stmt_movimiento = $db->prepare("INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, referencia, usuario_id, saldo_anterior, saldo_nuevo, fecha_movimiento) VALUES (:producto_id, 'salida', :cantidad, :motivo, :referencia, :usuario_id, :saldo_ant, :saldo_nue, NOW())");

        // 3. Iterar y procesar cada artículo vendido
        foreach ($items as $item) {
            $producto_id = (int)$item['id'];
            $cantidad_pedida = (int)$item['cantidad'];
            $precio_usd = (float)$item['precio'];

            // Consultar balance matemático en movimientos_inventario
            $stmt_get_saldo->execute([':p_id' => $producto_id]);
            $last_mov = $stmt_get_saldo->fetch();
            $saldo_anterior = $last_mov ? (int)$last_mov['saldo_nuevo'] : 0;

            if ($saldo_anterior < $cantidad_pedida) {
                throw new Exception("Operación rechazada: Stock insuficiente para el producto ID $producto_id. Disponible en Kárdex: $saldo_anterior");
            }

            // Insertar detalle
            $stmt_detalle->execute([
                ':pedido_id'   => $pedido_id,
                ':producto_id' => $producto_id,
                ':cantidad'    => $cantidad_pedida,
                ':pu_usd'      => $precio_usd
            ]);

            $saldo_nuevo = $saldo_anterior - $cantidad_pedida;

            // Registrar movimiento de salida
            $stmt_movimiento->execute([
                ':producto_id'    => $producto_id,
                ':cantidad'       => $cantidad_pedida,
                ':motivo'         => "Despacho automatizado por Venta Factura #$pedido_id",
                ':referencia'     => !empty($referencia_pago) ? $referencia_pago : 'Efectivo',
                ':usuario_id'     => $usuario_sesion_id,
                ':saldo_ant'      => $saldo_anterior,
                ':saldo_nue'      => $saldo_nuevo
            ]);
        }

        // 4. Inyectar registro en auditoría
        $datos_nuevos_json = json_encode([
            "pedido_id"    => $pedido_id,
            "cliente_id"   => $cliente_id,
            "zona_id"      => $zona_id,
            "total_usd"    => $total_general_usd,
            "metodo_pago"  => $metodo_pago,
            "referencia"   => !empty($referencia_pago) ? $referencia_pago : 'N/A'
        ]);

        $stmt_audit = $db->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, fecha_auditoria) VALUES (:u_id, 'VENTA_PROCESADA_CON_TRIGGERS', 'pedidos', :r_id, NULL, :d_nuevos, :ip, NOW())");
        $stmt_audit->execute([
            ':u_id'     => $usuario_sesion_id,
            ':r_id'     => $pedido_id,
            ':d_nuevos' => $datos_nuevos_json,
            ':ip'       => $ip_address
        ]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "¡Venta guardada correctamente! El pedido fue enviado a la cola de despacho."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error de automatización en pedidos: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error de Base de Datos: " . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ACCIÓN B: ANULAR PEDIDO Y REINTEGRAR
// ==========================================
if ($accion === 'anular') {
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);

    if (!$pedido_id) {
        echo json_encode(["status" => "error", "message" => "Parámetro de anulación inválido."]);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt_detalle = $db->prepare("SELECT producto_id, cantidad FROM detalle_pedidos WHERE pedido_id = :pedido_id");
        $stmt_detalle->execute([':pedido_id' => $pedido_id]);
        $items_anular = $stmt_detalle->fetchAll();

        $stmt_get_saldo = $db->prepare("SELECT saldo_nuevo FROM movimientos_inventario WHERE producto_id = :p_id ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt_reintegrar = $db->prepare("INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, usuario_id, saldo_anterior, saldo_nuevo, fecha_movimiento) VALUES (:producto_id, 'entrada', :cantidad, :motivo, :usuario_id, :saldo_ant, :saldo_nue, NOW())");

        foreach ($items_anular as $item) {
            $p_id = (int)$item['producto_id'];
            $cant = (int)$item['cantidad'];

            $stmt_get_saldo->execute([':p_id' => $p_id]);
            $last_mov = $stmt_get_saldo->fetch();
            $saldo_anterior = $last_mov ? (int)$last_mov['saldo_nuevo'] : 0;
            $saldo_nuevo = $saldo_anterior + $cant;

            $stmt_reintegrar->execute([
                ':producto_id' => $p_id,
                ':cantidad'    => $cant,
                ':motivo'      => "Reintegro automático por anulación de Orden #$pedido_id",
                ':usuario_id'  => $usuario_sesion_id,
                ':saldo_ant'   => $saldo_anterior,
                ':saldo_nue'   => $saldo_nuevo
            ]);
        }

        $stmt_delete = $db->prepare("DELETE FROM pedidos WHERE id = :pedido_id");
        $stmt_delete->execute([':pedido_id' => $pedido_id]);

        $db->commit();
        echo json_encode(["status" => "success", "message" => "Orden anulada con éxito. Los disparadores restituyeron los balances del inventario."]);

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log("Error al anular pedido: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al anular pedido: " . $e->getMessage()]);
    }
    exit;
}
