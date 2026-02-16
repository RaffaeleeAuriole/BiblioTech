<?php
session_start();
require 'config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

?>

<?php include 'header.php'; ?>

<div class="page-container">
    <h2>Prestiti</h2>

<?php if($_SESSION['ruolo'] === 'studente'): ?>

    <h3>Libri disponibili per il prestito</h3>

    <?php
    $libri = $conn->query("SELECT * FROM libri WHERE copie_disponibili > 0");
    ?>

    <table class="libri-table">
        <tr>
            <th>Titolo</th>
            <th>Autore</th>
            <th>Disponibili</th>
            <th>Azione</th>
        </tr>

        <?php while($row = $libri->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['titolo']); ?></td>
            <td><?php echo htmlspecialchars($row['autore']); ?></td>
            <td><?php echo $row['copie_disponibili']; ?></td>
            <td>
                <form method="POST" action="prestito.php">
                    <input type="hidden" name="libro_id" value="<?php echo $row['id']; ?>">
                    <button class="button">Prendi in prestito</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php endif; ?>


<?php if($_SESSION['ruolo'] === 'bibliotecario'): ?>

    <h3>Prestiti attivi</h3>

    <?php
    $prestiti = $conn->query("
        SELECT p.*, l.titolo, u.email
        FROM prestiti p
        JOIN libri l ON p.id_libro = l.id
        JOIN utenti u ON p.id_utente = u.id
        WHERE p.data_restituzione IS NULL
    ");
    ?>

    <table class="prestiti-table">
        <tr>
            <th>Studente</th>
            <th>Libro</th>
            <th>Data Prestito</th>
            <th>Azione</th>
        </tr>

        <?php while($p = $prestiti->fetch_assoc()): ?>
        <tr>
            <td><?php echo $p['email']; ?></td>
            <td><?php echo $p['titolo']; ?></td>
            <td><?php echo $p['data_prestito']; ?></td>
            <td>
                <form method="POST" action="restituisci.php">
                    <input type="hidden" name="prestito_id" value="<?php echo $p['id']; ?>">
                    <button class="button">Restituisci</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

<?php endif; ?>

</div>

<?php include 'footer.php'; ?>