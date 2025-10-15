<?php
session_start();

// Función para sanear entradas de formularios
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para redireccionar
function redirect($url) {
    header('location: ' . $url);
    exit;
}

// Verificar si el usuario está logueado
function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        return true;
    } else {
        return false;
    }
}

// Verificar si el usuario tiene el rol requerido
function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return $_SESSION['user_role'] == $requiredRole;
}

// Función para mostrar mensajes flash
function flash($name = '', $message = '', $class = 'alert alert-success') {
    if (!empty($name)) {
        if (!empty($message) && empty($_SESSION[$name])) {
            if (!empty($_SESSION[$name])) {
                unset($_SESSION[$name]);
            }
            
            if (!empty($_SESSION[$name . '_class'])) {
                unset($_SESSION[$name . '_class']);
            }
            
            $_SESSION[$name] = $message;
            $_SESSION[$name . '_class'] = $class;
        } elseif (empty($message) && !empty($_SESSION[$name])) {
            $class = !empty($_SESSION[$name . '_class']) ? $_SESSION[$name . '_class'] : '';
            echo '<div class="' . $class . '" id="msg-flash">' . $_SESSION[$name] . '</div>';
            unset($_SESSION[$name]);
            unset($_SESSION[$name . '_class']);
        }
    }
}

// Función para formatear moneda
function formatMoney($amount) {
    return '$' . number_format($amount, 2, '.', ',');
}

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y') {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

// Función para verificar si un UUID es válido
function isValidUUID($uuid) {
    return preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $uuid);
}

// Evita redeclaraciones si este archivo se incluye en varias vistas
if (!function_exists('sx_attr')) {
    /**
     * Lee un atributo de SimpleXMLElement siendo tolerante con variaciones de mayúsculas.
     * Ej: "TipoDeComprobante" o "tipoDeComprobante".
     */
    function sx_attr(SimpleXMLElement $node, string $attr): string {
        // Intento exacto
        if (isset($node[$attr]) && $node[$attr] !== '') {
            return (string)$node[$attr];
        }
        // Intento con primera letra minúscula (tipoDeComprobante)
        $alt = lcfirst($attr);
        if ($alt !== $attr && isset($node[$alt]) && $node[$alt] !== '') {
            return (string)$node[$alt];
        }
        return '';
    }
}

if (!function_exists('mapearTipoComprobante')) {
    /**
     * Mapea el código del TipoDeComprobante (I,E,P,N) a su nombre solicitado.
     * Nota: Por petición, "Nomina" sin acento.
     */
    function mapearTipoComprobante(string $codigo): string {
        switch (strtoupper(trim($codigo))) {
            case 'I': return 'Ingreso';
            case 'E': return 'Egreso';
            case 'P': return 'Pago';
            case 'N': return 'Nomina';
            // Extra: si llega 'T' (Traslado) lo etiquetamos como 'Traslado' (no solicitado pero útil)
            case 'T': return 'Traslado';
            default:  return 'Otro';
        }
    }
}

if (!function_exists('getTipoComprobanteBadge')) {
    /**
     * Badge visual para el tipo en tablas (acepta 'Nomina' o 'Nómina').
     */
    function getTipoComprobanteBadge(string $tipo): string {
        $t = strtolower($tipo);
        if ($t === 'ingreso')  return '<span class="badge bg-success">Ingreso</span>';
        if ($t === 'egreso')   return '<span class="badge bg-danger">Egreso</span>';
        if ($t === 'pago')     return '<span class="badge bg-info">Pago</span>';
        if ($t === 'nomina' || $t === 'nómina') return '<span class="badge bg-secondary">Nomina</span>';
        if ($t === 'traslado') return '<span class="badge bg-warning">Traslado</span>';
        return '<span class="badge bg-secondary">Otro</span>';
    }
}
// ... resto de helpers ...

if (!function_exists('getFormaPago')) {
    function getFormaPago($codigo) {
        $formasPago = [
            '01' => 'Efectivo',
            '02' => 'Cheque nominativo',
            '03' => 'Transferencia electrónica',
            '04' => 'Tarjeta de crédito',
            '05' => 'Monedero electrónico',
            '06' => 'Dinero electrónico',
            '08' => 'Vales de despensa',
            '12' => 'Dación en pago',
            '13' => 'Pago por subrogación',
            '14' => 'Pago por consignación',
            '15' => 'Condonación',
            '17' => 'Compensación',
            '23' => 'Novación',
            '24' => 'Confusión',
            '25' => 'Remisión de deuda',
            '26' => 'Prescripción o caducidad',
            '27' => 'A satisfacción del acreedor',
            '28' => 'Tarjeta de débito',
            '29' => 'Tarjeta de servicios',
            '30' => 'Aplicación de anticipos',
            '31' => 'Intermediario pagos',
            '99' => 'Por definir',
        ];
        return isset($formasPago[$codigo]) ? ($codigo . ' - ' . $formasPago[$codigo]) : (string)$codigo;
    }
}

if (!function_exists('getMetodoPago')) {
    function getMetodoPago($codigo) {
        $metodosPago = [
            'PUE' => 'Pago en una sola exhibición',
            'PPD' => 'Pago en parcialidades o diferido',
        ];
        return isset($metodosPago[$codigo]) ? ($codigo . ' - ' . $metodosPago[$codigo]) : (string)$codigo;
    }
}

// En el archivo: /includes/functions.php

// ... (todo tu código existente)

/**
 * Convierte un número a su representación en letras en español (formato moneda MXN).
 * @param float $numero El número a convertir.
 * @return string La cantidad en letras.
 */
function numeroALetrasMX(float $numero): string {
  $numero = round($numero, 2);
  $entero = (int)floor($numero);
  $centavos = (int)round(($numero - $entero) * 100);

  $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
               'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
  $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
  $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

  $toWords999 = function(int $n) use ($unidades, $decenas, $centenas): string {
    if ($n === 0) return 'CERO';
    if ($n === 100) return 'CIEN';
    $c = (int)floor($n / 100);
    $d = (int)floor(($n % 100) / 10);
    $u = $n % 10;
    $out = [];

    if ($c > 0) $out[] = $centenas[$c];

    $du = $n % 100;
    if ($du <= 20) {
      if ($du > 0) $out[] = $unidades[$du];
    } else {
      $textoDecena = $decenas[$d];
      if ($u === 0) {
        $out[] = $textoDecena;
      } else {
        $out[] = $textoDecena . ' Y ' . $unidades[$u];
      }
    }
    return trim(implode(' ', array_filter($out)));
  };

  $texto = '';
  if ($entero === 0) {
    $texto = 'CERO';
  } else {
    $millones = (int)floor($entero / 1000000);
    $miles    = (int)floor(($entero % 1000000) / 1000);
    $resto    = $entero % 1000;

    if ($millones > 0) $texto .= ($millones === 1) ? 'UN MILLON' : ($toWords999($millones) . ' MILLONES');
    if ($miles > 0)   $texto .= ($texto ? ' ' : '') . ($miles === 1 ? 'MIL' : $toWords999($miles) . ' MIL');
    if ($resto > 0)   $texto .= ($texto ? ' ' : '') . $toWords999($resto);
  }

  $cent = str_pad((string)$centavos, 2, '0', STR_PAD_LEFT);
  return $texto . ' ' . $cent . '/100 M.N.';
}

/**
 * Convierte una fecha en formato 'YYYY-MM-DD' a un formato largo en español.
 * @param string $fechaYmd La fecha a convertir.
 * @return string La fecha en formato "día, DD de mes de AAAA".
 */
function fechaLargaES(string $fechaYmd): string {
  if (empty($fechaYmd)) return '';
  $ts = strtotime($fechaYmd);
  $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  
  $diaSemana = $dias[(int)date('w', $ts)];
  $dia = (int)date('j', $ts);
  $mes = $meses[(int)date('n', $ts)];
  $anio = date('Y', $ts);
  
  return ucfirst($diaSemana) . ', ' . $dia . ' de ' . $mes . ' de ' . $anio;
}