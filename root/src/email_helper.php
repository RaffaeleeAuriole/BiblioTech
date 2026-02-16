<?php
/**
 * BiblioTech - Email Helper Functions
 * Gestisce l'invio di email tramite Mailpit durante lo sviluppo
 * Configurato per usare msmtp che inoltra a Mailpit (porta 1025)
 */

/**
 * Invia email di reset password con link sicuro
 * 
 * @param string $to Indirizzo email destinatario
 * @param string $token Token di reset (128 caratteri)
 * @return bool True se email inviata con successo
 */
function sendPasswordResetEmail($to, $token) {
    $subject = "BiblioTech - Recupero Password";
    
    // Costruisci link di reset
    $reset_link = "http://localhost:9000/nuova_pw.php?token=" . urlencode($token);
    
    // Email HTML con stile moderno
    $message = '
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f4f6f8;
            }
            .email-container {
                max-width: 600px;
                margin: 40px auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .email-header {
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .email-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            .email-body {
                padding: 40px 30px;
            }
            .email-body h2 {
                color: #333;
                margin-top: 0;
                font-size: 22px;
            }
            .email-body p {
                color: #666;
                margin: 15px 0;
                font-size: 16px;
            }
            .reset-button {
                display: inline-block;
                margin: 30px 0;
                padding: 16px 40px;
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white !important;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                font-size: 16px;
                box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            }
            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #4CAF50;
                padding: 20px;
                margin: 25px 0;
                border-radius: 4px;
            }
            .warning-box {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                color: #856404;
            }
            .footer {
                background: #f8f9fa;
                padding: 25px 30px;
                text-align: center;
                color: #666;
                font-size: 14px;
                border-top: 1px solid #e0e0e0;
            }
            .footer a {
                color: #4CAF50;
                text-decoration: none;
            }
            .code {
                background: #f4f4f4;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
                color: #d63384;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>🔐 Reset Password</h1>
            </div>
            
            <div class="email-body">
                <h2>Ciao!</h2>
                
                <p>Abbiamo ricevuto una richiesta di reset della password per il tuo account BiblioTech.</p>
                
                <p>Per reimpostare la tua password, clicca sul pulsante qui sotto:</p>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($reset_link) . '" class="reset-button">
                        Reimposta Password
                    </a>
                </div>
                
                <div class="info-box">
                    <strong>📌 Link alternativo:</strong><br>
                    Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
                    <span class="code">' . htmlspecialchars($reset_link) . '</span>
                </div>
                
                <div class="warning-box">
                    <strong>⚠️ Importante:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Questo link scadrà tra <strong>1 ora</strong></li>
                        <li>Può essere usato <strong>una sola volta</strong></li>
                        <li>Se non hai richiesto tu il reset, ignora questa email</li>
                    </ul>
                </div>
                
                <p style="margin-top: 30px; color: #999; font-size: 14px;">
                    Questa email è stata inviata automaticamente. Per favore non rispondere a questo messaggio.
                </p>
            </div>
            
            <div class="footer">
                <p><strong>📚 BiblioTech</strong> - Sistema di Gestione Biblioteca</p>
                <p>&copy; ' . date('Y') . ' BiblioTech. Tutti i diritti riservati.</p>
                <p style="margin-top: 15px; font-size: 12px; color: #999;">
                    Ambiente di sviluppo - Le email sono intercettate da Mailpit<br>
                    <a href="http://localhost:8025" target="_blank">Visualizza in Mailpit</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Headers per email HTML
    $headers = "From: BiblioTech - Sistema Biblioteca <no-reply@bibliotech.local>\r\n";
    $headers .= "Reply-To: no-reply@bibliotech.local\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: BiblioTech Email System\r\n";
    $headers .= "X-Priority: 1\r\n";
    
    // Invia email (msmtp la inoltrerà a Mailpit)
    $result = mail($to, $subject, $message, $headers);
    
    // Log risultato
    if ($result) {
        error_log("Password reset email sent to: $to");
    } else {
        error_log("Failed to send password reset email to: $to");
    }
    
    return $result;
}

/**
 * Invia email di benvenuto per nuovi utenti
 * 
 * @param string $to Indirizzo email destinatario
 * @param string $nome Nome utente (opzionale)
 * @return bool True se email inviata con successo
 */
function sendWelcomeEmail($to, $nome = '') {
    $subject = "Benvenuto in BiblioTech!";
    
    $saluto = $nome ? "Ciao $nome" : "Ciao";
    
    $message = '
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white;
                padding: 30px;
                text-align: center;
                border-radius: 8px 8px 0 0;
            }
            .content {
                background: #f9f9f9;
                padding: 30px;
                border-radius: 0 0 8px 8px;
            }
            .button {
                display: inline-block;
                padding: 12px 30px;
                background: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📚 Benvenuto in BiblioTech!</h1>
        </div>
        <div class="content">
            <h2>' . htmlspecialchars($saluto) . '!</h2>
            <p>La tua registrazione è stata completata con successo.</p>
            <p>Ora puoi accedere al sistema e iniziare a gestire i tuoi prestiti librari.</p>
            <div style="text-align: center;">
                <a href="http://localhost:9000/login.php" class="button">Accedi Ora</a>
            </div>
            <p>Se hai domande o problemi, non esitare a contattare il supporto.</p>
            <p style="margin-top: 30px; color: #666; font-size: 14px;">
                Buona lettura!<br>
                Il Team di BiblioTech
            </p>
        </div>
    </body>
    </html>
    ';
    
    $headers = "From: BiblioTech <no-reply@bibliotech.local>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Funzione generica per inviare email
 * 
 * @param string $to Destinatario
 * @param string $subject Oggetto
 * @param string $message Corpo del messaggio (HTML)
 * @param array $options Opzioni aggiuntive (from, reply_to, etc.)
 * @return bool True se inviata con successo
 */
function sendEmail($to, $subject, $message, $options = []) {
    $from = $options['from'] ?? 'BiblioTech <no-reply@bibliotech.local>';
    $reply_to = $options['reply_to'] ?? 'no-reply@bibliotech.local';
    
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $reply_to\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}
