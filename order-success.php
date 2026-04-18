<?php
$pageTitle = "Order Placed!";
require_once 'includes/header.php';

if(!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$orderId = (int)$_GET['id'];

// Ia detaliile de ordine
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if(!$order) {
    header('Location: index.php');
    exit;
}
?>

<div class="text-center py-5">
    <div style="font-size: 72px;">✅</div>
    <h1 class="fw-bold mt-3">Comanda Plasata cu Succes!</h1>
    <p class="text-muted fs-5">
        Multumesc pentru ca ai comandat de la noi. Numarul comenzii tale este: 
        <strong>#<?= $orderId ?></strong>.
    </p>
    <p class="text-muted">
        Livrat la: <strong><?= htmlspecialchars($order['address']) ?></strong>
    </p>
    <p class="text-muted">
        Total de plata: <strong class="text-danger"><?= number_format($order['total'], 2) ?> RON</strong>
    </p>

    <div class="d-flex justify-content-center gap-3 mt-4">
        <a href="profile.php" class="btn btn-dark px-4">
            Vezi comenzile mele
        </a>
        <a href="index.php" class="btn btn-outline-dark px-4">
            Continua cumparaturile!
        </a>
        <a href="factura.php?id=<?= $_GET['id'] ?>" 
        class="btn btn-outline-dark" target="_blank">
            <i class="fas fa-file-invoice me-2"></i>Descarca Factura
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>