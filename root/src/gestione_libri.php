<?php
/**
 * BiblioTech - Gestione Catalogo
 * SOLO bibliotecari: aggiunge e rimuove libri.
 */
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole('bibliotecario');

$error   = '';
$success = '';

// ── ELIMINA LIBRO ─────────────────────────────────────────────
if (isset($_GET['elimina'])) {
    $libro_id = filter_var($_GET['elimina'], FILTER_VALIDATE_INT);
    if ($libro_id) {
        try {
            $pdo->beginTransaction();

            // Impedisce eliminazione se ci sono prestiti attivi
            $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM prestiti WHERE id_libro = ? AND data_restituzione IS NULL");
            $stmt->execute([$libro_id]);
            if ($stmt->fetch()['n'] > 0) {
                throw new Exception("Impossibile eliminare: il libro ha prestiti attivi.");
            }

            $stmt = $pdo->prepare("DELETE FROM libri WHERE id = ?");
            $stmt->execute([$libro_id]);
            $pdo->commit();
            $success = "Libro eliminato con successo.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ── AGGIUNGI LIBRO ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'aggiungi') {
    $titolo       = trim($_POST['titolo']  ?? '');
    $autore       = trim($_POST['autore']  ?? '');
    $copie        = filter_var($_POST['copie_totali'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($titolo) || empty($autore) || !$copie || $copie < 1) {
        $error = "Compila tutti i campi. Le copie devono essere almeno 1.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO libri (titolo, autore, copie_totali, copie_disponibili) VALUES (?, ?, ?, ?)");
            $stmt->execute([$titolo, $autore, $copie, $copie]);
            $success = "Libro <strong>" . htmlspecialchars($titolo) . "</strong> aggiunto!";
        } catch (Exception $e) {
            $error = "Errore: " . $e->getMessage();
        }
    }
}

// ── CATALOGO ──────────────────────────────────────────────────
$libri = $pdo->query("
    SELECT l.id, l.titolo, l.autore, l.copie_totali, l.copie_disponibili,
           (SELECT COUNT(*) FROM prestiti p WHERE p.id_libro = l.id AND p.data_restituzione IS NULL) AS prestiti_attivi
    FROM libri l
    ORDER BY l.titolo ASC
")->fetchAll();
?>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">
<div class="page-container">

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <h2>📚 Gestione Catalogo</h2>
        <a href="prestiti.php" class="btn btn-secondary">← Pannello</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><strong>Errore:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><strong>✅</strong> <?= $success ?></div>
    <?php endif; ?>

    <!-- Form aggiungi libro -->
    <div style="background:#fff;padding:2rem;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:2rem;">
        <h3 style="margin-top:0;">➕ Aggiungi nuovo libro</h3>
        <form method="POST" action="gestione_libri.php">
            <input type="hidden" name="azione" value="aggiungi">
            <div style="display:grid;grid-template-columns:1fr 1fr 80px auto;gap:1rem;align-items:end;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;">
                    <label>Titolo *</label>
                    <input type="text" name="titolo" placeholder="Es. Il Signore degli Anelli"
                           value="<?= htmlspecialchars($_POST['titolo'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Autore *</label>
                    <input type="text" name="autore" placeholder="Es. J.R.R. Tolkien"
                           value="<?= htmlspecialchars($_POST['autore'] ?? '') ?>" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Copie *</label>
                    <input type="number" name="copie_totali" min="1" max="100"
                           value="<?= htmlspecialchars($_POST['copie_totali'] ?? '1') ?>" required>
                </div>
                <div style="padding-bottom:0.05rem;">
                    <button type="submit" class="btn btn-primary">➕ Aggiungi</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabella catalogo -->
    <h3>📋 Catalogo (<?= count($libri) ?> titoli)</h3>

    <?php if (count($libri) > 0): ?>
        <div style="overflow-x:auto;">
            <table class="libri-table">
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Autore</th>
                        <th>Tot. copie</th>
                        <th>Disponibili</th>
                        <th>In prestito</th>
                        <th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($libri as $l): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($l['titolo']) ?></strong></td>
                        <td><?= htmlspecialchars($l['autore']) ?></td>
                        <td><?= $l['copie_totali'] ?></td>
                        <td>
                            <span class="badge <?= $l['copie_disponibili'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                <?= $l['copie_disponibili'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($l['prestiti_attivi'] > 0): ?>
                                <span class="badge badge-warning"><?= $l['prestiti_attivi'] ?></span>
                            <?php else: ?>
                                <span style="color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['prestiti_attivi'] == 0): ?>
                                <a href="gestione_libri.php?elimina=<?= $l['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Eliminare definitivamente questo libro?');">
                                    🗑️ Elimina
                                </a>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:.85rem;">Ha prestiti attivi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top:1rem;color:#666;font-size:.85rem;">
            Un libro può essere eliminato solo se non ha prestiti attivi.
        </p>
    <?php else: ?>
        <div class="alert alert-info">Il catalogo è vuoto. Aggiungi il primo libro qui sopra.</div>
    <?php endif; ?>

</div>
<?php include 'footer.php'; ?>