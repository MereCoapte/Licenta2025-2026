<?php
session_start();
require_once '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Update order status
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['order_id']]);
    header('Location: orders.php?updated=1');
    exit;
}

// Fetch all orders
$orders = $pdo->query("SELECT o.*, u.name as user_name, u.email
                        FROM orders o
                        LEFT JOIN users u ON o.user_id = u.id
                        ORDER BY o.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
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

    <?php if(isset($_GET['updated'])): ?>
        <div class="alert alert-success">Update la statusul comenzii!</div>
    <?php endif; ?>

    <?php if(count($orders) === 0): ?>
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
                <?php foreach($orders as $order):
                    $badges = [
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'shipped'    => 'primary',
                        'delivered'  => 'success'
                    ];
                    $badge = $badges[$order['status']] ?? 'secondary';
                ?>
                <tr>
                    <td class="align-middle"><?= $order['id'] ?></td>
                    <td class="align-middle">
                        <div class="fw-semibold"><?= htmlspecialchars($order['user_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                    </td>
                    
                    <td class="align-middle small">
                        <?= htmlspecialchars($order['address']) ?>
                    </td>
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
                        <form method="POST" class="d-flex gap-1">
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
                        class="btn btn-sm btn-outline-warning" target="_blank">
                            <i class="fas fa-file-invoice"></i> Factura
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