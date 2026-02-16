<?php
session_start();
require_once "config.php";

// Se già loggato, redirect
if(isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

require 'vendor/autoload.php';

$error = '';
$show2FA = false;
$totp_secret = '';
$qrCodeUrl = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validazione
    if(empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Tutti i campi sono obbligatori.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email non valida.";
    } elseif($password !== $password_confirm) {
        $error = "Le password non coincidono.";
    } elseif(strlen($password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri.";
    } else {

        // Controllo email esistente
        $stmt = $conn->prepare("SELECT id FROM utenti WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0) {

            $error = "Email già registrata nel sistema.";

        } else {

            // Genera TOTP Base32
            $totp = \OTPHP\TOTP::create();
            $totp->setLabel($email);
            $totp->setIssuer('BiblioTech');

            // Prendiamo il secret generato
            $totp_secret = substr($totp->getSecret(), 0, 16);

            // Ricreiamo il TOTP con secret lungo 16
            $totp = \OTPHP\TOTP::create($totp_secret);
            $totp->setLabel($email);
            $totp->setIssuer('BiblioTech');

            $qrCodeUrl = $totp->getProvisioningUri();


            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO utenti (email, password, ruolo, totp_secret) VALUES (?, ?, 'studente', ?)");
            $stmt->bind_param("sss", $email, $hash, $totp_secret);

            if($stmt->execute()) {

                $show2FA = true;

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

        <?php if($show2FA): ?>
            <div class="success-msg">
                <strong>✅ Registrazione completata!</strong>
                <br><br>

                <h3>🔐 Configura l'autenticazione a due fattori</h3>

                <p><strong>1️⃣ Scansiona il QR Code con Google Authenticator:</strong></p>

                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qrCodeUrl); ?>" 
                    alt="QR Code 2FA">

                <br><br>

                <p><strong>2️⃣ Oppure inserisci manualmente questa chiave:</strong></p>

                <div style="background:#f4f4f4;padding:10px;border-radius:5px;font-size:18px;letter-spacing:2px;">
                    <?php echo htmlspecialchars($totp_secret); ?>
                </div>

                <br>

                <p style="color:red;">
                    ⚠️ Salva questa chiave in un posto sicuro. Ti servirà per accedere.
                </p>

                <br>

                <a href="login.php" class="btn btn-primary">Vai al Login</a>
            </div>

        <?php else: ?>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
                <small>Almeno 8 caratteri</small>
            </div>

            <div class="form-group">
                <label>Conferma Password</label>
                <input type="password" name="password_confirm" required>
            </div>

            <button type="submit" class="btn btn-primary">Registrati</button>

            <div class="form-links">
                <a href="login.php">Hai già un account? Accedi</a>
            </div>

        <?php endif; ?>

    </form>
</div>

<?php include 'footer.php'; ?>
