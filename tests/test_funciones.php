<?php
/**
 * Prueba de las funciones utilitarias y las reglas de negocio (se ejecuta por consola).
 * Uso: php tests/test_funciones.php
 * Verifica escape HTML, URLs, CSRF, mensajes flash, traducciones de estado/rol,
 * formato de dinero y la tabla de transiciones de estado por rol.
 */

require_once __DIR__ . '/../includes/funciones.php';

$fallos = 0;

/** Imprime OK o FALLA para una comprobación y cuenta los fallos. */
function comprobar(string $nombre, bool $ok): void
{
    global $fallos;
    echo ($ok ? 'OK  ' : 'FALLA ') . $nombre . PHP_EOL;
    if (!$ok) {
        $fallos++;
    }
}

// Sin servidor web no hay sesión: la simulamos con un arreglo.
$_SESSION = [];

// Escape y formato
comprobar('e() escapa HTML', e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
comprobar('e(null) devuelve cadena vacía', e(null) === '');
comprobar('dinero(12.5) === $12.50', dinero(12.5) === '$12.50');
comprobar('dinero(1234.5) usa separador de miles', dinero(1234.5) === '$1,234.50');
comprobar('url() antepone BASE_URL', url('admin/x.php') === '/pwatarea6/admin/x.php');

// Estados y roles
comprobar('ESTADOS tiene 4 estados en orden', ESTADOS === ['Pending', 'Preparing', 'Ready to serve', 'Served']);
comprobar('estado_es Pending', estado_es('Pending') === 'Pendiente');
comprobar('estado_es Preparing', estado_es('Preparing') === 'En preparación');
comprobar('estado_es Ready to serve', estado_es('Ready to serve') === 'Listo para servir');
comprobar('estado_es Served', estado_es('Served') === 'Entregado');
comprobar('estado_es desconocido se devuelve igual', estado_es('Otro') === 'Otro');
comprobar('clase_estado Pending', clase_estado('Pending') === 'pendiente');
comprobar('clase_estado Preparing', clase_estado('Preparing') === 'preparando');
comprobar('clase_estado Ready to serve', clase_estado('Ready to serve') === 'listo');
comprobar('clase_estado Served', clase_estado('Served') === 'servido');
comprobar('rol_es Administrator', rol_es('Administrator') === 'Administrador');
comprobar('rol_es Chef', rol_es('Chef') === 'Chef');
comprobar('rol_es Waiter', rol_es('Waiter') === 'Camarero');
comprobar('rol_es Client', rol_es('Client') === 'Cliente');

// Transiciones
comprobar('Chef Pending->Preparing', transicion_permitida('Chef', 'Pending', 'Preparing'));
comprobar('Chef Preparing->Ready to serve', transicion_permitida('Chef', 'Preparing', 'Ready to serve'));
comprobar('Chef no salta Pending->Served', !transicion_permitida('Chef', 'Pending', 'Served'));
comprobar('Chef no entrega', !transicion_permitida('Chef', 'Ready to serve', 'Served'));
comprobar('Waiter no entrega Pending->Served', !transicion_permitida('Waiter', 'Pending', 'Served'));
comprobar('Waiter Ready to serve->Served', transicion_permitida('Waiter', 'Ready to serve', 'Served'));
comprobar('Client no cocina', !transicion_permitida('Client', 'Pending', 'Preparing'));
comprobar('Chef mismo estado no permitido', !transicion_permitida('Chef', 'Pending', 'Pending'));
comprobar('Administrator Served->Pending', transicion_permitida('Administrator', 'Served', 'Pending'));
comprobar('Administrator mismo estado no permitido', !transicion_permitida('Administrator', 'Served', 'Served'));

// CSRF
$token = csrf_token();
comprobar('csrf_token tiene 64 caracteres hex', preg_match('/^[0-9a-f]{64}$/', $token) === 1);
comprobar('csrf_token es estable entre llamadas', csrf_token() === $token);
comprobar('campo_csrf incluye el token', str_contains(campo_csrf(), 'name="csrf"') && str_contains(campo_csrf(), $token));

// Flash
flash('success', 'Hecho <b>');
$html = mostrar_flash();
comprobar('mostrar_flash usa la clase del tipo', str_contains($html, 'alert-success'));
comprobar('mostrar_flash escapa el mensaje', str_contains($html, 'Hecho &lt;b&gt;') && !str_contains($html, '<b>'));
comprobar('mostrar_flash vacía la cola', mostrar_flash() === '');

exit($fallos > 0 ? 1 : 0);
