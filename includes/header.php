<?php
/**
 * Cabecera común de todas las páginas: desde <!doctype html> hasta la apertura de <main>.
 * Carga Bootstrap 5.3.3, Bootstrap Icons, las fuentes y estilos.css; incluye la barra de navegación
 * y los mensajes flash.
 * Variables que lee (la página las define antes del require):
 *   $titulo_pagina  string  título de la pestaña (ej: 'Menú')
 *   $clase_body     string  clase del <body> para el layout de cada rol (ej: 'rol-chef')
 *   $sin_navbar     bool    true en login/registro: sin barra ni flash (la página los ubica ella misma)
 *   $atributos_body array   atributos extra del <body>, ej: ['data-vaciar-carrito' => 'ebano_carrito_cliente']
 * No inicia la sesión: auth.php ya lo hizo antes de llegar aquí.
 */

$titulo_pagina = $titulo_pagina ?? APP_NAME;
$clase_body = $clase_body ?? '';
$sin_navbar = $sin_navbar ?? false;
$atributos_body = $atributos_body ?? [];

// ?v=fecha de modificación: el navegador descarga el CSS de nuevo solo cuando cambia
$version_css = (string) @filemtime(__DIR__ . '/../assets/css/estilos.css');
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> · <?= e(APP_NAME) ?></title>
    <meta name="description" content="Ébano, cocina de autor: carta, pedidos y cocina en un mismo lugar.">
    <meta name="theme-color" content="#0E0E11">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5' fill='%230E0E11'/%3E%3Cpath d='M12 3.5 19 12l-7 8.5L5 12z' fill='none' stroke='%23C9A45C' stroke-width='1.5'/%3E%3Cpath d='M12 8 15 12l-3 4-3-4z' fill='%23C9A45C'/%3E%3C/svg%3E">

    <!-- Fuentes: Cormorant Garamond (títulos y precios) y Manrope (texto e interfaz) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap">

    <!-- Bootstrap e iconos con versión fija; integrity verifica que el CDN entregue el archivo exacto -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">

    <!-- Estilos propios: van después de Bootstrap para poder sobrescribirlo -->
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>?v=<?= e($version_css) ?>">
</head>
<body class="<?= e($clase_body) ?>"<?php foreach ($atributos_body as $nombre => $valor): ?> <?= e($nombre) ?>="<?= e($valor) ?>"<?php endforeach; ?>>
<a class="saltar-contenido" href="#contenido">Saltar al contenido</a>
<?php if (!$sin_navbar): ?>
<?php require __DIR__ . '/navbar.php'; ?>
<?php endif; ?>
<main id="contenido" tabindex="-1">
<?php if (!$sin_navbar): ?>
    <?php $mensajes = mostrar_flash(); ?>
    <?php if ($mensajes !== ''): ?>
    <div class="container zona-flash"><?= $mensajes /* ya viene escapado por mostrar_flash() */ ?></div>
    <?php endif; ?>
<?php endif; ?>
