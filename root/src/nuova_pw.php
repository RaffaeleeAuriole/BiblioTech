<?php
/**
 * BiblioTech - Nuova Password
 * Permette di impostare una nuova password tramite token di reset
 */

session_start();
require_once 'config.php';

// Se già loggato, redirect
if(isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = '';
$success = '';
$token_valido = false;
$token = '';

// Verifica token da URL
if(isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verifica validità token
    $stmt = $conn->prepare("
        SELECT prt.*, u.email 
        FROM password_reset_tokens prt
        JOIN utenti u ON prt.id_utente = u.id
        WHERE prt.token = ? 
        AND prt.expires_at > NOW() 
        AND prt.used = FALSE
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $token_valido = true;
        $token_data = $result->fetch_assoc();
    } else {
        $error = "Token non valido o scaduto. Richiedi un nuovo link di reset.";
    }
}

// Gestione form cambio password
if($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token_post = $_POST['token'] ?? '';
    
    // Validazione
    if(empty($new_password) || empty($confirm_password)) {
        $error = "Tutti i campi sono obbligatori.";
    } elseif($new_password !== $confirm_password) {
        $error = "Le password non coincidono.";
    } elseif(strlen($new_password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri.";
    } else {
        // Ri-verifica token (sicurezza extra)
        $stmt = $conn->prepare("
            SELECT prt.*, u.id as user_id
            FROM password_reset_tokens prt
            JOIN utenti u ON prt.id_utente = u.id
            WHERE prt.token = ? 
            AND prt.expires_at > NOW() 
            AND prt.used = FALSE
        ");
        $stmt->bind_param("s", $token_post);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($token_info = $result->fetch_assoc()) {
            // Hash nuova password
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            
            // Inizio transazione
            $conn->begin_transaction();
            
            try {
                // 1. Aggiorna password utente
                $stmt = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $password_hash, $token_info['user_id']);
                $stmt->execute();
                
                // 2. Marca token come usato
                $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
                $stmt->bind_param("s", $token_post);
                $stmt->execute();
                
                // 3. Elimina tutti i token vecchi per questo utente
                $stmt = $conn->prepare("
                    DELETE FROM password_reset_tokens 
                    WHERE id_utente = ? 
                    AND (expires_at < NOW() OR used = TRUE)
                ");
                $stmt->bind_param("i", $token_info['user_id']);
                $stmt->execute();
                
                // Commit transazione
                $conn->commit();
                
                $success = "Password aggiornata con successo! Ora puoi effettuare il login.";
                $token_valido = false; // Previeni ulteriori submit
                
                // Redirect dopo 3 secondi
                header("refresh:3;url=login.php");
                
            } catch(Exception $e) {
                $conn->rollback();
                $error = "Errore durante l'aggiornamento della password. Riprova.";
            }
        } else {
            $error = "Token non valido. Operazione annullata.";
        }
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container">
    <?php if(!isset($_GET['token'])): ?>
        <!-- Nessun token fornito -->
        <div class="auth-form">
            <h2>🔒 Reset Password</h2>
            <div class="error-msg">
                <strong>⚠️ Errore:</strong> Token mancante. 
            </div>
            <p>Per reimpostare la password, utilizza il link ricevuto via email.</p>
            <div style="margin-top: 20px;">
                <a href="reset_pw.php" class="btn btn-primary">Richiedi Nuovo Link</a>
                <a href="login.php" class="btn btn-secondary">Torna al Login</a>
            </div>
        </div>
        
    <?php elseif($success): ?>
        <!-- Password cambiata con successo -->
        <div class="auth-form">
            <h2>✅ Password Aggiornata</h2>
            <div class="success-msg">
                <strong>✅ Successo!</strong><br>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div style="margin-top: 20px;">
                <a href="login.php" class="btn btn-primary">Vai al Login</a>
            </div>
        </div>
        
    <?php elseif(!$token_valido): ?>
        <!-- Token non valido o scaduto -->
        <div class="auth-form">
            <h2>🔒 Token Non Valido</h2>
            <div class="error-msg">
                <strong>⚠️ Errore:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="margin-top: 0;">ℹ️ Possibili cause:</h3>
                <ul style="line-height: 1.8; text-align: left;">
                    <li>Il link è scaduto (validità: 1 ora)</li>
                    <li>Il link è già stato utilizzato</li>
                    <li>Il link è stato copiato in modo errato</li>
                </ul>
            </div>
            <div style="margin-top: 20px;">
                <a href="reset_pw.php" class="btn btn-primary">Richiedi Nuovo Link</a>
                <a href="login.php" class="btn btn-secondary">Torna al Login</a>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Form per nuova password -->
        <form method="POST" action="nuova_pw.php?token=<?php echo urlencode($token); ?>" class="auth-form">
            <h2>🔑 Imposta Nuova Password</h2>
            
            <?php if($error): ?>
                <div class="error-msg">
                    <strong>⚠️ Errore:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-bottom: 20px; padding: 15px; background: #d1ecf1; border-left: 4px solid #0c5460; border-radius: 4px;">
                <strong>📧 Account:</strong> <?php echo htmlspecialchars($token_data['email']); ?>
            </div>
            
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="new_password">Nuova Password</label>
                <input type="password" id="new_password" name="new_password" 
                       placeholder="••••••••" required minlength="8"
                       autocomplete="new-password">
                <small>Almeno 8 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Conferma Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="••••••••" required minlength="8"
                       autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn btn-primary">Aggiorna Password</button>
            
            <div class="form-links">
                <a href="login.php">Ricordi la password? Accedi</a>
            </div>
        </form>
        
        <div style="max-width: 500px; margin: 20px auto; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Importante</h3>
            <ul style="line-height: 1.8; color: #856404;">
                <li>Dopo aver cambiato la password, dovrai effettuare un nuovo login</li>
                <li>Se hai attivato il 2FA, dovrai inserire anche il codice TOTP</li>
                <li>Il link di reset verrà automaticamente invalidato</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>