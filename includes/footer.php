</div> <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        // Usar $(function(){...}) como alias corto de $(document).ready()
        $(function(){ // Ejecutar cuando el DOM esté listo y jQuery disponible

            // --- INICIALIZACIÓN JQUERY DATATABLES ---
            if ($('#datatablesSimple').length) {
                try {
                    $('#datatablesSimple').DataTable({
                        language: { // Traducciones
                            search: "Buscar:", lengthMenu: "Mostrar _MENU_", info: "Mostrando _START_-_END_ de _TOTAL_",
                            infoEmpty: "Mostrando 0-0 de 0", infoFiltered: "(filtrado de _MAX_)",
                            paginate: { first: "<<", last: ">>", next: ">", previous: "<" },
                            zeroRecords: "No se encontraron registros", emptyTable: "Sin datos"
                        },
                        order: [],
                    });
                } catch (e) { console.error("Error inicializando DataTables:", e); }
            }

            // --- SCRIPT BOTÓN MARCAR CORTESÍA (Para control_honorarios/index.php) ---
            if ($('#datatablesSimple').length && $('.btn-marcar-cortesia').length) {
                $('body').on('click', '.btn-marcar-cortesia', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const clienteId = button.data('cliente-id'); const anio = button.data('anio'); const mes = button.data('mes'); const clienteNombre = button.data('cliente-nombre');
                    const titleAttr = button.attr('title') || ''; const titleParts = titleAttr.split('como');
                    const periodoTexto = titleParts[0] ? titleParts[0].replace('Marcar', '').trim() : `el periodo ${anio}-${mes}`;
                    
                    if (!clienteId || !anio || !mes) { alert('Error: Faltan datos.'); return; }

                    if (confirm(`¿Marcar honorario de "${clienteNombre}" para ${periodoTexto} como CORTESÍA (monto $0.00)?`)) {
                        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                        $.ajax({
                            url: '<?php echo URL_ROOT; ?>/modulos/control_honorarios/marcar_cortesia.php', type: 'POST', data: { cliente_id: clienteId, anio: anio, mes: mes }, dataType: 'json',
                            success: function(response) {
                                if (response && response.success) {
                                    const row = button.closest('tr');
                                    row.find('td:eq(3)').html('<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-1" title="Cortesía"><i class="fas fa-gift me-1"></i>Cortesía</span>');
                                    row.find('td:eq(2)').html('<span class="text-info">' + formatCurrency(0) + '</span>');
                                    button.remove();
                                    alert(response.message || '¡Marcado!');
                                } else { alert('Error: ' + (response && response.message ? response.message : 'Respuesta inválida.')); button.prop('disabled', false).html('<i class="fas fa-gift"></i>'); }
                            },
                            error: function(jqXHR, textStatus) { console.error("Error AJAX cortesia:", textStatus); alert('Error de conexión/servidor ('+ textStatus +'). Revise logs.'); button.prop('disabled', false).html('<i class="fas fa-gift"></i>'); }
                        });
                    } else { button.prop('disabled', false); }
                });
                function formatCurrency(amount) { const num = parseFloat(amount); if (isNaN(num)) return '$0.00'; return '$' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
            }
            // --- FIN SCRIPT BOTÓN CORTESÍA ---


            // --- INICIO SCRIPT NUEVO_SERVICIO.PHP ---
            if ($('#formNuevoServicio').length) {

                let clienteChoicesInstance = null;
                const clienteSelectElement = document.getElementById('cliente_id');
                if (clienteSelectElement) {
                    try {
                        clienteChoicesInstance = new Choices(clienteSelectElement, {
                            searchEnabled: true, itemSelectText: 'Seleccionar', noResultsText: 'No encontrado', noChoicesText: 'No hay más',
                            placeholder: true, placeholderValue: 'Busca o selecciona un cliente...'
                        });
                    } catch(e) { console.error("Error inicializando Choices.js:", e); }
                }

                const container = $('#servicios-container');
                let montoHonorarioCliente = 0;
                let periodosSeleccionados = [];

                $('#add-servicio').on('click', function() {
                    const newItemHtml = `
                        <div class="row servicio-item mb-2 align-items-center">
                            <div class="col-md-7"> <label class="form-label visually-hidden">Desc</label> <input type="text" name="descripcion[]" class="form-control form-control-sm" placeholder="Descripción del servicio" required> <input type="hidden" name="es_honorario[]" value="0"> <input type="hidden" name="fila_id[]" value="srv_${Date.now()}_${Math.random().toString(36).substring(2, 7)}"> </div>
                            <div class="col-md-3"> <label class="form-label visually-hidden">Imp</label> <input type="number" name="importe[]" class="form-control form-control-sm importe text-end" placeholder="Importe" step="0.01" min="0.00" required> </div>
                            <div class="col-md-2 text-end"> <button type="button" class="btn btn-danger btn-sm remove-servicio" title="Eliminar Servicio"><i class="fas fa-trash"></i></button> </div>
                        </div>`;
                    container.append(newItemHtml);
                    updateTotal();
                });
                
                 if (container.children('.servicio-item').length === 0) {
                     $('#add-servicio').trigger('click');
                 }

                container.on('click', '.remove-servicio', function() { $(this).closest('.servicio-item').remove(); updateTotal(); });
                container.on('input', '.importe', function() { updateTotal(); });

                function updateTotal() {
                    let total = 0; container.find('.importe').each(function() { total += parseFloat($(this).val()) || 0; });
                    $('#total').text(total.toFixed(2)); $('#monto_total_hidden').val(total.toFixed(2));
                }

                $('#btnAgregarHonorarios').on('click', function() {
                     const clienteId = $('#cliente_id').val(); let clienteNombre = 'N/A'; let honorariosClienteAttr = 0;
                     const selectedOption = clienteSelectElement ? clienteSelectElement.querySelector(`option[value="${clienteId}"]`) : null;
                     if(selectedOption){ clienteNombre = selectedOption.textContent || 'N/A'; honorariosClienteAttr = parseFloat(selectedOption.getAttribute('data-honorarios') || '0'); }
                     if (!clienteId || clienteId === "") { alert('Por favor, selecciona un cliente primero.'); return; }
                     if (honorariosClienteAttr >= 0) { 
                         montoHonorarioCliente = honorariosClienteAttr; 
                         fetchEstadosYMostrarModal(clienteId, clienteNombre, honorariosClienteAttr); 
                     }
                });

                function fetchEstadosYMostrarModal(clienteId, clienteNombre, montoHonorario) {
                     $('#listaPeriodosHonorarios').html('<div class="text-center p-3 text-muted">Cargando...</div>');
                     $.ajax({
                        url: '<?php echo URL_ROOT; ?>/modulos/recibos/get_periodos_estado.php', type: 'POST', data: { cliente_id: clienteId }, dataType: 'json',
                        success: function(estadoResponse) {
                            if (estadoResponse && estadoResponse.success) {
                                const estadosPeriodos = estadoResponse.periodos || {}; $('#modalClienteNombre').text(clienteNombre); $('#modalMontoHonorario').text(montoHonorario.toFixed(2));
                                $('#modalTotalSeleccionado').text('0.00'); periodosSeleccionados = []; 
                                generarListaPeriodos(estadosPeriodos, montoHonorario);
                                var modalElement = document.getElementById('modalHonorarios');
                                if (modalElement) {
                                    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
                                    modalInstance.show();
                                }
                            } else { alert(estadoResponse?.error || 'Error al obtener estados.'); $('#listaPeriodosHonorarios').html('<div class="text-center p-3 text-danger">Error al cargar.</div>'); }
                        },
                        error: function(jqXHR, textStatus) { console.error("Error AJAX (estados):", textStatus); alert('Error de conexión al obtener estados.'); $('#listaPeriodosHonorarios').html('<div class="text-center p-3 text-danger">Error conexión.</div>'); }
                    });
                }

                // --- FUNCIÓN MODIFICADA ---
                function generarListaPeriodos(estadosPeriodos = {}, montoHonorario) {
                    const listaDiv = $('#listaPeriodosHonorarios'); listaDiv.empty();
                    const hoy = new Date(); const anioActual = hoy.getFullYear(); const mesActual = hoy.getMonth();
                    const meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                    let contentHtml = '';
                    for (let i = -12; i <= 12; i++) {
                        let fechaPeriodo = new Date(anioActual, mesActual + i, 1);
                        let anioPeriodo = fechaPeriodo.getFullYear(); let mesPeriodo = fechaPeriodo.getMonth();
                        let valorPeriodo = anioPeriodo + '-' + ('0' + (mesPeriodo + 1)).slice(-2);
                        let nombreMes = meses[mesPeriodo];
                        if(typeof nombreMes === 'undefined'){ nombreMes = 'Mes?'; }
                        let textoPeriodo = nombreMes + ' ' + anioPeriodo;
                        let isDisabled = false; let esCortesia = false; let estadoActual = estadosPeriodos[valorPeriodo]; let labelExtra = '';

                        if (estadoActual === 'pagado') {
                            isDisabled = true;
                            labelExtra = ' <span class="badge bg-success ms-1">Pagado</span>';
                        } else if (estadoActual === 'cortesia') {
                            esCortesia = true;
                            labelExtra = ' <span class="badge bg-info ms-1">Cortesía</span>';
                            // No deshabilitar, se puede seleccionar para recibo de $0
                        
                        // *** INICIO DE LA CORRECCIÓN ***
                        } else if (estadoActual === 'pendiente') {
                            isDisabled = true; // Deshabilitar si ya está en un recibo pendiente
                            labelExtra = ' <span class="badge bg-warning text-dark ms-1">Pendiente</span>';
                        }
                        // *** FIN DE LA CORRECCIÓN ***

                        if (montoHonorario <= 0 && !esCortesia && !isDisabled) {
                            isDisabled = true; labelExtra = ' <span class="badge bg-secondary ms-1">N/A</span>';
                        }
                        
                        contentHtml += `
                            <div class="col-sm-6 col-md-4 col-lg-3 mb-2"> <div class="form-check">
                                <input class="form-check-input periodo-checkbox" type="checkbox" value="${valorPeriodo}" id="periodo_${valorPeriodo}" ${isDisabled ? 'disabled' : ''} ${esCortesia ? 'data-es-cortesia="true"' : ''}>
                                <label class="form-check-label ${isDisabled ? 'text-muted' : ''}" for="periodo_${valorPeriodo}"> ${textoPeriodo} ${labelExtra} </label>
                            </div> </div>`;
                    }
                    listaDiv.html(contentHtml);
                }
                // --- FIN FUNCIÓN MODIFICADA ---

                // Evento Change Periodo Checkbox
                $(document).on('change', '.periodo-checkbox', function() {
                    const periodo = $(this).val(); const esCortesia = $(this).data('es-cortesia') === true;
                    if ($(this).is(':checked')) { if (!periodosSeleccionados.some(p => p.id === periodo)) periodosSeleccionados.push({ id: periodo, cortesia: esCortesia }); }
                    else { periodosSeleccionados = periodosSeleccionados.filter(p => p.id !== periodo); }
                    periodosSeleccionados.sort((a, b) => a.id.localeCompare(b.id));
                    let total = 0; periodosSeleccionados.forEach(p => { if (!p.cortesia) total += montoHonorarioCliente; });
                    $('#modalTotalSeleccionado').text(total.toFixed(2));
                });

                // Botón Confirmar Agregar Honorarios
                $('#btnConfirmarAgregarHonorarios').on('click', function() {
                    if (periodosSeleccionados.length === 0) { alert('Debes seleccionar al menos un periodo.'); return; }
                    let totalPagar = 0; let contieneCortesia = false; periodosSeleccionados.forEach(p => { if (!p.cortesia) totalPagar += montoHonorarioCliente; else contieneCortesia = true; });
                    let descripcionConcepto = 'Pago Honorarios (' + obtenerTextoPeriodosSeleccionados(periodosSeleccionados) + ')';
                    if(contieneCortesia && totalPagar == 0 && periodosSeleccionados.length > 0) descripcionConcepto = 'Honorario Cortesía (' + obtenerTextoPeriodosSeleccionados(periodosSeleccionados) + ')';
                    else if (contieneCortesia) descripcionConcepto += ' [Inc. Cortesía]';
                    const filaIdUnico = 'fila_' + Date.now() + Math.random().toString(36).substring(2, 7);
                    const nombreCampoPeriodos = `periodos_pagados[${filaIdUnico}]`;
                    let inputsPeriodosHtml = ''; periodosSeleccionados.forEach(p => { inputsPeriodosHtml += `<input type="hidden" name="${nombreCampoPeriodos}[]" value="${p.id}">`; });
                    const newItemHtml = `
                        <div class="row servicio-item mb-2 align-items-center" data-fila-id="${filaIdUnico}">
                            <div class="col-md-7"> <label class="form-label visually-hidden">Desc</label> <input type="text" name="descripcion[]" class="form-control form-control-sm" value="${descripcionConcepto}" readonly> <input type="hidden" name="es_honorario[]" value="1"> <input type="hidden" name="fila_id[]" value="${filaIdUnico}"> ${inputsPeriodosHtml} </div>
                            <div class="col-md-3"> <label class="form-label visually-hidden">Imp</label> <input type="number" name="importe[]" class="form-control form-control-sm importe text-end" value="${totalPagar.toFixed(2)}" step="0.01" min="0.00" readonly> </div>
                            <div class="col-md-2 text-end"> <button type="button" class="btn btn-danger btn-sm remove-servicio" title="Eliminar"><i class="fas fa-trash"></i></button> </div>
                        </div>`;
                    container.append(newItemHtml);
                    updateTotal(); $(this).trigger('blur');
                    var modalHonorarios = bootstrap.Modal.getInstance(document.getElementById('modalHonorarios'));
                    if(modalHonorarios) modalHonorarios.hide();
                    periodosSeleccionados = []; montoHonorarioCliente = 0;
                });

                // Función Obtener Texto Periodos Seleccionados
                function obtenerTextoPeriodosSeleccionados(periodosArray) {
                    if (!periodosArray || periodosArray.length === 0) return '';
                    periodosArray.sort((a, b) => a.id.localeCompare(b.id));
                    const meses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                    let texto = ''; let anioActual = '';
                    periodosArray.forEach((p, index) => {
                        const [anio, mesNum] = p.id.split('-');
                        const mesIndex = parseInt(mesNum) - 1;
                        const mesAbrev = (mesIndex >= 0 && mesIndex < 12) ? meses[mesIndex] : '?';
                        const sufijoCortesia = p.cortesia ? '(C)' : '';
                        if (anio !== anioActual) { if (index > 0) texto += '; '; anioActual = anio; texto += mesAbrev + sufijoCortesia + '/' + anio.slice(-2); }
                        else { texto += ', ' + mesAbrev + sufijoCortesia; }
                    });
                    return texto;
                }

                // Inicializar total
                updateTotal();

            } // Fin if ($('#formNuevoServicio').length)
            // --- FIN SCRIPT NUEVO_SERVICIO.PHP ---


            // --- OTROS SCRIPTS GLOBALES ---
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

        }); // Fin $(function(){})
    </script>

</body>
</html>