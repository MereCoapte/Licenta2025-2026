<?php
require_once 'includes/header.php';

// Afiseaza primele 4 produse in Index
$stmt = $pdo->query("SELECT p.*, c.name as category_name
                     FROM products p
                     LEFT JOIN categories c ON p.category_id = c.id
                     ORDER BY p.id DESC LIMIT 4");
$products = $stmt->fetchAll();
?>

<!-- Banner-ul informatii -->
<div class="hero-banner p-5 mb-4 rounded-3">
  <div class="container-fluid py-3">
    <h1 class="display-5 fw-bold hero-title text-light">Bunvenit la MarketHub</h1>
    <p class="col-md-8 fs-4 hero-subtitle text-light">Descopera produsele pe care le avem!</p>
    <a href="products.php" class="btn btn-dark btn-lg hero-btn">
      <i class="fas fa-shopping-bag me-2"></i>Cumpara Acum :D
    </a>
  </div>
</div>

<!-- Afiseaza primele 4 produse -->
<h2 class="mb-4">Produsele Prezentate</h2>
<div id="produsele_prezente" class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
  <?php foreach($products as $product): ?>
  <div class="col">
    <div class="card product-card h-100 shadow-sm">
      <!-- Imagine pentru produse -->
      <img id="imagini" src="assets\images\products\<?= htmlspecialchars($product['image']) ?>"
           class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>"
           style="height: 300px; object-fit: cover;"
           onerror="this.src='assets/images/placeholder.jpg'">
      <div class="card-body d-flex flex-column bg-dark text-white">
        <!-- Detalii Categorii ale Produselor -->
        <span class="badge bg-secondary mb-2 align-self-start">
          <?= htmlspecialchars($product['category_name'] ?? 'General') ?>
        </span>
        <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
        <p class="card-text text-white small flex-grow-1">
          <?= htmlspecialchars(substr($product['description'], 0, 30)) ?>...
        </p>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <span class="fw-bold text-danger fs-5">
            <?= number_format($product['price'], 2) ?> RON
          </span>
          <!-- Indicator de inventar -->
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
            <i class="fas fa-cart-plus me-1"></i>Adauga in Cos
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="text-center mt-5">
  <a href="products.php" class="btn btn-outline-dark btn-lg view-all-btn">
    Vezi toate produsele <i class="fas fa-arrow-right ms-2"></i>
  </a>
</div>

<?php require_once 'includes/footer.php'; ?>
