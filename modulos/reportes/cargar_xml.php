<?php
// Configurar límites de PHP
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '52M');
ini_set('max_file_uploads', '100');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/cfdi_impuestos.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$database = new Database();

// Clientes
$database->query('SELECT id, razon_social, rfc FROM Clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $database->resultSet();

$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';

// Límites del sistema
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size       = ini_get('post_max_size');
$max_file_uploads    = ini_get('max_file_uploads');
$memory_limit        = ini_get('memory_limit');
$max_execution_time  = ini_get('max_execution_time');

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['cliente_id'])) {
        flash('mensaje', 'Debe seleccionar un cliente', 'alert alert-danger');
    } elseif (empty($_FILES['xml_files']['name'][0])) {
        flash('mensaje', 'Debe seleccionar al menos un archivo XML', 'alert alert-danger');
    } else {
        $cliente_id = sanitize($_POST['cliente_id']);
        $naturaleza = sanitize($_POST['tipo_comprobante']); // 'emitida' | 'recibida'

        $archivos_procesados = 0;
        $archivos_error = 0;
        $archivos_duplicados = 0;
        $errores = [];

        $archivos_totales = count($_FILES['xml_files']['name']);

        for ($i = 0; $i < $archivos_totales; $i++) {
            $nombreArchivo = $_FILES['xml_files']['name'][$i];

            if ($_FILES['xml_files']['error'][$i] !== UPLOAD_ERR_OK) {
                $archivos_error++;
                $errores[] = "Error al cargar el archivo $nombreArchivo: " . getFileUploadError($_FILES['xml_files']['error'][$i]);
                continue;
            }

            $tmp_name = $_FILES['xml_files']['tmp_name'][$i];
            $xml_content = @file_get_contents($tmp_name);
            if ($xml_content === false) {
                $archivos_error++;
                $errores[] = "Error al leer el archivo $nombreArchivo";
                continue;
            }

            try {
                $xml = new SimpleXMLElement($xml_content);
                $namespaces = $xml->getNamespaces(true);

                if ($naturaleza === 'emitida') {
                    $resultado = procesarCFDIEmitido($xml, $namespaces, $cliente_id, $database);
                } else {
                    $resultado = procesarCFDIRecibido($xml, $namespaces, $cliente_id, $database);
                }

                if (!is_array($resultado) || !isset($resultado['status'])) {
                    $archivos_error++;
                    $errores[] = "Respuesta desconocida al procesar $nombreArchivo";
                } elseif ($resultado['status'] === 'success') {
                    $archivos_procesados++;
                } elseif ($resultado['status'] === 'duplicate') {
                    $archivos_duplicados++;
                } else {
                    $archivos_error++;
                    $errores[] = ($resultado['message'] ?? 'Error desconocido') . " en archivo $nombreArchivo";
                }
            } catch (Throwable $e) {
                $archivos_error++;
                $errores[] = "Error al procesar el XML: " . $e->getMessage() . " en archivo $nombreArchivo";
            }

            unset($xml, $xml_content);
        }

        if ($archivos_procesados > 0) {
            $mensaje = "Se procesaron correctamente $archivos_procesados archivos de $archivos_totales. ";
            if ($archivos_duplicados > 0) $mensaje .= "$archivos_duplicados duplicados. ";
            if ($archivos_error > 0) {
                $mensaje .= "$archivos_error con errores.";
                flash('mensaje', $mensaje, 'alert alert-warning');
                $_SESSION['errores_xml'] = $errores;
            } else {
                flash('mensaje', $mensaje, 'alert alert-success');
            }
        } else {
            if ($archivos_duplicados > 0 && $archivos_error == 0) {
                flash('mensaje', "Los $archivos_duplicados archivos ya existen en la base de datos.", 'alert alert-info');
            } else {
                flash('mensaje', "No se pudo procesar ningún archivo. $archivos_error tuvieron errores.", 'alert alert-danger');
                $_SESSION['errores_xml'] = $errores;
            }
        }
    }
}

function getFileUploadError($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:  return "El archivo excede upload_max_filesize";
        case UPLOAD_ERR_FORM_SIZE: return "El archivo excede MAX_FILE_SIZE";
        case UPLOAD_ERR_PARTIAL:   return "El archivo se cargó parcialmente";
        case UPLOAD_ERR_NO_FILE:   return "No se cargó ningún archivo";
        case UPLOAD_ERR_NO_TMP_DIR:return "Falta la carpeta temporal";
        case UPLOAD_ERR_CANT_WRITE:return "No se pudo escribir el archivo en el disco";
        case UPLOAD_ERR_EXTENSION: return "Una extensión detuvo la carga";
        default: return "Error desconocido en la carga";
    }
}

if (!function_exists('sx_attr')) {
    function sx_attr(SimpleXMLElement $node, string $attr) {
        if (isset($node[$attr])) return (string)$node[$attr];
        $alts = [$attr, strtolower($attr), strtoupper($attr)];
        foreach ($alts as $a) if (isset($node[$a])) return (string)$node[$a];
        return '';
    }
}
if (!function_exists('mapearTipoComprobante')) {
    function mapearTipoComprobante(string $tipoC): string {
        $map = ['I'=>'Ingreso','E'=>'Egreso','P'=>'Pago','N'=>'Nomina','T'=>'Traslado'];
        return $map[$tipoC] ?? $tipoC;
    }
}

// -------- Procesar Emitidas --------
function procesarCFDIEmitido(SimpleXMLElement $xml, array $namespaces, $cliente_id, $database) {
    try {
        $compNodes = $xml->xpath('/*[local-name()="Comprobante"]');
        if (!$compNodes || !isset($compNodes[0])) {
            return ['status' => 'error', 'message' => 'No se encontró el nodo Comprobante'];
        }
        $comprobante = $compNodes[0];

        $tfdNodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
        if (!$tfdNodes || !isset($tfdNodes[0]['UUID'])) {
            return ['status' => 'error', 'message' => 'No se encontró el Timbre Fiscal Digital (UUID)'];
        }
        $uuid = strtoupper((string)$tfdNodes[0]['UUID']);

        // Duplicado
        $database->query('SELECT id FROM CFDIs_Emitidas WHERE folio_fiscal = :uuid');
        $database->bind(':uuid', $uuid);
        if ($database->single()) return ['status' => 'duplicate', 'message' => 'CFDI ya existente'];

        $tipoC = strtoupper(trim(sx_attr($comprobante, 'TipoDeComprobante')));
        $tipo_comprobante = mapearTipoComprobante($tipoC);

        $folio_interno = (string)sx_attr($comprobante, 'Folio');
        $fecha_emision = (string)sx_attr($comprobante, 'Fecha');
        $forma_pago    = (string)sx_attr($comprobante, 'FormaPago');
        $metodo_pago   = (string)sx_attr($comprobante, 'MetodoPago');
        $subtotal      = (float)((string)sx_attr($comprobante, 'SubTotal') ?: 0);
        $total         = (float)((string)sx_attr($comprobante, 'Total') ?: 0);

        // Resumen de impuestos según reglas solicitadas
        $imp = cfdi_impuestos_resumen_from_xml($xml->asXML());
        $tasa0_base      = $imp['tasa0_base'];
        $tasa16_base     = $imp['tasa16_base'];
        $iva_importe     = $imp['iva_importe'];
        $ieps_importe    = $imp['ieps_importe'];
        $isr_importe     = $imp['isr_importe'];
        $ret_iva         = $imp['retencion_iva'];
        $ret_ieps        = $imp['retencion_ieps'];
        $ret_isr         = $imp['retencion_isr'];

        // Receptor
        $recNodes = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Receptor"]');
        $nombre_receptor = $recNodes && isset($recNodes[0]['Nombre']) ? (string)$recNodes[0]['Nombre'] : '';
        $rfc_receptor    = $recNodes ? ((isset($recNodes[0]['Rfc']) ? (string)$recNodes[0]['Rfc'] : (isset($recNodes[0]['RFC']) ? (string)$recNodes[0]['RFC'] : ''))) : '';

        // Campos de compatibilidad antigua (si los usas aún en vistas)
        $tasa0 = (float)$tasa0_base;
        $tasa16 = (float)$tasa16_base;
        $iva = (float)$iva_importe;

        // Descripción rápida
        $descNode = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Conceptos"]/*[local-name()="Concepto"]');
        $descripcion = ($descNode && isset($descNode[0]['Descripcion'])) ? (string)$descNode[0]['Descripcion'] : '';

        // Insert
        $database->query('INSERT INTO CFDIs_Emitidas (
            cliente_id, tipo_comprobante, folio_interno, forma_pago, metodo_pago, folio_fiscal,
            fecha_emision, nombre_receptor, rfc_receptor, descripcion,
            subtotal, tasa0, tasa16, iva, total,
            tasa0_base, tasa16_base, iva_importe, ieps_importe, isr_importe,
            retencion_iva, retencion_ieps, retencion_isr
        ) VALUES (
            :cliente_id, :tipo_comprobante, :folio_interno, :forma_pago, :metodo_pago, :folio_fiscal,
            :fecha_emision, :nombre_receptor, :rfc_receptor, :descripcion,
            :subtotal, :tasa0, :tasa16, :iva, :total,
            :tasa0_base, :tasa16_base, :iva_importe, :ieps_importe, :isr_importe,
            :retencion_iva, :retencion_ieps, :retencion_isr
        )');

        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':tipo_comprobante', $tipo_comprobante);
        $database->bind(':folio_interno', $folio_interno);
        $database->bind(':forma_pago', $forma_pago);
        $database->bind(':metodo_pago', $metodo_pago);
        $database->bind(':folio_fiscal', $uuid);
        $database->bind(':fecha_emision', $fecha_emision);
        $database->bind(':nombre_receptor', $nombre_receptor);
        $database->bind(':rfc_receptor', $rfc_receptor);
        $database->bind(':descripcion', $descripcion);
        $database->bind(':subtotal', $subtotal);
        $database->bind(':tasa0', $tasa0);
        $database->bind(':tasa16', $tasa16);
        $database->bind(':iva', $iva);
        $database->bind(':total', $total);

        $database->bind(':tasa0_base',   $tasa0_base);
        $database->bind(':tasa16_base',  $tasa16_base);
        $database->bind(':iva_importe',  $iva_importe);
        $database->bind(':ieps_importe', $ieps_importe);
        $database->bind(':isr_importe',  $isr_importe);

        $database->bind(':retencion_iva',  $ret_iva);
        $database->bind(':retencion_ieps', $ret_ieps);
        $database->bind(':retencion_isr',  $ret_isr);

        return $database->execute()
            ? ['status' => 'success', 'message' => 'CFDI emitido procesado correctamente']
            : ['status' => 'error', 'message' => 'Error al guardar CFDI emitido en BD'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// -------- Procesar Recibidas --------
function procesarCFDIRecibido(SimpleXMLElement $xml, array $namespaces, $cliente_id, $database) {
    try {
        $compNodes = $xml->xpath('/*[local-name()="Comprobante"]');
        if (!$compNodes || !isset($compNodes[0])) {
            return ['status' => 'error', 'message' => 'No se encontró el nodo Comprobante'];
        }
        $comprobante = $compNodes[0];

        $tfdNodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
        if (!$tfdNodes || !isset($tfdNodes[0]['UUID'])) {
            return ['status' => 'error', 'message' => 'No se encontró el Timbre Fiscal Digital (UUID)'];
        }
        $uuid = strtoupper((string)$tfdNodes[0]['UUID']);

        // Duplicado
        $database->query('SELECT id FROM CFDIs_Recibidas WHERE folio_fiscal = :uuid');
        $database->bind(':uuid', $uuid);
        if ($database->single()) return ['status' => 'duplicate', 'message' => 'CFDI ya existente'];

        $tipoC = strtoupper(trim(sx_attr($comprobante, 'TipoDeComprobante')));
        $tipo_comprobante = mapearTipoComprobante($tipoC);

        $fecha_certificacion = isset($tfdNodes[0]['FechaTimbrado']) ? (string)$tfdNodes[0]['FechaTimbrado'] : '';

        $forma_pago  = (string)sx_attr($comprobante, 'FormaPago');
        $metodo_pago = (string)sx_attr($comprobante, 'MetodoPago');
        $subtotal    = (float)((string)sx_attr($comprobante, 'SubTotal') ?: 0);
        $total       = (float)((string)sx_attr($comprobante, 'Total') ?: 0);

        // Resumen de impuestos según reglas solicitadas
        $imp = cfdi_impuestos_resumen_from_xml($xml->asXML());
        $tasa0_base      = $imp['tasa0_base'];
        $tasa16_base     = $imp['tasa16_base'];
        $iva_importe     = $imp['iva_importe'];
        $ieps_importe    = $imp['ieps_importe'];
        $isr_importe     = $imp['isr_importe'];
        $ret_iva         = $imp['retencion_iva'];
        $ret_ieps        = $imp['retencion_ieps'];
        $ret_isr         = $imp['retencion_isr'];

        // Emisor
        $emiNodes = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Emisor"]');
        $nombre_emisor = $emiNodes && isset($emiNodes[0]['Nombre']) ? (string)$emiNodes[0]['Nombre'] : '';
        $rfc_emisor    = $emiNodes ? ((isset($emiNodes[0]['Rfc']) ? (string)$emiNodes[0]['Rfc'] : (isset($emiNodes[0]['RFC']) ? (string)$emiNodes[0]['RFC'] : ''))) : '';

        // Relacionados
        $uuid_relacionado = '';
        $rel = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="CfdiRelacionados"]/*[local-name()="CfdiRelacionado"]');
        if ($rel && isset($rel[0]['UUID'])) $uuid_relacionado = (string)$rel[0]['UUID'];
        if ($uuid_relacionado === '' && $tipoC === 'P') {
            $docRel = $xml->xpath('//*[local-name()="DoctoRelacionado"]');
            if ($docRel && isset($docRel[0]['IdDocumento'])) $uuid_relacionado = (string)$docRel[0]['IdDocumento'];
        }

        // Compatibilidad antigua
        $tasa0 = (float)$tasa0_base;
        $tasa16 = (float)$tasa16_base;
        $iva = (float)$iva_importe;

        // Descripción
        $descNode = $xml->xpath('/*[local-name()="Comprobante"]/*[local-name()="Conceptos"]/*[local-name()="Concepto"]');
        $descripcion = ($descNode && isset($descNode[0]['Descripcion'])) ? (string)$descNode[0]['Descripcion'] : '';

        // Insert
        $database->query('INSERT INTO CFDIs_Recibidas (
            cliente_id, tipo_comprobante, forma_pago, metodo_pago, folio_fiscal,
            fecha_certificacion, nombre_emisor, rfc_emisor, descripcion,
            subtotal, tasa0, tasa16, iva, total,
            retencion_iva, retencion_isr, retencion_ieps, uuid_relacionado,
            tasa0_base, tasa16_base, iva_importe, ieps_importe, isr_importe
        ) VALUES (
            :cliente_id, :tipo_comprobante, :forma_pago, :metodo_pago, :folio_fiscal,
            :fecha_certificacion, :nombre_emisor, :rfc_emisor, :descripcion,
            :subtotal, :tasa0, :tasa16, :iva, :total,
            :retencion_iva, :retencion_isr, :retencion_ieps, :uuid_relacionado,
            :tasa0_base, :tasa16_base, :iva_importe, :ieps_importe, :isr_importe
        )');

        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':tipo_comprobante', $tipo_comprobante);
        $database->bind(':forma_pago', $forma_pago);
        $database->bind(':metodo_pago', $metodo_pago);
        $database->bind(':folio_fiscal', $uuid);
        $database->bind(':fecha_certificacion', $fecha_certificacion);
        $database->bind(':nombre_emisor', $nombre_emisor);
        $database->bind(':rfc_emisor', $rfc_emisor);
        $database->bind(':descripcion', $descripcion);
        $database->bind(':subtotal', $subtotal);
        $database->bind(':tasa0', $tasa0);
        $database->bind(':tasa16', $tasa16);
        $database->bind(':iva', $iva);
        $database->bind(':total', $total);

        $database->bind(':retencion_iva', $ret_iva);
        $database->bind(':retencion_isr', $ret_isr);
        $database->bind(':retencion_ieps', $ret_ieps);
        $database->bind(':uuid_relacionado', $uuid_relacionado);

        $database->bind(':tasa0_base',   $tasa0_base);
        $database->bind(':tasa16_base',  $tasa16_base);
        $database->bind(':iva_importe',  $iva_importe);
        $database->bind(':ieps_importe', $ieps_importe);
        $database->bind(':isr_importe',  $isr_importe);

        return $database->execute()
            ? ['status' => 'success', 'message' => 'CFDI recibido procesado correctamente']
            : ['status' => 'error', 'message' => 'Error al guardar CFDI recibido en BD'];
    } catch (Throwable $e) {
        return ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6"><h2>Cargar Facturas (XML)</h2></div>
    <div class="col-md-6 text-end">
        <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-upload"></i> Formulario de Carga</div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        <?php if (isset($_SESSION['errores_xml']) && !empty($_SESSION['errores_xml'])): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Errores de procesamiento:</h5>
            <div style="max-height: 200px; overflow-y: auto;">
                <ul><?php foreach ($_SESSION['errores_xml'] as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
            </div>
        </div>
        <?php unset($_SESSION['errores_xml']); endif; ?>

        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Límites del sistema:</h5>
            <ul>
                <li>Tamaño máximo por archivo: <?php echo htmlspecialchars($upload_max_filesize, ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Tamaño máximo total: <?php echo htmlspecialchars($post_max_size, ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Número máximo de archivos: <?php echo htmlspecialchars($max_file_uploads, ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Límite de memoria: <?php echo htmlspecialchars($memory_limit, ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Tiempo máximo de ejecución: <?php echo htmlspecialchars($max_execution_time, ENT_QUOTES, 'UTF-8'); ?> segundos</li>
            </ul>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?php echo (int)$cliente->id; ?>" <?php echo ($cliente_id == $cliente->id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cliente->razon_social . ' (' . $cliente->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="tipo_comprobante" class="form-label">Tipo de Comprobante *</label>
                    <select class="form-select" id="tipo_comprobante" name="tipo_comprobante" required>
                        <option value="emitida">Facturas Emitidas</option>
                        <option value="recibida">Facturas Recibidas</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="xml_files" class="form-label">Archivos XML *</label>
                <input class="form-control" type="file" id="xml_files" name="xml_files[]" accept=".xml" multiple required>
                <div class="form-text">Puede seleccionar múltiples archivos XML. También puede arrastrar y soltar.</div>
                <div id="preview" class="mt-2"></div>
            </div>

            <div class="row"><div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Procesar Archivos
                </button>
            </div></div>
        </form>
    </div>
</div>

<script>
// Arrastrar/soltar + previsualización
const dropArea = document.getElementById('xml_files');
const preview = document.getElementById('preview');
['dragenter','dragover','dragleave','drop'].forEach(ev => dropArea.addEventListener(ev, e => {e.preventDefault();e.stopPropagation();}, false));
['dragenter','dragover'].forEach(ev => dropArea.addEventListener(ev, () => dropArea.classList.add('border-primary'), false));
['dragleave','drop'].forEach(ev => dropArea.addEventListener(ev, () => dropArea.classList.remove('border-primary'), false));
dropArea.addEventListener('drop', e => { const files = e.dataTransfer.files; handleFiles({target:{files}}); }, false);
dropArea.addEventListener('change', handleFiles, false);

function handleFiles(e){ updateFilePreview(e.target.files); }
function updateFilePreview(files){
  preview.innerHTML = '';
  if (!files || files.length===0) return;
  const head = document.createElement('div');
  head.className='alert alert-info mt-2';
  head.innerHTML = `<i class="fas fa-file-code"></i> ${files.length} archivo(s) seleccionado(s)`;
  preview.appendChild(head);
  const limit = Math.min(files.length, 10);
  for (let i=0;i<limit;i++){
    const div=document.createElement('div');
    div.className='small text-muted ms-2';
    div.innerHTML=`<i class="fas fa-file"></i> ${files[i].name}`;
    preview.appendChild(div);
  }
  if (files.length>limit){
    const more=document.createElement('div');
    more.className='small text-muted ms-2';
    more.innerHTML=`<i class="fas fa-ellipsis-h"></i> y ${files.length-limit} archivo(s) más...`;
    preview.appendChild(more);
  }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>