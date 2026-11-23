<?php
/**
 * Landing pública de Ébano (la primera página del sitio).
 * Es pública: NO llama a requerir_login(). Solo consulta usuario_actual() para adaptar los botones:
 * un visitante ve "Iniciar sesión" y la invitación a crear cuenta; un usuario logueado ve "Ir a mi panel".
 * Secciones: portada (hero) a pantalla completa, seis platos de la carta, cómo trabaja la casa
 * y una franja final para crear cuenta. Las animaciones están en assets/js/app.js.
 */

require_once __DIR__ . '/includes/auth.php';   // sesión, funciones, BD y usuario_actual()
require_once __DIR__ . '/includes/platos.php'; // obtener_platos(), platos_destacados()

$usuario = usuario_actual();

// Datos de la carta: todos los platos disponibles y una muestra de seis repartida por secciones
$platos = obtener_platos(true);
$destacados = platos_destacados($platos, 6);
$total_platos = count($platos);
$total_secciones = count(array_unique(array_column($platos, 'categoria')));

// Destino de los botones según quién mira la página
if ($usuario === null) {
    $boton_cuenta = ['texto' => 'Iniciar sesión', 'ruta' => 'login.php'];
} else {
    $boton_cuenta = ['texto' => 'Ir a mi panel', 'ruta' => dashboard_de($usuario['role'])];
}
// La carta completa vive en el menú del cliente; un visitante primero tiene que entrar
$enlace_carta = null;
if ($usuario === null) {
    $enlace_carta = ['texto' => 'Inicia sesión para ver la carta completa', 'ruta' => 'login.php'];
} elseif ($usuario['role'] === 'Client') {
    $enlace_carta = ['texto' => 'Ver la carta completa', 'ruta' => 'cliente/menu.php'];
}

// Fotos de ambiente (Unsplash)
$foto_portada = 'https://images.unsplash.com/photo-1558030006-450675393462?w=1920&q=70&auto=format&fit=crop';
$foto_cocina = 'https://images.unsplash.com/photo-1428515613728-6b4607e44363?w=1000&q=70&auto=format&fit=crop';

$titulo_pagina = 'Cocina de autor';
$clase_body = 'pagina-inicio';
require __DIR__ . '/includes/header.php';
?>

<!-- ===== Portada ===== -->
<section class="hero" aria-labelledby="hero-titulo">
    <img class="hero-foto" src="<?= e($foto_portada) ?>" alt="" fetchpriority="high">
    <div class="container hero-contenido">
        <h1 class="hero-titulo" id="hero-titulo">
            <span class="hero-mascara"><span class="hero-linea">Ébano</span></span>
            <span class="hero-mascara"><span class="hero-linea"><em>— Cocina de autor</em></span></span>
        </h1>
        <p class="hero-texto">
            Una carta corta de <?= e((string) $total_platos) ?> platos que cambia con la temporada,
            cocinada a la vista. Pide desde la mesa y sigue tu plato desde el fuego hasta que llega.
        </p>
        <div class="hero-acciones">
            <a class="btn btn-oro btn-lg" href="#carta">Ver la carta</a>
            <a class="btn btn-contorno btn-lg hero-boton-claro" href="<?= e(url($boton_cuenta['ruta'])) ?>">
                <?= e($boton_cuenta['texto']) ?>
            </a>
        </div>
    </div>
</section>

<!-- ===== La carta: seis platos destacados ===== -->
<section class="seccion" id="carta" aria-labelledby="carta-titulo">
    <div class="container">
        <header class="carta-cabecera revelar">
            <div class="titulo-seccion mb-0">
                <h2 id="carta-titulo">La carta</h2>
                <p>
                    <span class="numeros" data-contar="<?= e((string) $total_platos) ?>"><?= e((string) $total_platos) ?></span> platos
                    en <span class="numeros" data-contar="<?= e((string) $total_secciones) ?>"><?= e((string) $total_secciones) ?></span> secciones.
                    Aquí tienes uno o dos de cada una; el resto te espera dentro.
                </p>
            </div>
            <?php if ($enlace_carta !== null): ?>
                <a class="enlace-flecha" href="<?= e(url($enlace_carta['ruta'])) ?>">
                    <?= e($enlace_carta['texto']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </header>

        <?php if ($destacados): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 revelar" data-escalonar>
                <?php foreach ($destacados as $plato): ?>
                    <div class="col">
                        <?php
                        $mostrar_boton = false; // en la landing solo se mira; se pide desde el menú
                        include __DIR__ . '/includes/tarjeta_plato.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="carta-vacia">La carta se está renovando. Vuelve en un rato.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Cómo trabaja la casa: foto de la cocina + tres valores ===== -->
<section class="seccion casa" aria-labelledby="casa-titulo">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-5 revelar">
                <figure class="casa-foto">
                    <img src="<?= e($foto_cocina) ?>" alt="Cocinero de espaldas en el pase de la cocina, bajo lámparas colgantes"
                         width="1000" height="1250" loading="lazy" decoding="async">
                </figure>
            </div>
            <div class="col-lg-7">
                <div class="titulo-seccion revelar">
                    <h2 id="casa-titulo">Lo que pasa antes de que llegue el plato</h2>
                </div>
                <ul class="casa-lista revelar" data-escalonar>
                    <li>
                        <span class="casa-icono"><i class="bi bi-basket2" aria-hidden="true"></i></span>
                        <div>
                            <h3>Producto de temporada</h3>
                            <p>Compramos a productores cercanos y la carta cambia cuando cambia el mercado. Si un ingrediente no está en su punto, ese plato no sale.</p>
                        </div>
                    </li>
                    <li>
                        <span class="casa-icono"><i class="bi bi-fire" aria-hidden="true"></i></span>
                        <div>
                            <h3>Cocina abierta</h3>
                            <p>La cocina da a la sala. Cada comanda entra en el tablero del chef en cuanto la envías y se prepara en ese momento.</p>
                        </div>
                    </li>
                    <li>
                        <span class="casa-icono"><i class="bi bi-bell" aria-hidden="true"></i></span>
                        <div>
                            <h3>Servicio en mesa</h3>
                            <p>Cuando el plato sale del pase, tu camarero recibe el aviso y lo lleva a la mesa. Tú ves cada paso del pedido en tu cuenta.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php if ($usuario === null): ?>
<!-- ===== Cierre: solo para visitantes ===== -->
<section class="seccion pt-0" aria-labelledby="cierre-titulo">
    <div class="container">
        <div class="cierre revelar">
            <div class="cierre-marca" aria-hidden="true"><?= marca_ebano() ?></div>
            <div class="cierre-texto">
                <h2 id="cierre-titulo">Crea tu cuenta y haz tu pedido</h2>
                <p>Con una cuenta eliges de la carta completa, envías el pedido a cocina y ves cuándo está listo.</p>
            </div>
            <div class="cierre-acciones">
                <a class="btn btn-oro btn-lg" href="<?= e(url('registro.php')) ?>">Crear cuenta</a>
                <a class="btn btn-contorno btn-lg" href="<?= e(url('login.php')) ?>">Ya tengo cuenta</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
