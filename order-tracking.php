<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) {
    header('Location: ' . BASE_URL . 'profile.php');
    exit;
}

// Preluam comanda (clientul vede doar a lui)
$stmt = $pdo->prepare("
    SELECT o.*, u.name as client_name, u.email as client_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . 'profile.php');
    exit;
}

// Istoricul statusurilor
$stmt = $pdo->prepare("
    SELECT * FROM order_status_history
    WHERE order_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$order_id]);
$history = $stmt->fetchAll();

// Produsele din comanda
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Pasii tracking in ordine
$steps = [
    'pending'    => ['label' => 'Comanda Plasata',   'icon' => 'fas fa-shopping-bag',  'color' => '#ffc107'],
    'processing' => ['label' => 'In Procesare',      'icon' => 'fas fa-cog',           'color' => '#0dcaf0'],
    'shipped'    => ['label' => 'Expediat',           'icon' => 'fas fa-truck',         'color' => '#0d6efd'],
    'delivered'  => ['label' => 'Livrat',             'icon' => 'fas fa-check-circle',  'color' => '#198754'],
];

$stepOrder  = array_keys($steps);
$currentIdx = array_search($order['status'], $stepOrder);
if ($currentIdx === false) $currentIdx = 0;

$pageTitle = "Tracking Comanda #" . $order_id;
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php">Acasa</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>profile.php">Profilul Meu</a></li>
        <li class="breadcrumb-item active">Tracking #<?= $order_id ?></li>
    </ol>
</nav>

<div class="row g-4">

    <!-- STANGA: Tracking timeline -->
    <div class="col-lg-8">

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Tracking Comanda #<?= $order_id ?>
                </span>
                <a href="factura.php?id=<?= $order_id ?>" target="_blank"
                   class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-file-invoice me-1"></i>Factura
                </a>
            </div>
            <div class="card-body p-4">

                <!-- ===== PROGRESS BAR VIZUAL ===== -->
                <div class="d-flex justify-content-between align-items-center mb-2 position-relative">
                    <!-- Linia de progres -->
                    <div style="position:absolute;top:20px;left:10%;right:10%;height:4px;
                                background:#e9ecef;z-index:0;border-radius:2px;">
                        <div style="height:100%;border-radius:2px;background:#198754;
                                    width:<?= ($currentIdx / (count($steps) - 1)) * 100 ?>%;
                                    transition:width 0.5s;"></div>
                    </div>

                    <?php foreach ($steps as $key => $step):
                        $idx      = array_search($key, $stepOrder);
                        $done     = $idx <= $currentIdx;
                        $current  = $idx === $currentIdx;
                        $color    = $done ? $step['color'] : '#dee2e6';
                    ?>
                    <div class="text-center position-relative" style="z-index:1; flex:1;">
                        <div class="mx-auto d-flex align-items-center justify-content-center rounded-circle mb-2"
                             style="width:42px;height:42px;
                                    background:<?= $done ? $step['color'] : '#e9ecef' ?>;
                                    <?= $current ? 'box-shadow:0 0 0 4px ' . $step['color'] . '44;' : '' ?>
                                    transition:all 0.3s;">
                            <i class="<?= $step['icon'] ?>"
                               style="color:<?= $done ? '#fff' : '#aaa' ?>; font-size:16px;"></i>
                        </div>
                        <div style="font-size:11px; font-weight:<?= $current ? '700' : '500' ?>;
                                    color:<?= $done ? '#333' : '#aaa' ?>;">
                            <?= $step['label'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Status curent -->
                <div class="text-center mt-4 p-3 rounded"
                     style="background:<?= $steps[$order['status']]['color'] ?? '#ffc107' ?>22;">
                    <div class="fw-bold" style="color:<?= $steps[$order['status']]['color'] ?? '#ffc107' ?>;">
                        <i class="<?= $steps[$order['status']]['icon'] ?? 'fas fa-info' ?> me-2"></i>
                        <?= $steps[$order['status']]['label'] ?? ucfirst($order['status']) ?>
                    </div>
                    <small class="text-muted">
                        Ultima actualizare: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                    </small>
                </div>

            </div>
        </div>

        <!-- ===== ISTORICUL STATUSURILOR ===== -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold border-bottom">
                <i class="fas fa-history me-2 text-muted"></i>
                Istoricul Comenzii
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-muted small">Nu exista istoric disponibil.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach (array_reverse($history) as $i => $h):
                            $stepInfo = $steps[$h['status']] ?? ['label' => ucfirst($h['status']), 'icon' => 'fas fa-circle', 'color' => '#6c757d'];
                        ?>
                        <div class="d-flex gap-3 mb-3 <?= $i > 0 ? 'opacity-75' : '' ?>">
                            <div class="d-flex flex-column align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                     style="width:36px;height:36px;background:<?= $stepInfo['color'] ?>;flex-shrink:0;">
                                    <i class="<?= $stepInfo['icon'] ?>" style="color:#fff;font-size:14px;"></i>
                                </div>
                                <?php if ($i < count($history) - 1): ?>
                                    <div style="width:2px;flex-grow:1;background:#e9ecef;margin:4px 0;min-height:20px;"></div>
                                <?php endif; ?>
                            </div>
                            <div class="pt-1">
                                <div class="fw-bold" style="font-size:14px;">
                                    <?= $stepInfo['label'] ?>
                                </div>
                                <?php if ($h['note']): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($h['note']) ?></div>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:12px;">
                                    <?= date('d.m.Y H:i', strtotime($h['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- DREAPTA: Detalii comanda -->
    <div class="col-lg-4">

        <!-- Info comanda -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-bold border-bottom">
                <i class="fas fa-info-circle me-2 text-muted"></i>Detalii Comanda
            </div>
            <div class="card-body" style="font-size:14px;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Numar comanda:</span>
                    <span class="fw-bold">#<?= $order_id ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Data:</span>
                    <span><?= date('d.m.Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total:</span>
                    <span class="fw-bold text-danger"><?= number_format($order['total'], 2) ?> RON</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Adresa:</span>
                    <span class="text-end" style="max-width:180px;">
                        <?= htmlspecialchars($order['address']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Produsele -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold border-bottom">
                <i class="fas fa-box me-2 text-muted"></i>Produse Comandate
            </div>
            <div class="card-body p-0">
                <?php foreach ($items as $item): ?>
                <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                    <img src="assets/images/products/<?= htmlspecialchars($item['image'] ?? '') ?>"
                         style="width:50px;height:50px;object-fit:cover;border-radius:8px;"
                         onerror="this.src='assets/images/placeholder.jpg'">
                    <div class="flex-grow-1">
                        <div class="fw-semibold small"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="text-muted small">
                            <?= $item['quantity'] ?> x <?= number_format($item['price'], 2) ?> RON
                        </div>
                    </div>
                    <div class="fw-bold small"><?= number_format($item['quantity'] * $item['price'], 2) ?> RON</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>