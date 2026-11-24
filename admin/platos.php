<?php
/**
 * Gestión de platos (CRUD) del administrador.
 * - Tabla con miniatura, nombre y descripción, categoría, precio, interruptor "en carta" y acciones.
 * - Un único modal Bootstrap sirve para crear y para editar: admin.js lo rellena con los data-* de la fila.
 * POST (patrón PRG, con CSRF):
 *   accion=guardar   -> guardar_plato() (crea si no llega id, edita si llega)
 *   accion=alternar  -> alternar_disponible(): ocultar o mostrar en la carta
 *   accion=eliminar  -> eliminar_plato(); si tiene pedidos no se puede borrar y se oculta en su lugar
 * Si guardar falla, los datos escritos se guardan en la sesión y el modal se reabre con ellos.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platos.php';

$usuario = requerir_rol(['Administrator']);

// ---- POST: guardar, ocultar/mostrar o eliminar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $accion = $_POST['accion'] ?? '';
    if (!in_array($accion, ['guardar', 'alternar', 'eliminar'], true)) {
        flash('danger', 'Solicitud no válida.');
        redirigir('admin/platos.php');
    }

    // Id del plato: entero positivo o false. Al crear un plato el campo llega vacío.
    $id_crudo = $_POST['id'] ?? '';
    $id = filter_var($id_crudo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $es_nuevo = $accion === 'guardar' && $id_crudo === '';
    if ($id === false && !$es_nuevo) {
        flash('danger', 'Solicitud no válida.');
        redirigir('admin/platos.php');
    }

    if ($accion === 'guardar') {
        // Solo se copian los campos esperados, y solo si son texto (un arreglo se convierte en '')
        $datos = [];
        foreach (['name', 'description', 'price', 'category', 'image'] as $campo) {
            $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
        }
        $datos['available'] = isset($_POST['available']) ? '1' : '0'; // un checkbox sin marcar no se envía
        try {
            guardar_plato($datos, $es_nuevo ? null : $id);
            flash('success', $es_nuevo ? 'Plato «' . trim($datos['name']) . '» añadido a la carta.' : 'Cambios guardados.');
        } catch (InvalidArgumentException $ex) {
            flash('danger', $ex->getMessage());
            // Se guardan los datos para reabrir el modal con lo que el admin ya había escrito
            $_SESSION['plato_previo'] = $datos + ['id' => $es_nuevo ? '' : (string) $id];
        }
    } elseif ($accion === 'alternar') {
        $plato = obtener_plato($id);
        if ($plato === null) {
            flash('danger', 'Ese plato ya no existe.');
        } else {
            alternar_disponible($id);
            $visible = (int) $plato['available'] === 0; // estaba oculto -> ahora se ve
            flash('success', '«' . $plato['name'] . '» ' . ($visible ? 'vuelve a la carta.' : 'quedó oculto del menú.'));
        }
    } else {
        $plato = obtener_plato($id);
        $resultado = eliminar_plato($id);
        if ($resultado === 'ok') {
            flash('success', 'Plato eliminado.');
        } elseif ($resultado === 'tiene_pedidos') {
            // La FK no deja borrar un plato con pedidos: se oculta para que el cliente ya no lo vea
            if ((int) $plato['available'] === 1) {
                alternar_disponible($id);
            }
            flash('warning', 'Este plato tiene pedidos: se ocultó del menú en lugar de borrarse.');
        } else {
            flash('danger', 'Ese plato ya no existe.');
        }
    }
    redirigir('admin/platos.php');
}

// ---- GET ----
$platos = obtener_platos(false);
$ocultos = count(array_filter($platos, fn(array $p): bool => (int) $p['available'] === 0));

// Datos de un intento fallido (se leen una sola vez); si no hay, el formulario empieza vacío
$previo = $_SESSION['plato_previo'] ?? null;
unset($_SESSION['plato_previo']);
$form = $previo ?? ['id' => '', 'name' => '', 'description' => '', 'price' => '', 'category' => 'Principales', 'image' => '', 'available' => '1'];

$titulo_pagina = 'Platos';
$clase_body = 'rol-admin pagina-gestion';
$scripts_pagina = ['assets/js/admin.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container gestion">
    <header class="gestion-cabecera anim-item">
        <div>
            <h1 class="gestion-titulo">Platos</h1>
            <p class="gestion-resumen"><?= e((string) count($platos)) ?> en la carta · <?= e((string) $ocultos) ?> <?= $ocultos === 1 ? 'oculto' : 'ocultos' ?>. Un plato oculto no aparece en el menú del cliente ni del camarero.</p>
        </div>
        <button type="button" class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modal-plato" data-plato-nuevo>
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo plato
        </button>
    </header>

    <div class="gestion-tabla anim-item">
        <div class="table-responsive">
            <table class="table tabla-ebano tabla-gestion">
                <caption>Pulsa el interruptor para ocultar un plato del menú sin borrarlo.</caption>
                <thead>
                    <tr>
                        <th scope="col">Plato</th>
                        <th scope="col">Categoría</th>
                        <th scope="col" class="text-end">Precio</th>
                        <th scope="col">En carta</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($platos as $plato): ?>
                        <?php $visible = (int) $plato['available'] === 1; ?>
                        <tr class="<?= $visible ? '' : 'fila-oculta' ?>">
                            <td>
                                <div class="celda-plato">
                                    <?php if (trim((string) $plato['image']) !== ''): ?>
                                        <img class="miniatura" src="<?= e($plato['image']) ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="miniatura miniatura-vacia" aria-hidden="true"><?= marca_ebano() ?></span>
                                    <?php endif; ?>
                                    <div class="celda-plato-texto">
                                        <span class="celda-plato-nombre"><?= e($plato['name']) ?></span>
                                        <span class="celda-plato-desc"><?= e($plato['description'] ?? '') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($plato['categoria']) ?></td>
                            <td class="text-end precio celda-precio"><?= e(dinero((float) $plato['price'])) ?></td>
                            <td>
                                <form method="post" action="<?= e(url('admin/platos.php')) ?>" class="m-0">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="alternar">
                                    <input type="hidden" name="id" value="<?= e((string) $plato['id']) ?>">
                                    <button type="submit" class="interruptor" role="switch" aria-checked="<?= $visible ? 'true' : 'false' ?>"
                                            aria-label="<?= e($plato['name']) ?> en carta">
                                        <span class="interruptor-pista" aria-hidden="true"></span>
                                        <span class="interruptor-texto"><?= $visible ? 'Visible' : 'Oculto' ?></span>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="acciones-fila">
                                    <!-- data-*: admin.js copia estos valores al formulario del modal (con .value) -->
                                    <button type="button" class="btn btn-contorno btn-sm" data-bs-toggle="modal" data-bs-target="#modal-plato"
                                            data-plato-editar
                                            data-id="<?= e((string) $plato['id']) ?>"
                                            data-nombre="<?= e($plato['name']) ?>"
                                            data-descripcion="<?= e($plato['description'] ?? '') ?>"
                                            data-precio="<?= e((string) $plato['price']) ?>"
                                            data-categoria="<?= e($plato['categoria']) ?>"
                                            data-imagen="<?= e($plato['image'] ?? '') ?>"
                                            data-disponible="<?= $visible ? '1' : '0' ?>"
                                            aria-label="Editar <?= e($plato['name']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                                    </button>
                                    <form method="post" action="<?= e(url('admin/platos.php')) ?>" class="m-0"
                                          data-confirmar="¿Eliminar «<?= e($plato['name']) ?>»? Si tiene pedidos, solo se ocultará del menú.">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= e((string) $plato['id']) ?>">
                                        <button type="submit" class="btn btn-contorno-peligro btn-sm" aria-label="Eliminar <?= e($plato['name']) ?>">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$platos): ?>
            <p class="control-vacio">La carta está vacía. Empieza con «Nuevo plato».</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal crear / editar. data-reabrir: tras un error de validación se abre solo con lo escrito -->
<div class="modal fade modal-ebano" id="modal-plato" tabindex="-1" aria-labelledby="modal-plato-titulo" aria-hidden="true"
     <?= $previo !== null ? 'data-reabrir' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content needs-validation" method="post" action="<?= e(url('admin/platos.php')) ?>" novalidate>
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="plato-id" value="<?= e((string) $form['id']) ?>">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-plato-titulo"><?= $form['id'] !== '' ? 'Editar plato' : 'Nuevo plato' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="form-plato">
                    <div class="form-plato-campos">
                        <div class="mb-3">
                            <label class="form-label" for="plato-nombre">Nombre</label>
                            <input class="form-control" id="plato-nombre" name="name" required minlength="2" maxlength="50"
                                   value="<?= e($form['name']) ?>">
                            <div class="invalid-feedback">Entre 2 y 50 caracteres.</div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="plato-categoria">Categoría</label>
                                <select class="form-select" id="plato-categoria" name="category" required>
                                    <?php foreach (categorias_menu() as $categoria): ?>
                                        <option value="<?= e($categoria) ?>"<?= $form['category'] === $categoria ? ' selected' : '' ?>><?= e($categoria) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="plato-precio">Precio ($)</label>
                                <input class="form-control" id="plato-precio" name="price" type="number" inputmode="decimal"
                                       required min="0.01" max="9999.99" step="0.01" value="<?= e($form['price']) ?>">
                                <div class="invalid-feedback">Entre 0.01 y 9999.99.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="plato-descripcion">Descripción <span class="texto-2">(opcional)</span></label>
                            <textarea class="form-control" id="plato-descripcion" name="description" rows="3" maxlength="255"><?= e($form['description']) ?></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="plato-disponible" name="available" value="1"
                                <?= $form['available'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="plato-disponible">Visible en la carta</label>
                        </div>
                    </div>
                    <div class="form-plato-imagen">
                        <label class="form-label" for="plato-imagen">Foto (URL) <span class="texto-2">(opcional)</span></label>
                        <input class="form-control" id="plato-imagen" name="image" type="url" maxlength="255" pattern="https?://.+"
                               placeholder="https://…" value="<?= e($form['image']) ?>" aria-describedby="plato-imagen-ayuda">
                        <div class="invalid-feedback">Debe empezar por http:// o https://</div>
                        <div class="form-text" id="plato-imagen-ayuda">Pega la dirección de una foto; la vista previa se actualiza sola.</div>
                        <!-- Vista previa: admin.js muestra la foto o, si no hay o no carga, el marcador -->
                        <div class="vista-previa">
                            <img id="plato-vista" alt="Vista previa de la foto" hidden>
                            <span class="vista-previa-vacia" id="plato-vista-vacia"><?= marca_ebano() ?> Sin foto</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-contorno" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-oro"><i class="bi bi-check2" aria-hidden="true"></i> Guardar plato</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/modal_confirmar.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
