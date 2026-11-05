-- Generic, non-sensitive reference and test data for GestionAqua installation.

-- Asegurar columna codigo_postal en zonas si no existe
ALTER TABLE zonas ADD COLUMN IF NOT EXISTS codigo_postal VARCHAR(20) DEFAULT NULL AFTER nombre_zona;

-- 1. Configuración de Empresa (Guacara, Edo. Carabobo)
INSERT INTO configuracion_empresa (id, nombre_empresa, razon_social, rif, telefono, email, direccion, tasa_cambio_usd)
VALUES (1, 'AQUA - Purificadora & Distribuidora', 'Inversiones Aqua Guacara C.A.', 'J-40123456-7', '+58 412-5551234', 'contacto@gestionaqua.com', 'Carretera Nacional Guacara - San Joaquín, Sector El Samán, Galpón 4, Guacara, Edo. Carabobo', 45.5000)
ON DUPLICATE KEY UPDATE 
    nombre_empresa = VALUES(nombre_empresa),
    razon_social = VALUES(razon_social),
    rif = VALUES(rif),
    telefono = VALUES(telefono),
    email = VALUES(email),
    direccion = VALUES(direccion),
    tasa_cambio_usd = VALUES(tasa_cambio_usd);

-- 2. Categorías
INSERT INTO categorias (id, nombre, descripcion, activo) VALUES
    (1, 'Botellas Vacías', 'Botellones y envases vacíos de diferentes capacidades', 1),
    (2, 'Botellas Llenas', 'Botellones y envases con agua 100% purificada', 1),
    (3, 'Servicios de Llenado', 'Recargas de agua y servicios de sanitizado', 1),
    (4, 'Accesorios', 'Dispensadores, bombas, racks y repuestos', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre), 
    descripcion = VALUES(descripcion), 
    activo = VALUES(activo);

-- 3. Zonas de Despacho (Guacara y Alrededores - Carabobo)
INSERT INTO zonas (id, nombre_zona, codigo_postal, descripcion, activo) VALUES
    (1, 'Ciudad Alianza', '2015', '1ra a 5ta Etapa, Parque Alianza, Av. Principal y zonas aledañas', 1),
    (2, 'Guacara Centro', '2015', 'Casco Central, Calle Piar, Marqués del Toro, San Agustín, Plaza Bolívar', 1),
    (3, 'Yagua', '2016', 'Parroquia Yagua: El Cabrito, El Cardonal, Tronconero, San Rafael, Av. Pocaterra', 1),
    (4, 'El Samán - Vigirima', '2015', 'Urb. El Samán, Loma Linda, Piedras Negras y eje Vigirima', 1),
    (5, 'Los Naranjos - Aragüita', '2015', 'Urb. Los Naranjos, Aragüita, La Coromoto, Augusto Malavé Villalba', 1),
    (6, 'San Joaquín', '2017', 'Alrededores: Centro de San Joaquín, La Pradera, Los Jabillos', 1),
    (7, 'Los Guayos - Paraparal', '2011', 'Alrededores: Paraparal, Casco Central de Los Guayos, Las Agüitas', 1)
ON DUPLICATE KEY UPDATE 
    nombre_zona = VALUES(nombre_zona), 
    codigo_postal = VALUES(codigo_postal), 
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

-- 4. Usuarios de prueba (Contraseña para todos: Aqua12345*)
INSERT INTO usuarios (id, nombre, apellido, email, password, telefono, cedula, direccion, tipo_documento, rol, activo) VALUES
    (1, 'Administrador', 'Sistema', 'admin@ejemplo.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0414-0001122', '10000001', 'Sede Central Aqua, Galpón El Samán, Guacara', 'V', 'administrador', 1),
    -- Transportistas
    (2, 'Juan Carlos', 'Martínez', 'juan.martinez@gestionaqua.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0414-1112233', '18555123', 'Urb. Ciudad Alianza, 2da Etapa, Manzana F, Guacara', 'V', 'transportista', 1),
    (3, 'Pedro José', 'Infante', 'pedro.infante@gestionaqua.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0424-2223344', '20111456', 'Sector El Samán, Calle Los Claveles #22, Guacara', 'V', 'transportista', 1),
    (4, 'Marcos Antonio', 'Silva', 'marcos.silva@gestionaqua.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0412-3334455', '22333789', 'Parroquia Yagua, Sector El Cabrito, Guacara', 'V', 'transportista', 1),
    -- Clientes
    (5, 'Roberto Carlos', 'Gómez', 'roberto.gomez@gmail.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0416-4445566', '15444321', 'Urb. Ciudad Alianza, 4ta Etapa, Manzana 18, Casa 12, Guacara', 'V', 'cliente', 1),
    (6, 'Ana María', 'Mendoza', 'ana.mendoza@gmail.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0424-5556677', '19888777', 'Urb. Los Naranjos, Vereda 8, Casa 04, Guacara', 'V', 'cliente', 1),
    (7, 'Panadería La Espiga', 'C.A.', 'compras@laespiga.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0245-5641234', '309876541', 'Av. Piar cruce con Calle Sucre, CC Guacara Plaza, Casco Central Guacara', 'J', 'cliente', 1),
    (8, 'Consultorio Odontológico', 'Sonrisas', 'contacto@sonrisas.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0245-5712345', '405556660', 'CC Alianza Mall, Nivel 1, Local 18, Ciudad Alianza, Guacara', 'J', 'cliente', 1),
    (9, 'Gimnasio FitZone', 'Express', 'fitzone.gym@gmail.com', '$2y$10$1zpzZrIZJ3dpoYT80pklQ.gQ8t/npJc89q8jwfVNCEfPm9W.io29.', '0414-7778899', '501112223', 'Av. Principal de Ciudad Alianza, frente a la Cruz Roja, Guacara', 'J', 'cliente', 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    apellido = VALUES(apellido),
    telefono = VALUES(telefono),
    direccion = VALUES(direccion),
    activo = VALUES(activo);

-- 5. Asignación de Transportistas a Zonas de Guacara
INSERT INTO asignacion_transportistas (id, transportista_id, zona_id, activo) VALUES
    (1, 2, 1, 1), -- Juan Carlos a Ciudad Alianza
    (2, 3, 2, 1), -- Pedro José a Guacara Centro
    (3, 4, 3, 1), -- Marcos Antonio a Yagua
    (4, 3, 4, 1), -- Pedro José a El Samán - Vigirima
    (5, 2, 5, 1)  -- Juan Carlos a Los Naranjos - Aragüita
ON DUPLICATE KEY UPDATE activo = VALUES(activo);

-- 6. Catálogo de Productos (Botellones, Recargas, Agua y Accesorios)
INSERT INTO productos (id, codigo, nombre, descripcion, precio_usd, categoria_id, tipo, stock_minimo, activo) VALUES
    -- Categoría 1: Botellas Vacías
    (1, 'BOT-20L-VAC', 'Botellón 20L Policarbonato Vacío (Asa Ergonómica)', 'Botellón de 20L de policarbonato virgen de alta resistencia con asa integrada.', 8.50, 1, 'producto', 10, 1),
    (2, 'BOT-20L-PET', 'Botellón 20L PET Transparente Vacío', 'Envase PET liviano grado alimenticio para 20 litros.', 6.00, 1, 'producto', 10, 1),
    (3, 'BOT-12L-VAC', 'Botellón 12L Compacto Vacío', 'Botellón mediano de 12 litros con asa, fácil de levantar y transportar.', 5.00, 1, 'producto', 8, 1),
    (4, 'GAR-5L-VAC', 'Garrafa 5L con Asa Vacía', 'Garrafa plástica resistente de 5 litros con tapa rosca.', 2.00, 1, 'producto', 15, 1),

    -- Categoría 2: Botellas Llenas
    (5, 'BOT-20L-LLENO', 'Botellón 20L Agua Purificada (Nuevo + Lleno)', 'Botellón nuevo de policarbonato con 20L de agua purificada por ósmosis inversa y ozono.', 11.00, 2, 'producto', 15, 1),
    (6, 'BOT-12L-LLENO', 'Botellón 12L Agua Purificada (Nuevo + Lleno)', 'Botellón nuevo de 12L lleno con agua purificada microfiltrada.', 7.50, 2, 'producto', 10, 1),
    (7, 'GAR-5L-LLENA', 'Garrafa 5L Agua Purificada', 'Garrafa sellada de 5 litros de agua pura tratada lista para consumir.', 3.50, 2, 'producto', 20, 1),
    (8, 'PACK-15L-6U', 'Pack Agua Purificada 6 x 1.5L', 'Paquete termocontraíble con 6 botellas de 1.5 litros cada una.', 4.50, 2, 'producto', 12, 1),
    (9, 'CAJA-500ML', 'Caja Agua Purificada 24 x 500ml', 'Caja de 24 botellitas individuales de 500ml ideales para eventos y oficinas.', 7.00, 2, 'producto', 15, 1),

    -- Categoría 3: Servicios de Llenado / Recargas
    (10, 'REC-20L', 'Recarga Botellón 20L Agua Purificada', 'Recarga de botellón de 20L con agua purificada bajo normas sanitarias y tapa sellada.', 2.50, 3, 'servicio', 0, 1),
    (11, 'REC-12L', 'Recarga Botellón 12L Agua Purificada', 'Recarga de botellón mediano de 12L con agua purificada y precinto de seguridad.', 1.80, 3, 'servicio', 0, 1),
    (12, 'REC-5L', 'Recarga Garrafa 5L Agua Purificada', 'Recarga para garrafa de 5 litros con agua microfiltrada.', 1.00, 3, 'servicio', 0, 1),
    (13, 'SRV-LAV', 'Lavado y Sanitizado Profundo de Botellón', 'Servicio especializado de lavado interno a vapor y desinfección grado alimenticio.', 1.00, 3, 'servicio', 0, 1),

    -- Categoría 4: Accesorios
    (14, 'ACC-DISP-USB', 'Dispensador Eléctrico USB para Botellón', 'Bomba dispensadora recargable por USB con luz LED, apta para botellones de 20L y 12L.', 12.00, 4, 'producto', 6, 1),
    (15, 'ACC-BOMB-MAN', 'Bomba Manual a Presión para Botellón', 'Bomba mecánica de fuelle manual fácil de instalar sobre el pico del botellón.', 5.00, 4, 'producto', 8, 1),
    (16, 'ACC-SOP-VALV', 'Soporte Metálico de Mesa con Válvula', 'Base de acero cromado con grifo dosificador antiderrame para botellón de 20L.', 9.50, 4, 'producto', 5, 1),
    (17, 'ACC-RACK-3B', 'Rack Apilador Metálico para 3 Botellones', 'Organizador vertical de acero esmaltado para guardar 3 botellones de 20L ahorrando espacio.', 28.00, 4, 'producto', 3, 1),
    (18, 'ACC-TAP-10P', 'Pack 10 Tapas Antiderrame para Botellón 20L', 'Repuesto de tapas reutilizables de silicona grado alimenticio antiderrame.', 2.00, 4, 'producto', 10, 1),
    (19, 'ACC-ASA-PORT', 'Asa Portátil Ergonómica para Botellón', 'Manija ergonómica de polipropileno para cargar botellones de 20L sin esfuerzo.', 3.50, 4, 'producto', 8, 1)
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    precio_usd = VALUES(precio_usd),
    stock_minimo = VALUES(stock_minimo),
    activo = VALUES(activo);

-- 7. Carga de Inventario Inicial y Movimientos Kárdex
DELETE FROM movimientos_inventario WHERE motivo = 'Carga inicial de inventario';

INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, motivo, referencia, usuario_id, saldo_anterior, saldo_nuevo, fecha_movimiento) VALUES
    (1, 'entrada', 80, 'Carga inicial de inventario', 'INV-INI-001', 1, 0, 80, NOW() - INTERVAL 5 DAY),
    (2, 'entrada', 60, 'Carga inicial de inventario', 'INV-INI-002', 1, 0, 60, NOW() - INTERVAL 5 DAY),
    (3, 'entrada', 45, 'Carga inicial de inventario', 'INV-INI-003', 1, 0, 45, NOW() - INTERVAL 5 DAY),
    (4, 'entrada', 90, 'Carga inicial de inventario', 'INV-INI-004', 1, 0, 90, NOW() - INTERVAL 5 DAY),
    (5, 'entrada', 150, 'Carga inicial de inventario', 'INV-INI-005', 1, 0, 150, NOW() - INTERVAL 5 DAY),
    (6, 'entrada', 50, 'Carga inicial de inventario', 'INV-INI-006', 1, 0, 50, NOW() - INTERVAL 5 DAY),
    (7, 'entrada', 80, 'Carga inicial de inventario', 'INV-INI-007', 1, 0, 80, NOW() - INTERVAL 5 DAY),
    (8, 'entrada', 40, 'Carga inicial de inventario', 'INV-INI-008', 1, 0, 40, NOW() - INTERVAL 5 DAY),
    (9, 'entrada', 50, 'Carga inicial de inventario', 'INV-INI-009', 1, 0, 50, NOW() - INTERVAL 5 DAY),
    (10, 'entrada', 500, 'Carga inicial de inventario', 'INV-INI-010', 1, 0, 500, NOW() - INTERVAL 5 DAY),
    (11, 'entrada', 300, 'Carga inicial de inventario', 'INV-INI-011', 1, 0, 300, NOW() - INTERVAL 5 DAY),
    (12, 'entrada', 200, 'Carga inicial de inventario', 'INV-INI-012', 1, 0, 200, NOW() - INTERVAL 5 DAY),
    (13, 'entrada', 200, 'Carga inicial de inventario', 'INV-INI-013', 1, 0, 200, NOW() - INTERVAL 5 DAY),
    (14, 'entrada', 35, 'Carga inicial de inventario', 'INV-INI-014', 1, 0, 35, NOW() - INTERVAL 5 DAY),
    (15, 'entrada', 40, 'Carga inicial de inventario', 'INV-INI-015', 1, 0, 40, NOW() - INTERVAL 5 DAY),
    (16, 'entrada', 20, 'Carga inicial de inventario', 'INV-INI-016', 1, 0, 20, NOW() - INTERVAL 5 DAY),
    (17, 'entrada', 12, 'Carga inicial de inventario', 'INV-INI-017', 1, 0, 12, NOW() - INTERVAL 5 DAY),
    (18, 'entrada', 60, 'Carga inicial de inventario', 'INV-INI-018', 1, 0, 60, NOW() - INTERVAL 5 DAY),
    (19, 'entrada', 30, 'Carga inicial de inventario', 'INV-INI-019', 1, 0, 30, NOW() - INTERVAL 5 DAY);

-- Actualizar stock explícito en inventario
UPDATE inventario SET stock_actual = 80, ultima_entrada = CURDATE() WHERE producto_id = 1;
UPDATE inventario SET stock_actual = 60, ultima_entrada = CURDATE() WHERE producto_id = 2;
UPDATE inventario SET stock_actual = 45, ultima_entrada = CURDATE() WHERE producto_id = 3;
UPDATE inventario SET stock_actual = 90, ultima_entrada = CURDATE() WHERE producto_id = 4;
UPDATE inventario SET stock_actual = 150, ultima_entrada = CURDATE() WHERE producto_id = 5;
UPDATE inventario SET stock_actual = 50, ultima_entrada = CURDATE() WHERE producto_id = 6;
UPDATE inventario SET stock_actual = 80, ultima_entrada = CURDATE() WHERE producto_id = 7;
UPDATE inventario SET stock_actual = 40, ultima_entrada = CURDATE() WHERE producto_id = 8;
UPDATE inventario SET stock_actual = 50, ultima_entrada = CURDATE() WHERE producto_id = 9;
UPDATE inventario SET stock_actual = 500, ultima_entrada = CURDATE() WHERE producto_id = 10;
UPDATE inventario SET stock_actual = 300, ultima_entrada = CURDATE() WHERE producto_id = 11;
UPDATE inventario SET stock_actual = 200, ultima_entrada = CURDATE() WHERE producto_id = 12;
UPDATE inventario SET stock_actual = 200, ultima_entrada = CURDATE() WHERE producto_id = 13;
UPDATE inventario SET stock_actual = 35, ultima_entrada = CURDATE() WHERE producto_id = 14;
UPDATE inventario SET stock_actual = 40, ultima_entrada = CURDATE() WHERE producto_id = 15;
UPDATE inventario SET stock_actual = 20, ultima_entrada = CURDATE() WHERE producto_id = 16;
UPDATE inventario SET stock_actual = 12, ultima_entrada = CURDATE() WHERE producto_id = 17;
UPDATE inventario SET stock_actual = 60, ultima_entrada = CURDATE() WHERE producto_id = 18;
UPDATE inventario SET stock_actual = 30, ultima_entrada = CURDATE() WHERE producto_id = 19;

-- 8. Pedidos de Prueba (Ubicados en Guacara)
INSERT INTO pedidos (id, numero_pedido, cliente_id, transportista_id, zona_id, estado, fecha_pedido, fecha_entrega_estimada, fecha_entrega_real, direccion_entrega, subtotal_usd, subtotal_bs, total_usd, total_bs, metodo_pago, monto_recibido_usd, monto_recibido_bs, observaciones) VALUES
    (1, 'PED-000001', 7, 2, 2, 'entregado', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY + INTERVAL 2 HOUR, NOW() - INTERVAL 2 DAY + INTERVAL 3 HOUR, 'Av. Piar cruce con Calle Sucre, CC Guacara Plaza, Casco Central Guacara', 47.00, 2138.50, 47.00, 2138.50, 'transferencia', 47.00, 2138.50, 'Entregado en puerta principal del local comercial'),
    (2, 'PED-000002', 9, 2, 1, 'en_transito', NOW() - INTERVAL 4 HOUR, NOW() + INTERVAL 2 HOUR, NULL, 'Av. Principal de Ciudad Alianza, frente a la Cruz Roja, Guacara', 95.00, 4322.50, 95.00, 4322.50, 'pago_movil', 0.00, 4322.50, 'Despacho prioritario para reabastecer área de entrenamiento'),
    (3, 'PED-000003', 5, 2, 1, 'preparando', NOW() - INTERVAL 2 HOUR, NOW() + INTERVAL 3 HOUR, NULL, 'Urb. Ciudad Alianza, 4ta Etapa, Manzana 18, Casa 12, Guacara', 10.00, 455.00, 10.00, 455.00, 'efectivo_usd', 10.00, 0.00, 'Paga en efectivo con billete de $10 exacto'),
    (4, 'PED-000004', 6, NULL, 5, 'confirmado', NOW() - INTERVAL 45 MINUTE, NOW() + INTERVAL 4 HOUR, NULL, 'Urb. Los Naranjos, Vereda 8, Casa 04, Guacara', 23.00, 1046.50, 23.00, 1046.50, 'pago_movil', 0.00, 1046.50, 'Llamar al cliente 10 minutos antes de llegar'),
    (5, 'PED-000005', 8, NULL, 1, 'pendiente', NOW() - INTERVAL 15 MINUTE, NOW() + INTERVAL 5 HOUR, NULL, 'CC Alianza Mall, Nivel 1, Local 18, Ciudad Alianza, Guacara', 17.00, 773.50, 17.00, 773.50, 'divisas', 0.00, 0.00, 'Entregar en recepción del consultorio')
ON DUPLICATE KEY UPDATE 
    estado = VALUES(estado),
    zona_id = VALUES(zona_id),
    direccion_entrega = VALUES(direccion_entrega),
    total_usd = VALUES(total_usd),
    total_bs = VALUES(total_bs);

-- 9. Detalle de Pedidos
INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario_usd, precio_unitario_bs, subtotal_usd, subtotal_bs) VALUES
    -- Pedido 1
    (1, 10, 10, 2.50, 113.75, 25.00, 1137.50),
    (1, 5, 2, 11.00, 500.50, 22.00, 1001.00),

    -- Pedido 2
    (2, 5, 5, 11.00, 500.50, 55.00, 2502.50),
    (2, 17, 1, 28.00, 1274.00, 28.00, 1274.00),
    (2, 14, 1, 12.00, 546.00, 12.00, 546.00),

    -- Pedido 3
    (3, 10, 2, 2.50, 113.75, 5.00, 227.50),
    (3, 15, 1, 5.00, 227.50, 5.00, 227.50),

    -- Pedido 4
    (4, 5, 1, 11.00, 500.50, 11.00, 500.50),
    (4, 14, 1, 12.00, 546.00, 12.00, 546.00),

    -- Pedido 5
    (5, 10, 3, 2.50, 113.75, 7.50, 341.25),
    (5, 16, 1, 9.50, 432.25, 9.50, 432.25)
ON DUPLICATE KEY UPDATE 
    cantidad = VALUES(cantidad),
    precio_unitario_usd = VALUES(precio_unitario_usd),
    subtotal_usd = VALUES(subtotal_usd);

-- 10. Notificaciones
INSERT INTO notificaciones (id, usuario_id, tipo, titulo, mensaje, leida, fecha_creacion, referencia_id) VALUES
    (1, 1, 'pedido_nuevo', 'Nuevo Pedido Registrado', 'Consultorio Odontológico Sonrisas (Ciudad Alianza) ha generado el pedido PED-000005 por $17.00 USD.', 0, NOW() - INTERVAL 15 MINUTE, 5),
    (2, 1, 'pedido_nuevo', 'Nuevo Pedido Registrado', 'Ana María Mendoza (Los Naranjos) ha generado el pedido PED-000004 por $23.00 USD.', 0, NOW() - INTERVAL 45 MINUTE, 4),
    (3, 2, 'pedido_nuevo', 'Ruta Asignada', 'Se te ha asignado el despacho del pedido PED-000003 en Ciudad Alianza.', 1, NOW() - INTERVAL 1 HOUR, 3),
    (4, 2, 'pedido_nuevo', 'Despacho en Camino', 'Pedido PED-000002 en ruta hacia Gimnasio FitZone en Ciudad Alianza.', 0, NOW() - INTERVAL 3 HOUR, 2)
ON DUPLICATE KEY UPDATE 
    titulo = VALUES(titulo),
    mensaje = VALUES(mensaje),
    leida = VALUES(leida);
