<?php
/**
 * Gestión de usuarios y roles (CRUD) del administrador.
 * - Tabla con iniciales, nombre, correo, rol y acciones. La fila propia lleva la marca "Tú"
 *   y no se puede eliminar.
 * - Un único modal Bootstrap sirve para crear y para editar (admin.js lo rellena con los data-* de la fila).
 *   Al editar, la contraseña vacía conserva la actual.
 * POST (patrón PRG, con CSRF):
 *   accion=guardar  -> guardar_usuario(). Regla extra: el admin no puede cambiarse su propio rol,
 *                      así el sistema nunca se queda sin administrador.
 *   accion=eliminar -> eliminar_usuario(); cada código de respuesta tiene su mensaje.
 */

require_once __DIR__ . '/../includes/auth.php';

$usuario = requerir_rol(['Administrator']);

/** Mensaje y tipo de alerta para cada código que devuelve eliminar_usuario(). */
const MENSAJES_ELIMINAR_USUARIO = [
    'ok'            => ['success', 'Usuario eliminado.'],
    'propio'        => ['danger', 'No puedes eliminar tu propia cuenta.'],
    'tiene_pedidos' => ['warning', 'Este usuario tiene pedidos registrados; no se puede eliminar.'],
    'no_existe'     => ['danger', 'Ese usuario ya no existe.'],
];

// ---- POST: guardar o eliminar ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $accion = $_POST['accion'] ?? '';
    $id_crudo = $_POST['id'] ?? '';
    $id = filter_var($id_crudo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $es_nuevo = $accion === 'guardar' && $id_crudo === '';

    if (!in_array($accion, ['guardar', 'eliminar'], true) || ($id === false && !$es_nuevo)) {
        flash('danger', 'Solicitud no válida.');
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'eliminar') {
        [$tipo, $mensaje] = MENSAJES_ELIMINAR_USUARIO[eliminar_usuario($id, $usuario['id'])];
        flash($tipo, $mensaje);
        redirigir('admin/usuarios.php');
    }

    // Guardar: solo los campos esperados y solo si son texto
    $datos = [];
    foreach (['username', 'email', 'password', 'role_id'] as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }

    // Nadie se quita a sí mismo el rol de administrador (el sistema podría quedarse sin admin)
    if (!$es_nuevo && $id === $usuario['id'] && (int) $datos['role_id'] !== id_rol('Administrator')) {
        flash('danger', 'No puedes cambiar tu propio rol: el sistema se quedaría sin administrador.');
        redirigir('admin/usuarios.php');
    }

    try {
        guardar_usuario($datos, $es_nuevo ? null : $id);
        flash('success', $es_nuevo ? 'Usuario «' . trim($datos['username']) . '» creado.' : 'Cambios guardados.');
    } catch (InvalidArgumentException $ex) {
        flash('danger', $ex->getMessage());
        // La contraseña NO se guarda en la sesión: se vuelve a escribir
        unset($datos['password']);
        $_SESSION['usuario_previo'] = $datos + ['id' => $es_nuevo ? '' : (string) $id];
    }
    redirigir('admin/usuarios.php');
}

// ---- GET ----
$usuarios = obtener_usuarios();
$roles = obtener_roles();

$previo = $_SESSION['usuario_previo'] ?? null;
unset($_SESSION['usuario_previo']);
$form = $previo ?? ['id' => '', 'username' => '', 'email' => '', 'role_id' => (string) id_rol('Client')];
// ¿El modal está editando al propio administrador? (solo ocurre al reabrirlo tras un error; admin.js lo decide en el resto de casos)
$editando_yo = (string) $form['id'] === (string) $usuario['id'];

/** Iniciales para el avatar: "Ana López" -> "AL", "camarero" -> "CA". */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    if (count($partes) > 1) {
        return mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr(end($partes), 0, 1));
    }
    return mb_strtoupper(mb_substr($partes[0], 0, 2));
}

$titulo_pagina = 'Usuarios';
$clase_body = 'rol-admin pagina-gestion';
$scripts_pagina = ['assets/js/admin.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container gestion">
    <header class="gestion-cabecera anim-item">
        <div>
            <h1 class="gestion-titulo">Usuarios</h1>
            <p class="gestion-resumen"><?= e((string) count($usuarios)) ?> cuentas. Los clientes se registran solos; el personal (chef, camarero, administrador) se crea aquí.</p>
        </div>
        <button type="button" class="btn btn-oro" data-bs-toggle="modal" data-bs-target="#modal-usuario" data-usuario-nuevo>
            <i class="bi bi-person-plus" aria-hidden="true"></i> Nuevo usuario
        </button>
    </header>

    <div class="gestion-tabla anim-item">
        <div class="table-responsive">
            <table class="table tabla-ebano tabla-gestion">
                <thead>
                    <tr>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $fila): ?>
                        <?php $es_yo = (int) $fila['id'] === $usuario['id']; ?>
                        <tr>
                            <td>
                                <div class="celda-usuario">
                                    <span class="avatar rol-<?= e(strtolower($fila['role'])) ?>" aria-hidden="true"><?= e(iniciales($fila['username'])) ?></span>
                                    <span class="celda-plato-nombre"><?= e($fila['username']) ?></span>
                                    <?php if ($es_yo): ?><span class="marca-tu">Tú</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="celda-email"><?= e($fila['email']) ?></td>
                            <td><span class="badge-rol"><?= e(rol_es($fila['role'])) ?></span></td>
                            <td>
                                <div class="acciones-fila">
                                    <button type="button" class="btn btn-contorno btn-sm" data-bs-toggle="modal" data-bs-target="#modal-usuario"
                                            data-usuario-editar
                                            data-id="<?= e((string) $fila['id']) ?>"
                                            data-nombre="<?= e($fila['username']) ?>"
                                            data-email="<?= e($fila['email']) ?>"
                                            data-rol="<?= e((string) $fila['role_id']) ?>"
                                            aria-label="Editar <?= e($fila['username']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                                    </button>
                                    <?php if ($es_yo): ?>
                                        <button type="button" class="btn btn-contorno-peligro btn-sm" disabled
                                                aria-label="No puedes eliminar tu propia cuenta" title="No puedes eliminar tu propia cuenta">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="m-0"
                                              data-confirmar="¿Eliminar a «<?= e($fila['username']) ?>»? Esta acción no se puede deshacer.">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= e((string) $fila['id']) ?>">
                                            <button type="submit" class="btn btn-contorno-peligro btn-sm" aria-label="Eliminar <?= e($fila['username']) ?>">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear / editar usuario -->
<div class="modal fade modal-ebano" id="modal-usuario" tabindex="-1" aria-labelledby="modal-usuario-titulo" aria-hidden="true"
     data-yo="<?= e((string) $usuario['id']) ?>" <?= $previo !== null ? 'data-reabrir' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" action="<?= e(url('admin/usuarios.php')) ?>" novalidate>
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="usuario-id" value="<?= e((string) $form['id']) ?>">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-usuario-titulo"><?= $form['id'] !== '' ? 'Editar usuario' : 'Nuevo usuario' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="usuario-nombre">Nombre de usuario</label>
                    <input class="form-control" id="usuario-nombre" name="username" required minlength="2" maxlength="50"
                           autocomplete="off" value="<?= e($form['username']) ?>">
                    <div class="invalid-feedback">Entre 2 y 50 caracteres.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="usuario-email">Correo electrónico</label>
                    <input class="form-control" id="usuario-email" name="email" type="email" required maxlength="100"
                           autocomplete="off" value="<?= e($form['email']) ?>">
                    <div class="invalid-feedback">Escribe un correo válido.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="usuario-rol">Rol</label>
                    <select class="form-select" id="usuario-rol" name="role_id" required<?= $editando_yo ? ' disabled' : '' ?>>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= e((string) $rol['id']) ?>"<?= (string) $form['role_id'] === (string) $rol['id'] ? ' selected' : '' ?>><?= e(rol_es($rol['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Un select deshabilitado no se envía: este campo oculto manda el rol actual (admin.js lo activa solo en tu fila).
                         El servidor igualmente bloquea el cambio de tu propio rol. -->
                    <input type="hidden" name="role_id" id="usuario-rol-oculto" value="<?= e((string) $form['role_id']) ?>"<?= $editando_yo ? '' : ' disabled' ?>>
                    <div class="form-text" id="usuario-rol-aviso"<?= $editando_yo ? '' : ' hidden' ?>>Tu propio rol no se puede cambiar.</div>
                </div>
                <div>
                    <label class="form-label" for="usuario-clave">Contraseña</label>
                    <input class="form-control" id="usuario-clave" name="password" type="password" minlength="6" maxlength="72"
                           autocomplete="new-password" <?= $form['id'] === '' ? 'required' : '' ?> aria-describedby="usuario-clave-ayuda">
                    <div class="invalid-feedback">Al menos 6 caracteres.</div>
                    <div class="form-text" id="usuario-clave-ayuda"><?= $form['id'] === '' ? 'Mínimo 6 caracteres.' : 'Dejar en blanco para mantener la actual.' ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-contorno" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-oro"><i class="bi bi-check2" aria-hidden="true"></i> Guardar usuario</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/modal_confirmar.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
