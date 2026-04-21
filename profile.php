<?php

session_start();
require_once 'includes/db.php';
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = "My Profile";
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

// Must be logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user orders
$stmt = $pdo->prepare("SELECT * FROM orders 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>

<div class="row g-4">

    <!-- Left: User Info -->
    <div class="col-lg-3">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-4">
                <div style="font-size:64px;">👤</div>
                <h5 class="fw-bold mt-2">
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </h5>
                <span class="badge bg-dark">
                    <?= ucfirst($_SESSION['role']) ?>
                </span>
                <hr>
                <div class="d-grid gap-2">
                    <?php if($_SESSION['role'] === 'admin'): ?>
                        <a href="admin/dashboard.php" class="btn btn-warning btn-sm">
                            ⚙️ Panoul Admin
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        Iesi de pe Cont
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Statusul Meu</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">Total Comanda</span>
                    <strong><?= count($orders) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Total Cheltuit</span>
                    <strong class="text-danger">
                        <?= number_format(array_sum(array_column($orders, 'total')), 2) ?> RON
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Order History -->
    <div class="col-lg-9">
        <h4 class="fw-bold mb-4">Comenzile Mele</h4>

        <?php if(count($orders) === 0): ?>
            <div class="text-center py-5">
                <h5 class="text-muted">Nu ai plasat nicio comanda pana acuma</h5>
                <a href="products.php" class="btn btn-dark mt-3">
                    Incepe Cumparaturile
                </a>
            </div>
        <?php else: ?>
            <?php foreach($orders as $order): ?>

            <!-- Fetch items for this order -->
            <?php
            $items = $pdo->prepare("SELECT oi.*, p.name as product_name, p.image
                                    FROM order_items oi
                                    LEFT JOIN products p ON oi.product_id = p.id
                                    WHERE oi.order_id = ?");
            $items->execute([$order['id']]);
            $orderItems = $items->fetchAll();

            $badges = [
                'pending'    => 'warning',
                'processing' => 'info',
                'shipped'    => 'primary',
                'delivered'  => 'success'
            ];
            $badge = $badges[$order['status']] ?? 'secondary';
            ?>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Order #<?= $order['id'] ?></strong>
                        <small class="text-muted ms-2">
                            <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold text-danger">
                            <?= number_format($order['total'], 2) ?> RON
                        </span>
                        <span class="badge bg-<?= $badge ?>">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach($orderItems as $item): ?>
                        <div class="d-flex align-items-center gap-2">
                            <img src="assets/images/products/<?= htmlspecialchars($item['image'] ?? '') ?>"
                                 style="width:45px; height:45px; object-fit:cover; border-radius:6px;"
                                 onerror="this.src='assets/images/placeholder.jpg'">
                            <div>
                                <div class="small fw-semibold">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </div>
                                <div class="small text-muted">
                                    <?= $item['quantity'] ?> x <?= number_format($item['price'], 2) ?> RON
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 small text-muted">
                        📍 <?= htmlspecialchars($order['address']) ?>
                        <a href="factura.php?id=<?= $order['id'] ?>" 
                        class="btn btn-sm btn-outline-warning ms-2" target="_blank">
                            <i class="fas fa-file-invoice"></i> Factura
                        </a>
                    </div>
                        <a href="order-tracking.php?id=<?= $order['id'] ?>" 
                        class="btn btn-sm btn-outline-dark ms-2">
                            <i class="fas fa-map-marker-alt me-1"></i>Urmărește Comanda
                        </a>
                </div>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>