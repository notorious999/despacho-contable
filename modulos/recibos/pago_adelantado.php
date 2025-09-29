<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-3">
  <div class="col">
    <h3>Registrar pago adelantado</h3>
    <div class="text-muted">Busca un cliente, selecciona los meses y registra el pago.</div>
  </div>
  <div class="col text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
  </div>
</div>

<?php flash('mensaje'); ?>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?php echo URL_ROOT; ?>/modulos/recibos/registrar_pago_adelantado.php" id="formPagoAdelantado">
      <div class="row g-3">
        <div class="col-md-7">
          <label class="form-label">Buscar cliente (Razón social o RFC)</label>
          <input type="text" id="pa_search" class="form-control" placeholder="Escribe para buscar...">
          <div id="pa_results" class="list-group mt-2" style="max-height:270px; overflow:auto;">
            <div class="list-group-item text-muted small">Escribe para buscar…</div>
          </div>
        </div>
        <div class="col-md-5">
          <label class="form-label">Cliente seleccionado</label>
          <div class="input-group">
            <input type="text" id="pa_cliente_text" class="form-control" placeholder="Ninguno" readonly>
            <button type="button" id="pa_clear" class="btn btn-outline-danger" title="Quitar selección">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <input type="hidden" name="cliente_id" id="pa_cliente_id" required>
          <div class="form-text">Haz clic en un resultado para seleccionarlo.</div>

          <div class="mt-3">
            <label class="form-label">Vencimiento para nuevos recibos (opcional)</label>
            <input type="date" name="fecha_vencimiento" class="form-control">
          </div>
        </div>

        <div class="col-md-12">
          <label class="form-label">Meses a pagar (adelantados)</label>
          <select name="meses[]" id="selMesesPA" class="form-select" multiple required></select>
          <div class="form-text">Selecciona uno o varios meses futuros; se crearán (si faltan) y se pagarán completos.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Método</label>
          <select name="metodo" class="form-select">
            <option value="">No especificado</option>
            <option>Transferencia</option>
            <option>Efectivo</option>
            <option>Tarjeta</option>
            <option>Cheque</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Referencia</label>
          <input type="text" name="referencia" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Observaciones</label>
          <input type="text" name="observaciones" class="form-control">
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Registrar</button>
        <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  // Construye los próximos 12 meses
  const $meses = document.getElementById('selMesesPA');
  function buildNextMonths(n=12){
    $meses.innerHTML = '';
    const now = new Date();
    let y = now.getFullYear();
    let m = now.getMonth()+1;
    for (let i=0;i<n;i++){
      const ym = y+'-'+String(m).padStart(2,'0');
      const d = new Date(y, m-1, 1);
      const label = d.toLocaleDateString('es-MX', {month:'long', year:'numeric'});
      const opt = document.createElement('option');
      opt.value = ym; opt.textContent = label;
      $meses.appendChild(opt);
      m++; if (m>12){ m=1; y++; }
    }
  }
  buildNextMonths(12);

  const inputSearch = document.getElementById('pa_search');
  const results = document.getElementById('pa_results');
  const selText = document.getElementById('pa_cliente_text');
  const selId = document.getElementById('pa_cliente_id');
  const btnClear = document.getElementById('pa_clear');
  const form = document.getElementById('formPagoAdelantado');

  let t = null;
  function debounce(fn, ms){ return function(...args){ clearTimeout(t); t=setTimeout(()=>fn.apply(this,args), ms); }; }

  async function fetchClientes(term, page=1){
    const url = '<?php echo URL_ROOT; ?>/modulos/clientes/buscar.php?q=' + encodeURIComponent(term||'') + '&page=' + page;
    try {
      const r = await fetch(url, { headers: { 'Accept':'application/json' } });
      if (!r.ok) throw new Error('HTTP '+r.status);
      const data = await r.json();
      return data && data.results ? data.results : [];
    } catch(e) {
      console.warn('Error buscando clientes:', e);
      return [];
    }
  }

  function renderResults(items){
    results.innerHTML = '';
    if (!items.length) {
      results.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
      return;
    }
    items.forEach(it => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action';
      btn.textContent = it.text;
      btn.dataset.id = it.id;
      btn.dataset.text = it.text;
      btn.addEventListener('click', () => {
        selId.value = it.id;
        selText.value = it.text;
        results.innerHTML = '<div class="list-group-item small text-success">Seleccionado: '+it.text+'</div>';
      });
      results.appendChild(btn);
    });
  }

  const doSearch = debounce(async function(){
    const term = inputSearch.value.trim();
    const items = await fetchClientes(term, 1);
    renderResults(items);
  }, 300);

  inputSearch.addEventListener('input', doSearch);

  btnClear.addEventListener('click', () => {
    selId.value = '';
    selText.value = '';
    inputSearch.focus();
  });

  form.addEventListener('submit', (e) => {
    if (!selId.value) {
      e.preventDefault();
      alert('Selecciona un cliente de la lista.');
      inputSearch.focus();
      return false;
    }
  });

  // Carga inicial (primeros 20)
  doSearch();
})();
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>