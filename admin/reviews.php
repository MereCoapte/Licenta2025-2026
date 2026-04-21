<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Aprobare / Respingere / Stergere
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    $actiune   = $_POST['actiune'] ?? '';

    if ($review_id > 0) {
        if ($actiune === 'approve') {
            $pdo->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$review_id]);
        } elseif ($actiune === 'reject') {
            $pdo->prepare("UPDATE reviews SET status='rejected' WHERE id=?")->execute([$review_id]);
        } elseif ($actiune === 'delete') {
            $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$review_id]);
        }
    }
    header('Location: reviews.php?tab=' . ($_POST['tab'] ?? 'pending'));
    exit;
}

$tab = $_GET['tab'] ?? 'pending';

// Toate review-urile grupate pe status
$reviewsPending  = $pdo->query("
    SELECT r.*, p.name as product_name, u.name as user_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
")->fetchAll();

$reviewsApproved = $pdo->query("
    SELECT r.*, p.name as product_name, u.name as user_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
")->fetchAll();

$reviewsRejected = $pdo->query("
    SELECT r.*, p.name as product_name, u.name as user_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'rejected'
    ORDER BY r.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review-uri — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets\images\FavIcon.png">
    <?php require_once 'admin-style.php'; ?>
    <style>
        .tab-nav .nav-link { color:#888;font-size:13px;font-weight:600;border-bottom:2px solid transparent;border-radius:0;padding:10px 20px; }
        .tab-nav .nav-link.active { color:#e84700;border-bottom-color:#e84700;background:none; }
        .star-display { color:#ffc107;font-size:13px; }
        .avatar { width:36px;height:36px;border-radius:50%;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-star me-2" style="color:#e84700;"></i>
                Moderare Review-uri
            </h4>
            <small class="text-muted">Aproba, respinge sau sterge review-urile clientilor</small>
        </div>
        <!-- Sumar rapid -->
        <div class="d-flex gap-2">
            <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                <?= count($reviewsPending) ?> in asteptare
            </span>
            <span class="badge bg-success fs-6 px-3 py-2">
                <?= count($reviewsApproved) ?> aprobate
            </span>
            <span class="badge bg-danger fs-6 px-3 py-2">
                <?= count($reviewsRejected) ?> respinse
            </span>
        </div>
    </div>

    <div class="card-box">
        <!-- Tab-uri -->
        <ul class="nav tab-nav border-bottom px-3">
            <li class="nav-item">
                <a class="nav-link <?= $tab==='pending' ? 'active' : '' ?>" href="?tab=pending">
                    <i class="fas fa-clock me-1 text-warning"></i>In Asteptare
                    <?php if (count($reviewsPending) > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= count($reviewsPending) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab==='approved' ? 'active' : '' ?>" href="?tab=approved">
                    <i class="fas fa-check me-1 text-success"></i>Aprobate
                    <span class="badge bg-success ms-1"><?= count($reviewsApproved) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab==='rejected' ? 'active' : '' ?>" href="?tab=rejected">
                    <i class="fas fa-times me-1 text-danger"></i>Respinse
                    <span class="badge bg-danger ms-1"><?= count($reviewsRejected) ?></span>
                </a>
            </li>
        </ul>

        <?php
        $activeReviews = match($tab) {
            'approved' => $reviewsApproved,
            'rejected' => $reviewsRejected,
            default    => $reviewsPending,
        };
        ?>

        <?php if (empty($activeReviews)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-star fa-3x mb-3 opacity-25"></i>
                <p>Nu exista review-uri in aceasta categorie.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Autor</th>
                            <th>Produs</th>
                            <th class="text-center">Rating</th>
                            <th>Comentariu</th>
                            <th>Data</th>
                            <th class="text-center">Actiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeReviews as $r):
                            $reviewerName = $r['user_name'] ?? $r['guest_name'] ?? 'Anonim';
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar"><?= strtoupper(substr($reviewerName, 0, 1)) ?></div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($reviewerName) ?></div>
                                        <?php if (!$r['user_id']): ?>
                                            <span class="badge bg-light text-muted" style="font-size:10px;">Guest</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark" style="font-size:10px;">Client</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="../product.php?id=<?= $r['product_id'] ?>" target="_blank"
                                   class="text-dark fw-semibold" style="font-size:13px;">
                                    <?= htmlspecialchars($r['product_name'] ?? 'N/A') ?>
                                    <i class="fas fa-external-link-alt ms-1 text-muted" style="font-size:10px;"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <div class="star-display">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fas fa-star" style="color:<?= $s <= $r['rating'] ? '#ffc107' : '#dee2e6' ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted"><?= $r['rating'] ?>/5</small>
                            </td>
                            <td style="max-width:300px;">
                                <div style="font-size:13px;color:#555;line-height:1.5;
                                            overflow:hidden;display:-webkit-box;
                                            line-clamp:2;-webkit-box-orient:vertical;">
                                    <?= htmlspecialchars($r['comment']) ?>
                                </div>
                            </td>
                            <td><small class="text-muted"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small></td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php if ($tab !== 'approved'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="actiune" value="approve">
                                        <input type="hidden" name="tab" value="<?= $tab ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Aproba">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <?php if ($tab !== 'rejected'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="actiune" value="reject">
                                        <input type="hidden" name="tab" value="<?= $tab ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Respinge">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <form method="POST" onsubmit="return confirm('Stergi definitiv acest review?')">
                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="actiune" value="delete">
                                        <input type="hidden" name="tab" value="<?= $tab ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>