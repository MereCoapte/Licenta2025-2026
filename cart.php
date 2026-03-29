<?php
session_start();
require_once 'includes/db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = (int)$_POST['product_id'];
    $action     = $_POST['action'];

    if($action === 'add') {
        $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if($product) {
            if(isset($_SESSION['cart'][$product_id])) {
                // Deja in cos - verifica daca mai este stock pentru a mai adauga
                $currentQty = $_SESSION['cart'][$product_id]['qty'];
                $newQty     = $currentQty + $qty;

                if($newQty > $product['stock']) {
                    // Stock-ul maxim
                    $_SESSION['cart'][$product_id]['qty'] = $product['stock'];
                    $_SESSION['cart_message'] = 'Maximum stock reached for ' . $product['name'] . '!';
                } else {
                    $_SESSION['cart'][$product_id]['qty'] = $newQty;
                }
            } else {
                // Un nou item
                $_SESSION['cart'][$product_id] = [
                    'name'  => $product['name'],
                    'price' => $product['price'],
                    'image' => $product['image'],
                    'qty'   => min($qty, $product['stock']),
                    'stock' => $product['stock']
                ];
            }
        }
    }       

    // Update la cantitate
    if($action === 'update') {
        $qty = (int)$_POST['quantity'];
        if($qty > 0) {
            $_SESSION['cart'][$product_id]['qty'] = $qty;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    }

    // Sterge item-ul din cos
    if($action === 'remove') {
        unset($_SESSION['cart'][$product_id]);
    }

    header('Location: cart.php');
    exit;
}

// Calculeaza totalul
$total = 0;
if(!empty($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['qty'];
    }
}

$pageTitle = "Cosul Meu";
require_once 'includes/header.php';

?>

<h2 class="fw-bold mb-4">🛒 Cosul Tau</h2>

<?php if(isset($_SESSION['cart_message'])): ?>
    <div class="alert alert-warning">
        ⚠️ <?= htmlspecialchars($_SESSION['cart_message']) ?>
    </div>
    <?php unset($_SESSION['cart_message']); ?>
<?php endif; ?>

<?php if(empty($_SESSION['cart'])): ?>
    <div class="text-center py-5">
        <h4 class="text-muted">Cosul tau este gol.</h4>
        <a href="products.php" class="btn btn-dark mt-3">
            ← Continua Cumparaturile !
        </a>
    </div>

<?php else: ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($_SESSION['cart'] as $product_id => $item): ?>
                            <tr>
                                <td class="align-middle">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="assets\images\products\<?= htmlspecialchars($item['image']) ?>"
                                             style="width:60px; height:60px; object-fit:cover; border-radius:8px;"
                                             onerror="this.src='assets/images/placeholder.jpg'">
                                        <span class="fw-semibold">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="align-middle">
                                    <?= number_format($item['price'], 2) ?> RON
                                </td>

                                <!-- Cantitatea update -->
                                <td class="align-middle" style="width:140px;">
                                    <form method="POST" class="d-flex gap-1 align-items-center">
                                        <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                        <input type="hidden" name="action" value="update">

                                        <div class="qty-wrapper d-flex align-items-center border rounded overflow-hidden">
                                            <button type="button" class="btn text-dark qty-btn px-1" data-action="minus">−</button>
                                            <input type="number" name="quantity"
                                                class="qty-input form-control border-0 text-center"
                                                value="<?= $item['qty'] ?>"
                                                min="0"
                                                max="<?= $item['stock'] ?? 99 ?>"
                                                style="width:60px;"
                                                readonly>
                                            <button type="button" class="btn text-dark qty-btn px-1" data-action="plus">+</button>
                                        </div>

                                        <button type="submit" class="btn btn-sm btn-outline-secondary">↺</button>
                                    </form>
                                </td>

                                <!-- Subtotal -->
                                <td class="align-middle fw-bold text-danger">
                                    <?= number_format($item['price'] * $item['qty'], 2) ?> RON
                                </td>

                                <!-- Butonul de stergere -->
                                <td class="align-middle">
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                            🗑
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="products.php" class="btn btn-outline-dark mt-3">
                ← Continua Cumparaturile
            </a>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-4">Rezumatul Comenzii</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span><?= number_format($total, 2) ?> RON</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Trasnport</span>
                        <span class="text-success">Gratis</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                        <span>Total</span>
                        <span class="text-danger"><?= number_format($total, 2) ?> RON</span>
                    </div>

                    <!-- Checkout button -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="checkout.php" class="btn btn-dark w-100 py-2">
                            Finalizeaza Cumparaturile →
                        </a>
                    <?php else: ?>
                        <!-- Not logged in — prompt to login -->
                        <a href="login.php" class="btn btn-dark w-100 py-2">
                            Conectati-va la Checkout →
                        </a>
                        <p class="text-muted text-center small mt-2">
                            Ai nevoie de un cont pentru a putea plasa comanda!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>