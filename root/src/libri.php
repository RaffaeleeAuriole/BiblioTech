<?php
/**
 * BiblioTech - Book Catalog
 * Shows all available books with their availability status
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Require login to view catalog
requireLogin();

// Fetch all books with availability
$stmt = $pdo->query("
    SELECT id, titolo, autore, copie_totali, copie_disponibili, created_at
    FROM libri 
    ORDER BY titolo ASC
");
$libri = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioTech - Catalogo Libri</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .catalog-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .catalog-header {
            margin-bottom: 30px;
        }
        .books-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .books-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .books-table thead {
            background: #4CAF50;
            color: white;
        }
        .books-table th, .books-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .books-table th {
            font-weight: bold;
        }
        .books-table tbody tr:hover {
            background: #f5f5f5;
        }
        .book-title-link {
            color: #4CAF50;
            text-decoration: none;
            font-weight: bold;
        }
        .book-title-link:hover {
            text-decoration: underline;
        }
        .availability {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .available {
            background: #d4edda;
            color: #155724;
        }
        .unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        .limited {
            background: #fff3cd;
            color: #856404;
        }
        .stats-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-box h3 {
            margin-top: 0;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="catalog-container">
        <div class="catalog-header">
            <h1>📚 Catalogo Libri</h1>
            <p>Sfoglia il catalogo e richiedi i libri disponibili</p>
        </div>
        
        <div class="stats-box">
            <h3>Statistiche Catalogo</h3>
            <p>
                <strong>Totale titoli:</strong> <?= count($libri) ?> | 
                <strong>Copie totali:</strong> <?= array_sum(array_column($libri, 'copie_totali')) ?> | 
                <strong>Copie disponibili:</strong> <?= array_sum(array_column($libri, 'copie_disponibili')) ?>
            </p>
        </div>
        
        <?php if (count($libri) > 0): ?>
            <div class="books-table">
                <table>
                    <thead>
                        <tr>
                            <th>Titolo</th>
                            <th>Autore</th>
                            <th>Copie Totali</th>
                            <th>Disponibili</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($libri as $libro): ?>
                            <tr>
                                <td>
                                    <a href="libro.php?id=<?= $libro['id'] ?>" class="book-title-link">
                                        <?= htmlspecialchars($libro['titolo']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($libro['autore']) ?></td>
                                <td><?= $libro['copie_totali'] ?></td>
                                <td><strong><?= $libro['copie_disponibili'] ?></strong></td>
                                <td>
                                    <?php if ($libro['copie_disponibili'] > 2): ?>
                                        <span class="availability available">Disponibile</span>
                                    <?php elseif ($libro['copie_disponibili'] > 0): ?>
                                        <span class="availability limited">Poche copie</span>
                                    <?php else: ?>
                                        <span class="availability unavailable">Non disponibile</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="stats-box">
                <p style="text-align: center; color: #666;">
                    Nessun libro presente nel catalogo.
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>