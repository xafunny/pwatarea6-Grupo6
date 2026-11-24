// Recorrido E2E de Ébano con playwright-cli. Ver tests/e2e.md para cómo ejecutarlo.
// Antes de correrlo: BASE_ID = SELECT MAX(id) FROM orders (los pedidos nuevos tendrán id mayor;
// con los datos de ejemplo de database.sql es 15).
async page => {
  const BASE = 'http://localhost/pwatarea6/';
  const BASE_ID = 15;
  // Las capturas se guardan en tests/capturas/, relativo a este script (no depende de dónde esté el repo).
  // run-code evalúa el script como función suelta: si __dirname no existe, se usa la ruta desde la raíz del repo.
  const CAP = (typeof __dirname !== 'undefined' ? __dirname + '/../capturas/' : 'tests/capturas/');
  const res = [];
  const paso = async (nombre, fn) => {
    try { const ev = await fn(); res.push({ paso: nombre, ok: true, ev: String(ev ?? '') }); }
    catch (e) { res.push({ paso: nombre, ok: false, ev: String(e.message).split('\n')[0] }); }
  };
  const ok = (c, m) => { if (!c) throw new Error(m); };
  const browser = page.context().browser();
  const ctxs = {};
  const emails = { Cliente: 'cliente@ebano.com', Chef: 'chef@ebano.com', Camarero: 'camarero@ebano.com', Admin: 'admin@ebano.com' };
  const inicio = { Cliente: 'cliente/menu.php', Chef: 'chef/cocina.php', Camarero: 'camarero/dashboard.php', Admin: 'admin/dashboard.php' };
  const abrir = async (rol) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const p = await ctx.newPage();
    await p.goto(BASE + 'login.php');
    await p.fill('#email', emails[rol]);
    await p.fill('#clave', '123456');
    await Promise.all([p.waitForURL(u => !u.href.includes('login.php')), p.click('#form-login button[type=submit]')]);
    ctxs[rol] = { ctx, p };
    return p;
  };
  const ir = async (p, ruta) => { const r = await p.goto(BASE + ruta); return r.status(); };

  // 1. Cliente pide 2 platos
  let cli, ids = [], platos = [], histAntes = 0;
  await paso('1. Cliente inicia sesión y llega al menú', async () => {
    cli = await abrir('Cliente');
    ok(cli.url().endsWith('cliente/menu.php'), 'URL: ' + cli.url());
    return cli.url();
  });
  await paso('1b. Historial del cliente antes (conteo)', async () => {
    await ir(cli, 'cliente/mis_pedidos.php');
    histAntes = Number((await cli.locator('#titulo-historial .bloque-pedidos-numero').innerText()).trim());
    return 'entregados antes: ' + histAntes;
  });
  await paso('2. Cliente añade 2 platos y confirma el pedido', async () => {
    await ir(cli, 'cliente/menu.php');
    const botones = cli.locator('.btn-anadir');
    for (const i of [0, 1]) {
      platos.push(await botones.nth(i).getAttribute('data-nombre'));
      await botones.nth(i).click();
    }
    await cli.locator('.carrito-flotante').click();
    await cli.locator('[data-carrito-confirmar]:not([disabled])').click();
    await cli.waitForURL(/mis_pedidos\.php/);
    const txt = await cli.locator('main').innerText();
    ok(/enviado a cocina/i.test(txt), 'no aparece el aviso de pedido enviado');
    return 'platos: ' + platos.join(' + ') + ' -> ' + cli.url();
  });

  // 2. Chef: Pendiente -> En preparación -> Listo
  let chef;
  await paso('3. Chef ve las 2 comandas nuevas en Pendiente', async () => {
    chef = await abrir('Chef');
    ok(chef.url().endsWith('chef/cocina.php'), 'URL: ' + chef.url());
    const t = chef.locator('[data-columna="Pending"] .ticket');
    const n = await t.count();
    for (let i = 0; i < n; i++) { const id = Number(await t.nth(i).getAttribute('data-id')); if (id > BASE_ID) ids.push(id); }
    ok(ids.length === 2, 'comandas nuevas halladas: ' + ids.length);
    return 'ids nuevos: ' + ids.join(', ');
  });
  for (const id of ids.length ? ids : []) {
    await paso('4. Chef pasa #' + id + ' a En preparación', async () => {
      await chef.locator('.ticket-accion[data-id="' + id + '"][data-estado="Preparing"]').click();
      await chef.locator('[data-columna="Preparing"] .ticket[data-id="' + id + '"]').waitFor({ timeout: 8000 });
      return 'comanda #' + id + ' aparece en la columna En preparación';
    });
    await paso('5. Chef pasa #' + id + ' a Listo', async () => {
      await chef.locator('.ticket-accion[data-id="' + id + '"][data-estado="Ready to serve"]').click();
      await chef.locator('[data-columna="Ready to serve"] .ticket[data-id="' + id + '"]').waitFor({ timeout: 8000 });
      return 'comanda #' + id + ' aparece en la columna Listo';
    });
  }

  // 3. Camarero entrega
  let cam;
  await paso('6. Camarero ve los 2 pedidos en "Listos para servir"', async () => {
    cam = await abrir('Camarero');
    for (const id of ids) ok(await cam.locator('form:has(input[name="pedido_id"][value="' + id + '"]):has(input[value="entregar"])').count() === 1, 'falta el botón Entregar de #' + id);
    return 'botones Entregar de ' + ids.join(', ');
  });
  for (const id of ids) {
    await paso('7. Camarero entrega #' + id, async () => {
      await cam.locator('form:has(input[name="pedido_id"][value="' + id + '"]):has(input[value="entregar"]) button').click();
      await cam.waitForLoadState();
      const txt = await cam.locator('main').innerText();
      ok(/Pedido entregado/.test(txt), 'sin aviso "Pedido entregado"');
      return 'aviso "Pedido entregado" para #' + id;
    });
  }

  // 4. Cliente: historial
  await paso('8. Cliente ve los 2 pedidos en el historial', async () => {
    await ir(cli, 'cliente/mis_pedidos.php');
    const ahora = Number((await cli.locator('#titulo-historial .bloque-pedidos-numero').innerText()).trim());
    ok(ahora === histAntes + 2, 'historial pasó de ' + histAntes + ' a ' + ahora);
    const tabla = await cli.locator('.tabla-historial').innerText();
    for (const n of platos) ok(tabla.includes(n), 'falta ' + n + ' en el historial');
    return 'historial de ' + histAntes + ' a ' + ahora + '; contiene ' + platos.join(' y ');
  });

  // 5. Admin ve en pedidos
  let adm;
  await paso('9. Admin ve los 2 pedidos como Entregado en admin/pedidos.php', async () => {
    adm = await abrir('Admin');
    ok(await ir(adm, 'admin/pedidos.php') === 200, 'estado HTTP distinto de 200');
    const out = [];
    for (const id of ids) {
      const fila = adm.locator('tr', { has: adm.locator('.celda-numero', { hasText: new RegExp('^\\s*#0*' + id + '\\s*$') }) });
      ok(await fila.count() === 1, 'no hay fila para #' + id);
      const t = await fila.innerText();
      ok(/Entregado/.test(t), 'estado distinto de Entregado en #' + id);
      out.push('#' + id + ' Entregado');
    }
    return out.join(', ');
  });

  // 6. Matriz 403
  const urlsRol = { Cliente: ['cliente/menu.php', 'cliente/mis_pedidos.php'], Chef: ['chef/cocina.php'], Camarero: ['camarero/dashboard.php', 'camarero/nuevo_pedido.php'], Admin: ['admin/dashboard.php', 'admin/platos.php', 'admin/usuarios.php', 'admin/pedidos.php'] };
  const pagRol = { Cliente: cli, Chef: chef, Camarero: cam, Admin: adm };
  // Excepciones permitidas por la especificación: el chef ve la carta (solo lectura) y el admin crea pedidos
  const permitidas = { Chef: ['cliente/menu.php'], Admin: ['camarero/nuevo_pedido.php'] };
  for (const rol of Object.keys(pagRol)) {
    for (const otro of Object.keys(urlsRol)) {
      if (otro === rol) continue;
      for (const u of urlsRol[otro]) {
        if ((permitidas[rol] || []).includes(u)) continue;
        await paso('403. ' + rol + ' abre ' + u + (rol === 'Admin' && u === 'chef/cocina.php' ? ' (esperado: el admin tiene sus propias páginas)' : ''), async () => {
          const s = await ir(pagRol[rol], u);
          ok(s === 403, 'HTTP ' + s);
          return 'HTTP ' + s;
        });
      }
    }
  }
  await paso('403b. Visitante sin sesión es llevado a login.php', async () => {
    const ctx = await browser.newContext(); const p = await ctx.newPage();
    await p.goto(BASE + 'admin/dashboard.php'); const u = p.url(); await ctx.close();
    ok(u.includes('login.php'), u); return u;
  });

  // 7. XSS: plato <b>x</b>
  const NOMBRE = '<b>x</b>';
  await paso('10. Admin crea el plato <b>x</b>', async () => {
    await ir(adm, 'admin/platos.php');
    await adm.locator('[data-plato-nuevo]').click();
    await adm.locator('#plato-nombre').fill(NOMBRE);
    await adm.locator('#plato-categoria').selectOption({ index: 0 });
    await adm.locator('#plato-precio').fill('5');
    await adm.locator('#plato-descripcion').fill('prueba e2e');
    await adm.locator('#plato-imagen').fill('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400');
    await adm.locator('#modal-plato button[type=submit]').click();
    await adm.waitForLoadState();
    ok(/Guardad|creado|añadid/i.test(await adm.locator('main').innerText()) || await adm.locator('.tabla-gestion td', { hasText: NOMBRE }).count() > 0, 'no se creó');
    return 'plato creado';
  });
  await paso('11. En la tabla del admin se ve literal (sin etiqueta <b>)', async () => {
    const celda = adm.locator('.tabla-gestion td', { hasText: NOMBRE });
    ok(await celda.count() >= 1, 'no está en la tabla');
    ok(await adm.locator('.tabla-gestion b').count() === 0, 'hay un <b> real en la tabla');
    return 'texto literal "' + NOMBRE + '"; elementos <b> en la tabla: 0';
  });
  await paso('12. En el menú del cliente se ve literal', async () => {
    await ir(cli, 'cliente/menu.php');
    const n = cli.locator('.plato-nombre', { hasText: NOMBRE });
    ok(await n.count() >= 1, 'no aparece en el menú');
    ok(await cli.locator('.plato-nombre b').count() === 0, 'hay un <b> real en el menú');
    return 'texto literal en .plato-nombre; elementos <b>: 0';
  });
  await paso('13. Admin elimina el plato <b>x</b>', async () => {
    await ir(adm, 'admin/platos.php');
    await adm.locator('button[aria-label="Eliminar ' + NOMBRE + '"]').click();
    await adm.locator('#modal-confirmar-si').click();
    await adm.waitForLoadState();
    await adm.waitForTimeout(500);
    ok(await adm.locator('.tabla-gestion td', { hasText: NOMBRE }).count() === 0, 'sigue en la tabla');
    return 'ya no está en la tabla';
  });

  // 8. Capturas de cada dashboard
  for (const rol of Object.keys(pagRol)) {
    for (const w of [1280, 375]) {
      await paso('Captura ' + rol + ' ' + w + ' px', async () => {
        const p = pagRol[rol];
        await p.setViewportSize({ width: w, height: w === 375 ? 812 : 800 });
        await ir(p, inicio[rol]);
        await p.waitForTimeout(1500); // deja terminar las animaciones GSAP
        const f = 'e2e-' + rol.toLowerCase() + '-' + w + '.png';
        await p.screenshot({ path: CAP + f, fullPage: true });
        return f;
      });
    }
  }
  for (const k of Object.keys(ctxs)) await ctxs[k].ctx.close();
  return JSON.stringify(res);
}
