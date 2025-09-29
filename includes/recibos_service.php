<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

class RecibosService {
    private Database $db;
    public function __construct() { $this->db = new Database(); }

    // Periodo mensual a partir de una fecha (YYYY-MM o YYYY-MM-DD)
    public static function monthlyPeriod(string $anyDay): array {
        if (strlen($anyDay) === 7) $anyDay .= '-01';
        $start = date('Y-m-01', strtotime($anyDay));
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    // Periodo desde YYYY-MM
    public static function monthlyPeriodFromYm(string $ym): array {
        $start = $ym . '-01';
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    // Periodo anual basado en inicio_regimen (o fecha_alta si no hay)
    public static function annualPeriodForClient(object $cliente, int $year): array {
        $month = null;
        if (!empty($cliente->inicio_regimen)) {
            $month = (int)date('n', strtotime((string)$cliente->inicio_regimen));
        } elseif (!empty($cliente->fecha_alta)) {
            $month = (int)date('n', strtotime((string)$cliente->fecha_alta));
        } else {
            $month = 1;
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    private static function mesAnioES(string $dateYmd): string {
        static $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $ts = strtotime($dateYmd);
        return $meses[(int)date('n',$ts)] . ' ' . date('Y',$ts);
    }

    private function conceptoPorPeriodo(?string $razonSocial, string $pStart, string $pEnd): string {
        $same = (date('Y-m', strtotime($pStart)) === date('Y-m', strtotime($pEnd)));
        $per = $same ? self::mesAnioES($pStart) : (self::mesAnioES($pStart) . ' - ' . self::mesAnioES($pEnd));
        $rs  = $razonSocial ? (' - ' . $razonSocial) : '';
        return 'Honorarios ' . $per . $rs;
    }

    // ESTADOS BINARIOS: pendiente/pagado
    public function recalcularEstado(int $reciboId): bool {
        $this->db->query('SELECT monto FROM recibos WHERE id = :id');
        $this->db->bind(':id', $reciboId);
        $recibo = $this->db->single();
        if (!$recibo) return false;

        $this->db->query('SELECT COALESCE(SUM(monto),0) AS pagado FROM recibos_pagos WHERE recibo_id = :id');
        $this->db->bind(':id', $reciboId);
        $row = $this->db->single();
        $pagado = (float)($row->pagado ?? 0.0);
        $total  = (float)$recibo->monto;

        $estado = ($pagado + 0.00001 >= $total) ? 'pagado' : 'pendiente';

        $this->db->query('UPDATE recibos SET monto_pagado = :mp, estado = :est WHERE id = :id');
        $this->db->bind(':mp', $pagado);
        $this->db->bind(':est', $estado);
        $this->db->bind(':id', $reciboId);
        return $this->db->execute();
    }

    // Generar recibos del periodo seleccionado
    // - Mensual: genera el mes en el que cae $desde
    // - Anual: genera si el mes de inicio anual del cliente cae dentro de [desde..hasta]
    public function generarRecibosPeriodo(string $desde, string $hasta, ?int $clienteId = null, ?string $fechaVencimiento = null): int {
        $sql = 'SELECT * FROM clientes WHERE estatus = "activo"';
        $params = [];
        if (!empty($clienteId)) { $sql .= ' AND id = :cid'; $params[':cid'] = $clienteId; }
        $this->db->query($sql);
        foreach ($params as $k => $v) $this->db->bind($k, $v);
        $clientes = $this->db->resultSet();

        $count = 0;
        foreach ($clientes as $c) {
            $periodicidad = strtolower((string)($c->periodicidad ?? 'mensual'));
            $honorarios   = (float)($c->honorarios ?? 0);
            if ($honorarios <= 0) continue;

            if ($periodicidad === 'mensual') {
                [$pStart, $pEnd] = self::monthlyPeriod($desde);
                $id = $this->crearReciboSiNoExiste((int)$c->id, $c, $pStart, $pEnd, $honorarios, 'auto', $fechaVencimiento);
                if ($id) $count++;
            } else {
                $year = (int)date('Y', strtotime($desde));
                [$annualStart, $annualEnd] = self::annualPeriodForClient($c, $year);
                $fromM = date('Y-m', strtotime($desde));
                $toM   = date('Y-m', strtotime($hasta));
                $annualM = date('Y-m', strtotime($annualStart));
                if ($annualM >= $fromM && $annualM <= $toM) {
                    $id = $this->crearReciboSiNoExiste((int)$c->id, $c, $annualStart, $annualEnd, $honorarios, 'auto', $fechaVencimiento);
                    if ($id) $count++;
                }
            }
        }
        return $count;
    }

    // Crear recibo si no existe uno ACTIVO para ese cliente y periodo
    public function crearReciboSiNoExiste(
        ?int $clienteId,
        ?object $cliente,
        string $pStart,
        string $pEnd,
        float $monto,
        string $origen='auto',
        ?string $fechaVencimiento = null,
        array $externo = []
    ): ?int {
        $this->db->query('SELECT id FROM recibos
                          WHERE cliente_id <=> :cid
                            AND periodo_inicio = :pi AND periodo_fin = :pf
                            AND estatus = "activo"');
        $this->db->bind(':cid', $clienteId);
        $this->db->bind(':pi', $pStart);
        $this->db->bind(':pf', $pEnd);
        $existe = $this->db->single();
        if ($existe) return null;

        $concepto = $this->conceptoPorPeriodo($clienteId ? ($cliente->razon_social ?? null) : ($externo['nombre'] ?? null), $pStart, $pEnd);

        $this->db->query('INSERT INTO recibos
          (cliente_id, externo_nombre, externo_rfc, externo_domicilio, externo_email, externo_tel,
           concepto, tipo, origen, monto, monto_pagado, fecha_pago, fecha_vencimiento,
           periodo_inicio, periodo_fin, estado, estatus, observaciones, usuario_id)
           VALUES
          (:cid, :ex_nom, :ex_rfc, :ex_dom, :ex_email, :ex_tel,
           :concepto, :tipo, :origen, :monto, 0, :fecha_pago, :fv,
           :pi, :pf, :estado, "activo", :obs, :uid)');

        $this->db->bind(':cid', $clienteId);
        $this->db->bind(':ex_nom', $externo['nombre'] ?? null);
        $this->db->bind(':ex_rfc', $externo['rfc'] ?? null);
        $this->db->bind(':ex_dom', $externo['domicilio'] ?? null);
        $this->db->bind(':ex_email', $externo['email'] ?? null);
        $this->db->bind(':ex_tel', $externo['tel'] ?? null);
        $this->db->bind(':concepto', $concepto);
        $this->db->bind(':tipo', ($cliente && isset($cliente->periodicidad) && strtolower((string)$cliente->periodicidad)==='anual') ? 'anual' : 'mensual');
        $this->db->bind(':origen', $origen);
        $this->db->bind(':monto', $monto);
        $this->db->bind(':fecha_pago', null);
        $this->db->bind(':fv', $fechaVencimiento);
        $this->db->bind(':pi', $pStart);
        $this->db->bind(':pf', $pEnd);
        $this->db->bind(':estado', 'pendiente');
        $this->db->bind(':obs', null);
        $this->db->bind(':uid', null);

        if (!$this->db->execute()) return null;
        return (int)$this->db->lastInsertId();
    }

    // Registrar pago con clamp anti-sobrepago y folio
    public function registrarPago(int $reciboId, string $fechaPago, float $monto, ?string $metodo, ?string $referencia, ?string $obs, ?int $usuarioId): bool {
        $this->db->query('SELECT monto, monto_pagado, estatus FROM recibos WHERE id = :id');
        $this->db->bind(':id', $reciboId);
        $r = $this->db->single();
        if (!$r || $r->estatus === 'cancelado') return false;

        $saldo = max(((float)$r->monto - (float)$r->monto_pagado), 0.0);
        $monto = min($monto, $saldo);
        if ($monto <= 0) return true;

        $this->db->query('SELECT COALESCE(MAX(folio),0) AS mx FROM recibos_pagos');
        $mx = $this->db->single();
        $folio = ((int)($mx->mx ?? 0)) + 1;

        $this->db->query('INSERT INTO recibos_pagos (recibo_id, fecha_pago, monto, metodo, referencia, observaciones, usuario_id, folio)
                          VALUES (:rid, :fp, :m, :mt, :ref, :obs, :uid, :folio)');
        $this->db->bind(':rid', $reciboId);
        $this->db->bind(':fp', $fechaPago);
        $this->db->bind(':m', $monto);
        $this->db->bind(':mt', $metodo);
        $this->db->bind(':ref', $referencia);
        $this->db->bind(':obs', $obs);
        $this->db->bind(':uid', $usuarioId);
        $this->db->bind(':folio', $folio);

        if (!$this->db->execute()) return false;
        return $this->recalcularEstado($reciboId);
    }

    // Pago adelantado (crea si falta y paga completo) para meses YYYY-MM
    public function pagarMesesAdelantados(int $clienteId, array $mesesYm, ?string $vencimientoGlobal, ?string $metodo, ?string $referencia, ?string $obs, ?int $usuarioId): array {
        $this->db->query('SELECT * FROM clientes WHERE id = :id AND estatus = "activo"');
        $this->db->bind(':id', $clienteId);
        $c = $this->db->single();
        if (!$c) return ['ok'=>false, 'msg'=>'Cliente no encontrado o inactivo.'];

        $montoBase = (float)($c->honorarios ?? 0);
        if ($montoBase <= 0) return ['ok'=>false, 'msg'=>'El cliente no tiene honorarios definidos.'];

        $generados = 0; $pagados = 0; $recibos_info = [];

        foreach ($mesesYm as $ym) {
            if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) continue;
            [$pi, $pf] = self::monthlyPeriodFromYm($ym);

            $id = $this->crearReciboSiNoExiste((int)$c->id, $c, $pi, $pf, $montoBase, 'auto', $vencimientoGlobal);
            if ($id) $generados++;

            // Traer saldo y pagar
            $this->db->query('SELECT id, monto, monto_pagado FROM recibos WHERE cliente_id = :cid AND periodo_inicio = :pi AND periodo_fin = :pf AND estatus="activo"');
            $this->db->bind(':cid', (int)$c->id);
            $this->db->bind(':pi', $pi);
            $this->db->bind(':pf', $pf);
            $r = $this->db->single();
            if ($r) {
                $saldo = max(((float)$r->monto - (float)$r->monto_pagado), 0.0);
                if ($saldo > 0 && $this->registrarPago((int)$r->id, date('Y-m-d'), $saldo, $metodo, $referencia, $obs, $usuarioId)) {
                    $pagados++;
                }
                $recibos_info[] = ['id'=>(int)$r->id, 'periodo_inicio'=>$pi, 'periodo_fin'=>$pf];
            }
        }
        return ['ok'=>true, 'generados'=>$generados, 'pagados'=>$pagados, 'recibos'=>$recibos_info];
    }
}