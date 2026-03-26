<?php
$pageTitle = "Product Details";
require_once 'includes/header.php';

// Ia produsul ID din URL
if(!isset($_GET['id'])) {
    header('Location: products.php');
    exit;
}

$id = (int)$_GET['id'];

// Ia produsul
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name
                       FROM products p
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if(!$product) {
    header('Location: products.php');
    exit;
}
?>

<!-- Link-urile -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Acasa</a></li>
        <li class="breadcrumb-item"><a href="products.php">Produsele</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
    </ol>
</nav>

<!-- Detalii produs -->
<div class="row g-5">

    <div class="col-md-5">
        <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
             class="img-fluid rounded shadow"
             alt="<?= htmlspecialchars($product['name']) ?>"
             onerror="this.src='assets/images/placeholder.jpg'"
             style="width:100%; object-fit:cover; max-height:400px;">
    </div>

    <div class="col-md-7">

        <span class="badge bg-secondary mb-2">
            <?= htmlspecialchars($product['category_name'] ?? 'General') ?>
        </span>

        <h2 class="fw-bold"><?= htmlspecialchars($product['name']) ?></h2>

        <h3 class="text-danger fw-bold my-3">
            <?= number_format($product['price'], 2) ?> RON
        </h3>

        <p class="text-muted mb-4">
            <?= htmlspecialchars($product['description']) ?>
        </p>

        <p>
            Valabil:
            <?php if($product['stock'] > 0): ?>
                <span class="badge bg-success">In Stock (<?= $product['stock'] ?> ramase)</span>
            <?php else: ?>
                <span class="badge bg-danger">Stock Epuizat</span>
            <?php endif; ?>
        </p>

        <?php if($product['stock'] > 0): ?>
        <form method="POST" action="cart.php" class="d-flex gap-3 align-items-center mt-4">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="action" value="add">

                <div class="qty-wrapper d-flex align-items-center border rounded overflow-hidden">
                    <button type="button" class="btn text-dark qty-btn px-3" data-action="minus">−</button>
                    <input type="number" name="quantity" class="qty-input form-control border-0 text-center" readonly
                        value="1" min="1" max="<?= $product['stock'] ?>"
                        style="width:60px;">
                    <button type="button" class="btn text-dark qty-btn px-3" data-action="plus">+</button>
                </div>

            <button type="submit" class="btn btn-dark px-4 py-2">
                🛒 Adauga in Cos
            </button>
        </form>
        <?php endif; ?>

        <a href="products.php" class="btn btn-outline-secondary mt-4">
            ← Intoarce-te
        </a>

    </div>
</div>


<script src="C:\xampp\htdocs\Ecommerce_site\assets\js\app.js"></script>

<?php require_once 'includes/footer.php'; ?>