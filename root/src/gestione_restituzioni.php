<?php
/**
 * BiblioTech - Returns Management (Librarian Dashboard)
 * Shows all active loans with ability to process returns
 */

session_start();
require_once 'config.php';
require_once 'auth.php';

// Only librarians can access this page
requireLogin();
requireRole('bibliotecario');

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Fetch all active loans
$stmt = $pdo->query("
    SELECT p.id, p.data_prestito, p.quantita, 
           l.titolo, l.autore, l.id as libro_id,
           u.email, u.id as id_utente
    FROM prestiti p
    JOIN libri l ON p.id_libro = l.id
    JOIN utenti u ON p.id_utente = u.id
    WHERE p.data_restituzione IS NULL
    ORDER BY p.data_prestito ASC
");
$prestiti_attivi = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_active_loans,
        COUNT(DISTINCT id_utente) as active_users,
        SUM(quantita) as total_books_out
    FROM prestiti
    WHERE data_restituzione IS NULL
");
$stats = $stmt->fetch();

// Get recently returned
$stmt = $pdo->query("
    SELECT p.id, p.data_prestito, p.data_restituzione, 
           l.titolo, u.email
    FROM prestiti p
    JOIN libri l ON p.id_libro = l.id
    JOIN utenti u ON p.id_utente = u.id
    WHERE p.data_restituzione IS NOT NULL
    ORDER BY p.data_restituzione DESC
    LIMIT 5
");
$recenti = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiblioTech - Gestione Restituzioni</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #4CAF50;
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .section h2 {
            margin-top: 0;
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .loans-table {
            width: 100%;
            border-collapse: collapse;
        }
        .loans-table th, .loans-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .loans-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .loans-table tbody tr:hover {
            background: #f8f9fa;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .days-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .days-ok {
            background: #d4edda;
            color: #155724;
        }
        .days-warning {
            background: #fff3cd;
            color: #856404;
        }
        .days-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>📊 Gestione Restituzioni - Dashboard Bibliotecario</h1>
            <p>Monitora e gestisci tutti i prestiti attivi</p>
        </div>
        
        <?php if ($success === 'returned'): ?>
            <div class="alert-success">
                ✅ Restituzione registrata con successo!
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error">
                ❌ Errore: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Prestiti Attivi</div>
                <div class="stat-value"><?= $stats['total_active_loans'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Utenti con Prestiti</div>
                <div class="stat-value"><?= $stats['active_users'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Libri in Circolazione</div>
                <div class="stat-value"><?= $stats['total_books_out'] ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2>Prestiti Attivi</h2>
            
            <?php if (count($prestiti_attivi) > 0): ?>
                <table class="loans-table">
                    <thead>
                        <tr>
                            <th>Libro</th>
                            <th>Autore</th>
                            <th>Studente</th>
                            <th>Data Prestito</th>
                            <th>Giorni</th>
                            <th>Quantità</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestiti_attivi as $prestito): ?>
                            <?php 
                            $days = floor((time() - strtotime($prestito['data_prestito'])) / 86400);
                            $days_class = $days < 14 ? 'days-ok' : ($days < 30 ? 'days-warning' : 'days-danger');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($prestito['titolo']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($prestito['autore']) ?></td>
                                <td><?= htmlspecialchars($prestito['email']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($prestito['data_prestito'])) ?></td>
                                <td>
                                    <span class="days-badge <?= $days_class ?>">
                                        <?= $days ?> giorni
                                    </span>
                                </td>
                                <td><?= $prestito['quantita'] ?></td>
                                <td>
                                    <a href="libro.php?id=<?= $prestito['libro_id'] ?>" class="btn btn-info">
                                        📖 Dettagli
                                    </a>
                                    <form method="POST" action="restituisci.php" style="display: inline;">
                                        <input type="hidden" name="id_prestito" value="<?= $prestito['id'] ?>">
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Confermi la restituzione di questo libro?')">
                                            ↩️ RESTITUISCI
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>✅ Nessun prestito attivo</h3>
                    <p>Tutti i libri sono stati restituiti!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($recenti) > 0): ?>
            <div class="section">
                <h2>Ultime Restituzioni</h2>
                <table class="loans-table">
                    <thead>
                        <tr>
                            <th>Libro</th>
                            <th>Studente</th>
                            <th>Data Prestito</th>
                            <th>Data Restituzione</th>
                            <th>Durata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recenti as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['titolo']) ?></td>
                                <td><?= htmlspecialchars($item['email']) ?></td>
                                <td><?= date('d/m/Y', strtotime($item['data_prestito'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($item['data_restituzione'])) ?></td>
                                <td>
                                    <?php 
                                    $days = floor((strtotime($item['data_restituzione']) - strtotime($item['data_prestito'])) / 86400);
                                    echo $days . ($days == 1 ? ' giorno' : ' giorni');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>