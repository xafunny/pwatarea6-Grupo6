<?php
/**
 * Acceso a datos de usuarios y roles (tablas users y roles).
 * Aquí está todo el SQL de usuarios: búsqueda por email, autenticación con password_verify,
 * registro de clientes, CRUD para el administrador y validaciones.
 * Todas las consultas son sentencias preparadas. Ante datos inválidos se lanza
 * InvalidArgumentException con un mensaje en español listo para mostrar.
 */

require_once __DIR__ . '/db.php';

/** Normaliza un email para guardarlo y compararlo siempre igual (sin espacios y en minúsculas). */
function normalizar_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

/** Busca un usuario por email junto con el nombre de su rol. Incluye el hash de la contraseña. */
function buscar_usuario_por_email(string $email): ?array
{
    $sql = 'SELECT u.id, u.username, u.email, u.password, u.role_id, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = ?';
    $st = conectar()->prepare($sql);
    $st->execute([normalizar_email($email)]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/**
 * Comprueba email y contraseña. Devuelve el usuario (sin el hash) o null si no coinciden.
 * No dice cuál de los dos falló, para no revelar qué correos están registrados.
 */
function autenticar(string $email, string $clave): ?array
{
    $u = buscar_usuario_por_email($email);
    if ($u === null) {
        // Se verifica contra un hash cualquiera para que la respuesta tarde lo mismo exista o no el correo
        password_verify($clave, '$2y$12$O9CgK0FO2IXJ.1hKNR2sDuPLPxHro5.IuQBkQQXCplpC3bQGs/u32');
        return null;
    }
    if (!password_verify($clave, $u['password'])) {
        return null;
    }
    // Si PHP cambió su algoritmo o costo por defecto, se aprovecha el login para actualizar el hash
    if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
        $st = conectar()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $st->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
    }
    unset($u['password']); // el hash nunca sale de esta función
    return $u;
}

/** Indica si el email ya está en uso. $excepto: id del usuario que se está editando (se ignora). */
function email_existe(string $email, ?int $excepto = null): bool
{
    $st = conectar()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
    $st->execute([normalizar_email($email), $excepto ?? 0]);
    return (int) $st->fetchColumn() > 0;
}

/**
 * Valida nombre, email y contraseña. Devuelve [campo => mensaje]; vacío si todo es válido.
 * $clave_obligatoria = false permite dejar la contraseña vacía (editar sin cambiarla).
 * La usan registro.php (para marcar cada campo) y las funciones de guardado.
 */
function errores_usuario(string $nombre, string $email, string $clave, bool $clave_obligatoria, ?int $excepto = null): array
{
    $errores = [];
    $largo = mb_strlen(trim($nombre), 'UTF-8');
    if ($largo < 2 || $largo > 50) {
        $errores['nombre'] = 'El nombre debe tener entre 2 y 50 caracteres.';
    }

    $email = normalizar_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errores['email'] = 'Escribe un correo electrónico válido.';
    } elseif (email_existe($email, $excepto)) {
        $errores['email'] = 'Ese correo ya está registrado.';
    }

    if ($clave !== '' || $clave_obligatoria) {
        if (strlen($clave) < 6) {
            $errores['clave'] = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (strlen($clave) > 72) {
            // bcrypt solo usa los primeros 72 bytes; más largo daría una falsa sensación de seguridad
            $errores['clave'] = 'La contraseña no puede superar los 72 caracteres.';
        }
    }
    return $errores;
}

/** Lanza InvalidArgumentException con el primer error si la lista no está vacía. */
function lanzar_si_hay_errores(array $errores): void
{
    if ($errores) {
        throw new InvalidArgumentException(reset($errores));
    }
}

/** Devuelve el id de un rol a partir de su nombre en la BD (ej: 'Client'), o null si no existe. */
function id_rol(string $nombre): ?int
{
    $st = conectar()->prepare('SELECT id FROM roles WHERE name = ?');
    $st->execute([$nombre]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Registra a un cliente nuevo (el rol siempre es Client) y devuelve su id. */
function registrar_cliente(string $nombre, string $email, string $clave): int
{
    lanzar_si_hay_errores(errores_usuario($nombre, $email, $clave, true));

    $st = conectar()->prepare('INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)');
    $st->execute([trim($nombre), normalizar_email($email), password_hash($clave, PASSWORD_DEFAULT), id_rol('Client')]);
    return (int) conectar()->lastInsertId();
}

/** Lista todos los usuarios con el nombre de su rol, ordenados por rol y nombre. */
function obtener_usuarios(): array
{
    $sql = 'SELECT u.id, u.username, u.email, u.role_id, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            ORDER BY u.role_id, u.username';
    return conectar()->query($sql)->fetchAll();
}

/** Lista solo los clientes (para que el camarero o el admin elijan a nombre de quién va un pedido). */
function obtener_clientes(): array
{
    $sql = "SELECT u.id, u.username, u.email
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.name = 'Client'
            ORDER BY u.username";
    return conectar()->query($sql)->fetchAll();
}

/**
 * Indica si el id corresponde a un usuario con rol Client.
 * El camarero elige al cliente en un <select>, pero el valor llega del navegador y puede venir
 * manipulado: el servidor lo comprueba aquí antes de crear pedidos a su nombre.
 */
function es_cliente(int $id): bool
{
    return nombre_cliente($id) !== null;
}

/**
 * Devuelve el nombre de usuario si el id es de un usuario con rol Client; si no, null.
 * Valida y trae el nombre en una sola consulta: nuevo_pedido.php la usa para comprobar el
 * cliente elegido y armar el mensaje "Pedido registrado para ...".
 */
function nombre_cliente(int $id): ?string
{
    $st = conectar()->prepare(
        "SELECT u.username FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = 'Client'"
    );
    $st->execute([$id]);
    $nombre = $st->fetchColumn();
    return $nombre === false ? null : (string) $nombre;
}

/** Lista los roles [id, name] en el orden en que se crearon. */
function obtener_roles(): array
{
    return conectar()->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
}

/**
 * Crea ($id null) o actualiza un usuario. Devuelve su id.
 * $datos: username, email, password, role_id. Al editar, una contraseña vacía conserva la actual.
 */
function guardar_usuario(array $datos, ?int $id = null): int
{
    $nombre = trim((string) ($datos['username'] ?? ''));
    $email  = normalizar_email((string) ($datos['email'] ?? ''));
    $clave  = (string) ($datos['password'] ?? '');
    $rol_id = (int) ($datos['role_id'] ?? 0);

    lanzar_si_hay_errores(errores_usuario($nombre, $email, $clave, $id === null, $id));

    // El rol debe existir en la tabla roles (no se confía en el valor que llega del formulario)
    if (!in_array($rol_id, array_map('intval', array_column(obtener_roles(), 'id')), true)) {
        throw new InvalidArgumentException('El rol elegido no existe.');
    }

    $pdo = conectar();
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)');
        $st->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $rol_id]);
        return (int) $pdo->lastInsertId();
    }

    $existe = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $existe->execute([$id]);
    if ((int) $existe->fetchColumn() === 0) {
        throw new InvalidArgumentException('El usuario no existe.');
    }

    if ($clave === '') {
        $st = $pdo->prepare('UPDATE users SET username = ?, email = ?, role_id = ? WHERE id = ?');
        $st->execute([$nombre, $email, $rol_id, $id]);
    } else {
        $st = $pdo->prepare('UPDATE users SET username = ?, email = ?, password = ?, role_id = ? WHERE id = ?');
        $st->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $rol_id, $id]);
    }
    return $id;
}

/**
 * Elimina un usuario. Devuelve:
 * 'propio' si intenta borrarse a sí mismo, 'no_existe' si el id no es de ningún usuario,
 * 'tiene_pedidos' si tiene pedidos (la FK no lo permite) u 'ok'.
 */
function eliminar_usuario(int $id, int $id_actual): string
{
    if ($id === $id_actual) {
        return 'propio';
    }
    $st = conectar()->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() === 0) {
        return 'no_existe';
    }
    $st = conectar()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        return 'tiene_pedidos';
    }
    // Los pedidos que atendía como camarero quedan sin asignar (ON DELETE SET NULL en orders.waiter_id)
    $st = conectar()->prepare('DELETE FROM users WHERE id = ?');
    $st->execute([$id]);
    return 'ok';
}
