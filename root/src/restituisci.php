<?php
session_start();
require_once "db.php";

if($_SESSION["ruolo"] != "bibliotecario"){
    die("Accesso negato");
}

$id_prestito = $_POST["id_prestito"];

$conn->beginTransaction();

$stmt = $conn->prepare("SELECT id_libro FROM prestiti WHERE id_prestito = ? AND data_fine IS NULL FOR UPDATE");
$stmt->execute([$id_prestito]);
$prestito = $stmt->fetch();

if($prestito){

    $conn->prepare("UPDATE prestiti SET data_fine = NOW() WHERE id_prestito = ?")
         ->execute([$id_prestito]);

    $conn->prepare("UPDATE libri SET copie_disponibili = copie_disponibili + 1 WHERE id_libro = ?")
         ->execute([$prestito["id_libro"]]);

    $conn->commit();

    header("Location: gestione_restituzioni.php");

} else {
    $conn->rollBack();
    echo "Prestito già chiuso";
}
