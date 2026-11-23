/**
 * app.js · Utilidades de interfaz comunes a todas las páginas de Ébano.
 * Se carga desde includes/footer.php después de Bootstrap y GSAP.
 * Funciones:
 *   - rellenarCuentaDemo(): en login, los botones "Usar" completan el formulario con una cuenta de prueba.
 *   - activarValidacion(): mensajes de Bootstrap en formularios .needs-validation (solo comodidad;
 *     el servidor valida siempre).
 *   - animarEntrada(): aparición escalonada de los elementos .anim-item con GSAP.
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
 * Aparición escalonada de los elementos .anim-item (máx. 0.5 s cada uno).
 * Solo corre si el usuario NO pidió reducir el movimiento (gsap.matchMedia).
 * Sin JavaScript o sin GSAP los elementos ya son visibles: gsap.from parte de un estado oculto
 * solo en el momento de animar.
 */
function animarEntrada() {
    if (typeof gsap === 'undefined') {
        return;
    }
    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', function () {
        const elementos = gsap.utils.toArray('.anim-item');
        if (elementos.length === 0) {
            return;
        }
        gsap.from(elementos, {
            opacity: 0,
            y: 14,
            duration: 0.5,
            ease: 'power3.out',
            stagger: 0.06,
            clearProps: 'opacity,transform' // al terminar, el CSS vuelve a mandar (hover, etc.)
        });
    });
}

// Punto de entrada: se inicializa todo cuando el HTML está listo
document.addEventListener('DOMContentLoaded', function () {
    rellenarCuentaDemo();
    activarValidacion();
    animarEntrada();
});
