<?php
class RecibosService {
  private $db;

  public function __construct() {
    $this->db = new Database();
  }

  /**
   * Genera recibos para un periodo específico.
   * AHORA CON EL CONCEPTO DINÁMICO.
   */
  public function generarRecibosPeriodo(string $desde, string $hasta, ?int $clienteId, ?int $usuarioId): int {
    $generados = 0;
    
    $sql = 'SELECT id, honorarios FROM clientes WHERE estatus = "activo" AND periodicidad = "mensual" AND honorarios > 0';
    if ($clienteId) {
        $sql .= ' AND id = :cid';
    }
    $this->db->query($sql);
    if ($clienteId) {
        $this->db->bind(':cid', $clienteId);
    }
    $clientes = $this->db->resultSet();

    if (empty($clientes)) {
        return 0;
    }

    $fechaInicioPeriodo = date('Y-m-01', strtotime($desde));
    $fechaFinPeriodo = date('Y-m-t', strtotime($desde));

    // --- CAMBIO AQUÍ ---
    // Array para traducir el número del mes a español
    $meses_es = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    $numero_mes = (int)date('n', strtotime($fechaInicioPeriodo));
    $nombre_mes = $meses_es[$numero_mes] ?? 'Mes';
    // Se crea el concepto dinámico. Ej: "Honorarios Octubre"
    $concepto_dinamico = 'Honorarios ' . ucfirst($nombre_mes);
    
    foreach ($clientes as $cliente) {
        $this->db->query('SELECT id FROM recibos WHERE cliente_id = :cid AND periodo_inicio = :pi');
        $this->db->bind(':cid', $cliente->id);
        $this->db->bind(':pi', $fechaInicioPeriodo);
        $reciboExistente = $this->db->single();

        if (!$reciboExistente) {
            $this->db->query('INSERT INTO recibos (cliente_id, concepto, monto, periodo_inicio, periodo_fin, origen, usuario_id)
                              VALUES (:cid, :con, :mon, :pi, :pf, :ori, :uid)');
            $this->db->bind(':cid', $cliente->id);
            $this->db->bind(':con', $concepto_dinamico); // <-- Se usa el nuevo concepto
            $this->db->bind(':mon', (float)$cliente->honorarios);
            $this->db->bind(':pi', $fechaInicioPeriodo);
            $this->db->bind(':pf', $fechaFinPeriodo);
            $this->db->bind(':ori', 'auto');
            $this->db->bind(':uid', $usuarioId);
            
            if ($this->db->execute()) {
                $generados++;
            }
        }
    }
    
    return $generados;
  }

  /**
   * Genera recibos para pagos adelantados.
   * AHORA CON EL CONCEPTO DINÁMICO.
   */
  public function generarRecibosPorMeses(int $clienteId, array $meses, ?string $fechaVencimiento, ?int $usuarioId): array {
    $recibosResultantes = [];

    $this->db->query('SELECT honorarios FROM clientes WHERE id = :id');
    $this->db->bind(':id', $clienteId);
    $cliente = $this->db->single();

    if (!$cliente || (float)$cliente->honorarios <= 0) {
      return ['ok' => false, 'msg' => 'El cliente no tiene honorarios configurados.'];
    }
    $montoHonorarios = (float)$cliente->honorarios;

    // Array para traducir el número del mes a español
    $meses_es = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

    foreach ($meses as $mes) {
      $fechaInicio = $mes . '-01';
      $fechaFin = date('Y-m-t', strtotime($fechaInicio));

      $this->db->query('SELECT id, monto FROM recibos WHERE cliente_id = :cid AND periodo_inicio = :pi AND estatus = "activo"');
      $this->db->bind(':cid', $clienteId);
      $this->db->bind(':pi', $fechaInicio);
      $reciboExistente = $this->db->single();

      if ($reciboExistente) {
        $recibosResultantes[] = [
          'id' => (int)$reciboExistente->id,
          'monto' => (float)$reciboExistente->monto,
          'periodo_inicio' => $fechaInicio,
          'periodo_fin' => $fechaFin
        ];
      } else {
        // --- CAMBIO AQUÍ ---
        $numero_mes = (int)date('n', strtotime($fechaInicio));
        $nombre_mes = $meses_es[$numero_mes] ?? 'Mes';
        $concepto_dinamico = 'Honorarios ' . ucfirst($nombre_mes);

        $this->db->query('INSERT INTO recibos (cliente_id, concepto, monto, periodo_inicio, periodo_fin, fecha_vencimiento, origen, usuario_id)
                          VALUES (:cid, :con, :mon, :pi, :pf, :fv, :ori, :uid)');
        $this->db->bind(':cid', $clienteId);
        $this->db->bind(':con', $concepto_dinamico); // <-- Se usa el nuevo concepto
        $this->db->bind(':mon', $montoHonorarios);
        $this->db->bind(':pi', $fechaInicio);
        $this->db->bind(':pf', $fechaFin);
        $this->db->bind(':fv', $fechaVencimiento);
        $this->db->bind(':ori', 'auto');
        $this->db->bind(':uid', $usuarioId);
        
        if ($this->db->execute()) {
          $recibosResultantes[] = [
            'id' => (int)$this->db->lastInsertId(),
            'monto' => $montoHonorarios,
            'periodo_inicio' => $fechaInicio,
            'periodo_fin' => $fechaFin
          ];
        } else {
          return ['ok' => false, 'msg' => 'Error al crear el recibo para el periodo ' . $mes];
        }
      }
    }

    return ['ok' => true, 'recibos' => $recibosResultantes];
  }

  /**
   * Registra un pago para un recibo específico.
   */
  public function registrarPago(int $reciboId, float $monto, string $fechaPago, string $metodo, string $referencia, string $observaciones, ?int $usuarioId, ?int $loteId = null): bool {
    $this->db->query('SELECT MAX(folio) + 1 AS next_folio FROM recibos_pagos');
    $row = $this->db->single();
    $nextFolio = $row->next_folio ?? 1;

    $this->db->query('INSERT INTO recibos_pagos (recibo_id, lote_id, folio, fecha_pago, monto, metodo, referencia, observaciones, usuario_id)
                      VALUES (:rid, :lid, :folio, :fp, :mon, :met, :ref, :obs, :uid)');
    $this->db->bind(':rid', $reciboId);
    $this->db->bind(':lid', $loteId);
    $this->db->bind(':folio', $nextFolio);
    $this->db->bind(':fp', $fechaPago);
    $this->db->bind(':mon', $monto);
    $this->db->bind(':met', $metodo);
    $this->db->bind(':ref', $referencia);
    $this->db->bind(':obs', $observaciones);
    $this->db->bind(':uid', $usuarioId);

    if (!$this->db->execute()) {
      return false;
    }

    $this->db->query('UPDATE recibos SET
                        monto_pagado = (SELECT COALESCE(SUM(monto), 0) FROM recibos_pagos WHERE recibo_id = :rid),
                        estado = IF(monto_pagado >= monto, "pagado", "pendiente"),
                        fecha_pago = IF(monto_pagado >= monto, :fp, fecha_pago)
                      WHERE id = :rid');
    $this->db->bind(':rid', $reciboId);
    $this->db->bind(':fp', $fechaPago);
    
    return $this->db->execute();
  }
}