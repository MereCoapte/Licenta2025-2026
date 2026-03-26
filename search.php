<?php
$pageTitle = "Search Results";
require_once 'includes/header.php';

$query    = trim($_GET['q'] ?? '');
$products = [];

if(!empty($query)) {
    $search = '%' . $query . '%';
    $stmt   = $pdo->prepare("SELECT p.*, c.name as category_name
                              FROM products p
                              LEFT JOIN categories c ON p.category_id = c.id
                              WHERE p.name LIKE ? 
                              OR p.description LIKE ?
                              OR c.name LIKE ?
                              ORDER BY p.id DESC");
    $stmt->execute([$search, $search, $search]);
    $products = $stmt->fetchAll();
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Rezultatul Cautarii</h2>
        <?php if(!empty($query)): ?>
            <small class="text-muted">
                <?= count($products) ?> results for 
                "<strong><?= htmlspecialchars($query) ?></strong>"
            </small>
        <?php endif; ?>
    </div>
    <!-- Search again -->
    <form class="d-flex gap-2" action="search.php" method="GET">
        <input type="text" name="q" class="form-control"
               value="<?= htmlspecialchars($query) ?>"
               placeholder="Search again...">
        <button type="submit" class="btn btn-dark">Cauta</button>
    </form>
</div>

<?php if(empty($query)): ?>
    <!-- No search term -->
    <div class="text-center py-5">
        <h4 class="text-muted">Scrie ceva pentru a cauta!</h4>
    </div>

<?php elseif(count($products) === 0): ?>
    <!-- No results -->
    <div class="text-center py-5">
        <h4 class="text-muted">
            Niciun produs gasit pentru "<?= htmlspecialchars($query) ?>".
        </h4>
        <a href="products.php" class="btn btn-dark mt-3">
            Rasfoiti toate produsele
        </a>
    </div>

<?php else: ?>
    <!-- Results grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach($products as $product): ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                     class="card-img-top"
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     style="height:200px; object-fit:cover;"
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
                        <button type="submit" class="btn btn-primary btn-sm">
                            Adauga in Cos
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>