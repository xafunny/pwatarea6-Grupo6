/**
 * app.js · Utilidades de interfaz comunes a todas las páginas de Ébano.
 * Se carga desde includes/footer.php después de Bootstrap y GSAP.
 * Funciones:
 *   - rellenarCuentaDemo(): en login, los botones "Usar" completan el formulario con una cuenta de prueba.
 *   - activarValidacion(): mensajes de Bootstrap en formularios .needs-validation (solo comodidad;
 *     el servidor valida siempre).
 *   - animarEntrada(): timeline de entrada (portada de la landing + .anim-item escalonados).
 *   - revelarAlScroll(): los bloques .revelar aparecen al llegar con el scroll (ScrollTrigger).
 *   - contarNumeros(): los [data-contar] cuentan desde 0 hasta su número.
 * Toda animación va dentro de gsap.matchMedia() con (prefers-reduced-motion: no-preference).
 */
'use strict';

/**
 * Completa el formulario de login con la cuenta de prueba del botón pulsado.
 * Los botones llevan data-demo-email y data-demo-clave.
 */
function rellenarCuentaDemo() {
    const formulario = document.getElementById('form-login');
    if (!formulario) {
        return; // no estamos en la página de login
    }
    const campoEmail = formulario.querySelector('#email');
    const campoClave = formulario.querySelector('#clave');

    document.querySelectorAll('[data-demo-email]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            campoEmail.value = boton.dataset.demoEmail;
            campoClave.value = boton.dataset.demoClave;
            formulario.classList.remove('was-validated');
            // Se lleva el foco al botón Entrar: el usuario solo tiene que pulsar Enter
            formulario.querySelector('button[type="submit"]').focus();
        });
    });
}

/**
 * Activa la validación visual de Bootstrap: si el formulario tiene campos inválidos
 * no se envía y se muestran los mensajes .invalid-feedback.
 * Un campo con data-igual-a="id" debe coincidir con ese otro campo (confirmar contraseña).
 */
function activarValidacion() {
    document.querySelectorAll('form.needs-validation').forEach(function (formulario) {
        const confirmaciones = formulario.querySelectorAll('[data-igual-a]');

        // Revisa que cada confirmación coincida con su campo original
        function revisarConfirmaciones() {
            confirmaciones.forEach(function (campo) {
                const original = document.getElementById(campo.dataset.igualA);
                const coincide = original && campo.value === original.value;
                campo.setCustomValidity(coincide ? '' : 'Las contraseñas no coinciden.');
            });
        }

        // Al escribir, un campo marcado como inválido por el servidor se limpia
        formulario.querySelectorAll('.is-invalid').forEach(function (campo) {
            campo.addEventListener('input', function () {
                campo.classList.remove('is-invalid');
            }, { once: true });
        });

        formulario.addEventListener('input', revisarConfirmaciones);

        formulario.addEventListener('submit', function (evento) {
            revisarConfirmaciones();
            if (!formulario.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
                // Lleva el foco al primer campo con error, para teclado y lectores de pantalla
                const primerError = formulario.querySelector(':invalid');
                if (primerError) {
                    primerError.focus();
                }
            }
            formulario.classList.add('was-validated');
        });
    });
}

/**
 * Entrada de la página con un timeline de GSAP (cada paso dura 0.6 s o menos):
 *   1. Si hay portada (.hero, solo en la landing): la foto se asienta con un leve zoom,
 *      las líneas del título suben dentro de su máscara y luego aparecen el texto y los botones.
 *   2. Los elementos .anim-item de cualquier página aparecen escalonados.
 * Solo corre si el usuario NO pidió reducir el movimiento (gsap.matchMedia).
 * Sin JavaScript o sin GSAP todo ya es visible: gsap.from parte de un estado oculto
 * solo en el momento de animar.
 */
function animarEntrada() {
    if (typeof gsap === 'undefined') {
        return;
    }
    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        const linea = gsap.timeline({ defaults: { duration: 0.6, ease: 'power3.out' } });
        const hayPortada = document.querySelector('.hero') !== null;

        if (hayPortada) {
            linea
                .from('.hero-foto', { scale: 1.06, opacity: 0.4, ease: 'power2.out' })
                // yPercent: 100 deja cada línea justo debajo de su máscara (overflow: hidden)
                .from('.hero-linea', { yPercent: 100, stagger: 0.1 }, 0.1)
                .from('.hero-texto, .hero-acciones', { opacity: 0, y: 16, duration: 0.5, stagger: 0.08 }, 0.35);
        }

        const elementos = gsap.utils.toArray('.anim-item');
        if (elementos.length > 0) {
            // Con portada, los .anim-item entran cuando ella ya va terminando
            linea.from(elementos, {
                opacity: 0,
                y: 14,
                duration: 0.5,
                stagger: 0.06,
                clearProps: 'opacity,transform' // al terminar, el CSS vuelve a mandar (hover, etc.)
            }, hayPortada ? 0.5 : 0);
        }
    });
}

/**
 * Aparición de los bloques .revelar al llegar con el scroll (ScrollTrigger).
 * start: 'top 85%' = cuando el borde superior del bloque pasa el 85 % del alto de la ventana.
 * once: true = se anima una sola vez; al volver a subir el bloque ya no se oculta.
 * Si el bloque tiene data-escalonar se animan sus hijos uno tras otro (las tarjetas de la carta).
 */
function revelarAlScroll() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        return;
    }
    gsap.registerPlugin(ScrollTrigger);

    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        gsap.utils.toArray('.revelar').forEach(function (bloque) {
            const escalonar = bloque.hasAttribute('data-escalonar');
            gsap.from(escalonar ? bloque.children : bloque, {
                opacity: 0,
                y: 24,
                duration: 0.6,
                ease: 'power3.out',
                stagger: escalonar ? 0.08 : 0,
                clearProps: 'opacity,transform',
                scrollTrigger: { trigger: bloque, start: 'top 85%', once: true }
            });
        });
    });
}

/**
 * Contador animado: cada elemento [data-contar="12"] sube de 0 a su número al aparecer en pantalla.
 * El HTML ya trae el número final, así que sin JavaScript (o con movimiento reducido) se lee igual.
 */
function contarNumeros() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        return;
    }
    gsap.registerPlugin(ScrollTrigger);

    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        document.querySelectorAll('[data-contar]').forEach(function (elemento) {
            const final = parseInt(elemento.dataset.contar, 10);
            if (Number.isNaN(final)) {
                return;
            }
            const contador = { valor: 0 }; // GSAP anima esta propiedad y onUpdate la escribe en el HTML
            gsap.to(contador, {
                valor: final,
                duration: 0.6,
                ease: 'power2.out',
                scrollTrigger: { trigger: elemento, start: 'top 85%', once: true },
                onStart: function () {
                    elemento.textContent = '0';
                },
                onUpdate: function () {
                    elemento.textContent = String(Math.round(contador.valor));
                }
            });
        });
    });
}

// Punto de entrada: se inicializa todo cuando el HTML está listo
document.addEventListener('DOMContentLoaded', function () {
    rellenarCuentaDemo();
    activarValidacion();
    animarEntrada();
    revelarAlScroll();
    contarNumeros();
});
