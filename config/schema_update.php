<?php
require_once 'config.php';
require_once 'db.php';

// Inicializar la base de datos
$database = new Database();

// Modificar la tabla CFDIs para adaptarla a los nuevos requisitos
$database->query("DROP TABLE IF EXISTS CFDIs");

// Crear tabla para CFDIs emitidos
$database->query("CREATE TABLE CFDIs_Emitidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    tipo VARCHAR(20) DEFAULT 'emitida',
    folio_interno VARCHAR(50),
    forma_pago VARCHAR(50),
    metodo_pago VARCHAR(50),
    folio_fiscal VARCHAR(36) UNIQUE,
    fecha_emision DATETIME,
    nombre_receptor VARCHAR(150),
    rfc_receptor VARCHAR(13),
    descripcion TEXT,
    subtotal DECIMAL(12,2),
    tasa0 DECIMAL(12,2) DEFAULT 0,
    tasa16 DECIMAL(12,2) DEFAULT 0,
    iva DECIMAL(12,2),
    total DECIMAL(12,2),
    estado VARCHAR(20) DEFAULT 'vigente',
    fecha_cancelacion DATETIME DEFAULT NULL,
    motivo_cancelacion TEXT,
    FOREIGN KEY (cliente_id) REFERENCES Clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Crear tabla para CFDIs recibidos
$database->query("CREATE TABLE CFDIs_Recibidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    tipo VARCHAR(20) DEFAULT 'recibida',
    forma_pago VARCHAR(50),
    metodo_pago VARCHAR(50),
    folio_fiscal VARCHAR(36) UNIQUE,
    fecha_certificacion DATETIME,
    nombre_emisor VARCHAR(150),
    rfc_emisor VARCHAR(13),
    descripcion TEXT,
    subtotal DECIMAL(12,2),
    tasa0 DECIMAL(12,2) DEFAULT 0,
    tasa16 DECIMAL(12,2) DEFAULT 0,
    iva DECIMAL(12,2),
    total DECIMAL(12,2),
    retencion_iva DECIMAL(12,2) DEFAULT 0,
    retencion_isr DECIMAL(12,2) DEFAULT 0,
    retencion_ieps DECIMAL(12,2) DEFAULT 0,
    uuid_relacionado VARCHAR(36),
    estado VARCHAR(20) DEFAULT 'vigente',
    fecha_cancelacion DATETIME DEFAULT NULL,
    motivo_cancelacion TEXT,
    FOREIGN KEY (cliente_id) REFERENCES Clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Crear tabla para recibos de pago
$database->query("CREATE TABLE Recibos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    concepto VARCHAR(200) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'contado', /* contado o abono */
    monto DECIMAL(12,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    vencimiento DATE,
    duracion VARCHAR(20), /* meses o año */
    estado VARCHAR(20) DEFAULT 'pagado',
    observaciones TEXT,
    usuario_id INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES Clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Estructura de base de datos actualizada correctamente.";
?>