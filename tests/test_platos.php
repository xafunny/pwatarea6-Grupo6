<?php
/**
 * Prueba de las funciones de platos del administrador (includes/platos.php), por consola.
 * Uso: php tests/test_platos.php
 * Usa la BD real `restaurante`. Cada plato y pedido que crea se anota y se borra al final
 * (register_shutdown_function), así el seed queda igual aunque una comprobación falle a medio camino.
 */

$_SESSION = [];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platos.php';
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

$pdo = conectar();
$platos_creados = [];  // ids de platos de prueba
$pedidos_creados = []; // ids de pedidos de prueba

// Limpieza: primero los pedidos (la FK de orders apunta a dishes) y después los platos
register_shutdown_function(function () use (&$platos_creados, &$pedidos_creados) {
    $pdo = conectar();
    foreach ($pedidos_creados as $id) {
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    }
    foreach ($platos_creados as $id) {
        $pdo->prepare('DELETE FROM dishes WHERE id = ?')->execute([$id]);
    }
});

/** Datos válidos de un plato; $cambios sobrescribe los campos que se quieran probar. */
function plato_valido(array $cambios = []): array
{
    return array_merge([
        'name'        => 'Plato de prueba',
        'description' => 'Descripción de prueba',
        'price'       => '12.50',
        'category'    => 'Postres',
        'image'       => 'https://images.unsplash.com/photo-1.jpg',
        'available'   => '1',
    ], $cambios);
}

// guardar_plato(): creación válida
$id = guardar_plato(plato_valido());
$platos_creados[] = $id;
$fila = obtener_plato($id);
comprobar('guardar_plato crea y devuelve un id', $id > 0 && $fila !== null);
comprobar('guardar_plato guarda nombre, precio y categoría',
    ($fila['name'] ?? '') === 'Plato de prueba' && (float) $fila['price'] === 12.5 && $fila['category'] === 'Postres');
comprobar('guardar_plato guarda la imagen y available', $fila['image'] === 'https://images.unsplash.com/photo-1.jpg' && (int) $fila['available'] === 1);

$sin_extras = guardar_plato(plato_valido(['description' => '   ', 'image' => '', 'available' => '0']));
$platos_creados[] = $sin_extras;
$fila = obtener_plato($sin_extras);
comprobar('descripción e imagen vacías se guardan como NULL', $fila['description'] === null && $fila['image'] === null);
comprobar('available 0 se guarda oculto', (int) $fila['available'] === 0);

// guardar_plato(): cada validación lanza
comprobar('nombre de 1 letra lanza', lanza(fn() => guardar_plato(plato_valido(['name' => 'A']))));
comprobar('nombre de 51 letras lanza', lanza(fn() => guardar_plato(plato_valido(['name' => str_repeat('a', 51)]))));
comprobar('descripción de 256 letras lanza', lanza(fn() => guardar_plato(plato_valido(['description' => str_repeat('a', 256)]))));
comprobar('precio 0 lanza', lanza(fn() => guardar_plato(plato_valido(['price' => '0']))));
comprobar('precio 10000 lanza', lanza(fn() => guardar_plato(plato_valido(['price' => '10000']))));
comprobar('precio no numérico lanza', lanza(fn() => guardar_plato(plato_valido(['price' => 'doce']))));
comprobar('categoría fuera de la lista lanza', lanza(fn() => guardar_plato(plato_valido(['category' => 'Sopas']))));
comprobar('imagen javascript: lanza', lanza(fn() => guardar_plato(plato_valido(['image' => 'javascript:alert(1)']))));
// Esta SÍ pasa filter_var(FILTER_VALIDATE_URL): solo la comprobación de esquema (http/https) la frena
comprobar('imagen javascript://x%0Aalert(1) lanza', lanza(fn() => guardar_plato(plato_valido(['image' => 'javascript://x%0Aalert(1)']))));
comprobar('imagen ftp:// lanza', lanza(fn() => guardar_plato(plato_valido(['image' => 'ftp://ejemplo.com/a.jpg']))));
comprobar('imagen que no es URL lanza', lanza(fn() => guardar_plato(plato_valido(['image' => 'foto.jpg']))));
comprobar('available distinto de 0/1 lanza', lanza(fn() => guardar_plato(plato_valido(['available' => '7']))));
comprobar('actualizar un id inexistente lanza', lanza(fn() => guardar_plato(plato_valido(), 99999999)));
try {
    guardar_plato(plato_valido(['name' => 'A', 'price' => '0']));
    $mensaje = '';
} catch (InvalidArgumentException $ex) {
    $mensaje = $ex->getMessage();
}
comprobar('el mensaje de error está en español y junta varios errores',
    str_contains($mensaje, 'nombre') && str_contains($mensaje, 'precio'));

// guardar_plato(): actualización
$devuelto = guardar_plato(plato_valido(['name' => 'Plato editado', 'price' => '20', 'category' => 'Bebidas']), $id);
$fila = obtener_plato($id);
comprobar('guardar_plato al editar devuelve el mismo id', $devuelto === $id);
comprobar('guardar_plato al editar cambia los campos',
    $fila['name'] === 'Plato editado' && (float) $fila['price'] === 20.0 && $fila['category'] === 'Bebidas');

// alternar_disponible()
alternar_disponible($id);
comprobar('alternar_disponible oculta un plato visible', (int) obtener_plato($id)['available'] === 0);
alternar_disponible($id);
comprobar('alternar_disponible vuelve a mostrarlo', (int) obtener_plato($id)['available'] === 1);

// obtener_platos(): con false trae también los ocultos
$ids_todos = array_map('intval', array_column(obtener_platos(false), 'id'));
$ids_visibles = array_map('intval', array_column(obtener_platos(true), 'id'));
comprobar('obtener_platos(false) incluye el plato oculto', in_array($sin_extras, $ids_todos, true));
comprobar('obtener_platos(true) no incluye el plato oculto', !in_array($sin_extras, $ids_visibles, true));
comprobar('obtener_platos(true) incluye el plato visible', in_array($id, $ids_visibles, true));

// eliminar_plato()
$cliente = buscar_usuario_por_email('cliente@ebano.com');
crear_pedidos((int) $cliente['id'], [['dish_id' => $id, 'quantity' => 1]]);
$pedidos_creados[] = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
comprobar('eliminar_plato con pedidos devuelve tiene_pedidos', eliminar_plato($id) === 'tiene_pedidos');
comprobar('el plato con pedidos sigue existiendo', obtener_plato($id) !== null);
comprobar('eliminar_plato de un plato sin pedidos devuelve ok', eliminar_plato($sin_extras) === 'ok');
comprobar('el plato eliminado ya no existe', obtener_plato($sin_extras) === null);
comprobar('eliminar_plato de un id inexistente devuelve no_existe', eliminar_plato(99999999) === 'no_existe');

exit($fallos > 0 ? 1 : 0);
