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
// Se guarda su disponibilidad original para restaurarla tal cual (no se asume que era 1)
$st_original = $pdo->prepare('SELECT available FROM dishes WHERE id = ?');
$st_original->execute([$plato_oculto]);
$disponible_original = (int) $st_original->fetchColumn();

// Limpieza garantizada aunque una comprobación lance una excepción a medio camino
register_shutdown_function(function () use ($max_previo, $plato_oculto, $disponible_original) {
    conectar()->prepare('DELETE FROM orders WHERE id > ?')->execute([$max_previo]);
    conectar()->prepare('UPDATE dishes SET available = ? WHERE id = ?')->execute([$disponible_original, $plato_oculto]);
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
$pdo->prepare('UPDATE dishes SET available = ? WHERE id = ?')->execute([$disponible_original, $plato_oculto]);

comprobar('validar antes de escribir: un carrito mixto (válido + inválido) lanza',
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
$hay_empate = false; // los 2 platos creados juntos arriba comparten created_at: prueban el desempate
foreach ($cocina as $i => $fila_cocina) {
    if ($i > 0 && $cocina[$i - 1]['created_at'] === $fila_cocina['created_at']) {
        $hay_empate = true;
    }
    if (!in_array($fila_cocina['status'], $estados_cocina, true)) {
        $solo_cocina = false;
    }
    if ($fila_cocina['status'] === 'Served') {
        $hay_servido = true;
    }
    if ($i > 0) {
        $anterior = $cocina[$i - 1];
        // Más viejo primero; si se crearon en el mismo segundo, desempata el id menor
        $mismo_momento = $anterior['created_at'] === $fila_cocina['created_at'];
        if ($anterior['created_at'] > $fila_cocina['created_at']
            || ($mismo_momento && (int) $anterior['id'] > (int) $fila_cocina['id'])) {
            $ordenado = false;
        }
    }
}
comprobar('pedidos_cocina devuelve al menos un pedido', count($cocina) > 0);
comprobar('pedidos_cocina solo trae Pending, Preparing y Ready to serve', $solo_cocina);
comprobar('pedidos_cocina no incluye pedidos Served', !$hay_servido);
comprobar('pedidos_cocina tiene pedidos con el mismo created_at (caso de desempate)', $hay_empate);
comprobar('pedidos_cocina ordena por created_at ascendente y desempata por id', $ordenado);
comprobar('pedidos_cocina trae plato, cliente, camarero y minutos',
    isset($cocina[0]) && array_key_exists('camarero', $cocina[0])
    && isset($cocina[0]['plato'], $cocina[0]['cliente'], $cocina[0]['minutos']));

// items_desde_post(): convierte items[id]=cantidad del formulario en la lista para crear_pedidos()
comprobar('items_desde_post convierte id y cantidad a int',
    items_desde_post(['4' => '2', '7' => '1']) === [['dish_id' => 4, 'quantity' => 2], ['dish_id' => 7, 'quantity' => 1]]);
comprobar('items_desde_post con algo que no es arreglo devuelve []', items_desde_post('x') === [] && items_desde_post(null) === []);
comprobar('items_desde_post deja en 0 una cantidad que es arreglo',
    items_desde_post(['4' => ['9']]) === [['dish_id' => 4, 'quantity' => 0]]);

// ---- Camarero: pedidos a su nombre, sin asignar y listos para servir ----
$otro_camarero = ['id' => id_por_email('chef@ebano.com'), 'role' => 'Waiter']; // solo hace falta otro id

// crear_pedidos() con $camarero_id guarda ese camarero (el pedido tomado en sala)
crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1]], $camarero['id']);
$id_en_sala = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
comprobar('crear_pedidos con $camarero_id guarda ese waiter_id',
    (int) (fila_pedido($id_en_sala)['waiter_id'] ?? 0) === $camarero['id']);

// Un pedido nuevo sin camarero: tiene que aparecer en pedidos_sin_asignar()
crear_pedidos($cliente_id, [['dish_id' => 3, 'quantity' => 2]]);
$id_libre = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();

$sin_asignar = pedidos_sin_asignar();
$solo_libres = true;
foreach ($sin_asignar as $fila_libre) {
    if ($fila_libre['camarero_id'] !== null || $fila_libre['status'] === 'Served') {
        $solo_libres = false;
    }
}
comprobar('pedidos_sin_asignar solo trae waiter_id NULL y no Served', $solo_libres);
comprobar('pedidos_sin_asignar incluye el pedido libre', in_array($id_libre, array_map('intval', array_column($sin_asignar, 'id')), true));
comprobar('pedidos_sin_asignar trae plato, cliente y minutos',
    isset($sin_asignar[0]['plato'], $sin_asignar[0]['cliente'], $sin_asignar[0]['minutos']));
$asc = true;
foreach ($sin_asignar as $i => $fila_libre) {
    if ($i > 0 && $sin_asignar[$i - 1]['created_at'] > $fila_libre['created_at']) {
        $asc = false;
    }
}
comprobar('pedidos_sin_asignar va del más viejo al más nuevo', $asc);

// pedidos_listos(): se preparan 2 pedidos 'Ready to serve' conocidos (uno de hace 1 hora y otro de ahora)
// para que la prueba nunca quede vacía. Son filas nuevas: la limpieza final (id > max_previo) las borra.
crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1]], $camarero['id']);
$id_listo_viejo = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$pdo->prepare("UPDATE orders SET status = 'Ready to serve', created_at = NOW() - INTERVAL 1 HOUR WHERE id = ?")
    ->execute([$id_listo_viejo]);
crear_pedidos($cliente_id, [['dish_id' => 3, 'quantity' => 1]]);
$id_listo_nuevo = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$pdo->prepare("UPDATE orders SET status = 'Ready to serve' WHERE id = ?")->execute([$id_listo_nuevo]);

$listos = pedidos_listos();
$ids_listos = array_map('intval', array_column($listos, 'id'));
$solo_listos = true;
$listos_ascendente = true;
foreach ($listos as $i => $fila_lista) {
    if ($fila_lista['status'] !== 'Ready to serve') {
        $solo_listos = false;
    }
    if ($i > 0) {
        $anterior = $listos[$i - 1];
        $mismo_momento = $anterior['created_at'] === $fila_lista['created_at'];
        if ($anterior['created_at'] > $fila_lista['created_at']
            || ($mismo_momento && (int) $anterior['id'] > (int) $fila_lista['id'])) {
            $listos_ascendente = false;
        }
    }
}
comprobar('pedidos_listos incluye los 2 pedidos listos preparados',
    in_array($id_listo_viejo, $ids_listos, true) && in_array($id_listo_nuevo, $ids_listos, true));
comprobar('pedidos_listos solo trae Ready to serve', $solo_listos);
comprobar('pedidos_listos va del más viejo al más nuevo', $listos_ascendente);
// array_search devuelve false si no encuentra el id: se comprueba antes de usarlo como índice
$pos_viejo = array_search($id_listo_viejo, $ids_listos, true);
$pos_nuevo = array_search($id_listo_nuevo, $ids_listos, true);
comprobar('pedidos_listos pone el de hace 1 hora antes que el de ahora',
    $pos_viejo !== false && $pos_nuevo !== false && $pos_viejo < $pos_nuevo);
$fila_vieja = $pos_viejo !== false ? $listos[$pos_viejo] : [];
$fila_nueva = $pos_nuevo !== false ? $listos[$pos_nuevo] : [];
comprobar('pedidos_listos trae el camarero del pedido asignado',
    ($fila_vieja['camarero'] ?? null) !== null && (int) ($fila_vieja['camarero_id'] ?? 0) === $camarero['id']);
comprobar('pedidos_listos trae camarero null en el pedido sin asignar',
    array_key_exists('camarero', $fila_nueva) && $fila_nueva['camarero'] === null);

// Un entregado de hace 2 días de este camarero: NO debe aparecer en pedidos_de_camarero()
// (solo se muestran los entregados de pedidos creados hoy)
crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1]], $camarero['id']);
$id_servido_viejo = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$pdo->prepare("UPDATE orders SET status = 'Served', created_at = NOW() - INTERVAL 2 DAY WHERE id = ?")
    ->execute([$id_servido_viejo]);
// Y uno entregado creado hoy: SÍ debe aparecer
crear_pedidos($cliente_id, [['dish_id' => 3, 'quantity' => 1]], $camarero['id']);
$id_servido_hoy = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$pdo->prepare("UPDATE orders SET status = 'Served' WHERE id = ?")->execute([$id_servido_hoy]);

// tomar_pedido(): solo el primero lo consigue
comprobar('tomar_pedido la primera vez es true', tomar_pedido($id_libre, $camarero['id']) === true);
comprobar('tomar_pedido deja el waiter_id del camarero', (int) fila_pedido($id_libre)['waiter_id'] === $camarero['id']);
comprobar('tomar_pedido la segunda vez es false', tomar_pedido($id_libre, $camarero['id']) === false);
comprobar('tomar_pedido de otro camarero es false', tomar_pedido($id_libre, $otro_camarero['id']) === false);
comprobar('el pedido sigue siendo del primer camarero', (int) fila_pedido($id_libre)['waiter_id'] === $camarero['id']);
comprobar('tomar_pedido de un pedido inexistente es false', tomar_pedido(99999999, $camarero['id']) === false);
// Un entregado sin camarero (como el que sirvió el admin) no se puede tomar
crear_pedidos($cliente_id, [['dish_id' => 1, 'quantity' => 1]]);
$id_servido_libre = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$pdo->prepare("UPDATE orders SET status = 'Served' WHERE id = ?")->execute([$id_servido_libre]);
comprobar('tomar_pedido de un pedido Served es false', tomar_pedido($id_servido_libre, $camarero['id']) === false);

// pedidos_de_camarero(): incluye lo tomado; los activos van antes que los entregados
$mios_camarero = pedidos_de_camarero($camarero['id']);
$ids_camarero = array_map('intval', array_column($mios_camarero, 'id'));
comprobar('pedidos_de_camarero contiene el pedido tomado', in_array($id_libre, $ids_camarero, true));
comprobar('pedidos_de_camarero contiene el pedido creado en sala', in_array($id_en_sala, $ids_camarero, true));
comprobar('pedidos_de_camarero incluye un entregado creado hoy', in_array($id_servido_hoy, $ids_camarero, true));
comprobar('pedidos_de_camarero excluye un entregado creado hace 2 días', !in_array($id_servido_viejo, $ids_camarero, true));
$visto_servido = false;
$activos_primero = true;
$solo_suyos = true;
foreach ($mios_camarero as $fila_mia) {
    if ($fila_mia['status'] === 'Served') {
        $visto_servido = true;
    } elseif ($visto_servido) {
        $activos_primero = false; // un activo después de un entregado
    }
    if ((int) $fila_mia['camarero_id'] !== $camarero['id']) {
        $solo_suyos = false;
    }
}
comprobar('pedidos_de_camarero pone los activos primero', $activos_primero);
comprobar('pedidos_de_camarero solo trae pedidos del camarero', $solo_suyos);
comprobar('pedidos_de_camarero trae plato, cliente y minutos',
    isset($mios_camarero[0]['plato'], $mios_camarero[0]['cliente'], $mios_camarero[0]['minutos']));

// ---- Administrador: estadísticas, listado con filtros y eliminar ----
$stats = estadisticas_admin();
comprobar('estadisticas_admin trae las 6 claves',
    array_keys($stats) === ['pedidos_hoy', 'en_cocina', 'ingresos', 'platos_activos', 'por_estado', 'top_platos']);
comprobar('estadisticas_admin: contadores enteros e ingresos float',
    is_int($stats['pedidos_hoy']) && is_int($stats['en_cocina']) && is_int($stats['platos_activos']) && is_float($stats['ingresos']));
comprobar('estadisticas_admin: por_estado tiene los 4 estados en orden', array_keys($stats['por_estado']) === ESTADOS);
comprobar('estadisticas_admin: en_cocina = Pending + Preparing',
    $stats['en_cocina'] === $stats['por_estado']['Pending'] + $stats['por_estado']['Preparing']);
comprobar('estadisticas_admin: por_estado suma el total de pedidos', array_sum($stats['por_estado']) === contar_pedidos());
comprobar('estadisticas_admin: hay pedidos de hoy (los de esta prueba)', $stats['pedidos_hoy'] > 0);
comprobar('estadisticas_admin: top_platos tiene hasta 5 con nombre, unidades e imagen',
    count($stats['top_platos']) > 0 && count($stats['top_platos']) <= 5
    && array_key_exists('imagen', $stats['top_platos'][0]) && isset($stats['top_platos'][0]['nombre'])
    && is_int($stats['top_platos'][0]['unidades']));
$unidades = array_column($stats['top_platos'], 'unidades');
$ordenadas = $unidades;
rsort($ordenadas);
comprobar('estadisticas_admin: top_platos va de más a menos unidades', $unidades === $ordenadas);

$todos = todos_los_pedidos();
comprobar('todos_los_pedidos trae plato, cliente, email, camarero y subtotal',
    isset($todos[0]['plato'], $todos[0]['cliente'], $todos[0]['email'], $todos[0]['subtotal']) && array_key_exists('camarero', $todos[0]));
comprobar('todos_los_pedidos va del más nuevo al más viejo',
    count($todos) < 2 || $todos[0]['created_at'] >= $todos[count($todos) - 1]['created_at']);
comprobar('todos_los_pedidos con 200 como máximo', count($todos) <= 200);
$solo_listos_admin = todos_los_pedidos('Ready to serve');
comprobar('todos_los_pedidos filtra por estado',
    count($solo_listos_admin) > 0 && count(array_unique(array_column($solo_listos_admin, 'status'))) === 1
    && $solo_listos_admin[0]['status'] === 'Ready to serve');
comprobar('todos_los_pedidos ignora un estado inválido', count(todos_los_pedidos('Borrado')) === count($todos));
$por_email = todos_los_pedidos(null, 'cliente@ebano');
comprobar('todos_los_pedidos busca por email del cliente',
    count($por_email) > 0 && in_array($id_libre, array_map('intval', array_column($por_email, 'id')), true));
$nombre_plato_1 = (string) $pdo->query('SELECT name FROM dishes WHERE id = 1')->fetchColumn();
$por_plato = todos_los_pedidos(null, $nombre_plato_1);
comprobar('todos_los_pedidos busca por nombre del plato',
    count($por_plato) > 0 && count(array_unique(array_column($por_plato, 'plato'))) === 1);
comprobar('todos_los_pedidos combina estado y búsqueda',
    count(todos_los_pedidos('Served', 'cliente@ebano')) <= count($por_email));
comprobar('buscar "%" no coincide con todo (se escapa el comodín)', count(todos_los_pedidos(null, '%')) === 0);
comprobar('buscar "_" no coincide con todo (se escapa el comodín)', count(todos_los_pedidos(null, '_')) === 0);
comprobar('buscar "!" (el carácter de escape) no rompe la consulta', count(todos_los_pedidos(null, '!')) === 0);

comprobar('eliminar_pedido de un pedido existente es true', eliminar_pedido($id_servido_viejo) === true);
comprobar('el pedido eliminado ya no existe', fila_pedido($id_servido_viejo) === []);
comprobar('eliminar_pedido de un id inexistente es false', eliminar_pedido(99999999) === false);

// El admin puede llevar un pedido a cualquier estado (incluso hacia atrás)
$admin = ['id' => id_por_email('admin@ebano.com'), 'role' => 'Administrator'];
comprobar('el admin puede pasar de Served a Pending', cambiar_estado($id_servido_hoy, 'Pending', $admin) === true);

exit($fallos > 0 ? 1 : 0);
