/**
 * carrito.js · Carrito de pedidos de Ébano (menú del cliente y "nuevo pedido" del camarero).
 * Se carga después de app.js. Funciona en cualquier página que tenga:
 *   - un formulario raíz con data-carrito="<clave>" (la clave de localStorage, ej: 'ebano_carrito_cliente');
 *   - botones .btn-anadir con data-id, data-nombre y data-precio (includes/tarjeta_plato.php);
 *   - dentro del formulario: [data-carrito-lista], [data-carrito-vacio], [data-carrito-total],
 *     [data-carrito-campos] y el botón [data-carrito-confirmar];
 *   - en cualquier lugar: [data-carrito-contador] (insignia) y [data-carrito-aviso] (región aria-live).
 * El estado es un objeto { idPlato: { nombre, precio, cantidad } } guardado en localStorage.
 * El precio solo se usa para MOSTRAR el total: el servidor nunca lo recibe ni confía en él.
 * También filtra la carta con los chips de categoría ([data-filtro]).
 */
'use strict';

const CANTIDAD_MAXIMA = 20; // el mismo tope que valida el servidor (includes/pedidos.php)

let carrito = {};          // estado actual del carrito
let claveCarrito = null;   // clave de localStorage de este carrito

/** Formatea un número como dinero, igual que dinero() en PHP: 1234.5 -> '$1,234.50'. */
function formatearDinero(valor) {
    return '$' + valor.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Lee el carrito guardado. Si no hay nada, el JSON está roto o el navegador bloquea
 * localStorage (modo privado), devuelve un carrito vacío. Descarta entradas con datos raros.
 */
function cargarCarrito(clave) {
    let guardado = {};
    try {
        guardado = JSON.parse(localStorage.getItem(clave) || '{}') || {};
    } catch (error) {
        guardado = {};
    }

    const limpio = {};
    Object.keys(guardado).forEach(function (id) {
        const item = guardado[id];
        const cantidad = parseInt(item && item.cantidad, 10);
        const precio = parseFloat(item && item.precio);
        if (/^\d+$/.test(id) && cantidad >= 1 && !Number.isNaN(precio) && typeof item.nombre === 'string') {
            limpio[id] = { nombre: item.nombre, precio: precio, cantidad: Math.min(cantidad, CANTIDAD_MAXIMA) };
        }
    });
    return limpio;
}

/** Guarda el carrito en localStorage (si el navegador no deja, el carrito sigue en memoria). */
function guardarCarrito() {
    try {
        localStorage.setItem(claveCarrito, JSON.stringify(carrito));
    } catch (error) {
        // Sin almacenamiento el carrito funciona igual; solo no sobrevive a una recarga
    }
}

/** Escribe un mensaje en la región aria-live: los lectores de pantalla lo anuncian. */
function anunciar(mensaje) {
    const aviso = document.querySelector('[data-carrito-aviso]');
    if (aviso) {
        aviso.textContent = mensaje;
    }
}

/** Suma una unidad de un plato (o lo agrega con cantidad 1). Respeta el tope de 20. */
function agregarAlCarrito(id, nombre, precio) {
    const item = carrito[id];
    if (item && item.cantidad >= CANTIDAD_MAXIMA) {
        anunciar('Máximo ' + CANTIDAD_MAXIMA + ' unidades de ' + nombre + '.');
        return;
    }
    if (item) {
        item.cantidad++;
    } else {
        carrito[id] = { nombre: nombre, precio: precio, cantidad: 1 };
    }
    guardarCarrito();
    renderizarCarrito();
    anunciar(nombre + ' añadido. Llevas ' + carrito[id].cantidad + '.');
}

/** Cambia la cantidad de un plato en +1 o -1, sin bajar de 1 ni pasar de 20. */
function cambiarCantidad(id, delta) {
    const item = carrito[id];
    if (!item) {
        return;
    }
    item.cantidad = Math.min(CANTIDAD_MAXIMA, Math.max(1, item.cantidad + delta));
    guardarCarrito();
    renderizarCarrito();
    anunciar(item.nombre + ': ' + item.cantidad + '.');
}

/** Quita un plato del carrito. */
function quitarDelCarrito(id) {
    const item = carrito[id];
    if (!item) {
        return;
    }
    delete carrito[id];
    guardarCarrito();
    renderizarCarrito();
    anunciar(item.nombre + ' quitado del pedido.');
}

/** Crea un botón pequeño con icono para la fila del carrito (−, + o quitar). */
function crearBotonIcono(clase, icono, etiqueta, accion, id) {
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = clase;
    boton.dataset.accion = accion;
    boton.dataset.id = id;
    boton.setAttribute('aria-label', etiqueta);
    const i = document.createElement('i');
    i.className = 'bi ' + icono;
    i.setAttribute('aria-hidden', 'true');
    boton.appendChild(i);
    return boton;
}

/**
 * Dibuja el carrito: una fila por plato (nombre, precio unitario, selector de cantidad,
 * subtotal y botón quitar), el total y el estado vacío.
 * Se construye con createElement + textContent: los nombres nunca se interpretan como HTML.
 */
function renderizarCarrito() {
    const raiz = document.querySelector('[data-carrito]');
    if (!raiz) {
        return;
    }
    const lista = raiz.querySelector('[data-carrito-lista]');
    const vacio = raiz.querySelector('[data-carrito-vacio]');
    const total = raiz.querySelector('[data-carrito-total]');
    const confirmar = raiz.querySelector('[data-carrito-confirmar]');
    const ids = Object.keys(carrito);
    let suma = 0;

    lista.replaceChildren(); // vacía la lista antes de volver a dibujarla

    ids.forEach(function (id) {
        const item = carrito[id];
        const subtotal = item.precio * item.cantidad;
        suma += subtotal;

        const fila = document.createElement('li');
        fila.className = 'carrito-item';

        const info = document.createElement('div');
        info.className = 'carrito-item-info';
        const nombre = document.createElement('span');
        nombre.className = 'carrito-item-nombre';
        nombre.textContent = item.nombre;
        const unitario = document.createElement('span');
        unitario.className = 'carrito-item-unitario';
        unitario.textContent = formatearDinero(item.precio) + ' c/u';
        info.append(nombre, unitario);

        // Selector de cantidad: − número +
        const paso = document.createElement('div');
        paso.className = 'paso';
        paso.setAttribute('role', 'group');
        paso.setAttribute('aria-label', 'Cantidad de ' + item.nombre);
        const menos = crearBotonIcono('paso-boton', 'bi-dash', 'Quitar una unidad', 'menos', id);
        menos.disabled = item.cantidad <= 1;
        const numero = document.createElement('output');
        numero.className = 'paso-numero';
        numero.textContent = String(item.cantidad);
        const mas = crearBotonIcono('paso-boton', 'bi-plus', 'Añadir una unidad', 'mas', id);
        mas.disabled = item.cantidad >= CANTIDAD_MAXIMA;
        paso.append(menos, numero, mas);

        const precio = document.createElement('span');
        precio.className = 'carrito-item-subtotal precio';
        precio.textContent = formatearDinero(subtotal);

        const quitar = crearBotonIcono('carrito-quitar', 'bi-x-lg', 'Quitar ' + item.nombre + ' del pedido', 'quitar', id);

        fila.append(info, quitar, paso, precio);
        lista.appendChild(fila);
    });

    const estaVacio = ids.length === 0;
    vacio.hidden = !estaVacio;
    lista.hidden = estaVacio;
    total.textContent = formatearDinero(suma);
    confirmar.disabled = estaVacio;
    actualizarContador();
}

/** Actualiza la insignia del botón flotante con el número total de unidades. */
function actualizarContador() {
    const unidades = Object.values(carrito).reduce(function (suma, item) {
        return suma + item.cantidad;
    }, 0);
    document.querySelectorAll('[data-carrito-contador]').forEach(function (insignia) {
        insignia.textContent = String(unidades);
        insignia.hidden = unidades === 0;
    });
}

let animarPulso = null; // función del pulso; queda en null si el usuario pidió reducir el movimiento

/**
 * Registra (una sola vez) el pulso de la insignia con gsap.matchMedia():
 * si la preferencia de movimiento cambia, GSAP activa o desactiva el pulso solo.
 */
function prepararPulso() {
    if (typeof gsap === 'undefined') {
        return;
    }
    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        animarPulso = function () {
            gsap.fromTo('[data-carrito-contador]',
                { scale: 1.35 },
                { scale: 1, duration: 0.4, ease: 'back.out(3)', overwrite: true, clearProps: 'transform' });
        };
        return function () {
            animarPulso = null;
        };
    });
}

/** Pulso corto en la insignia al añadir un plato. */
function pulsarContador() {
    if (animarPulso) {
        animarPulso();
    }
}

/**
 * Tras redibujar la lista, devuelve el foco al botón equivalente (el anterior se destruyó).
 * Si ese botón ya no existe o quedó deshabilitado, usa otro de la misma fila o el botón de cerrar.
 */
function restaurarFoco(raiz, accion, id) {
    const lista = raiz.querySelector('[data-carrito-lista]');
    let destino = lista.querySelector('button[data-accion="' + accion + '"][data-id="' + id + '"]');
    if (destino && destino.disabled) {
        // "−" se deshabilita en 1 y "+" en 20: el foco pasa al botón opuesto del selector
        const opuesto = accion === 'menos' ? 'mas' : 'menos';
        destino = lista.querySelector('button[data-accion="' + opuesto + '"][data-id="' + id + '"]');
    }
    if (!destino || destino.disabled) {
        destino = lista.querySelector('button[data-id="' + id + '"]:not(:disabled)')
            || lista.querySelector('button:not(:disabled)')
            || raiz.querySelector('.btn-close');
    }
    if (destino) {
        destino.focus();
    }
}

/** Marca el botón "Añadir" pulsado con un check durante un momento (confirmación visual). */
function marcarAnadido(boton) {
    const icono = boton.querySelector('.bi');
    boton.classList.add('anadido');
    if (icono) {
        icono.className = 'bi bi-check2';
    }
    clearTimeout(boton.temporizador);
    boton.temporizador = setTimeout(function () {
        boton.classList.remove('anadido');
        if (icono) {
            icono.className = 'bi bi-plus-lg';
        }
    }, 1200);
}

/**
 * Antes de enviar el formulario escribe un campo oculto items[id]=cantidad por plato.
 * Si el carrito está vacío, cancela el envío. Solo viajan ids y cantidades: el precio lo pone el servidor.
 */
function prepararEnvio(evento) {
    const raiz = evento.currentTarget;
    const campos = raiz.querySelector('[data-carrito-campos]');
    const ids = Object.keys(carrito);

    if (ids.length === 0) {
        evento.preventDefault();
        anunciar('Tu pedido está vacío. Añade al menos un plato.');
        return;
    }

    campos.replaceChildren();
    ids.forEach(function (id) {
        const campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = 'items[' + id + ']';
        campo.value = String(carrito[id].cantidad);
        campos.appendChild(campo);
    });

    // Evita el doble envío si se pulsa dos veces seguidas
    const confirmar = raiz.querySelector('[data-carrito-confirmar]');
    confirmar.disabled = true;
    confirmar.setAttribute('aria-busy', 'true');
}

/**
 * Chips de categoría: muestran solo las secciones [data-categoria] elegidas.
 * aria-pressed indica cuál está activo (también lo usa el CSS para pintarlo en oro).
 */
function activarFiltros() {
    const chips = document.querySelectorAll('[data-filtro]');
    const secciones = document.querySelectorAll('[data-categoria]');

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            const filtro = chip.dataset.filtro;
            chips.forEach(function (otro) {
                otro.setAttribute('aria-pressed', otro === chip ? 'true' : 'false');
            });
            secciones.forEach(function (seccion) {
                seccion.hidden = filtro !== 'todas' && seccion.dataset.categoria !== filtro;
            });
        });
    });
}

/**
 * Prepara el carrito de la página:
 * 1. Si el <body> trae data-vaciar-carrito (pedido recién enviado), borra ese carrito.
 * 2. Carga el carrito guardado y lo sincroniza con la carta: quita platos que ya no se ofrecen
 *    y toma nombre y precio actuales de los botones (los datos del servidor mandan).
 * 3. Conecta los botones Añadir, la lista (− + quitar) y el envío del formulario.
 */
function iniciarCarrito() {
    const vaciar = document.body.dataset.vaciarCarrito;
    if (vaciar) {
        try {
            localStorage.removeItem(vaciar);
        } catch (error) {
            // nada que borrar si el almacenamiento está bloqueado
        }
    }

    const raiz = document.querySelector('[data-carrito]');
    if (!raiz) {
        return; // página sin carrito (ej: Mis pedidos)
    }
    claveCarrito = raiz.dataset.carrito;
    carrito = cargarCarrito(claveCarrito);

    const botones = document.querySelectorAll('.btn-anadir');
    const enCarta = {};
    botones.forEach(function (boton) {
        enCarta[boton.dataset.id] = boton;
    });
    Object.keys(carrito).forEach(function (id) {
        if (!enCarta[id]) {
            delete carrito[id]; // el plato se ocultó desde la última visita
        } else {
            carrito[id].nombre = enCarta[id].dataset.nombre;
            carrito[id].precio = parseFloat(enCarta[id].dataset.precio);
        }
    });
    guardarCarrito();

    botones.forEach(function (boton) {
        boton.addEventListener('click', function () {
            agregarAlCarrito(boton.dataset.id, boton.dataset.nombre, parseFloat(boton.dataset.precio));
            marcarAnadido(boton);
            pulsarContador();
        });
    });

    // Delegación: un solo listener para todos los botones de las filas (que se redibujan)
    raiz.querySelector('[data-carrito-lista]').addEventListener('click', function (evento) {
        const boton = evento.target.closest('button[data-accion]');
        if (!boton) {
            return;
        }
        const accion = boton.dataset.accion;
        const id = boton.dataset.id;
        if (accion === 'mas') {
            cambiarCantidad(id, 1);
        } else if (accion === 'menos') {
            cambiarCantidad(id, -1);
        } else if (accion === 'quitar') {
            quitarDelCarrito(id);
        }
        restaurarFoco(raiz, accion, id);
    });

    raiz.addEventListener('submit', prepararEnvio);
    prepararPulso();
    renderizarCarrito();
}

document.addEventListener('DOMContentLoaded', function () {
    iniciarCarrito();
    activarFiltros();
});
