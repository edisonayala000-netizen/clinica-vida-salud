/* =====================================================================
   Clínica Vida Salud — comportamiento común (JavaScript sin librerías)
   - Menú lateral en celulares
   - Validación de formularios en el navegador (data-validar)
   - Confirmaciones antes de acciones importantes (data-confirmar)
   - Ventanas modales (<dialog>) y mostrar/ocultar contraseña
   La validación definitiva SIEMPRE se hace en el servidor (PHP).
   ===================================================================== */
(function () {
    'use strict';

    /* ---------- Menú lateral (celular) ---------- */
    const lateral = document.getElementById('barra-lateral');
    const fondo = document.querySelector('.fondo-menu');
    document.querySelectorAll('[data-alternar-menu]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!lateral) return;
            const abierto = lateral.classList.toggle('abierta');
            if (fondo) fondo.hidden = !abierto;
        });
    });

    /* ---------- Cerrar alertas ---------- */
    document.addEventListener('click', function (ev) {
        const b = ev.target.closest('[data-cerrar-alerta]');
        if (b) b.closest('.alerta').remove();
    });
    document.querySelectorAll('.alerta-exito').forEach(function (a) {
        setTimeout(function () { a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 400); }, 6000);
    });

    /* ---------- Mostrar / ocultar contraseña ---------- */
    document.querySelectorAll('[data-ver-clave]').forEach(function (b) {
        b.addEventListener('click', function () {
            const campo = document.getElementById(b.dataset.verClave);
            const ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });

    /* ---------- Validación en el navegador ---------- */
    const MENSAJES = {
        valueMissing: 'Este dato es obligatorio.',
        typeMismatch: 'El formato no es válido.',
        patternMismatch: 'El formato no es válido.',
        tooShort: 'Es demasiado corto.',
        tooLong: 'Es demasiado largo.',
        rangeUnderflow: 'El valor es menor al permitido.',
        rangeOverflow: 'El valor es mayor al permitido.'
    };

    function mensajeDe(campo) {
        if (campo.dataset.mensaje && !campo.validity.valueMissing) return campo.dataset.mensaje;
        for (const clave in MENSAJES) {
            if (campo.validity[clave]) return MENSAJES[clave];
        }
        return campo.validationMessage;
    }

    function marcar(campo) {
        const cont = campo.closest('.campo');
        if (!cont) return true;
        let p = cont.querySelector('.campo-error[data-js]');
        // Coincidencia de contraseñas
        if (campo.dataset.igualA) {
            const otro = document.getElementById(campo.dataset.igualA);
            campo.setCustomValidity(otro && otro.value !== campo.value ? 'No coincide.' : '');
        }
        if (campo.checkValidity()) {
            cont.classList.remove('con-error');
            if (p) p.remove();
            return true;
        }
        cont.classList.add('con-error');
        if (!p) {
            p = document.createElement('p');
            p.className = 'campo-error';
            p.dataset.js = '1';
            p.setAttribute('role', 'alert');
            cont.appendChild(p);
        }
        p.textContent = campo.validity.customError ? 'La confirmación no coincide.' : mensajeDe(campo);
        return false;
    }

    document.querySelectorAll('form[data-validar]').forEach(function (form) {
        form.querySelectorAll('input, select, textarea').forEach(function (c) {
            c.addEventListener('blur', function () { if (c.value !== '' || c.required) marcar(c); });
            c.addEventListener('input', function () { if (c.closest('.con-error')) marcar(c); });
        });
        form.addEventListener('submit', function (ev) {
            let primero = null;
            form.querySelectorAll('input, select, textarea').forEach(function (c) {
                if (c.type === 'hidden' || c.disabled) return;
                if (!marcar(c) && !primero) primero = c;
            });
            if (primero) {
                ev.preventDefault();
                primero.focus();
            }
        });
    });

    /* ---------- Confirmaciones ---------- */
    const dlg = document.createElement('dialog');
    dlg.className = 'modal';
    dlg.innerHTML =
        '<div class="modal-cabecera"><h2 data-titulo>Confirmar</h2></div>' +
        '<div class="modal-cuerpo"><p data-texto></p></div>' +
        '<div class="modal-pie"><button type="button" class="boton boton-secundario" data-no>Volver</button>' +
        '<button type="button" class="boton boton-primario" data-si>Confirmar</button></div>';
    document.body.appendChild(dlg);
    let accionPendiente = null;
    dlg.querySelector('[data-no]').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('[data-si]').addEventListener('click', function () {
        dlg.close();
        if (accionPendiente) accionPendiente();
    });

    document.addEventListener('submit', function (ev) {
        const form = ev.target;
        if (!form.dataset.confirmar || form.dataset.confirmado) return;
        ev.preventDefault();
        dlg.querySelector('[data-titulo]').textContent = form.dataset.confirmarTitulo || 'Confirmar';
        dlg.querySelector('[data-texto]').textContent = form.dataset.confirmar;
        const si = dlg.querySelector('[data-si]');
        si.textContent = form.dataset.confirmarBoton || 'Confirmar';
        si.className = 'boton ' + (form.dataset.peligro ? 'boton-peligro-lleno' : 'boton-primario');
        accionPendiente = function () { form.dataset.confirmado = '1'; form.submit(); };
        dlg.showModal();
    }, true);

    /* ---------- Modales declarativos ---------- */
    document.addEventListener('click', function (ev) {
        const abrir = ev.target.closest('[data-abrir-modal]');
        if (abrir) {
            const m = document.getElementById(abrir.dataset.abrirModal);
            if (m) {
                // Copia datos del botón a campos del modal: data-campo-xxx="valor"
                Object.keys(abrir.dataset).forEach(function (k) {
                    if (k.indexOf('campo') === 0 && k.length > 5) {
                        const nombre = k.charAt(5).toLowerCase() + k.slice(6);
                        const c = m.querySelector('[name="' + nombre + '"], [data-mostrar="' + nombre + '"]');
                        if (!c) return;
                        if ('value' in c && c.tagName !== 'SPAN' && c.tagName !== 'STRONG') c.value = abrir.dataset[k];
                        else c.textContent = abrir.dataset[k];
                    }
                });
                if (abrir.dataset.accion) {
                    const f = m.querySelector('form');
                    if (f) f.action = abrir.dataset.accion;
                }
                m.showModal();
                const foco = m.querySelector('[autofocus], input:not([type=hidden]), textarea, select');
                if (foco) foco.focus();
            }
        }
        const cerrar = ev.target.closest('[data-cerrar-modal]');
        if (cerrar) cerrar.closest('dialog').close();
    });

    /* ---------- Imprimir ---------- */
    document.querySelectorAll('[data-imprimir]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });

    /* ---------- Enviar formulario de filtros al cambiar un select ---------- */
    document.querySelectorAll('[data-autoenviar]').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });
})();
