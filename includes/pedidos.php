<?php
/**
 * Lógica de pedidos (tabla orders).
 * Cada fila de orders es UN plato con su cantidad: un "pedido" del carrito con 3 platos
 * se guarda como 3 filas, todas en estado Pending.
 * Ofrece: leer el carrito del formulario (items_desde_post), crear pedidos (con validación y transacción), listar los pedidos de un cliente,
 * listar las comandas de la cocina (pedidos_cocina) y cambiar el estado de un pedido respetando la tabla de transiciones por rol.
 * Para el camarero: sus pedidos, los sin asignar, los listos para servir y tomar_pedido().
 * Para el administrador: estadisticas_admin() (sala de control), todos_los_pedidos() con filtros
 * y eliminar_pedido().
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funciones.php'; // transicion_permitida()

/** Cantidad mínima y máxima de un mismo plato por pedido. */
const CANTIDAD_MINIMA = 1;
const CANTIDAD_MAXIMA = 20;
/** Tope de platos distintos en un mismo envío: corta formularios manipulados con miles de filas. */
const MAX_PLATOS_POR_PEDIDO = 30;
/** Minutos de espera a partir de los cuales una comanda de cocina se marca como urgente. */
const MINUTOS_URGENTE = 15;
/** Prefijo de la clave de localStorage del carrito del cliente (la usan menu.php y mis_pedidos.php). */
const CLAVE_CARRITO_CLIENTE = 'ebano_carrito_cliente';
/** Prefijo del carrito de quien toma el pedido en sala (camarero o administrador): distinto del del cliente. */
const CLAVE_CARRITO_CAMARERO = 'ebano_carrito_camarero';

/**
 * Clave final de localStorage de un carrito: el prefijo más el id del usuario ('ebano_carrito_cliente_4').
 * localStorage es del navegador, no de la cuenta: sin el id, el carrito de una persona
 * le aparecería a la siguiente que inicie sesión en el mismo equipo.
 */
function clave_carrito(string $prefijo, int $usuario_id): string
{
    return $prefijo . '_' . $usuario_id;
}

/**
 * Convierte los campos items[id_plato] = cantidad que manda carrito.js en la lista que espera crear_pedidos().
 * Solo se aceptan valores simples: si llega un arreglo dentro de otro, la cantidad queda en 0
 * y crear_pedidos() rechaza el pedido. La usan el menú del cliente y el "nuevo pedido" del camarero.
 */
function items_desde_post(mixed $crudos): array
{
    if (!is_array($crudos)) {
        return [];
    }
    $items = [];
    foreach ($crudos as $plato_id => $cantidad) {
        $items[] = [
            'dish_id'  => (int) $plato_id,
            'quantity' => is_scalar($cantidad) ? (int) $cantidad : 0,
        ];
    }
    return $items;
}

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
    // La disponibilidad se comprueba fuera de la transacción a propósito: si el chef oculta un plato justo
    // entre la validación y el INSERT, la ventana es de milisegundos y el costo (un plato recién agotado) es mínimo.
    $buscar_plato = $pdo->prepare('SELECT name, available FROM dishes WHERE id = ?');

    // 1) Validación: sin platos repetidos, cantidades en rango y platos que existen y están disponibles
    $vistos = []; // ids ya revisados: [id => true]
    foreach ($items as $item) {
        $plato_id = (int) ($item['dish_id'] ?? 0);
        $cantidad = (int) ($item['quantity'] ?? 0);

        // Un mismo plato en dos filas saltaría el tope de 20 (ej: items[1]=20&items[01]=20 llegan como ids distintos)
        if (isset($vistos[$plato_id])) {
            throw new InvalidArgumentException('Un plato aparece repetido en el pedido.');
        }
        $vistos[$plato_id] = true;

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
 * Comandas que la cocina tiene que ver: Pending, Preparing y Ready to serve (las Served ya salieron).
 * Van del más viejo al más nuevo, porque en cocina se atiende primero lo que lleva más tiempo esperando.
 * Trae el nombre del plato ('plato'), el usuario del cliente ('cliente') y el del camarero ('camarero',
 * con LEFT JOIN porque los pedidos hechos por el cliente no tienen camarero y llega null).
 */
function pedidos_cocina(): array
{
    $st = conectar()->prepare(
        "SELECT o.id, o.status, o.quantity, o.created_at,
                TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS minutos,
                d.name AS plato,
                c.username AS cliente,
                w.username AS camarero
         FROM orders o
         JOIN dishes d ON d.id = o.dish_id
         JOIN users c ON c.id = o.user_id
         LEFT JOIN users w ON w.id = o.waiter_id
         WHERE o.status IN ('Pending', 'Preparing', 'Ready to serve')
         ORDER BY o.created_at ASC, o.id ASC"
    );
    $st->execute();
    return $st->fetchAll();
}

/**
 * Tiempo que lleva esperando una comanda, para el ticket del chef:
 * 0 -> 'Recién entrada', 7 -> '7 min', 65 -> '1 h 05 min'.
 * La API manda este texto ya armado (campo 'espera'), así cocina.js no repite la regla.
 */
function texto_espera(int $minutos): string
{
    if ($minutos < 1) {
        return 'Recién entrada';
    }
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    return intdiv($minutos, 60) . ' h ' . sprintf('%02d', $minutos % 60) . ' min';
}

/**
 * Una comanda es urgente si lleva MINUTOS_URGENTE o más esperando y la cocina aún no la terminó.
 * Las que están listas para servir ya no dependen del chef, así que no se marcan.
 */
function es_urgente(string $estado, int $minutos): bool
{
    return in_array($estado, ['Pending', 'Preparing'], true) && $minutos >= MINUTOS_URGENTE;
}

/**
 * Botón que ve el chef en cada comanda: a qué estado la lleva y con qué texto.
 * Las listas para servir devuelven null: desde ahí las mueve el camarero.
 */
function accion_chef(string $estado): ?array
{
    $acciones = [
        'Pending'   => ['estado' => 'Preparing', 'texto' => 'Empezar'],
        'Preparing' => ['estado' => 'Ready to serve', 'texto' => 'Marcar listo'],
    ];
    return $acciones[$estado] ?? null;
}

/**
 * Convierte una fila de pedidos_cocina() en los datos de un ticket de cocina.
 * La usan cocina.php (primer pintado en PHP) y api_pedidos.php (JSON para cocina.js),
 * así los dos dibujan exactamente lo mismo. Son DATOS: el escape se hace al imprimir.
 */
function datos_comanda(array $fila): array
{
    $minutos = (int) $fila['minutos'];
    return [
        'id'        => (int) $fila['id'],
        'numero'    => '#' . sprintf('%04d', (int) $fila['id']), // número de comanda: #0042
        'plato'     => $fila['plato'],
        'cliente'   => $fila['cliente'],
        'camarero'  => $fila['camarero'], // null si nadie lo tomó todavía
        'cantidad'  => (int) $fila['quantity'],
        'estado'    => $fila['status'],
        'estado_es' => estado_es($fila['status']),
        'clase'     => clase_estado($fila['status']),
        'minutos'   => $minutos,
        'espera'    => texto_espera($minutos),
        'urgente'   => es_urgente($fila['status'], $minutos),
        'hora'      => date('H:i', strtotime($fila['created_at'])),
        'fecha_iso' => str_replace(' ', 'T', $fila['created_at']),
        'accion'    => accion_chef($fila['status']),
    ];
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
 * Consulta base del panel del camarero: cada pedido con el nombre del plato ('plato'),
 * el usuario del cliente ('cliente'), el id y el usuario del camarero ('camarero_id', 'camarero';
 * null si nadie lo tomó) y los minutos de espera calculados por MySQL ('minutos').
 * $condicion y $orden son SIEMPRE texto fijo escrito en este archivo (nunca datos del usuario);
 * los valores variables viajan aparte en $parametros, como en cualquier sentencia preparada.
 */
function consultar_pedidos_sala(string $condicion, string $orden, array $parametros = []): array
{
    $st = conectar()->prepare(
        "SELECT o.id, o.status, o.quantity, o.created_at,
                TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS minutos,
                o.waiter_id AS camarero_id,
                d.name AS plato,
                c.username AS cliente,
                w.username AS camarero
         FROM orders o
         JOIN dishes d ON d.id = o.dish_id
         JOIN users c ON c.id = o.user_id
         LEFT JOIN users w ON w.id = o.waiter_id
         WHERE $condicion
         ORDER BY $orden"
    );
    $st->execute($parametros);
    return $st->fetchAll();
}

/**
 * Pedidos asignados a un camarero: primero los que siguen en curso y después los entregados,
 * cada grupo del más nuevo al más viejo. De los entregados solo se muestran los pedidos creados hoy
 * que ya fueron entregados, para que la lista no crezca sin fin.
 * Se filtra por created_at porque la tabla orders no guarda la fecha de entrega.
 */
function pedidos_de_camarero(int $camarero_id): array
{
    return consultar_pedidos_sala(
        // "Hoy" lo calcula PHP (zona America/Guayaquil) y viaja como parámetro: una sola fuente de la fecha
        "o.waiter_id = ? AND (o.status <> 'Served' OR DATE(o.created_at) = ?)",
        // (o.status = 'Served') vale 0 o 1: los activos (0) quedan arriba
        "(o.status = 'Served') ASC, o.created_at DESC, o.id DESC",
        [$camarero_id, date('Y-m-d')]
    );
}

/** Pedidos que nadie atiende todavía (sin camarero y sin entregar), del más viejo al más nuevo. */
function pedidos_sin_asignar(): array
{
    return consultar_pedidos_sala(
        "o.waiter_id IS NULL AND o.status <> 'Served'",
        'o.created_at ASC, o.id ASC'
    );
}

/** Pedidos que la cocina ya terminó y esperan en el pase, del que más espera al más reciente. */
function pedidos_listos(): array
{
    return consultar_pedidos_sala(
        "o.status = 'Ready to serve'",
        'o.created_at ASC, o.id ASC'
    );
}

/**
 * El camarero se queda con un pedido sin asignar. Devuelve true solo si lo consiguió.
 * Todo va en un único UPDATE con "waiter_id IS NULL": si dos camareros pulsan "Tomar" a la vez,
 * MySQL deja pasar solo al primero y al segundo no le cambia ninguna fila (rowCount 0 -> false).
 */
function tomar_pedido(int $pedido_id, int $camarero_id): bool
{
    $st = conectar()->prepare(
        "UPDATE orders SET waiter_id = ? WHERE id = ? AND waiter_id IS NULL AND status <> 'Served'"
    );
    $st->execute([$camarero_id, $pedido_id]);
    return $st->rowCount() === 1;
}

/**
 * Cambia el estado de un pedido si el rol del usuario lo permite (transicion_permitida()).
 * $usuario: ['id' => int, 'role' => string], normalmente el de usuario_actual().
 * Devuelve true solo si el pedido realmente cambió.
 */
function cambiar_estado(int $pedido_id, string $nuevo, array $usuario): bool
{
    $pdo = conectar();
    $st = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $st->execute([$pedido_id]);
    $pedido = $st->fetch();

    if (!$pedido || !transicion_permitida($usuario['role'], $pedido['status'], $nuevo)) {
        return false;
    }

    // "AND status = ?": si otra persona cambió el pedido entre la lectura y este UPDATE,
    // no se pisa su cambio (la consulta no afecta ninguna fila y se devuelve false)
    if ($usuario['role'] === 'Waiter' && $nuevo === 'Served') {
        // Quien entrega el pedido en la mesa queda como su camarero (spec §12.4), aunque antes fuera de otro
        $up = $pdo->prepare('UPDATE orders SET status = ?, waiter_id = ? WHERE id = ? AND status = ?');
        $up->execute([$nuevo, (int) $usuario['id'], $pedido_id, $pedido['status']]);
    } else {
        $up = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
        $up->execute([$nuevo, $pedido_id, $pedido['status']]);
    }

    return $up->rowCount() === 1;
}

/** Máximo de filas que muestra la tabla de pedidos del administrador. */
const LIMITE_PEDIDOS_ADMIN = 200;

/**
 * Números de la "sala de control" del administrador:
 * - pedidos_hoy: pedidos creados hoy · en_cocina: Pending + Preparing · platos_activos: available = 1
 * - ingresos: suma de precio × cantidad de los pedidos Served. OJO: orders no guarda el precio del
 *   momento de la venta, así que se usa el precio ACTUAL del plato (en la página se rotula como tal).
 * - por_estado: [estado => cantidad] con los 4 estados siempre presentes (en 0 si no hay)
 * - top_platos: los 5 platos con más unidades pedidas [['nombre', 'unidades', 'imagen'], ...]
 */
function estadisticas_admin(): array
{
    $pdo = conectar();

    // "Hoy" viaja como parámetro desde PHP, igual que en pedidos_de_camarero()
    $st_hoy = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?');
    $st_hoy->execute([date('Y-m-d')]);
    $pedidos_hoy = (int) $st_hoy->fetchColumn();
    $ingresos = (float) $pdo->query(
        "SELECT COALESCE(SUM(d.price * o.quantity), 0)
         FROM orders o JOIN dishes d ON d.id = o.dish_id
         WHERE o.status = 'Served'"
    )->fetchColumn();
    $platos_activos = (int) $pdo->query('SELECT COUNT(*) FROM dishes WHERE available = 1')->fetchColumn();

    // Se parte de los 4 estados en 0 y la consulta rellena los que tienen pedidos
    $por_estado = array_fill_keys(ESTADOS, 0);
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $fila) {
        if (array_key_exists($fila['status'], $por_estado)) {
            $por_estado[$fila['status']] = (int) $fila['n'];
        }
    }

    $top_platos = [];
    $filas = $pdo->query(
        'SELECT d.name, d.image, SUM(o.quantity) AS unidades
         FROM orders o JOIN dishes d ON d.id = o.dish_id
         GROUP BY d.id, d.name, d.image
         ORDER BY unidades DESC, d.name ASC
         LIMIT 5'
    );
    foreach ($filas as $fila) {
        $top_platos[] = ['nombre' => $fila['name'], 'unidades' => (int) $fila['unidades'], 'imagen' => $fila['image']];
    }

    return [
        'pedidos_hoy'    => $pedidos_hoy,
        'en_cocina'      => $por_estado['Pending'] + $por_estado['Preparing'],
        'ingresos'       => $ingresos,
        'platos_activos' => $platos_activos,
        'por_estado'     => $por_estado,
        'top_platos'     => $top_platos,
    ];
}

/**
 * Todos los pedidos para el administrador, del más nuevo al más viejo (máximo LIMITE_PEDIDOS_ADMIN).
 * $estado: solo ese estado (si no es uno de ESTADOS se ignora). $buscar: texto que se busca en el
 * usuario o el email del cliente y en el nombre del plato. Trae plato, cliente, email, camarero
 * (null si no tiene), precio actual del plato y subtotal = precio × cantidad.
 */
function todos_los_pedidos(?string $estado = null, ?string $buscar = null): array
{
    $condiciones = [];
    $parametros = [];

    if ($estado !== null && in_array($estado, ESTADOS, true)) {
        $condiciones[] = 'o.status = ?';
        $parametros[] = $estado;
    }

    $buscar = trim((string) $buscar);
    if ($buscar !== '') {
        // En LIKE, "%" y "_" son comodines: se escapan para que el texto del usuario se busque literal
        // (si no, buscar "%" traería todos los pedidos). Los "%" de los extremos los pone PHP.
        // Se usa "!" como carácter de escape declarado con ESCAPE: "\" depende del sql_mode del servidor
        // (con NO_BACKSLASH_ESCAPES cambia su significado), "!" funciona igual en cualquier configuración.
        $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
        $condiciones[] = "(c.username LIKE ? ESCAPE '!' OR c.email LIKE ? ESCAPE '!' OR d.name LIKE ? ESCAPE '!')";
        array_push($parametros, $patron, $patron, $patron);
    }

    // $where solo une textos fijos escritos aquí; los valores del usuario viajan en $parametros
    $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
    $st = conectar()->prepare(
        "SELECT o.id, o.status, o.quantity, o.created_at,
                d.name AS plato, d.price, d.price * o.quantity AS subtotal,
                c.username AS cliente, c.email,
                w.username AS camarero
         FROM orders o
         JOIN dishes d ON d.id = o.dish_id
         JOIN users c ON c.id = o.user_id
         LEFT JOIN users w ON w.id = o.waiter_id
         $where
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT " . LIMITE_PEDIDOS_ADMIN
    );
    $st->execute($parametros);
    return $st->fetchAll();
}

/** Elimina un pedido (solo el administrador). Devuelve true si existía y se borró. */
function eliminar_pedido(int $id): bool
{
    $st = conectar()->prepare('DELETE FROM orders WHERE id = ?');
    $st->execute([$id]);
    return $st->rowCount() === 1;
}
