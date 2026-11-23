<?php
/**
 * Monitor de pedidos del administrador.
 * - Filtros por GET: estado (chips "Todos" + los 4 estados) y q (búsqueda por cliente, correo o plato).
 * - Tabla con todos los pedidos (máximo 200, del más nuevo al más viejo).
 * - Cada fila tiene un <select> para llevar el pedido a CUALQUIER estado y un botón para eliminarlo.
 * POST (patrón PRG, con CSRF):
 *   accion=estado   -> cambiar_estado() (el rol Administrator puede hacer cualquier transición)
 *   accion=eliminar -> eliminar_pedido()
 * Tras un POST se vuelve a la misma lista: los filtros viajan en campos ocultos, se validan de nuevo
 * y la URL de regreso se arma con http_build_query (que también escapa los valores).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

$usuario = requerir_rol(['Administrator']);

/**
 * Valida los filtros que llegan del navegador. Devuelve ['estado' => ?string, 'q' => string]:
 * un estado que no está en ESTADOS se descarta y la búsqueda se recorta a 50 caracteres.
 */
function filtros_pedidos(array $fuente): array
{
    $estado = $fuente['estado'] ?? null;
    $q = $fuente['q'] ?? '';
    return [
        'estado' => is_string($estado) && in_array($estado, ESTADOS, true) ? $estado : null,
        'q'      => is_string($q) ? mb_substr(trim($q), 0, 50) : '',
    ];
}

/** URL de esta página con los filtros dados (sin los vacíos). */
function url_pedidos(array $filtros): string
{
    $consulta = http_build_query(array_filter($filtros, fn($v): bool => $v !== null && $v !== ''));
    return 'admin/pedidos.php' . ($consulta !== '' ? '?' . $consulta : '');
}

// ---- POST: cambiar estado o eliminar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $volver = url_pedidos(filtros_pedidos(['estado' => $_POST['filtro_estado'] ?? null, 'q' => $_POST['filtro_q'] ?? '']));
    $accion = $_POST['accion'] ?? '';
    $pedido_id = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($pedido_id === false || !in_array($accion, ['estado', 'eliminar'], true)) {
        flash('danger', 'Solicitud no válida.');
        redirigir($volver);
    }

    $numero = '#' . sprintf('%04d', $pedido_id);
    if ($accion === 'estado') {
        $nuevo = $_POST['nuevo'] ?? '';
        if (!is_string($nuevo) || !in_array($nuevo, ESTADOS, true)) {
            flash('danger', 'Ese estado no existe.');
        } elseif (cambiar_estado($pedido_id, $nuevo, $usuario)) {
            flash('success', 'Pedido ' . $numero . ' pasó a «' . estado_es($nuevo) . '».');
        } else {
            flash('warning', 'El pedido ' . $numero . ' no cambió: ya estaba en ese estado o alguien lo modificó antes.');
        }
    } elseif (eliminar_pedido($pedido_id)) {
        flash('success', 'Pedido ' . $numero . ' eliminado.');
    } else {
        flash('danger', 'El pedido ' . $numero . ' ya no existe.');
    }
    redirigir($volver);
}

// ---- GET ----
$filtros = filtros_pedidos($_GET);
$pedidos = todos_los_pedidos($filtros['estado'], $filtros['q']);

$titulo_pagina = 'Pedidos';
$clase_body = 'rol-admin pagina-gestion';
$scripts_pagina = ['assets/js/admin.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container gestion">
    <header class="gestion-cabecera anim-item">
        <div>
            <h1 class="gestion-titulo">Pedidos</h1>
            <p class="gestion-resumen">Todos los pedidos del restaurante. Como administrador puedes llevar cualquiera a cualquier estado.</p>
        </div>
    </header>

    <!-- Filtros: enlaces GET (los chips) y un formulario GET (la búsqueda); se pueden combinar -->
    <div class="filtros-pedidos anim-item">
        <nav class="filtros-chips" aria-label="Filtrar por estado">
            <!-- Son enlaces (cambian de URL), así que el activo se marca con aria-current y no con aria-pressed -->
            <a class="chip<?= $filtros['estado'] === null ? ' activo' : '' ?>" href="<?= e(url(url_pedidos(['estado' => null, 'q' => $filtros['q']]))) ?>"
               <?= $filtros['estado'] === null ? 'aria-current="true"' : '' ?>>Todos</a>
            <?php foreach (ESTADOS as $estado): ?>
                <a class="chip<?= $filtros['estado'] === $estado ? ' activo' : '' ?>" href="<?= e(url(url_pedidos(['estado' => $estado, 'q' => $filtros['q']]))) ?>"
                   <?= $filtros['estado'] === $estado ? 'aria-current="true"' : '' ?>>
                    <span class="punto-estado <?= e(clase_estado($estado)) ?>" aria-hidden="true"></span><?= e(estado_es($estado)) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="buscador" method="get" action="<?= e(url('admin/pedidos.php')) ?>" role="search">
            <?php if ($filtros['estado'] !== null): ?>
                <input type="hidden" name="estado" value="<?= e($filtros['estado']) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="buscar-pedido">Buscar por cliente, correo o plato</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input class="form-control" type="search" id="buscar-pedido" name="q" maxlength="50"
                   placeholder="Cliente, correo o plato" value="<?= e($filtros['q']) ?>">
            <button type="submit" class="btn btn-contorno btn-sm">Buscar</button>
        </form>
    </div>

    <p class="gestion-cuenta" aria-live="polite">
        <?= e((string) count($pedidos)) ?> <?= count($pedidos) === 1 ? 'pedido' : 'pedidos' ?>
        <?php if ($filtros['estado'] !== null): ?> en «<?= e(estado_es($filtros['estado'])) ?>»<?php endif; ?>
        <?php if ($filtros['q'] !== ''): ?> que <?= count($pedidos) === 1 ? 'coincide' : 'coinciden' ?> con «<?= e($filtros['q']) ?>»<?php endif; ?>
        <?php if (count($pedidos) === LIMITE_PEDIDOS_ADMIN): ?> · se muestran los <?= LIMITE_PEDIDOS_ADMIN ?> más recientes<?php endif; ?>
        <?php if ($filtros['estado'] !== null || $filtros['q'] !== ''): ?>
            · <a href="<?= e(url('admin/pedidos.php')) ?>">Quitar filtros</a>
        <?php endif; ?>
    </p>

    <div class="gestion-tabla anim-item">
        <?php if ($pedidos): ?>
            <div class="table-responsive">
                <table class="table tabla-ebano tabla-gestion tabla-pedidos">
                    <thead>
                        <tr>
                            <th scope="col">Pedido</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Plato</th>
                            <th scope="col" class="text-end">Cant.</th>
                            <th scope="col" class="text-end">Subtotal</th>
                            <th scope="col">Camarero</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Cambiar a</th>
                            <th scope="col"><span class="visually-hidden">Eliminar</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php $numero = '#' . sprintf('%04d', (int) $pedido['id']); ?>
                            <tr>
                                <!-- Número y fecha/hora en una sola celda, apilados -->
                                <td class="celda-pedido">
                                    <span class="celda-numero"><?= e($numero) ?></span>
                                    <time datetime="<?= e(str_replace(' ', 'T', $pedido['created_at'])) ?>"><?= e(date('d/m/Y H:i', strtotime($pedido['created_at']))) ?></time>
                                </td>
                                <td><?= e($pedido['cliente']) ?></td>
                                <td class="celda-plato-pedido"><?= e($pedido['plato']) ?></td>
                                <td class="text-end"><?= e((string) $pedido['quantity']) ?></td>
                                <td class="text-end precio"><?= e(dinero((float) $pedido['subtotal'])) ?></td>
                                <td><?= $pedido['camarero'] !== null ? e($pedido['camarero']) : '<span class="texto-2">—</span>' ?></td>
                                <td><span class="badge-estado <?= e(clase_estado($pedido['status'])) ?>"><?= e(estado_es($pedido['status'])) ?></span></td>
                                <td>
                                    <form method="post" action="<?= e(url('admin/pedidos.php')) ?>" class="cambiar-estado">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="pedido_id" value="<?= e((string) $pedido['id']) ?>">
                                        <input type="hidden" name="filtro_estado" value="<?= e($filtros['estado'] ?? '') ?>">
                                        <input type="hidden" name="filtro_q" value="<?= e($filtros['q']) ?>">
                                        <label class="visually-hidden" for="nuevo-<?= e((string) $pedido['id']) ?>">Nuevo estado del pedido <?= e($numero) ?></label>
                                        <select class="form-select form-select-sm" name="nuevo" id="nuevo-<?= e((string) $pedido['id']) ?>">
                                            <?php foreach (ESTADOS as $estado): ?>
                                                <option value="<?= e($estado) ?>"<?= $estado === $pedido['status'] ? ' selected' : '' ?>><?= e(estado_es($estado)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-contorno btn-sm">Cambiar</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" action="<?= e(url('admin/pedidos.php')) ?>" class="m-0"
                                          data-confirmar="¿Eliminar el pedido <?= e($numero) ?> (<?= e($pedido['plato']) ?> para <?= e($pedido['cliente']) ?>)? No se puede deshacer.">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="pedido_id" value="<?= e((string) $pedido['id']) ?>">
                                        <input type="hidden" name="filtro_estado" value="<?= e($filtros['estado'] ?? '') ?>">
                                        <input type="hidden" name="filtro_q" value="<?= e($filtros['q']) ?>">
                                        <button type="submit" class="btn btn-contorno-peligro btn-sm" aria-label="Eliminar pedido <?= e($numero) ?>">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="sala-vacio">
                <i class="bi bi-search" aria-hidden="true"></i>
                <div>
                    <p class="sala-vacio-titulo">Ningún pedido coincide</p>
                    <p>Prueba con otro estado u otra búsqueda, o <a href="<?= e(url('admin/pedidos.php')) ?>">quita los filtros</a>.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/modal_confirmar.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
