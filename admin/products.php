<?php
session_start();
require_once '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM order_items WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    header('Location: products.php?deleted=1');
    exit;
}

$products = $pdo->query("SELECT p.*, c.name as category_name
                          FROM products p
                          LEFT JOIN categories c ON p.category_id = c.id
                          ORDER BY p.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manageriaza Produsele - Admin</title>
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
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        🛍️ MarketHub<br>
        <small style="font-size:11px; color:#aaa; font-weight:400;">Panoul Admin</small>
    </div>
    <nav class="nav flex-column">
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-home me-2"></i> Dashboard
        </a>
        <a href="products.php" class="nav-link active">
            <i class="fas fa-box me-2"></i> Produse
        </a>
        <a href="add-product.php" class="nav-link">
            <i class="fas fa-plus me-2"></i> Adauga Produse
        </a>
        <a href="orders.php" class="nav-link">
            <i class="fas fa-shopping-bag me-2"></i> Comenzi
        </a>
        <hr style="border-color:rgba(255,255,255,0.1); margin: 10px 24px;">
        <a href="../index.php" class="nav-link">
            <i class="fas fa-store me-2"></i> Vezi magazinul
        </a>
        <a href="../logout.php" class="nav-link text-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Iesi din cont
        </a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">Manageriaza Produsele</h3>
        <a href="add-product.php" class="btn btn-dark">
            <i class="fas fa-plus me-1"></i> Adauga Produse
        </a>
    </div>

    <?php if(isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Produs sters cu succes.</div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
        <div class="alert alert-success">Produs actualizat cu succes.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Imagine</th>
                        <th>Nume</th>
                        <th>Categorie</th>
                        <th>Pret</th>
                        <th>Stock</th>
                        <th>Actioneaza</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($products as $product): ?>
                <tr>
                    <td class="align-middle"><?= $product['id'] ?></td>
                    <td class="align-middle">
                        <img src="../assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                             style="width:45px; height:45px; object-fit:cover; border-radius:6px;"
                             onerror="this.src='../assets/images/placeholder.jpg'">
                    </td>
                    <td class="align-middle fw-semibold">
                        <?= htmlspecialchars($product['name']) ?>
                    </td>
                    <td class="align-middle">
                        <span class="badge bg-secondary">
                            <?= htmlspecialchars($product['category_name'] ?? 'N/A') ?>
                        </span>
                    </td>
                    <td class="align-middle">
                        <?= number_format($product['price'], 2) ?> RON
                    </td>
                    <td class="align-middle">
                        <?php if($product['stock'] > 0): ?>
                            <span class="text-success fw-bold"><?= $product['stock'] ?></span>
                        <?php else: ?>
                            <span class="text-danger fw-bold">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <a href="edit-product.php?id=<?= $product['id'] ?>"
                           class="btn btn-sm btn-warning me-1">
                            <i class="fas fa-edit"></i> Editeaza
                        </a>
                        <a href="products.php?delete=<?= $product['id'] ?>"
                           class="btn btn-sm btn-danger btn-delete">
                            <i class="fas fa-trash"></i> Sterge
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets\js\app.js"></script>
</body>
</html>