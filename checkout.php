<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_check.php';

// Trebuie sa fie logat
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Daca cosul de cumparaturi este gol, intoarce-te
if(empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$error = '';

// Calculeaza totalul
$total = 0;
foreach($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['qty'];
}

// Comenzi Depuse
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['full_name']);
    $address = trim($_POST['address']);
    $city    = trim($_POST['city']);
    $phone   = trim($_POST['phone']);
    $payment = $_POST['payment'];

    if(empty($name) || empty($address) || empty($city) || empty($phone)) {
        $error = 'Te rog adauga toate field-urile necesare.';
    } else {
        // Salveaza comenzile
        $fullAddress = $address . ', ' . $city;
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, address, status) 
                               VALUES (?, ?, ?, 'in asteptare')");
        $stmt->execute([$_SESSION['user_id'], $total, $fullAddress]);
        $orderId = $pdo->lastInsertId();

        // Salveaza fiecare produs
        foreach($_SESSION['cart'] as $product_id => $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items 
                                   (order_id, product_id, quantity, price)
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $product_id, $item['qty'], $item['price']]);

            // Reduce stock-ul in baza de data
            $stmt = $pdo->prepare("UPDATE products 
                                   SET stock = stock - ? 
                                   WHERE id = ?");
            $stmt->execute([$item['qty'], $product_id]);
        }

        // Curata Cosul
        unset($_SESSION['cart']);

        // Mergi la pagina de succes
        header('Location: order-success.php?id=' . $orderId);
        exit;
    }
}
$pageTitle = "Checkout";
require_once 'includes/header.php';
?>

<div class="row g-5">

    <div class="col-lg-7">
        <h2 class="fw-bold mb-4">🚚 Checkout</h2>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">
                    Detalii de Livrare
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Numele Intreg *</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresa *</label>
                        <input type="text" name="address" class="form-control"
                               placeholder="Numele strazii si numarul acesteia" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Oras *</label>
                            <input type="text" name="city" class="form-control"
                                   placeholder="e.g. București" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Telefon *</label>
                            <input type="text" name="phone" class="form-control"
                                   placeholder="07xx xxx xxx" required>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">
                    Metoda de Plata
                </div>
                <div class="card-body">

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               name="payment" value="cash" id="cash" checked>
                        <label class="form-check-label" for="cash">
                            💵 Bani Cash la Livrare
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio"
                               name="payment" value="card" id="card">
                        <label class="form-check-label" for="card">
                            💳 Card la Livrare
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="payment" value="bank" id="bank">
                        <label class="form-check-label" for="bank">
                            🏦 Transfer bancar
                        </label>
                    </div>

                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-3 fw-bold fs-5">
                ✅ Plaseaza comanda — <?= number_format($total, 2) ?> RON
            </button>

        </form>
    </div>

    <!-- Right: Order Summary -->
    <div class="col-lg-5">
        <h5 class="fw-bold mb-3">Rezumatul Comenzii</h5>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Produs</th>
                            <th>Cantitatea</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($_SESSION['cart'] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= $item['qty'] ?></td>
                            <td><?= number_format($item['price'] * $item['qty'], 2) ?> RON</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="2" class="fw-bold">Total</td>
                            <td class="fw-bold text-warning">
                                <?= number_format($total, 2) ?> RON
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Back to cart -->
        <a href="cart.php" class="btn btn-outline-dark w-100 mt-3">
            ← Modifica Cosul
        </a>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>