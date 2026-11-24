<?php
/**
 * Prueba de la base de datos y la conexión (se ejecuta por consola).
 * Uso: php tests/test_db.php
 * Verifica la conexión PDO, los 4 roles, los 4 usuarios de prueba y sus contraseñas.
 */

require_once __DIR__ . '/../includes/db.php';

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

$pdo = conectar();
comprobar('conectar() devuelve un PDO', $pdo instanceof PDO);
comprobar('conectar() es singleton', conectar() === $pdo);

$total = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
comprobar('hay 4 roles', $total === 4);

$emails = ['admin@ebano.com', 'chef@ebano.com', 'camarero@ebano.com', 'cliente@ebano.com'];
$stmt = $pdo->prepare('SELECT password FROM users WHERE email = ?');
foreach ($emails as $email) {
    $stmt->execute([$email]);
    $hash = $stmt->fetchColumn();
    comprobar("existe $email", $hash !== false);
    comprobar("contraseña 123456 válida para $email", $hash !== false && password_verify('123456', $hash));
}

exit($fallos > 0 ? 1 : 0);
