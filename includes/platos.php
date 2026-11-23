<?php
/**
 * Consultas de la carta (tabla dishes).
 * Lo usan la landing pública (index.php) y el menú del cliente (Task 5).
 * Ofrece: las categorías fijas de la carta, la lectura de platos (todos o solo los disponibles)
 * y la lectura de un plato por id. Todas las consultas usan sentencias preparadas.
 */

require_once __DIR__ . '/db.php'; // conectar(); así el archivo funciona también incluido solo

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
    // available >= ? : con 1 trae solo los disponibles, con 0 trae todos (sentencia preparada igualmente)
    $st = conectar()->prepare(
        'SELECT id, name, description, price, category, image, available FROM dishes WHERE available >= ?'
    );
    $st->execute([$solo_disponibles ? 1 : 0]);
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
