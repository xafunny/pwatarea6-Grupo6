# Diseño — Sistema de Gestión de Restaurante Online (Tarea 6, Grupo 6)

Fecha: 2026-11-23 · Estado: borrador para aprobación

## 1. Intención y por qué

Tarea universitaria de Programación Web Avanzada. Objetivo del grupo: **nota máxima (20/20) en los 5 criterios** de la rúbrica:
comprensión de PHP, CSS y JS, desarrollo PHP, y estilos/diseño. El sistema tiene que funcionar, ser seguro, verse muy bien y
ser **fácil de defender** ante el profesor: el código va comentado en español y la estructura se puede explicar en voz alta.

## 2. Objetivos, no-objetivos, anti-objetivos

**Objetivos**
- Los 4 roles (Administrador, Chef, Camarero, Cliente) con sus permisos, verificados en el servidor en cada página.
- Corre en XAMPP/Laragon y en InfinityFree con solo editar un archivo de configuración.
- PHP organizado con `include`/`require`, funciones propias y variables con nombres claros. La rúbrica lo evalúa de forma explícita.
- Interfaz "Noche elegante": oscura, con acentos dorados y un dashboard distinto para cada rol.
- `database.sql` con tablas, datos de prueba y un usuario por rol. `README.md` con la instalación local y en el hosting.

**No-objetivos**
- Pagos reales, envío de correos, recuperación de contraseña, multi-restaurante y reservas.
- Frameworks PHP (Laravel, etc.) y build tools de JS (npm, bundlers). Todo funciona subiendo los archivos.

**Anti-objetivos** (fallaría aunque "funcione")
- Que parezca una plantilla de Bootstrap sin personalizar.
- Que una persona pueda entrar a la página de otro rol escribiendo la URL.
- SQL concatenado, contraseñas en texto plano o datos del usuario impresos sin escapar.
- Código que el grupo no sepa explicar: nada de "magia", abstracciones de más ni librerías innecesarias.

## 3. Restricciones (fijadas por el profesor)

- PHP + MySQL (phpMyAdmin), HTML, Bootstrap 5, CSS propio y JavaScript.
- Las tablas `roles`, `users`, `dishes` y `orders` del documento, **sin romperlas**.
- Hosting gratuito (InfinityFree): ahí la base de datos se crea desde el panel, tiene un prefijo en el nombre y el host es distinto de `localhost`.
- Repositorio en GitHub llamado `pwatarea6`.

## 4. Enfoques considerados

| Enfoque | Qué implica | Contras |
|---|---|---|
| **A. PHP multipágina con includes (ELEGIDO)** | Una página `.php` por pantalla. Partes comunes en `includes/` vía `require_once`. Los formularios hacen POST a la misma página (patrón PRG). JS para el carrito, los modales, las animaciones y la actualización automática del tablero del chef | Algo de repetición entre páginas, que se resuelve con funciones |
| B. API PHP en JSON + frontend en JS (tipo SPA) | Toda la UI se arma con `fetch` | Casi no muestra `include`/`require` ni PHP generando HTML, que es justo lo que pide la rúbrica. Es más difícil de defender |
| C. Mini‑MVC con router frontal | `index.php?r=...`, controladores y vistas | Se ve profesional, pero es una sobre‑ingeniería para la tarea y tiene más piezas que explicar. En InfinityFree un `.htaccess` mal hecho rompe las rutas |

**Elegido A** porque muestra de forma directa los conceptos que califica la rúbrica, es el más fácil de defender y funciona en cualquier hosting gratuito.
Ya hay JS de sobra para el criterio de JavaScript: carrito, validación, `fetch` en el tablero del chef, GSAP y filtros.

## 5. Base de datos

Los `CREATE TABLE` y el `INSERT` de roles del documento se copian **sin cambios**. Al final de `database.sql` se agregan columnas
**opcionales** con `ALTER TABLE`. Todas tienen un valor por defecto o aceptan NULL, así que cualquier INSERT del enunciado sigue funcionando:

| Columna agregada | Para qué |
|---|---|
| `dishes.category VARCHAR(30) DEFAULT 'Principal'` | Agrupar el menú: Entradas, Principales, Postres y Bebidas |
| `dishes.image VARCHAR(255) NULL` | URL de la foto del plato |
| `dishes.available TINYINT(1) NOT NULL DEFAULT 1` | Ocultar del menú un plato que ya tiene pedidos, porque la FK no deja borrarlo |
| `orders.waiter_id INT NULL` + FK a `users(id)` | Saber qué camarero tiene asignado el pedido |
| `orders.created_at DATETIME DEFAULT CURRENT_TIMESTAMP` | Ordenar el historial y mostrar la hora |

Decisiones:
- **Un pedido de varios platos se guarda como varias filas** de `orders`, una por plato. En la cocina eso es natural: cada fila es una *comanda*. El carrito inserta todas las filas en una **transacción** PDO.
- Los estados se quedan en inglés en la BD, tal como en el ENUM. Una función PHP `estado_es()` los traduce: Pendiente, En preparación, Listo para servir y Entregado.
- No se usa `CREATE DATABASE` dentro de la parte importable, porque InfinityFree no lo permite. El README explica cómo crear la BD `restaurante` en local.

**Datos de prueba**: unos 12 platos en 4 categorías, con fotos de Unsplash por URL. Se incluyen 6 clientes en total contando el de prueba y pedidos de ejemplo en todos los estados, para que los dashboards no se vean vacíos.

**Usuarios de prueba** (se guardan con `password_hash`; los hashes se generan con PHP de verdad):

| Rol | Email | Contraseña |
|---|---|---|
| Administrador | admin@ebano.com | 123456 |
| Chef | chef@ebano.com | 123456 |
| Camarero | camarero@ebano.com | 123456 |
| Cliente | cliente@ebano.com | 123456 |

(Decisión del grupo: misma contraseña `123456` para todos los usuarios de prueba. Mínimo de contraseña en registro: 6 caracteres.)

## 6. Roles y permisos (se aplican en el servidor)

| Acción | Admin | Chef | Camarero | Cliente |
|---|---|---|---|---|
| Ver menú | ✔ | ✔ | ✔ | ✔ |
| CRUD de platos | ✔ | | | |
| CRUD de usuarios y cambio de rol | ✔ | | | |
| Ver todos los pedidos | ✔ | ✔ (cocina) | ✔ (listos y sin asignar) | |
| Pendiente → En preparación → Listo | ✔ | ✔ | | |
| Listo → Entregado | ✔ | | ✔ | |
| Crear pedido a nombre de un cliente | ✔ | | ✔ (queda asignado a él) | |
| Tomar un pedido sin asignar | | | ✔ | |
| Hacer pedido propio, ver estado e historial | | | | ✔ |
| Eliminar un pedido / cambiarlo a cualquier estado | ✔ | | | |

- Los estados solo avanzan; nadie salvo el admin los hace retroceder. El cliente no cancela pedidos, porque el ENUM no tiene estado "cancelado".
- El tablero del chef muestra Pendiente, En preparación y Listo; lo entregado sale del tablero. El camarero ve tres listas: los pedidos asignados a él (en cualquier estado), los pedidos sin asignar y los que están listos para servir.
- Al editar un usuario, el admin puede dejar la contraseña vacía para conservarla.

- Cada página empieza con `requerir_rol(['Administrator'])` o el rol que corresponda. Si no hay sesión, redirige a `login.php`. Si el rol no coincide, devuelve **403** con una página bonita.
- Las transiciones de estado se validan en el servidor con una tabla de transiciones permitidas por rol. No basta con ocultar el botón.
- El admin no puede borrarse a sí mismo ni quitarse el rol de admin. Tampoco se puede borrar un usuario o plato que ya tiene pedidos: se avisa y, en el caso del plato, se ofrece "ocultar".
- Los clientes se registran solos en `registro.php` y siempre quedan con rol Cliente. Los usuarios de personal (chef, camarero, admin) los crea el admin.

## 7. Estructura de archivos

```
/
├─ index.php                 Landing pública: hero, menú destacado y llamada a iniciar sesión
├─ login.php · registro.php · logout.php · 403.php
├─ config/config.php         Credenciales BD + URL base (lo ÚNICO que cambia en el hosting)
├─ config/config.example.php
├─ includes/
│  ├─ db.php                 Función conectar() → PDO (excepciones, prepared statements reales)
│  ├─ sesion.php             Inicio de sesión seguro, iniciar_sesion_usuario(), cerrar_sesion()
│  ├─ auth.php               usuario_actual(), requerir_login(), requerir_rol(), puede()
│  ├─ funciones.php          e() (escape XSS), csrf_token()/verificar_csrf(), flash(), estado_es(), dinero()
│  ├─ header.php · footer.php · navbar.php (menú según el rol)
├─ admin/    dashboard.php · platos.php · usuarios.php · pedidos.php
├─ chef/     cocina.php · api_pedidos.php (JSON para la actualización automática)
├─ camarero/ dashboard.php · nuevo_pedido.php
├─ cliente/  menu.php · mis_pedidos.php
├─ assets/css/estilos.css    Tokens de diseño + personalización de Bootstrap
├─ assets/js/app.js          Utilidades, GSAP y validación
├─ assets/js/carrito.js      Carrito (cliente y camarero)
├─ assets/js/cocina.js       Tablero del chef
├─ database.sql · README.md
```

## 8. Diseño visual — "Noche elegante"

- **Marca**: restaurante ficticio **"Ébano — Cocina de autor"** (decisión mía, se puede cambiar).
- **Paleta** (variables CSS en `:root`, que sobreescriben las variables de Bootstrap `--bs-*`):
  fondo `#0E0E11`, superficie `#16161B`, superficie elevada `#1E1E25`, borde `#2A2A33`,
  texto `#ECE6D8`, texto secundario `#A39E93`, **oro** `#C9A45C` (acento único), oro claro `#E2C78C`.
  Colores de estado apagados para que combinen con lo oscuro: ámbar (Pendiente), cobre (En preparación), verde salvia (Listo) y gris piedra (Entregado).
- **Tipografía** (Google Fonts): *Cormorant Garamond* para títulos y precios, que da el toque elegante, y *Manrope* para el texto y la UI, que se lee bien en tablas.
- **Contraste**: todo texto cumple WCAG AA sobre fondo oscuro. El oro no se usa para texto pequeño sobre superficies claras.
- **Tarjetas de plato**: foto en proporción 4:3 con degradado inferior, categoría en versalitas doradas, nombre en serif, precio grande y botón "Añadir" en forma de píldora. Al pasar el mouse la foto hace un zoom suave y el borde se ilumina en oro.
- **Dashboards por rol** (cada uno con su propia composición, no la misma plantilla repetida):
  - *Admin*: "sala de control" con 4 KPI (pedidos de hoy, en cocina, ingresos y platos activos), gráfico de barras CSS de pedidos por estado, top de platos y accesos a la gestión.
  - *Chef*: tablero **kanban de comandas** a pantalla completa con 3 columnas (Pendiente, En preparación, Listo). Cada comanda parece un ticket de cocina con borde dentado, hora y minutos transcurridos. Se actualiza solo cada 20 s vía `fetch`.
  - *Camarero*: arriba, una franja "Listos para servir"; luego "Mis pedidos asignados" y "Sin asignar". Tiene un botón grande "Nuevo pedido".
  - *Cliente*: menú con filtros por categoría y un carrito lateral (offcanvas de Bootstrap). En "Mis pedidos", cada pedido activo tiene una línea de progreso de 4 pasos y debajo va el historial.
- **Animaciones (GSAP 3 + ScrollTrigger, CDN cdnjs)**: entrada escalonada de tarjetas y KPI, aparición de secciones al hacer scroll en la landing y en el menú, contador animado en los KPI y un "pulso" cuando llega una comanda nueva al tablero del chef. Todas son cortas (≤ 0.6 s) y se desactivan con `prefers-reduced-motion` usando `gsap.matchMedia()`.
- **Responsive**: funciona desde 360 px. Los dashboards pasan a una sola columna y el kanban se desplaza en horizontal en móvil.

## 9. Seguridad

- `password_hash()` / `password_verify()` (PASSWORD_DEFAULT).
- PDO con `ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION` y **consultas preparadas en todas partes**.
- Sesión: cookie `HttpOnly`, `SameSite=Lax` y `Secure` cuando hay HTTPS. Se usa `session_regenerate_id(true)` al iniciar sesión y la sesión se destruye por completo al salir.
- **CSRF**: token por sesión en todos los formularios POST y en las llamadas `fetch` que modifican datos.
- **XSS**: todo dato impreso pasa por `e()` (`htmlspecialchars` con ENT_QUOTES y UTF‑8). En JS se usa `textContent`, no `innerHTML` con datos.
- Validación en el servidor de todo input (email válido, precio > 0, cantidad entre 1 y 20, rol existente, estado permitido). La validación en JS es solo para comodidad del usuario.
- Mensajes de login genéricos ("credenciales incorrectas"). Los errores de BD se registran en el log del servidor y al usuario se le muestra un mensaje amable, sin mostrar el SQL.
- `.htaccess` que bloquea el acceso directo a `config/` e `includes/` (cada archivo también corta si se abre directo).

## 10. Pruebas y verificación

- Prueba manual automatizada con **playwright-cli** en `http://localhost/...`: se inicia sesión con cada uno de los 4 usuarios y se recorre su flujo (cliente pide → chef prepara → camarero entrega → admin lo ve). Además se intenta entrar a páginas de otros roles para confirmar el 403, junto con capturas en escritorio y en móvil.
- Revisión de seguridad con la checklist de *security-and-hardening* antes de cerrar.

## 11. Despliegue

- **Local**: copiar la carpeta a `C:\xampp\htdocs\pwatarea6` (o `C:\laragon\www\pwatarea6`), crear la BD `restaurante` en phpMyAdmin, importar `database.sql` y abrir `http://localhost/pwatarea6`.
- **InfinityFree**: crear la BD en el panel, importar `database.sql` con su phpMyAdmin, editar `config/config.php` con el host `sqlXXX.infinityfree.com`, el usuario y la BD con prefijo, y subir los archivos a `htdocs/` por el File Manager o por FTP (FileZilla).
- `config/config.php` va en `.gitignore`. En el repositorio se sube `config.example.php`, para no publicar contraseñas en GitHub.

## 12. Decisiones mías que conviene revisar

1. El nombre de la marca: "Ébano".
2. Las columnas extra de la sección 5.
3. Los clientes pueden registrarse solos.
4. Un camarero puede "tomar" pedidos sin asignar y marcar como entregado cualquier pedido listo. Al entregarlo, el pedido queda asignado a él.
5. Las contraseñas de prueba.
6. Las fotos se cargan desde Unsplash por URL, sin archivos locales. El admin puede cambiar la URL.

## 13. Lo que queda a criterio de quien lo implemente

Los textos exactos de la interfaz, los platos de ejemplo concretos, los iconos (Bootstrap Icons por CDN) y los detalles finos de espaciado y animación, siempre dentro de las reglas de las secciones 8 y 9.
