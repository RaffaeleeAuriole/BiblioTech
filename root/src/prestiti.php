<?php
session_start();
require_once "db.php";

$id_utente = $_SESSION["user_id"];

$sql = "SELECT l.titolo, p.data_inizio
        FROM prestiti p
        JOIN libri l ON p.id_libro = l.id_libro
        WHERE p.id_utente = ? AND p.data_fine IS NULL";

$stmt = $conn->prepare($sql);
$stmt->execute([$id_utente]);
$prestiti = $stmt->fetchAll();
?>

<h2>I tuoi prestiti</h2>

<?php foreach($prestiti as $p): ?>
    <p><?= $p["titolo"] ?> - <?= $p["data_inizio"] ?></p>
<?php endforeach; ?>
