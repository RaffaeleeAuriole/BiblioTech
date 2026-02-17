<?php
/**
 * BiblioTech - Dashboard Restituzioni (Bibliotecario)
 * Panoramica prestiti attivi + ultimi 10 restituiti.
 * Sola lettura: le restituzioni le fa lo studente.
 */
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole('bibliotecario');

// Prestiti attivi
$attivi = $pdo->query("
    SELECT p.id, p.data_prestito, l.titolo, l.autore, u.email
    FROM prestiti p
    JOIN libri l ON p.id_libro = l.id
    JOIN utenti u ON p.id_utente = u.id
    WHERE p.data_restituzione IS NULL
    ORDER BY p.data_prestito ASC
")->fetchAll();

// Ultime 10 restituzioni
$recenti = $pdo->query("
    SELECT p.data_prestito, p.data_restituzione, l.titolo, u.email
    FROM prestiti p
    JOIN libri l ON p.id_libro = l.id
    JOIN utenti u ON p.id_utente = u.id
    WHERE p.data_restituzione IS NOT NULL
    ORDER BY p.data_restituzione DESC
    LIMIT 10
")->fetchAll();

// Statistiche
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL)         AS attivi,
        (SELECT COUNT(DISTINCT id_utente) FROM prestiti WHERE data_restituzione IS NULL) AS utenti,
        (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NOT NULL)     AS completati,
        (SELECT COALESCE(SUM(copie_disponibili),0) FROM libri)                  AS disponibili
")->fetch();
?>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">
<div class="page-container">

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <h2>📊 Dashboard Restituzioni</h2>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="gestione_libri.php" class="btn btn-primary">📚 Gestisci Catalogo</a>
            <a href="prestiti.php" class="btn btn-secondary">← Pannello</a>
        </div>
    </div>

    <!-- Statistiche -->
    <?php
    $cards = [
        ['Prestiti Attivi',     'attivi',     '#e74c3c'],
        ['Studenti con Libri',  'utenti',     '#3498db'],
        ['Prestiti Completati', 'completati', '#27ae60'],
        ['Copie Disponibili',   'disponibili','#9b59b6'],
    ];
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">
        <?php foreach ($cards as [$label,$key,$color]): ?>
        <div style="background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:4px solid <?= $color ?>;">
            <div style="font-size:2rem;font-weight:bold;color:<?= $color ?>;"><?= (int)$stats[$key] ?></div>
            <div style="color:#666;margin-top:.4rem;"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Prestiti attivi - sola lettura -->
    <div style="background:#fff;padding:2rem;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:2rem;">
        <h3 style="margin-top:0;color:#e74c3c;">📋 Prestiti attivi (<?= count($attivi) ?>)</h3>

        <?php if (count($attivi) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="prestiti-table">
                    <thead>
                        <tr><th>Studente</th><th>Libro</th><th>Autore</th><th>Dal</th><th>Giorni</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attivi as $p):
                        $giorni = (int)floor((time() - strtotime($p['data_prestito'])) / 86400);
                        $cls    = $giorni > 30 ? 'badge-danger' : ($giorni > 14 ? 'badge-warning' : 'badge-success');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><strong><?= htmlspecialchars($p['titolo']) ?></strong></td>
                            <td><?= htmlspecialchars($p['autore']) ?></td>
                            <td><?= date('d/m/Y', strtotime($p['data_prestito'])) ?></td>
                            <td><span class="badge <?= $cls ?>"><?= $giorni ?> gg</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:1rem;color:#666;font-size:.85rem;">
                Le restituzioni vengono effettuate dagli studenti dalla propria area Prestiti.
            </p>
        <?php else: ?>
            <div class="alert alert-success">✅ Nessun prestito attivo. Tutti i libri sono stati restituiti!</div>
        <?php endif; ?>
    </div>

    <!-- Ultime restituzioni -->
    <?php if (count($recenti) > 0): ?>
    <div style="background:#fff;padding:2rem;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
        <h3 style="margin-top:0;color:#27ae60;">✅ Ultime restituzioni</h3>
        <div style="overflow-x:auto;">
            <table class="prestiti-table">
                <thead>
                    <tr><th>Studente</th><th>Libro</th><th>Prestito</th><th>Restituzione</th><th>Durata</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recenti as $r):
                    $durata = (int)floor((strtotime($r['data_restituzione']) - strtotime($r['data_prestito'])) / 86400);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= htmlspecialchars($r['titolo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data_prestito'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data_restituzione'])) ?></td>
                        <td><?= $durata ?> gg</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php include 'footer.php'; ?>