<?php
/**
 * BiblioTech - Restituzione Libro
 * SOLO LO STUDENTE può restituire, e solo i PROPRI libri.
 * Usa UPDATE data_restituzione (mai DELETE) per mantenere lo storico.
 */
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireRole('studente'); // SOLO studenti - bibliotecario NON può restituire

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prestito_id'])) {

    $prestito_id = filter_var($_POST['prestito_id'], FILTER_VALIDATE_INT);
    $user_id     = (int)$_SESSION['user_id'];

    if (!$prestito_id) {
        $error = "ID prestito non valido.";
    } else {
        try {
            $pdo->beginTransaction();

            // Legge il prestito con lock, verifica che appartenga a questo studente
            $stmt = $pdo->prepare("
                SELECT p.id, p.id_libro, p.id_utente, p.data_restituzione,
                       l.titolo, l.copie_disponibili, l.copie_totali
                FROM prestiti p
                JOIN libri l ON p.id_libro = l.id
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$prestito_id]);
            $prestito = $stmt->fetch();

            if (!$prestito) {
                throw new Exception("Prestito non trovato.");
            }

            // Sicurezza: lo studente può restituire SOLO i propri libri
            if ((int)$prestito['id_utente'] !== $user_id) {
                throw new Exception("Non sei autorizzato a restituire questo libro.");
            }

            if ($prestito['data_restituzione'] !== null) {
                throw new Exception("Questo libro è già stato restituito.");
            }

            // UPDATE (non DELETE!) per mantenere lo storico
            $stmt = $pdo->prepare("UPDATE prestiti SET data_restituzione = NOW() WHERE id = ?");
            $stmt->execute([$prestito_id]);

            // Incrementa copie disponibili
            $stmt = $pdo->prepare("UPDATE libri SET copie_disponibili = copie_disponibili + 1 WHERE id = ?");
            $stmt->execute([$prestito['id_libro']]);

            // Verifica integrità: copie_disponibili non può superare copie_totali
            $stmt = $pdo->prepare("SELECT copie_disponibili, copie_totali FROM libri WHERE id = ?");
            $stmt->execute([$prestito['id_libro']]);
            $chk = $stmt->fetch();
            if ($chk['copie_disponibili'] > $chk['copie_totali']) {
                throw new Exception("Errore integrità dati. Operazione annullata.");
            }

            $pdo->commit();
            $success = "Libro <strong>" . htmlspecialchars($prestito['titolo']) . "</strong> restituito con successo!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">
<div class="page-container">
    <h2>Restituzione Libro</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Errore:</strong> <?= htmlspecialchars($error) ?>
            <br><br>
            <a href="prestiti.php" class="btn btn-secondary">Torna ai Prestiti</a>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success">
            <strong>Successo!</strong> <?= $success ?>
            <br><br>
            <a href="prestiti.php" class="btn btn-primary">Torna ai Miei Prestiti</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Nessuna operazione richiesta.
            <a href="prestiti.php" class="btn btn-primary">Vai ai Prestiti</a>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>