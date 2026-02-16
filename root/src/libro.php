<?php
require 'config.php';
require 'auth.php';
requireLogin();

$id = $_GET['id'] ?? 0;

// Usa PDO per compatibilità con la versione originale
$stmt = $pdo->prepare("SELECT * FROM libri WHERE id = ?");
$stmt->execute([$id]);
$libro = $stmt->fetch();

include 'header.php';
?>

<link rel="stylesheet" href="assets/style.css">

<div class="page-container">
    <?php if($libro): ?>
        <div class="libro-dettaglio">
            <h1>📖 <?php echo htmlspecialchars($libro['titolo']); ?></h1>
            
            <div class="libro-info">
                <div class="info-row">
                    <span class="info-label">Autore:</span>
                    <span class="info-value"><?php echo htmlspecialchars($libro['autore']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">ISBN:</span>
                    <span class="info-value"><?php echo htmlspecialchars($libro['isbn'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Copie Totali:</span>
                    <span class="info-value"><?php echo $libro['copie_totali']; ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Copie Disponibili:</span>
                    <span class="info-value">
                        <span class="badge <?php echo $libro['copie_disponibili'] > 0 ? 'badge-success' : 'badge-danger'; ?>">
                            <?php echo $libro['copie_disponibili']; ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Stato:</span>
                    <span class="info-value">
                        <?php if($libro['copie_disponibili'] > 0): ?>
                            <span class="status-available">✅ Disponibile per il prestito</span>
                        <?php else: ?>
                            <span class="status-unavailable">❌ Tutte le copie sono in prestito</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="libro-actions">
                <?php if(isStudente()): ?>
                    <?php if($libro['copie_disponibili'] > 0): ?>
                        <a href="prestito.php?libro_id=<?php echo $libro['id']; ?>" 
                           class="btn btn-primary btn-lg"
                           onclick="return confirm('Confermi di voler richiedere il prestito di questo libro?');">
                            📥 Richiedi Prestito
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg" disabled>
                            ❌ Non Disponibile
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="libri.php" class="btn btn-secondary">
                    ← Torna al Catalogo
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <strong>⚠️ Libro non trovato</strong>
            <p>Il libro richiesto non esiste nel catalogo.</p>
            <a href="libri.php" class="btn btn-secondary">Torna al Catalogo</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>