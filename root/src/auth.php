<?php
/**
 * BiblioTech - Authentication & Authorization
 * Gestione unificata delle sessioni e dei permessi
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Richiede che l'utente sia autenticato
 * Redirect a login.php se non loggato
 */
function requireLogin() {
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Richiede un ruolo specifico
 * @param string $role - 'studente' o 'bibliotecario'
 */
function requireRole($role) {
    requireLogin();
    if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $role) {
        http_response_code(403);
        die("Accesso non autorizzato. Richiesto ruolo: $role");
    }
}

/**
 * Verifica se l'utente è loggato
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Ottiene il ruolo dell'utente corrente
 * @return string|null - 'studente', 'bibliotecario' o null
 */
function getUserRole() {
    return $_SESSION['ruolo'] ?? null;
}

/**
 * Ottiene l'ID dell'utente corrente
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Verifica se l'utente è uno studente
 * @return bool
 */
function isStudente() {
    return getUserRole() === 'studente';
}

/**
 * Verifica se l'utente è un bibliotecario
 * @return bool
 */
function isBibliotecario() {
    return getUserRole() === 'bibliotecario';
}
?>