<?php include 'header.php'; ?>
<link rel="stylesheet" href="assets/style.css">


<?php
if(isset($_SESSION['user_id'])){
            echo '<div class="home-hero">
                <h1>Benvenuto in BiblioTech</h1>
                <h2>Gestisci i libri e i prestiti della tua biblioteca.</h2>
                <p>BiblioTech ti permette di registrarti, visualizzare il catalogo dei libri, richiedere prestiti, restituire libri e gestire tutto tramite autenticazione sicura con password e TOTP.</p>
            </div>';
    }else{
        echo '<div class="home-hero">
                <h1>Benvenuto in BiblioTech</h1>
                <h2>Gestisci i libri e i prestiti della tua biblioteca.</h2>
                <p>BiblioTech ti permette di registrarti, visualizzare il catalogo dei libri, richiedere prestiti, restituire libri e gestire tutto tramite autenticazione sicura con password e TOTP.</p>
                <a href="login.php" class="btn">Login</a>
                <a href="registrazione.php" class="btn">Registrati</a>
            </div>';
    }
?>


<?php include 'footer.php'; ?>
