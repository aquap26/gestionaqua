/*M!999999\- enable the sandbox mode */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `asignacion_transportistas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignacion_transportistas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transportista_id` int(11) NOT NULL,
  `zona_id` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_transportista_zona` (`transportista_id`,`zona_id`),
  KEY `zona_id` (`zona_id`),
  CONSTRAINT `asignacion_transportistas_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `asignacion_transportistas_ibfk_2` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_auditoria` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configuracion_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_empresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_empresa` varchar(100) NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `rif` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `tasa_cambio_usd` decimal(10,4) DEFAULT 36.5000,
  `fecha_actualizacion_tasa` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `direccion_entrega` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rif` (`rif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario_usd` decimal(10,2) NOT NULL,
  `precio_unitario_bs` decimal(15,2) NOT NULL,
  `subtotal_usd` decimal(10,2) NOT NULL,
  `subtotal_bs` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pedido_producto` (`pedido_id`,`producto_id`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `detalle_pedidos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_pedidos_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `tr_detalle_pedido_before_insert` BEFORE INSERT ON `detalle_pedidos` FOR EACH ROW
BEGIN
    DECLARE tasa_cambio DECIMAL(10,4);
    DECLARE precio_bs DECIMAL(15,2);

    SELECT tasa_cambio_usd INTO tasa_cambio FROM configuracion_empresa LIMIT 1;
    SET precio_bs = NEW.precio_unitario_usd * tasa_cambio;

    SET NEW.precio_unitario_bs = precio_bs;
    SET NEW.subtotal_usd = NEW.cantidad * NEW.precio_unitario_usd;
    SET NEW.subtotal_bs = NEW.cantidad * precio_bs;
END
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `stock_actual` int(11) DEFAULT 0,
  `stock_reservado` int(11) DEFAULT 0,
  `stock_disponible` int(11) GENERATED ALWAYS AS (`stock_actual` - `stock_reservado`) STORED,
  `ultima_entrada` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_producto` (`producto_id`),
  KEY `idx_inventario_stock` (`stock_actual`),
  CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `tr_stock_minimo` AFTER UPDATE ON `inventario` FOR EACH ROW
BEGIN
    DECLARE stock_min INT;
    DECLARE producto_nombre VARCHAR(100);

    SELECT p.stock_minimo, p.nombre INTO stock_min, producto_nombre
    FROM productos p WHERE p.id = NEW.producto_id;

    IF NEW.stock_actual <= stock_min AND OLD.stock_actual > stock_min THEN
        INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_id)
        SELECT u.id, 'stock_bajo', 'Stock Bajo',
               CONCAT('El producto "', producto_nombre, '" tiene stock bajo (', NEW.stock_actual, ' unidades)'),
               NEW.producto_id
        FROM usuarios u WHERE u.rol = 'administrador';
    END IF;
END
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `mensajes_chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mensajes_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remitente_id` int(11) NOT NULL,
  `destinatario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `destinatario_id` (`destinatario_id`),
  KEY `idx_chat` (`remitente_id`,`destinatario_id`),
  KEY `idx_fecha` (`fecha_envio`),
  CONSTRAINT `mensajes_chat_ibfk_1` FOREIGN KEY (`remitente_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `mensajes_chat_ibfk_2` FOREIGN KEY (`destinatario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimientos_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `saldo_anterior` int(11) DEFAULT NULL,
  `saldo_nuevo` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_fecha` (`fecha_movimiento`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `movimientos_inventario_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `tr_movimiento_entrada` AFTER INSERT ON `movimientos_inventario` FOR EACH ROW
BEGIN
    IF NEW.tipo_movimiento = 'entrada' THEN
        UPDATE inventario
        SET stock_actual = stock_actual + NEW.cantidad,
            ultima_entrada = CURDATE()
        WHERE producto_id = NEW.producto_id;


        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_address)
        VALUES (NEW.usuario_id, 'ENTRADA_INVENTARIO', 'movimientos_inventario', NEW.id,
                JSON_OBJECT('producto_id', NEW.producto_id, 'cantidad', NEW.cantidad),
                COALESCE(@user_ip, '127.0.0.1'));

    ELSEIF NEW.tipo_movimiento = 'salida' THEN
        UPDATE inventario
        SET stock_actual = GREATEST(0, stock_actual - NEW.cantidad)
        WHERE producto_id = NEW.producto_id;


        INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_address)
        VALUES (NEW.usuario_id, 'SALIDA_INVENTARIO', 'movimientos_inventario', NEW.id,
                JSON_OBJECT('producto_id', NEW.producto_id, 'cantidad', NEW.cantidad),
                COALESCE(@user_ip, '127.0.0.1'));

    ELSEIF NEW.tipo_movimiento = 'ajuste' THEN
        UPDATE inventario
        SET stock_actual = NEW.saldo_nuevo
        WHERE producto_id = NEW.producto_id;
    END IF;
END
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('pedido_nuevo','pedido_entregado','mensaje_chat','stock_bajo') NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `referencia_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_tipo` (`tipo`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_pedido` varchar(20) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `transportista_id` int(11) DEFAULT NULL,
  `zona_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','confirmado','preparando','en_transito','entregado','cancelado') DEFAULT 'pendiente',
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_entrega_estimada` datetime DEFAULT NULL,
  `fecha_entrega_real` datetime DEFAULT NULL,
  `direccion_entrega` text NOT NULL,
  `subtotal_usd` decimal(10,2) DEFAULT 0.00,
  `subtotal_bs` decimal(15,2) DEFAULT 0.00,
  `total_usd` decimal(10,2) DEFAULT 0.00,
  `total_bs` decimal(15,2) DEFAULT 0.00,
  `metodo_pago` enum('efectivo_usd','efectivo_bs','transferencia','pago_movil','divisas') NOT NULL,
  `monto_recibido_usd` decimal(10,2) DEFAULT 0.00,
  `monto_recibido_bs` decimal(15,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_pedido` (`numero_pedido`),
  KEY `transportista_id` (`transportista_id`),
  KEY `zona_id` (`zona_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_pedido`),
  KEY `idx_pedidos_fecha_estado` (`fecha_pedido`,`estado`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`transportista_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `tr_pedido_before_insert` BEFORE INSERT ON `pedidos` FOR EACH ROW
BEGIN
    DECLARE ultimo_numero INT DEFAULT 0;
    SELECT MAX(CAST(SUBSTRING(numero_pedido, 5) AS UNSIGNED)) INTO ultimo_numero
    FROM pedidos WHERE numero_pedido LIKE 'PED-%';

    SET NEW.numero_pedido = CONCAT('PED-', LPAD(IFNULL(ultimo_numero, 0) + 1, 6, '0'));
END
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_usd` decimal(10,2) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `tipo` enum('producto','servicio') NOT NULL DEFAULT 'producto',
  `stock_minimo` int(11) DEFAULT 5,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_productos_categoria_activo` (`categoria_id`,`activo`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 */ /*!50003 TRIGGER `tr_producto_after_insert` AFTER INSERT ON `productos` FOR EACH ROW
BEGIN
    INSERT INTO inventario (producto_id, stock_actual) VALUES (NEW.id, 0);
END
*/;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `tipo_documento` varchar(10) DEFAULT 'V',
  `fecha_nacimiento` date DEFAULT NULL,
  `rol` enum('administrador','cliente','transportista') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_rol` (`rol`),
  KEY `idx_usuarios_rol_activo` (`rol`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vista_productos_vendidos`;
/*!50001 DROP VIEW IF EXISTS `vista_productos_vendidos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vista_productos_vendidos` AS SELECT
 NULL AS `id`,
 NULL AS `nombre`,
 NULL AS `codigo`,
 NULL AS `total_vendido`,
 NULL AS `ingresos_usd`,
 NULL AS `categoria` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vista_ventas_periodo`;
/*!50001 DROP VIEW IF EXISTS `vista_ventas_periodo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `vista_ventas_periodo` AS SELECT
 NULL AS `id`,
 NULL AS `numero_pedido`,
 NULL AS `cliente`,
 NULL AS `fecha_pedido`,
 NULL AS `total_usd`,
 NULL AS `total_bs`,
 NULL AS `estado`,
 NULL AS `nombre_zona`,
 NULL AS `transportista` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `zonas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zonas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_zona` varchar(100) NOT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_zona` (`nombre_zona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetDashboardStats` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `GetDashboardStats`()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente' AND activo = TRUE) as total_clientes,
        (SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente') as pedidos_pendientes,
        (SELECT COUNT(*) FROM pedidos WHERE estado = 'en_transito') as pedidos_en_transito,
        (SELECT SUM(total_usd) FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()) as ventas_hoy_usd,
        (SELECT SUM(total_usd) FROM pedidos WHERE fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as ventas_mes_usd,
        (SELECT COUNT(*) FROM productos WHERE activo = TRUE) as productos_activos,
        (SELECT COUNT(*) FROM inventario i JOIN productos p ON i.producto_id = p.id WHERE i.stock_actual <= p.stock_minimo) as productos_stock_bajo,
        (SELECT tasa_cambio_usd FROM configuracion_empresa LIMIT 1) as tasa_cambio;
END
;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetProductosStockBajo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `GetProductosStockBajo`()
BEGIN
    SELECT
        pr.codigo,
        pr.nombre,
        c.nombre as categoria,
        i.stock_actual,
        pr.stock_minimo,
        CASE
            WHEN i.stock_actual = 0 THEN 'SIN STOCK'
            WHEN i.stock_actual <= pr.stock_minimo THEN 'STOCK BAJO'
            ELSE 'NORMAL'
        END as estado_stock
    FROM productos pr
    JOIN categorias c ON pr.categoria_id = c.id
    JOIN inventario i ON pr.id = i.producto_id
    WHERE pr.activo = TRUE
    ORDER BY i.stock_actual ASC, pr.nombre;
END
;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetTopClientes` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_unicode_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `GetTopClientes`(IN limit_count INT)
BEGIN
    SELECT
        CONCAT(u.nombre, ' ', u.apellido) as cliente,
        u.email,
        COUNT(p.id) as total_pedidos,
        ROUND(SUM(p.total_usd), 2) as total_gastado_usd
    FROM usuarios u
    JOIN pedidos p ON u.id = p.cliente_id
    WHERE u.rol = 'cliente' AND u.activo = TRUE
    GROUP BY u.id, u.nombre, u.apellido, u.email
    ORDER BY total_pedidos DESC, total_gastado_usd DESC
    LIMIT limit_count;
END
;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `vista_productos_vendidos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vista_productos_vendidos` AS select `pr`.`id` AS `id`,`pr`.`nombre` AS `nombre`,`pr`.`codigo` AS `codigo`,sum(`dp`.`cantidad`) AS `total_vendido`,sum(`dp`.`subtotal_usd`) AS `ingresos_usd`,`c`.`nombre` AS `categoria` from ((`detalle_pedidos` `dp` join `productos` `pr` on(`dp`.`producto_id` = `pr`.`id`)) join `categorias` `c` on(`pr`.`categoria_id` = `c`.`id`)) group by `pr`.`id`,`pr`.`nombre`,`pr`.`codigo`,`c`.`nombre` order by sum(`dp`.`cantidad`) desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vista_ventas_periodo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `vista_ventas_periodo` AS select `p`.`id` AS `id`,`p`.`numero_pedido` AS `numero_pedido`,concat(`u`.`nombre`,' ',`u`.`apellido`) AS `cliente`,`p`.`fecha_pedido` AS `fecha_pedido`,`p`.`total_usd` AS `total_usd`,`p`.`total_bs` AS `total_bs`,`p`.`estado` AS `estado`,`z`.`nombre_zona` AS `nombre_zona`,concat(`t`.`nombre`,' ',`t`.`apellido`) AS `transportista` from (((`pedidos` `p` join `usuarios` `u` on(`p`.`cliente_id` = `u`.`id`)) left join `zonas` `z` on(`p`.`zona_id` = `z`.`id`)) left join `usuarios` `t` on(`p`.`transportista_id` = `t`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;
