# Sistema de Gestión de Restaurante "Ébano": plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sistema web de restaurante con 4 roles (Administrador, Chef, Camarero, Cliente). Se ejecuta en XAMPP/Laragon y en InfinityFree.

**Architecture:** PHP multipágina. Las piezas comunes van en `includes/` y se cargan con `require_once`. Toda la lógica de datos está en funciones con nombre claro (`includes/platos.php`, `pedidos.php`, `usuarios.php`). Las páginas de cada rol solo validan el rol, llaman a esas funciones y pintan HTML. JS sin build: carrito, tablero del chef con `fetch` y GSAP.

**Tech Stack:** PHP 8 (PDO MySQL) · MySQL/MariaDB · Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 (jsDelivr) · GSAP 3.12.5 + ScrollTrigger (cdnjs) · Google Fonts Cormorant Garamond + Manrope · playwright-cli para pruebas E2E.

**Spec:** `docs/superpowers/specs/2026-11-23-restaurante-design.md`

## Global Constraints

- Los `CREATE TABLE` de `roles`, `users`, `dishes` y `orders` y el `INSERT` de roles se copian literales del enunciado. Los extras se agregan solo con `ALTER TABLE` al final.
- Nombres de roles en la BD: `Administrator`, `Chef`, `Waiter`, `Client`. Estados en la BD: `Pending`, `Preparing`, `Ready to serve`, `Served`.
- Usuarios de prueba: `admin@ebano.com`, `chef@ebano.com`, `camarero@ebano.com` y `cliente@ebano.com`, todos con contraseña `123456` guardada con `password_hash`. Mínimo de 6 caracteres al registrarse.
- Todo comentario y texto de UI va en español. Cada archivo PHP empieza con un bloque de comentario que explica qué hace.
- Paleta: fondo `#0E0E11`, superficie `#16161B`, elevada `#1E1E25`, borde `#2A2A33`, texto `#ECE6D8`, secundario `#A39E93`, oro `#C9A45C`, oro claro `#E2C78C`.
- Todo SQL usa sentencias preparadas. Todo dato impreso pasa por `e()`. Todo POST verifica CSRF. Cada página llama a `requerir_rol()` o `requerir_login()` antes de imprimir nada.
- Las animaciones GSAP duran ≤ 0.6 s y se envuelven en `gsap.matchMedia()` con `(prefers-reduced-motion: no-preference)`.
- `config/config.php` va en `.gitignore`. Al repositorio se sube `config/config.example.php`.
- PHP local: `C:\xampp\php\php.exe`. Proyecto servido en `http://localhost/pwatarea6/` mediante un alias o enlace a esta carpeta.

## Review Focus

1. Un usuario con sesión de un rol abre la URL de otro rol → debe recibir 403, no ver la página. *(Task 3 + Task 9 E2E)*
2. POST que cambia el estado saltándose pasos (Chef `Pending`→`Served`, Camarero `Pending`→`Served`) → rechazado en el servidor. *(Task 2 test)*
3. Carrito con cantidad 0, negativa, mayor que 20 o con un `dish_id` inexistente u oculto → se rechaza y no se inserta nada (transacción). *(Task 5 test)*
4. Borrar un plato o usuario que tiene pedidos → mensaje amable, sin error de FK en pantalla. *(Task 8)*
5. Nombre de plato o usuario con `<script>` → se muestra como texto, también en el JSON del tablero del chef pintado con `textContent`. *(Task 2 test de `e()` + Task 9 E2E)*

---

### Task 1: Base de datos, configuración y conexión

**Files:**
- Create: `database.sql`, `config/config.example.php`, `config/config.php`, `includes/db.php`, `.gitignore`, `.htaccess`, `config/.htaccess`, `includes/.htaccess`, `tests/test_db.php`

**Interfaces:**
- Produces: constantes `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL` (por ejemplo `/pwatarea6`), `APP_NAME` (`'Ébano'`). `conectar(): PDO`, singleton con `static`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`, charset `utf8mb4`.

- [ ] **Step 1:** `git init`. Crear `.gitignore` con `config/config.php`. Crear `config/.htaccess` e `includes/.htaccess` con `Require all denied`, y el `.htaccess` raíz con `Options -Indexes` y `ErrorDocument 403`.
- [ ] **Step 2:** Escribir `database.sql`:
  - Tablas literales.
  - `ALTER TABLE` de la sección 5 del spec. Incluye la FK `orders.waiter_id → users(id) ON DELETE SET NULL`.
  - 12 platos en 4 categorías (Entradas, Principales, Postres, Bebidas) con URLs `https://images.unsplash.com/photo-...?w=800&q=70`.
  - Los 4 usuarios de prueba más 5 clientes extra y unos 15 pedidos en todos los estados, con `created_at` repartido en las últimas horas.
  - Hashes generados con `C:\xampp\php\php.exe -r "echo password_hash('123456', PASSWORD_DEFAULT);"`.
- [ ] **Step 3:** Arrancar MySQL de XAMPP, crear la BD `restaurante` con `utf8mb4_unicode_ci` e importar: `C:\xampp\mysql\bin\mysql.exe -u root restaurante < database.sql`.
- [ ] **Step 4:** Escribir `tests/test_db.php`. Verifica que `conectar()` devuelve PDO, que `SELECT COUNT(*) FROM roles` = 4, que existen los 4 emails de prueba y que `password_verify('123456', hash)` da true para cada uno.
- [ ] **Step 5:** Ejecutar `C:\xampp\php\php.exe tests/test_db.php` → se espera `OK` en cada línea y código de salida 0.
- [ ] **Step 6:** Commit `feat: base de datos, configuración y conexión PDO`.

### Task 2: Funciones utilitarias y reglas de negocio (con pruebas)

**Files:**
- Create: `includes/funciones.php`, `tests/test_funciones.php`

**Interfaces:**
- Produces:
  - `e(?string $s): string`
  - `url(string $ruta = ''): string` (antepone `BASE_URL`)
  - `redirigir(string $ruta): never`
  - `csrf_token(): string`
  - `campo_csrf(): string` (`<input type="hidden" name="csrf">`)
  - `verificar_csrf(): void` (usa `hash_equals`; responde 403 si falla; acepta POST `csrf` o la cabecera `X-CSRF-Token`)
  - `flash(string $tipo, string $mensaje): void`
  - `mostrar_flash(): string`
  - `estado_es(string $estado): string`
  - `clase_estado(string $estado): string` (`'pendiente'|'preparando'|'listo'|'servido'`)
  - `dinero(float $v): string` (`'$12.50'`)
  - `transicion_permitida(string $rol, string $desde, string $hacia): bool`
  - `rol_es(string $rol): string` (Administrador, Chef, Camarero, Cliente)
  - constante `ESTADOS` (array de los 4 estados en orden)

- [ ] **Step 1: Escribir `tests/test_funciones.php`** con un mini helper `comprobar(string $nombre, bool $ok)`. Debe afirmar:
  - `e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;'`
  - `estado_es('Ready to serve') === 'Listo para servir'` y `estado_es('Served') === 'Entregado'`
  - `dinero(12.5) === '$12.50'`
  - `transicion_permitida('Chef','Pending','Preparing')` y `('Chef','Preparing','Ready to serve')` son true
  - `('Chef','Pending','Served')`, `('Chef','Ready to serve','Served')`, `('Waiter','Pending','Served')`, `('Client','Pending','Preparing')` y `('Chef','Pending','Pending')` son false
  - `('Waiter','Ready to serve','Served')` es true
  - `('Administrator','Served','Pending')` es true
- [ ] **Step 2:** Ejecutar `C:\xampp\php\php.exe tests/test_funciones.php` → FAIL (falta el archivo).
- [ ] **Step 3:** Implementar `includes/funciones.php`. Las transiciones se definen como un array de reglas `[rol => [desde => [hacia...]]]`. El admin acepta cualquier par de estados distintos.
- [ ] **Step 4:** Ejecutar de nuevo → todos `OK`.
- [ ] **Step 5:** Commit `feat: funciones utilitarias y reglas de transición`.

### Task 3: Sesión, autenticación, layout y estilos base

**Files:**
- Create: `includes/sesion.php`, `includes/auth.php`, `includes/usuarios.php`, `includes/header.php`, `includes/navbar.php`, `includes/footer.php`, `login.php`, `registro.php`, `logout.php`, `403.php`, `assets/css/estilos.css`, `assets/js/app.js`

**Interfaces:**
- Consumes: `conectar()` y todo `funciones.php`.
- Produces:
  - `iniciar_sesion_segura(): void`, llamado desde `header.php` y desde las páginas antes de cualquier salida. Cookie HttpOnly, SameSite=Lax, Secure si hay HTTPS.
  - `iniciar_sesion_usuario(array $u): void`: `session_regenerate_id(true)`, guarda `usuario_id` y `rol`.
  - `cerrar_sesion(): void`
  - `usuario_actual(): ?array` con `['id','username','email','role']`
  - `requerir_login(): array`
  - `requerir_rol(array $roles): array`: si no hay sesión redirige a login; si el rol no coincide, código 403 e `include '403.php'`; `exit`.
  - `dashboard_de(string $rol): string`: Administrator → `admin/dashboard.php`, Chef → `chef/cocina.php`, Waiter → `camarero/dashboard.php`, Client → `cliente/menu.php`.
  - En `usuarios.php`: `buscar_usuario_por_email(string $email): ?array`, `autenticar(string $email, string $clave): ?array`, `registrar_cliente(string $nombre, string $email, string $clave): int`, `obtener_usuarios(): array`, `obtener_clientes(): array`, `obtener_roles(): array`, `guardar_usuario(array $datos, ?int $id = null): int`, `eliminar_usuario(int $id, int $id_actual): string` (devuelve `'ok'|'propio'|'tiene_pedidos'`), `email_existe(string $email, ?int $excepto = null): bool`.
  - Variables de plantilla que lee `header.php`: `$titulo_pagina` (string) y `$clase_body` (string, por ejemplo `rol-chef`).
- [ ] **Step 1:** `estilos.css`:
  - Tokens en `:root`, incluidos los overrides de `--bs-body-bg`, `--bs-body-color`, `--bs-border-color` y `--bs-primary-rgb` (oro).
  - Componentes `.btn-oro`, `.card-ebano`, `.form-control` oscuros, `.badge-estado.{pendiente,preparando,listo,servido}`, navbar con vidrio y desenfoque, y foco visible dorado.
  - `@media (prefers-reduced-motion)`.
- [ ] **Step 2:** Layout y `login.php`: dos columnas, una imagen de ambiente y un formulario, con la tabla de usuarios de prueba plegable bajo "Cuentas de demostración". Con credenciales correctas redirige a `dashboard_de(rol)`. Si fallan, muestra "Correo o contraseña incorrectos". Si ya hay sesión, redirige.
- [ ] **Step 3:** `registro.php` valida nombre de 2 a 50 caracteres, email válido y único, clave ≥ 6 y confirmación igual, y crea siempre el rol Client. `logout.php` solo acepta POST con CSRF. `403.php` es una página temática con un botón "Volver a mi panel".
- [ ] **Step 4:** Verificar con el servidor local: `curl -s -o NUL -w "%{http_code}" http://localhost/pwatarea6/login.php` → 200. Ejecutar `php -l` en cada archivo nuevo → "No syntax errors".
- [ ] **Step 5:** Commit `feat: autenticación, sesiones seguras y layout base`.

### Task 4: Landing pública

**Files:**
- Create: `index.php`
- Modify: `assets/js/app.js`, `assets/css/estilos.css`

**Interfaces:**
- Consumes: `obtener_platos(true)` de la Task 5. Si la Task 5 aún no existe, se crea en esta task y la Task 5 la reutiliza.
- [ ] **Step 1:** `index.php` incluye:
  - Hero a pantalla completa con foto de ambiente, título serif "Ébano — Cocina de autor" y CTA "Ver la carta" / "Iniciar sesión".
  - Sección "La carta" con 6 platos destacados en tarjetas.
  - Franja de 3 valores con iconos.
  - Footer.
- [ ] **Step 2:** `app.js`:
  - `animarEntrada()` con un timeline del hero y stagger de `.anim-item`.
  - `revelarAlScroll()` con ScrollTrigger `start: 'top 85%'` y `once: true` sobre `.revelar`.
  - `contarNumeros()` sobre `[data-contar]`.
  - Todo dentro de `gsap.matchMedia()`.
- [ ] **Step 3:** Verificar en el navegador que no hay errores de consola y que se ve bien a 1280 px y a 375 px.
- [ ] **Step 4:** Commit `feat: landing pública con animaciones GSAP`.

### Task 5: Cliente: menú, carrito y mis pedidos

**Files:**
- Create: `includes/platos.php`, `includes/pedidos.php`, `cliente/menu.php`, `cliente/mis_pedidos.php`, `assets/js/carrito.js`, `includes/tarjeta_plato.php` (parcial reutilizable que recibe `$plato`), `tests/test_pedidos.php`

**Interfaces:**
- Produces:
  - `obtener_platos(bool $solo_disponibles = true): array`
  - `obtener_plato(int $id): ?array`
  - `categorias_menu(): array`
  - `crear_pedidos(int $cliente_id, array $items, ?int $camarero_id = null): int`, donde `$items` es `[['dish_id'=>int,'quantity'=>int], ...]`. Usa una transacción. Lanza `InvalidArgumentException` si el array está vacío, si una cantidad cae fuera de 1–20 o si el plato no existe o no está disponible. Devuelve el número de filas insertadas.
  - `pedidos_de_cliente(int $cliente_id): array`, con JOIN a dishes y orden `created_at DESC`.
  - `cambiar_estado(int $pedido_id, string $nuevo, array $usuario): bool`. Usa `transicion_permitida`. Si el rol es Waiter y el pedido no tiene `waiter_id`, se lo asigna.
- [ ] **Step 1: Escribir `tests/test_pedidos.php`** contra la BD local, dentro de una transacción con rollback al final. Debe afirmar:
  - `crear_pedidos(clienteId, [['dish_id'=>1,'quantity'=>2]]) === 1`
  - `[]`, `quantity` 0, `quantity` 21 y `dish_id` 99999 lanzan `InvalidArgumentException` y no cambian `COUNT(*)`
  - `cambiar_estado(id,'Served',chef)` → false y el estado sigue `Pending`
  - `cambiar_estado(id,'Preparing',chef)` → true
- [ ] **Step 2:** Ejecutar `C:\xampp\php\php.exe tests/test_pedidos.php` → FAIL.
- [ ] **Step 3:** Implementar `platos.php` y `pedidos.php`.
- [ ] **Step 4:** Ejecutar de nuevo → todos `OK`.
- [ ] **Step 5:** Páginas del cliente:
  - `menu.php` (`requerir_rol(['Client'])`) tiene chips de categoría que filtran con JS, una grilla de `tarjeta_plato.php` y un offcanvas de carrito.
  - `carrito.js` maneja el estado en un objeto `{dishId: {nombre, precio, cantidad}}`. Lo guarda en `localStorage` con try/catch, renderiza con `textContent`, calcula el total y antes de enviar el formulario POST escribe los `items[ID]=cantidad` ocultos.
  - `mis_pedidos.php` muestra los activos con una línea de progreso de 4 pasos y el historial (Entregados) en una tabla.
  - Patrón PRG con `flash` "¡Pedido enviado a cocina!".
- [ ] **Step 6:** Commit `feat: menú del cliente, carrito y seguimiento de pedidos`.

### Task 6: Chef: tablero de cocina

**Files:**
- Create: `chef/cocina.php`, `chef/api_pedidos.php`, `assets/js/cocina.js`
- Modify: `includes/pedidos.php`

**Interfaces:**
- Produces: `pedidos_cocina(): array`, que trae los estados `Pending`, `Preparing` y `Ready to serve` con plato, cliente, cantidad, `created_at` y `minutos` (`TIMESTAMPDIFF`).
- `api_pedidos.php` (`requerir_rol(['Chef','Administrator'])`):
  - GET → JSON `{pedidos:[...]}`.
  - POST JSON `{id, estado}` con la cabecera `X-CSRF-Token` → `{ok:bool, mensaje:string}`.
  - Siempre `Content-Type: application/json`.
- [ ] **Step 1:** `cocina.php` pinta el tablero inicial en PHP: 3 columnas con contador cada una. Cada ticket muestra el número de comanda, la hora, los minutos, la cantidad × el plato, el cliente y un botón con la siguiente acción ("Empezar" o "Listo").
- [ ] **Step 2:** `cocina.js`:
  - `cargarPedidos()` cada 20 s con `fetch`.
  - `renderizarTablero(pedidos)` construye nodos con `textContent`.
  - `avanzarEstado(id, estado)` hace el POST y vuelve a renderizar.
  - Los tickets nuevos (id no visto antes) reciben un pulso GSAP.
  - Muestra un indicador "Actualizado hace X s".
- [ ] **Step 3:** Verificar que `curl` sin sesión a `chef/api_pedidos.php` responde 302 a login, y que en el navegador avanzar un ticket lo mueve de columna.
- [ ] **Step 4:** Commit `feat: tablero de cocina del chef con actualización automática`.

### Task 7: Camarero: panel y nuevo pedido

**Files:**
- Create: `camarero/dashboard.php`, `camarero/nuevo_pedido.php`
- Modify: `includes/pedidos.php`

**Interfaces:**
- Produces:
  - `pedidos_de_camarero(int $camarero_id): array`, con los activos primero.
  - `pedidos_sin_asignar(): array`, con los no entregados y `waiter_id IS NULL`.
  - `pedidos_listos(): array`
  - `tomar_pedido(int $pedido_id, int $camarero_id): bool`, que solo funciona si `waiter_id IS NULL`.
- [ ] **Step 1:** `dashboard.php` (`requerir_rol(['Waiter'])`):
  - Arriba, una franja dorada "Listos para servir" con el botón "Entregar".
  - Pestañas "Mis pedidos" y "Sin asignar" (con el botón "Tomar").
  - KPI: asignados activos, listos y entregados hoy.
  - Todas las acciones van por POST con CSRF y `cambiar_estado`/`tomar_pedido`.
- [ ] **Step 2:** `nuevo_pedido.php` tiene un select de cliente (`obtener_clientes()`) y reutiliza la grilla de tarjetas y `carrito.js`. Al enviar llama a `crear_pedidos($cliente_id, $items, $camarero['id'])`. El servidor valida que el `cliente_id` sea un usuario con rol Client.
- [ ] **Step 3:** Verificar en el navegador: crear un pedido para "cliente@ebano.com" → aparece en "Mis pedidos" del camarero y en la cocina.
- [ ] **Step 4:** Commit `feat: panel del camarero y pedidos a nombre de clientes`.

### Task 8: Administrador: dashboard, platos, usuarios y pedidos

**Files:**
- Create: `admin/dashboard.php`, `admin/platos.php`, `admin/usuarios.php`, `admin/pedidos.php`
- Modify: `includes/platos.php`, `includes/pedidos.php`

**Interfaces:**
- Produces:
  - `guardar_plato(array $datos, ?int $id = null): int`, que valida un nombre de 2 a 50 caracteres, una descripción de ≤ 255, un precio entre 0.01 y 9999.99, una categoría de la lista y una imagen que sea URL http(s) válida o vacía.
  - `eliminar_plato(int $id): string`, que devuelve `'ok'|'tiene_pedidos'`.
  - `alternar_disponible(int $id): void`
  - `estadisticas_admin(): array` con `['pedidos_hoy','en_cocina','ingresos','platos_activos','por_estado'=>[estado=>n],'top_platos'=>[...]]`
  - `todos_los_pedidos(?string $estado = null, ?string $buscar = null): array`
  - `eliminar_pedido(int $id): void`
- [ ] **Step 1:** `dashboard.php` muestra 4 KPI con `data-contar`, barras CSS de pedidos por estado (ancho calculado en PHP), top 5 platos y accesos rápidos.
- [ ] **Step 2:** `platos.php` muestra una tabla con miniatura y un modal Bootstrap para crear y editar (lo rellena JS desde `data-*`). Tiene un interruptor de disponible, y para eliminar pide confirmación. Si el plato tiene pedidos, muestra el flash "Tiene pedidos: se ocultó del menú en su lugar" y lo oculta.
- [ ] **Step 3:** `usuarios.php` es un CRUD con select de rol. La contraseña es opcional al editar. Se bloquea borrarse a sí mismo y cambiar su propio rol, y los mensajes usan los códigos que devuelve `eliminar_usuario`.
- [ ] **Step 4:** `pedidos.php` tiene filtros por estado y búsqueda por cliente o plato (GET), un select para cambiar a cualquier estado y la opción de eliminar con confirmación.
- [ ] **Step 5:** Verificar en el navegador:
  - Crear un plato → aparece en el menú del cliente.
  - Intentar borrar un plato con pedidos → mensaje amable.
  - Intentar borrarse a sí mismo → bloqueado.
- [ ] **Step 6:** Commit `feat: panel de administración completo`.

### Task 9: README, pruebas E2E por rol, seguridad y verificación final

**Files:**
- Create: `README.md`, `tests/e2e.md` (registro de lo probado y capturas en `tests/capturas/`)

- [ ] **Step 1:** `README.md` incluye:
  - Descripción, tecnologías y tabla de usuarios de prueba.
  - Instalación en XAMPP y en Laragon paso a paso.
  - Despliegue en InfinityFree: crear la cuenta y el hosting, crear la BD, importar con phpMyAdmin, editar `config.php` y subir por File Manager o FileZilla a `htdocs/`. Ahí `BASE_URL` queda `''`.
  - Estructura de carpetas explicada y mapa de la rúbrica a archivos (dónde se ve cada criterio).
- [ ] **Step 2:** E2E con playwright-cli contra `http://localhost/pwatarea6/`:
  - El cliente hace un pedido de 2 platos.
  - El chef lo pasa a Preparando y luego a Listo.
  - El camarero lo entrega.
  - El cliente lo ve en el historial.
  - El admin lo ve en pedidos.
  - Por cada rol, abrir una URL de otro rol → 403.
  - Crear un plato con el nombre `<b>x</b>` → se muestra literal.
  - Capturas a 1280 px y a 375 px de cada dashboard.
- [ ] **Step 3:** Revisión con la checklist de security-and-hardening:
  - `grep` de `query(` con variables.
  - `echo $` sin `e()`.
  - Formularios POST sin `campo_csrf()`.
  - Páginas sin `requerir_`.
  - Corregir lo que aparezca.
- [ ] **Step 4:** verification-before-completion: ejecutar los 3 `tests/*.php`, `php -l` en todos los `.php` y el flujo E2E completo, y reportar los resultados reales.
- [ ] **Step 5:** Commit `docs: README e informe de pruebas`.
