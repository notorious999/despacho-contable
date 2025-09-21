<?php
declare(strict_types=1);

/**
 * Resumen de impuestos desde un CFDI (4.0 o compatible) a partir del XML:
 *
 * Traslados:
 * - tasa0_base: Base de IVA (Impuesto=002) con TasaOCuota ~ 0.000000
 * - tasa16_base: Base de IVA (Impuesto=002) con TasaOCuota ~ 0.160000
 * - iva_importe: Importe de IVA (Impuesto=002) con TasaOCuota ~ 0.160000
 * - ieps_importe: Importe con Impuesto=003 (IEPS)
 * - isr_importe: Importe con Impuesto=001 (ISR trasladado, poco común)
 *
 * Retenciones:
 * - retencion_iva: Importe con Impuesto=002
 * - retencion_ieps: Importe con Impuesto=003
 * - retencion_isr: Importe con Impuesto=001
 *
 * Importante: NO duplica. Prefiere RESUMEN del Comprobante; si no existe para un impuesto/tasa específico,
 * usa el desglose POR CONCEPTO como fallback.
 */
function cfdi_impuestos_resumen_from_xml(string $xmlString): array
{
    $zero = [
        'tasa0_base'      => 0.0,
        'tasa16_base'     => 0.0,
        'iva_importe'     => 0.0,
        'ieps_importe'    => 0.0,
        'isr_importe'     => 0.0,
        'retencion_iva'   => 0.0,
        'retencion_ieps'  => 0.0,
        'retencion_isr'   => 0.0,
    ];
    $xmlString = trim($xmlString);
    if ($xmlString === '') return $zero;

    $xml = @simplexml_load_string($xmlString);
    if ($xml === false) return $zero;

    $approx = static function (float $a, float $b, float $eps = 0.000001): bool {
        return abs($a - $b) < $eps;
    };
    $r2 = static function (float $n): float {
        return (float)number_format($n, 2, '.', '');
    };

    // Acumuladores por nivel
    $res = $zero; // resumen a nivel comprobante
    $con = $zero; // por concepto

    // Presencia por campo para decidir fallback
    $hasRes = [
        'tasa0_base' => false,
        'tasa16_base' => false,
        'iva_importe' => false,
        'ieps_importe' => false,
        'isr_importe' => false,
        'retencion_iva' => false,
        'retencion_ieps' => false,
        'retencion_isr' => false,
    ];
    $hasCon = $hasRes;

    // 1) RESUMEN a nivel Comprobante
    $trasResumen = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Impuestos"]/*[local-name()="Traslados"]/*[local-name()="Traslado"]');
    if ($trasResumen) {
        foreach ($trasResumen as $t) {
            $imp     = isset($t['Impuesto']) ? (string)$t['Impuesto'] : '';
            $tasa    = isset($t['TasaOCuota']) ? (float)$t['TasaOCuota'] : null;
            $base    = isset($t['Base']) ? (float)$t['Base'] : 0.0;
            $importe = isset($t['Importe']) ? (float)$t['Importe'] : 0.0;

            if ($imp === '002') { // IVA
                if ($tasa !== null) {
                    if ($approx($tasa, 0.0)) {
                        $res['tasa0_base'] += $base;
                        $hasRes['tasa0_base'] = true;
                    }
                    if ($approx($tasa, 0.16)) {
                        $res['tasa16_base'] += $base;
                        $res['iva_importe']  += $importe;
                        $hasRes['tasa16_base'] = true;
                        $hasRes['iva_importe'] = true;
                    }
                }
            } elseif ($imp === '003') { // IEPS
                $res['ieps_importe'] += $importe;
                $hasRes['ieps_importe'] = true;
            } elseif ($imp === '001') { // ISR trasladado (raro)
                $res['isr_importe'] += $importe;
                $hasRes['isr_importe'] = true;
            }
        }
    }
    $retResumen = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Impuestos"]/*[local-name()="Retenciones"]/*[local-name()="Retencion"]');
    if ($retResumen) {
        foreach ($retResumen as $r) {
            $imp     = isset($r['Impuesto']) ? (string)$r['Impuesto'] : '';
            $importe = isset($r['Importe']) ? (float)$r['Importe'] : 0.0;
            if     ($imp === '002') { $res['retencion_iva']  += $importe; $hasRes['retencion_iva']  = true; }
            elseif ($imp === '003') { $res['retencion_ieps'] += $importe; $hasRes['retencion_ieps'] = true; }
            elseif ($imp === '001') { $res['retencion_isr']  += $importe; $hasRes['retencion_isr']  = true; }
        }
    }

    // 2) POR CONCEPTO (fallback si no hay resumen específico)
    $trasConcepto = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Conceptos"]/*[local-name()="Concepto"]//*[local-name()="Traslado"]');
    if ($trasConcepto) {
        foreach ($trasConcepto as $t) {
            $imp     = isset($t['Impuesto']) ? (string)$t['Impuesto'] : '';
            $tasa    = isset($t['TasaOCuota']) ? (float)$t['TasaOCuota'] : null;
            $base    = isset($t['Base']) ? (float)$t['Base'] : 0.0;
            $importe = isset($t['Importe']) ? (float)$t['Importe'] : 0.0;

            if ($imp === '002') {
                if ($tasa !== null) {
                    if ($approx($tasa, 0.0)) {
                        $con['tasa0_base'] += $base;
                        $hasCon['tasa0_base'] = true;
                    }
                    if ($approx($tasa, 0.16)) {
                        $con['tasa16_base'] += $base;
                        $con['iva_importe']  += $importe;
                        $hasCon['tasa16_base'] = true;
                        $hasCon['iva_importe'] = true;
                    }
                }
            } elseif ($imp === '003') {
                $con['ieps_importe'] += $importe;
                $hasCon['ieps_importe'] = true;
            } elseif ($imp === '001') {
                $con['isr_importe'] += $importe;
                $hasCon['isr_importe'] = true;
            }
        }
    }
    $retConcepto = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Conceptos"]/*[local-name()="Concepto"]//*[local-name()="Retencion"]');
    if ($retConcepto) {
        foreach ($retConcepto as $r) {
            $imp     = isset($r['Impuesto']) ? (string)$r['Impuesto'] : '';
            $importe = isset($r['Importe']) ? (float)$r['Importe'] : 0.0;
            if     ($imp === '002') { $con['retencion_iva']  += $importe; $hasCon['retencion_iva']  = true; }
            elseif ($imp === '003') { $con['retencion_ieps'] += $importe; $hasCon['retencion_ieps'] = true; }
            elseif ($imp === '001') { $con['retencion_isr']  += $importe; $hasCon['retencion_isr']  = true; }
        }
    }

    // 3) Elegir por campo: prefiera RESUMEN si existe; si no, use CONCEPTO
    $out = [
        'tasa0_base'      => $hasRes['tasa0_base']      ? $res['tasa0_base']      : $con['tasa0_base'],
        'tasa16_base'     => $hasRes['tasa16_base']     ? $res['tasa16_base']     : $con['tasa16_base'],
        'iva_importe'     => $hasRes['iva_importe']     ? $res['iva_importe']     : $con['iva_importe'],
        'ieps_importe'    => $hasRes['ieps_importe']    ? $res['ieps_importe']    : $con['ieps_importe'],
        'isr_importe'     => $hasRes['isr_importe']     ? $res['isr_importe']     : $con['isr_importe'],
        'retencion_iva'   => $hasRes['retencion_iva']   ? $res['retencion_iva']   : $con['retencion_iva'],
        'retencion_ieps'  => $hasRes['retencion_ieps']  ? $res['retencion_ieps']  : $con['retencion_ieps'],
        'retencion_isr'   => $hasRes['retencion_isr']   ? $res['retencion_isr']   : $con['retencion_isr'],
    ];

    // Redondeo
    foreach ($out as $k => $v) {
        $out[$k] = $r2((float)$v);
    }
    return $out;
}