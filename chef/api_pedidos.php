<?php
/**
 * API JSON del tablero de cocina (la consume assets/js/cocina.js con fetch).
 * - GET  → {"pedidos": [...], "servidor": "<hora ISO>"}: las comandas Pending, Preparing y Ready to serve.
 * - POST → cuerpo JSON {"id": int, "estado": string} + cabecera X-CSRF-Token.
 *          Responde {"ok": bool, "mensaje": string}: 200 si cambió, 400 si los datos no sirven,
 *          422 si el paso no está permitido o alguien cambió la comanda antes (carrera).
 * - Cualquier otro método → 405.
 * Acceso: solo Chef y Administrador. Sin sesión, requerir_rol() redirige a login (302).
 * Los datos van tal cual en el JSON; el escape lo hace cocina.js al pintar con textContent.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

// Primero el control de acceso: si falla, se corta aquí con su propia respuesta
$usuario = requerir_rol(['Chef', 'Administrator']);

/** Tamaño máximo del cuerpo del POST: {"id":123,"estado":"Ready to serve"} ocupa unos 40 bytes. */
const MAX_BYTES_CUERPO = 1024;

/** Envía una respuesta JSON con su código HTTP y termina el script. */
function responder_json(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store'); // los pedidos cambian a cada rato: nada de caché

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

// POST: antes que nada, el token CSRF de la cabecera X-CSRF-Token (si falla, 403 y termina)
verificar_csrf();

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
