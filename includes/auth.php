<?php
/**
 * Autenticación y control de acceso por rol.
 * Es el PRIMER archivo que incluye cada página: carga sesion.php, funciones.php, db.php y usuarios.php,
 * abre la sesión segura y ofrece:
 * - usuario_actual(): el usuario logueado, leído de la BD en cada petición.
 * - requerir_login() / requerir_rol(): cortan el acceso (redirección a login o página 403).
 * - dashboard_de(): la página de inicio de cada rol.
 */

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/usuarios.php';

// Se ejecuta al incluir el archivo, antes de que la página imprima nada
iniciar_sesion_segura();
enviar_cabeceras_seguridad();

/**
 * Devuelve el usuario logueado ['id','username','email','role'] o null si no hay sesión.
 * Se lee de la BD (una vez por petición) y no de la sesión: si el admin cambia el rol
 * o borra al usuario, el cambio se aplica en la siguiente página que abra.
 */
function usuario_actual(): ?array
{
    // static: el resultado se guarda entre llamadas, así solo hay una consulta por petición
    static $cargado = false;
    static $usuario = null;

    if ($cargado) {
        return $usuario;
    }
    $cargado = true;

    $id = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $st = conectar()->prepare(
        'SELECT u.id, u.username, u.email, r.name AS role
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?'
    );
    $st->execute([$id]);
    $fila = $st->fetch();

    if (!$fila) {
        // El usuario fue eliminado mientras tenía la sesión abierta: se limpia la sesión
        $_SESSION = [];
        return null;
    }

    $fila['id'] = (int) $fila['id'];
    $_SESSION['rol'] = $fila['role']; // la BD manda; la sesión solo guarda una copia
    $usuario = $fila;
    return $usuario;
}

/** Exige una sesión iniciada; si no la hay, redirige a login.php. Devuelve el usuario. */
function requerir_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        flash('warning', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }
    // Páginas privadas: que el navegador no las guarde (el botón "atrás" tras salir no las muestra)
    if (!headers_sent()) {
        header('Cache-Control: no-store');
    }
    return $u;
}

/**
 * Exige que el usuario tenga uno de los roles indicados (nombres de la BD, ej: ['Chef']).
 * Sin sesión redirige a login; con otro rol responde 403 con la página temática y termina.
 */
function requerir_rol(array $roles): array
{
    $usuario = requerir_login();
    if (!in_array($usuario['role'], $roles, true)) {
        http_response_code(403);
        // 403.php usa $usuario; se incluye aquí mismo para no cambiar de URL
        include __DIR__ . '/../403.php';
        exit;
    }
    return $usuario;
}

/** Ruta del panel principal de cada rol (relativa a BASE_URL). */
function dashboard_de(string $rol): string
{
    $paneles = [
        'Administrator' => 'admin/dashboard.php',
        'Chef'          => 'chef/cocina.php',
        'Waiter'        => 'camarero/dashboard.php',
        'Client'        => 'cliente/menu.php',
    ];
    return $paneles[$rol] ?? 'index.php';
}
