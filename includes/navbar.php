<?php
/**
 * Barra de navegación. Lo incluye header.php.
 * Los enlaces cambian según el rol del usuario logueado (usuario_actual()):
 *   Cliente: Menú, Mis pedidos · Chef: Cocina, Carta · Camarero: Panel, Nuevo pedido
 *   Administrador: Panel, Platos, Usuarios, Pedidos · Visitante: Iniciar sesión y Crear cuenta.
 * El botón "Salir" es un formulario POST con token CSRF (un enlace GET podría dispararse desde otro sitio).
 */

$usuario_nav = usuario_actual();

// [ruta, texto, icono de Bootstrap Icons] por rol
$enlaces_por_rol = [
    'Client' => [
        ['cliente/menu.php', 'Menú', 'bi-book'],
        ['cliente/mis_pedidos.php', 'Mis pedidos', 'bi-receipt'],
    ],
    'Chef' => [
        ['chef/cocina.php', 'Cocina', 'bi-fire'],
        ['cliente/menu.php', 'Carta', 'bi-book'], // solo lectura para el chef
    ],
    'Waiter' => [
        ['camarero/dashboard.php', 'Panel', 'bi-grid'],
        ['camarero/nuevo_pedido.php', 'Nuevo pedido', 'bi-plus-circle'],
    ],
    'Administrator' => [
        ['admin/dashboard.php', 'Panel', 'bi-speedometer2'],
        ['admin/platos.php', 'Platos', 'bi-egg-fried'],
        ['admin/usuarios.php', 'Usuarios', 'bi-people'],
        ['admin/pedidos.php', 'Pedidos', 'bi-receipt'],
    ],
];
$enlaces = $usuario_nav ? ($enlaces_por_rol[$usuario_nav['role']] ?? []) : [];

// Ruta del script actual, para marcar el enlace de la página en la que estamos
$ruta_actual = $_SERVER['SCRIPT_NAME'] ?? '';
$inicio = $usuario_nav ? dashboard_de($usuario_nav['role']) : 'index.php';
?>
<nav class="navbar navbar-expand-lg navbar-ebano sticky-top" aria-label="Navegación principal">
    <div class="container">
        <a class="navbar-brand marca" href="<?= e(url($inicio)) ?>">
            <?= marca_ebano() ?>
            <span class="marca-nombre"><?= e(APP_NAME) ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse" id="menu-principal">
            <ul class="navbar-nav me-auto">
                <?php foreach ($enlaces as [$ruta, $texto, $icono]): ?>
                    <?php $activo = url($ruta) === $ruta_actual; ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $activo ? ' active' : '' ?>" href="<?= e(url($ruta)) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
                            <i class="bi <?= e($icono) ?>" aria-hidden="true"></i> <?= e($texto) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($usuario_nav): ?>
                <div class="nav-usuario">
                    <span class="nav-usuario-nombre"><?= e($usuario_nav['username']) ?></span>
                    <span class="badge-rol"><?= e(rol_es($usuario_nav['role'])) ?></span>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= campo_csrf() ?>
                        <button type="submit" class="btn btn-contorno btn-sm">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Salir
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="nav-usuario">
                    <a class="btn btn-contorno btn-sm" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
                    <a class="btn btn-oro btn-sm" href="<?= e(url('registro.php')) ?>">Crear cuenta</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
