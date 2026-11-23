<?php
/**
 * Plantilla de configuración. Copia este archivo como config/config.php y ajusta los valores.
 * config/config.php está en .gitignore para no subir credenciales al repositorio.
 *
 * En InfinityFree (hosting):
 *   DB_HOST: el host que muestra el panel, por ejemplo sqlXXX.infinityfree.com
 *   DB_NAME: nombre con prefijo, por ejemplo if0_12345678_restaurante
 *   DB_USER: usuario con prefijo, por ejemplo if0_12345678
 *   DB_PASS: la contraseña de la cuenta de hosting
 *   BASE_URL: '' (cadena vacía) porque el sitio queda en la raíz del dominio
 */

define('DB_HOST', 'localhost');                  // host de MySQL
define('DB_NAME', 'nombre_de_la_base_de_datos'); // en local: restaurante
define('DB_USER', 'usuario_mysql');              // en local: root
define('DB_PASS', 'contrasena_mysql');           // en local: vacía

// Carpeta del proyecto bajo el servidor, sin barra final. Local: '/pwatarea6'. InfinityFree: ''.
define('BASE_URL', '/pwatarea6');

define('APP_NAME', 'Ébano');
