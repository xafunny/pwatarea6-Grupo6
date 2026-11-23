<?php
/**
 * Registro de clientes nuevos.
 * Valida en el servidor (siempre): nombre de 2 a 50 caracteres, email válido y no registrado,
 * contraseña de al menos 6 caracteres y confirmación igual. La validación en JS es solo comodidad.
 * El rol siempre es Client (lo fija registrar_cliente(), no el formulario).
 * Si todo es correcto, inicia la sesión y lleva al menú con un mensaje de bienvenida.
 */

require_once __DIR__ . '/includes/auth.php';

$actual = usuario_actual();
if ($actual !== null) {
    redirigir(dashboard_de($actual['role']));
}

$nombre = '';
$email = '';
$errores = [];   // [campo => mensaje] para marcar cada campo con su error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');
    $clave2 = (string) ($_POST['clave2'] ?? '');

    // Aquí no se usa verificar_csrf(): si la sesión caducó entre abrir el formulario y enviarlo,
    // se vuelve a mostrar el formulario (ya con token nuevo) con un aviso en lugar de un 403 en texto plano.
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        $errores['general'] = 'La sesión expiró, vuelve a intentarlo.';
    } else {
        $errores = errores_usuario($nombre, $email, $clave, true);
        if (!isset($errores['clave']) && $clave !== $clave2) {
            $errores['clave2'] = 'Las contraseñas no coinciden.';
        }
    }

    if (!$errores) {
        try {
            $id = registrar_cliente($nombre, $email, $clave);
            iniciar_sesion_usuario(['id' => $id, 'role' => 'Client']);
            flash('success', 'Bienvenido a Ébano, ' . $nombre . '. Ya puedes elegir tus platos.');
            redirigir('cliente/menu.php');
        } catch (InvalidArgumentException $ex) {
            // Datos inválidos que se colaron pese a la validación previa
            $errores['email'] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                // Otra persona registró el mismo correo en el mismo instante: lo frena el UNIQUE de la BD en el INSERT
                $errores['email'] = 'Ese correo ya está registrado.';
            } else {
                error_log('Error al registrar cliente: ' . $ex->getMessage());
                $errores['general'] = 'No pudimos crear tu cuenta en este momento. Inténtalo de nuevo.';
            }
        }
    }
}

/** Devuelve ' is-invalid' si el campo tiene error (clase de Bootstrap que lo pinta en rojo). */
function clase_invalido(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

$titulo_pagina = 'Crear cuenta';
$clase_body = 'pagina-acceso';
$sin_navbar = true;
require __DIR__ . '/includes/header.php';
?>
<div class="acceso">
    <aside class="acceso-ambiente">
        <img class="acceso-foto" src="https://images.unsplash.com/photo-1559339352-11d035aa65de?w=1600&q=70&auto=format&fit=crop"
             alt="Comedor del restaurante de noche, con luces cálidas sobre las mesas" width="1600" height="1067">
        <a class="marca marca-grande" href="<?= e(url('index.php')) ?>">
            <?= marca_ebano() ?>
            <span class="marca-nombre"><?= e(APP_NAME) ?></span>
        </a>
        <p class="acceso-lema">Pide desde la mesa.<br><em>Sigue tu plato hasta que llegue.</em></p>
    </aside>

    <section class="acceso-panel" aria-labelledby="titulo-registro">
        <div class="acceso-caja">
            <a class="marca acceso-marca-movil" href="<?= e(url('index.php')) ?>">
                <?= marca_ebano() ?>
                <span class="marca-nombre"><?= e(APP_NAME) ?></span>
            </a>

            <h1 id="titulo-registro" class="acceso-titulo anim-item">Crea tu cuenta</h1>
            <p class="acceso-subtitulo anim-item">Con tu cuenta pides de la carta y ves en qué paso va cada plato.</p>

            <?= mostrar_flash() ?>
            <?php if (isset($errores['general'])): ?>
                <div class="alert alert-danger" role="alert"><?= e($errores['general']) ?></div>
            <?php elseif ($errores): ?>
                <div class="alert alert-danger" role="alert">Revisa los campos marcados.</div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('registro.php')) ?>" class="needs-validation" novalidate>
                <?= campo_csrf() ?>
                <div class="mb-3 anim-item">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input type="text" class="form-control<?= clase_invalido($errores, 'nombre') ?>" id="nombre" name="nombre"
                           value="<?= e($nombre) ?>" autocomplete="name" minlength="2" maxlength="50" required>
                    <div class="invalid-feedback"><?= e($errores['nombre'] ?? 'El nombre debe tener entre 2 y 50 caracteres.') ?></div>
                </div>
                <div class="mb-3 anim-item">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control<?= clase_invalido($errores, 'email') ?>" id="email" name="email"
                           value="<?= e($email) ?>" autocomplete="email" maxlength="100" required>
                    <div class="invalid-feedback"><?= e($errores['email'] ?? 'Escribe un correo electrónico válido.') ?></div>
                </div>
                <div class="row g-3 mb-4 anim-item">
                    <div class="col-sm-6">
                        <label for="clave" class="form-label">Contraseña</label>
                        <input type="password" class="form-control<?= clase_invalido($errores, 'clave') ?>" id="clave" name="clave"
                               autocomplete="new-password" minlength="6" maxlength="72" required aria-describedby="ayuda-clave">
                        <div id="ayuda-clave" class="form-text">Mínimo 6 caracteres.</div>
                        <div class="invalid-feedback"><?= e($errores['clave'] ?? 'Usa al menos 6 caracteres.') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label for="clave2" class="form-label">Repite la contraseña</label>
                        <input type="password" class="form-control<?= clase_invalido($errores, 'clave2') ?>" id="clave2" name="clave2"
                               autocomplete="new-password" required data-igual-a="clave">
                        <div class="invalid-feedback"><?= e($errores['clave2'] ?? 'Las contraseñas no coinciden.') ?></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-oro btn-lg w-100 anim-item">Crear cuenta</button>
            </form>

            <p class="acceso-alterno anim-item">¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión</a></p>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
