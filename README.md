# Ébano — Cocina de autor

Sistema web de gestión de restaurante con cuatro roles (Administrador, Chef, Camarero y Cliente).
Los clientes piden desde el menú, la cocina ve las comandas en un tablero que se actualiza solo,
el camarero entrega y el administrador controla todo.

**Proyecto académico — Programación Web Avanzada, Tarea 6, Grupo 6**

## Tecnologías

- PHP 8.1+ (sin frameworks; multipágina con `include`/`require`)
- MySQL / MariaDB (acceso con PDO y sentencias preparadas)
- Bootstrap 5.3.3 y Bootstrap Icons 1.11.3 (por CDN)
- CSS propio (`assets/css/estilos.css`, tema "Noche elegante")
- JavaScript (carrito, `fetch`, modales del administrador)
- GSAP 3.12.5 + ScrollTrigger (animaciones)
- Fuentes: Cormorant Garamond y Manrope (Google Fonts)

## Roles y qué puede hacer cada uno

Los permisos se comprueban **en el servidor** en cada página (`requerir_rol()` en `includes/auth.php`).
Si entras a la página de otro rol, el servidor responde **403**; sin sesión, te lleva a `login.php`.

| Rol | Páginas | Qué puede hacer |
|---|---|---|
| **Cliente** | `cliente/menu.php`, `cliente/mis_pedidos.php` | Ver el menú con filtros por categoría, armar un carrito y hacer su pedido, ver el estado (línea de progreso de 4 pasos) y su historial. Se registra solo en `registro.php`. |
| **Chef** | `chef/cocina.php`, `cliente/menu.php` (solo lectura) | Tablero de comandas (Pendiente, En preparación, Listo). Pasa Pendiente → En preparación → Listo. Se actualiza cada 20 s. Puede ver la carta (enlace "Carta") con los filtros por categoría, pero sin carrito ni pedidos. |
| **Camarero** | `camarero/dashboard.php`, `camarero/nuevo_pedido.php` | Ve "Listos para servir", sus pedidos asignados y los sin asignar. Entrega los pedidos listos (Listo → Entregado; el pedido queda asignado a quien lo entrega), toma pedidos sin asignar y crea pedidos a nombre de un cliente (quedan asignados a él). |
| **Administrador** | `admin/dashboard.php`, `admin/platos.php`, `admin/usuarios.php`, `admin/pedidos.php`, `camarero/nuevo_pedido.php` | Sala de control con KPI, CRUD de platos (también ocultar/mostrar), CRUD de usuarios y cambio de rol, ver todos los pedidos con filtros, cambiarlos de estado y eliminarlos. Puede crear un pedido a nombre de un cliente con el botón "Nuevo pedido" de `admin/pedidos.php` (queda sin asignar para que un camarero lo tome). No puede borrarse a sí mismo ni quitarse el rol de administrador. |

Reglas importantes: los estados solo avanzan (solo el administrador puede retroceder); un plato o usuario con pedidos
no se puede borrar (el plato se puede ocultar). La tabla de transiciones está en `includes/funciones.php`.

## Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@ebano.com | 123456 |
| Chef | chef@ebano.com | 123456 |
| Camarero | camarero@ebano.com | 123456 |
| Cliente | cliente@ebano.com | 123456 |

## Instalación local con XAMPP

1. Copia la carpeta del proyecto a `C:\xampp\htdocs\pwatarea6`.
2. Abre el panel de XAMPP y arranca **Apache** y **MySQL**.
3. Entra a http://localhost/phpmyadmin, crea la base de datos `restaurante` con cotejamiento `utf8mb4_unicode_ci`
   y, dentro de ella, usa la pestaña **Importar** para cargar `database.sql`.
4. Copia `config/config.example.php` como `config/config.php` y revisa los valores. Para XAMPP:
   `DB_HOST 'localhost'`, `DB_NAME 'restaurante'`, `DB_USER 'root'`, `DB_PASS ''` y `BASE_URL '/pwatarea6'`.
5. Abre http://localhost/pwatarea6/ e inicia sesión con un usuario de prueba.

### Variante con Laragon

1. Copia la carpeta a `C:\laragon\www\pwatarea6`.
2. Arranca Laragon (Apache y MySQL) y crea la BD `restaurante` (utf8mb4_unicode_ci) desde HeidiSQL o phpMyAdmin; importa `database.sql`.
3. Copia `config/config.example.php` a `config/config.php`. Normalmente el usuario es `root` sin contraseña y `BASE_URL` es `'/pwatarea6'`.
4. Abre http://localhost/pwatarea6/. (Laragon también puede crear el host automático http://pwatarea6.test; en ese caso
   el sitio queda en la raíz y `BASE_URL` debe ser `''`.)

## Despliegue en InfinityFree

1. Crea una cuenta en InfinityFree y un sitio de hosting (subdominio gratuito o dominio propio).
2. En el panel, crea una base de datos MySQL. Anota el **host** (`sqlXXX.infinityfree.com`), el **nombre de la BD con prefijo**
   (por ejemplo `if0_12345678_restaurante`), el **usuario** (`if0_12345678`) y la contraseña.
3. Abre el phpMyAdmin del panel, elige esa base y usa **Importar** con `database.sql`. Verifica que la importación use
   el juego de caracteres **utf8mb4** (es el valor por defecto de la pestaña Importar; el archivo ya trae `SET NAMES utf8mb4`)
   y que la BD y sus tablas queden con cotejamiento `utf8mb4_unicode_ci`; si no, las tildes y la «ñ» se verán mal.
   El archivo también fija la zona horaria `-05:00` para que los pedidos de ejemplo no queden en el futuro.
4. Copia `config/config.example.php` como `config/config.php` y pon esos valores (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   Deja `BASE_URL` en `''` (cadena vacía), porque el sitio vive en la raíz de `htdocs`.
5. Sube todos los archivos a `/htdocs` con el File Manager en línea o con FileZilla (los datos FTP salen del panel).
   **No subas** `.git`, `.superpowers`, `tests` ni `docs`: no hacen falta.
6. Abre la dirección de tu sitio e inicia sesión.

### Problemas frecuentes

- **Página en blanco o error 500**: revisa `config/config.php` (host, nombre de BD con prefijo, usuario, contraseña, comillas y `;`).
  Confirma también que `database.sql` se importó y que el PHP del hosting sea 8.1 o superior (se elige en el panel).
- **"Too many connections"**: el hosting gratuito limita las conexiones a MySQL. Espera un minuto y recarga; no dejes muchas pestañas
  con actualización automática (el tablero del chef consulta cada 20 s).
- **HTTPS**: activa el certificado SSL gratuito en el panel y usa `https://`. La cookie de sesión se marca `Secure`
  automáticamente cuando detecta HTTPS (incluso detrás del proxy del hosting).
- **Contraseñas de prueba**: los hashes de `database.sql` usan bcrypt con costo 12. Si el PHP del hosting trae otro costo por
  defecto, se vuelven a calcular solos en el primer inicio de sesión (`password_needs_rehash` en `includes/usuarios.php`).

## Publicar en GitHub

El repositorio se llama `pwatarea6`. Después de crearlo vacío en GitHub:

```
git remote add origin https://github.com/<usuario>/pwatarea6.git
git push -u origin main
```

`config/config.php` **no se sube a propósito**: está en `.gitignore` porque contiene las contraseñas de la base de datos.
En el repositorio queda `config/config.example.php` como plantilla.

## Estructura de carpetas

```
/
├─ index.php                 Landing pública (hero, menú destacado)
├─ login.php · registro.php · logout.php · 403.php
├─ database.sql              Tablas, datos de prueba y un usuario por rol
├─ .htaccess                 Sin listado de carpetas; bloquea archivos ocultos, .sql y .md
├─ config/
│  ├─ config.example.php     Plantilla: BD, BASE_URL, nombre y zona horaria
│  └─ config.php             Tu configuración (fuera de Git)
├─ includes/                 Piezas reutilizables, cargadas con require_once
│  ├─ auth.php               usuario_actual(), requerir_login(), requerir_rol(), dashboard_de()
│  ├─ sesion.php             Sesión segura, cabeceras de seguridad
│  ├─ db.php                 conectar(): PDO
│  ├─ funciones.php          e(), CSRF, flash, estado_es(), dinero(), transiciones por rol
│  ├─ usuarios.php · platos.php · pedidos.php   Consultas y reglas de cada tabla
│  ├─ header.php · navbar.php · footer.php      Estructura común de las páginas
│  └─ tarjeta_plato.php · ticket_comanda.php · modal_confirmar.php   Fragmentos de HTML
├─ admin/                    dashboard · platos · usuarios · pedidos
├─ chef/                     cocina.php y api_pedidos.php (JSON para el tablero)
├─ camarero/                 dashboard.php · nuevo_pedido.php
├─ cliente/                  menu.php · mis_pedidos.php
├─ assets/
│  ├─ css/estilos.css        Tokens, componentes y layout por rol
│  └─ js/                    app.js · carrito.js · cocina.js · admin.js
└─ tests/                    Pruebas PHP, informe E2E y capturas (bloqueada por .htaccess)
```

## Cómo se cumple la rúbrica

| Criterio | Dónde se ve |
|---|---|
| **Comprensión de PHP** (include/require, funciones, variables) | Cada página empieza con `require_once __DIR__ . '/../includes/auth.php';`, que a su vez encadena `sesion.php`, `funciones.php`, `db.php` y `usuarios.php`. Las páginas incluyen `header.php`, `navbar.php` y `footer.php` con `require`. Funciones propias en `includes/*.php` (`e()`, `url()`, `crear_pedidos()`, `cambiar_estado()`, `guardar_plato()`...). Variables de plantilla como `$titulo_pagina`, `$scripts_pagina` y `$usuario`. |
| **Comprensión de CSS** | `assets/css/estilos.css`: tokens en `:root` (paleta, tipografía), overrides de Bootstrap, componentes (tarjeta de plato, insignias de estado, chips, ticket de cocina) y layout por rol, con índice al inicio y responsive desde 360 px. |
| **Comprensión de JavaScript** | `carrito.js` (carrito en `localStorage`, filtros por categoría); `cocina.js` (`fetch` con actualización automática cada 20 s y cambio de estado por POST con token CSRF); `app.js` (GSAP + ScrollTrigger dentro de `gsap.matchMedia()`, validación y contadores); `admin.js` (modales de platos y usuarios, confirmación de borrado). |
| **Desarrollo PHP** (estructura de la aplicación) | Una página por pantalla, partes comunes en `includes/`, SQL aislado en `usuarios.php`, `platos.php` y `pedidos.php`, patrón POST-redirect-GET con mensajes `flash()`, roles con `requerir_rol()` y la API JSON `chef/api_pedidos.php`. |
| **Estilos y diseño** | Tema oscuro con acentos dorados ("Noche elegante"), un dashboard distinto por rol (KPI del administrador, kanban del chef, "el pase" del camarero, carrito lateral del cliente), animaciones cortas que respetan `prefers-reduced-motion`. |

## Seguridad

- Contraseñas con `password_hash()` / `password_verify()`, con re-hash automático: `includes/usuarios.php`.
- Todo el SQL con sentencias preparadas (PDO con `ATTR_EMULATE_PREPARES` en `false`): `includes/db.php` y `includes/*.php`.
- Sesión con cookie `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS; `session_regenerate_id(true)` al entrar; caducidad por inactividad: `includes/sesion.php`.
- Token CSRF en todos los formularios POST y en el `fetch` del chef: `campo_csrf()` y `verificar_csrf()` en `includes/funciones.php`.
- Escape de todo dato impreso con `e()` (`htmlspecialchars`); en JS se usa `textContent`.
- Control de acceso por rol en cada página y transiciones de estado validadas en el servidor (`transicion_permitida()`).
- Validación de todo lo que llega del usuario en el servidor (email, precio, cantidades de 1 a 20, rol, estado).
- Mensajes de login genéricos y comparación contra un hash señuelo; los errores de BD van al log, no a pantalla.
- Cabeceras `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy`; `.htaccess` que bloquea `config/`, `includes/`, `tests/`, `.git`, `.sql` y `.md`.

## Pruebas

Desde la raíz del proyecto, con la BD importada y MySQL encendido:

```
C:\xampp\php\php.exe tests/test_db.php
C:\xampp\php\php.exe tests/test_funciones.php
C:\xampp\php\php.exe tests/test_auth.php
C:\xampp\php\php.exe tests/test_pedidos.php
C:\xampp\php\php.exe tests/test_platos.php
C:\xampp\php\php.exe tests/test_api_cocina.php
```

Cada script imprime `OK` o `FALLA` por comprobación y termina con código 1 si algo falla. Para revisar la sintaxis: `php -l archivo.php`.

El recorrido E2E con playwright-cli (cliente pide, chef prepara, camarero entrega, el cliente ve el historial, el administrador lo ve,
403 entre roles, escape de `<b>x</b>` y capturas a 1280 px y 375 px) queda descrito en `tests/e2e.md`, con las capturas en `tests/capturas/`.

## Créditos y notas

- Las fotos de los platos vienen de Unsplash y se cargan por URL (no hay imágenes locales); el administrador puede cambiar la URL de cada plato.
- "Ébano" es un restaurante ficticio creado para esta tarea.
