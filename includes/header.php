<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';


// Cate iteme sunt in cart
$cartCount = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cartCount += $item['qty'];
    }
}

$wishlistCount = 0;
if (isset($_SESSION['user_id'])) {
    $wCount = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $wCount->execute([$_SESSION['user_id']]);
    $wishlistCount = (int)$wCount->fetchColumn();
}

// Extrage categoriile din navbar
$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="assets\images\FavIcon.png">
  <title>MarketHub - Site de E-Commerce</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="assets\css\style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="link-light navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">
      <i class="fas fa-store me-2"></i>MarketHub
    </a>
    <!-- Search Bar-ul -->
    <form class="d-flex mx-auto w-50" action="search.php" method="GET">
      <input class="form-control me-2" type="search" name="q" placeholder="Cauta produsele...">
      <button id="btn_submit" class="btn" type="submit">
        <i class="fas fa-search text-white"></i>
      </button>
    </form>
    <!-- Link-urile din NavBar (Cosul de cumparaturi/ Panoul Admin/ Login&Register/ Logout) -->
    <div class="d-flex align-items-center gap-3">
     <?php if (isset($_SESSION['user_id'])): ?>
       <a href="wishlist.php" class="text-white position-relative" title="Produsele mele favorite">
        <i class="fas fa-heart fa-lg"></i>
        <?php if ($wishlistCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  id="wishlist-count" style="font-size:10px;">
                <?= $wishlistCount ?>
            </span>
        <?php else: ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  id="wishlist-count" style="font-size:10px; display:none;">0</span>
        <?php endif; ?>
      </a>
<?php endif; ?>

  <a href="cart.php" class="text-white position-relative">
    <i class="fas fa-shopping-cart fa-lg"></i>
      <?php if ($cartCount > 0): ?>
        <span class="position-sticky top-0 start-100 badge rounded-pill bg-danger">
            <?= $cartCount ?>
        </span>
      <?php endif; ?>
  </a>
 
  <?php if (isset($_SESSION['user_id'])): ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="admin/dashboard.php" class="btn btn-sm btn-warning me-2">⚙️ Panoul Admin</a>
    <?php endif; ?>
      <a href="profile.php" class="text-white text-decoration-none me-2">
        <i class="fas fa-user me-1"></i>
          <?= htmlspecialchars($_SESSION['user_name']) ?>
      </a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-dark text-light">Iesi de pe Cont</a>
  <?php else: ?>
    <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-light text-dark">Intra in Cont</a>
    <a href="<?= BASE_URL ?>register.php" class="btn btn-sm btn-secondary">Cont nou</a>
  <?php endif; ?>
 </div>
</nav>
<main class="container my-4">
