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
    <?php require_once 'admin-style.php'; ?>
</head>
<body>

<?php require_once 'sidebar.php'; ?>
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