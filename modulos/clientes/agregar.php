<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$database = new Database();

// Obtener usuarios activos para Responsable
$database->query('SELECT id, nombre, apellidos FROM usuarios WHERE estatus = "activo" ORDER BY nombre, apellidos');
$usuarios = $database->resultSet();

// Normalizador de montos (acepta 1,000.50 o 1.000,50 o 500,00)
function normalize_money($val): float {
    $s = trim((string)$val);
    if ($s === '') return 0.0;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        // “1,234.56” -> quitar comas
        $s = str_replace(',', '', $s);
    } else {
        // “500,00” -> usar coma como decimal
        $s = str_replace(',', '.', $s);
    }
    return (float)$s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos básicos
    $razon_social   = sanitize($_POST['razon_social'] ?? '');
    $actividad      = sanitize($_POST['actividad'] ?? ''); // reemplaza nombre_comercial
    $rfc            = strtoupper(trim(sanitize($_POST['rfc'] ?? '')));
    $regimen_fiscal = sanitize($_POST['regimen_fiscal'] ?? '');
    $inicio_regimen = sanitize($_POST['inicio_regimen'] ?? '') ?: null;
    $domicilio_fiscal = sanitize($_POST['domicilio_fiscal'] ?? '');
    $codigo_postal  = sanitize($_POST['codigo_postal'] ?? '');
    $telefono       = sanitize($_POST['telefono'] ?? '');
    $email          = sanitize($_POST['email'] ?? '');
    $fecha_alta     = sanitize($_POST['fecha_alta'] ?? date('Y-m-d'));
    $estatus        = sanitize($_POST['estatus'] ?? 'activo');
    $responsable_id = isset($_POST['responsable_id']) && $_POST['responsable_id'] !== '' ? (int)sanitize($_POST['responsable_id']) : null;
    $notas          = sanitize($_POST['notas'] ?? '');

    // Nuevos campos de control
    $honorarios     = normalize_money($_POST['honorarios'] ?? '0');
    $periodicidad   = strtolower((string)($_POST['periodicidad'] ?? 'mensual'));
    if (!in_array($periodicidad, ['mensual','anual'], true)) $periodicidad = 'mensual';
    $nivel          = isset($_POST['nivel']) && $_POST['nivel'] !== '' ? (int)sanitize($_POST['nivel']) : 2; // 1..3
    $ultima_mes     = isset($_POST['ultima_declaracion_mes']) && $_POST['ultima_declaracion_mes'] !== '' ? (int)sanitize($_POST['ultima_declaracion_mes']) : null;
    $ultima_anio    = isset($_POST['ultima_declaracion_anio']) && $_POST['ultima_declaracion_anio'] !== '' ? (int)sanitize($_POST['ultima_declaracion_anio']) : null;
    $limite_dia     = isset($_POST['limite_declaracion_dia']) && $_POST['limite_declaracion_dia'] !== '' ? (int)sanitize($_POST['limite_declaracion_dia']) : null;

    // Validaciones
    $error = false;
    if ($razon_social === '') {
        flash('mensaje', 'La razón social es obligatoria', 'alert alert-danger');
        $error = true;
    }
    if ($rfc === '' || strlen($rfc) < 12 || strlen($rfc) > 13) {
        flash('mensaje', 'El RFC debe tener entre 12 y 13 caracteres', 'alert alert-danger');
        $error = true;
    }
    if ($fecha_alta === '') {
        flash('mensaje', 'La fecha de alta es obligatoria', 'alert alert-danger');
        $error = true;
    }
    if ($honorarios < 0) {
        flash('mensaje', 'El monto de honorarios no puede ser negativo', 'alert alert-danger');
        $error = true;
    }
    if ($nivel < 1 || $nivel > 3) {
        flash('mensaje', 'El nivel debe ser 1, 2 o 3', 'alert alert-danger');
        $error = true;
    }

    // RFC único
    $database->query('SELECT id FROM clientes WHERE rfc = :rfc');
    $database->bind(':rfc', $rfc);
    if ($database->single()) {
        flash('mensaje', 'El RFC ya está registrado para otro cliente', 'alert alert-danger');
        $error = true;
    }

    if (!$error) {
        // Insert con los nuevos campos
        $sql = 'INSERT INTO clientes 
            (razon_social, actividad, rfc, regimen_fiscal, inicio_regimen,
             domicilio_fiscal, codigo_postal, telefono, email, fecha_alta,
             estatus, responsable_id, honorarios, periodicidad, nivel,
             ultima_declaracion_mes, ultima_declaracion_anio, limite_declaracion_dia, notas)
            VALUES
            (:razon_social, :actividad, :rfc, :regimen_fiscal, :inicio_regimen,
             :domicilio_fiscal, :codigo_postal, :telefono, :email, :fecha_alta,
             :estatus, :responsable_id, :honorarios, :periodicidad, :nivel,
             :ultima_mes, :ultima_anio, :limite_dia, :notas)';

        $database->query($sql);
        $database->bind(':razon_social', $razon_social);
        $database->bind(':actividad', $actividad);
        $database->bind(':rfc', $rfc);
        $database->bind(':regimen_fiscal', $regimen_fiscal);
        $database->bind(':inicio_regimen', $inicio_regimen);
        $database->bind(':domicilio_fiscal', $domicilio_fiscal);
        $database->bind(':codigo_postal', $codigo_postal);
        $database->bind(':telefono', $telefono);
        $database->bind(':email', $email);
        $database->bind(':fecha_alta', $fecha_alta);
        $database->bind(':estatus', $estatus);
        $database->bind(':responsable_id', $responsable_id);
        $database->bind(':honorarios', $honorarios);
        $database->bind(':periodicidad', $periodicidad);
        $database->bind(':nivel', $nivel);
        $database->bind(':ultima_mes', $ultima_mes);
        $database->bind(':ultima_anio', $ultima_anio);
        $database->bind(':limite_dia', $limite_dia);
        $database->bind(':notas', $notas);

        if ($database->execute()) {
            flash('mensaje', 'Cliente agregado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/clientes/index.php');
        } else {
            flash('mensaje', 'Error al guardar el cliente', 'alert alert-danger');
        }
    }
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-6">
    <h2>Agregar Cliente</h2>
  </div>
  <div class="col-md-6 text-end">
    <a href="index.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-user-plus"></i> Formulario de Registro
  </div>
  <div class="card-body">
    <?php flash('mensaje'); ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post">
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="razon_social" class="form-label">Razón Social *</label>
          <input type="text" class="form-control" id="razon_social" name="razon_social" required>
        </div>
        <div class="col-md-6">
          <label for="actividad" class="form-label">Actividad</label>
          <input type="text" class="form-control" id="actividad" name="actividad">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="rfc" class="form-label">RFC *</label>
          <input type="text" class="form-control" id="rfc" name="rfc" maxlength="13" required>
        </div>
        <div class="col-md-4">
          <label for="regimen_fiscal" class="form-label">Régimen Fiscal</label>
          <input type="text" class="form-control" id="regimen_fiscal" name="regimen_fiscal" placeholder="Ej. 626">
        </div>
        <div class="col-md-4">
          <label for="inicio_regimen" class="form-label">Inicio de Régimen</label>
          <input type="date" class="form-control" id="inicio_regimen" name="inicio_regimen">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-12">
          <label for="domicilio_fiscal" class="form-label">Domicilio Fiscal</label>
          <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2"></textarea>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="codigo_postal" class="form-label">Código Postal</label>
          <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" maxlength="5">
        </div>
        <div class="col-md-4">
          <label for="telefono" class="form-label">Teléfono</label>
          <input type="text" class="form-control" id="telefono" name="telefono">
        </div>
        <div class="col-md-4">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="fecha_alta" class="form-label">Fecha Alta *</label>
          <input type="date" class="form-control" id="fecha_alta" name="fecha_alta" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-4">
          <label for="estatus" class="form-label">Estatus *</label>
          <select class="form-select" id="estatus" name="estatus" required>
            <option value="activo">Activo</option>
            <option value="suspendido">Suspendido</option>
            <option value="baja">Baja</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="responsable_id" class="form-label">Responsable</label>
          <select class="form-select" id="responsable_id" name="responsable_id">
            <option value="">Seleccione responsable</option>
            <?php foreach($usuarios as $usuario): ?>
              <option value="<?php echo (int)$usuario->id; ?>">
                <?php echo htmlspecialchars($usuario->nombre . ' ' . $usuario->apellidos, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-3">
          <label class="form-label">Honorarios</label>
          <input type="text" name="honorarios" class="form-control" inputmode="decimal" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Periodicidad</label>
          <select name="periodicidad" class="form-select">
            <option value="mensual">Mensual</option>
            <option value="anual">Anual</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nivel (Prioridad)</label>
          <select name="nivel" class="form-select">
            <option value="1">1</option>
            <option value="2" selected>2</option>
            <option value="3">3</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Límite de declaración (día)</label>
          <input type="number" name="limite_declaracion_dia" class="form-control" min="1" max="31" value="17">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-3">
          <label class="form-label">Última declaración (mes)</label>
          <select name="ultima_declaracion_mes" class="form-select">
            <option value="">—</option>
            <?php
              $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
              for ($m=1;$m<=12;$m++):
            ?>
              <option value="<?php echo $m; ?>"><?php echo $meses[$m]; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Última declaración (año)</label>
          <input type="number" name="ultima_declaracion_anio" class="form-control" min="2000" max="2100">
        </div>
        <div class="col-md-6">
          <label class="form-label">Notas</label>
          <textarea class="form-control" name="notas" rows="2"></textarea>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Guardar Cliente
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>