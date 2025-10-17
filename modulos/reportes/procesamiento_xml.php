<?php
// Función para procesar CFDIs emitidos con el tipo de comprobante correcto
function procesarCFDIEmitido($xml, $namespaces, $cliente_id, $database) {
    try {
        // Registrar namespaces importantes para CFDI
        if (isset($namespaces['cfdi'])) {
            $xml->registerXPathNamespace('cfdi', $namespaces['cfdi']);
        }
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', $namespaces['tfd']);
        }
        
        // Extraer datos del comprobante
        $comprobante = $xml;
        
        // Verificar si el XML tiene el formato esperado
        if (!isset($comprobante['Version']) && !isset($comprobante['version'])) {
            return ['status' => 'error', 'message' => 'El XML no tiene el formato de CFDI esperado'];
        }
        
        // Extraer UUID (Folio Fiscal)
        $tfd = $xml->xpath('//tfd:TimbreFiscalDigital');
        if (empty($tfd)) {
            return ['status' => 'error', 'message' => 'No se encontró el Timbre Fiscal Digital'];
        }
        $uuid = (string)$tfd[0]['UUID'];
        
        // Verificar si ya existe este UUID en la base de datos
        $database->query('SELECT id FROM CFDIs_Emitidas WHERE folio_fiscal = :uuid');
        $database->bind(':uuid', $uuid);
        if ($database->single()) {
            return ['status' => 'duplicate', 'message' => 'El CFDI ya existe en la base de datos'];
        }
        
        // Extraer tipo de comprobante directamente del atributo TipoDeComprobante
        $tipoComprobante = isset($comprobante['TipoDeComprobante']) ? (string)$comprobante['TipoDeComprobante'] : '';
        
        // Mapear el código del tipo de comprobante a un nombre más descriptivo
        $tipo_comprobante = mapearTipoComprobante($tipoComprobante);
        
        // Extraer datos básicos
        $folio_interno = isset($comprobante['Folio']) ? (string)$comprobante['Folio'] : '';
        $fecha_emision = isset($comprobante['Fecha']) ? (string)$comprobante['Fecha'] : '';
        $forma_pago = isset($comprobante['FormaPago']) ? (string)$comprobante['FormaPago'] : '';
        $metodo_pago = isset($comprobante['MetodoPago']) ? (string)$comprobante['MetodoPago'] : '';
        $subtotal = isset($comprobante['SubTotal']) ? (float)$comprobante['SubTotal'] : 0;
        $total = isset($comprobante['Total']) ? (float)$comprobante['Total'] : 0;
        
        // Extraer datos del receptor
        $receptor = $xml->xpath('//cfdi:Receptor');
        $nombre_receptor = '';
        $rfc_receptor = '';
        
        if (!empty($receptor)) {
            $nombre_receptor = isset($receptor[0]['Nombre']) ? (string)$receptor[0]['Nombre'] : '';
            $rfc_receptor = isset($receptor[0]['Rfc']) ? (string)$receptor[0]['Rfc'] : '';
        }

        // Extraer datos del emisor
        $emisor = $xml->xpath('//cfdi:Emisor');
        $nombre_emisor = '';
        $rfc_emisor = '';
        
        if (!empty($emisor)) {
            $nombre_emisor = isset($emisor[0]['Nombre']) ? (string)$emisor[0]['Nombre'] : '';
            $rfc_emisor = isset($emisor[0]['Rfc']) ? (string)$emisor[0]['Rfc'] : '';
        }
        

        // Buscar CFDIs relacionados
        $uuid_relacionado = '';
        $cfdiRelacionados = $xml->xpath('//cfdi:CfdiRelacionados/cfdi:CfdiRelacionado');
        if (!empty($cfdiRelacionados)) {
            $uuid_relacionado = (string)$cfdiRelacionados[0]['UUID'];
        }
        
        // Pago también puede tener docto relacionado
        if (empty($uuid_relacionado) && $tipoComprobante === 'P') {
            $doctoRelacionado = $xml->xpath('//pago20:DoctoRelacionado');
            if (empty($doctoRelacionado)) {
                $doctoRelacionado = $xml->xpath('//pago:DoctoRelacionado');
            }
            if (!empty($doctoRelacionado)) {
                $uuid_relacionado = isset($doctoRelacionado[0]['IdDocumento']) ? (string)$doctoRelacionado[0]['IdDocumento'] : '';
            }
        }
        
        // Inicializar variables para impuestos
        $tasa0 = 0;
        $tasa16 = 0;
        $iva = 0;
        
        // Extraer conceptos para la descripción
        $conceptos = $xml->xpath('//cfdi:Concepto');
        $descripcion = '';
        
        // Si es tipo Pago o Nómina, no procesar impuestos y conceptos igual
        if ($tipoComprobante !== "P" && $tipoComprobante !== "N") {
            foreach ($conceptos as $concepto) {
                $desc = isset($concepto['Descripcion']) ? (string)$concepto['Descripcion'] : '';
                $importe = isset($concepto['Importe']) ? (float)$concepto['Importe'] : 0;
                
                if (!empty($desc)) {
                    $descripcion .= $desc . ' - $' . number_format($importe, 2) . "\n";
                }
                
                // Buscar impuestos en cada concepto
                $traslados = $concepto->xpath('.//cfdi:Traslado');
                
                foreach ($traslados as $traslado) {
                    $impuesto = isset($traslado['Impuesto']) ? (string)$traslado['Impuesto'] : '';
                    
                    if ($impuesto === '002') { // IVA
                        $tasa = isset($traslado['TasaOCuota']) ? (float)$traslado['TasaOCuota'] : 0;
                        $base = isset($traslado['Base']) ? (float)$traslado['Base'] : 0;
                        $importe_impuesto = isset($traslado['Importe']) ? (float)$traslado['Importe'] : 0;
                        
                        if (abs($tasa - 0.16) < 0.001) {
                            $tasa16 += $base;
                            $iva += $importe_impuesto;
                        } elseif (abs($tasa) < 0.001) {
                            $tasa0 += $base;
                        }
                    }
                }
            }
            
            // Si no encontramos impuestos a nivel concepto, buscar a nivel comprobante
            if ($iva === 0 && $tasa16 === 0 && $tasa0 === 0) {
                $impuestos = $xml->xpath('//cfdi:Impuestos');
                
                if (!empty($impuestos)) {
                    $traslados_globales = $xml->xpath('//cfdi:Traslado');
                    
                    foreach ($traslados_globales as $traslado) {
                        $impuesto = isset($traslado['Impuesto']) ? (string)$traslado['Impuesto'] : '';
                        
                        if ($impuesto === '002') { // IVA
                            $tasa = isset($traslado['TasaOCuota']) ? (float)$traslado['TasaOCuota'] : 0;
                            $importe_impuesto = isset($traslado['Importe']) ? (float)$traslado['Importe'] : 0;
                            
                            if (abs($tasa - 0.16) < 0.001) {
                                $tasa16 = $subtotal;
                                $iva = $importe_impuesto;
                            } elseif (abs($tasa) < 0.001) {
                                $tasa0 = $subtotal;
                            }
                        }
                    }
                }
            }
            
            // Si seguimos sin detectar, hacer una estimación simple
            if ($iva === 0 && $tasa16 === 0 && $tasa0 === 0) {
                // Calcular diferencia entre total y subtotal
                $diferencia = $total - $subtotal;
                
                // Si hay diferencia, probablemente es IVA
                if (abs($diferencia) > 0.01) {
                    // Verificar si es aproximadamente 16%
                    if (abs($diferencia - ($subtotal * 0.16)) < ($subtotal * 0.01)) {
                        $iva = $diferencia;
                        $tasa16 = $subtotal;
                    } else {
                        // Si no es cerca de 16%, solo registramos el subtotal como tasa 0
                        $tasa0 = $subtotal;
                    }
                } else {
                    // Si no hay diferencia, todo es tasa 0
                    $tasa0 = $subtotal;
                }
            }
        } else {
            // Si es tipo Pago o Nómina, simplificar la extracción de la descripción
            if (!empty($conceptos)) {
                $concepto = $conceptos[0]; // Solo tomar el primer concepto
                $descripcion = isset($concepto['Descripcion']) ? (string)$concepto['Descripcion'] : '';
            }
        }
        
        // Insertar en la base de datos
        $database->query('INSERT INTO CFDIs_Emitidas (
                          cliente_id, tipo_comprobante, folio_interno, forma_pago, metodo_pago, folio_fiscal, 
                          fecha_emision, nombre_receptor, rfc_receptor, rfc_emisor, nombre_emisor, descripcion, 
                          subtotal, tasa0, tasa16, iva, total, uuid_relacionado) 
                          VALUES (
                          :cliente_id, :tipo_comprobante, :folio_interno, :forma_pago, :metodo_pago, :folio_fiscal, 
                          :fecha_emision, :nombre_receptor, :rfc_receptor, :rfc_emisor, :nombre_emisor, :descripcion, 
                          :subtotal, :tasa0, :tasa16, :iva, :total, :uuid_relacionado)');
        
        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':tipo_comprobante', $tipo_comprobante);
        $database->bind(':folio_interno', $folio_interno);
        $database->bind(':forma_pago', $forma_pago);
        $database->bind(':metodo_pago', $metodo_pago);
        $database->bind(':folio_fiscal', $uuid);
        $database->bind(':fecha_emision', $fecha_emision);
        $database->bind(':nombre_receptor', $nombre_receptor);
        $database->bind(':rfc_receptor', $rfc_receptor);
        $database->bind(':rfc_emisor', $rfc_emisor);
        $database->bind(':nombre_emisor', $nombre_emisor);
        $database->bind(':descripcion', $descripcion);
        $database->bind(':subtotal', $subtotal);
        $database->bind(':tasa0', $tasa0);
        $database->bind(':tasa16', $tasa16);
        $database->bind(':iva', $iva);
        $database->bind(':total', $total);
        $database->bind(':uuid_relacionado', $uuid_relacionado); // CFDIs emitidos no suelen tener UUID relacionado
        
        if ($database->execute()) {
            return ['status' => 'success', 'message' => 'CFDI procesado correctamente'];
        } else {
            return ['status' => 'error', 'message' => 'Error al guardar el CFDI en la base de datos'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Error al procesar el CFDI: ' . $e->getMessage()];
    }
}

// Función para procesar CFDIs recibidos con el tipo de comprobante correcto
function procesarCFDIRecibido($xml, $namespaces, $cliente_id, $database) {
    try {
        // Registrar namespaces importantes para CFDI
        if (isset($namespaces['cfdi'])) {
            $xml->registerXPathNamespace('cfdi', $namespaces['cfdi']);
        }
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', $namespaces['tfd']);
        }
        
        // Extraer datos del comprobante
        $comprobante = $xml;
        
        // Verificar si el XML tiene el formato esperado
        if (!isset($comprobante['Version']) && !isset($comprobante['version'])) {
            return ['status' => 'error', 'message' => 'El XML no tiene el formato de CFDI esperado'];
        }
        
        // Extraer UUID (Folio Fiscal)
        $tfd = $xml->xpath('//tfd:TimbreFiscalDigital');
        if (empty($tfd)) {
            return ['status' => 'error', 'message' => 'No se encontró el Timbre Fiscal Digital'];
        }
        $uuid = (string)$tfd[0]['UUID'];
        
        // Verificar si ya existe este UUID en la base de datos
        $database->query('SELECT id FROM CFDIs_Recibidas WHERE folio_fiscal = :uuid');
        $database->bind(':uuid', $uuid);
        if ($database->single()) {
            return ['status' => 'duplicate', 'message' => 'El CFDI ya existe en la base de datos'];
        }
        
        // Extraer tipo de comprobante directamente del atributo TipoDeComprobante
        $tipoComprobante = isset($comprobante['TipoDeComprobante']) ? (string)$comprobante['TipoDeComprobante'] : '';
        
        // Mapear el código del tipo de comprobante a un nombre más descriptivo
        $tipo_comprobante = mapearTipoComprobante($tipoComprobante);
        
        // Extraer fecha de certificación del TFD
        $fecha_certificacion = isset($tfd[0]['FechaTimbrado']) ? (string)$tfd[0]['FechaTimbrado'] : '';
        
        // Extraer datos básicos
        $forma_pago = isset($comprobante['FormaPago']) ? (string)$comprobante['FormaPago'] : '';
        $metodo_pago = isset($comprobante['MetodoPago']) ? (string)$comprobante['MetodoPago'] : '';
        $subtotal = isset($comprobante['SubTotal']) ? (float)$comprobante['SubTotal'] : 0;
        $total = isset($comprobante['Total']) ? (float)$comprobante['Total'] : 0;
        
        // Extraer datos del emisor
        $emisor = $xml->xpath('//cfdi:Emisor');
        $nombre_emisor = '';
        $rfc_emisor = '';
        
        if (!empty($emisor)) {
            $nombre_emisor = isset($emisor[0]['Nombre']) ? (string)$emisor[0]['Nombre'] : '';
            $rfc_emisor = isset($emisor[0]['Rfc']) ? (string)$emisor[0]['Rfc'] : '';
        }

        // Extraer datos del receptor
        $receptor = $xml->xpath('//cfdi:Receptor');
        $nombre_receptor = '';
        $rfc_receptor = '';
        
        if (!empty($receptor)) {
            $nombre_receptor = isset($receptor[0]['Nombre']) ? (string)$receptor[0]['Nombre'] : '';
            $rfc_receptor = isset($receptor[0]['Rfc']) ? (string)$receptor[0]['Rfc'] : '';
        }
        
        // Buscar CFDIs relacionados
        $uuid_relacionado = '';
        $cfdiRelacionados = $xml->xpath('//cfdi:CfdiRelacionados/cfdi:CfdiRelacionado');
        if (!empty($cfdiRelacionados)) {
            $uuid_relacionado = (string)$cfdiRelacionados[0]['UUID'];
        }
        
        // Pago también puede tener docto relacionado
        if (empty($uuid_relacionado) && $tipoComprobante === 'P') {
            $doctoRelacionado = $xml->xpath('//pago20:DoctoRelacionado');
            if (empty($doctoRelacionado)) {
                $doctoRelacionado = $xml->xpath('//pago:DoctoRelacionado');
            }
            if (!empty($doctoRelacionado)) {
                $uuid_relacionado = isset($doctoRelacionado[0]['IdDocumento']) ? (string)$doctoRelacionado[0]['IdDocumento'] : '';
            }
        }
        
        // Inicializar variables para impuestos
        $tasa0 = 0;
        $tasa16 = 0;
        $iva = 0;
        $retencion_iva = 0;
        $retencion_isr = 0;
        $retencion_ieps = 0;
        
        // Extraer conceptos para la descripción
        $conceptos = $xml->xpath('//cfdi:Concepto');
        $descripcion = '';
        
        // Si es tipo Pago o Nómina, no procesar impuestos y conceptos igual
        if ($tipoComprobante !== "P" && $tipoComprobante !== "N") {
            foreach ($conceptos as $concepto) {
                $desc = isset($concepto['Descripcion']) ? (string)$concepto['Descripcion'] : '';
                $importe = isset($concepto['Importe']) ? (float)$concepto['Importe'] : 0;
                
                if (!empty($desc)) {
                    $descripcion .= $desc . ' - $' . number_format($importe, 2) . "\n";
                }
                
                // Buscar impuestos en cada concepto
                $traslados = $concepto->xpath('.//cfdi:Traslado');
                foreach ($traslados as $traslado) {
                    $impuesto = isset($traslado['Impuesto']) ? (string)$traslado['Impuesto'] : '';
                    
                    if ($impuesto === '002') { // IVA
                        $tasa = isset($traslado['TasaOCuota']) ? (float)$traslado['TasaOCuota'] : 0;
                        $base = isset($traslado['Base']) ? (float)$traslado['Base'] : 0;
                        $importe_impuesto = isset($traslado['Importe']) ? (float)$traslado['Importe'] : 0;
                        
                        if (abs($tasa - 0.16) < 0.001) {
                            $tasa16 += $base;
                            $iva += $importe_impuesto;
                        } elseif (abs($tasa) < 0.001) {
                            $tasa0 += $base;
                        }
                    }
                }
                
                // Buscar retenciones en cada concepto
                $retenciones = $concepto->xpath('.//cfdi:Retencion');
                foreach ($retenciones as $retencion) {
                    $impuesto = isset($retencion['Impuesto']) ? (string)$retencion['Impuesto'] : '';
                    $importe_retencion = isset($retencion['Importe']) ? (float)$retencion['Importe'] : 0;
                    
                    if ($impuesto === '001') { // ISR
                        $retencion_isr += $importe_retencion;
                    } elseif ($impuesto === '002') { // IVA
                        $retencion_iva += $importe_retencion;
                    } elseif ($impuesto === '003') { // IEPS
                        $retencion_ieps += $importe_retencion;
                    }
                }
            }
            
            // Si no encontramos impuestos a nivel concepto, buscar a nivel comprobante
            if (($iva === 0 && $tasa16 === 0 && $tasa0 === 0) || 
                ($retencion_iva === 0 && $retencion_isr === 0 && $retencion_ieps === 0)) {
                
                // Buscar traslados globales
                $impuestos = $xml->xpath('//cfdi:Impuestos');
                if (!empty($impuestos)) {
                    // Traslados
                    $traslados_globales = $xml->xpath('//cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
                    foreach ($traslados_globales as $traslado) {
                        $impuesto = isset($traslado['Impuesto']) ? (string)$traslado['Impuesto'] : '';
                        
                        if ($impuesto === '002') { // IVA
                            $tasa = isset($traslado['TasaOCuota']) ? (float)$traslado['TasaOCuota'] : 0;
                            $importe_impuesto = isset($traslado['Importe']) ? (float)$traslado['Importe'] : 0;
                            
                            if (abs($tasa - 0.16) < 0.001) {
                                $tasa16 = $subtotal;
                                $iva = $importe_impuesto;
                            } elseif (abs($tasa) < 0.001) {
                                $tasa0 = $subtotal;
                            }
                        }
                    }
                    
                    // Retenciones
                    $retenciones_globales = $xml->xpath('//cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion');
                    foreach ($retenciones_globales as $retencion) {
                        $impuesto = isset($retencion['Impuesto']) ? (string)$retencion['Impuesto'] : '';
                        $importe_retencion = isset($retencion['Importe']) ? (float)$retencion['Importe'] : 0;
                        
                        if ($impuesto === '001') { // ISR
                            $retencion_isr += $importe_retencion;
                        } elseif ($impuesto === '002') { // IVA
                            $retencion_iva += $importe_retencion;
                        } elseif ($impuesto === '003') { // IEPS
                            $retencion_ieps += $importe_retencion;
                        }
                    }
                }
            }
            
            // Si sigue sin detectar, hacer una estimación
            if ($iva === 0 && $tasa16 === 0 && $tasa0 === 0 && 
                $retencion_iva === 0 && $retencion_isr === 0 && $retencion_ieps === 0) {
                
                // Calcular diferencia entre total y subtotal
                $diferencia = $total - $subtotal;
                
                // Si hay diferencia, analizar si son impuestos
                if (abs($diferencia) > 0.01) {
                    if ($diferencia > 0) {
                        // Diferencia positiva, probablemente es IVA
                        if (abs($diferencia - ($subtotal * 0.16)) < ($subtotal * 0.01)) {
                            $iva = $diferencia;
                            $tasa16 = $subtotal;
                        } else {
                            // Si no es cerca de 16%, solo registramos el subtotal como tasa 0
                            $tasa0 = $subtotal;
                        }
                    } else {
                        // Diferencia negativa, probablemente son retenciones
                        $diferencia_abs = abs($diferencia);
                        // Aproximaciones comunes de retenciones
                        if (abs($diferencia_abs - ($subtotal * 0.1067)) < ($subtotal * 0.01)) {
                            // Retenciones de honorarios: IVA 2/3, ISR 1/3 (aproximado)
                            $retencion_iva = round($diferencia_abs * 2/3, 2);
                            $retencion_isr = round($diferencia_abs * 1/3, 2);
                            $tasa16 = $subtotal;
                            $iva = round($subtotal * 0.16, 2);
                        } else {
                            // Si no coincide con un patrón, dividir en partes iguales
                            $retencion_iva = round($diferencia_abs / 2, 2);
                            $retencion_isr = round($diferencia_abs / 2, 2);
                        }
                    }
                } else {
                    // Si no hay diferencia, todo es tasa 0
                    $tasa0 = $subtotal;
                }
            }
        } else {
            // Si es tipo Pago o Nómina, simplificar la extracción de la descripción
            if (!empty($conceptos)) {
                $concepto = $conceptos[0]; // Solo tomar el primer concepto
                $descripcion = isset($concepto['Descripcion']) ? (string)$concepto['Descripcion'] : '';
            }
        }
        
        // Insertar en la base de datos
        $database->query('INSERT INTO CFDIs_Recibidas (
                          cliente_id, tipo_comprobante, forma_pago, metodo_pago, folio_fiscal, 
                          fecha_certificacion, nombre_emisor, rfc_emisor, rfc_receptor, nombre_receptor, descripcion, 
                          subtotal, tasa0, tasa16, iva, total, 
                          retencion_iva, retencion_isr, retencion_ieps, uuid_relacionado) 
                          VALUES (
                          :cliente_id, :tipo_comprobante, :forma_pago, :metodo_pago, :folio_fiscal, 
                          :fecha_certificacion, :nombre_emisor, :rfc_emisor, :rfc_receptor, :nombre_receptor, :descripcion, 
                          :subtotal, :tasa0, :tasa16, :iva, :total, 
                          :retencion_iva, :retencion_isr, :retencion_ieps, :uuid_relacionado)');
        
        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':tipo_comprobante', $tipo_comprobante);
        $database->bind(':forma_pago', $forma_pago);
        $database->bind(':metodo_pago', $metodo_pago);
        $database->bind(':folio_fiscal', $uuid);
        $database->bind(':fecha_certificacion', $fecha_certificacion);
        $database->bind(':nombre_emisor', $nombre_emisor);
        $database->bind(':rfc_emisor', $rfc_emisor);
        $database->bind(':rfc_receptor', $rfc_receptor);
        $database->bind(':nombre_receptor', $nombre_receptor);
        $database->bind(':descripcion', $descripcion);
        $database->bind(':subtotal', $subtotal);
        $database->bind(':tasa0', $tasa0);
        $database->bind(':tasa16', $tasa16);
        $database->bind(':iva', $iva);
        $database->bind(':total', $total);
        $database->bind(':retencion_iva', $retencion_iva);
        $database->bind(':retencion_isr', $retencion_isr);
        $database->bind(':retencion_ieps', $retencion_ieps);
        $database->bind(':uuid_relacionado', $uuid_relacionado);
        
        if ($database->execute()) {
            return ['status' => 'success', 'message' => 'CFDI procesado correctamente'];
        } else {
            return ['status' => 'error', 'message' => 'Error al guardar el CFDI en la base de datos'];
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Error al procesar el CFDI: ' . $e->getMessage()];
    }
}

// Función para mapear el código de tipo de comprobante a un nombre descriptivo
function mapearTipoComprobante($codigo) {
    switch ($codigo) {
        case 'I':
            return 'Ingreso';
        case 'E':
            return 'Egreso';
        case 'P':
            return 'Pago';
        case 'N':
            return 'Nómina';
        case 'T':
            return 'Traslado';
        default:
            return 'Otro';
    }
}
?>