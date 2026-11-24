<?php
/**
 * Consultas de la carta (tabla dishes).
 * Lo usan la landing pública (index.php), el menú del cliente y la gestión de platos del administrador.
 * Ofrece: las categorías fijas de la carta, la lectura de platos (todos o solo los disponibles),
 * la lectura de un plato por id y, para el admin, guardar (crear/editar), eliminar y ocultar/mostrar.
 * Todas las consultas usan sentencias preparadas.
 */

require_once __DIR__ . '/db.php'; // conectar(); así el archivo funciona también incluido solo
require_once __DIR__ . '/funciones.php'; // dinero(), para el mensaje de precio fuera de rango

/** Las secciones de la carta, en el orden en que se muestran. */
const CATEGORIAS_MENU = ['Entradas', 'Principales', 'Postres', 'Bebidas'];

/** Devuelve la lista fija de categorías de la carta. */
function categorias_menu(): array
{
    return CATEGORIAS_MENU;
}

/**
 * Lleva cualquier categoría a una de las cuatro de la carta.
 * El valor por defecto de la columna es 'Principal' (singular) y un plato puede no tener categoría:
 * en esos casos el plato no debe desaparecer, se muestra en 'Principales'.
 */
function categoria_normalizada(?string $c): string
{
    return in_array($c, CATEGORIAS_MENU, true) ? $c : 'Principales';
}

/**
 * Devuelve los platos ordenados por sección de la carta y luego por nombre.
 * Con $solo_disponibles = true se omiten los platos ocultos (available = 0).
 * Cada fila trae además 'categoria' (la categoría ya normalizada).
 */
function obtener_platos(bool $solo_disponibles = true): array
{
    // Dos consultas fijas y fáciles de leer: el cliente ve solo los disponibles; el admin, todos
    $sql = 'SELECT id, name, description, price, category, image, available FROM dishes';
    if ($solo_disponibles) {
        $sql .= ' WHERE available = 1';
    }
    $st = conectar()->prepare($sql);
    $st->execute();
    $platos = $st->fetchAll();

    foreach ($platos as &$plato) {
        $plato['categoria'] = categoria_normalizada($plato['category']);
    }
    unset($plato); // corta la referencia del foreach para no modificar la última fila por error

    // El orden de la carta (Entradas, Principales...) no es alfabético: se ordena en PHP
    usort($platos, function (array $a, array $b): int {
        $orden = array_search($a['categoria'], CATEGORIAS_MENU, true)
            <=> array_search($b['categoria'], CATEGORIAS_MENU, true);
        return $orden !== 0 ? $orden : strcmp($a['name'], $b['name']);
    });

    return $platos;
}

/**
 * Elige $cantidad platos repartidos entre las secciones (para la landing).
 * Toma uno de cada categoría por vuelta (Entradas, Principales, Postres, Bebidas, y otra vez)
 * hasta completar la cantidad; así la muestra no queda llena de una sola sección.
 * Recibe la lista ya ordenada de obtener_platos() y conserva ese orden.
 */
function platos_destacados(array $platos, int $cantidad = 6): array
{
    // Agrupa los platos por categoría: ['Entradas' => [...], 'Principales' => [...], ...]
    $por_categoria = [];
    foreach ($platos as $plato) {
        $por_categoria[$plato['categoria']][] = $plato;
    }

    $elegidos = [];
    for ($vuelta = 0; count($elegidos) < $cantidad && count($elegidos) < count($platos); $vuelta++) {
        foreach (CATEGORIAS_MENU as $categoria) {
            if (isset($por_categoria[$categoria][$vuelta]) && count($elegidos) < $cantidad) {
                $elegidos[] = $por_categoria[$categoria][$vuelta];
            }
        }
    }

    // Se devuelven en el orden de la carta, no en el orden de las vueltas
    $ids = array_column($elegidos, 'id');
    return array_values(array_filter($platos, fn(array $p): bool => in_array($p['id'], $ids, true)));
}

/** Devuelve un plato por su id (con 'categoria' normalizada) o null si no existe. */
function obtener_plato(int $id): ?array
{
    $st = conectar()->prepare(
        'SELECT id, name, description, price, category, image, available FROM dishes WHERE id = ?'
    );
    $st->execute([$id]);
    $plato = $st->fetch();

    if (!$plato) {
        return null;
    }
    $plato['categoria'] = categoria_normalizada($plato['category']);
    return $plato;
}

/** Precio mínimo y máximo que acepta un plato. */
const PRECIO_MINIMO = 0.01;
const PRECIO_MAXIMO = 9999.99;

/**
 * Indica si un texto es una URL http:// o https:// válida.
 * filter_var solo revisa la forma; el esquema se comprueba aparte porque filter_var también
 * acepta "javascript:..." o "ftp://...", y una imagen así no debe llegar al <img src> del menú.
 */
function es_url_imagen(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($esquema, ['http', 'https'], true);
}

/**
 * Crea ($id null) o actualiza un plato y devuelve su id.
 * $datos: name, description, price, category, image, available (tal como llegan del formulario).
 * Si algo no es válido lanza InvalidArgumentException con TODOS los errores en un solo mensaje.
 */
function guardar_plato(array $datos, ?int $id = null): int
{
    $nombre      = trim((string) ($datos['name'] ?? ''));
    $descripcion = trim((string) ($datos['description'] ?? ''));
    $precio      = trim((string) ($datos['price'] ?? ''));
    $categoria   = (string) ($datos['category'] ?? '');
    $imagen      = trim((string) ($datos['image'] ?? ''));
    $disponible  = (string) ($datos['available'] ?? '1');

    $errores = [];
    $largo = mb_strlen($nombre, 'UTF-8');
    if ($largo < 2 || $largo > 50) {
        $errores[] = 'El nombre debe tener entre 2 y 50 caracteres.';
    }
    if (mb_strlen($descripcion, 'UTF-8') > 255) {
        $errores[] = 'La descripción no puede superar los 255 caracteres.';
    }
    if (!is_numeric($precio) || (float) $precio < PRECIO_MINIMO || (float) $precio > PRECIO_MAXIMO) {
        $errores[] = 'El precio debe ser un número entre ' . dinero(PRECIO_MINIMO) . ' y ' . dinero(PRECIO_MAXIMO) . '.';
    }
    if (!in_array($categoria, CATEGORIAS_MENU, true)) {
        $errores[] = 'Elige una categoría de la carta.';
    }
    if ($imagen !== '' && (strlen($imagen) > 255 || !es_url_imagen($imagen))) {
        $errores[] = 'La imagen debe ser una dirección http:// o https:// válida (o quedar vacía).';
    }
    if (!in_array($disponible, ['0', '1'], true)) {
        $errores[] = 'El valor de "disponible" no es válido.';
    }
    if ($errores) {
        throw new InvalidArgumentException(implode(' ', $errores));
    }

    // Los campos opcionales vacíos se guardan como NULL (la columna lo permite)
    $valores = [
        $nombre,
        $descripcion === '' ? null : $descripcion,
        round((float) $precio, 2),
        $categoria,
        $imagen === '' ? null : $imagen,
        (int) $disponible,
    ];

    $pdo = conectar();
    if ($id === null) {
        $st = $pdo->prepare(
            'INSERT INTO dishes (name, description, price, category, image, available) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute($valores);
        return (int) $pdo->lastInsertId();
    }

    if (obtener_plato($id) === null) {
        throw new InvalidArgumentException('El plato que intentas editar ya no existe.');
    }
    $st = $pdo->prepare(
        'UPDATE dishes SET name = ?, description = ?, price = ?, category = ?, image = ?, available = ? WHERE id = ?'
    );
    $st->execute([...$valores, $id]);
    return $id;
}

/**
 * Elimina un plato. Devuelve 'ok', 'no_existe' o 'tiene_pedidos'.
 * Se cuentan los pedidos ANTES de borrar: la FK de orders no deja borrar un plato con historial,
 * y así nunca se llega a ese error de la BD (la página ofrece ocultarlo en su lugar).
 */
function eliminar_plato(int $id): string
{
    if (obtener_plato($id) === null) {
        return 'no_existe';
    }
    $st = conectar()->prepare('SELECT COUNT(*) FROM orders WHERE dish_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'tiene_pedidos';
    }
    conectar()->prepare('DELETE FROM dishes WHERE id = ?')->execute([$id]);
    return 'ok';
}

/** Oculta un plato visible o muestra uno oculto (1 - available invierte el valor en la misma consulta). */
function alternar_disponible(int $id): void
{
    conectar()->prepare('UPDATE dishes SET available = 1 - available WHERE id = ?')->execute([$id]);
}
