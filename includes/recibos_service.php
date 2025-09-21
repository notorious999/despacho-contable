<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

class RecibosService {
    private Database $db;
    public function __construct() { $this->db = new Database(); }

    public static function monthlyPeriod(string $anyDay): array {
        $d = substr($anyDay, 0, 10);
        if (strlen($d) === 7) $d .= '-01';
        $start = date('Y-m-01', strtotime($d));
        $end   = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

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
        static $meses = [
            1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];
        $ts = strtotime($dateYmd);
        $m = (int)date('n', $ts);
        $y = date('Y', $ts);
        return $meses[$m] . ' ' . $y;
    }

    // Genera recibos del periodo; fechaVencimiento es opcional y se aplicará a los recibos nuevos
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
                $count += $this->crearReciboSiNoExiste((int)$c->id, $c, $pStart, $pEnd, $honorarios, 'auto', $fechaVencimiento);
            } else {
                $year = (int)date('Y', strtotime($desde));
                [$annualStart, $annualEnd] = self::annualPeriodForClient($c, $year);
                $fromM = date('Y-m', strtotime($desde));
                $toM   = date('Y-m', strtotime($hasta));
                $annualM = date('Y-m', strtotime($annualStart));
                if ($annualM >= $fromM && $annualM <= $toM) {
                    $count += $this->crearReciboSiNoExiste((int)$c->id, $c, $annualStart, $annualEnd, $honorarios, 'auto', $fechaVencimiento);
                }
            }
        }
        return $count;
    }

    private function crearReciboSiNoExiste(
        int $clienteId,
        object $cliente,
        string $pStart,
        string $pEnd,
        float $monto,
        string $origen='auto',
        ?string $fechaVencimiento = null
    ): int {
        $this->db->query('SELECT id FROM recibos WHERE cliente_id = :cid AND periodo_inicio = :pi AND periodo_fin = :pf');
        $this->db->bind(':cid', $clienteId);
        $this->db->bind(':pi', $pStart);
        $this->db->bind(':pf', $pEnd);
        $existe = $this->db->single();
        if ($existe) return 0;

        $this->db->query('INSERT INTO recibos
            (cliente_id, concepto, tipo, origen, monto, monto_pagado, fecha_pago, vencimiento, fecha_vencimiento,
             periodo_inicio, periodo_fin, estado, observaciones, usuario_id)
            VALUES
            (:cid, :concepto, :tipo, :origen, :monto, 0, :fecha_pago, :venc, :fecha_venc, :pi, :pf, :estado, :obs, :uid)');

        $concepto = $this->conceptoPorPeriodo($cliente, $pStart, $pEnd);
        $estado   = 'pendiente';

        $this->db->bind(':cid', $clienteId);
        $this->db->bind(':concepto', $concepto);
        $this->db->bind(':tipo', (isset($cliente->periodicidad) && strtolower((string)$cliente->periodicidad) === 'anual') ? 'anual' : 'mensual');
        $this->db->bind(':origen', $origen);
        $this->db->bind(':monto', $monto);
        $this->db->bind(':fecha_pago', null);
        $this->db->bind(':venc', $fechaVencimiento);      // por compatibilidad si existe columna 'vencimiento'
        $this->db->bind(':fecha_venc', $fechaVencimiento);
        $this->db->bind(':pi', $pStart);
        $this->db->bind(':pf', $pEnd);
        $this->db->bind(':estado', $estado);
        $this->db->bind(':obs', null);
        $this->db->bind(':uid', null);

        return $this->db->execute() ? 1 : 0;
    }

    private function conceptoPorPeriodo(object $cliente, string $pStart, string $pEnd): string {
        $rs = (string)($cliente->razon_social ?? '');
        $sameMonth = (date('Y-m', strtotime($pStart)) === date('Y-m', strtotime($pEnd)));
        $per = $sameMonth
            ? self::mesAnioES($pStart)
            : (self::mesAnioES($pStart) . ' - ' . self::mesAnioES($pEnd));
        return 'Honorarios ' . $per . ' - ' . $rs;
    }

    public function registrarPago(int $reciboId, string $fechaPago, float $monto, ?string $metodo, ?string $referencia, ?string $obs, ?int $usuarioId): bool {
        $this->db->query('INSERT INTO recibos_pagos (recibo_id, fecha_pago, monto, metodo, referencia, observaciones, usuario_id)
                          VALUES (:rid, :fp, :m, :mt, :ref, :obs, :uid)');
        $this->db->bind(':rid', $reciboId);
        $this->db->bind(':fp', $fechaPago);
        $this->db->bind(':m', $monto);
        $this->db->bind(':mt', $metodo);
        $this->db->bind(':ref', $referencia);
        $this->db->bind(':obs', $obs);
        $this->db->bind(':uid', $usuarioId);
        if (!$this->db->execute()) return false;

        return $this->recalcularEstado($reciboId);
    }

    // Estados: pendiente, vencido, pagado (sin "parcial")
    public function recalcularEstado(int $reciboId): bool {
        $this->db->query('SELECT monto, fecha_vencimiento FROM recibos WHERE id = :id');
        $this->db->bind(':id', $reciboId);
        $recibo = $this->db->single();
        if (!$recibo) return false;

        $this->db->query('SELECT COALESCE(SUM(monto),0) AS pagado FROM recibos_pagos WHERE recibo_id = :id');
        $this->db->bind(':id', $reciboId);
        $row = $this->db->single();
        $pagado = (float)($row->pagado ?? 0.0);
        $total  = (float)$recibo->monto;

        $hoy = date('Y-m-d');
        if ($pagado + 0.00001 >= $total) {
            $estado = 'pagado';
        } else {
            $estado = (!empty($recibo->fecha_vencimiento) && $hoy > $recibo->fecha_vencimiento) ? 'vencido' : 'pendiente';
        }

        $this->db->query('UPDATE recibos
                          SET monto_pagado = :mp, estado = :est
                          WHERE id = :id');
        $this->db->bind(':mp', $pagado);
        $this->db->bind(':est', $estado);
        $this->db->bind(':id', $reciboId);
        return $this->db->execute();
    }

    public function marcarVencidos(): int {
        $hoy = date('Y-m-d');
        $this->db->query('UPDATE recibos
                          SET estado = "vencido"
                          WHERE (estado IN ("pendiente") OR estado IS NULL)
                            AND fecha_vencimiento IS NOT NULL
                            AND fecha_vencimiento < :hoy
                            AND (monto_pagado + 0.00001) < monto');
        $this->db->bind(':hoy', $hoy);
        $this->db->execute();
        return $this->db->rowCount();
    }
}