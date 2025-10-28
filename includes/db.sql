-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-10-2025 a las 01:45:00
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `despacho_contable`
--
CREATE DATABASE IF NOT EXISTS `despacho_contable` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `despacho_contable`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cfdis_emitidas`
--

CREATE TABLE `cfdis_emitidas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo` varchar(20) DEFAULT 'emitida',
  `tipo_comprobante` varchar(50) DEFAULT 'Ingreso',
  `folio_interno` varchar(50) DEFAULT NULL,
  `forma_pago` varchar(50) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `folio_fiscal` varchar(36) DEFAULT NULL,
  `fecha_emision` datetime DEFAULT NULL,
  `rfc_emisor` varchar(20) NOT NULL,
  `nombre_emisor` varchar(100) NOT NULL,
  `nombre_receptor` varchar(150) DEFAULT NULL,
  `rfc_receptor` varchar(13) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `tasa0_base` decimal(18,2) DEFAULT 0.00,
  `tasa16_base` decimal(18,2) DEFAULT 0.00,
  `iva_importe` decimal(18,2) DEFAULT 0.00,
  `ieps_importe` decimal(18,2) DEFAULT 0.00,
  `isr_importe` decimal(18,2) DEFAULT 0.00,
  `retencion_iva` decimal(18,2) DEFAULT 0.00,
  `retencion_ieps` decimal(18,2) DEFAULT 0.00,
  `retencion_isr` decimal(18,2) DEFAULT 0.00,
  `tasa0_old` decimal(12,2) DEFAULT 0.00,
  `tasa16_old` decimal(12,2) DEFAULT 0.00,
  `iva_old` decimal(12,2) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `uuid_relacionado` varchar(36) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `total_raw` varchar(30) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'vigente',
  `estado_sat` varchar(32) DEFAULT NULL,
  `codigo_estatus_sat` varchar(255) DEFAULT NULL,
  `es_cancelable_sat` varchar(64) DEFAULT NULL,
  `estatus_cancelacion_sat` varchar(64) DEFAULT NULL,
  `fecha_consulta_sat` datetime DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cfdis_recibidas`
--

CREATE TABLE `cfdis_recibidas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo` varchar(20) DEFAULT 'recibida',
  `tipo_comprobante` varchar(50) DEFAULT 'Ingreso',
  `forma_pago` varchar(50) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `folio_fiscal` varchar(36) DEFAULT NULL,
  `fecha_certificacion` datetime DEFAULT NULL,
  `nombre_emisor` varchar(150) DEFAULT NULL,
  `rfc_emisor` varchar(13) DEFAULT NULL,
  `rfc_receptor` varchar(20) NOT NULL,
  `nombre_receptor` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `tasa0_base` decimal(18,2) DEFAULT 0.00,
  `tasa16_base` decimal(18,2) DEFAULT 0.00,
  `iva_importe` decimal(18,2) DEFAULT 0.00,
  `ieps_importe` decimal(18,2) DEFAULT 0.00,
  `isr_importe` decimal(18,2) DEFAULT 0.00,
  `tasa0_old` decimal(12,2) DEFAULT 0.00,
  `tasa16_old` decimal(12,2) DEFAULT 0.00,
  `iva_old` decimal(12,2) DEFAULT NULL,
  `total` decimal(12,2) DEFAULT NULL,
  `total_raw` varchar(30) DEFAULT NULL,
  `retencion_iva` decimal(12,2) DEFAULT 0.00,
  `retencion_isr` decimal(12,2) DEFAULT 0.00,
  `retencion_ieps` decimal(12,2) DEFAULT 0.00,
  `uuid_relacionado` varchar(36) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'vigente',
  `estado_sat` varchar(32) DEFAULT NULL,
  `codigo_estatus_sat` varchar(255) DEFAULT NULL,
  `es_cancelable_sat` varchar(64) DEFAULT NULL,
  `estatus_cancelacion_sat` varchar(64) DEFAULT NULL,
  `fecha_consulta_sat` datetime DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `actividad` varchar(150) DEFAULT NULL,
  `rfc` varchar(13) NOT NULL,
  `regimen_fiscal` varchar(100) DEFAULT NULL,
  `inicio_regimen` date DEFAULT NULL,
  `domicilio_fiscal` text DEFAULT NULL,
  `codigo_postal` varchar(5) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fecha_alta` date NOT NULL,
  `estatus` enum('activo','suspendido','baja') DEFAULT 'activo',
  `responsable_id` int(11) DEFAULT NULL,
  `honorarios` decimal(12,2) DEFAULT 0.00,
  `periodicidad` enum('mensual','anual') NOT NULL DEFAULT 'mensual',
  `ultima_declaracion_mes` tinyint(3) UNSIGNED DEFAULT NULL,
  `ultima_declaracion_anio` smallint(5) UNSIGNED DEFAULT NULL,
  `limite_declaracion_dia` tinyint(3) UNSIGNED DEFAULT 17,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `razon_social`, `actividad`, `rfc`, `regimen_fiscal`, `inicio_regimen`, `domicilio_fiscal`, `codigo_postal`, `telefono`, `email`, `fecha_alta`, `estatus`, `responsable_id`, `honorarios`, `periodicidad`, `ultima_declaracion_mes`, `ultima_declaracion_anio`, `limite_declaracion_dia`, `notas`, `created_at`, `updated_at`) VALUES
(1, 'ALINA GUADALUPE CAUICH CAUICH', 'LAVADERO Y TALLER MEC', 'CACX010702J81', 'RESICO', NULL, '', '24600', '9811645478', 'alina@gmail.com', '2025-10-12', 'activo', 3, 100.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:35:50', '2025-10-26 07:04:29'),
(2, 'ANGEL ABRAHAM GONZALEZ CHAVARRIA', 'DIBUJO Y PINTURAS', 'GOCA010726532', 'PERSONA FISICA', NULL, '', '', '', '', '2025-10-12', 'activo', 1, 950.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:45:46', '2025-10-12 19:45:46'),
(3, 'ANNA LOEWEN FRIESEN', 'SIEMBRA', 'LOFA9307083C6', 'PERSONA FISICA', NULL, '', '', '', '', '2025-10-12', 'activo', 3, 1250.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:46:36', '2025-10-12 19:46:36'),
(4, 'ANTONIO JESUS DZIB MEDINA', 'LAVADERO CAMPECHE', 'DIMA780101137', 'PERSONA FISICA', NULL, '', '', '', '', '2025-10-12', 'activo', 1, 950.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:47:23', '2025-10-12 19:49:04'),
(5, 'ARTEMIO RAMIREZ RAMIREZ', 'COMPRA VTAS SANDIAS', 'RARA850210649', 'PERSONA FISICA', NULL, '', '', '', '', '2025-10-12', 'activo', 1, 7500.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:48:09', '2025-10-12 19:48:09'),
(6, 'MARTHA TAPIA ALCARAZ', 'CARNITAS', 'TAAM710226P26', 'RIF', NULL, '', '', '', '', '2025-10-12', 'activo', 3, 750.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 20:13:49', '2025-10-12 20:13:49'),
(7, 'FRANCISCO JAVIER CHABLE KU', '', 'CAKF680208F13', 'RIF', NULL, '', '', '', '', '2025-10-12', 'activo', NULL, 500.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 22:00:14', '2025-10-12 22:00:14'),
(8, 'PRUEBA', '', 'PRUEBA0101010', '', NULL, '', '', '', '', '2025-10-13', 'activo', NULL, 200.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 23:07:10', '2025-10-15 02:31:28'),
(9, 'LOURDES DEL CARMEN  HEREDIA ARCEO', '', 'HEAL660211QI8', '', NULL, '', '', '', '', '2025-10-13', 'activo', 1, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-13 15:15:16', '2025-10-13 15:15:16'),
(10, 'JOHAN PETERS WIEBE', 'MINISUPER SANTA FE', 'PEWJ940630R7A', 'RESICO', NULL, 'SANTA FE', '24604', '9903716176', '', '2025-10-01', 'activo', 1, 1300.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-13 17:32:26', '2025-10-13 17:32:26'),
(11, 'JORGE ALBERTO CRUZ VIOLANTE', '', 'GORGY3YYG3YGY', '', NULL, '', '', '', '', '2025-10-15', 'activo', NULL, 500.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-15 15:05:09', '2025-10-15 22:27:55'),
(12, 'JUAN PABLO LOEZA HERRERA', '', 'JUPA77TYGYGJH', '', NULL, '', '', '', '', '2025-10-15', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-15 15:09:32', '2025-10-15 15:09:32'),
(13, 'MARIO GENARO CANUL LEON', '', 'MARFEHUHUEUHU', '', NULL, '', '', '', '', '2025-10-16', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-16 16:51:57', '2025-10-16 16:51:57'),
(14, 'OSCAR JESUS SANDOVAL GOMEZ', '', 'OSCAR12345667', '', NULL, '', '', '', '', '2025-10-23', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-23 14:58:27', '2025-10-23 14:58:27'),
(15, 'KBLEX', '', 'KBLEX12345678', '', NULL, '', '', '', '', '2025-10-24', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-24 14:47:30', '2025-10-24 14:47:30'),
(16, 'FUENTES Y FUENTES', '', 'FUENTES123468', '', NULL, '', '', '', '', '2025-10-24', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-24 14:57:12', '2025-10-24 14:57:12'),
(17, 'PROMOTORA INMOBILIARIA', '', 'PROMOTORA1234', '', NULL, '', '', '', '', '2025-10-24', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-24 15:01:18', '2025-10-24 15:01:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_honorarios`
--

CREATE TABLE `control_honorarios` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `anio` int(4) NOT NULL,
  `mes` int(2) NOT NULL,
  `monto_mensual` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','pagado','cortesia') NOT NULL DEFAULT 'pendiente',
  `fecha_pago` date DEFAULT NULL,
  `recibo_servicio_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_adelantados_lotes`
--

CREATE TABLE `pagos_adelantados_lotes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_pago` date NOT NULL,
  `monto_total` decimal(12,2) NOT NULL,
  `metodo` varchar(50) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recibos`
--

CREATE TABLE `recibos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `externo_nombre` varchar(150) DEFAULT NULL,
  `externo_rfc` varchar(13) DEFAULT NULL,
  `externo_domicilio` varchar(255) DEFAULT NULL,
  `externo_email` varchar(150) DEFAULT NULL,
  `externo_tel` varchar(30) DEFAULT NULL,
  `concepto` varchar(200) NOT NULL,
  `periodo_inicio` date DEFAULT NULL,
  `periodo_fin` date DEFAULT NULL,
  `tipo` varchar(20) DEFAULT 'contado',
  `origen` enum('auto','manual') NOT NULL DEFAULT 'auto',
  `monto` decimal(12,2) NOT NULL,
  `monto_pagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` date DEFAULT NULL,
  `vencimiento` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `duracion` varchar(20) DEFAULT NULL,
  `estado` enum('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
  `estatus` enum('activo','cancelado') NOT NULL DEFAULT 'activo',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recibos_pagos`
--

CREATE TABLE `recibos_pagos` (
  `id` int(11) NOT NULL,
  `folio` int(11) NOT NULL,
  `recibo_id` int(11) NOT NULL,
  `lote_id` int(11) DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` varchar(30) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `recibos_pagos`
--
DELIMITER $$
CREATE TRIGGER `before_pago_insert` BEFORE INSERT ON `recibos_pagos` FOR EACH ROW BEGIN
    DECLARE total DECIMAL(12,2);
    DECLARE pagado DECIMAL(12,2);
    DECLARE saldo DECIMAL(12,2);

    -- Solo ejecuta las validaciones si el método NO es 'Cortesía'
    IF NEW.metodo != 'Cortesía' THEN
        -- Validación 1: El monto debe ser mayor a 0
        IF NEW.monto <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto del pago debe ser mayor a 0';
        END IF;

        -- Validación 2: El pago no debe exceder el saldo
        SELECT r.monto, COALESCE(SUM(p.monto),0)
        INTO total, pagado
        FROM recibos r
        LEFT JOIN recibos_pagos p ON p.recibo_id = r.id
        WHERE r.id = NEW.recibo_id;

        SET saldo = total - pagado;

        IF NEW.monto > saldo + 0.001 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto del pago excede el saldo del recibo';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_recibos_pagos_bu` BEFORE UPDATE ON `recibos_pagos` FOR EACH ROW BEGIN
  DECLARE total DECIMAL(12,2);
  DECLARE pagado DECIMAL(12,2);
  DECLARE saldo DECIMAL(12,2);

  SELECT r.monto, COALESCE(SUM(p.monto),0)
    INTO total, pagado
  FROM recibos r
  LEFT JOIN recibos_pagos p ON p.recibo_id = r.id AND p.id <> NEW.id
  WHERE r.id = NEW.recibo_id;

  SET saldo = total - pagado;
  IF NEW.monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto del pago debe ser mayor a 0';
  END IF;
  IF NEW.monto > saldo + 0.00001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto del pago excede el saldo del recibo';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recibo_servicios`
--

CREATE TABLE `recibo_servicios` (
  `id` int(11) NOT NULL,
  `recibo_id` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `importe` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'administrador', 'Acceso total al sistema', '2025-10-12 17:19:10', '2025-10-12 17:27:06'),
(2, 'auxiliar', 'Acceso limitado para tareas de apoyo', '2025-10-12 17:19:10', '2025-10-12 17:54:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `estatus` enum('activo','inactivo','suspendido') DEFAULT 'activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `intentos_fallidos` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `email`, `nombre`, `apellidos`, `rol_id`, `estatus`, `ultimo_acceso`, `intentos_fallidos`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$b4Pib.sQEkPG2Fbbe1.T4.OBsZ/58C2zqK6qdjE7WY9UxBn1Ndfyy', 'admin@gmail.com', 'Admin', 'Principal', 1, 'activo', '2025-10-26 18:43:17', 0, '2025-10-12 17:43:59', '2025-10-27 00:43:17'),
(3, 'angel', '$2y$10$lkKmKpVXZTSya0DTjgkplOzIE7FNE2qilrztAQy6UErXSWyXi/mU.', 'angelriveroqwer23@gmail.com', 'Angel Enrique', 'Rivero Chuc', 2, 'activo', '2025-10-12 19:07:12', 0, '2025-10-12 19:43:03', '2025-10-13 01:07:12');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cfdis_emitidas`
--
ALTER TABLE `cfdis_emitidas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio_fiscal` (`folio_fiscal`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `cfdis_recibidas`
--
ALTER TABLE `cfdis_recibidas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio_fiscal` (`folio_fiscal`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfc` (`rfc`),
  ADD KEY `responsable_id` (`responsable_id`);

--
-- Indices de la tabla `control_honorarios`
--
ALTER TABLE `control_honorarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cliente_periodo` (`cliente_id`,`anio`,`mes`),
  ADD KEY `fk_control_honorarios_cliente` (`cliente_id`),
  ADD KEY `fk_control_honorarios_servicio` (`recibo_servicio_id`);

--
-- Indices de la tabla `pagos_adelantados_lotes`
--
ALTER TABLE `pagos_adelantados_lotes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `pagos_adelantados_lotes_ibfk_2` (`usuario_id`);

--
-- Indices de la tabla `recibos`
--
ALTER TABLE `recibos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_recibos_periodo_estatus` (`cliente_id`,`periodo_inicio`,`periodo_fin`,`estatus`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_recibos_externo_nombre` (`externo_nombre`);

--
-- Indices de la tabla `recibos_pagos`
--
ALTER TABLE `recibos_pagos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_recibos_pagos_folio` (`folio`),
  ADD KEY `idx_recibo_id` (`recibo_id`),
  ADD KEY `idx_fecha_pago` (`fecha_pago`),
  ADD KEY `fk_recibos_pagos_usuario` (`usuario_id`),
  ADD KEY `idx_lote_id` (`lote_id`);

--
-- Indices de la tabla `recibo_servicios`
--
ALTER TABLE `recibo_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recibo_id` (`recibo_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `rol_id` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cfdis_emitidas`
--
ALTER TABLE `cfdis_emitidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cfdis_recibidas`
--
ALTER TABLE `cfdis_recibidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `control_honorarios`
--
ALTER TABLE `control_honorarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos_adelantados_lotes`
--
ALTER TABLE `pagos_adelantados_lotes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recibos`
--
ALTER TABLE `recibos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recibos_pagos`
--
ALTER TABLE `recibos_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recibo_servicios`
--
ALTER TABLE `recibo_servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cfdis_emitidas`
--
ALTER TABLE `cfdis_emitidas`
  ADD CONSTRAINT `cfdis_emitidas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cfdis_recibidas`
--
ALTER TABLE `cfdis_recibidas`
  ADD CONSTRAINT `cfdis_recibidas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `control_honorarios`
--
ALTER TABLE `control_honorarios`
  ADD CONSTRAINT `fk_control_honorarios_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_control_honorarios_servicio` FOREIGN KEY (`recibo_servicio_id`) REFERENCES `recibo_servicios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pagos_adelantados_lotes`
--
ALTER TABLE `pagos_adelantados_lotes`
  ADD CONSTRAINT `pagos_adelantados_lotes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_adelantados_lotes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `recibos`
--
ALTER TABLE `recibos`
  ADD CONSTRAINT `recibos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recibos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `recibos_pagos`
--
ALTER TABLE `recibos_pagos`
  ADD CONSTRAINT `fk_recibos_pagos_lote` FOREIGN KEY (`lote_id`) REFERENCES `pagos_adelantados_lotes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_recibos_pagos_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recibos_pagos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `recibo_servicios`
--
ALTER TABLE `recibo_servicios`
  ADD CONSTRAINT `recibo_servicios_ibfk_1` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
