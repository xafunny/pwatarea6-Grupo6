<?php
/**
 * Manejo seguro de la sesión PHP.
 * - iniciar_sesion_segura(): abre la sesión con una cookie protegida (HttpOnly, SameSite=Lax y Secure si hay HTTPS).
 * - iniciar_sesion_usuario(): guarda al usuario tras un login correcto y cambia el id de sesión.
 * - cerrar_sesion(): borra los datos, la cookie y destruye la sesión.
 * - enviar_cabeceras_seguridad(): cabeceras HTTP básicas contra clickjacking y sniffing.
 * Lo incluye includes/auth.php; las páginas no lo incluyen directamente.
 */

/** Minutos sin actividad tras los cuales la sesión se descarta. */
const SESION_INACTIVIDAD_MIN = 120;

/** Indica si la petición llegó por HTTPS (directo o detrás del proxy del hosting). */
function es_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Inicia la sesión con opciones seguras. Se puede llamar varias veces: si ya está activa no hace nada.
 * En consola (tests) no se abre una sesión real: se trabaja con el arreglo $_SESSION.
 */
function iniciar_sesion_segura(): void
{
    if (PHP_SAPI === 'cli') {
        $_SESSION = $_SESSION ?? [];
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Modo estricto: PHP rechaza ids de sesión que él no generó (evita la fijación de sesión)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name('EBANOSESSID');
    session_set_cookie_params([
        'lifetime' => 0,          // la cookie dura hasta cerrar el navegador
        'path'     => '/',
        'secure'   => es_https(), // solo viaja cifrada cuando el sitio usa HTTPS
        'httponly' => true,       // JavaScript no puede leerla (mitiga robo por XSS)
        'samesite' => 'Lax',      // no se envía en POST desde otros sitios (refuerza el CSRF)
    ]);
    session_start();

    // Caducidad por inactividad: una sesión olvidada en un equipo compartido no queda abierta para siempre
    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && $ahora - $_SESSION['ultima_actividad'] > SESION_INACTIVIDAD_MIN * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultima_actividad'] = $ahora;
}

/**
 * Registra al usuario en la sesión después de verificar su contraseña.
 * $u debe traer 'id' y 'role'.
 */
function iniciar_sesion_usuario(array $u): void
{
    // Nuevo id de sesión al cambiar de privilegios: un id robado o fijado antes del login deja de servir
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['usuario_id'] = (int) $u['id'];
    $_SESSION['rol'] = (string) $u['role'];
    unset($_SESSION['csrf']); // token CSRF nuevo para la sesión autenticada
}

/** Cierra la sesión por completo: datos, cookie del navegador y archivo de sesión en el servidor. */
function cerrar_sesion(): void
{
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    // Se envía la misma cookie ya vencida para que el navegador la borre
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

/** Cabeceras de seguridad para todas las respuestas HTML. */
function enviar_cabeceras_seguridad(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');            // el navegador no "adivina" tipos de archivo
    header('X-Frame-Options: SAMEORIGIN');                // nadie puede meter el sitio en un iframe ajeno
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
