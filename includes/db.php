<?php
/**
 * Conexión a la base de datos con PDO.
 * Todas las páginas llaman a conectar() y reciben siempre el MISMO objeto PDO (singleton).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Devuelve la conexión PDO, creándola la primera vez.
 * Si falla, no se muestran detalles técnicos al usuario: se registran en el log del servidor.
 */
function conectar(): PDO
{
    // static: la variable conserva su valor entre llamadas, así se abre una sola conexión por petición
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // los errores SQL lanzan excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // filas como arreglos asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                    // sentencias preparadas reales (anti inyección SQL)
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $ex) {
        error_log('Error de conexión a la BD: ' . $ex->getMessage());
        // En consola (tests) se relanza para ver el error real
        if (PHP_SAPI === 'cli') {
            throw $ex;
        }
        http_response_code(500);
        echo 'No pudimos conectar con el servicio en este momento. Inténtalo de nuevo en unos minutos.';
        exit;
    }

    return $pdo;
}
