<?php
/**
 * Nuevo pedido (camarero o administrador): registra lo que pide una mesa a nombre de un cliente.
 * Paso 1: elegir al cliente (select con obtener_clientes()).
 * Paso 2: elegir los platos con la misma carta del cliente (tarjeta_plato.php) y el carrito de carrito.js,
 *         guardado con otra clave (CLAVE_CARRITO_CAMARERO + id) para no mezclarlo con el del cliente.
 * POST (accion=pedir): verifica CSRF, comprueba en el servidor que cliente_id sea de verdad un Client
 * (nombre_cliente) y crea los pedidos. Si lo hace un camarero quedan asignados a él; si lo hace el
 * administrador quedan sin asignar (waiter_id NULL) para que cualquier camarero los tome.
 * Patrón PRG con mensaje flash: cada rol vuelve a su propio panel.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platos.php';
require_once __DIR__ . '/../includes/pedidos.php';
require_once __DIR__ . '/../includes/usuarios.php';

$usuario = requerir_rol(['Waiter', 'Administrator']);
$es_camarero = $usuario['role'] === 'Waiter';

// ---- POST: registrar el pedido de la mesa ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    if (($_POST['accion'] ?? '') !== 'pedir') {
        flash('danger', 'Solicitud no válida.');
        redirigir('camarero/nuevo_pedido.php');
    }

    // El select manda un id, pero puede venir manipulado: debe ser un entero positivo de un usuario Client
    $cliente_id = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    // nombre_cliente() valida el rol Client y devuelve el nombre en la misma consulta (null si no es cliente)
    $nombre_cliente = $cliente_id === false ? null : nombre_cliente($cliente_id);
    if ($nombre_cliente === null) {
        flash('danger', 'Elige de la lista el cliente para el que es el pedido.');
        redirigir('camarero/nuevo_pedido.php');
    }

    try {
        // Solo el camarero queda como responsable; el pedido del administrador queda sin asignar
        crear_pedidos($cliente_id, items_desde_post($_POST['items'] ?? null), $es_camarero ? (int) $usuario['id'] : null);
        flash('success', 'Pedido registrado para ' . $nombre_cliente); // mostrar_flash() lo escapa al imprimirlo
        // ?nuevo=1 hace que la página de destino borre el carrito guardado
        redirigir($es_camarero ? 'camarero/dashboard.php?nuevo=1' : 'admin/pedidos.php?nuevo=1');
    } catch (InvalidArgumentException $ex) {
        flash('danger', $ex->getMessage()); // el carrito sigue guardado: se corrige y se reenvía
    }
    // Se vuelve con el mismo cliente elegido (es un int validado, no texto del usuario)
    redirigir('camarero/nuevo_pedido.php?cliente=' . $cliente_id);
}

// ---- GET: clientes y carta ----
$clientes = obtener_clientes();
$cliente_elegido = (int) ($_GET['cliente'] ?? 0); // preselección tras un error; 0 = ninguno

$platos = obtener_platos(true);
$carta = [];
foreach (categorias_menu() as $categoria) {
    $carta[$categoria] = [];
}
foreach ($platos as $plato) {
    $carta[$plato['categoria']][] = $plato;
}
$carta = array_filter($carta); // sin secciones vacías

$titulo_pagina = 'Nuevo pedido';
$clase_body = ($es_camarero ? 'rol-camarero' : 'rol-admin') . ' pagina-nuevo-pedido';
$scripts_pagina = ['assets/js/carrito.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <header class="sala-cabecera">
        <div class="titulo-seccion mb-0 anim-item">
            <h1>Nuevo pedido</h1>
            <p>Anota lo que pide la mesa: elige al cliente, añade los platos y envíalo a cocina.
                <?= $es_camarero ? 'El pedido queda a tu cargo.' : 'El pedido queda sin asignar hasta que un camarero lo tome.' ?></p>
        </div>
        <a class="btn btn-contorno anim-item" href="<?= e(url($es_camarero ? 'camarero/dashboard.php' : 'admin/pedidos.php')) ?>">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= $es_camarero ? 'Volver a la sala' : 'Volver a pedidos' ?>
        </a>
    </header>

    <?php if (!$clientes): ?>
        <div class="estado-vacio anim-item">
            <div class="estado-vacio-marca" aria-hidden="true"><?= marca_ebano() ?></div>
            <h2>Todavía no hay clientes registrados</h2>
            <p>Cada pedido va a nombre de un cliente. Pídele a la mesa que cree su cuenta en Ébano y vuelve a esta página.</p>
        </div>
    <?php else: ?>
        <!-- Paso 1: el select pertenece al formulario del carrito gracias al atributo form="carrito" -->
        <section class="paso-sala anim-item" aria-labelledby="titulo-paso-cliente">
            <span class="paso-sala-numero" aria-hidden="true">1</span>
            <div class="paso-sala-cuerpo">
                <h2 id="titulo-paso-cliente">¿Para quién es?</h2>
                <label class="form-label" for="cliente_id">Cliente</label>
                <select class="form-select" id="cliente_id" name="cliente_id" form="carrito" required
                        aria-describedby="ayuda-cliente" data-carrito-cliente>
                    <option value="">Elige un cliente…</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= e((string) $cliente['id']) ?>" data-nombre="<?= e($cliente['username']) ?>"<?= (int) $cliente['id'] === $cliente_elegido ? ' selected' : '' ?>>
                            <?= e($cliente['username']) ?> · <?= e($cliente['email']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-text" id="ayuda-cliente">
                    ¿Cliente nuevo? Pídele que cree su cuenta en «Crear cuenta» desde su teléfono; aparecerá en esta lista al recargar.
                </p>
            </div>
        </section>

        <!-- Paso 2: la carta -->
        <section class="paso-sala paso-sala-carta" aria-labelledby="titulo-paso-platos">
            <span class="paso-sala-numero" aria-hidden="true">2</span>
            <div class="paso-sala-cuerpo">
                <h2 id="titulo-paso-platos" class="anim-item">¿Qué va a tomar?</h2>

                <?php if ($carta): ?>
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
                        <?php $id_seccion = 'seccion-' . strtolower($categoria); ?>
                        <section class="carta-seccion" data-categoria="<?= e($categoria) ?>" aria-labelledby="<?= e($id_seccion) ?>">
                            <h3 class="carta-seccion-titulo" id="<?= e($id_seccion) ?>"><?= e($categoria) ?></h3>
                            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4 revelar" data-escalonar>
                                <?php foreach ($platos_seccion as $plato): ?>
                                    <div class="col">
                                        <?php
                                        $mostrar_boton = true; // botón "Añadir" que lee carrito.js
                                        include __DIR__ . '/../includes/tarjeta_plato.php';
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="bloque-pedidos-vacio">No hay platos disponibles en la carta ahora mismo.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Botón flotante: abre el pedido de la mesa y muestra cuántas unidades lleva -->
        <button type="button" class="btn btn-oro carrito-flotante" data-bs-toggle="offcanvas" data-bs-target="#carrito"
                aria-controls="carrito">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <span class="carrito-flotante-texto">Pedido de la mesa</span>
            <span class="carrito-contador" data-carrito-contador hidden>0</span>
        </button>

        <p class="visually-hidden" data-carrito-aviso aria-live="polite"></p>

        <!-- Carrito lateral: el mismo formulario que el del cliente, con otra clave de localStorage -->
        <form class="offcanvas offcanvas-end carrito" id="carrito" tabindex="-1" aria-labelledby="carrito-titulo"
              method="post" action="<?= e(url('camarero/nuevo_pedido.php')) ?>" data-carrito="<?= e(clave_carrito(CLAVE_CARRITO_CAMARERO, (int) $usuario['id'])) ?>">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="pedir">
            <div data-carrito-campos></div>

            <div class="offcanvas-header">
                <h2 class="offcanvas-title carrito-titulo" id="carrito-titulo">Pedido de la mesa</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar el pedido"></button>
            </div>

            <div class="offcanvas-body">
                <ul class="carrito-lista" data-carrito-lista hidden></ul>
                <div class="carrito-vacio" data-carrito-vacio>
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <p class="carrito-vacio-titulo">El pedido está en blanco</p>
                    <p>Pulsa <strong>Añadir</strong> en los platos que pida la mesa y aparecerán aquí.</p>
                </div>
            </div>

            <div class="carrito-pie">
                <!-- carrito.js escribe aquí para quién es el pedido (o avisa si falta elegirlo) -->
                <div class="carrito-para" data-carrito-para>
                    <span data-carrito-para-texto aria-live="polite">Falta elegir el cliente (paso 1).</span>
                    <!-- Cierra el carrito y lleva el foco al select del paso 1 (carrito.js) -->
                    <button type="button" class="btn btn-contorno btn-sm" data-bs-dismiss="offcanvas" data-carrito-elegir>
                        Elegir cliente
                    </button>
                </div>
                <div class="carrito-total">
                    <span>Total</span>
                    <span class="precio" data-carrito-total>$0.00</span>
                </div>
                <p class="carrito-nota">Al confirmar, el pedido entra al tablero de la cocina<?= $es_camarero ? ' y queda asignado a ti' : ' sin camarero asignado' ?>.</p>
                <button type="submit" class="btn btn-oro btn-lg w-100" data-carrito-confirmar disabled>
                    Enviar a cocina
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
