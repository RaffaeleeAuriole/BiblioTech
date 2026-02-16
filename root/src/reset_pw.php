<?php
/**
 * BiblioTech - Reset Password Request
 * Genera token sicuro e invia email con link di reset
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

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email'] ?? '';
    
    if(empty($email)) {
        $error = "Inserisci la tua email.";
    } else {
        // Verifica se l'email esiste
        $stmt = $conn->prepare("SELECT id, email FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($user = $result->fetch_assoc()){
            // Genera token sicuro (64 byte = 128 caratteri hex)
            $token = bin2hex(random_bytes(64));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
            
            // Elimina eventuali token precedenti per questo utente
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE id_utente = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            // Inserisci nuovo token
            $stmt = $conn->prepare("INSERT INTO password_reset_tokens (id_utente, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['id'], $token, $expires);
            
            if($stmt->execute()) {
                // Invia email con link di reset
                if(file_exists('email_helper.php')) {
                    require_once 'email_helper.php';
                    $email_sent = sendPasswordResetEmail($email, $token);
                    
                    if($email_sent) {
                        $success = "Email di reset inviata! Controlla la tua casella di posta.";
                        $success .= "<br><br><small>In sviluppo: <a href='http://localhost:8025' target='_blank'>Apri Mailpit</a></small>";
                    } else {
                        $error = "Errore nell'invio dell'email. Riprova più tardi.";
                    }
                } else {
                    // Fallback se email_helper.php non esiste
                    $reset_link = "http://localhost:9000/nuova_pw.php?token=" . urlencode($token);
                    $success = "Token generato. Link di reset (simulato):<br>";
                    $success .= "<a href='$reset_link' target='_blank'>$reset_link</a>";
                }
            } else {
                $error = "Errore nella generazione del token. Riprova.";
            }
        } else {
            // Per sicurezza, mostra lo stesso messaggio anche se email non esiste
            // Questo previene enumeration attacks
            $success = "Se l'email esiste nel sistema, riceverai un link di reset.";
        }
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container">
    <form method="POST" action="reset_pw.php" class="auth-form">
        <h2>🔑 Password Dimenticata</h2>
        <p>Inserisci la tua email per ricevere il link di reset della password.</p>
        
        <?php if($error): ?>
            <div class="error-msg">
                <strong>⚠️ Errore:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-msg">
                <strong>✅ Successo!</strong><br>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!$success): ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="esempio@scuola.it" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Invia Link di Reset</button>
            
            <div class="form-links">
                <a href="login.php">Ricordi la password? Accedi</a>
                <span>•</span>
                <a href="registrazione.php">Non hai un account? Registrati</a>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px;">
                <a href="login.php" class="btn btn-secondary">Torna al Login</a>
            </div>
        <?php endif; ?>
    </form>
    
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3 style="margin-top: 0;">ℹ️ Informazioni</h3>
        <ul style="line-height: 1.8;">
            <li>Il link di reset è valido per <strong>1 ora</strong></li>
            <li>Può essere utilizzato <strong>una sola volta</strong></li>
            <li>Se non ricevi l'email, controlla la cartella spam</li>
            <li>In caso di problemi, contatta l'amministratore</li>
        </ul>
    </div>
</div>

<?php include 'footer.php'; ?>