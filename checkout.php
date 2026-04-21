<?php
session_start();
require_once 'includes/db.php';

// Guest sau logat — ambii pot face checkout
if (empty($_SESSION['cart'])) {
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

$isGuest = !isset($_SESSION['user_id']);

$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['qty'];
}

// =====================================================
// AJAX: Verificare cupon
// =====================================================
if (isset($_GET['check_coupon'])) {
    $code = strtoupper(trim($_GET['check_coupon']));
    $stmt = $pdo->prepare("
        SELECT * FROM coupons
        WHERE code = ? AND active = 1
          AND (expires_at IS NULL OR expires_at >= CURDATE())
          AND (uses_limit IS NULL OR uses_count < uses_limit)
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    header('Content-Type: application/json');
    if ($coupon) {
        if ($subtotal < $coupon['min_order']) {
            echo json_encode(['valid' => false, 'message' => 'Comanda minima pentru acest cupon este ' . number_format($coupon['min_order'], 2) . ' RON.']);
        } else {
            $disc = $coupon['type'] === 'percent'
                ? round($subtotal * $coupon['value'] / 100, 2)
                : min((float)$coupon['value'], $subtotal);
            echo json_encode([
                'valid'    => true,
                'message'  => $coupon['type'] === 'percent' ? 'Cupon aplicat! Reducere ' . $coupon['value'] . '%' : 'Cupon aplicat! Reducere ' . number_format($coupon['value'], 2) . ' RON',
                'discount' => $disc,
                'final'    => round($subtotal - $disc, 2),
                'code'     => $coupon['code']
            ]);
        }
    } else {
        echo json_encode(['valid' => false, 'message' => 'Codul este invalid sau expirat.']);
    }
    exit;
}

// =====================================================
// POST: Salvare comanda
// =====================================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['full_name']   ?? '');
    $address     = trim($_POST['address']     ?? '');
    $city        = trim($_POST['city']        ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $payment     = $_POST['payment']          ?? 'cash';
    $couponCode  = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $guestEmail  = trim($_POST['guest_email'] ?? '');
    $finalTotal  = $subtotal;

    if (empty($name))    $errors[] = 'Numele este obligatoriu.';
    if (empty($address)) $errors[] = 'Adresa este obligatorie.';
    if (empty($city))    $errors[] = 'Orasul este obligatoriu.';
    if (empty($phone))   $errors[] = 'Telefonul este obligatoriu.';

    // Validare extra pentru guest
    if ($isGuest) {
        if (empty($guestEmail))
            $errors[] = 'Emailul este obligatoriu pentru comenzile fara cont.';
        elseif (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Adresa de email nu este valida.';
    }

    // Valideaza cupon
    $couponId = null;
    $discount = 0;
    if (!empty($couponCode)) {
        $stmt = $pdo->prepare("
            SELECT * FROM coupons
            WHERE code = ? AND active = 1
              AND (expires_at IS NULL OR expires_at >= CURDATE())
              AND (uses_limit IS NULL OR uses_count < uses_limit)
        ");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch();
        if (!$coupon) {
            $errors[] = 'Codul de cupon este invalid sau expirat.';
        } elseif ($subtotal < $coupon['min_order']) {
            $errors[] = 'Comanda minima pentru acest cupon este ' . number_format($coupon['min_order'], 2) . ' RON.';
        } else {
            $discount   = $coupon['type'] === 'percent' ? round($subtotal * $coupon['value'] / 100, 2) : min((float)$coupon['value'], $subtotal);
            $couponId   = $coupon['id'];
            $finalTotal = round($subtotal - $discount, 2);
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $fullAddress = $address . ', ' . $city;

            if ($isGuest) {
                $pdo->prepare("
                    INSERT INTO orders (user_id, guest_name, guest_email, guest_phone, total, address, status, created_at)
                    VALUES (NULL, ?, ?, ?, ?, ?, 'pending', NOW())
                ")->execute([$name, $guestEmail, $phone, $finalTotal, $fullAddress]);
            } else {
                $pdo->prepare("
                    INSERT INTO orders (user_id, total, address, status, created_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ")->execute([$_SESSION['user_id'], $finalTotal, $fullAddress]);
            }

            $orderId = $pdo->lastInsertId();

            foreach ($_SESSION['cart'] as $product_id => $item) {
                $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)")
                    ->execute([$orderId, $product_id, $item['qty'], $item['price']]);
                $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")
                    ->execute([$item['qty'], $product_id]);
            }

            $pdo->prepare("INSERT INTO order_status_history (order_id, status, note) VALUES (?, 'pending', 'Comanda plasata cu succes.')")
                ->execute([$orderId]);

            if ($couponId) {
                $pdo->prepare("UPDATE coupons SET uses_count = uses_count + 1 WHERE id = ?")
                    ->execute([$couponId]);
            }

            $pdo->commit();
            unset($_SESSION['cart']);

            header('Location: ' . BASE_URL . 'order-success.php?id=' . $orderId);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Eroare la salvarea comenzii. Te rugam incearca din nou.';
        }
    }
}

$pageTitle = "Checkout";
require_once 'includes/header.php';
?>

<div class="row g-5">

    <div class="col-lg-7">
        <h2 class="fw-bold mb-4">🚚 Checkout</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <div><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Banner guest -->
        <?php if ($isGuest): ?>
        <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
            <i class="fas fa-user-circle fa-2x text-info flex-shrink-0"></i>
            <div>
                <div class="fw-bold">Comanzi ca vizitator</div>
                <small>
                    Ai deja cont?
                    <a href="login.php" class="fw-bold">Logheaza-te</a>
                    pentru a urmari comenzile mai usor.
                </small>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
            <input type="hidden" name="final_total" id="finalTotalInput" value="<?= $subtotal ?>">
            <input type="hidden" name="coupon_code" id="couponCodeInput" value="">

            <!-- Livrare -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">Detalii de Livrare</div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Numele Intreg *</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? $_SESSION['user_name'] ?? '') ?>" required>
                    </div>

                    <?php if ($isGuest): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Email * <small class="text-muted fw-normal">(pentru confirmarea comenzii)</small>
                        </label>
                        <input type="email" name="guest_email" class="form-control"
                               value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>"
                               placeholder="exemplu@email.com" required>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresa *</label>
                        <input type="text" name="address" class="form-control"
                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                               placeholder="Strada si numarul" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Oras *</label>
                            <input type="text" name="city" class="form-control"
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                   placeholder="e.g. București" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Telefon *</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   placeholder="07xx xxx xxx" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plata -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">Metoda de Plata</div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment" value="cash" id="cash" checked>
                        <label class="form-check-label" for="cash">💵 Bani Cash la Livrare</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment" value="card" id="cardpay">
                        <label class="form-check-label" for="cardpay">💳 Card la Livrare</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment" value="bank" id="bank">
                        <label class="form-check-label" for="bank">🏦 Transfer Bancar</label>
                    </div>
                </div>
            </div>

            <!-- Cupon -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold">🏷️ Cod de Reducere</div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <input type="text" id="couponInput" class="form-control"
                               placeholder="Ex: WELCOME10" style="text-transform:uppercase;"
                               value="<?= htmlspecialchars($_POST['coupon_code'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-dark px-4" onclick="applyCoupon()">Aplica</button>
                    </div>
                    <div id="couponMessage" class="mt-2" style="display:none;"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-3 fw-bold fs-5">
                ✅ Plaseaza comanda — <span id="totalBtn"><?= number_format($subtotal, 2) ?></span> RON
            </button>

            <?php if ($isGuest): ?>
            <div class="text-center mt-2">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1 text-success"></i>
                    Comanda ta este securizata. Nu este necesar un cont.
                </small>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- DREAPTA: Sumar -->
    <div class="col-lg-5">
        <h5 class="fw-bold mb-3">Rezumatul Comenzii</h5>
        <div class="card shadow-sm mb-3">
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-dark">
                        <tr><th>Produs</th><th class="text-center">Cant.</th><th class="text-end">Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td class="text-center"><?= $item['qty'] ?></td>
                            <td class="text-end"><?= number_format($item['price'] * $item['qty'], 2) ?> RON</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-muted small">Subtotal</td>
                            <td class="text-end"><?= number_format($subtotal, 2) ?> RON</td>
                        </tr>
                        <tr id="discountRow" style="display:none;">
                            <td colspan="2" class="small text-success">🏷️ Reducere (<span id="couponLabel"></span>)</td>
                            <td class="text-end fw-bold text-success" id="discountAmount"></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-muted small">Transport</td>
                            <td class="text-end text-success fw-bold small">GRATUIT</td>
                        </tr>
                        <tr class="table-dark">
                            <td colspan="2" class="fw-bold">TOTAL</td>
                            <td class="fw-bold text-warning text-end" id="totalDisplay">
                                <?= number_format($subtotal, 2) ?> RON
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <a href="cart.php" class="btn btn-outline-dark w-100">← Modifica Cosul</a>
    </div>

</div>

<script>
const subtotal = <?= $subtotal ?>;
function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim().toUpperCase();
    if (!code) { showMsg('Te rugam introdu un cod.', 'warning'); return; }
    fetch('checkout.php?check_coupon=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.valid) {
                showMsg('<i class="fas fa-check-circle me-1"></i>' + data.message, 'success');
                document.getElementById('discountRow').style.display  = '';
                document.getElementById('couponLabel').textContent    = data.code;
                document.getElementById('discountAmount').textContent = '-' + data.discount.toFixed(2) + ' RON';
                document.getElementById('totalDisplay').textContent   = data.final.toFixed(2) + ' RON';
                document.getElementById('totalBtn').textContent       = data.final.toFixed(2);
                document.getElementById('couponCodeInput').value      = data.code;
                document.getElementById('finalTotalInput').value      = data.final;
            } else {
                showMsg('<i class="fas fa-times-circle me-1"></i>' + data.message, 'danger');
                resetTotal();
            }
        });
}
function showMsg(msg, type) {
    const b = document.getElementById('couponMessage');
    b.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-0 small">' + msg + '</div>';
    b.style.display = '';
}
function resetTotal() {
    document.getElementById('discountRow').style.display = 'none';
    document.getElementById('totalDisplay').textContent  = subtotal.toFixed(2) + ' RON';
    document.getElementById('totalBtn').textContent      = subtotal.toFixed(2);
    document.getElementById('couponCodeInput').value     = '';
    document.getElementById('finalTotalInput').value     = subtotal;
}
document.getElementById('couponInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
});
</script>

<?php require_once 'includes/footer.php'; ?>