<?php
/**
 * Prueba de autenticación y de las funciones de usuarios (se ejecuta por consola).
 * Uso: php tests/test_auth.php
 * Usa la BD real `restaurante`; el usuario de prueba que crea se borra al final.
 * No abre una sesión real: en consola iniciar_sesion_segura() solo prepara el arreglo $_SESSION.
 */

$_SESSION = [];
require_once __DIR__ . '/../includes/auth.php';

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

// autenticar()
$admin = autenticar('admin@ebano.com', '123456');
comprobar('autenticar admin devuelve una fila', is_array($admin));
comprobar('autenticar admin tiene rol Administrator', ($admin['role'] ?? '') === 'Administrator');
comprobar('autenticar no devuelve el hash', is_array($admin) && !array_key_exists('password', $admin));
comprobar('autenticar ignora mayúsculas y espacios del email', autenticar('  ADMIN@ebano.com ', '123456') !== null);
comprobar('autenticar con clave incorrecta devuelve null', autenticar('admin@ebano.com', 'otra-clave') === null);
comprobar('autenticar con email desconocido devuelve null', autenticar('nadie@ebano.com', '123456') === null);

// dashboard_de()
comprobar('dashboard_de Chef', dashboard_de('Chef') === 'chef/cocina.php');
comprobar('dashboard_de Administrator', dashboard_de('Administrator') === 'admin/dashboard.php');
comprobar('dashboard_de Waiter', dashboard_de('Waiter') === 'camarero/dashboard.php');
comprobar('dashboard_de Client', dashboard_de('Client') === 'cliente/menu.php');

// usuario_actual() sin sesión
comprobar('usuario_actual sin sesión es null', usuario_actual() === null);

// registrar_cliente() y email_existe()
$correo = 'prueba_' . bin2hex(random_bytes(4)) . '@ebano.test';
$id = registrar_cliente('Cliente de Prueba', $correo, 'secreto1');
// Limpieza garantizada aunque una comprobación lance una excepción a medio camino
register_shutdown_function(function () use ($correo) {
    conectar()->prepare('DELETE FROM users WHERE email = ?')->execute([$correo]);
});
comprobar('registrar_cliente devuelve un id', $id > 0);
$nuevo = buscar_usuario_por_email($correo);
comprobar('el usuario registrado tiene rol Client', ($nuevo['role'] ?? '') === 'Client');
comprobar('la contraseña se guardó con hash', isset($nuevo['password']) && password_verify('secreto1', $nuevo['password']));
comprobar('email_existe encuentra el correo', email_existe($correo));
comprobar('email_existe con $excepto del mismo usuario es false', !email_existe($correo, $id));
comprobar('email_existe con $excepto de otro usuario es true', email_existe($correo, 1));
comprobar('email_existe de un correo libre es false', !email_existe('libre_' . $correo));

// Validaciones (deben lanzar InvalidArgumentException)
function lanza(callable $f): bool
{
    try {
        $f();
        return false;
    } catch (InvalidArgumentException $ex) {
        return true;
    }
}
comprobar('registrar_cliente rechaza email repetido', lanza(fn() => registrar_cliente('Otro', $correo, 'secreto1')));
comprobar('registrar_cliente rechaza clave corta', lanza(fn() => registrar_cliente('Otro', 'x_' . $correo, '123')));
comprobar('registrar_cliente rechaza nombre de 1 letra', lanza(fn() => registrar_cliente('A', 'y_' . $correo, 'secreto1')));
comprobar('registrar_cliente rechaza email inválido', lanza(fn() => registrar_cliente('Otro', 'no-es-email', 'secreto1')));
comprobar('guardar_usuario rechaza rol inexistente', lanza(fn() => guardar_usuario(
    ['username' => 'Cliente de Prueba', 'email' => $correo, 'password' => '', 'role_id' => 999], $id)));

// Largo de la contraseña: el mínimo cuenta caracteres y el máximo cuenta bytes (límite de bcrypt)
$libre = 'largo_' . $correo;
comprobar('errores_usuario: 5 letras con tilde (10 bytes) son menos de 6 caracteres',
    isset(errores_usuario('Ana', $libre, 'ñáéíó', true)['clave']));
comprobar('errores_usuario: 6 letras con tilde (12 bytes) cumplen el mínimo',
    !isset(errores_usuario('Ana', $libre, 'ñáéíóú', true)['clave']));
comprobar('errores_usuario: 37 letras con tilde (74 bytes) superan el máximo de 72 bytes',
    isset(errores_usuario('Ana', $libre, str_repeat('ñ', 37), true)['clave']));
comprobar('errores_usuario: 36 letras con tilde (72 bytes) caben',
    !isset(errores_usuario('Ana', $libre, str_repeat('ñ', 36), true)['clave']));

// guardar_usuario(): editar sin contraseña conserva la actual
guardar_usuario(['username' => 'Cliente Editado', 'email' => $correo, 'password' => '', 'role_id' => id_rol('Client')], $id);
$editado = buscar_usuario_por_email($correo);
comprobar('guardar_usuario cambia el nombre', ($editado['username'] ?? '') === 'Cliente Editado');
comprobar('guardar_usuario con clave vacía conserva la contraseña', password_verify('secreto1', $editado['password'] ?? ''));

// eliminar_usuario()
comprobar('eliminar_usuario a sí mismo devuelve propio', eliminar_usuario($id, $id) === 'propio');
// El usuario "con pedidos" se busca por su email, no por un id fijo
$id_con_pedidos = (int) (buscar_usuario_por_email('cliente@ebano.com')['id'] ?? 0);
comprobar('eliminar_usuario con pedidos devuelve tiene_pedidos', eliminar_usuario($id_con_pedidos, 1) === 'tiene_pedidos');
comprobar('eliminar_usuario del usuario de prueba devuelve ok', eliminar_usuario($id, 1) === 'ok');
comprobar('el usuario de prueba ya no existe', buscar_usuario_por_email($correo) === null);
comprobar('eliminar_usuario de un id inexistente devuelve no_existe', eliminar_usuario(99999999, 1) === 'no_existe');

// es_cliente(): el camarero solo puede pedir a nombre de un usuario con rol Client
$id_cliente_seed = (int) (buscar_usuario_por_email('cliente@ebano.com')['id'] ?? 0);
$id_chef_seed = (int) (buscar_usuario_por_email('chef@ebano.com')['id'] ?? 0);
comprobar('es_cliente del cliente de prueba es true', es_cliente($id_cliente_seed));
comprobar('es_cliente del chef es false', !es_cliente($id_chef_seed));
comprobar('es_cliente de un id inexistente es false', !es_cliente(99999999));
comprobar('es_cliente de 0 es false', !es_cliente(0));
// nombre_cliente(): valida y devuelve el nombre en una sola consulta (para el mensaje del camarero)
comprobar('nombre_cliente del cliente de prueba devuelve su usuario',
    nombre_cliente($id_cliente_seed) === (buscar_usuario_por_email('cliente@ebano.com')['username'] ?? ''));
comprobar('nombre_cliente del chef es null', nombre_cliente($id_chef_seed) === null);
comprobar('nombre_cliente de un id inexistente es null', nombre_cliente(99999999) === null);

exit($fallos > 0 ? 1 : 0);
