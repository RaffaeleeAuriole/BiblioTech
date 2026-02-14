<?php
session_start();
require_once "db.php";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM utenti WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);
    $utente = $stmt->fetch();

    if($utente && password_verify($password, $utente["password_hash"])){

        $_SESSION["user_id"] = $utente["id_utente"];
        $_SESSION["ruolo"] = $utente["ruolo"];

        header("Location: libri.php");
        exit();
    } else {
        echo "Credenziali errate";
    }
}
?>

<form method="POST">
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <button type="submit">Login</button>
</form>
