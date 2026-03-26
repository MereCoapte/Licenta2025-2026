<?php
session_start();
require_once '../includes/db.php';

// Block non-admins
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ── Fetch Stats ───────────────────────────────────────────────
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue  = $pdo->query("SELECT SUM(total) FROM orders")->fetchColumn() ?? 0;

// Latest 5 orders
$recentOrders  = $pdo->query("SELECT o.*, u.name as user_name 
                               FROM orders o 
                               LEFT JOIN users u ON o.user_id = u.id 
                               ORDER BY o.created_at DESC 
                               LIMIT 5")->fetchAll();

// Latest 5 products
$recentProducts = $pdo->query("SELECT p.*, c.name as category_name 
                                FROM products p
                                LEFT JOIN categories c ON p.category_id = c.id
                                ORDER BY p.id DESC 
                                LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #1a1a2e;
            width: 240px;
            position: fixed;
            top: 0; left: 0;
            padding-top: 20px;
            z-index: 100;
        }
        .sidebar .nav-link {
            color: #aaa;
            padding: 12px 24px;
            border-radius: 0;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.08);
            border-left: 3px solid #e84700;
        }
        .sidebar .brand {
            color: white;
            font-size: 18px;
            font-weight: 700;
            padding: 0 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        .main-content {
            margin-left: 240px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 24px;
            color: white;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-icon {
            font-size: 36px;
            opacity: 0.8;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 800;
        }
    </style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────── -->
<div class="sidebar">
    <div class="brand">
        🛍️ MarketHub<br>
        <small style="font-size:11px; color:#aaa; font-weight:400;">Panoul Admin</small>
    </div>
    <nav class="nav flex-column">
        <a href="dashboard.php" class="nav-link active">
            <i class="fas fa-home me-2"></i> Dashboard
        </a>
        <a href="products.php" class="nav-link">
            <i class="fas fa-box me-2"></i> Produse
        </a>
        <a href="add-product.php" class="nav-link">
            <i class="fas fa-plus me-2"></i> Adauga Produse
        </a>
        <a href="orders.php" class="nav-link">
            <i class="fas fa-shopping-bag me-2"></i> Comenzi
        </a>
        <a href="users.php" class="nav-link">
            <i class="fas fa-users me-2"></i> Utilizatorii
        </a>
        <hr style="border-color:rgba(255,255,255,0.1); margin: 10px 24px;">
        <a href="../index.php" class="nav-link">
            <i class="fas fa-store me-2"></i> Afiseaza magazinul
        </a>
        <a href="../logout.php" class="nav-link text-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Iesi de pe Cont
        </a>
    </nav>
</div>

<!-- ── Main Content ────────────────────────────────────── -->
<div class="main-content">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Dashboard</h3>
            <small class="text-muted">
                Bine ai revenit, <?= htmlspecialchars($_SESSION['user_name']) ?>!
            </small>
        </div>
        <a href="add-product.php" class="btn btn-dark">
            <i class="fas fa-plus me-1"></i> Adauga Produse
        </a>
    </div>

    <!-- ── Stat Cards ──────────────────────────────────── -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small mb-1">Total Produse</div>
                        <div class="stat-number"><?= $totalProducts ?></div>
                    </div>
                    <i class="fas fa-box stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small mb-1">Total Utilizatori</div>
                        <div class="stat-number"><?= $totalUsers ?></div>
                    </div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small mb-1">Total Comenzi</div>
                        <div class="stat-number"><?= $totalOrders ?></div>
                    </div>
                    <i class="fas fa-shopping-bag stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small mb-1">Total Venituri</div>
                        <div class="stat-number"><?= number_format($totalRevenue, 0) ?></div>
                        <div class="text-white-50 small">RON</div>
                    </div>
                    <i class="fas fa-coins stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Recent Orders + Recent Products ────────────── -->
    <div class="row g-4">

        <!-- Recent Orders -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold border-0 pt-3">
                    <i class="fas fa-shopping-bag me-2 text-primary"></i>Comenzi Recente
                </div>
                <div class="card-body p-0">
                    <?php if(count($recentOrders) > 0): ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($recentOrders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['user_name']) ?></td>
                                <td><?= number_format($order['total'], 2) ?> RON</td>
                                <td>
                                    <?php
                                    $badges = [
                                        'pending'    => 'warning',
                                        'processing' => 'info',
                                        'shipped'    => 'primary',
                                        'delivered'  => 'success'
                                    ];
                                    $badge = $badges[$order['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Nicio comanda pana acuma.</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="orders.php" class="btn btn-sm btn-outline-dark">
                        Vezi toate comenzile →
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Products -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold border-0 pt-3">
                    <i class="fas fa-box me-2 text-success"></i>Produse Recente
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nume</th>
                                <th>Categorie</th>
                                <th>Pret</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($recentProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($product['category_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= number_format($product['price'], 2) ?> RON</td>
                                <td>
                                    <?php if($product['stock'] > 0): ?>
                                        <span class="text-success fw-bold"><?= $product['stock'] ?></span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="products.php" class="btn btn-sm btn-outline-dark">
                        Vezi toate produsele →
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>