<?php
/**
 * Pie común de todas las páginas: cierra <main>, imprime el pie del sitio y carga los scripts.
 * Orden de carga: Bootstrap (collapse, alertas, offcanvas), GSAP + ScrollTrigger, app.js
 * y por último los scripts propios de la página.
 * Variables que lee:
 *   $sin_navbar      bool   en login/registro no se imprime el pie del sitio
 *   $scripts_pagina  array  rutas relativas a BASE_URL, ej: ['assets/js/carrito.js']
 */

$sin_navbar = $sin_navbar ?? false;
$scripts_pagina = $scripts_pagina ?? [];
$version_js = (string) @filemtime(__DIR__ . '/../assets/js/app.js');
?>
</main>
<?php if (!$sin_navbar): ?>
<footer class="pie">
    <div class="container pie-contenido">
        <a class="marca marca-pie" href="<?= e(url('index.php')) ?>">
            <?= marca_ebano() ?>
            <span class="marca-nombre"><?= e(APP_NAME) ?></span>
        </a>
        <p class="pie-texto mb-0">Cocina de autor · Proyecto académico, Grupo 6 · <?= date('Y') ?></p>
    </div>
</footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
        integrity="sha384-g4NTh/Iv5PPU4xPyhEWqPcwtNXOvdaDI8LLnyYfyNZOjKJeYQyjzQ9X5275eBjpt" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"
        integrity="sha384-Z3REaz79l2IaAZqJsSABtTbhjgOUYyV3p90XNnAPCSHg3EMTz1fouunq9WZRtj3d" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e($version_js) ?>"></script>
<?php foreach ($scripts_pagina as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
