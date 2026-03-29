<?php
session_start();
require_once '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle delete user
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Nu sterge adminul curent
    if($id !== (int)$_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        header('Location: users.php?deleted=1');
        exit;
    }
}

// Handle schimbare rol
if(isset($_POST['change_role'])) {
    $id   = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if(in_array($role, ['admin', 'customer'])) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
        header('Location: users.php?updated=1');
        exit;
    }
}

// Fetch toti utilizatorii
$users = $pdo->query("
    SELECT u.*,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(o.total), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utilizatori - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .sidebar {
            min-height: 100vh;
            background: #1a1a2e;
            width: 240px;
            position: fixed;
            top: 0; left: 0;
            padding-top: 20px;
            z-index: 100;
        }
        .sidebar .brand {
            color: white;
            font-size: 17px;
            font-weight: 700;
            padding: 0 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 10px;
        }
        .sidebar .nav-link {
            color: #aaa;
            padding: 11px 24px;
            border-left: 3px solid transparent;
            transition: all 0.2s;
            font-size: 14px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.06);
            border-left-color: #e84700;
        }
        .sidebar .nav-link i { width: 20px; }
        .main-content {
            margin-left: 240px;
            padding: 28px 32px;
            min-height: 100vh;
        }
        .table-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            font-weight: 600;
        }
        .table td { vertical-align: middle; font-size: 13.5px; }
        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <div>MarketHub<br>
        <small style="font-size:10px; color:#aaa; font-weight:400;">Panou Admin</small></div>
    </div>
    <nav class="nav flex-column mt-2">
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="products.php" class="nav-link">
            <i class="fas fa-box"></i> Produse
        </a>
        <a href="add-product.php" class="nav-link">
            <i class="fas fa-plus"></i> Adauga Produs
        </a>
        <a href="orders.php" class="nav-link">
            <i class="fas fa-shopping-bag"></i> Comenzi
        </a>
        <a href="users.php" class="nav-link active">
            <i class="fas fa-users"></i> Utilizatori
        </a>
        <hr style="border-color:rgba(255,255,255,0.08); margin:12px 20px;">
        <a href="../index.php" class="nav-link">
            <i class="fas fa-store"></i> Vezi magazinul
        </a>
        <a href="../logout.php" class="nav-link" style="color:#ff6b6b;">
            <i class="fas fa-sign-out-alt"></i> Iesi din cont
        </a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Utilizatori</h4>
            <small class="text-muted"><?= count($users) ?> conturi inregistrate</small>
        </div>
    </div>

    <!-- Mesaje -->
    <?php if(isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Utilizatorul a fost sters.</div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
        <div class="alert alert-success">Rolul a fost actualizat.</div>
    <?php endif; ?>

    <!-- Tabel utilizatori -->
    <div class="table-card">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Utilizator</th>
                    <th>Email</th>
                    <th>Comenzi</th>
                    <th>Total Cheltuit</th>
                    <th>Data Inregistrare</th>
                    <th>Rol</th>
                    <th>Actiuni</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="fw-semibold">
                            <?= htmlspecialchars($user['name']) ?>
                            <?php if($user['id'] == $_SESSION['user_id']): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:9px;">Tu</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($user['email']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= $user['total_orders'] ?> comenzi
                    </span>
                </td>
                <td class="fw-bold text-danger">
                    <?= number_format($user['total_spent'], 2) ?> RON
                </td>
                <td class="text-muted small">
                    <?= date('d M Y', strtotime($user['created_at'])) ?>
                </td>
                <td>
                    <!-- Schimbare rol -->
                    <form method="POST" class="d-flex gap-1">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <select name="role" class="form-select form-select-sm"
                                style="width:110px;"
                                <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="customer" <?= $user['role']==='customer' ? 'selected':'' ?>>
                                Client
                            </option>
                            <option value="admin" <?= $user['role']==='admin' ? 'selected':'' ?>>
                                Admin
                            </option>
                        </select>
                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                            <button type="submit" name="change_role"
                                    class="btn btn-sm btn-outline-dark">✓</button>
                        <?php endif; ?>
                    </form>
                </td>
                <td>
                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <a href="users.php?delete=<?= $user['id'] ?>"
                           class="btn btn-sm btn-danger btn-delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>