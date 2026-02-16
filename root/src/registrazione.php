<?php
session_start();
require_once "config.php";

// Se già loggato, redirect a home
if(isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validazione
    if(empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Tutti i campi sono obbligatori.";
    } elseif($password !== $password_confirm) {
        $error = "Le password non coincidono.";
    } elseif(strlen($password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri.";
    } else {
        // Verifica se email già esistente
        $stmt = $conn->prepare("SELECT id FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $error = "Email già registrata nel sistema.";
        } else {
            // Genera TOTP secret per future implementazioni
            $totp_secret = bin2hex(random_bytes(10));
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO utenti (email, password, ruolo, totp_secret) VALUES (?, ?, 'studente', ?)");
            $stmt->bind_param("sss", $email, $hash, $totp_secret);

            if($stmt->execute()){
                $success = "Registrazione completata con successo! Puoi effettuare il login.";
                
                // Opzionale: invia email di benvenuto
                if(file_exists('email_helper.php')) {
                    require_once 'email_helper.php';
                    sendWelcomeEmail($email);
                }
            } else {
                $error = "Errore durante la registrazione. Riprova.";
            }
        }
    }
}
?>

<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">

<div class="page-container">
    <form method="POST" action="registrazione.php" class="auth-form">
        <h2>Registrazione Nuovo Utente</h2>
        
        <?php if($error): ?>
            <div class="error-msg">
                <strong>⚠️ Errore:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-msg">
                <strong>✅ Successo!</strong> <?php echo htmlspecialchars($success); ?>
                <br><br>
                <a href="login.php" class="btn btn-primary">Vai al Login</a>
            </div>
        <?php endif; ?>
        
        <?php if(!$success): ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="esempio@scuola.it" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
                <small>Almeno 8 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Conferma Password</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Registrati</button>
            
            <div class="form-links">
                <a href="login.php">Hai già un account? Accedi</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php include 'footer.php'; ?>