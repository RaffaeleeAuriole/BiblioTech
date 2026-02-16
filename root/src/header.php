<?php
if(session_status() === PHP_SESSION_NONE) session_start();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>BiblioTech</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>BiblioTech</h1>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="libri.php">Libri</a></li>
            <li><a href="prestiti.php">Prestiti</a></li>

            <?php if(isset($_SESSION['user_id'])): ?>
                <li><a href="#">(<?php echo $_SESSION['ruolo']; ?>)</a></li>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="registrazione.php">Registrati</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
