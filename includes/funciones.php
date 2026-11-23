<?php
/**
 * Funciones utilitarias y reglas de negocio de Ébano.
 * Se incluye con require_once desde cada página. Reúne: escape de HTML, URLs y redirecciones,
 * protección CSRF, mensajes flash, traducciones de estados y roles, formato de dinero
 * y la tabla de transiciones de estado permitidas por rol.
 * No inicia la sesión: eso lo hace includes/sesion.php antes de usar CSRF o flash.
 */

require_once __DIR__ . '/../config/config.php'; // para BASE_URL

/** Los 4 estados de un pedido, en el orden en que avanzan. */
const ESTADOS = ['Pending', 'Preparing', 'Ready to serve', 'Served'];

/**
 * Reglas de transición: [rol => [estado actual => [estados a los que puede pasar]]].
 * El administrador no aparece aquí porque puede pasar a cualquier estado distinto.
 */
const TRANSICIONES = [
    'Chef'   => [
        'Pending'   => ['Preparing'],
        'Preparing' => ['Ready to serve'],
    ],
    'Waiter' => [
        'Ready to serve' => ['Served'],
    ],
];

/**
 * Escapa texto para imprimirlo en HTML (evita XSS). Acepta null.
 * ENT_SUBSTITUTE: si llega UTF-8 inválido se reemplaza por "�" en vez de devolver una cadena vacía.
 */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** SVG de la marca de Ébano (un rombo dorado), para la barra de navegación y las páginas de acceso. */
function marca_ebano(): string
{
    return '<svg class="marca-icono" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">'
        . '<path d="M12 1.8 21 12l-9 10.2L3 12z" fill="none" stroke="currentColor" stroke-width="1.4"/>'
        . '<path d="M12 7.2 16.2 12 12 16.8 7.8 12z" fill="currentColor"/>'
        . '</svg>';
}

/** Construye una URL del sitio anteponiendo BASE_URL (ej: url('admin/x.php')). */
function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/** Redirige a una ruta del sitio y termina el script (nada se imprime después). */
function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

/** Devuelve el token CSRF de la sesión; lo crea la primera vez. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** HTML del campo oculto con el token CSRF, para poner dentro de cada formulario POST. */
function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verifica el token CSRF enviado (campo POST o cabecera X-CSRF-Token); si falla responde 403. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // hash_equals compara en tiempo constante, para no filtrar información del token
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        echo 'Solicitud no válida (token CSRF incorrecto).';
        exit;
    }
}

/** Guarda un mensaje para mostrarlo en la siguiente página. $tipo: success, danger, warning o info. */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve el HTML de los mensajes flash pendientes (alertas Bootstrap) y vacía la cola. */
function mostrar_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['tipo']) . ' alert-dismissible fade show" role="alert">'
            . e($f['mensaje'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>'
            . '</div>';
    }
    unset($_SESSION['flash']); // se muestran una sola vez
    return $html;
}

/** Traduce un estado de la BD al español (un estado desconocido se devuelve igual). */
function estado_es(string $estado): string
{
    $nombres = [
        'Pending'        => 'Pendiente',
        'Preparing'      => 'En preparación',
        'Ready to serve' => 'Listo para servir',
        'Served'         => 'Entregado',
    ];
    return $nombres[$estado] ?? $estado;
}

/** Clase CSS (sufijo de .estado-xxx) que corresponde a cada estado. */
function clase_estado(string $estado): string
{
    $clases = [
        'Pending'        => 'pendiente',
        'Preparing'      => 'preparando',
        'Ready to serve' => 'listo',
        'Served'         => 'servido',
    ];
    return $clases[$estado] ?? '';
}

/** Formatea un monto como dinero: 12.5 -> '$12.50'. */
function dinero(float $v): string
{
    return '$' . number_format($v, 2, '.', ',');
}

/** Traduce el nombre de un rol de la BD al español. */
function rol_es(string $rol): string
{
    $nombres = [
        'Administrator' => 'Administrador',
        'Chef'          => 'Chef',
        'Waiter'        => 'Camarero',
        'Client'        => 'Cliente',
    ];
    return $nombres[$rol] ?? $rol;
}

/** Indica si un rol puede cambiar un pedido del estado $desde al estado $hacia. */
function transicion_permitida(string $rol, string $desde, string $hacia): bool
{
    if ($desde === $hacia) {
        return false;
    }
    // El administrador corrige cualquier pedido: acepta cualquier par de estados distintos
    if ($rol === 'Administrator') {
        return in_array($desde, ESTADOS, true) && in_array($hacia, ESTADOS, true);
    }
    return in_array($hacia, TRANSICIONES[$rol][$desde] ?? [], true);
}
