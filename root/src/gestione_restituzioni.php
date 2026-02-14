<?php
session_start();
require_once "db.php";

if($_SESSION["ruolo"] != "bibliotecario"){
    die("Accesso negato");
}

$sql = "SELECT p.id_prestito, l.titolo
        FROM prestiti p
        JOIN libri l ON p.id_libro = l.id_libro
        WHERE p.data_fine IS NULL";

$prestiti = $conn->query($sql)->fetchAll();
?>

<h2>Prestiti Attivi</h2>

<?php foreach($prestiti as $p): ?>
    <p>
        <?= $p["titolo"] ?>
        <form method="POST" action="restituisci.php">
            <input type="hidden" name="id_prestito" value="<?= $p["id_prestito"] ?>">
            <button type="submit">RESTITUISCI</button>
        </form>
    </p>
<?php endforeach; ?>
