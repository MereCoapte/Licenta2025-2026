<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// AJAX sau POST normal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $action     = $_POST['action'] ?? '';

    if ($product_id > 0) {
        if ($action === 'add_wishlist') {
            $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?,?)")
                ->execute([$_SESSION['user_id'], $product_id]);
        } elseif ($action === 'remove_wishlist') {
            $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")
                ->execute([$_SESSION['user_id'], $product_id]);
        }
    }

    // Raspuns AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
        $check->execute([$_SESSION['user_id'], $product_id]);
        $inWishlist = (bool)$check->fetch();

        $count = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
        $count->execute([$_SESSION['user_id']]);
        $wishlistCount = (int)$count->fetchColumn();

        header('Content-Type: application/json');
        echo json_encode(['in_wishlist' => $inWishlist, 'count' => $wishlistCount]);
        exit;
    }

    header('Location: ' . BASE_URL . 'wishlist.php');
    exit;
}

// Preluam produsele
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, w.created_at as added_at
    FROM wishlist w
    JOIN products p ON w.product_id=p.id
    LEFT JOIN categories c ON p.category_id=c.id
    WHERE w.user_id=?
    ORDER BY w.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$wishlistItems = $stmt->fetchAll();

$pageTitle = "Produsele Mele Favorite";
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">
            <i class="fas fa-heart text-danger me-2"></i>Produsele Mele Favorite
        </h2>
        <small class="text-muted" id="wishlistCountText">
            <?= count($wishlistItems) ?> produse salvate
        </small>
    </div>
    <?php if (!empty($wishlistItems)): ?>
        <a href="products.php" class="btn btn-outline-dark btn-sm">+ Adauga mai multe</a>
    <?php endif; ?>
</div>

<!-- Container produse -->
<div id="wishlistContainer">
    <?php if (empty($wishlistItems)): ?>
        <div class="text-center py-5">
            <div style="font-size:80px;">🤍</div>
            <h4 class="text-muted mt-3">Nu ai niciun produs salvat încă.</h4>
            <p class="text-muted">Apasă iconița ❤️ pe orice produs pentru a-l salva aici.</p>
            <a href="<?= BASE_URL ?>products.php" class="btn btn-dark mt-2">Explorează Produsele</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4" id="wishlistGrid">
            <?php foreach ($wishlistItems as $product): ?>
            <div class="col wishlist-card-wrapper" id="wcard-<?= $product['id'] ?>">
                <div class="card product-card h-100 shadow-sm">
                    <div class="position-relative">
                        <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                             class="card-img-top"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             style="height:300px;object-fit:cover;"
                             onerror="this.src='assets/images/placeholder.jpg'">
                        <button class="position-absolute top-0 end-0 m-2 wishlist-remove"
                                data-product-id="<?= $product['id'] ?>"
                                title="Sterge din favorite"
                                style="background:rgba(220,53,69,0.9);border:none;border-radius:50%;
                                       width:36px;height:36px;cursor:pointer;">
                            <i class="fas fa-heart" style="color:#fff;"></i>
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column bg-dark text-white">
                        <span class="badge bg-secondary mb-2 align-self-start">
                            <?= htmlspecialchars($product['category_name']??'General') ?>
                        </span>
                        <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                        <p class="card-text text-white small flex-grow-1">
                            <?= htmlspecialchars(substr($product['description'],0,60)) ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="fw-bold text-danger fs-5"><?= number_format($product['price'],2) ?> RON</span>
                            <?php if ($product['stock']>0): ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Stoc Epuizat</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-white-50 mt-1" style="font-size:11px;">
                            Salvat pe <?= date('d.m.Y', strtotime($product['added_at'])) ?>
                        </small>
                    </div>
                    <div class="card-footer bg-dark border-0 pb-3 d-flex gap-1">
                        <a href="product.php?id=<?= $product['id'] ?>"
                           class="btn btn-outline-light btn-sm flex-grow-1">Afiseaza</a>
                        <?php if ($product['stock']>0): ?>
                        <form method="POST" action="cart.php" class="d-inline flex-grow-1">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="action" value="add">
                            <button type="submit" class="btn btn-light btn-sm w-100">🛒 In Cos</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Template HTML pentru starea goala (injectat via JS) -->
<template id="emptyTemplate">
    <div class="text-center py-5" id="emptyState">
        <div style="font-size:80px;">🤍</div>
        <h4 class="text-muted mt-3">Nu ai niciun produs salvat inca.</h4>
        <p class="text-muted">Apasa iconita ❤️ pe orice produs pentru a-l salva aici.</p>
        <a href="products.php" class="btn btn-dark mt-2">Exploreaza Produsele</a>
    </div>
</template>

<script>
let totalItems = <?= count($wishlistItems) ?>;

document.querySelectorAll('.wishlist-remove').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.dataset.productId;
        const card = document.getElementById('wcard-' + productId);
        const self = this;

        // Animatie disparitie
        if (card) {
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
        }

        fetch('wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'product_id=' + productId + '&action=remove_wishlist'
        })
        .then(r => r.json())
        .then(data => {
            // Stergem cardul din DOM
            setTimeout(() => {
                if (card) card.remove();

                totalItems = data.count;

                // Actualizam textul counter
                const countText = document.getElementById('wishlistCountText');
                if (countText) countText.textContent = totalItems + ' produse salvate';

                // Actualizam badge navbar
                const badge = document.getElementById('wishlist-count');
                if (badge) {
                    badge.textContent = totalItems;
                    badge.style.display = totalItems > 0 ? '' : 'none';
                }

                // Daca nu mai sunt produse, afisam starea goala
                if (totalItems === 0) {
                    const grid = document.getElementById('wishlistGrid');
                    if (grid) grid.remove();

                    const template = document.getElementById('emptyTemplate');
                    const container = document.getElementById('wishlistContainer');
                    if (template && container) {
                        container.appendChild(template.content.cloneNode(true));
                    }

                    // Ascundem si butonul "Adauga mai multe"
                    const addBtn = document.querySelector('a[href="products.php"].btn');
                    if (addBtn) addBtn.style.display = 'none';
                }
            }, 300);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>