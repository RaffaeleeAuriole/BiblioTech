<?php
session_start();
require_once "db.php";

if(!isset($_SESSION["user_id"])){
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM libri";
$stmt = $conn->query($sql);
$libri = $stmt->fetchAll();
?>

<h2>Catalogo Libri</h2>

<?php foreach($libri as $libro): ?>
    <p>
        <?= $libro["titolo"] ?> -
        Disponibili: <?= $libro["copie_disponibili"] ?>

        <?php if($_SESSION["ruolo"] == "studente" && $libro["copie_disponibili"] > 0): ?>
            <form method="POST" action="presta.php">
                <input type="hidden" name="id_libro" value="<?= $libro["id_libro"] ?>">
                <button type="submit">PRENDI IN PRESTITO</button>
            </form>
        <?php endif; ?>
    </p>
<?php endforeach; ?>

<a href="logout.php">Logout</a>
