/* =====================================================================
   Clínica Vida Salud — programar y reprogramar citas (CU09, CU10)
   Consulta al servidor (fetch) los médicos de una especialidad, los
   días de atención y los horarios disponibles. Solo se pueden elegir
   horarios libres; aun así el servidor vuelve a validar al guardar.
   ===================================================================== */
(function () {
    'use strict';
    const form = document.getElementById('form-cita');
    if (!form) return;

    const API = form.dataset.api;
    const HOY = form.dataset.hoy;
    const excluir = form.dataset.excluir || '';            // id de la cita que se reprograma
    const medicoFijo = form.dataset.medicoFijo || '';      // en reprogramación el médico no cambia
    const $ = function (id) { return document.getElementById(id); };
    const DIAS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    let desde = HOY;

    function texto(el, valor) { if (el) el.textContent = valor || '—'; }
    function obtener(url) {
        return fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); });
    }
    function sumarDias(iso, n) {
        const d = new Date(iso + 'T12:00:00');
        d.setDate(d.getDate() + n);
        return d.toISOString().slice(0, 10);
    }
    function marcarPaso(id, listo) { const p = $(id); if (p) p.classList.toggle('listo', !!listo); }

    function actualizarBoton() {
        const ok = $('id_paciente').value && $('fecha').value && $('hora_inicio').value &&
                   (medicoFijo || ($('id_medico') && $('id_medico').value));
        $('confirmar-cita').disabled = !ok;
        marcarPaso('paso-detalle', ok);
    }

    /* ---------- Paso 1: buscar paciente (solo recepción) ---------- */
    const buscar = $('buscar-paciente');
    if (buscar) {
        let temporizador = null;
        const resultados = $('resultados-paciente');
        buscar.addEventListener('input', function () {
            clearTimeout(temporizador);
            const q = buscar.value.trim();
            if (q.length < 2) { resultados.hidden = true; return; }
            temporizador = setTimeout(function () {
                obtener(API + '/pacientes?q=' + encodeURIComponent(q)).then(function (r) {
                    resultados.innerHTML = '';
                    if (!r.pacientes || !r.pacientes.length) {
                        resultados.innerHTML = '<p class="cargando" style="padding:10px">Sin resultados. Revise el CI o regístrelo.</p>';
                    }
                    (r.pacientes || []).forEach(function (p) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.setAttribute('role', 'option');
                        b.innerHTML = '<span class="avatar"></span><span><strong></strong><span class="celda-sub"></span></span>';
                        b.querySelector('.avatar').textContent = p.nombre.split(' ').slice(0, 2).map(function (x) { return x[0]; }).join('');
                        b.querySelector('strong').textContent = p.nombre;
                        b.querySelector('.celda-sub').textContent = p.codigo + ' · ' + p.detalle;
                        b.addEventListener('click', function () { elegirPaciente(p); });
                        resultados.appendChild(b);
                    });
                    resultados.hidden = false;
                });
            }, 250);
        });
        $('cambiar-paciente') && $('cambiar-paciente').addEventListener('click', function () {
            $('id_paciente').value = '';
            $('paciente-elegido').hidden = true;
            $('buscador-paciente').hidden = false;
            texto($('r-paciente'), '');
            marcarPaso('paso-paciente', false);
            buscar.value = '';
            buscar.focus();
            actualizarBoton();
        });
    }
    function elegirPaciente(p) {
        $('id_paciente').value = p.id;
        $('pac-nombre').textContent = p.nombre;
        $('pac-detalle').textContent = p.codigo + ' · ' + p.detalle;
        $('pac-iniciales').textContent = p.nombre.split(' ').slice(0, 2).map(function (x) { return x[0]; }).join('');
        $('paciente-elegido').hidden = false;
        $('buscador-paciente').hidden = true;
        $('resultados-paciente').hidden = true;
        texto($('r-paciente'), p.nombre);
        marcarPaso('paso-paciente', true);
        actualizarBoton();
    }

    /* ---------- Paso 2: especialidad -> médicos ---------- */
    const selEsp = $('id_especialidad');
    const selMed = $('id_medico');
    if (selEsp) {
        selEsp.addEventListener('change', function () {
            texto($('r-especialidad'), selEsp.value ? selEsp.selectedOptions[0].textContent : '');
            selMed.innerHTML = '<option value="">Cargando médicos…</option>';
            selMed.disabled = true;
            reiniciarFecha();
            if (!selEsp.value) { selMed.innerHTML = '<option value="">Primero elija la especialidad</option>'; return; }
            obtener(API + '/medicos?especialidad=' + selEsp.value).then(function (r) {
                selMed.innerHTML = '<option value="">Seleccione el médico…</option>';
                r.medicos.forEach(function (m) {
                    const o = document.createElement('option');
                    o.value = m.id;
                    o.textContent = m.nombre;
                    selMed.appendChild(o);
                });
                selMed.disabled = false;
                if (selMed.dataset.anterior) {
                    selMed.value = selMed.dataset.anterior;
                    selMed.dataset.anterior = '';
                    if (selMed.value) selMed.dispatchEvent(new Event('change'));
                }
            });
        });
        selMed.addEventListener('change', function () {
            texto($('r-medico'), selMed.value ? selMed.selectedOptions[0].textContent : '');
            marcarPaso('paso-medico', !!selMed.value);
            desde = HOY;
            reiniciarFecha();
            if (selMed.value) cargarDias();
        });
        if (selEsp.value) selEsp.dispatchEvent(new Event('change'));
    }

    function medicoActual() { return medicoFijo || (selMed ? selMed.value : ''); }

    function reiniciarFecha() {
        $('fecha').value = '';
        $('hora_inicio').value = '';
        texto($('r-fecha'), ''); texto($('r-hora'), '');
        marcarPaso('paso-fecha', false); marcarPaso('paso-hora', false);
        $('zona-dias').innerHTML = '<p class="texto-tenue">Elija un médico para ver sus días de atención.</p>';
        $('zona-horarios').innerHTML = '<p class="texto-tenue">Elija una fecha.</p>';
        actualizarBoton();
    }

    /* ---------- Paso 3: días de atención (14 días) ---------- */
    function cargarDias() {
        const zona = $('zona-dias');
        zona.innerHTML = '<p class="cargando">Consultando días de atención…</p>';
        obtener(API + '/disponibilidad/dias?medico=' + medicoActual() + '&desde=' + desde + (excluir ? '&excluir=' + excluir : ''))
            .then(function (r) {
                const cont = document.createElement('div');
                cont.className = 'dias-semana';
                cont.setAttribute('role', 'listbox');
                cont.setAttribute('aria-label', 'Días');
                if (desde > HOY) cont.appendChild(botonNavegar(-14, 'Días anteriores', '‹'));
                r.dias.forEach(function (d) {
                    const f = new Date(d.fecha + 'T12:00:00');
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'dia-opcion';
                    b.dataset.fecha = d.fecha;
                    const libre = d.atiende && d.libres > 0;
                    b.disabled = !libre;
                    b.title = libre ? d.libres + ' horario(s) libre(s)' : (d.motivo || 'No atiende');
                    b.innerHTML = '<small>' + DIAS[f.getDay()] + '</small><strong>' + f.getDate() + '</strong><em>' +
                        (libre ? d.libres + ' libres' : (d.atiende ? 'Lleno' : '—')) + '</em>';
                    b.addEventListener('click', function () { elegirDia(d.fecha, b); });
                    cont.appendChild(b);
                });
                cont.appendChild(botonNavegar(14, 'Días siguientes', '›'));
                zona.innerHTML = '';
                zona.appendChild(cont);
                const p = document.createElement('p');
                p.className = 'ayuda';
                p.textContent = 'Del ' + fechaCorta(r.dias[0].fecha) + ' al ' + fechaCorta(r.dias[r.dias.length - 1].fecha) + '. Los días en gris no tienen horarios libres.';
                zona.appendChild(p);
            });
    }
    function botonNavegar(n, etiqueta, simbolo) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'dia-opcion';
        b.setAttribute('aria-label', etiqueta);
        b.innerHTML = '<small>&nbsp;</small><strong>' + simbolo + '</strong><em>&nbsp;</em>';
        b.addEventListener('click', function () { desde = sumarDias(desde, n); if (desde < HOY) desde = HOY; cargarDias(); });
        return b;
    }
    function fechaCorta(iso) { const d = new Date(iso + 'T12:00:00'); return d.getDate() + ' ' + MESES[d.getMonth()]; }

    /* ---------- Paso 4: horarios del día ---------- */
    function elegirDia(fecha, boton) {
        document.querySelectorAll('.dia-opcion.seleccionado').forEach(function (x) { x.classList.remove('seleccionado'); });
        boton.classList.add('seleccionado');
        $('fecha').value = fecha;
        $('hora_inicio').value = '';
        texto($('r-hora'), '');
        marcarPaso('paso-fecha', true);
        marcarPaso('paso-hora', false);
        actualizarBoton();
        const zona = $('zona-horarios');
        zona.innerHTML = '<p class="cargando">Consultando horarios disponibles…</p>';
        obtener(API + '/disponibilidad?medico=' + medicoActual() + '&fecha=' + fecha + (excluir ? '&excluir=' + excluir : ''))
            .then(function (r) {
                texto($('r-fecha'), r.fecha_texto.charAt(0).toUpperCase() + r.fecha_texto.slice(1));
                if (!r.intervalos || !r.intervalos.length) {
                    zona.innerHTML = '<p class="texto-suave">' + (r.bloqueo || 'No hay horarios ese día.') + '</p>';
                    return;
                }
                const grid = document.createElement('div');
                grid.className = 'franja-horarios';
                r.intervalos.forEach(function (i) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'horario-slot' + (i.libre ? '' : ' ocupado');
                    b.disabled = !i.libre;
                    b.innerHTML = i.inicio + '<small>' + (i.libre ? 'hasta ' + i.fin : 'Ocupado') + '</small>';
                    b.setAttribute('aria-label', i.inicio + ' a ' + i.fin + (i.libre ? ', libre' : ', ocupado'));
                    if (i.libre) {
                        b.addEventListener('click', function () {
                            grid.querySelectorAll('.seleccionado').forEach(function (x) { x.classList.remove('seleccionado'); });
                            b.classList.add('seleccionado');
                            $('hora_inicio').value = i.inicio;
                            texto($('r-hora'), i.inicio + ' – ' + i.fin);
                            marcarPaso('paso-hora', true);
                            actualizarBoton();
                        });
                    }
                    grid.appendChild(b);
                });
                zona.innerHTML = '';
                zona.appendChild(grid);
            });
    }

    /* Reprogramación: el médico ya está definido, se cargan sus días al abrir. */
    if (medicoFijo) cargarDias();

    form.addEventListener('submit', function (ev) {
        if ($('confirmar-cita').disabled) { ev.preventDefault(); return; }
        $('confirmar-cita').disabled = true;
        $('confirmar-cita').textContent = 'Guardando…';
    });
})();
