<?php
/**
 * BiblioTech - Gestione Prestiti
 * STUDENTE: libri disponibili + propri prestiti attivi + pulsante restituisci
 * BIBLIOTECARIO: panoramica "chi ha cosa" (sola lettura) + link gestione
 */
session_start();
require_once 'config.php';
require_once 'auth.php';
requireLogin();
?>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<style>
/* ── Modal overlay ─────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-box {
    background: #fff;
    border-radius: 14px;
    padding: 2.5rem 2rem;
    max-width: 440px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    text-align: center;
    animation: popIn .18s ease;
}
@keyframes popIn {
    from { transform: scale(.92); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}
.modal-box h3 {
    margin: 0 0 .5rem;
    font-size: 1.3rem;
    color: #222;
}
.modal-box p {
    margin: 0 0 1.8rem;
    color: #555;
    font-size: .97rem;
    line-height: 1.5;
}
.modal-book {
    display: inline-block;
    margin-bottom: 1.4rem;
    padding: .6rem 1.2rem;
    background: #f0f4ff;
    border-radius: 8px;
    color: #1a3a8f;
    font-weight: 600;
    font-size: 1rem;
}
.modal-actions {
    display: flex;
    gap: .75rem;
    justify-content: center;
}

/* ── Card prestito (sezione "I miei prestiti") ─────────────── */
.prestiti-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
    margin-top: 1rem;
}
.prestito-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: .6rem;
    border-left: 4px solid #27ae60;
    transition: box-shadow .2s;
}
.prestito-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,.13);
}
.prestito-card.scaduto  { border-left-color: #e74c3c; }
.prestito-card.in-scadenza { border-left-color: #f39c12; }

.prestito-card__titolo {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.3;
}
.prestito-card__autore {
    font-size: .9rem;
    color: #666;
}
.prestito-card__meta {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: .25rem;
}
.prestito-card__giorni {
    display: inline-block;
    padding: .25rem .75rem;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 600;
}
.giorni-ok      { background: #d4edda; color: #155724; }
.giorni-warning { background: #fff3cd; color: #856404; }
.giorni-danger  { background: #f8d7da; color: #721c24; }

.prestito-card__footer {
    margin-top: .75rem;
}
</style>

<div class="page-container">

<?php /* ══════════════════════════════════════════════════════
         VISTA STUDENTE
   ══════════════════════════════════════════════════════ */ ?>
<?php if ($_SESSION['ruolo'] === 'studente'):
    $uid = (int)$_SESSION['user_id'];
?>

    <h2>📚 Prestiti</h2>

    <!-- ── Libri disponibili ─────────────────────────── -->
    <h3>Libri disponibili</h3>
    <?php
    $libri_disp = $conn->query("
        SELECT id, titolo, autore, copie_disponibili
        FROM libri WHERE copie_disponibili > 0 ORDER BY titolo ASC
    ");
    ?>
    <?php if ($libri_disp && $libri_disp->num_rows > 0): ?>
        <div style="overflow-x:auto;">
            <table class="libri-table">
                <thead>
                    <tr>
                        <th>Titolo</th><th>Autore</th>
                        <th>Disponibili</th><th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $libri_disp->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['titolo']) ?></td>
                        <td><?= htmlspecialchars($row['autore']) ?></td>
                        <td>
                            <span class="badge badge-success">
                                <?= $row['copie_disponibili'] ?>
                            </span>
                        </td>
                        <td>
                            <!-- Apre modal di conferma invece di confirm() -->
                            <button class="btn btn-sm btn-primary"
                                    onclick="apriModalPrestito(
                                        <?= $row['id'] ?>,
                                        '<?= htmlspecialchars($row['titolo'], ENT_QUOTES) ?>'
                                    )">
                                Prendi in prestito
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>Nessun libro disponibile al momento.</strong>
        </div>
    <?php endif; ?>

    <hr style="margin:3rem 0; border:none; border-top:2px solid #eee;">

    <!-- ── I miei prestiti attivi ─────────────────────── -->
    <h3>📖 I miei prestiti attivi</h3>
    <?php
    $miei = $conn->query("
        SELECT p.id, p.data_prestito, l.titolo, l.autore
        FROM prestiti p
        JOIN libri l ON p.id_libro = l.id
        WHERE p.id_utente = $uid AND p.data_restituzione IS NULL
        ORDER BY p.data_prestito DESC
    ");
    ?>

    <?php if ($miei && $miei->num_rows > 0): ?>
        <div class="prestiti-cards">
        <?php while ($p = $miei->fetch_assoc()):
            $giorni = (int)floor((time() - strtotime($p['data_prestito'])) / 86400);
            if ($giorni > 30)     { $card_cls = 'scaduto';    $gg_cls = 'giorni-danger';  }
            elseif ($giorni > 14) { $card_cls = 'in-scadenza';$gg_cls = 'giorni-warning'; }
            else                  { $card_cls = '';            $gg_cls = 'giorni-ok';      }
        ?>
            <div class="prestito-card <?= $card_cls ?>">
                <div class="prestito-card__titolo">
                    <?= htmlspecialchars($p['titolo']) ?>
                </div>
                <div class="prestito-card__autore">
                    <?= htmlspecialchars($p['autore']) ?>
                </div>
                <div class="prestito-card__meta">
                    <span style="color:#888; font-size:.85rem;">
                        Dal <?= date('d/m/Y', strtotime($p['data_prestito'])) ?>
                    </span>
                    <span class="prestito-card__giorni <?= $gg_cls ?>">
                        <?= $giorni ?> giorni
                    </span>
                </div>
                <div class="prestito-card__footer">
                    <!-- Apre modal di conferma restituzione -->
                    <button class="btn btn-primary"
                            style="width:100%;"
                            onclick="apriModalRestituzione(
                                <?= $p['id'] ?>,
                                '<?= htmlspecialchars($p['titolo'], ENT_QUOTES) ?>'
                            )">
                        ↩️ Restituisci
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
        </div>

    <?php else: ?>
        <div class="alert alert-info">
            <strong>Non hai prestiti attivi.</strong><br>
            Sfoglia i libri disponibili qui sopra per richiederne uno.
        </div>
    <?php endif; ?>

<?php endif; /* fine STUDENTE */ ?>


<?php /* ══════════════════════════════════════════════════════
         VISTA BIBLIOTECARIO
   ══════════════════════════════════════════════════════ */ ?>
<?php if ($_SESSION['ruolo'] === 'bibliotecario'): ?>

    <div style="display:flex;justify-content:space-between;align-items:center;
                flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <h2>📊 Pannello Bibliotecario</h2>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="gestione_libri.php" class="btn btn-primary">📚 Gestisci Catalogo</a>
            <a href="gestione_restituzioni.php" class="btn btn-secondary">📋 Dashboard</a>
        </div>
    </div>

    <!-- Statistiche -->
    <?php
    $stats = $conn->query("SELECT
        (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL)              AS attivi,
        (SELECT COUNT(DISTINCT id_utente) FROM prestiti WHERE data_restituzione IS NULL) AS utenti,
        (SELECT COALESCE(SUM(copie_disponibili),0) FROM libri)                       AS disponibili,
        (SELECT COUNT(*) FROM libri)                                                 AS titoli
    ")->fetch_assoc();
    $cards = [
        ['Prestiti Attivi',    'attivi',      '#e74c3c'],
        ['Studenti con Libri', 'utenti',      '#3498db'],
        ['Copie Disponibili',  'disponibili', '#27ae60'],
        ['Titoli in Catalogo', 'titoli',      '#9b59b6'],
    ];
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                gap:1rem;margin-bottom:2rem;">
        <?php foreach ($cards as [$label, $key, $color]): ?>
        <div style="background:#fff;padding:1.5rem;border-radius:8px;
                    box-shadow:0 2px 8px rgba(0,0,0,.1);border-left:4px solid <?= $color ?>;">
            <div style="font-size:2rem;font-weight:bold;color:<?= $color ?>;">
                <?= (int)$stats[$key] ?>
            </div>
            <div style="color:#666;margin-top:.4rem;"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chi ha cosa — sola lettura -->
    <h3>📋 Prestiti attivi — chi ha cosa</h3>
    <?php
    $prestiti = $conn->query("
        SELECT p.id, p.data_prestito, l.titolo, l.autore, u.email
        FROM prestiti p
        JOIN libri l ON p.id_libro = l.id
        JOIN utenti u ON p.id_utente = u.id
        WHERE p.data_restituzione IS NULL
        ORDER BY u.email ASC, p.data_prestito ASC
    ");
    ?>
    <?php if ($prestiti && $prestiti->num_rows > 0): ?>
        <div style="overflow-x:auto;">
            <table class="prestiti-table">
                <thead>
                    <tr>
                        <th>Studente</th><th>Libro</th><th>Autore</th>
                        <th>Dal</th><th>Giorni</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($p = $prestiti->fetch_assoc()):
                    $giorni = (int)floor((time() - strtotime($p['data_prestito'])) / 86400);
                    $cls = $giorni > 30 ? 'badge-danger'
                         : ($giorni > 14 ? 'badge-warning' : 'badge-success');
                ?>
                    <tr>
                        <td><?= htmlspecialchars($p['email']) ?></td>
                        <td><strong><?= htmlspecialchars($p['titolo']) ?></strong></td>
                        <td><?= htmlspecialchars($p['autore']) ?></td>
                        <td><?= date('d/m/Y', strtotime($p['data_prestito'])) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= $giorni ?> gg</span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top:1rem;color:#888;font-size:.85rem;">
            Le restituzioni vengono effettuate dagli studenti dalla propria area Prestiti.
        </p>
    <?php else: ?>
        <div class="alert alert-success">
            <strong>Nessun prestito attivo.</strong> Tutti i libri sono stati restituiti!
        </div>
    <?php endif; ?>

<?php endif; /* fine BIBLIOTECARIO */ ?>
</div><!-- /.page-container -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — Conferma Prestito
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPrestito">
    <div class="modal-box">
        <h3>Confermi il prestito?</h3>
        <div class="modal-book" id="modalPrestitoTitolo"></div>
        <p>Il libro verrà registrato a tuo nome.<br>Potrai restituirlo in qualsiasi momento.</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="chiudiModal('modalPrestito')">
                Annulla
            </button>
            <form method="GET" id="formPrestito" action="prestito.php" style="margin:0;">
                <input type="hidden" name="id" id="inputPrestitoId">
                <!-- Il prestito.php in GET mostra la pagina di conferma,
                     se vuoi POST diretta cambia action="prestito.php" e method="POST"
                     aggiungendo input name="libro_id" -->
                <button type="submit" class="btn btn-primary">
                    ✅ Sì, prendi in prestito
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL — Conferma Restituzione
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalRestituzione">
    <div class="modal-box">
        <h3>Restituisci libro</h3>
        <div class="modal-book" id="modalRestituzioneTitolo"></div>
        <p>Vuoi davvero restituire questo libro?<br>L'operazione non è reversibile.</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="chiudiModal('modalRestituzione')">
                Annulla
            </button>
            <form method="POST" action="restituisci.php" id="formRestituzione" style="margin:0;">
                <input type="hidden" name="prestito_id" id="inputRestituzioneId">
                <button type="submit" class="btn btn-primary">
                    ↩️ Sì, restituisci
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function apriModalPrestito(id, titolo) {
    document.getElementById('inputPrestitoId').value = id;
    document.getElementById('modalPrestitoTitolo').textContent = titolo;
    document.getElementById('modalPrestito').classList.add('active');
}

function apriModalRestituzione(id, titolo) {
    document.getElementById('inputRestituzioneId').value = id;
    document.getElementById('modalRestituzioneTitolo').textContent = titolo;
    document.getElementById('modalRestituzione').classList.add('active');
}

function chiudiModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Chiude cliccando fuori dal box
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) chiudiModal(this.id);
    });
});

// Chiude con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active')
                .forEach(m => m.classList.remove('active'));
    }
});
</script>

<?php include 'footer.php'; ?>