<?php
/**
 * Mis pedidos (cliente): seguimiento de lo que está en curso e historial de lo entregado.
 * - En curso (estado distinto de Served): una tarjeta por plato con foto, cantidad, hace cuánto
 *   se pidió, insignia de estado y una línea de progreso de 4 pasos.
 * - Historial (Served): tabla con fecha, plato, cantidad, subtotal y el total gastado.
 * Con ?nuevo=1 (llega desde el menú tras confirmar) el <body> lleva data-vaciar-carrito
 * y carrito.js borra el carrito guardado: el pedido ya está en la BD.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

$usuario = requerir_rol(['Client']);

$pedidos = pedidos_de_cliente($usuario['id']);

// Se separan los pedidos en curso de los ya entregados
$activos = array_values(array_filter($pedidos, fn(array $p): bool => $p['status'] !== 'Served'));
$historial = array_values(array_filter($pedidos, fn(array $p): bool => $p['status'] === 'Served'));

// Total gastado: precio de la BD × cantidad de cada plato entregado
$total_gastado = 0.0;
foreach ($historial as $pedido) {
    $total_gastado += (float) $pedido['price'] * (int) $pedido['quantity'];
}

$titulo_pagina = 'Mis pedidos';
$clase_body = 'rol-cliente pagina-mis-pedidos';
$scripts_pagina = ['assets/js/carrito.js'];
if (($_GET['nuevo'] ?? '') === '1') {
    $atributos_body = ['data-vaciar-carrito' => clave_carrito(CLAVE_CARRITO_CLIENTE, (int) $usuario['id'])];
}
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <header class="menu-cabecera">
        <div class="titulo-seccion mb-0 anim-item">
            <h1>Mis pedidos</h1>
            <p>Sigue cada plato desde que entra en cocina hasta que llega a tu mesa.</p>
        </div>
        <a class="btn btn-contorno anim-item" href="<?= e(url('cliente/menu.php')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Pedir algo más
        </a>
    </header>

    <?php if (!$pedidos): ?>
        <!-- Sin ningún pedido todavía -->
        <div class="estado-vacio anim-item">
            <div class="estado-vacio-marca" aria-hidden="true"><?= marca_ebano() ?></div>
            <h2>Tu mesa aún está por estrenar</h2>
            <p>Cuando envíes tu primer pedido, aquí verás cómo avanza de la cocina a tu mesa.</p>
            <a class="btn btn-oro btn-lg" href="<?= e(url('cliente/menu.php')) ?>">Ver la carta</a>
        </div>
    <?php else: ?>

        <!-- ===== En curso ===== -->
        <section class="bloque-pedidos" aria-labelledby="titulo-en-curso">
            <h2 class="bloque-pedidos-titulo" id="titulo-en-curso">
                En curso <span class="bloque-pedidos-numero"><?= e((string) count($activos)) ?></span>
            </h2>

            <?php if ($activos): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    <?php foreach ($activos as $pedido): ?>
                        <?php $paso_actual = array_search($pedido['status'], ESTADOS, true); ?>
                        <div class="col anim-item">
                            <article class="card-ebano pedido-activo" aria-label="<?= e($pedido['name']) ?>: <?= e(estado_es($pedido['status'])) ?>">
                                <div class="pedido-activo-cabecera">
                                    <?php if (trim((string) $pedido['image']) !== ''): ?>
                                        <img class="pedido-miniatura" src="<?= e($pedido['image']) ?>" alt=""
                                             width="64" height="64" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="pedido-miniatura pedido-miniatura-vacia" aria-hidden="true"><?= marca_ebano() ?></span>
                                    <?php endif; ?>
                                    <div class="pedido-activo-datos">
                                        <h3 class="pedido-activo-nombre"><?= e($pedido['name']) ?></h3>
                                        <p class="pedido-activo-meta">
                                            <span class="numeros">× <?= e((string) $pedido['quantity']) ?></span>
                                            <span aria-hidden="true">·</span>
                                            <time datetime="<?= e(str_replace(' ', 'T', $pedido['created_at'])) ?>"><?= e(hace_cuanto((int) $pedido['minutos'], $pedido['created_at'])) ?></time>
                                        </p>
                                        <span class="badge-estado <?= e(clase_estado($pedido['status'])) ?>">
                                            <?= e(estado_es($pedido['status'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Progreso: los pasos anteriores quedan rellenos y el actual en oro -->
                                <ol class="progreso">
                                    <?php foreach (ESTADOS as $indice => $estado): ?>
                                        <?php
                                        $clase_paso = '';
                                        if ($indice < $paso_actual) {
                                            $clase_paso = 'hecho';
                                        } elseif ($indice === $paso_actual) {
                                            $clase_paso = 'actual';
                                        }
                                        ?>
                                        <li class="progreso-paso <?= e($clase_paso) ?>"<?= $indice === $paso_actual ? ' aria-current="step"' : '' ?>>
                                            <span class="progreso-punto" aria-hidden="true">
                                                <?php if ($clase_paso === 'hecho'): ?><i class="bi bi-check"></i><?php endif; ?>
                                            </span>
                                            <span class="progreso-texto"><?= e(estado_es($estado)) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="bloque-pedidos-vacio">
                    Nada en el fuego ahora mismo.
                    <a class="enlace-flecha" href="<?= e(url('cliente/menu.php')) ?>">Ver la carta <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </p>
            <?php endif; ?>
        </section>

        <!-- ===== Historial ===== -->
        <section class="bloque-pedidos" aria-labelledby="titulo-historial">
            <h2 class="bloque-pedidos-titulo" id="titulo-historial">
                Historial <span class="bloque-pedidos-numero"><?= e((string) count($historial)) ?></span>
            </h2>

            <?php if ($historial): ?>
                <div class="card-ebano p-0 anim-item">
                    <div class="table-responsive">
                        <table class="table tabla-ebano tabla-historial">
                            <caption class="visually-hidden">Platos entregados, del más reciente al más antiguo</caption>
                            <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Plato</th>
                                <th scope="col" class="text-end">Cant.</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col">Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historial as $pedido): ?>
                                <tr>
                                    <td class="text-nowrap texto-2">
                                        <time datetime="<?= e(str_replace(' ', 'T', $pedido['created_at'])) ?>">
                                            <?= e(date('d/m/Y · H:i', strtotime($pedido['created_at']))) ?>
                                        </time>
                                    </td>
                                    <td><?= e($pedido['name']) ?></td>
                                    <td class="text-end"><?= e((string) $pedido['quantity']) ?></td>
                                    <td class="text-end text-nowrap"><?= e(dinero((float) $pedido['price'] * (int) $pedido['quantity'])) ?></td>
                                    <td><span class="badge-estado <?= e(clase_estado($pedido['status'])) ?>"><?= e(estado_es($pedido['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th scope="row" colspan="3">Total gastado</th>
                                <td class="text-end text-nowrap"><span class="precio tabla-total"><?= e(dinero($total_gastado)) ?></span></td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p class="bloque-pedidos-vacio">Los platos que ya llegaron a tu mesa aparecerán aquí, con lo que llevas gastado.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
