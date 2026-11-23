<?php
/**
 * Sala de control del administrador.
 * - Cabecera: saludo con el nombre y la fecha de hoy.
 * - Franja de 4 indicadores: ingresos entregados, pedidos de hoy, en cocina y platos activos.
 * - "Pedidos por estado": barras horizontales hechas solo con CSS. El ancho de cada barra lo calcula
 *   PHP como porcentaje del estado con más pedidos y se escribe como un entero en style="width: N%".
 * - "Lo más pedido": los 5 platos con más unidades, con su miniatura.
 * - Accesos a la gestión de platos, usuarios y pedidos.
 * Solo lectura: esta página no recibe POST.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';
require_once __DIR__ . '/../includes/platos.php';

$usuario = requerir_rol(['Administrator']);

$stats = estadisticas_admin();
$total_pedidos = array_sum($stats['por_estado']);
// El estado con más pedidos ocupa la barra completa; max(1, ...) evita dividir por cero sin pedidos
$maximo = max(1, max($stats['por_estado']));

$todos_los_platos = obtener_platos(false);
$platos_ocultos = count($todos_los_platos) - $stats['platos_activos'];
$cantidad_usuarios = count(obtener_usuarios());

// Saludo según la hora y fecha en español (date() da los nombres en inglés, por eso las listas)
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha_hoy = $dias[(int) date('w')] . ' ' . date('j') . ' de ' . $meses[(int) date('n') - 1] . ' de ' . date('Y');

// Accesos rápidos: [ruta, título, detalle, icono]
$accesos = [
    ['admin/pedidos.php', 'Pedidos', $total_pedidos . ' registrados · ' . $stats['en_cocina'] . ' en cocina', 'bi-receipt'],
    ['admin/platos.php', 'Platos', count($todos_los_platos) . ' en la carta · ' . $platos_ocultos . ($platos_ocultos === 1 ? ' oculto' : ' ocultos'), 'bi-egg-fried'],
    ['admin/usuarios.php', 'Usuarios', $cantidad_usuarios . ' cuentas · roles y accesos', 'bi-people'],
];

$titulo_pagina = 'Sala de control';
$clase_body = 'rol-admin pagina-control';
require __DIR__ . '/../includes/header.php';
?>
<div class="container control">
    <header class="control-cabecera anim-item">
        <div>
            <h1 class="control-titulo">Sala de control</h1>
            <p class="control-saludo"><?= e($saludo) ?>, <strong><?= e($usuario['username']) ?></strong>. Así va el restaurante.</p>
        </div>
        <p class="control-fecha"><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="<?= e(date('Y-m-d')) ?>"><?= e($fecha_hoy) ?></time></p>
    </header>

    <!-- Franja de indicadores: una sola superficie dividida, no cuatro tarjetas iguales -->
    <dl class="control-indicadores anim-item">
        <div class="indicador indicador-principal">
            <dt><i class="bi bi-cash-coin" aria-hidden="true"></i> Ingresos entregados</dt>
            <dd class="precio"><?= e(dinero($stats['ingresos'])) ?></dd>
            <dd class="indicador-nota">Pedidos entregados × precio actual de la carta</dd>
        </div>
        <div class="indicador">
            <dt><i class="bi bi-calendar-check" aria-hidden="true"></i> Pedidos de hoy</dt>
            <dd data-contar="<?= e((string) $stats['pedidos_hoy']) ?>"><?= e((string) $stats['pedidos_hoy']) ?></dd>
        </div>
        <div class="indicador">
            <dt><i class="bi bi-fire" aria-hidden="true"></i> En cocina</dt>
            <dd data-contar="<?= e((string) $stats['en_cocina']) ?>"><?= e((string) $stats['en_cocina']) ?></dd>
        </div>
        <div class="indicador">
            <dt><i class="bi bi-egg-fried" aria-hidden="true"></i> Platos activos</dt>
            <dd data-contar="<?= e((string) $stats['platos_activos']) ?>"><?= e((string) $stats['platos_activos']) ?></dd>
        </div>
    </dl>

    <div class="control-rejilla">
        <!-- Barras por estado: el número va escrito al lado, el color no es la única pista -->
        <section class="control-panel control-estados anim-item" aria-labelledby="titulo-estados">
            <header class="control-panel-cabecera">
                <h2 id="titulo-estados">Pedidos por estado</h2>
                <span class="control-total"><?= e((string) $total_pedidos) ?> en total</span>
            </header>
            <ul class="barras">
                <?php foreach ($stats['por_estado'] as $estado => $cantidad): ?>
                    <?php $ancho = (int) round($cantidad / $maximo * 100); // entero de 0 a 100: seguro en el style ?>
                    <li class="barra <?= e(clase_estado($estado)) ?>">
                        <span class="barra-etiqueta"><?= e(estado_es($estado)) ?></span>
                        <span class="barra-pista" aria-hidden="true"><span class="barra-relleno" style="width: <?= $ancho ?>%"></span></span>
                        <span class="barra-numero"><?= e((string) $cantidad) ?><span class="visually-hidden"> pedidos</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="control-panel control-top anim-item" aria-labelledby="titulo-top">
            <header class="control-panel-cabecera">
                <h2 id="titulo-top">Lo más pedido</h2>
                <span class="control-total">unidades</span>
            </header>
            <?php if ($stats['top_platos']): ?>
                <ol class="top-lista">
                    <?php foreach ($stats['top_platos'] as $posicion => $plato): ?>
                        <li class="top-fila">
                            <span class="top-puesto"><?= e((string) ($posicion + 1)) ?></span>
                            <?php if (trim((string) $plato['imagen']) !== ''): ?>
                                <img class="top-foto" src="<?= e($plato['imagen']) ?>" alt="" width="48" height="48" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="top-foto top-sin-foto" aria-hidden="true"><?= marca_ebano() ?></span>
                            <?php endif; ?>
                            <span class="top-nombre"><?= e($plato['nombre']) ?></span>
                            <span class="top-unidades"><?= e((string) $plato['unidades']) ?><span class="visually-hidden"> unidades</span></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="control-vacio">Todavía no hay pedidos. Cuando lleguen, aquí verás qué platos salen más.</p>
            <?php endif; ?>
        </section>
    </div>

    <nav class="control-accesos anim-item" aria-label="Gestión">
        <?php foreach ($accesos as [$ruta, $titulo, $detalle, $icono]): ?>
            <a class="acceso-gestion" href="<?= e(url($ruta)) ?>">
                <i class="bi <?= e($icono) ?>" aria-hidden="true"></i>
                <span class="acceso-gestion-texto">
                    <span class="acceso-gestion-titulo"><?= e($titulo) ?></span>
                    <span class="acceso-gestion-detalle"><?= e($detalle) ?></span>
                </span>
                <i class="bi bi-arrow-right acceso-gestion-flecha" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
