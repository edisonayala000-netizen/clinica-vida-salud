/*
 * CU16 · Registrar atención: agregar y quitar medicamentos de la receta,
 * calcular el IMC y mostrar cuántas tomas tendrá cada medicamento.
 * Es solo una ayuda: el servidor vuelve a validar todo.
 */
(function () {
    'use strict';

    const lista = document.querySelector('[data-lista-receta]');
    const plantilla = document.getElementById('plantilla-fila-receta');
    if (!lista || !plantilla) return;

    let siguiente = lista.querySelectorAll('[data-fila-receta]').length;

    function resumenTomas(fila) {
        const cada = parseInt(fila.querySelector('[data-frecuencia]').value, 10);
        const dias = parseInt(fila.querySelector('[data-dias]').value, 10);
        const salida = fila.querySelector('[data-resumen-tomas]');
        if (cada > 0 && dias > 0) {
            const tomas = Math.floor(dias * 24 / cada);
            salida.textContent = tomas + ' toma(s) en total' + (tomas > 90 ? ' · se recuerdan las primeras 90' : '');
        } else {
            salida.textContent = '';
        }
    }

    function preparar(fila) {
        fila.querySelector('[data-quitar-fila]').addEventListener('click', function () {
            fila.remove();
        });
        fila.querySelectorAll('[data-frecuencia], [data-dias]').forEach(function (campo) {
            campo.addEventListener('input', function () { resumenTomas(fila); });
        });
        resumenTomas(fila);
    }

    lista.querySelectorAll('[data-fila-receta]').forEach(preparar);

    document.querySelector('[data-agregar-fila]').addEventListener('click', function () {
        const html = plantilla.innerHTML.replace(/__i__/g, String(siguiente++));
        const contenedor = document.createElement('div');
        contenedor.innerHTML = html.trim();
        const fila = contenedor.firstElementChild;
        lista.appendChild(fila);
        preparar(fila);
        fila.querySelector('[data-medicamento]').focus();
    });

    /* IMC = peso (kg) / talla (m)² — no se guarda: se calcula (vista v_signo_vital). */
    const peso = document.getElementById('peso_kg');
    const talla = document.getElementById('talla_cm');
    const imc = document.querySelector('[data-imc]');
    function calcularImc() {
        const p = parseFloat(peso.value);
        const t = parseFloat(talla.value) / 100;
        if (p > 0 && t > 0) {
            const valor = p / (t * t);
            const texto = valor < 18.5 ? 'bajo peso' : valor < 25 ? 'normal' : valor < 30 ? 'sobrepeso' : 'obesidad';
            imc.textContent = valor.toFixed(1) + ' · ' + texto;
        } else {
            imc.textContent = '—';
        }
    }
    if (peso && talla && imc) {
        peso.addEventListener('input', calcularImc);
        talla.addEventListener('input', calcularImc);
        calcularImc();
    }
})();
