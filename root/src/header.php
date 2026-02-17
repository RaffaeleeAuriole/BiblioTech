<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioTech</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>📚 BiblioTech</h1>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="libri.php">Catalogo</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>

                <?php if ($_SESSION['ruolo'] === 'studente'): ?>
                    <li><a href="prestiti.php">I miei prestiti</a></li>
                <?php endif; ?>

                <?php if ($_SESSION['ruolo'] === 'bibliotecario'): ?>
                    <li><a href="prestiti.php">Panoramica</a></li>
                    <li><a href="gestione_libri.php">Catalogo+</a></li>
                    <li><a href="gestione_restituzioni.php">Dashboard</a></li>
                <?php endif; ?>

                <li style="opacity:.6;">
                    <a href="#" onclick="return false;">
                        (<?= htmlspecialchars($_SESSION['ruolo']) ?>)
                    </a>
                </li>
                <li><a href="logout.php">Logout</a></li>

            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="registrazione.php">Registrati</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>