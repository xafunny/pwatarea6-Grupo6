<?php
/**
 * Modal de confirmación compartido por las páginas del administrador (lo incluyen antes del footer).
 * Cualquier formulario con data-confirmar="texto" pasa por aquí antes de enviarse: admin.js pone el
 * texto en el modal (con textContent) y, si se pulsa "Sí, eliminar", envía ese formulario.
 * Sin JavaScript el formulario se envía directo; el servidor valida igual.
 */
?>
<div class="modal fade modal-ebano" id="modal-confirmar" tabindex="-1" aria-labelledby="modal-confirmar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-confirmar-titulo">¿Seguro?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="modal-confirmar-texto"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-contorno btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-peligro btn-sm" id="modal-confirmar-si">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>
