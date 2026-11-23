<?php
/**
 * Lógica de pedidos (tabla orders).
 * Cada fila de orders es UN plato con su cantidad: un "pedido" del carrito con 3 platos
 * se guarda como 3 filas, todas en estado Pending.
 * Ofrece: crear pedidos (con validación y transacción), listar los pedidos de un cliente
 * y cambiar el estado de un pedido respetando la tabla de transiciones por rol.
 * Las Tasks del chef, el camarero y el admin agregan aquí sus propias consultas.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funciones.php'; // transicion_permitida()

/** Cantidad mínima y máxima de un mismo plato por pedido. */
const CANTIDAD_MINIMA = 1;
const CANTIDAD_MAXIMA = 20;
/** Tope de platos distintos en un mismo envío: corta formularios manipulados con miles de filas. */
const MAX_PLATOS_POR_PEDIDO = 30;
/** Clave de localStorage del carrito del cliente (la usan menu.php y mis_pedidos.php). */
const CLAVE_CARRITO_CLIENTE = 'ebano_carrito_cliente';

/**
 * Crea un pedido por cada plato del carrito y devuelve cuántas filas insertó.
 * $items: [['dish_id' => int, 'quantity' => int], ...]
 * $camarero_id: cuando un camarero toma el pedido en sala queda asignado a él; el cliente pasa null.
 * Lanza InvalidArgumentException (con un mensaje para el usuario) si el carrito no es válido.
 * Primero se valida TODO y recién después se escribe: un carrito con un solo plato malo no inserta nada.
 */
function crear_pedidos(int $cliente_id, array $items, ?int $camarero_id = null): int
{
    if (count($items) === 0) {
        throw new InvalidArgumentException('Tu pedido está vacío. Añade al menos un plato.');
    }
    if (count($items) > MAX_PLATOS_POR_PEDIDO) {
        throw new InvalidArgumentException('Un pedido admite hasta ' . MAX_PLATOS_POR_PEDIDO . ' platos distintos.');
    }

    $pdo = conectar();
    $buscar_plato = $pdo->prepare('SELECT name, available FROM dishes WHERE id = ?');

    // 1) Validación: cantidades en rango y platos que existen y están disponibles
    foreach ($items as $item) {
        $plato_id = (int) ($item['dish_id'] ?? 0);
        $cantidad = (int) ($item['quantity'] ?? 0);

        if ($cantidad < CANTIDAD_MINIMA || $cantidad > CANTIDAD_MAXIMA) {
            throw new InvalidArgumentException(
                'La cantidad de cada plato debe estar entre ' . CANTIDAD_MINIMA . ' y ' . CANTIDAD_MAXIMA . '.'
            );
        }

        $buscar_plato->execute([$plato_id]);
        $plato = $buscar_plato->fetch();
        if (!$plato) {
            throw new InvalidArgumentException('Uno de los platos ya no existe en la carta.');
        }
        if ((int) $plato['available'] !== 1) {
            throw new InvalidArgumentException('"' . $plato['name'] . '" ya no está disponible hoy. Quítalo del pedido.');
        }
    }

    // 2) Escritura: todas las filas o ninguna (transacción)
    $insertar = $pdo->prepare(
        "INSERT INTO orders (user_id, dish_id, quantity, status, waiter_id) VALUES (?, ?, ?, 'Pending', ?)"
    );
    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $insertar->execute([$cliente_id, (int) $item['dish_id'], (int) $item['quantity'], $camarero_id]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack(); // si falla una fila se deshacen las anteriores
        throw $ex;
    }

    return count($items);
}

/**
 * Devuelve los pedidos de un cliente, del más nuevo al más viejo,
 * con el nombre, precio e imagen del plato (JOIN con dishes).
 * 'minutos' = minutos transcurridos; lo calcula MySQL con su propio reloj (el mismo que llenó
 * created_at), así no importa si PHP y MySQL tienen zonas horarias distintas.
 */
function pedidos_de_cliente(int $cliente_id): array
{
    $st = conectar()->prepare(
        'SELECT o.id, o.dish_id, o.quantity, o.status, o.created_at,
                TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS minutos,
                d.name, d.price, d.image
         FROM orders o
         JOIN dishes d ON d.id = o.dish_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC, o.id DESC'
    );
    $st->execute([$cliente_id]);
    return $st->fetchAll();
}

/**
 * Texto corto de cuándo se hizo un pedido: "hace un momento", "hace 12 min",
 * "hoy, 14:05" o "21/11, 14:05". $fecha es created_at tal como viene de la BD.
 */
function hace_cuanto(int $minutos, string $fecha): string
{
    if ($minutos < 1) {
        return 'hace un momento';
    }
    if ($minutos < 60) {
        return 'hace ' . $minutos . ' min';
    }
    $momento = strtotime($fecha);
    // "Ahora" según la BD = creación + minutos; si cae el mismo día, el pedido es de hoy
    $es_hoy = date('Y-m-d', $momento) === date('Y-m-d', $momento + $minutos * 60);
    return ($es_hoy ? 'hoy, ' : date('d/m', $momento) . ', ') . date('H:i', $momento);
}

/**
 * Cambia el estado de un pedido si el rol del usuario lo permite (transicion_permitida()).
 * $usuario: ['id' => int, 'role' => string], normalmente el de usuario_actual().
 * Devuelve true solo si el pedido realmente cambió.
 */
function cambiar_estado(int $pedido_id, string $nuevo, array $usuario): bool
{
    $pdo = conectar();
    $st = $pdo->prepare('SELECT status, waiter_id FROM orders WHERE id = ?');
    $st->execute([$pedido_id]);
    $pedido = $st->fetch();

    if (!$pedido || !transicion_permitida($usuario['role'], $pedido['status'], $nuevo)) {
        return false;
    }

    // "AND status = ?": si otra persona cambió el pedido entre la lectura y este UPDATE,
    // no se pisa su cambio (la consulta no afecta ninguna fila y se devuelve false)
    if ($usuario['role'] === 'Waiter' && $pedido['waiter_id'] === null) {
        // El camarero que atiende un pedido sin asignar se lo queda
        $up = $pdo->prepare('UPDATE orders SET status = ?, waiter_id = ? WHERE id = ? AND status = ?');
        $up->execute([$nuevo, (int) $usuario['id'], $pedido_id, $pedido['status']]);
    } else {
        $up = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
        $up->execute([$nuevo, $pedido_id, $pedido['status']]);
    }

    return $up->rowCount() === 1;
}
