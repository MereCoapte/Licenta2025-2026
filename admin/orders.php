<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Update status + tracking history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $newStatus = $_POST['status'];
    $orderId   = (int)$_POST['order_id'];

    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
        ->execute([$newStatus, $orderId]);

    $statusNotes = [
        'pending'    => 'Comanda in asteptare.',
        'processing' => 'Comanda a fost preluata si se proceseaza.',
        'shipped'    => 'Comanda a fost expediata.',
        'delivered'  => 'Comanda a fost livrata cu succes.',
    ];
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)")
        ->execute([$orderId, $newStatus, $statusNotes[$newStatus] ?? 'Status actualizat.']);

    header('Location: orders.php?updated=1');
    exit;
}

// Fetch all orders — JOIN cu users si guest fallback
$orders = $pdo->query("
    SELECT 
        o.*,
        u.name  AS user_name,
        u.email AS user_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comenzi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'admin-style.php'; ?>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <h3 class="fw-bold mb-4">Manageriaza comenzile</h3>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Status comanda actualizat!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (count($orders) === 0): ?>
        <div class="text-center py-5">
            <h5 class="text-muted">Inca nicio comanda.</h5>
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Adresa</th>
                        <th>Total</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order):
                    $badges = [
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'shipped'    => 'primary',
                        'delivered'  => 'success'
                    ];
                    $badge = $badges[$order['status']] ?? 'secondary';

                    // Afisam datele userului sau ale guestului
                    $isGuest   = is_null($order['user_id']);
                    $clientName  = $isGuest
                        ? ($order['guest_name']  ?? 'Guest')
                        : ($order['user_name']   ?? 'N/A');
                    $clientEmail = $isGuest
                        ? ($order['guest_email'] ?? '—')
                        : ($order['user_email']  ?? '—');
                ?>
                <tr>
                    <td class="align-middle fw-bold"><?= $order['id'] ?></td>
                    <td class="align-middle">
                        <div class="fw-semibold">
                            <?= htmlspecialchars($clientName) ?>
                            <?php if ($isGuest): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:10px;">Guest</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars($clientEmail) ?></small>
                        <?php if ($isGuest && !empty($order['guest_phone'])): ?>
                            <br><small class="text-muted">📞 <?= htmlspecialchars($order['guest_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle small"><?= htmlspecialchars($order['address']) ?></td>
                    <td class="align-middle fw-bold text-danger">
                        <?= number_format($order['total'], 2) ?> RON
                    </td>
                    <td class="align-middle small text-muted">
                        <?= date('d M Y', strtotime($order['created_at'])) ?>
                    </td>
                    <td class="align-middle">
                        <span class="badge bg-<?= $badge ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </td>
                    <td class="align-middle">
                        <form method="POST" class="d-flex gap-1 mb-1">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <option value="pending"    <?= $order['status']==='pending'    ? 'selected':'' ?>>În asteptare</option>
                                <option value="processing" <?= $order['status']==='processing' ? 'selected':'' ?>>În procesare</option>
                                <option value="shipped"    <?= $order['status']==='shipped'    ? 'selected':'' ?>>Expediat</option>
                                <option value="delivered"  <?= $order['status']==='delivered'  ? 'selected':'' ?>>Livrat</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-dark">✓</button>
                        </form>
                        <a href="../factura.php?id=<?= $order['id'] ?>"
                           class="btn btn-sm btn-outline-warning w-100" target="_blank">
                            <i class="fas fa-file-invoice me-1"></i>Factura
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>