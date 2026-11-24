<?php
/**
 * Tarjeta de un plato (componente parcial). Se incluye dentro de un foreach:
 *   foreach ($platos as $plato) { $mostrar_boton = true; include 'includes/tarjeta_plato.php'; }
 * Variables que espera:
 *   $plato          array  una fila de obtener_platos() (name, description, price, image, categoria...)
 *   $mostrar_boton  bool   true en el menú del cliente: muestra el botón "Añadir" (lo usa el carrito)
 * Diseño: foto 4:3 con degradado, categoría en versalitas doradas, nombre serif, descripción
 * de 2 líneas y precio grande. Sin foto se muestra un fondo con la marca de Ébano.
 */

$mostrar_boton = $mostrar_boton ?? false;
$categoria_plato = $plato['categoria'] ?? categoria_normalizada($plato['category'] ?? null);
$tiene_foto = trim((string) ($plato['image'] ?? '')) !== '';
?>
<article class="plato">
    <div class="plato-foto">
        <?php if ($tiene_foto): ?>
            <img src="<?= e($plato['image']) ?>" alt="<?= e($plato['name']) ?>"
                 width="800" height="600" loading="lazy" decoding="async">
        <?php else: ?>
            <!-- Sin foto: degradado con la marca, para que la carta no muestre huecos -->
            <div class="plato-sin-foto" role="img" aria-label="<?= e($plato['name']) ?>">
                <?= marca_ebano() ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="plato-cuerpo">
        <span class="plato-categoria"><?= e($categoria_plato) ?></span>
        <h3 class="plato-nombre"><?= e($plato['name']) ?></h3>
        <p class="plato-descripcion"><?= e($plato['description'] ?? '') ?></p>

        <div class="plato-pie">
            <span class="plato-precio precio"><?= e(dinero((float) $plato['price'])) ?></span>
            <?php if ($mostrar_boton): ?>
                <!-- data-*: el carrito (carrito.js) lee aquí el id, el nombre y el precio del plato -->
                <button type="button" class="btn btn-oro btn-sm btn-anadir"
                        data-id="<?= e((string) $plato['id']) ?>"
                        data-nombre="<?= e($plato['name']) ?>"
                        data-precio="<?= e((string) $plato['price']) ?>"
                        aria-label="Añadir <?= e($plato['name']) ?> al pedido">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Añadir
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
