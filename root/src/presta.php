<?php
session_start();
require_once "db.php";

if($_SESSION["ruolo"] != "studente"){
    die("Accesso negato");
}

$id_libro = $_POST["id_libro"];
$id_utente = $_SESSION["user_id"];

$conn->beginTransaction();

try {

    $stmt = $conn->prepare("SELECT copie_disponibili FROM libri WHERE id_libro = ? FOR UPDATE");
    $stmt->execute([$id_libro]);
    $libro = $stmt->fetch();

    if($libro["copie_disponibili"] <= 0){
        throw new Exception("Non disponibile");
    }

    $conn->prepare("INSERT INTO prestiti (id_utente, id_libro) VALUES (?, ?)")
         ->execute([$id_utente, $id_libro]);

    $conn->prepare("UPDATE libri SET copie_disponibili = copie_disponibili - 1 WHERE id_libro = ?")
         ->execute([$id_libro]);

    $conn->commit();

    header("Location: prestiti.php");

} catch(Exception $e){

    $conn->rollBack();
    echo "Errore prestito";

}
