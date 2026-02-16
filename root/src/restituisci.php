<?php
/**
 * BiblioTech - Gestione Restituzione Libro
 * IMPLEMENTAZIONE COMPLETA con UPDATE (NON DELETE!) e transazioni
 * 
 * Conforme al documento di analisi:
 * - UPDATE prestiti SET data_restituzione = NOW() (NON DELETE!)
 * - Transazioni atomiche
 * - FOR UPDATE lock
 * - Incremento copie_disponibili
 * - Verifica integrità (copie_disponibili <= copie_totali)
 * - Authorization (studente = solo propri libri, bibliotecario = tutti)
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Richiede login
requireLogin();

$error = '';
$success = '';

// Gestione restituzione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prestito_id'])) {
    $prestito_id = filter_var($_POST['prestito_id'], FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];
    $ruolo = $_SESSION['ruolo'];
    
    if (!$prestito_id) {
        $error = "ID prestito non valido.";
    } else {
        try {
            // ====================================================
            // INIZIO TRANSAZIONE ATOMICA
            // ====================================================
            $pdo->beginTransaction();
            
            // ====================================================
            // STEP 1: LOCK ROWS con FOR UPDATE
            // ====================================================
            $stmt = $pdo->prepare("
                SELECT p.*, l.titolo, l.copie_disponibili, l.copie_totali, u.email
                FROM prestiti p
                JOIN libri l ON p.id_libro = l.id
                JOIN utenti u ON p.id_utente = u.id
                WHERE p.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$prestito_id]);
            $prestito = $stmt->fetch();
            
            if (!$prestito) {
                throw new Exception("Prestito non trovato.");
            }
            
            // ====================================================
            // STEP 2: VERIFICA PRESTITO NON GIÀ RESTITUITO
            // ====================================================
            if ($prestito['data_restituzione'] !== null) {
                throw new Exception("Questo libro è già stato restituito il " . date('d/m/Y', strtotime($prestito['data_restituzione'])));
            }
            
            // ====================================================
            // STEP 3: VERIFICA AUTORIZZAZIONE
            // Studenti possono restituire solo i propri libri
            // Bibliotecari possono restituire qualsiasi libro
            // ====================================================
            if ($ruolo === 'studente' && $prestito['id_utente'] != $user_id) {
                throw new Exception("Non sei autorizzato a restituire questo libro.");
            }
            
            // ====================================================
            // STEP 4: UPDATE PRESTITO CON DATA RESTITUZIONE
            // IMPORTANTE: NON DELETE! Si usa UPDATE per mantenere lo storico
            // ====================================================
            $stmt = $pdo->prepare("
                UPDATE prestiti 
                SET data_restituzione = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$prestito_id]);
            
            if ($stmt->rowCount() !== 1) {
                throw new Exception("Errore nell'aggiornamento del prestito.");
            }
            
            // ====================================================
            // STEP 5: INCREMENTA COPIE DISPONIBILI (ATOMICO)
            // ====================================================
            $quantita = $prestito['quantita'] ?? 1;
            
            $stmt = $pdo->prepare("
                UPDATE libri 
                SET copie_disponibili = copie_disponibili + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantita, $prestito['id_libro']]);
            
            if ($stmt->rowCount() !== 1) {
                throw new Exception("Errore nell'aggiornamento delle copie disponibili.");
            }
            
            // ====================================================
            // STEP 6: VERIFICA INTEGRITÀ FINALE
            // copie_disponibili NON deve MAI superare copie_totali
            // ====================================================
            $stmt = $pdo->prepare("
                SELECT copie_disponibili, copie_totali 
                FROM libri 
                WHERE id = ?
            ");
            $stmt->execute([$prestito['id_libro']]);
            $updated = $stmt->fetch();
            
            if ($updated['copie_disponibili'] > $updated['copie_totali']) {
                throw new Exception("Errore: copie disponibili maggiori del totale. Transazione annullata.");
            }
            
            // ====================================================
            // COMMIT TRANSAZIONE
            // ====================================================
            $pdo->commit();
            
            $giorni_prestito = ceil((time() - strtotime($prestito['data_prestito'])) / 86400);
            
            $success = "✅ Restituzione completata con successo!";
            $success .= "<br><strong>Libro:</strong> " . htmlspecialchars($prestito['titolo']);
            if ($ruolo === 'bibliotecario') {
                $success .= "<br><strong>Studente:</strong> " . htmlspecialchars($prestito['email']);
            }
            $success .= "<br><strong>Data prestito:</strong> " . date('d/m/Y', strtotime($prestito['data_prestito']));
            $success .= "<br><strong>Data restituzione:</strong> " . date('d/m/Y H:i');
            $success .= "<br><strong>Giorni di prestito:</strong> $giorni_prestito";
            $success .= "<br><strong>Copie ora disponibili:</strong> " . $updated['copie_disponibili'] . " / " . $updated['copie_totali'];
            
            // Log per audit
            error_log("Restituzione completata - Prestito ID: $prestito_id, User ID: $user_id, Giorni: $giorni_prestito");
            
        } catch (Exception $e) {
            // ====================================================
            // ROLLBACK in caso di errore
            // ====================================================
            $pdo->rollBack();
            $error = $e->getMessage();
            error_log("Errore restituzione - Prestito ID: $prestito_id, User ID: $user_id, Error: " . $e->getMessage());
        }
    }
}

// Se richiesta diretta via GET (redirect da prestiti.php)
if (isset($_GET['id']) && !$success && !$error) {
    $prestito_id_get = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if ($prestito_id_get) {
        try {
            $ruolo = $_SESSION['ruolo'];
            $user_id = $_SESSION['user_id'];
            
            // Query diversa in base al ruolo
            if ($ruolo === 'studente') {
                $stmt = $pdo->prepare("
                    SELECT p.*, l.titolo, l.autore
                    FROM prestiti p
                    JOIN libri l ON p.id_libro = l.id
                    WHERE p.id = ? AND p.id_utente = ? AND p.data_restituzione IS NULL
                ");
                $stmt->execute([$prestito_id_get, $user_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.*, l.titolo, l.autore, u.email
                    FROM prestiti p
                    JOIN libri l ON p.id_libro = l.id
                    JOIN utenti u ON p.id_utente = u.id
                    WHERE p.id = ? AND p.data_restituzione IS NULL
                ");
                $stmt->execute([$prestito_id_get]);
            }
            
            $prestito_details = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Error fetching prestito details: " . $e->getMessage());
        }
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container" style="max-width: 800px; margin: 60px auto; padding: 30px;">
    <h1>📤 Restituzione Libro</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745;">
            <?= $success ?>
            <br><br>
            <?php if ($_SESSION['ruolo'] === 'studente'): ?>
                <a href="prestiti.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px;">→ Vai ai Miei Prestiti</a>
            <?php else: ?>
                <a href="gestione_restituzioni.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px;">→ Gestione Restituzioni</a>
            <?php endif; ?>
            <a href="libri.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #2196F3; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">← Catalogo Libri</a>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #dc3545;">
            <strong>⚠️ Errore:</strong> <?= htmlspecialchars($error) ?>
            <br><br>
            <?php if ($_SESSION['ruolo'] === 'studente'): ?>
                <a href="prestiti.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 6px;">← Torna ai Prestiti</a>
            <?php else: ?>
                <a href="gestione_restituzioni.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 6px;">← Gestione Restituzioni</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($prestito_details) && $prestito_details && !$success): ?>
        <div class="prestito-card" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin: 30px 0;">
            <h2><?= htmlspecialchars($prestito_details['titolo']) ?></h2>
            <p><strong>Autore:</strong> <?= htmlspecialchars($prestito_details['autore']) ?></p>
            <?php if ($_SESSION['ruolo'] === 'bibliotecario'): ?>
                <p><strong>Studente:</strong> <?= htmlspecialchars($prestito_details['email']) ?></p>
            <?php endif; ?>
            <p><strong>Data prestito:</strong> <?= date('d/m/Y H:i', strtotime($prestito_details['data_prestito'])) ?></p>
            
            <?php 
            $giorni = ceil((time() - strtotime($prestito_details['data_prestito'])) / 86400);
            $color = $giorni > 30 ? '#dc3545' : ($giorni > 14 ? '#ffc107' : '#28a745');
            ?>
            <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid <?= $color ?>; border-radius: 4px;">
                <strong>Giorni di prestito:</strong> 
                <span style="font-size: 24px; font-weight: bold; color: <?= $color ?>;">
                    <?= $giorni ?> giorni
                </span>
            </div>
            
            <form method="POST" action="restituisci.php" onsubmit="return confirm('Confermi la restituzione di questo libro?');">
                <input type="hidden" name="prestito_id" value="<?= $prestito_details['id'] ?>">
                <button type="submit" class="btn" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer;">
                    ✅ Conferma Restituzione
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <?php if ($_SESSION['ruolo'] === 'studente'): ?>
                    <a href="prestiti.php" style="color: #666; text-decoration: none;">← Indietro</a>
                <?php else: ?>
                    <a href="gestione_restituzioni.php" style="color: #666; text-decoration: none;">← Indietro</a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (!$success && !$error): ?>
        <div class="alert alert-info" style="background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px;">
            Nessun prestito selezionato o già restituito.
            <?php if ($_SESSION['ruolo'] === 'studente'): ?>
                <a href="prestiti.php" style="color: #0c5460; font-weight: bold;">Vai ai tuoi prestiti</a>
            <?php else: ?>
                <a href="gestione_restituzioni.php" style="color: #0c5460; font-weight: bold;">Vai alla gestione restituzioni</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>