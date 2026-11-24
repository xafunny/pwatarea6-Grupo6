# Informe de pruebas E2E

Fecha: 2026-11-23 · Entorno: http://localhost/pwatarea6/ (XAMPP, PHP 8.5 local, MariaDB 10.11) · Herramienta: playwright-cli 0.1.25 con Microsoft Edge (`--browser=msedge`).

**Resultado: 53 pasos PASS, 0 FALLA.** No se encontraron errores de la aplicación.

## Cómo ejecutarlo

1. Apache y MySQL encendidos, con `database.sql` importado (solo datos de ejemplo).
2. Obtener el último id de pedido: `mysql -u root restaurante -e "select max(id) from orders"` y poner ese valor en `BASE_ID` dentro de `tests/e2e/e2e.js` (con los datos de ejemplo es 15).
3. Desde la raíz del repo:

```
playwright-cli open about:blank --browser=msedge
playwright-cli --raw run-code --filename=tests/e2e/e2e.js > resultado.txt
playwright-cli close-all
```

El script abre un contexto de navegador por rol (cliente, chef, camarero, admin), recorre el flujo, guarda las capturas en `tests/capturas/` y devuelve al final un JSON con cada paso (`paso`, `ok`, `ev`).
Después de correrlo hay que volver a importar la BD: `cmd /c "C:\xampp\mysql\bin\mysql.exe -u root restaurante < database.sql"`.

## Pasos y resultados

| # | Paso | Resultado | Evidencia |
|---|---|---|---|
| 1 | 1. Cliente inicia sesión y llega al menú | PASS | http://localhost/pwatarea6/cliente/menu.php |
| 2 | 1b. Historial del cliente antes (conteo) | PASS | entregados antes: 3 |
| 3 | 2. Cliente añade 2 platos y confirma el pedido | PASS | platos: Bowl de quinoa y vegetales asados + Ensalada de burrata y tomate ancestral -> http://localhost/pwatarea6/cliente/mis_pedidos.php?nuevo=1 |
| 4 | 3. Chef ve las 2 comandas nuevas en Pendiente | PASS | ids nuevos: 18, 19 |
| 5 | 4. Chef pasa #18 a En preparación | PASS | comanda #18 aparece en la columna En preparación |
| 6 | 5. Chef pasa #18 a Listo | PASS | comanda #18 aparece en la columna Listo |
| 7 | 4. Chef pasa #19 a En preparación | PASS | comanda #19 aparece en la columna En preparación |
| 8 | 5. Chef pasa #19 a Listo | PASS | comanda #19 aparece en la columna Listo |
| 9 | 6. Camarero ve los 2 pedidos en "Listos para servir" | PASS | botones Entregar de 18, 19 |
| 10 | 7. Camarero entrega #18 | PASS | aviso "Pedido entregado" para #18 |
| 11 | 7. Camarero entrega #19 | PASS | aviso "Pedido entregado" para #19 |
| 12 | 8. Cliente ve los 2 pedidos en el historial | PASS | historial de 3 a 5; contiene Bowl de quinoa y vegetales asados y Ensalada de burrata y tomate ancestral |
| 13 | 9. Admin ve los 2 pedidos como Entregado en admin/pedidos.php | PASS | #18 Entregado, #19 Entregado |
| 14 | 403. Cliente abre chef/cocina.php | PASS | HTTP 403 |
| 15 | 403. Cliente abre camarero/dashboard.php | PASS | HTTP 403 |
| 16 | 403. Cliente abre camarero/nuevo_pedido.php | PASS | HTTP 403 |
| 17 | 403. Cliente abre admin/dashboard.php | PASS | HTTP 403 |
| 18 | 403. Cliente abre admin/platos.php | PASS | HTTP 403 |
| 19 | 403. Cliente abre admin/usuarios.php | PASS | HTTP 403 |
| 20 | 403. Cliente abre admin/pedidos.php | PASS | HTTP 403 |
| 21 | 403. Chef abre cliente/menu.php | PASS | HTTP 403 |
| 22 | 403. Chef abre cliente/mis_pedidos.php | PASS | HTTP 403 |
| 23 | 403. Chef abre camarero/dashboard.php | PASS | HTTP 403 |
| 24 | 403. Chef abre camarero/nuevo_pedido.php | PASS | HTTP 403 |
| 25 | 403. Chef abre admin/dashboard.php | PASS | HTTP 403 |
| 26 | 403. Chef abre admin/platos.php | PASS | HTTP 403 |
| 27 | 403. Chef abre admin/usuarios.php | PASS | HTTP 403 |
| 28 | 403. Chef abre admin/pedidos.php | PASS | HTTP 403 |
| 29 | 403. Camarero abre cliente/menu.php | PASS | HTTP 403 |
| 30 | 403. Camarero abre cliente/mis_pedidos.php | PASS | HTTP 403 |
| 31 | 403. Camarero abre chef/cocina.php | PASS | HTTP 403 |
| 32 | 403. Camarero abre admin/dashboard.php | PASS | HTTP 403 |
| 33 | 403. Camarero abre admin/platos.php | PASS | HTTP 403 |
| 34 | 403. Camarero abre admin/usuarios.php | PASS | HTTP 403 |
| 35 | 403. Camarero abre admin/pedidos.php | PASS | HTTP 403 |
| 36 | 403. Admin abre cliente/menu.php | PASS | HTTP 403 |
| 37 | 403. Admin abre cliente/mis_pedidos.php | PASS | HTTP 403 |
| 38 | 403. Admin abre chef/cocina.php (esperado: el admin tiene sus propias páginas) | PASS | HTTP 403 |
| 39 | 403. Admin abre camarero/dashboard.php | PASS | HTTP 403 |
| 40 | 403. Admin abre camarero/nuevo_pedido.php | PASS | HTTP 403 |
| 41 | 403b. Visitante sin sesión es llevado a login.php | PASS | http://localhost/pwatarea6/login.php |
| 42 | 10. Admin crea el plato <b>x</b> | PASS | plato creado |
| 43 | 11. En la tabla del admin se ve literal (sin etiqueta <b>) | PASS | texto literal "<b>x</b>"; elementos <b> en la tabla: 0 |
| 44 | 12. En el menú del cliente se ve literal | PASS | texto literal en .plato-nombre; elementos <b>: 0 |
| 45 | 13. Admin elimina el plato <b>x</b> | PASS | ya no está en la tabla |
| 46 | Captura Cliente 1280 px | PASS | e2e-cliente-1280.png |
| 47 | Captura Cliente 375 px | PASS | e2e-cliente-375.png |
| 48 | Captura Chef 1280 px | PASS | e2e-chef-1280.png |
| 49 | Captura Chef 375 px | PASS | e2e-chef-375.png |
| 50 | Captura Camarero 1280 px | PASS | e2e-camarero-1280.png |
| 51 | Captura Camarero 375 px | PASS | e2e-camarero-375.png |
| 52 | Captura Admin 1280 px | PASS | e2e-admin-1280.png |
| 53 | Captura Admin 375 px | PASS | e2e-admin-375.png |

Notas:
- La fila "Admin abre chef/cocina.php" da 403 y es el comportamiento esperado: el administrador tiene sus propias páginas y `chef/cocina.php` exige el rol Chef (`requerir_rol(['Chef'])`).
- Un primer intento del paso 9 falló por un error del propio script (buscaba el número de pedido como `#16` y la tabla lo muestra como `#0016`). No fue un fallo de la aplicación. Se corrigió el script y se repitió todo el recorrido completo; los resultados de arriba son de esa segunda ejecución (pedidos #18 y #19).
- El plato `<b>x</b>` se creó y se eliminó dentro del mismo recorrido: en la tabla del administrador y en el menú del cliente se vio como texto literal y no hay elementos `<b>` reales.

## Capturas (`tests/capturas/`)

Dashboard de cada rol a 1280 px y a 375 px (página completa): `e2e-cliente-1280.png`, `e2e-cliente-375.png`, `e2e-chef-1280.png`, `e2e-chef-375.png`, `e2e-camarero-1280.png`, `e2e-camarero-375.png`, `e2e-admin-1280.png`, `e2e-admin-375.png`.
En 375 px el tablero del chef se desplaza en horizontal, como está diseñado.

## Pruebas PHP y sintaxis (tras volver a importar `database.sql`)

| Suite | OK | FALLA |
|---|---|---|
| tests/test_db.php | 11 | 0 |
| tests/test_funciones.php | 37 | 0 |
| tests/test_auth.php | 41 | 0 |
| tests/test_pedidos.php | 99 | 0 |
| tests/test_platos.php | 31 | 0 |
| tests/test_api_cocina.php | 24 | 0 |

`php -l` sobre todos los archivos `.php`: 0 errores. Al final la BD quedó con los datos de ejemplo (15 pedidos, 12 platos).
