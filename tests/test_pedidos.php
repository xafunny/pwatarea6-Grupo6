<?php
/**
 * Prueba de includes/pedidos.php (se ejecuta por consola).
 * Uso: php tests/test_pedidos.php
 * Usa la BD real `restaurante`. Limpieza: antes de empezar se guarda el MAX(id) de orders y al final
 * se borran los pedidos con id mayor. No se usa rollback porque crear_pedidos() abre su propia transacción.
 */

$_SESSION = [];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

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

/** true si la función lanza InvalidArgumentException. */
function lanza(callable $f): bool
{
    try {
        $f();
        return false;
    } catch (InvalidArgumentException $ex) {
        return true;
    }
}

/** Cantidad actual de filas en orders. */
function contar_pedidos(): int
{
    return (int) conectar()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
}

/** Id de un usuario buscado por su correo (los ids del seed podrían cambiar). */
function id_por_email(string $email): int
{
    $st = conectar()->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    return (int) $st->fetchColumn();
}

/** Fila completa de un pedido (o [] si no existe). */
function fila_pedido(int $id): array
{
    $st = conectar()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: [];
}

$pdo = conectar();
$max_previo = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();
$cliente_id = id_por_email('cliente@ebano.com');
$chef = ['id' => id_por_email('chef@ebano.com'), 'role' => 'Chef'];
$plato_oculto = 2; // se oculta un momento para probar que no se puede pedir

// Limpieza garantizada aunque una comprobación lance una excepción a medio camino
register_shutdown_function(function () use ($max_previo, $plato_oculto) {
    conectar()->prepare('DELETE FROM orders WHERE id > ?')->execute([$max_previo]);
    conectar()->prepare('UPDATE dishes SET available = 1 WHERE id = ?')->execute([$plato_oculto]);
});

comprobar('el cliente y el chef de prueba existen', $cliente_id > 0 && $chef['id'] > 0);

// crear_pedidos(): caso válido
$insertados = crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 2]]);
comprobar('crear_pedidos con un plato devuelve 1', $insertados === 1);

$id_nuevo = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$fila = fila_pedido($id_nuevo);
comprobar('el pedido nuevo queda Pending', ($fila['status'] ?? '') === 'Pending');
comprobar('el pedido nuevo es del cliente', (int) ($fila['user_id'] ?? 0) === $cliente_id);
comprobar('el pedido nuevo guarda la cantidad', (int) ($fila['quantity'] ?? 0) === 2);
comprobar('el pedido del cliente no tiene camarero', array_key_exists('waiter_id', $fila) && $fila['waiter_id'] === null);

comprobar('crear_pedidos con dos platos devuelve 2',
    crear_pedidos($cliente_id, [['dish_id' => 3, 'quantity' => 1], ['dish_id' => 8, 'quantity' => 20]]) === 2);

// crear_pedidos(): entradas inválidas (lanzan y no insertan nada)
$antes = contar_pedidos();
comprobar('crear_pedidos con [] lanza', lanza(fn() => crear_pedidos($cliente_id, [])));
comprobar('crear_pedidos con cantidad 0 lanza', lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 0]])));
comprobar('crear_pedidos con cantidad 21 lanza', lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 21]])));
comprobar('crear_pedidos con plato 99999 lanza', lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => 99999, 'quantity' => 1]])));

$pdo->prepare('UPDATE dishes SET available = 0 WHERE id = ?')->execute([$plato_oculto]);
comprobar('crear_pedidos con un plato oculto lanza',
    lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => $plato_oculto, 'quantity' => 1]])));
$pdo->prepare('UPDATE dishes SET available = 1 WHERE id = ?')->execute([$plato_oculto]);

comprobar('un carrito mixto (válido + inválido) lanza',
    lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1], ['dish_id' => 99999, 'quantity' => 1]])));
$demasiados = array_fill(0, 31, ['dish_id' => 1, 'quantity' => 1]);
comprobar('crear_pedidos con más de 30 platos distintos lanza', lanza(fn() => crear_pedidos($cliente_id, $demasiados)));
comprobar('crear_pedidos con un plato repetido lanza',
    lanza(fn() => crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 15], ['dish_id' => 1, 'quantity' => 15]])));
comprobar('las entradas inválidas no cambian COUNT(*)', contar_pedidos() === $antes);

// hace_cuanto()
comprobar('hace_cuanto de 0 min', hace_cuanto(0, '2026-11-23 14:05:00') === 'hace un momento');
comprobar('hace_cuanto de 12 min', hace_cuanto(12, '2026-11-23 14:05:00') === 'hace 12 min');
comprobar('hace_cuanto del mismo día', hace_cuanto(90, '2026-11-23 14:05:00') === 'hoy, 14:05');
comprobar('hace_cuanto de otro día', hace_cuanto(3000, '2026-11-21 14:05:00') === '21/11, 14:05');

// texto_espera() y es_urgente(): lo que muestra cada comanda del chef
comprobar('texto_espera de 0 min', texto_espera(0) === 'Recién entrada');
comprobar('texto_espera de 7 min', texto_espera(7) === '7 min');
comprobar('texto_espera de 65 min', texto_espera(65) === '1 h 05 min');
comprobar('es urgente un Pending de 15 min', es_urgente('Pending', 15) === true);
comprobar('no es urgente un Preparing de 14 min', es_urgente('Preparing', 14) === false);
comprobar('no es urgente un Ready to serve de 40 min', es_urgente('Ready to serve', 40) === false);

// accion_chef() y datos_comanda()
comprobar('accion_chef de Pending es Empezar', (accion_chef('Pending')['texto'] ?? '') === 'Empezar');
comprobar('accion_chef de Preparing lleva a Ready to serve', (accion_chef('Preparing')['estado'] ?? '') === 'Ready to serve');
comprobar('accion_chef de Ready to serve es null', accion_chef('Ready to serve') === null);
$comanda = datos_comanda([
    'id' => 42, 'status' => 'Pending', 'quantity' => '2', 'created_at' => '2026-11-23 14:05:00',
    'minutos' => '20', 'plato' => 'Risotto', 'cliente' => 'ana', 'camarero' => null,
]);
comprobar('datos_comanda arma el número #0042', $comanda['numero'] === '#0042');
comprobar('datos_comanda da la hora H:i', $comanda['hora'] === '14:05');
comprobar('datos_comanda marca urgente a los 20 min', $comanda['urgente'] === true);
comprobar('datos_comanda traduce el estado', $comanda['estado_es'] === 'Pendiente' && $comanda['clase'] === 'pendiente');

// pedidos_de_cliente()
$mios = pedidos_de_cliente($cliente_id);
comprobar('pedidos_de_cliente trae el pedido nuevo primero', (int) ($mios[0]['id'] ?? 0) === $id_nuevo + 2);
comprobar('pedidos_de_cliente trae nombre y precio del plato', isset($mios[0]['name'], $mios[0]['price']));

// cambiar_estado()
comprobar('chef no puede pasar de Pending a Served', cambiar_estado($id_nuevo, 'Served', $chef) === false);
$estado = fila_pedido($id_nuevo)['status'];
comprobar('el estado sigue Pending', $estado === 'Pending');
comprobar('chef puede pasar de Pending a Preparing', cambiar_estado($id_nuevo, 'Preparing', $chef) === true);
$estado = fila_pedido($id_nuevo)['status'];
comprobar('el estado ahora es Preparing', $estado === 'Preparing');
comprobar('cambiar_estado de un pedido inexistente es false', cambiar_estado(99999999, 'Preparing', $chef) === false);

// Camarero: al servir un pedido sin camarero, queda asignado a él
$camarero = ['id' => id_por_email('camarero@ebano.com'), 'role' => 'Waiter'];
cambiar_estado($id_nuevo, 'Ready to serve', $chef);
comprobar('camarero puede pasar de Ready to serve a Served', cambiar_estado($id_nuevo, 'Served', $camarero) === true);
$asignado = (int) fila_pedido($id_nuevo)['waiter_id'];
comprobar('el pedido queda asignado al camarero', $asignado === $camarero['id']);

// pedidos_cocina(): solo los 3 estados de cocina, del más viejo al más nuevo
crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1]]); // garantiza al menos uno Pending
$cocina = pedidos_cocina();
$estados_cocina = ['Pending', 'Preparing', 'Ready to serve'];
$solo_cocina = true;
$hay_servido = false;
$ordenado = true;
foreach ($cocina as $i => $fila_cocina) {
    if (!in_array($fila_cocina['status'], $estados_cocina, true)) {
        $solo_cocina = false;
    }
    if ($fila_cocina['status'] === 'Served') {
        $hay_servido = true;
    }
    if ($i > 0 && $cocina[$i - 1]['created_at'] > $fila_cocina['created_at']) {
        $ordenado = false;
    }
}
comprobar('pedidos_cocina devuelve al menos un pedido', count($cocina) > 0);
comprobar('pedidos_cocina solo trae Pending, Preparing y Ready to serve', $solo_cocina);
comprobar('pedidos_cocina no incluye pedidos Served', !$hay_servido);
comprobar('pedidos_cocina ordena por created_at ascendente', $ordenado);
comprobar('pedidos_cocina trae plato, cliente, camarero y minutos',
    isset($cocina[0]) && array_key_exists('camarero', $cocina[0])
    && isset($cocina[0]['plato'], $cocina[0]['cliente'], $cocina[0]['minutos']));

exit($fallos > 0 ? 1 : 0);
