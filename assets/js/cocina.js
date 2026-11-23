/**
 * cocina.js · Tablero de cocina del chef (chef/cocina.php).
 * Se carga después de app.js. Qué hace:
 *   - cargarPedidos(): pide las comandas a chef/api_pedidos.php (GET) al abrir y cada 20 s.
 *     Si la pestaña está oculta no pregunta; al volver a ella, refresca enseguida.
 *   - renderizarTablero(pedidos): vuelve a armar las 3 columnas, los contadores y los textos vacíos.
 *   - crearTicket(pedido): arma un ticket con createElement + textContent (nunca innerHTML con datos),
 *     el mismo HTML que includes/ticket_comanda.php.
 *   - avanzarEstado(id, estado): envía el cambio por POST con la cabecera X-CSRF-Token.
 *   - actualizarIndicador(): "Actualizado hace X s" (cada segundo).
 * Las comandas nuevas (un id que no se había visto) reciben un pulso dorado corto con GSAP.
 * El servidor decide todo (qué estado sigue, si una comanda es urgente); aquí solo se pinta.
 */
'use strict';

const INTERVALO_REFRESCO = 20000; // 20 s entre cada consulta automática
const ESTADOS_COCINA = ['Pending', 'Preparing', 'Ready to serve'];

let tablero = null;               // elemento raíz [data-tablero]
let urlApi = '';                  // chef/api_pedidos.php (viene en data-api)
let tokenCsrf = '';               // de <meta name="csrf-token">
let ultimaActualizacion = Date.now();
let sinConexion = false;
let cargando = false;             // evita dos GET a la vez (botón + temporizador)
let refrescoPendiente = false;    // alguien pidió refrescar mientras había un GET en curso
let cambiosLocales = 0;           // sube con cada cambio de estado enviado: detecta respuestas viejas
let sesionTerminada = false;      // si el servidor nos manda al login, se deja de consultar
let movimientoPermitido = false;  // lo activa gsap.matchMedia si el usuario no pidió reducir movimiento
let temporizadorAviso = null;     // setTimeout que esconde el aviso flotante
const idsVistos = new Set();      // ids de comandas ya mostradas: un id nuevo = comanda recién llegada

/** Muestra un mensaje en el aviso flotante (región aria-live). tipo: 'error' o 'info'. */
function mostrarAviso(mensaje, tipo) {
    const aviso = document.querySelector('[data-aviso]');
    if (!aviso) {
        return;
    }
    aviso.textContent = mensaje;
    aviso.classList.toggle('error', tipo === 'error');
    aviso.hidden = false;
    // El aviso se esconde solo; si llega otro mensaje, el temporizador vuelve a empezar
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(function () {
        aviso.hidden = true;
    }, 5000);
}

/** Crea un elemento con una clase y, si se indica, su texto (siempre como textContent). */
function crearElemento(etiqueta, clase, texto) {
    const elemento = document.createElement(etiqueta);
    if (clase) {
        elemento.className = clase;
    }
    if (texto !== undefined) {
        elemento.textContent = texto;
    }
    return elemento;
}

/** Icono de Bootstrap Icons decorativo (aria-hidden: el lector de pantalla no lo lee). */
function crearIcono(nombre) {
    const icono = crearElemento('i', 'bi ' + nombre);
    icono.setAttribute('aria-hidden', 'true');
    return icono;
}

/** Texto solo para lectores de pantalla (ej: "Cliente:"). */
function crearOculto(texto) {
    return crearElemento('span', 'visually-hidden', texto);
}

/**
 * Arma el <li> de un ticket con los datos que manda la API (ver datos_comanda() en PHP).
 * Mismo HTML que includes/ticket_comanda.php, para que el tablero no "salte" al refrescar.
 */
function crearTicket(pedido) {
    const envoltura = crearElemento('li', 'ticket-envoltura');
    const ticket = crearElemento('article', 'ticket' + (pedido.urgente ? ' urgente' : ''));
    ticket.dataset.id = String(pedido.id);
    ticket.setAttribute('aria-label', 'Comanda ' + pedido.numero + ': ' + pedido.cantidad + ' × ' + pedido.plato);

    // Cabecera: número de comanda y hora en que entró
    const cabecera = crearElemento('header', 'ticket-cabecera');
    cabecera.appendChild(crearElemento('span', 'ticket-numero', pedido.numero));
    const hora = crearElemento('time', 'ticket-hora', pedido.hora);
    hora.setAttribute('datetime', pedido.fecha_iso);
    cabecera.appendChild(hora);

    // Cantidad × plato
    const plato = crearElemento('h3', 'ticket-plato');
    plato.appendChild(crearElemento('span', 'ticket-cantidad', pedido.cantidad + ' ×'));
    plato.appendChild(document.createTextNode(' ' + pedido.plato));

    // Cliente y, si lo hay, camarero
    const datos = crearElemento('ul', 'ticket-datos');
    const cliente = crearElemento('li');
    cliente.append(crearIcono('bi-person'), crearOculto('Cliente:'), ' ' + pedido.cliente);
    datos.appendChild(cliente);
    if (pedido.camarero !== null) {
        const camarero = crearElemento('li');
        camarero.append(crearIcono('bi-person-badge'), crearOculto('Camarero:'), ' ' + pedido.camarero);
        datos.appendChild(camarero);
    }

    // Pie: tiempo de espera y el botón del siguiente paso (o "Esperando camarero")
    const pie = crearElemento('footer', 'ticket-pie');
    const espera = crearElemento('span', 'ticket-espera' + (pedido.urgente ? ' urgente' : ''));
    espera.append(crearIcono('bi-stopwatch'), crearOculto('Espera:'), ' ' + pedido.espera);
    pie.appendChild(espera);

    if (pedido.accion !== null) {
        const boton = crearElemento('button', 'btn btn-oro btn-sm ticket-accion', pedido.accion.texto);
        boton.type = 'button';
        boton.dataset.id = String(pedido.id);
        boton.dataset.estado = pedido.accion.estado;
        boton.setAttribute('aria-label', pedido.accion.texto + ' comanda ' + pedido.numero);
        pie.appendChild(boton);
    } else {
        const esperando = crearElemento('span', 'ticket-esperando');
        esperando.append(crearIcono('bi-bell'), ' Esperando camarero');
        pie.appendChild(esperando);
    }

    ticket.append(cabecera, plato, datos, pie);
    envoltura.appendChild(ticket);
    return envoltura;
}

/**
 * Pulso dorado corto (≤ 0.6 s) para una comanda recién llegada: el ticket baja 8 px a su lugar
 * mientras un velo dorado (sombra interior que cubre todo el ticket) se desvanece.
 */
function pulsarTicket(ticket) {
    if (!movimientoPermitido) {
        return; // movimiento reducido o GSAP no cargó: la comanda aparece sin animación
    }
    gsap.fromTo(ticket,
        { y: -8, boxShadow: 'inset 0 0 0 999px rgba(201, 164, 92, 0.3)' },
        { y: 0, boxShadow: 'inset 0 0 0 999px rgba(201, 164, 92, 0)', duration: 0.6, ease: 'power2.out', clearProps: 'transform,boxShadow' }
    );
}

/**
 * Vuelve a armar las 3 columnas con los pedidos recibidos.
 * Recuerda qué botón tenía el foco para devolvérselo: así refrescar no le quita el lugar
 * a quien usa el teclado.
 */
function renderizarTablero(pedidos) {
    const enfocado = document.activeElement;
    const idEnfocado = enfocado && enfocado.classList.contains('ticket-accion') ? enfocado.dataset.id : null;
    const nuevos = [];

    ESTADOS_COCINA.forEach(function (estado) {
        const columna = tablero.querySelector('[data-columna="' + estado + '"]');
        const lista = columna.querySelector('[data-lista]');
        const delEstado = pedidos.filter(function (pedido) {
            return pedido.estado === estado;
        });

        const fragmento = document.createDocumentFragment();
        delEstado.forEach(function (pedido) {
            const item = crearTicket(pedido);
            fragmento.appendChild(item);
            if (!idsVistos.has(pedido.id)) {
                nuevos.push(item.firstChild);
            }
            idsVistos.add(pedido.id);
        });
        lista.replaceChildren(fragmento);

        // Contador de la columna, KPI de la barra y texto de columna vacía
        columna.querySelector('[data-cuenta]').textContent = String(delEstado.length);
        const kpi = document.querySelector('[data-kpi="' + estado + '"]');
        if (kpi) {
            kpi.textContent = String(delEstado.length);
        }
        columna.querySelector('[data-vacio]').hidden = delEstado.length > 0;
    });

    if (idEnfocado !== null) {
        const boton = tablero.querySelector('.ticket-accion[data-id="' + idEnfocado + '"]');
        if (boton) {
            boton.focus();
        }
    }
    nuevos.forEach(pulsarTicket);
}

/** Escribe "Actualizado hace X s" (o "Sin conexión...") junto al punto de estado. */
function actualizarIndicador() {
    const indicador = document.querySelector('[data-indicador]');
    const punto = document.querySelector('[data-sync-punto]');
    if (!indicador) {
        return;
    }
    if (punto) {
        punto.classList.toggle('sin-conexion', sinConexion || sesionTerminada);
    }
    if (sesionTerminada) {
        indicador.textContent = 'Sesión cerrada';
        return;
    }
    if (sinConexion) {
        indicador.textContent = 'Sin conexión, reintentando…';
        return;
    }
    const segundos = Math.max(0, Math.round((Date.now() - ultimaActualizacion) / 1000));
    indicador.textContent = segundos < 60
        ? 'Actualizado hace ' + segundos + ' s'
        : 'Actualizado hace ' + Math.floor(segundos / 60) + ' min';
}

/**
 * Lee la respuesta de la API como JSON. Si el cuerpo no es JSON (un 502 del hosting,
 * un error de PHP con HTML...) devuelve null: quien llama decide qué hacer.
 */
async function leerJson(respuesta) {
    const tipo = respuesta.headers.get('Content-Type') || '';
    if (!tipo.includes('application/json')) {
        return null;
    }
    try {
        return await respuesta.json();
    } catch (error) {
        return null;
    }
}

/**
 * true solo si el servidor dijo claramente que la sesión ya no sirve: nos redirigió al login
 * o respondió 401/403. Un 500 o un 502 pasajero NO cuenta: ahí se sigue reintentando.
 */
function esSesionPerdida(respuesta) {
    return respuesta.redirected || respuesta.status === 401 || respuesta.status === 403;
}

/** Marca la sesión como terminada: deja de consultar y avisa cómo seguir. */
function terminarSesion(mensaje) {
    sesionTerminada = true;
    mostrarAviso(mensaje || 'Tu sesión terminó. Recarga la página para volver a entrar.', 'error');
    actualizarIndicador();
}

/**
 * Pide las comandas a la API (GET) y redibuja el tablero. Un fallo de red no rompe lo que ya se ve.
 * Si ya hay una consulta en curso, no lanza otra: deja anotado que hace falta repetirla al terminar
 * (refrescoPendiente). Si mientras tanto el chef cambió una comanda (cambiosLocales), la respuesta
 * en curso pudo salir ANTES del cambio: se descarta y se vuelve a pedir, para no pintar un estado viejo.
 */
async function cargarPedidos() {
    if (sesionTerminada) {
        return;
    }
    if (cargando) {
        refrescoPendiente = true;
        return;
    }
    cargando = true;
    const cambiosAlEmpezar = cambiosLocales;
    try {
        const respuesta = await fetch(urlApi, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (esSesionPerdida(respuesta)) {
            terminarSesion();
            return;
        }
        const datos = await leerJson(respuesta);
        if (datos === null || !respuesta.ok || !Array.isArray(datos.pedidos)) {
            throw new Error('Respuesta inesperada del servidor');
        }
        if (cambiosAlEmpezar !== cambiosLocales) {
            refrescoPendiente = true; // datos de antes del último cambio: no se pintan
            return;
        }
        renderizarTablero(datos.pedidos);
        ultimaActualizacion = Date.now();
        sinConexion = false;
    } catch (error) {
        sinConexion = true; // el tablero se queda como estaba y se reintenta en el próximo intervalo
    } finally {
        cargando = false;
        actualizarIndicador();
        if (refrescoPendiente && !sesionTerminada) {
            refrescoPendiente = false;
            cargarPedidos();
        }
    }
}

/**
 * Avanza una comanda al estado indicado (POST con la cabecera X-CSRF-Token).
 * El botón se desactiva mientras espera; luego el tablero se rearma con datos frescos del servidor.
 */
async function avanzarEstado(id, estado, boton) {
    boton.disabled = true;
    try {
        const respuesta = await fetch(urlApi, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': tokenCsrf
            },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id, estado: estado })
        });
        const datos = await leerJson(respuesta);
        if (esSesionPerdida(respuesta)) {
            // Sin sesión, rol sin permiso o token CSRF vencido: la API lo explica en JSON
            terminarSesion(datos && datos.mensaje);
            return;
        }
        if (datos === null) {
            mostrarAviso('No se pudo guardar el cambio. Inténtalo de nuevo.', 'error');
        } else if (!datos.ok) {
            mostrarAviso(datos.mensaje, 'error');
        }
    } catch (error) {
        mostrarAviso('Sin conexión: el cambio no se guardó. Inténtalo de nuevo.', 'error');
        boton.disabled = false;
        return;
    }
    // Cualquier GET que ya estuviera en camino salió antes de este cambio: se marca como viejo
    cambiosLocales++;
    await cargarPedidos(); // con éxito o con 422, se muestra lo que hay de verdad en la BD
}

/** Prepara el pulso de GSAP solo si el usuario no pidió reducir el movimiento. */
function prepararMovimiento() {
    if (typeof gsap === 'undefined') {
        return;
    }
    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        movimientoPermitido = true;
        return function () {
            movimientoPermitido = false; // si cambia la preferencia, se apaga el pulso
        };
    });
}

/** Punto de entrada: conecta botones, temporizadores y la visibilidad de la pestaña. */
function iniciarCocina() {
    tablero = document.querySelector('[data-tablero]');
    if (!tablero) {
        return; // no estamos en el tablero de cocina
    }
    urlApi = tablero.dataset.api;
    const meta = document.querySelector('meta[name="csrf-token"]');
    tokenCsrf = meta ? meta.content : '';

    // Las comandas que PHP ya pintó cuentan como vistas: no deben "pulsar" en la primera consulta
    tablero.querySelectorAll('.ticket[data-id]').forEach(function (ticket) {
        idsVistos.add(parseInt(ticket.dataset.id, 10));
    });

    prepararMovimiento();

    // Un solo listener para todos los botones, también los que se crean al refrescar (delegación)
    tablero.addEventListener('click', function (evento) {
        const boton = evento.target.closest('.ticket-accion');
        if (boton && !boton.disabled) {
            avanzarEstado(parseInt(boton.dataset.id, 10), boton.dataset.estado, boton);
        }
    });

    const botonActualizar = document.querySelector('[data-actualizar]');
    if (botonActualizar) {
        botonActualizar.addEventListener('click', cargarPedidos);
    }

    // Cada 20 s se consulta la API, salvo con la pestaña oculta (no gasta peticiones en segundo plano)
    setInterval(function () {
        if (!document.hidden) {
            cargarPedidos();
        }
    }, INTERVALO_REFRESCO);
    setInterval(actualizarIndicador, 1000);

    // Al volver a la pestaña, se refresca enseguida en vez de esperar al próximo intervalo
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            cargarPedidos();
        }
    });

    cargarPedidos();
}

document.addEventListener('DOMContentLoaded', iniciarCocina);
