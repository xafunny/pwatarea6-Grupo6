/**
 * admin.js · Interacción de las páginas de gestión del administrador (platos, usuarios y pedidos).
 * Se carga desde includes/footer.php después de Bootstrap y app.js.
 * Funciones:
 *   - prepararModalPlato(): el mismo modal sirve para "Nuevo plato" (vacío) y "Editar" (datos de la fila).
 *   - prepararModalUsuario(): igual para usuarios; al editar, la contraseña pasa a ser opcional.
 *   - vistaPreviaImagen(): muestra la foto del plato mientras se escribe su URL.
 *   - confirmarEnvios(): los formularios con data-confirmar piden confirmación en un modal.
 *   - reabrirModal(): tras un error de validación del servidor, vuelve a abrir el modal con lo escrito.
 * Los datos de la fila se copian con .value y .textContent (nunca innerHTML): un nombre como
 * "<b>x</b>" se ve tal cual y no se interpreta como HTML. La validación real la hace el servidor.
 */
'use strict';

/** Marca el título del modal y limpia la validación visual de Bootstrap de un intento anterior. */
function reiniciarFormulario(formulario, titulo) {
    formulario.classList.remove('was-validated');
    formulario.querySelector('.modal-title').textContent = titulo;
}

/**
 * Rellena el modal de platos al abrirse. event.relatedTarget es el botón que lo abrió:
 * con data-plato-editar copia sus data-*; con data-plato-nuevo deja el formulario vacío.
 * Si el modal se abrió desde reabrirModal() (sin botón) se conservan los valores del servidor.
 */
function prepararModalPlato() {
    const modal = document.getElementById('modal-plato');
    if (!modal) {
        return;
    }
    const formulario = modal.querySelector('form');

    modal.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        if (!boton) {
            return;
        }
        const editar = boton.hasAttribute('data-plato-editar');
        const datos = editar ? boton.dataset : {};

        reiniciarFormulario(formulario, editar ? 'Editar plato' : 'Nuevo plato');
        formulario.querySelector('#plato-id').value = datos.id || '';
        formulario.querySelector('#plato-nombre').value = datos.nombre || '';
        formulario.querySelector('#plato-descripcion').value = datos.descripcion || '';
        formulario.querySelector('#plato-precio').value = datos.precio || '';
        formulario.querySelector('#plato-categoria').value = datos.categoria || 'Principales';
        formulario.querySelector('#plato-imagen').value = datos.imagen || '';
        formulario.querySelector('#plato-disponible').checked = editar ? datos.disponible === '1' : true;
        actualizarVistaPrevia();
    });

    // El foco va al primer campo cuando el modal ya terminó de abrirse
    modal.addEventListener('shown.bs.modal', function () {
        formulario.querySelector('#plato-nombre').focus();
    });
}

/**
 * Si el admin se edita a sí mismo, el select de rol se deshabilita y el aviso se muestra.
 * Un select deshabilitado no se envía, así que el campo oculto lleva el rol actual en su lugar.
 * (Es solo comodidad: el servidor rechaza el cambio de tu propio rol de todos modos.)
 */
function bloquearRolPropio(formulario, esPropio) {
    const select = formulario.querySelector('#usuario-rol');
    const oculto = formulario.querySelector('#usuario-rol-oculto');
    select.disabled = esPropio;
    oculto.disabled = !esPropio;
    oculto.value = select.value;
    formulario.querySelector('#usuario-rol-aviso').hidden = !esPropio;
}

/** Igual que prepararModalPlato(), para usuarios. La contraseña es obligatoria solo al crear. */
function prepararModalUsuario() {
    const modal = document.getElementById('modal-usuario');
    if (!modal) {
        return;
    }
    const formulario = modal.querySelector('form');
    const clave = formulario.querySelector('#usuario-clave');
    const ayudaClave = formulario.querySelector('#usuario-clave-ayuda');

    modal.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        if (!boton) {
            return;
        }
        const editar = boton.hasAttribute('data-usuario-editar');
        const datos = editar ? boton.dataset : {};

        reiniciarFormulario(formulario, editar ? 'Editar usuario' : 'Nuevo usuario');
        formulario.querySelector('#usuario-id').value = datos.id || '';
        formulario.querySelector('#usuario-nombre').value = datos.nombre || '';
        formulario.querySelector('#usuario-email').value = datos.email || '';
        if (datos.rol) {
            formulario.querySelector('#usuario-rol').value = datos.rol;
        }
        // Solo en la fila del propio administrador: aviso visible y select bloqueado (data-yo = id del admin actual)
        bloquearRolPropio(formulario, editar && datos.id === modal.dataset.yo);
        clave.value = '';
        clave.required = !editar;
        ayudaClave.textContent = editar ? 'Dejar en blanco para mantener la actual.' : 'Mínimo 6 caracteres.';
    });

    modal.addEventListener('shown.bs.modal', function () {
        formulario.querySelector('#usuario-nombre').focus();
    });
}

/**
 * Vista previa de la foto del plato: solo se asigna img.src si la URL empieza por http:// o https://
 * (así nunca se carga un "javascript:" u otro esquema). Si no hay URL o la foto no carga,
 * se muestra el marcador "Sin foto".
 */
function actualizarVistaPrevia() {
    const campo = document.getElementById('plato-imagen');
    const imagen = document.getElementById('plato-vista');
    const vacia = document.getElementById('plato-vista-vacia');
    if (!campo || !imagen || !vacia) {
        return;
    }
    const direccion = campo.value.trim();
    if (/^https?:\/\/\S+$/i.test(direccion)) {
        imagen.src = direccion;
        imagen.hidden = false;
        vacia.hidden = true;
    } else {
        imagen.removeAttribute('src');
        imagen.hidden = true;
        vacia.hidden = false;
    }
}

/** Conecta la vista previa al campo de la URL y al error de carga de la imagen. */
function vistaPreviaImagen() {
    const campo = document.getElementById('plato-imagen');
    const imagen = document.getElementById('plato-vista');
    if (!campo || !imagen) {
        return;
    }
    campo.addEventListener('input', actualizarVistaPrevia);
    imagen.addEventListener('error', function () {
        imagen.hidden = true;
        document.getElementById('plato-vista-vacia').hidden = false;
    });
    actualizarVistaPrevia();
}

/**
 * Los formularios con data-confirmar no se envían a la primera: se abre #modal-confirmar con el texto
 * del atributo y, si el usuario pulsa "Sí, eliminar", se envía ese mismo formulario.
 */
function confirmarEnvios() {
    const modal = document.getElementById('modal-confirmar');
    if (!modal || typeof bootstrap === 'undefined') {
        return; // sin Bootstrap el formulario se envía directo
    }
    const dialogo = bootstrap.Modal.getOrCreateInstance(modal);
    const texto = document.getElementById('modal-confirmar-texto');
    const botonSi = document.getElementById('modal-confirmar-si');
    let pendiente = null; // formulario que espera la respuesta

    document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            pendiente = formulario;
            texto.textContent = formulario.dataset.confirmar;
            dialogo.show();
        });
    });

    botonSi.addEventListener('click', function () {
        if (pendiente) {
            botonSi.disabled = true; // evita el doble envío
            // form.submit() no vuelve a disparar el evento "submit", así no se pide confirmación otra vez
            pendiente.submit();
        }
    });

    modal.addEventListener('shown.bs.modal', function () {
        botonSi.focus();
    });

    // Al volver con "atrás" el navegador puede restaurar la página desde su caché (bfcache) con el botón
    // aún deshabilitado: pageshow con persisted=true lo reactiva y cierra el modal.
    window.addEventListener('pageshow', function (evento) {
        if (evento.persisted) {
            botonSi.disabled = false;
            pendiente = null;
            dialogo.hide();
        }
    });
}

/** Si el servidor marcó un modal con data-reabrir (hubo un error al guardar), se abre al cargar. */
function reabrirModal() {
    const modal = document.querySelector('.modal[data-reabrir]');
    if (modal && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    prepararModalPlato();
    prepararModalUsuario();
    vistaPreviaImagen();
    confirmarEnvios();
    reabrirModal();
});
