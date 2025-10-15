-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-10-2025 a las 05:28:02
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogoformapago`
--

CREATE TABLE `catalogoformapago` (
  `id` varchar(2) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogometodopago`
--

CREATE TABLE `catalogometodopago` (
  `id` varchar(3) NOT NULL,
  `descripcion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cfdis_emitidas`
--

CREATE TABLE `cfdis_emitidas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `rfc_emisor` varchar(20) DEFAULT NULL,
  `tipo` varchar(20) DEFAULT 'emitida',
  `tipo_comprobante` varchar(50) DEFAULT 'Ingreso',
  `folio_interno` varchar(50) DEFAULT NULL,
  `forma_pago` varchar(50) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `folio_fiscal` varchar(36) DEFAULT NULL,
  `fecha_emision` datetime DEFAULT NULL,
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
  `tasa0` decimal(12,2) DEFAULT 0.00,
  `tasa16` decimal(12,2) DEFAULT 0.00,
  `iva` decimal(12,2) DEFAULT NULL,
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
  `descripcion` text DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `tasa0_base` decimal(18,2) DEFAULT 0.00,
  `tasa16_base` decimal(18,2) DEFAULT 0.00,
  `iva_importe` decimal(18,2) DEFAULT 0.00,
  `ieps_importe` decimal(18,2) DEFAULT 0.00,
  `isr_importe` decimal(18,2) DEFAULT 0.00,
  `tasa0` decimal(12,2) DEFAULT 0.00,
  `tasa16` decimal(12,2) DEFAULT 0.00,
  `iva` decimal(12,2) DEFAULT NULL,
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
  `regimen_fiscal` varchar(3) DEFAULT NULL,
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
CREATE TRIGGER `trg_recibos_pagos_bi` BEFORE INSERT ON `recibos_pagos` FOR EACH ROW BEGIN
  DECLARE total DECIMAL(12,2);
  DECLARE pagado DECIMAL(12,2);
  DECLARE saldo DECIMAL(12,2);

  SELECT r.monto, COALESCE(SUM(p.monto),0)
    INTO total, pagado
  FROM recibos r
  LEFT JOIN recibos_pagos p ON p.recibo_id = r.id
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
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catalogoformapago`
--
ALTER TABLE `catalogoformapago`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogometodopago`
--
ALTER TABLE `catalogometodopago`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `fk_recibos_pagos_usuario` (`usuario_id`);

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
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Filtros para la tabla `recibos`
--
ALTER TABLE `recibos`
  ADD CONSTRAINT `recibos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recibos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `recibos_pagos`
--
ALTER TABLE `recibos_pagos`
  ADD CONSTRAINT `fk_recibos_pagos_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recibos_pagos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;