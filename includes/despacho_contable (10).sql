-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-10-2025 a las 05:44:05
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
(1, 'ALINA GUADALUPE CAUICH CAUICH', 'LAVADERO Y TALLER MEC', 'CACX010702J81', 'RESICO', NULL, '', '24600', '9811645478', 'alina@gmail.com', '2025-10-12', 'activo', 3, 650.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-12 19:35:50', '2025-10-12 19:44:13'),
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
(13, 'MARIO GENARO CANUL LEON', '', 'MARFEHUHUEUHU', '', NULL, '', '', '', '', '2025-10-16', 'activo', NULL, 0.00, 'mensual', NULL, NULL, 17, NULL, '2025-10-16 16:51:57', '2025-10-16 16:51:57');

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
(1, 'admin', '$2y$10$b4Pib.sQEkPG2Fbbe1.T4.OBsZ/58C2zqK6qdjE7WY9UxBn1Ndfyy', 'admin@gmail.com', 'Admin', 'Principal', 1, 'activo', '2025-10-16 10:50:41', 0, '2025-10-12 17:43:59', '2025-10-16 16:50:41'),
(3, 'angel', '$2y$10$lkKmKpVXZTSya0DTjgkplOzIE7FNE2qilrztAQy6UErXSWyXi/mU.', 'angelriveroqwer23@gmail.com', 'Angel Enrique', 'Rivero Chuc', 2, 'activo', '2025-10-12 19:07:12', 0, '2025-10-12 19:43:03', '2025-10-13 01:07:12');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfc` (`rfc`),
  ADD KEY `responsable_id` (`responsable_id`);

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
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
