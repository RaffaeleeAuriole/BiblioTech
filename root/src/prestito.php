<?php
/**
 * BiblioTech - Gestione Prestito Libro
 * IMPLEMENTAZIONE COMPLETA con transazioni, race condition prevention, validazioni
 * 
 * Conforme al documento di analisi:
 * - Transazioni atomiche (BEGIN/COMMIT/ROLLBACK)
 * - FOR UPDATE lock per prevenire race conditions
 * - Verifica copie disponibili
 * - Verifica prestito duplicato
 * - Decremento atomico copie_disponibili
 * - Audit trail completo
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Richiede login
requireLogin();

// Solo studenti possono prendere libri in prestito
requireRole('studente');

$error = '';
$success = '';

// Gestione richiesta prestito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['libro_id'])) {
    $libro_id = filter_var($_POST['libro_id'], FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'];
    
    if (!$libro_id) {
        $error = "ID libro non valido.";
    } else {
        try {
            // ====================================================
            // INIZIO TRANSAZIONE ATOMICA
            // ====================================================
            $pdo->beginTransaction();
            
            // ====================================================
            // STEP 1: LOCK ROW con FOR UPDATE (race condition prevention)
            // ====================================================
            $stmt = $pdo->prepare("
                SELECT id, titolo, autore, copie_disponibili, copie_totali
                FROM libri 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$libro_id]);
            $libro = $stmt->fetch();
            
            if (!$libro) {
                throw new Exception("Libro non trovato.");
            }
            
            // ====================================================
            // STEP 2: VERIFICA COPIE DISPONIBILI
            // ====================================================
            if ($libro['copie_disponibili'] <= 0) {
                throw new Exception("Libro non disponibile. Tutte le copie sono attualmente in prestito.");
            }
            
            // ====================================================
            // STEP 3: VERIFICA PRESTITO DUPLICATO
            // L'utente non può avere lo stesso libro in prestito due volte
            // ====================================================
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM prestiti 
                WHERE id_utente = ? 
                  AND id_libro = ? 
                  AND data_restituzione IS NULL
            ");
            $stmt->execute([$user_id, $libro_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                throw new Exception("Hai già questo libro in prestito. Restituiscilo prima di richiederlo di nuovo.");
            }
            
            // ====================================================
            // STEP 4: CREA RECORD PRESTITO
            // ====================================================
            $stmt = $pdo->prepare("
                INSERT INTO prestiti (id_utente, id_libro, data_prestito, data_restituzione, quantita)
                VALUES (?, ?, NOW(), NULL, 1)
            ");
            $stmt->execute([$user_id, $libro_id]);
            $prestito_id = $pdo->lastInsertId();
            
            // ====================================================
            // STEP 5: DECREMENTA COPIE DISPONIBILI (ATOMICO)
            // ====================================================
            $stmt = $pdo->prepare("
                UPDATE libri 
                SET copie_disponibili = copie_disponibili - 1 
                WHERE id = ?
            ");
            $stmt->execute([$libro_id]);
            
            // Verifica che l'update sia andato a buon fine
            if ($stmt->rowCount() !== 1) {
                throw new Exception("Errore nell'aggiornamento delle copie disponibili.");
            }
            
            // ====================================================
            // STEP 6: VERIFICA INTEGRITÀ FINALE
            // Copie disponibili non deve mai essere negativo
            // ====================================================
            $stmt = $pdo->prepare("SELECT copie_disponibili FROM libri WHERE id = ?");
            $stmt->execute([$libro_id]);
            $updated = $stmt->fetch();
            
            if ($updated['copie_disponibili'] < 0) {
                throw new Exception("Errore: copie disponibili negative. Transazione annullata.");
            }
            
            // ====================================================
            // COMMIT TRANSAZIONE
            // ====================================================
            $pdo->commit();
            
            $success = "✅ Prestito effettuato con successo!";
            $success .= "<br><strong>Libro:</strong> " . htmlspecialchars($libro['titolo']);
            $success .= "<br><strong>Data prestito:</strong> " . date('d/m/Y H:i');
            $success .= "<br><strong>Copie rimanenti:</strong> " . $updated['copie_disponibili'];
            
            // Log per audit
            error_log("Prestito creato - User ID: $user_id, Libro ID: $libro_id, Prestito ID: $prestito_id");
            
        } catch (Exception $e) {
            // ====================================================
            // ROLLBACK in caso di errore
            // ====================================================
            $pdo->rollBack();
            $error = $e->getMessage();
            error_log("Errore prestito - User ID: $user_id, Libro ID: $libro_id, Error: " . $e->getMessage());
        }
    }
}

// Se è una richiesta diretta via GET (da libro.php)
$libro_id_get = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$libro_details = null;

if ($libro_id_get) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM libri WHERE id = ?");
        $stmt->execute([$libro_id_get]);
        $libro_details = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching libro details: " . $e->getMessage());
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container" style="max-width: 800px; margin: 60px auto; padding: 30px;">
    <h1>📚 Richiesta Prestito</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745;">
            <?= $success ?>
            <br><br>
            <a href="prestiti.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">→ Vai ai Miei Prestiti</a>
            <a href="libri.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #2196F3; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">← Torna al Catalogo</a>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #dc3545;">
            <strong>⚠️ Errore:</strong> <?= htmlspecialchars($error) ?>
            <br><br>
            <a href="libri.php" class="btn" style="display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">← Torna al Catalogo</a>
        </div>
    <?php endif; ?>
    
    <?php if ($libro_details && !$success): ?>
        <div class="libro-card" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin: 30px 0;">
            <h2><?= htmlspecialchars($libro_details['titolo']) ?></h2>
            <p><strong>Autore:</strong> <?= htmlspecialchars($libro_details['autore']) ?></p>
            <p><strong>ISBN:</strong> <?= htmlspecialchars($libro_details['isbn']) ?></p>
            <p><strong>Categoria:</strong> <?= htmlspecialchars($libro_details['categoria']) ?></p>
            <p><strong>Anno:</strong> <?= htmlspecialchars($libro_details['anno_pubblicazione']) ?></p>
            
            <div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px;">
                <strong>Disponibilità:</strong> 
                <span style="font-size: 24px; font-weight: bold; color: <?= $libro_details['copie_disponibili'] > 0 ? '#28a745' : '#dc3545' ?>;">
                    <?= $libro_details['copie_disponibili'] ?> / <?= $libro_details['copie_totali'] ?>
                </span> copie
            </div>
            
            <?php if ($libro_details['copie_disponibili'] > 0): ?>
                <form method="POST" action="prestito.php" onsubmit="return confirm('Confermi di voler prendere in prestito questo libro?');">
                    <input type="hidden" name="libro_id" value="<?= $libro_details['id'] ?>">
                    <button type="submit" class="btn" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer;">
                        ✅ Conferma Prestito
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; text-align: center;">
                    ⚠️ Non ci sono copie disponibili al momento
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="libro.php?id=<?= $libro_details['id'] ?>" style="color: #666; text-decoration: none;">← Indietro</a>
            </div>
        </div>
    <?php elseif (!$success && !$error): ?>
        <div class="alert alert-info" style="background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px;">
            Nessun libro selezionato. <a href="libri.php" style="color: #0c5460; font-weight: bold;">Vai al catalogo</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>