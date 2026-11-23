<?php
/**
 * Inicio de sesión.
 * GET: muestra el formulario (a dos columnas: foto de ambiente y formulario).
 * POST: verifica el token CSRF, comprueba email y contraseña con autenticar() (password_verify)
 * y, si son correctos, inicia la sesión (con session_regenerate_id) y lleva al panel de su rol.
 * Si ya hay una sesión abierta, redirige directamente al panel.
 */

require_once __DIR__ . '/includes/auth.php';

$actual = usuario_actual();
if ($actual !== null) {
    redirigir(dashboard_de($actual['role']));
}

$email = '';   // se conserva en el formulario si el login falla
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    $u = autenticar($email, $clave);
    if ($u !== null) {
        iniciar_sesion_usuario($u);
        flash('success', 'Hola de nuevo, ' . $u['username'] . '.');
        redirigir(dashboard_de($u['role']));
    }
    // Mensaje genérico: no revela si el correo existe o si falló la contraseña
    $error = 'Correo o contraseña incorrectos';
}

// Cuentas de prueba que se muestran en la tabla plegable (todas usan la contraseña 123456)
$cuentas_demo = [
    ['Administrador', 'admin@ebano.com'],
    ['Chef', 'chef@ebano.com'],
    ['Camarero', 'camarero@ebano.com'],
    ['Cliente', 'cliente@ebano.com'],
];

$titulo_pagina = 'Iniciar sesión';
$clase_body = 'pagina-acceso';
$sin_navbar = true;
require __DIR__ . '/includes/header.php';
?>
<div class="acceso">
    <aside class="acceso-ambiente">
        <img class="acceso-foto" src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1600&q=70&auto=format&fit=crop"
             alt="Mesa servida con copas y velas en un comedor a media luz" width="1600" height="1067">
        <a class="marca marca-grande" href="<?= e(url('index.php')) ?>">
            <?= marca_ebano() ?>
            <span class="marca-nombre"><?= e(APP_NAME) ?></span>
        </a>
        <p class="acceso-lema">La cocina abre a las siete.<br><em>La mesa ya te espera.</em></p>
    </aside>

    <section class="acceso-panel" aria-labelledby="titulo-login">
        <div class="acceso-caja">
            <a class="marca acceso-marca-movil" href="<?= e(url('index.php')) ?>">
                <?= marca_ebano() ?>
                <span class="marca-nombre"><?= e(APP_NAME) ?></span>
            </a>

            <h1 id="titulo-login" class="acceso-titulo anim-item">Bienvenido de nuevo</h1>
            <p class="acceso-subtitulo anim-item">Entra con tu correo para ver tu mesa, tu cocina o tu sala.</p>

            <?= mostrar_flash() ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form id="form-login" method="post" action="<?= e(url('login.php')) ?>" class="needs-validation" novalidate>
                <?= campo_csrf() ?>
                <div class="mb-3 anim-item">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="email" maxlength="100" required <?= $email === '' ? 'autofocus' : '' ?>>
                    <div class="invalid-feedback">Escribe un correo electrónico válido.</div>
                </div>
                <div class="mb-4 anim-item">
                    <label for="clave" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="clave" name="clave"
                           autocomplete="current-password" required <?= $email !== '' ? 'autofocus' : '' ?>>
                    <div class="invalid-feedback">Escribe tu contraseña.</div>
                </div>
                <button type="submit" class="btn btn-oro btn-lg w-100 anim-item">Entrar</button>
            </form>

            <p class="acceso-alterno anim-item">¿Primera vez en Ébano? <a href="<?= e(url('registro.php')) ?>">Crea tu cuenta</a></p>

            <div class="demo anim-item">
                <button class="demo-boton" type="button" data-bs-toggle="collapse" data-bs-target="#cuentas-demo"
                        aria-expanded="false" aria-controls="cuentas-demo">
                    <span><i class="bi bi-person-badge" aria-hidden="true"></i> Cuentas de demostración</span>
                    <i class="bi bi-chevron-down demo-flecha" aria-hidden="true"></i>
                </button>
                <div class="collapse" id="cuentas-demo">
                    <table class="table tabla-ebano tabla-demo mb-0">
                        <caption>Todas usan la contraseña <code>123456</code>.</caption>
                        <thead>
                        <tr><th scope="col">Rol</th><th scope="col">Correo</th><th scope="col"><span class="visually-hidden">Acción</span></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cuentas_demo as [$rol, $correo]): ?>
                            <tr>
                                <td><?= e($rol) ?></td>
                                <td class="text-break"><?= e($correo) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-contorno btn-sm" data-demo-email="<?= e($correo) ?>"
                                            data-demo-clave="123456" aria-label="Usar la cuenta de <?= e($rol) ?>">Usar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
