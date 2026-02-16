<?php
/**
 * BiblioTech - Database Configuration
 * Supporta sia MySQLi che PDO per compatibilità con tutto il sistema
 */

$servername = "db";
$username = "myuser";
$password = "mypassword";
$dbname = "biblioTech";

// Connessione MySQLi (usata da alcuni file)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connessione MySQLi fallita: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Connessione PDO (usata da altri file)
try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connessione PDO fallita: " . $e->getMessage());
}
?>