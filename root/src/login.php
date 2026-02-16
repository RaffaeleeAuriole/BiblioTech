<?php
session_start();
require_once "config.php";

// Se già loggato, redirect a home
if(isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $totp_code = $_POST['totp'] ?? null;

    if(empty($email) || empty($password)) {
        $error = "Email e password sono obbligatori.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Verifica TOTP se fornito e se l'utente ha TOTP attivo
                if ($totp_code && !empty($user['totp_secret'])) {
                    require 'vendor/autoload.php';
                    $totp = new \OTPHP\TOTP($user['totp_secret']);
                    if (!$totp->verify($totp_code)) {
                        $error = "Codice TOTP non valido.";
                    }
                }
                
                // Login riuscito
                if(!$error){
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['ruolo'] = $user['ruolo'];
                    $_SESSION['email'] = $user['email'];
                    
                    header("Location: home.php");
                    exit;
                }
            } else {
                $error = "Password errata.";
            }
        } else {
            $error = "Email non registrata.";
        }
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container">
    <form method="POST" action="login.php" class="auth-form">
        <h2>Login BiblioTech</h2>
        
        <?php if($error): ?>
            <div class="error-msg">
                <strong>⚠️ Errore:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="esempio@scuola.it" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>
        
        <div class="form-group">
            <label for="totp">Codice TOTP (opzionale)</label>
            <input type="text" id="totp" name="totp" placeholder="123456" maxlength="6">
            <small>Inserisci solo se hai attivato l'autenticazione a due fattori</small>
        </div>
        
        <button type="submit" class="btn btn-primary">Accedi</button>
        
        <div class="form-links">
            <a href="reset_pw.php">Password dimenticata?</a>
            <span>•</span>
            <a href="registrazione.php">Non hai un account? Registrati</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>