<?php
/**
 * Ticket de una comanda en el tablero de cocina (componente parcial).
 * Se incluye dentro de un foreach en chef/cocina.php:
 *   foreach ($columna['comandas'] as $comanda) { include __DIR__ . '/../includes/ticket_comanda.php'; }
 * Variable que espera:
 *   $comanda  array  resultado de datos_comanda() (numero, hora, plato, cantidad, cliente, camarero,
 *                    espera, urgente, accion...)
 * assets/js/cocina.js (crearTicket) arma EXACTAMENTE este mismo HTML cuando refresca el tablero:
 * si se cambia uno, hay que cambiar el otro.
 */
?>
<li class="ticket-envoltura">
    <article class="ticket<?= $comanda['urgente'] ? ' urgente' : '' ?>" data-id="<?= e((string) $comanda['id']) ?>"
             aria-label="Comanda <?= e($comanda['numero']) ?>: <?= e((string) $comanda['cantidad']) ?> × <?= e($comanda['plato']) ?>">
        <header class="ticket-cabecera">
            <span class="ticket-numero"><?= e($comanda['numero']) ?></span>
            <time class="ticket-hora" datetime="<?= e($comanda['fecha_iso']) ?>"><?= e($comanda['hora']) ?></time>
        </header>

        <h3 class="ticket-plato">
            <span class="ticket-cantidad"><?= e((string) $comanda['cantidad']) ?> ×</span>
            <?= e($comanda['plato']) ?>
        </h3>

        <ul class="ticket-datos">
            <li><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden">Cliente:</span> <?= e($comanda['cliente']) ?></li>
            <?php if ($comanda['camarero'] !== null): ?>
                <li><i class="bi bi-person-badge" aria-hidden="true"></i><span class="visually-hidden">Camarero:</span> <?= e($comanda['camarero']) ?></li>
            <?php endif; ?>
        </ul>

        <footer class="ticket-pie">
            <span class="ticket-espera<?= $comanda['urgente'] ? ' urgente' : '' ?>">
                <i class="bi bi-stopwatch" aria-hidden="true"></i><span class="visually-hidden">Espera:</span> <?= e($comanda['espera']) ?>
            </span>
            <?php if ($comanda['accion'] !== null): ?>
                <button type="button" class="btn btn-oro btn-sm ticket-accion"
                        data-id="<?= e((string) $comanda['id']) ?>"
                        data-estado="<?= e($comanda['accion']['estado']) ?>"
                        aria-label="<?= e($comanda['accion']['texto']) ?> comanda <?= e($comanda['numero']) ?>">
                    <?= e($comanda['accion']['texto']) ?>
                </button>
            <?php else: ?>
                <span class="ticket-esperando"><i class="bi bi-bell" aria-hidden="true"></i> Esperando camarero</span>
            <?php endif; ?>
        </footer>
    </article>
</li>
