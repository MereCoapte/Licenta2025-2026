<?php
session_start();
require_once 'includes/db.php';

// Already logged in? Go home
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // ── Validation ────────────────────────────
    if(empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';

    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } elseif(strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';

    } elseif($password !== $confirm) {
        $error = 'Passwords do not match.';

    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if($stmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            // All good — save user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);

            $success = 'Account created! You can now login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inregistreaza-te - MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width:100%; max-width:460px;">

        <!-- Logo -->
        <div class="text-center mb-4">
            <h2 class="fw-bold">🛍️ MarketHub</h2>
            <p class="text-muted">Creaza un cont</p>
        </div>

        <!-- Messages -->
        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST">

            <div class="mb-3">
                <label class="form-label fw-semibold">Numele</label>
                <input type="text" name="name" class="form-control"
                       placeholder="John Doe"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Adresa de email</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Parola</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Minimum 6 characters"
                       required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirma parola</label>
                <input type="password" name="confirm_password" class="form-control"
                       placeholder="Repeat your password"
                       required>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">
                Creaza contul
            </button>
        </form>

        <!-- Login link -->
        <p class="text-center text-muted mt-3 mb-0">
            Ai deja un cont?
            <a href="login.php" class="text-dark fw-bold">Autentificate aici</a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>