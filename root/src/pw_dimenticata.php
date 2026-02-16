<?php
/**
 * BiblioTech - Password Recovery Request
 * Gestisce la richiesta di reset password con invio email via Mailpit
 * 
 * Implementazione conforme al documento di analisi:
 * - Token 128 caratteri
 * - Scadenza 1 ora
 * - Single-use (flag 'used')
 * - Invio email HTML tramite Mailpit
 */

session_start();
require_once 'config.php';
require_once 'email_helper.php';

// Se già loggato, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header("Location: home.php");
    exit;
}

$messaggio = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email non valida.";
    } else {
        try {
            // Cerca utente per email
            $stmt = $pdo->prepare("SELECT id, email, is_active FROM utenti WHERE email = ?");
            $stmt->execute([$email]);
            $utente = $stmt->fetch();

            if ($utente) {
                // Verifica se account è attivo
                if (!$utente['is_active']) {
                    // Non rivelare che account è disabilitato per sicurezza
                    $messaggio = "Se l'email esiste nel sistema, riceverai un link di reset.";
                } else {
                    // ======================================================
                    // GENERA TOKEN SICURO (128 CARATTERI come da schema DB)
                    // ======================================================
                    $token = bin2hex(random_bytes(64)); // 64 bytes = 128 caratteri hex
                    
                    // Calcola scadenza (1 ora = 3600 secondi come da PASSWORD_RESET_EXPIRY)
                    $expires_at = date("Y-m-d H:i:s", time() + PASSWORD_RESET_EXPIRY);

                    // ======================================================
                    // CANCELLA TOKEN PRECEDENTI NON USATI
                    // ======================================================
                    $stmt = $pdo->prepare("
                        DELETE FROM password_reset_tokens 
                        WHERE id_utente = ? AND used = FALSE
                    ");
                    $stmt->execute([$utente["id"]]);

                    // ======================================================
                    // INSERISCI NUOVO TOKEN NEL DATABASE
                    // ======================================================
                    $stmt = $pdo->prepare("
                        INSERT INTO password_reset_tokens (id_utente, token, expires_at, used)
                        VALUES (?, ?, ?, FALSE)
                    ");
                    $stmt->execute([$utente["id"], $token, $expires_at]);

                    // ======================================================
                    // INVIA EMAIL TRAMITE MAILPIT
                    // ======================================================
                    $email_sent = sendPasswordResetEmail($utente["email"], $token);

                    if ($email_sent) {
                        $messaggio = "✅ Email di recupero inviata con successo! Controlla la tua casella di posta.";
                        $messaggio .= "<br><br><small style='color: #666;'><strong>🔧 Ambiente di sviluppo:</strong> Visualizza l'email su <a href='http://localhost:8025' target='_blank' style='color: #4CAF50; font-weight: bold;'>Mailpit (http://localhost:8025)</a></small>";
                        
                        // Log per debug
                        error_log("Password reset token generated for user: " . $utente["email"]);
                    } else {
                        $error = "Errore nell'invio dell'email. Riprova più tardi.";
                        error_log("Failed to send password reset email to: " . $utente["email"]);
                    }
                }
            } else {
                // ======================================================
                // MESSAGGIO GENERICO PER PREVENIRE USER ENUMERATION
                // ======================================================
                // Non rivelare se l'email esiste o no nel sistema
                $messaggio = "Se l'email esiste nel sistema, riceverai un link di reset.";
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "Si è verificato un errore. Riprova più tardi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioTech - Recupero Password</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .recovery-container {
            max-width: 550px;
            margin: 60px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .recovery-header {
            text-align: center;
            margin-bottom: 35px;
        }
        .recovery-header h2 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 28px;
        }
        .recovery-header p {
            color: #666;
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #28a745;
            line-height: 1.6;
        }
        .alert-success strong {
            display: block;
            margin-bottom: 5px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #dc3545;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.3);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .back-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .back-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .back-link a:hover {
            color: #45a049;
            text-decoration: underline;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            color: #0d47a1;
        }
        .info-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="recovery-container">
        <div class="recovery-header">
            <h2>🔐 Recupero Password</h2>
            <p>Inserisci il tuo indirizzo email per ricevere un link di reset password sicuro</p>
        </div>

        <?php if ($messaggio): ?>
            <div class="alert-success">
                <?= $messaggio ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error">
                <strong>⚠️ Errore:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$messaggio): ?>
            <form method="POST" action="pw_dimenticata.php">
                <div class="form-group">
                    <label for="email">📧 Indirizzo Email</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tua@email.it"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email">
                </div>
                
                <button type="submit" class="btn-primary">
                    📧 Invia Link di Reset
                </button>
            </form>

            <div class="info-box">
                <strong>ℹ️ Come funziona:</strong>
                <ul>
                    <li>Inserisci la tua email registrata</li>
                    <li>Riceverai un link di reset valido per <strong>1 ora</strong></li>
                    <li>Il link può essere usato <strong>una sola volta</strong></li>
                    <li>Segui il link per impostare una nuova password</li>
                </ul>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">← Torna al Login</a>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>