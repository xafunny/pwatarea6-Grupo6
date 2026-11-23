<?php
/**
 * API JSON del tablero de cocina (la consume assets/js/cocina.js con fetch).
 * - GET  → {"pedidos": [...], "servidor": "<hora ISO>"}: las comandas Pending, Preparing y Ready to serve.
 * - POST → cuerpo JSON {"id": int, "estado": string} + cabecera X-CSRF-Token.
 *          Responde {"ok": bool, "mensaje": string}: 200 si cambió, 400 si los datos no sirven,
 *          422 si el paso no está permitido o alguien cambió la comanda antes (carrera).
 * - Cualquier otro método → 405.
 * Acceso: solo Chef y Administrador. Sin sesión → 401; con otro rol → 403; token CSRF malo → 403.
 * TODAS las respuestas son JSON {ok, mensaje}, también los errores de acceso.
 * Por eso esta API hace sus propias comprobaciones en vez de usar requerir_rol() y verificar_csrf():
 * esas dos responden para un navegador (redirección al login, página 403 en HTML, texto plano)
 * y las demás páginas dependen de ese comportamiento, así que no se tocan.
 * Los datos van tal cual en el JSON; el escape lo hace cocina.js al pintar con textContent.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

/** Tamaño máximo del cuerpo del POST: {"id":123,"estado":"Ready to serve"} ocupa unos 40 bytes. */
const MAX_BYTES_CUERPO = 1024;
/** Roles que pueden usar esta API. */
const ROLES_API_COCINA = ['Chef', 'Administrator'];

/** Envía una respuesta JSON con su código HTTP y termina el script. */
function responder_json(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store'); // los pedidos cambian a cada rato: nada de caché

// Control de acceso (antes de leer o cambiar nada). usuario_actual() relee el rol de la BD.
$usuario = usuario_actual();
if ($usuario === null) {
    responder_json(401, ['ok' => false, 'mensaje' => 'Tu sesión terminó. Vuelve a iniciar sesión.']);
}
if (!in_array($usuario['role'], ROLES_API_COCINA, true)) {
    responder_json(403, ['ok' => false, 'mensaje' => 'Tu cuenta no tiene acceso a la cocina.']);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    try {
        $pedidos = array_map('datos_comanda', pedidos_cocina());
    } catch (Throwable $ex) {
        error_log('api_pedidos GET: ' . $ex->getMessage()); // el detalle queda en el log, no en la respuesta
        responder_json(500, ['pedidos' => [], 'mensaje' => 'No se pudieron cargar las comandas.']);
    }
    responder_json(200, ['pedidos' => $pedidos, 'servidor' => date(DATE_ATOM)]);
}

if ($metodo !== 'POST') {
    header('Allow: GET, POST');
    responder_json(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

// POST: antes que nada, el token CSRF de la cabecera X-CSRF-Token.
// hash_equals compara en tiempo constante (igual que verificar_csrf), pero aquí el 403 va en JSON.
$token_enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token_enviado) || !hash_equals(csrf_token(), $token_enviado)) {
    responder_json(403, ['ok' => false, 'mensaje' => 'Solicitud no válida (token CSRF incorrecto). Recarga la página.']);
}

// Validación del cuerpo: JSON pequeño, con un id entero positivo y un estado conocido
$cuerpo = file_get_contents('php://input', false, null, 0, MAX_BYTES_CUERPO + 1);
if ($cuerpo === false || strlen($cuerpo) > MAX_BYTES_CUERPO) {
    responder_json(400, ['ok' => false, 'mensaje' => 'La solicitud es demasiado grande.']);
}

$datos = json_decode($cuerpo, true, 4);
$id = is_array($datos) ? ($datos['id'] ?? null) : null;
$estado = is_array($datos) ? ($datos['estado'] ?? null) : null;

if (!is_int($id) || $id < 1 || !is_string($estado) || !in_array($estado, ESTADOS, true)) {
    responder_json(400, ['ok' => false, 'mensaje' => 'Datos no válidos: falta la comanda o el estado.']);
}

try {
    // cambiar_estado() aplica las reglas del rol y no pisa un cambio hecho por otra persona
    $cambio = cambiar_estado($id, $estado, $usuario);
} catch (Throwable $ex) {
    error_log('api_pedidos POST: ' . $ex->getMessage());
    responder_json(500, ['ok' => false, 'mensaje' => 'No se pudo guardar el cambio. Inténtalo de nuevo.']);
}

$numero = '#' . sprintf('%04d', $id);
if (!$cambio) {
    responder_json(422, [
        'ok'      => false,
        'mensaje' => 'La comanda ' . $numero . ' no se pudo mover: ya cambió de estado o ese paso no está permitido.',
    ]);
}

responder_json(200, ['ok' => true, 'mensaje' => 'Comanda ' . $numero . ' → ' . estado_es($estado) . '.']);
