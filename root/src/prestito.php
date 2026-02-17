<?php
/**
 * BiblioTech - Richiesta Prestito
 * SOLO studenti. Transazione atomica con FOR UPDATE.
 */
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole('studente');

$error   = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];

// POST = conferma prestito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['libro_id'])) {
    $libro_id = filter_var($_POST['libro_id'], FILTER_VALIDATE_INT);

    if (!$libro_id) {
        $error = "ID libro non valido.";
    } else {
        try {
            $pdo->beginTransaction();

            // Lock sulla riga libro (race condition prevention)
            $stmt = $pdo->prepare("SELECT id, titolo, autore, copie_disponibili, copie_totali FROM libri WHERE id = ? FOR UPDATE");
            $stmt->execute([$libro_id]);
            $libro = $stmt->fetch();

            if (!$libro) {
                throw new Exception("Libro non trovato.");
            }
            if ($libro['copie_disponibili'] <= 0) {
                throw new Exception("Nessuna copia disponibile. Il libro è esaurito.");
            }

            // Nessun duplicato
            $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM prestiti WHERE id_utente = ? AND id_libro = ? AND data_restituzione IS NULL");
            $stmt->execute([$user_id, $libro_id]);
            if ($stmt->fetch()['n'] > 0) {
                throw new Exception("Hai già questo libro in prestito. Restituiscilo prima.");
            }

            // Inserisce il prestito
            $stmt = $pdo->prepare("INSERT INTO prestiti (id_utente, id_libro, data_prestito, data_restituzione, quantita) VALUES (?, ?, NOW(), NULL, 1)");
            $stmt->execute([$user_id, $libro_id]);

            // Decrementa copie disponibili
            $stmt = $pdo->prepare("UPDATE libri SET copie_disponibili = copie_disponibili - 1 WHERE id = ?");
            $stmt->execute([$libro_id]);

            // Verifica integrità
            $stmt = $pdo->prepare("SELECT copie_disponibili FROM libri WHERE id = ?");
            $stmt->execute([$libro_id]);
            if ($stmt->fetch()['copie_disponibili'] < 0) {
                throw new Exception("Errore integrità. Operazione annullata.");
            }

            $pdo->commit();
            $success = "Prestito di <strong>" . htmlspecialchars($libro['titolo']) . "</strong> effettuato!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// GET = pagina di conferma con dettagli libro
$libro_det = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("SELECT id, titolo, autore, copie_disponibili, copie_totali FROM libri WHERE id = ?");
        $stmt->execute([$id]);
        $libro_det = $stmt->fetch();
    }
}
?>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">
<div class="page-container">
    <h2>📥 Richiesta Prestito</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Errore:</strong> <?= htmlspecialchars($error) ?>
            <br><br><a href="libri.php" class="btn btn-secondary">Torna al Catalogo</a>
        </div>

    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <strong>Successo!</strong> <?= $success ?>
            <br><br>
            <a href="prestiti.php" class="btn btn-primary">Vai ai Miei Prestiti</a>
            <a href="libri.php" class="btn btn-secondary">Catalogo</a>
        </div>

    <?php elseif ($libro_det): ?>
        <!-- Pagina di conferma -->
        <div style="background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:580px;margin:2rem auto;">
            <h3><?= htmlspecialchars($libro_det['titolo']) ?></h3>
            <p><strong>Autore:</strong> <?= htmlspecialchars($libro_det['autore']) ?></p>
            <div style="margin:1.5rem 0;padding:1rem;background:#f8f9fa;border-left:4px solid #27ae60;border-radius:4px;">
                <strong>Copie disponibili:</strong>
                <span style="font-size:1.5rem;font-weight:bold;color:#27ae60;margin-left:.5rem;">
                    <?= $libro_det['copie_disponibili'] ?> / <?= $libro_det['copie_totali'] ?>
                </span>
            </div>

            <?php if ($libro_det['copie_disponibili'] > 0): ?>
                <form method="POST" action="prestito.php">
                    <input type="hidden" name="libro_id" value="<?= $libro_det['id'] ?>">
                    <button type="submit" class="btn btn-primary"
                            style="width:100%;padding:1rem;font-size:1.1rem;"
                            onclick="return confirm('Confermi il prestito?');">
                        Conferma Prestito
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning"><strong>Non disponibile.</strong> Tutte le copie sono in prestito.</div>
            <?php endif; ?>

            <div style="text-align:center;margin-top:1.5rem;">
                <a href="libri.php" style="color:#666;">← Torna al Catalogo</a>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-info">
            Nessun libro selezionato. <a href="libri.php" class="btn btn-primary">Vai al Catalogo</a>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>