<?php
/**
 * Cierre de sesión.
 * Solo acepta POST con token CSRF (el botón "Salir" de la barra es un formulario).
 * Así un enlace o una imagen en otro sitio no puede cerrar la sesión del usuario.
 * Con GET simplemente vuelve a la página de inicio.
 */

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('');
}

verificar_csrf();
cerrar_sesion();

// Sesión nueva y vacía solo para llevar el mensaje de despedida a login.php
iniciar_sesion_segura();
flash('info', 'Cerraste sesión. Vuelve pronto.');
redirigir('login.php');
