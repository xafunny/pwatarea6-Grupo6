<?php
/**
 * Tablero de cocina del chef: un kanban de comandas a todo el ancho.
 * - 3 columnas (Pendiente, En preparación, Listo para servir), cada una con su contador.
 * - Cada comanda es un ticket (includes/ticket_comanda.php) con número, hora, espera, cantidad × plato,
 *   cliente, camarero y el botón del siguiente paso ("Empezar" o "Marcar listo").
 * - PHP pinta el tablero inicial; después assets/js/cocina.js lo refresca cada 20 s desde
 *   chef/api_pedidos.php y envía los cambios de estado por fetch (con el token CSRF de la etiqueta meta).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

$usuario = requerir_rol(['Chef']);

// Columnas del tablero: estado de la BD => título y texto para cuando está vacía
$columnas = [
    'Pending'        => ['titulo' => estado_es('Pending'),        'vacio' => 'Sin comandas en espera. La sala está tranquila.'],
    'Preparing'      => ['titulo' => estado_es('Preparing'),      'vacio' => 'Nada en los fogones ahora mismo.'],
    'Ready to serve' => ['titulo' => estado_es('Ready to serve'), 'vacio' => 'El pase está despejado.'],
];

// Se reparte cada comanda en la columna de su estado (ya vienen de la más vieja a la más nueva)
foreach ($columnas as $estado => $columna) {
    $columnas[$estado]['comandas'] = [];
}
foreach (pedidos_cocina() as $fila) {
    $comanda = datos_comanda($fila);
    $columnas[$comanda['estado']]['comandas'][] = $comanda;
}

$titulo_pagina = 'Cocina';
$clase_body = 'rol-chef pagina-cocina';
$scripts_pagina = ['assets/js/cocina.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="cocina">
    <header class="cocina-barra">
        <div class="titulo-seccion mb-0">
            <h1>Cocina</h1>
            <p>Comandas en orden de llegada: lo más antiguo, arriba.</p>
        </div>

        <!-- Resumen: cuántas comandas hay en cada columna (cocina.js los actualiza) -->
        <dl class="cocina-kpis">
            <?php foreach ($columnas as $estado => $columna): ?>
                <div class="cocina-kpi <?= e(clase_estado($estado)) ?>">
                    <dt><?= e($columna['titulo']) ?></dt>
                    <dd data-kpi="<?= e($estado) ?>"><?= e((string) count($columna['comandas'])) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <div class="cocina-sync">
            <span class="sync-punto" data-sync-punto aria-hidden="true"></span>
            <span class="sync-texto" data-indicador>Actualizado hace 0 s</span>
            <button type="button" class="btn btn-contorno btn-sm" data-actualizar>
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Actualizar
            </button>
        </div>
    </header>

    <!-- Mensajes de error y confirmaciones: los lectores de pantalla los anuncian -->
    <div class="cocina-aviso" data-aviso role="status" aria-live="polite" hidden></div>

    <div class="tablero" data-tablero data-api="<?= e(url('chef/api_pedidos.php')) ?>">
        <?php foreach ($columnas as $estado => $columna): ?>
            <?php $id_titulo = 'columna-' . clase_estado($estado); ?>
            <section class="columna <?= e(clase_estado($estado)) ?>" data-columna="<?= e($estado) ?>" aria-labelledby="<?= e($id_titulo) ?>">
                <header class="columna-cabecera">
                    <span class="columna-punto" aria-hidden="true"></span>
                    <h2 class="columna-titulo" id="<?= e($id_titulo) ?>"><?= e($columna['titulo']) ?></h2>
                    <span class="columna-cuenta" data-cuenta><?= e((string) count($columna['comandas'])) ?></span>
                </header>

                <ol class="columna-lista" data-lista>
                    <?php foreach ($columna['comandas'] as $comanda): ?>
                        <?php include __DIR__ . '/../includes/ticket_comanda.php'; ?>
                    <?php endforeach; ?>
                </ol>
                <p class="columna-vacia" data-vacio<?= $columna['comandas'] ? ' hidden' : '' ?>><?= e($columna['vacio']) ?></p>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
