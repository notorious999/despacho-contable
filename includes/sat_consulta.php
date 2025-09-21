<?php
declare(strict_types=1);

const SAT_SOAP_ENDPOINT = 'https://consultaqr.facturaelectronica.sat.gob.mx/ConsultaCFDIService.svc';
const SOAP_ACTION = 'http://tempuri.org/IConsultaCFDIService/Consulta';

if (!function_exists('sat_normalizeTotal')) {
    function sat_normalizeTotal(string $total): string {
        $t = trim(str_replace([' ', ','], '', $total));
        if ($t === '' || $t === '.' || $t[0] === '-' || !preg_match('/^\d+(\.\d+)?$/', $t)) {
            throw new InvalidArgumentException('Total inválido para SAT.');
        }
        [$int, $frac] = array_pad(explode('.', $t, 2), 2, '');
        $int = ltrim($int, '0'); if ($int === '') $int = '0';
        if ($frac === '') { $frac = '000000'; }
        else {
            if (strlen($frac) < 6) $frac = str_pad($frac, 6, '0');
            elseif (strlen($frac) > 6) {
                $keep = substr($frac, 0, 6);
                $roundDigit = (int)($frac[6] ?? '0');
                if ($roundDigit >= 5) {
                    $arr = str_split($keep); $carry = 1;
                    for ($i = 5; $i >= 0 && $carry; $i--) {
                        $d = (ord($arr[$i]) - 48) + $carry;
                        if ($d >= 10) { $arr[$i] = '0'; $carry = 1; } else { $arr[$i] = chr(48 + $d); $carry = 0; }
                    }
                    $keep = implode('', $arr);
                    if ($carry === 1) {
                        $ia = str_split($int); $carry = 1;
                        for ($i = count($ia)-1; $i >= 0 && $carry; $i--) {
                            $d = (ord($ia[$i]) - 48) + $carry;
                            if ($d >= 10) { $ia[$i] = '0'; $carry = 1; } else { $ia[$i] = chr(48 + $d); $carry = 0; }
                        }
                        if ($carry === 1) array_unshift($ia, '1');
                        $int = ltrim(implode('', $ia), '0'); if ($int === '') $int = '0';
                    }
                }
                $frac = $keep;
            }
        }
        if (strlen($int) < 10) $int = str_pad($int, 10, '0', STR_PAD_LEFT);
        return $int . '.' . $frac;
    }
}
if (!function_exists('sat_buildExpresion')) {
    function sat_buildExpresion(string $rfcEmisor, string $rfcReceptor, string $tt, string $uuid): string {
        return sprintf('?re=%s&rr=%s&tt=%s&id=%s',
            strtoupper(trim($rfcEmisor)),
            strtoupper(trim($rfcReceptor)),
            $tt,
            strtoupper(trim($uuid))
        );
    }
}
if (!function_exists('sat_consultaSoapPost')) {
    function sat_consultaSoapPost(string $expresion, bool $debug = false): array {
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
          . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
          . '<Consulta xmlns="http://tempuri.org/"><expresionImpresa>'
          . htmlspecialchars($expresion, ENT_XML1 | ENT_QUOTES, 'UTF-8')
          . '</expresionImpresa></Consulta></s:Body></s:Envelope>';

        $ch = curl_init(SAT_SOAP_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ' . SOAP_ACTION,
                'User-Agent: PHP-CFDI-Status-Client/1.3', 'Connection: close',
            ],
        ]);
        $body = curl_exec($ch);
        $errNo = curl_errno($ch); $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) throw new RuntimeException("cURL $errNo: $err");
        if ($code < 200 || $code >= 300) throw new RuntimeException("HTTP $code del SAT.");

        $xml = @simplexml_load_string($body);
        if ($xml === false) throw new RuntimeException('No se pudo parsear XML SOAP del SAT.');

        $nodes = $xml->xpath('//*[local-name()="ConsultaResult"]');
        if (!$nodes || !isset($nodes[0])) {
            $fault = $xml->xpath('//*[local-name()="Fault"]');
            if ($fault && isset($fault[0])) {
                $faultStr = trim((string)$fault[0]->faultstring);
                throw new RuntimeException('SOAP Fault: ' . ($faultStr ?: 'Desconocido'));
            }
            throw new RuntimeException('Sin ConsultaResult en respuesta SAT.');
        }
        $res = $nodes[0];
        $get = function($name) use($res){ $n = $res->xpath('.//*[local-name()="'.$name.'"]'); return ($n && isset($n[0])) ? trim((string)$n[0]) : null; };
        return [
            'codigoEstatus' => $get('CodigoEstatus'),
            'estado' => $get('Estado'),
            'esCancelable' => $get('EsCancelable'),
            'estatusCancelacion' => $get('EstatusCancelacion'),
            'raw' => $body,
        ];
    }
}