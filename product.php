<?php
// =====================================================
// PASUL 1: session + db INTOTDEAUNA PRIMELE
// =====================================================
session_start();
require_once 'includes/db.php';

// Ia produsul ID din URL
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . 'products.php');
    exit;
}

$id = (int)$_GET['id'];

// =====================================================
// PASUL 2: Procesare POST review - inainte de HTML
// =====================================================
$reviewSuccess = false;
$reviewError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {

    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    // Validare
    if ($rating < 1 || $rating > 5) {
        $reviewError = 'Te rugam selecteaza un rating intre 1 si 5 stele.';
    } elseif (empty($comment)) {
        $reviewError = 'Te rugam scrie un comentariu.';
    } else {
        if (isset($_SESSION['user_id'])) {
            // User logat
            // Verificam daca a mai lasat review la acest produs
            $check = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $check->execute([$id, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $reviewError = 'Ai lasat deja un review pentru acest produs.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (product_id, user_id, rating, comment, status)
                    VALUES (?, ?, ?, ?, 'approved')
                ");
                $stmt->execute([$id, $_SESSION['user_id'], $rating, $comment]);
                $reviewSuccess = true;
            }
        } else {
            // Guest
            $guestName = trim($_POST['guest_name'] ?? '');
            if (empty($guestName)) {
                $reviewError = 'Te rugam introdu numele tau.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (product_id, guest_name, rating, comment, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$id, $guestName, $rating, $comment]);
                $reviewSuccess = true;
            }
        }
    }
}

// =====================================================
// PASUL 3: Queries date produs
// =====================================================
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . 'products.php');
    exit;
}

// Review-uri aprobate
$stmt = $pdo->prepare("
    SELECT r.*, u.name as user_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// Rating mediu
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(AVG(rating), 0) as rating_mediu,
        COUNT(*) as nr_review
    FROM reviews
    WHERE product_id = ? AND status = 'approved'
");
$stmt->execute([$id]);
$ratingInfo = $stmt->fetch();
$ratingMediu = round($ratingInfo['rating_mediu'], 1);
$nrReview    = (int)$ratingInfo['nr_review'];

// Distributie stele
$distributie = [];
for ($s = 5; $s >= 1; $s--) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = ? AND status = 'approved'");
    $stmt->execute([$id, $s]);
    $distributie[$s] = (int)$stmt->fetchColumn();
}

// Verifica daca userul logat a lasat deja review
$userALasatReview = false;
if (isset($_SESSION['user_id'])) {
    $check = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $check->execute([$id, $_SESSION['user_id']]);
    $userALasatReview = (bool)$check->fetch();
}

// =====================================================
// PASUL 4: ABIA ACUM HTML
// =====================================================
$pageTitle = htmlspecialchars($product['name']);
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php">Acasa</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>products.php">Produsele</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
    </ol>
</nav>

<!-- ===== DETALII PRODUS ===== -->
<div class="row g-5 mb-5">

    <div class="col-md-5">
        <img src="<?= BASE_URL ?>assets/images/products/<?= htmlspecialchars($product['image']) ?>"
             class="img-fluid rounded shadow"
             alt="<?= htmlspecialchars($product['name']) ?>"
             onerror="this.src='<?= BASE_URL ?>assets/images/placeholder.jpg'"
             style="width:100%; object-fit:cover; max-height:400px;">
    </div>

    <div class="col-md-7">

        <span class="badge bg-secondary mb-2">
            <?= htmlspecialchars($product['category_name'] ?? 'General') ?>
        </span>

        <h2 class="fw-bold"><?= htmlspecialchars($product['name']) ?></h2>

        <!-- Rating mediu langa titlu -->
        <?php if ($nrReview > 0): ?>
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="text-warning">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <i class="fas fa-star<?= $s <= round($ratingMediu) ? '' : '-o' ?>"></i>
                <?php endfor; ?>
            </div>
            <span class="fw-bold"><?= $ratingMediu ?></span>
            <span class="text-muted small">(<?= $nrReview ?> <?= $nrReview === 1 ? 'review' : 'review-uri' ?>)</span>
        </div>
        <?php endif; ?>

        <h3 class="text-danger fw-bold my-3">
            <?= number_format($product['price'], 2) ?> RON
        </h3>

        <p class="text-muted mb-4">
            <?= nl2br(htmlspecialchars($product['description'])) ?>
        </p>

        <p>
            Disponibilitate:
            <?php if ($product['stock'] > 0): ?>
                <span class="badge bg-success">In Stock (<?= $product['stock'] ?> ramase)</span>
            <?php else: ?>
                <span class="badge bg-danger">Stoc Epuizat</span>
            <?php endif; ?>
        </p>

        <?php if ($product['stock'] > 0): ?>
        <form method="POST" action="<?= BASE_URL ?>cart.php" class="d-flex gap-3 align-items-center mt-4">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="action" value="add">
            <div class="qty-wrapper d-flex align-items-center border rounded overflow-hidden">
                <button type="button" class="btn text-dark qty-btn px-3" data-action="minus">−</button>
                <input type="number" name="quantity" class="qty-input form-control border-0 text-center"
                       readonly value="1" min="1" max="<?= $product['stock'] ?>"
                       style="width:60px;">
                <button type="button" class="btn text-dark qty-btn px-3" data-action="plus">+</button>
            </div>
            <button type="submit" class="btn btn-dark px-4 py-2">
                🛒 Adauga in Cos
            </button>
        </form>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>products.php" class="btn btn-outline-secondary mt-4">
            ← Intoarce-te
        </a>

    </div>
</div>

<!-- ===== SECTIUNEA REVIEW-URI ===== -->
<div class="row g-4 mt-2">

    <!-- Coloana stanga: Sumar rating + Formular -->
    <div class="col-lg-4">

        <!-- Sumar rating -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center p-4">
                <div style="font-size:56px; font-weight:800; line-height:1; color:#1a1a2e;">
                    <?= $nrReview > 0 ? $ratingMediu : '—' ?>
                </div>
                <div class="text-warning fs-5 my-2">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fas fa-star" style="color: <?= $s <= round($ratingMediu) ? '#ffc107' : '#dee2e6' ?>;"></i>
                    <?php endfor; ?>
                </div>
                <div class="text-muted small mb-3"><?= $nrReview ?> review-uri</div>

                <!-- Distributie stele -->
                <?php for ($s = 5; $s >= 1; $s--):
                    $cnt = $distributie[$s];
                    $pct = $nrReview > 0 ? round(($cnt / $nrReview) * 100) : 0;
                ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <small class="text-muted" style="width:12px;"><?= $s ?></small>
                    <i class="fas fa-star text-warning" style="font-size:11px;"></i>
                    <div class="flex-grow-1 bg-light rounded" style="height:8px;">
                        <div class="rounded" style="height:8px;width:<?= $pct ?>%;background:#ffc107;"></div>
                    </div>
                    <small class="text-muted" style="width:24px;"><?= $cnt ?></small>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Formular review -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">
                ✍️ Lasa un Review
            </div>
            <div class="card-body">

                <?php if ($reviewSuccess): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= isset($_SESSION['user_id']) ? 'Review adaugat! Multumim.' : 'Review trimis! Va fi aprobat in curand.' ?>
                    </div>
                <?php elseif ($reviewError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($reviewError) ?></div>
                <?php endif; ?>

                <?php if ($userALasatReview): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Ai lasat deja un review pentru acest produs.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="review">

                    <!-- Daca nu e logat, cere numele -->
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Numele tau *</label>
                        <input type="text" name="guest_name" class="form-control"
                               placeholder="Ex: Ion Popescu" required>
                    </div>
                    <?php endif; ?>

                    <!-- Selectie stele -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating *</label>
                        <div class="star-selector d-flex gap-1 fs-4" id="starSelector">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="far fa-star star-btn" data-val="<?= $s ?>"
                                   style="cursor:pointer; color:#dee2e6; transition:color .15s;"
                                   onmouseover="hoverStars(<?= $s ?>)"
                                   onmouseout="resetStars()"
                                   onclick="selectStar(<?= $s ?>)"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="0">
                        <small class="text-muted" id="ratingLabel">Click pe o stea pentru a selecta</small>
                    </div>

                    <!-- Comentariu -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comentariu *</label>
                        <textarea name="comment" class="form-control" rows="4"
                                  placeholder="Spune-ne parerea ta despre acest produs..."
                                  required maxlength="1000"></textarea>
                    </div>

                    <button type="submit" class="btn btn-dark w-100">
                        <i class="fas fa-paper-plane me-2"></i>Trimite Review
                    </button>

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                <a href="<?= BASE_URL ?>login.php">Logheaza-te</a>
                                pentru a lasa review verificat
                            </small>
                        </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- Coloana dreapta: Lista review-uri -->
    <div class="col-lg-8">
        <h5 class="fw-bold mb-3">
            Review-uri Clienti
            <?php if ($nrReview > 0): ?>
                <span class="badge bg-dark ms-1"><?= $nrReview ?></span>
            <?php endif; ?>
        </h5>

        <?php if (empty($reviews)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div style="font-size:48px;">💬</div>
                    <h6 class="text-muted mt-2">Niciun review inca.</h6>
                    <p class="text-muted small">Fii primul care lasa o parere!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Avatar cu initiala -->
                            <?php
                            $reviewerName = $review['user_name'] ?? $review['guest_name'] ?? 'Anonim';
                            ?>
                            <div style="width:36px;height:36px;border-radius:50%;background:#1a1a2e;color:#fff;
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:14px;font-weight:700;flex-shrink:0;">
                                <?= strtoupper(substr($reviewerName, 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($reviewerName) ?>
                                    <?php if (!$review['user_id']): ?>
                                        <span class="badge bg-light text-muted" style="font-size:10px;">Guest</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?= date('d M Y', strtotime($review['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <!-- Stele review -->
                        <div class="text-warning">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fas fa-star" style="color: <?= $s <= $review['rating'] ? '#ffc107' : '#dee2e6' ?>;"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="mb-0 text-muted" style="font-size:14px; line-height:1.6;">
                        <?= nl2br(htmlspecialchars($review['comment'])) ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ===== JAVASCRIPT STELE ===== -->
<script>
let selectedRating = 0;
const stars = document.querySelectorAll('.star-btn');
const ratingInput = document.getElementById('ratingInput');
const ratingLabel = document.getElementById('ratingLabel');

const labels = ['', 'Foarte slab', 'Slab', 'Ok', 'Bun', 'Excelent'];

function hoverStars(val) {
    stars.forEach((s, i) => {
        s.className = i < val ? 'fas fa-star star-btn' : 'far fa-star star-btn';
        s.style.color = i < val ? '#ffc107' : '#dee2e6';
    });
}

function resetStars() {
    stars.forEach((s, i) => {
        s.className = i < selectedRating ? 'fas fa-star star-btn' : 'far fa-star star-btn';
        s.style.color = i < selectedRating ? '#ffc107' : '#dee2e6';
    });
}

function selectStar(val) {
    selectedRating = val;
    ratingInput.value = val;
    ratingLabel.textContent = labels[val] + ' (' + val + '/5)';
    ratingLabel.style.color = '#ffc107';
    resetStars();
}
</script>

<?php require_once 'includes/footer.php'; ?>