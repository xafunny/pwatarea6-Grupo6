<?php
/**
 * Panel de sala del camarero.
 * - Cabecera: saludo, resumen (asignados activos, listos para servir, entregados hoy) y el botón "Nuevo pedido".
 * - "El pase": franja dorada con los platos que la cocina ya terminó. Cada uno tiene su botón "Entregar".
 * - Pestañas: "Mis pedidos" (los asignados a este camarero, con su estado y progreso)
 *   y "Sin asignar" (pedidos que nadie atiende, con el botón "Tomar").
 * POST (patrón PRG): accion=entregar -> cambiar_estado(..., 'Served'); accion=tomar -> tomar_pedido().
 * Las dos acciones las vuelve a validar el servidor: ocultar un botón no basta.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

$usuario = requerir_rol(['Waiter']);

/** Pestañas del panel: valor de ?pestana => id del panel en el HTML. */
const PESTANAS_SALA = ['mios' => 'panel-mios', 'libres' => 'panel-libres'];

// ---- POST: entregar o tomar un pedido ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $accion = $_POST['accion'] ?? '';
    // Entero positivo o false (también es false si llega texto, un número negativo o un arreglo)
    $pedido_id = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($pedido_id === false || !in_array($accion, ['entregar', 'tomar'], true)) {
        flash('danger', 'Solicitud no válida. Vuelve a intentarlo desde el panel.');
        redirigir('camarero/dashboard.php');
    }

    if ($accion === 'entregar') {
        $hecho = cambiar_estado($pedido_id, 'Served', $usuario);
        $exito = 'Pedido entregado';
        $volver = 'camarero/dashboard.php';
    } else {
        $hecho = tomar_pedido($pedido_id, $usuario['id']);
        $exito = 'Pedido asignado a ti';
        $volver = 'camarero/dashboard.php?pestana=libres'; // sigue en la lista para tomar otro
    }

    if ($hecho) {
        flash('success', $exito);
    } else {
        flash('danger', 'No se pudo: el pedido ya cambió de estado o fue tomado por otro camarero.');
    }
    redirigir($volver);
}

// ---- GET: datos del panel ----
$mis_pedidos = pedidos_de_camarero($usuario['id']);
$listos = pedidos_listos();
$sin_asignar = pedidos_sin_asignar();

$activos = 0;          // mis pedidos que aún no llegaron a la mesa
$entregados_hoy = 0;   // pedidos_de_camarero() solo devuelve los entregados de hoy
foreach ($mis_pedidos as $pedido) {
    if ($pedido['status'] === 'Served') {
        $entregados_hoy++;
    } else {
        $activos++;
    }
}

// Pestaña abierta al cargar: la de la URL si es válida; si no, "Mis pedidos"
$pestana = $_GET['pestana'] ?? 'mios';
if (!is_string($pestana) || !array_key_exists($pestana, PESTANAS_SALA)) {
    $pestana = 'mios';
}

// Saludo según la hora del restaurante (la zona horaria se fija en config.php)
$hora = (int) date('G');
if ($hora < 12) {
    $saludo = 'Buenos días';
} elseif ($hora < 19) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

$titulo_pagina = 'Panel de sala';
$clase_body = 'rol-camarero pagina-sala';
// Tras registrar un pedido (?nuevo=1) se borra el carrito del camarero: carrito.js lee este atributo
if (($_GET['nuevo'] ?? '') === '1') {
    $atributos_body = ['data-vaciar-carrito' => CLAVE_CARRITO_CAMARERO];
    $scripts_pagina = ['assets/js/carrito.js'];
}
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <header class="sala-cabecera">
        <div class="titulo-seccion mb-0 anim-item">
            <h1>Sala</h1>
            <p><?= e($saludo) ?>, <strong class="sala-nombre"><?= e($usuario['username']) ?></strong>. Lo que está listo en el pase va primero; debajo, tus mesas y lo que nadie atiende todavía.</p>
        </div>
        <a class="btn btn-oro btn-lg btn-nuevo-pedido anim-item" href="<?= e(url('camarero/nuevo_pedido.php')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo pedido
        </a>
    </header>

    <!-- Resumen del turno: el número final ya está en el HTML; app.js lo anima con data-contar -->
    <dl class="sala-resumen anim-item">
        <div class="sala-dato">
            <dt><i class="bi bi-person-check" aria-hidden="true"></i> Asignados activos</dt>
            <dd data-contar="<?= e((string) $activos) ?>"><?= e((string) $activos) ?></dd>
        </div>
        <div class="sala-dato destacado">
            <dt><i class="bi bi-bell" aria-hidden="true"></i> Listos para servir</dt>
            <dd data-contar="<?= e((string) count($listos)) ?>"><?= e((string) count($listos)) ?></dd>
        </div>
        <div class="sala-dato">
            <dt><i class="bi bi-check2-all" aria-hidden="true"></i> Entregados hoy</dt>
            <dd data-contar="<?= e((string) $entregados_hoy) ?>"><?= e((string) $entregados_hoy) ?></dd>
        </div>
    </dl>

    <!-- ===== El pase: lo que la cocina ya terminó ===== -->
    <section class="pase anim-item" aria-labelledby="titulo-pase">
        <header class="pase-cabecera">
            <span class="pase-icono" aria-hidden="true"><i class="bi bi-bell"></i></span>
            <h2 class="pase-titulo" id="titulo-pase">Listos para servir</h2>
            <span class="pase-cuenta"><?= e((string) count($listos)) ?></span>
            <?php if ($listos): ?>
                <p class="pase-nota">Primero lo que más tiempo lleva esperando.</p>
            <?php endif; ?>
        </header>

        <?php if ($listos): ?>
            <ol class="pase-carril">
                <?php foreach ($listos as $pedido): ?>
                    <?php
                    $minutos = (int) $pedido['minutos'];
                    $es_mio = $pedido['camarero_id'] !== null && (int) $pedido['camarero_id'] === $usuario['id'];
                    $numero = '#' . sprintf('%04d', (int) $pedido['id']);
                    ?>
                    <li class="pase-tarjeta<?= $es_mio ? ' mia' : '' ?>">
                        <div class="pase-tarjeta-cabecera">
                            <span class="pase-numero"><?= e($numero) ?></span>
                            <span class="pase-espera<?= $minutos >= MINUTOS_URGENTE ? ' demora' : '' ?>">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <span class="visually-hidden">Esperando</span> <?= e(texto_espera($minutos)) ?>
                            </span>
                        </div>
                        <h3 class="pase-plato">
                            <span class="pase-cantidad"><?= e((string) $pedido['quantity']) ?> ×</span>
                            <?= e($pedido['plato']) ?>
                        </h3>
                        <dl class="pase-datos">
                            <div>
                                <dt>Cliente</dt>
                                <dd><?= e($pedido['cliente']) ?></dd>
                            </div>
                            <div>
                                <dt>Camarero</dt>
                                <?php if ($es_mio): ?>
                                    <dd class="pase-tuyo">Tú</dd>
                                <?php elseif ($pedido['camarero'] !== null): ?>
                                    <dd><?= e($pedido['camarero']) ?></dd>
                                <?php else: ?>
                                    <dd class="pase-libre">sin asignar</dd>
                                <?php endif; ?>
                            </div>
                        </dl>
                        <form method="post" action="<?= e(url('camarero/dashboard.php')) ?>" class="pase-form">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="entregar">
                            <input type="hidden" name="pedido_id" value="<?= e((string) $pedido['id']) ?>">
                            <button type="submit" class="btn btn-oro btn-sm w-100"
                                    aria-label="Entregar <?= e($numero) ?>: <?= e($pedido['plato']) ?> para <?= e($pedido['cliente']) ?>">
                                <i class="bi bi-check2-circle" aria-hidden="true"></i> Entregar
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="pase-vacio">El pase está despejado. Cuando la cocina termine un plato, aparecerá aquí para que lo lleves a la mesa.</p>
        <?php endif; ?>
    </section>

    <!-- ===== Pestañas: mis pedidos | sin asignar ===== -->
    <section class="sala-listas anim-item" aria-label="Pedidos de la sala">
        <ul class="nav nav-pills sala-pestanas" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= $pestana === 'mios' ? ' active' : '' ?>" id="pestana-mios" type="button" role="tab"
                        data-bs-toggle="pill" data-bs-target="#panel-mios" aria-controls="panel-mios"
                        aria-selected="<?= $pestana === 'mios' ? 'true' : 'false' ?>">
                    Mis pedidos <span class="pestana-numero"><?= e((string) count($mis_pedidos)) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= $pestana === 'libres' ? ' active' : '' ?>" id="pestana-libres" type="button" role="tab"
                        data-bs-toggle="pill" data-bs-target="#panel-libres" aria-controls="panel-libres"
                        aria-selected="<?= $pestana === 'libres' ? 'true' : 'false' ?>">
                    Sin asignar <span class="pestana-numero"><?= e((string) count($sin_asignar)) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Mis pedidos: en curso primero, después los entregados de hoy -->
            <div class="tab-pane fade<?= $pestana === 'mios' ? ' show active' : '' ?>" id="panel-mios" role="tabpanel"
                 aria-labelledby="pestana-mios" tabindex="0">
                <?php if ($mis_pedidos): ?>
                    <ul class="lista-sala">
                        <?php foreach ($mis_pedidos as $pedido): ?>
                            <?php
                            $paso_actual = array_search($pedido['status'], ESTADOS, true); // 0 a 3
                            $entregado = $pedido['status'] === 'Served';
                            ?>
                            <li class="fila-sala<?= $entregado ? ' entregada' : '' ?>">
                                <span class="fila-numero">#<?= e(sprintf('%04d', (int) $pedido['id'])) ?></span>
                                <div class="fila-cuerpo">
                                    <h3 class="fila-plato"><span class="fila-cantidad"><?= e((string) $pedido['quantity']) ?> ×</span> <?= e($pedido['plato']) ?></h3>
                                    <p class="fila-meta">
                                        <span><i class="bi bi-person" aria-hidden="true"></i> <?= e($pedido['cliente']) ?></span>
                                        <time datetime="<?= e(str_replace(' ', 'T', $pedido['created_at'])) ?>"><?= e(hace_cuanto((int) $pedido['minutos'], $pedido['created_at'])) ?></time>
                                    </p>
                                </div>
                                <div class="fila-estado">
                                    <span class="badge-estado <?= e(clase_estado($pedido['status'])) ?>"><?= e(estado_es($pedido['status'])) ?></span>
                                    <!-- Progreso compacto: un tramo por estado; aria-label lo dice en palabras -->
                                    <span class="progreso-mini" role="img" aria-label="Paso <?= e((string) ($paso_actual + 1)) ?> de <?= e((string) count(ESTADOS)) ?>">
                                        <?php foreach (ESTADOS as $indice => $estado): ?>
                                            <span class="<?= $indice < $paso_actual ? 'hecho' : ($indice === $paso_actual ? 'actual' : '') ?>"></span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="sala-vacio">
                        <i class="bi bi-cup-hot" aria-hidden="true"></i>
                        <div>
                            <p class="sala-vacio-titulo">Aún no tienes mesas a tu cargo</p>
                            <p>Toma un pedido de la pestaña «Sin asignar» o registra el de una mesa con «Nuevo pedido».</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sin asignar: del más antiguo al más nuevo, con el botón "Tomar" -->
            <div class="tab-pane fade<?= $pestana === 'libres' ? ' show active' : '' ?>" id="panel-libres" role="tabpanel"
                 aria-labelledby="pestana-libres" tabindex="0">
                <?php if ($sin_asignar): ?>
                    <ul class="lista-sala">
                        <?php foreach ($sin_asignar as $pedido): ?>
                            <?php $numero = '#' . sprintf('%04d', (int) $pedido['id']); ?>
                            <li class="fila-sala">
                                <span class="fila-numero"><?= e($numero) ?></span>
                                <div class="fila-cuerpo">
                                    <h3 class="fila-plato"><span class="fila-cantidad"><?= e((string) $pedido['quantity']) ?> ×</span> <?= e($pedido['plato']) ?></h3>
                                    <p class="fila-meta">
                                        <span><i class="bi bi-person" aria-hidden="true"></i> <?= e($pedido['cliente']) ?></span>
                                        <span><i class="bi bi-clock" aria-hidden="true"></i> <?= e(texto_espera((int) $pedido['minutos'])) ?></span>
                                    </p>
                                </div>
                                <div class="fila-estado">
                                    <span class="badge-estado <?= e(clase_estado($pedido['status'])) ?>"><?= e(estado_es($pedido['status'])) ?></span>
                                </div>
                                <form method="post" action="<?= e(url('camarero/dashboard.php')) ?>" class="fila-accion">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="tomar">
                                    <input type="hidden" name="pedido_id" value="<?= e((string) $pedido['id']) ?>">
                                    <button type="submit" class="btn btn-contorno btn-sm"
                                            aria-label="Tomar <?= e($numero) ?>: <?= e($pedido['plato']) ?> para <?= e($pedido['cliente']) ?>">
                                        <i class="bi bi-hand-index" aria-hidden="true"></i> Tomar
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="sala-vacio">
                        <i class="bi bi-stars" aria-hidden="true"></i>
                        <div>
                            <p class="sala-vacio-titulo">Toda la sala está atendida</p>
                            <p>Ningún pedido espera camarero. Los nuevos que lleguen sin asignar aparecerán aquí.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
