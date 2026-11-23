<?php
/**
 * Prueba HTTP de chef/api_pedidos.php (se ejecuta por consola, con Apache encendido).
 * Uso: php tests/test_api_cocina.php
 * Hace peticiones reales a http://localhost + BASE_URL (se puede cambiar con la variable de entorno
 * EBANO_URL) guardando la cookie de sesión como un navegador: inicia sesión como chef y como cliente
 * pasando por el formulario de login con su token CSRF.
 * Comprueba: 401/403 en JSON sin sesión o con otro rol, 403 sin token CSRF, 400 con datos malos,
 * 200 en un cambio válido, 422 al repetirlo y 405 con otro método.
 * Limpieza: crea su propio pedido Pending y al final borra los pedidos con id mayor al máximo previo,
 * así no toca el estado de los pedidos del seed.
 */

$_SESSION = [];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

$fallos = 0;
$base = getenv('EBANO_URL') ?: 'http://localhost' . BASE_URL;
$api = 'chef/api_pedidos.php';

/** Imprime OK o FALLA para una comprobación y cuenta los fallos. */
function comprobar(string $nombre, bool $ok): void
{
    global $fallos;
    echo ($ok ? 'OK  ' : 'FALLA ') . $nombre . PHP_EOL;
    if (!$ok) {
        $fallos++;
    }
}

/**
 * Hace una petición HTTP y guarda en $cookies las que mande el servidor (como un navegador).
 * No sigue redirecciones. Devuelve ['codigo' => int, 'tipo' => string, 'cuerpo' => string, 'json' => ?array].
 */
function peticion(array &$cookies, string $metodo, string $ruta, ?string $cuerpo = null, array $cabeceras = []): array
{
    global $base;
    if ($cookies) {
        $pares = [];
        foreach ($cookies as $nombre => $valor) {
            $pares[] = $nombre . '=' . $valor;
        }
        $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
    }
    $contexto = stream_context_create(['http' => [
        'method'          => $metodo,
        'header'          => implode("\r\n", $cabeceras),
        'content'         => $cuerpo ?? '',
        'ignore_errors'   => true, // también queremos leer el cuerpo de los 4xx
        'follow_location' => 0,
        'timeout'         => 10,
    ]]);
    $respuesta = @file_get_contents($base . '/' . $ruta, false, $contexto);
    // PHP 8.4+ trae una función; en 8.1 se usa la variable mágica que llena file_get_contents
    $lineas = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?? [])
        : ($http_response_header ?? []);

    $codigo = 0;
    $tipo = '';
    foreach ($lineas as $linea) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
            $codigo = (int) $m[1];
        } elseif (stripos($linea, 'Content-Type:') === 0) {
            $tipo = trim(substr($linea, 13));
        } elseif (stripos($linea, 'Set-Cookie:') === 0 && preg_match('#^Set-Cookie:\s*([^=]+)=([^;]*)#i', $linea, $m)) {
            $cookies[$m[1]] = $m[2];
        }
    }
    $texto = $respuesta === false ? '' : $respuesta;
    $json = json_decode($texto, true);
    return ['codigo' => $codigo, 'tipo' => $tipo, 'cuerpo' => $texto, 'json' => is_array($json) ? $json : null];
}

/** Inicia sesión por el formulario de login y devuelve las cookies de esa sesión. */
function iniciar_sesion_http(string $email): array
{
    $cookies = [];
    $login = peticion($cookies, 'GET', 'login.php');
    preg_match('#name="csrf" value="([0-9a-f]+)"#', $login['cuerpo'], $m);
    $formulario = http_build_query(['csrf' => $m[1] ?? '', 'email' => $email, 'clave' => '123456']);
    peticion($cookies, 'POST', 'login.php', $formulario, ['Content-Type: application/x-www-form-urlencoded']);
    return $cookies;
}

/** Token CSRF de la sesión, leído de la etiqueta <meta name="csrf-token"> de una página. */
function token_de_pagina(array &$cookies, string $ruta): string
{
    $pagina = peticion($cookies, 'GET', $ruta);
    preg_match('#name="csrf-token" content="([0-9a-f]+)"#', $pagina['cuerpo'], $m);
    return $m[1] ?? '';
}

/** POST JSON a la API con (o sin) token CSRF. */
function post_api(array &$cookies, string $cuerpo, ?string $token): array
{
    global $api;
    $cabeceras = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== null) {
        $cabeceras[] = 'X-CSRF-Token: ' . $token;
    }
    return peticion($cookies, 'POST', $api, $cuerpo, $cabeceras);
}

/** true si la respuesta es JSON con ok:false (forma de todos los errores de la API). */
function error_json(array $r): bool
{
    return str_contains($r['tipo'], 'application/json') && ($r['json']['ok'] ?? null) === false;
}

/** Estado actual de un pedido en la BD. */
function estado_en_bd(int $id): string
{
    $st = conectar()->prepare('SELECT status FROM orders WHERE id = ?');
    $st->execute([$id]);
    return (string) $st->fetchColumn();
}

// Pedido propio para la prueba; se borra al final aunque algo falle a mitad de camino
$pdo = conectar();
$max_previo = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();
register_shutdown_function(function () use ($max_previo) {
    conectar()->prepare('DELETE FROM orders WHERE id > ?')->execute([$max_previo]);
});
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute(['cliente@ebano.com']);
crear_pedidos((int) $st->fetchColumn(), [['dish_id' => 1, 'quantity' => 1]]);
$id = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();

// Sin sesión
$anonimo = [];
$r = peticion($anonimo, 'GET', $api);
comprobar('sin sesión: GET responde 401', $r['codigo'] === 401);
comprobar('sin sesión: el error viene en JSON', error_json($r));

// Chef: GET
$chef = iniciar_sesion_http('chef@ebano.com');
$token = token_de_pagina($chef, 'chef/cocina.php');
comprobar('el chef obtiene un token CSRF de la página', strlen($token) === 64);
$r = peticion($chef, 'GET', $api);
$ids = array_column($r['json']['pedidos'] ?? [], 'id');
comprobar('chef: GET responde 200 en JSON', $r['codigo'] === 200 && str_contains($r['tipo'], 'application/json'));
comprobar('chef: el GET incluye el pedido nuevo', in_array($id, $ids, true));

// POST: CSRF
$valido = json_encode(['id' => $id, 'estado' => 'Preparing']);
$r = post_api($chef, $valido, null);
comprobar('POST sin token: 403 en JSON', $r['codigo'] === 403 && error_json($r));
$r = post_api($chef, $valido, str_repeat('0', 64));
comprobar('POST con token falso: 403 en JSON', $r['codigo'] === 403 && error_json($r));
comprobar('los POST rechazados no cambian el pedido', estado_en_bd($id) === 'Pending');

// POST: datos con forma incorrecta
$malos = [
    'id como texto "5"'  => '{"id":"' . $id . '","estado":"Preparing"}',
    'id decimal 5.0'     => '{"id":' . $id . '.0,"estado":"Preparing"}',
    'sin id'             => '{"estado":"Preparing"}',
    'id negativo'        => '{"id":-1,"estado":"Preparing"}',
    'estado desconocido' => '{"id":' . $id . ',"estado":"Quemado"}',
    'JSON roto'          => '{"id":',
];
foreach ($malos as $caso => $cuerpo) {
    $r = post_api($chef, $cuerpo, $token);
    comprobar('POST con ' . $caso . ': 400 en JSON', $r['codigo'] === 400 && error_json($r));
}
comprobar('los datos malos no cambian el pedido', estado_en_bd($id) === 'Pending');

// POST: transiciones
$r = post_api($chef, $valido, $token);
comprobar('Pending → Preparing: 200 con ok:true', $r['codigo'] === 200 && ($r['json']['ok'] ?? null) === true);
comprobar('el pedido queda Preparing en la BD', estado_en_bd($id) === 'Preparing');
$r = post_api($chef, $valido, $token);
comprobar('repetir Pending → Preparing: 422 en JSON', $r['codigo'] === 422 && error_json($r));
$r = post_api($chef, json_encode(['id' => $id, 'estado' => 'Served']), $token);
comprobar('el chef no puede pasar a Served: 422', $r['codigo'] === 422 && error_json($r));
comprobar('el pedido sigue Preparing', estado_en_bd($id) === 'Preparing');

// Otros métodos
$r = peticion($chef, 'PUT', $api, $valido, ['Content-Type: application/json', 'X-CSRF-Token: ' . $token]);
comprobar('PUT: 405 en JSON', $r['codigo'] === 405 && error_json($r));

// Cliente: no tiene acceso a la API
$cliente = iniciar_sesion_http('cliente@ebano.com');
$r = peticion($cliente, 'GET', $api);
comprobar('cliente: GET responde 403 en JSON', $r['codigo'] === 403 && error_json($r));
$token_cliente = token_de_pagina($cliente, 'cliente/menu.php');
$r = post_api($cliente, json_encode(['id' => $id, 'estado' => 'Ready to serve']), $token_cliente);
comprobar('cliente: POST con su token válido responde 403 en JSON', $r['codigo'] === 403 && error_json($r));
comprobar('el cliente no cambió el pedido', estado_en_bd($id) === 'Preparing');

exit($fallos > 0 ? 1 : 0);
