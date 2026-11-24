<?php
/**
 * Menú del cliente: la carta completa y el carrito para hacer un pedido.
 * GET: muestra los platos disponibles agrupados por sección, los chips para filtrar
 * y el carrito lateral (offcanvas de Bootstrap) que maneja assets/js/carrito.js.
 * El Chef también puede abrir la carta, pero solo para consultarla: sin botones "Añadir" ni carrito.
 * POST (accion=pedir, solo Client): recibe items[id_plato] = cantidad, los valida con crear_pedidos()
 * y sigue el patrón PRG: guarda un mensaje flash y redirige (así recargar no repite el pedido).
 * El precio nunca viaja desde el navegador: el total que cuenta es el de la BD.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platos.php';
require_once __DIR__ . '/../includes/pedidos.php';

$usuario = requerir_rol(['Client', 'Chef']);
$puede_pedir = $usuario['role'] === 'Client'; // el chef solo ve la carta (spec §6 "Ver menú")

// ---- POST: enviar el pedido a cocina ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    // La página es visible para el chef, pero pedir no: se comprueba en el servidor, no solo ocultando el botón
    if (!$puede_pedir) {
        flash('danger', 'Tu rol solo puede consultar la carta.');
        redirigir('cliente/menu.php');
    }

    if (($_POST['accion'] ?? '') === 'pedir') {
        // items[id] = cantidad -> [['dish_id' => id, 'quantity' => cantidad], ...]
        $items = items_desde_post($_POST['items'] ?? null);

        try {
            crear_pedidos($usuario['id'], $items);
            flash('success', '¡Pedido enviado a cocina!');
            redirigir('cliente/mis_pedidos.php?nuevo=1');
        } catch (InvalidArgumentException $ex) {
            flash('danger', $ex->getMessage()); // el carrito sigue guardado: se puede corregir y reenviar
        }
    }
    redirigir('cliente/menu.php');
}

// ---- GET: datos de la carta ----
$platos = obtener_platos(true);

// Platos agrupados por sección, en el orden de la carta: ['Entradas' => [...], ...]
$carta = [];
foreach (categorias_menu() as $categoria) {
    $carta[$categoria] = [];
}
foreach ($platos as $plato) {
    $carta[$plato['categoria']][] = $plato;
}
$carta = array_filter($carta); // sin secciones vacías

// Platos del cliente que todavía no se entregaron (para el aviso de la cabecera)
$en_curso = 0;
if ($puede_pedir) { // el chef no tiene pedidos propios
    foreach (pedidos_de_cliente($usuario['id']) as $pedido) {
        if ($pedido['status'] !== 'Served') {
            $en_curso += (int) $pedido['quantity'];
        }
    }
}

$titulo_pagina = 'La carta';
$clase_body = 'rol-cliente pagina-menu';
// carrito.js se carga también para el chef: sin carrito en la página solo activa los chips de filtro
$scripts_pagina = ['assets/js/carrito.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <header class="menu-cabecera">
        <div class="titulo-seccion mb-0 anim-item">
            <h1>La carta de hoy</h1>
            <?php if ($puede_pedir): ?>
                <p>Elige tus platos, revisa el pedido y envíalo a cocina. Desde ese momento puedes seguirlo paso a paso.</p>
            <?php else: ?>
                <p>Así ve la carta el cliente. Es solo de consulta: desde aquí no se hacen pedidos.</p>
            <?php endif; ?>
        </div>
        <?php if ($en_curso > 0): ?>
            <a class="menu-en-curso anim-item" href="<?= e(url('cliente/mis_pedidos.php')) ?>">
                <span class="menu-en-curso-punto" aria-hidden="true"></span>
                <?= e((string) $en_curso) ?> <?= $en_curso === 1 ? 'plato en camino' : 'platos en camino' ?>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </header>

    <?php if ($carta): ?>
        <!-- Filtros: carrito.js muestra solo la sección elegida -->
        <div class="filtros anim-item" role="group" aria-label="Filtrar la carta por sección">
            <button type="button" class="chip" data-filtro="todas" aria-pressed="true">
                Todas <span class="chip-numero"><?= e((string) count($platos)) ?></span>
            </button>
            <?php foreach ($carta as $categoria => $platos_seccion): ?>
                <button type="button" class="chip" data-filtro="<?= e($categoria) ?>" aria-pressed="false">
                    <?= e($categoria) ?> <span class="chip-numero"><?= e((string) count($platos_seccion)) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($carta as $categoria => $platos_seccion): ?>
            <?php $id_seccion = 'seccion-' . strtolower($categoria); // ej: seccion-entradas ?>
            <section class="carta-seccion" data-categoria="<?= e($categoria) ?>" aria-labelledby="<?= e($id_seccion) ?>">
                <h2 class="carta-seccion-titulo" id="<?= e($id_seccion) ?>"><?= e($categoria) ?></h2>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 revelar" data-escalonar>
                    <?php foreach ($platos_seccion as $plato): ?>
                        <div class="col">
                            <?php
                            $mostrar_boton = $puede_pedir; // botón "Añadir" con data-id, data-nombre y data-precio
                            include __DIR__ . '/../includes/tarjeta_plato.php';
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="estado-vacio">
            <div class="estado-vacio-marca" aria-hidden="true"><?= marca_ebano() ?></div>
            <h2>La cocina está cambiando la carta</h2>
            <p>Ahora mismo no hay platos disponibles. Vuelve en un rato: la carta se renueva con la temporada.</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($puede_pedir): ?>
<!-- Botón flotante: abre el carrito y muestra cuántas unidades lleva -->
<button type="button" class="btn btn-oro carrito-flotante" data-bs-toggle="offcanvas" data-bs-target="#carrito"
        aria-controls="carrito">
    <i class="bi bi-bag" aria-hidden="true"></i>
    <span class="carrito-flotante-texto">Tu pedido</span>
    <span class="carrito-contador" data-carrito-contador hidden>0</span>
</button>

<!-- Región invisible: los lectores de pantalla anuncian lo que pasa en el carrito -->
<p class="visually-hidden" data-carrito-aviso aria-live="polite"></p>

<!-- Carrito lateral. Es un formulario POST: carrito.js agrega los items[id]=cantidad antes de enviarlo -->
<form class="offcanvas offcanvas-end carrito" id="carrito" tabindex="-1" aria-labelledby="carrito-titulo"
      method="post" action="<?= e(url('cliente/menu.php')) ?>" data-carrito="<?= e(clave_carrito(CLAVE_CARRITO_CLIENTE, (int) $usuario['id'])) ?>">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="pedir">
    <div data-carrito-campos></div>

    <div class="offcanvas-header">
        <h2 class="offcanvas-title carrito-titulo" id="carrito-titulo">Tu pedido</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar el pedido"></button>
    </div>

    <div class="offcanvas-body">
        <ul class="carrito-lista" data-carrito-lista hidden></ul>
        <div class="carrito-vacio" data-carrito-vacio>
            <i class="bi bi-bag" aria-hidden="true"></i>
            <p class="carrito-vacio-titulo">Todavía no has elegido nada</p>
            <p>Pulsa <strong>Añadir</strong> en cualquier plato de la carta y aparecerá aquí.</p>
        </div>
    </div>

    <div class="carrito-pie">
        <div class="carrito-total">
            <span>Total</span>
            <span class="precio" data-carrito-total>$0.00</span>
        </div>
        <p class="carrito-nota">Al confirmar, tu pedido entra directamente al tablero de la cocina.</p>
        <button type="submit" class="btn btn-oro btn-lg w-100" data-carrito-confirmar disabled>
            Confirmar pedido
        </button>
    </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
