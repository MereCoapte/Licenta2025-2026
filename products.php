<?php
$pageTitle = "Products";
$activePage = "products";
require_once 'includes/header.php';

// Selecteaza categoriile din URL
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Creaza tabele din informatii
if($categoryId) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name
                           FROM products p
                           LEFT JOIN categories c ON p.category_id = c.id
                           WHERE p.category_id = ?
                           ORDER BY p.id DESC");
    $stmt->execute([$categoryId]);
} else {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name
                         FROM products p
                         LEFT JOIN categories c ON p.category_id = c.id
                         ORDER BY p.id DESC");
}
$products = $stmt->fetchAll();

$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<!-- Titlul paginii -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Toate Produsele</h2>
    <span class="text-muted"><?= count($products) ?> produse gasite</span>
</div>

<!-- Category Filter Buttons -->
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="products.php"
       class="btn btn-sm <?= !$categoryId ? 'btn-dark' : 'btn-outline-dark' ?>">
        Tot
    </a>
    <?php foreach($cats as $cat): ?>
        <a href="products.php?category=<?= $cat['id'] ?>"
           class="btn btn-sm <?= $categoryId == $cat['id'] ? 'btn-dark' : 'btn-outline-dark' ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if(count($products) > 0): ?>
    <div id="produsele_prezente" class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach($products as $product): ?>
        <div class="col">
            <div class="card product-card h-100 shadow-sm">
                <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                     class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>"
                     style="height: 300px; object-fit: cover;"
                     onerror="this.src='assets/images/placeholder.jpg'">
                <div class="card-body d-flex flex-column bg-dark text-white">
                    <span class="badge bg-secondary mb-2 align-self-start">
                        <?= htmlspecialchars($product['category_name'] ?? 'General') ?>
                    </span>
                    <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                    <p class="card-text text-white small flex-grow-1">
                        <?= htmlspecialchars(substr($product['description'], 0, 60)) ?>...
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-danger fs-5">
                            <?= number_format($product['price'], 2) ?> RON
                        </span>
                        <?php if($product['stock'] > 0): ?>
                            <span class="badge bg-success">In Stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Stock Epuizat</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-dark border-0 pb-3">
                    <a href="product.php?id=<?= $product['id'] ?>"
                       class="btn btn-outline-light btn-sm me-1">Afiseaza</a>
                    <?php if($product['stock'] > 0): ?>
                    <form method="POST" action="cart.php" class="d-inline">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="btn btn-light btn-sm">
                            Adauga in Cos
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="text-center py-5">
        <h4 class="text-muted">Niciun produs nu a fost gasit in categoria selectata.</h4>
        <a href="products.php" class="btn btn-dark mt-3">Vezi toate produsele</a>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>