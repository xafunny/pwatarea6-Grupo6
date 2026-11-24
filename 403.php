<?php
/**
 * Página 403: acceso restringido.
 * Se usa de dos formas:
 * 1. requerir_rol() la incluye cuando el rol del usuario no tiene permiso (la URL no cambia).
 * 2. Abierta directamente en el navegador.
 * En ambos casos responde con código HTTP 403 y ofrece volver al panel del usuario.
 */

require_once __DIR__ . '/includes/auth.php';

http_response_code(403);

$usuario_403 = usuario_actual();
$destino = $usuario_403 ? dashboard_de($usuario_403['role']) : 'login.php';

$titulo_pagina = 'Acceso restringido';
$clase_body = 'pagina-403';
require __DIR__ . '/includes/header.php';
?>
<section class="container error-403" aria-labelledby="titulo-403">
    <p class="error-codigo anim-item" aria-hidden="true">403</p>
    <h1 id="titulo-403" class="error-titulo anim-item">Esta puerta es solo para el personal</h1>
    <p class="error-texto anim-item">
        <?php if ($usuario_403): ?>
            Tu cuenta de <?= e(rol_es($usuario_403['role'])) ?> no tiene permiso para ver esta página.
            Si crees que es un error, habla con el administrador.
        <?php else: ?>
            Necesitas iniciar sesión con una cuenta que tenga permiso para ver esta página.
        <?php endif; ?>
    </p>
    <div class="anim-item">
        <?php if ($usuario_403): ?>
            <a class="btn btn-oro btn-lg" href="<?= e(url($destino)) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mi panel</a>
        <?php else: ?>
            <a class="btn btn-oro btn-lg" href="<?= e(url($destino)) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Iniciar sesión</a>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
