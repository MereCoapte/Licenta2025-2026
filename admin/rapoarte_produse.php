<?php
// =====================================================
// PASUL 1: session_start + db - INTOTDEAUNA PRIMELE
// =====================================================
session_start();
require_once '../includes/db.php';

// =====================================================
// PASUL 2: Protectie admin
// =====================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// =====================================================
// PASUL 3: Export CSV - inainte de orice HTML
// =====================================================
if (isset($_GET['export'])) {

    $tip = $_GET['export']; // 'vandute', 'stoc', 'inactive'

    if ($tip === 'vandute') {
        $stmt = $pdo->query("
            SELECT 
                p.name              AS 'Produs',
                c.name              AS 'Categorie',
                p.price             AS 'Pret (RON)',
                p.stock             AS 'Stoc Ramas',
                COALESCE(SUM(oi.quantity), 0)        AS 'Total Vandute',
                COALESCE(SUM(oi.quantity * oi.price), 0) AS 'Venit Generat (RON)'
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON oi.product_id = p.id
            GROUP BY p.id
            ORDER BY `Total Vandute` DESC
        ");
        $filename = 'raport-produse-vandute-' . date('Y-m-d') . '.csv';

    } elseif ($tip === 'stoc') {
        $stmt = $pdo->query("
            SELECT 
                p.name   AS 'Produs',
                c.name   AS 'Categorie',
                p.price  AS 'Pret (RON)',
                p.stock  AS 'Stoc Ramas'
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock <= 10
            ORDER BY p.stock ASC
        ");
        $filename = 'raport-stoc-scazut-' . date('Y-m-d') . '.csv';

    } else { // inactive
        $stmt = $pdo->query("
            SELECT 
                p.name   AS 'Produs',
                c.name   AS 'Categorie',
                p.price  AS 'Pret (RON)',
                p.stock  AS 'Stoc'
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON oi.product_id = p.id
            WHERE oi.id IS NULL
            GROUP BY p.id
        ");
        $filename = 'raport-produse-fara-vanzari-' . date('Y-m-d') . '.csv';
    }

    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM diacritice Excel

    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
    } else {
        fputcsv($output, ['Nu exista date'], ';');
    }
    fclose($output);
    exit;
}

// =====================================================
// PASUL 4: Queries pentru toate sectiunile
// =====================================================

// --- Carduri sumar ---
$totalProduse    = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategorii  = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$prodStocZero    = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
$prodStocScazut  = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 10")->fetchColumn();

// --- Cele mai vandute produse (top 10) ---
$stmt = $pdo->query("
    SELECT 
        p.id,
        p.name,
        p.price,
        p.stock,
        p.image,
        c.name AS category,
        COALESCE(SUM(oi.quantity), 0)            AS total_vandute,
        COALESCE(SUM(oi.quantity * oi.price), 0) AS venit_generat
    FROM products p
    LEFT JOIN categories c    ON p.category_id  = c.id
    LEFT JOIN order_items oi  ON oi.product_id  = p.id
    GROUP BY p.id
    ORDER BY total_vandute DESC
    LIMIT 10
");
$topProduse = $stmt->fetchAll();

// --- Produse cu stoc scazut (<=10) ---
$stmt = $pdo->query("
    SELECT p.id, p.name, p.price, p.stock, c.name AS category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock <= 10
    ORDER BY p.stock ASC
");
$stocScazut = $stmt->fetchAll();

// --- Produse fara nicio vanzare ---
$stmt = $pdo->query("
    SELECT p.id, p.name, p.price, p.stock, c.name AS category
    FROM products p
    LEFT JOIN categories c    ON p.category_id = c.id
    LEFT JOIN order_items oi  ON oi.product_id  = p.id
    WHERE oi.id IS NULL
    GROUP BY p.id
    ORDER BY p.name ASC
");
$faraVanzari = $stmt->fetchAll();

// --- Grafic: venit pe categorie ---
$stmt = $pdo->query("
    SELECT 
        c.name                                       AS categorie,
        COALESCE(SUM(oi.quantity * oi.price), 0)    AS venit,
        COALESCE(SUM(oi.quantity), 0)               AS cantitate
    FROM categories c
    LEFT JOIN products p     ON p.category_id  = c.id
    LEFT JOIN order_items oi ON oi.product_id  = p.id
    GROUP BY c.id
    ORDER BY venit DESC
");
$graficCategorii = $stmt->fetchAll();

$chartCatLabels = [];
$chartCatVenit  = [];
$chartCatQty    = [];
foreach ($graficCategorii as $row) {
    $chartCatLabels[] = $row['categorie'];
    $chartCatVenit[]  = (float) $row['venit'];
    $chartCatQty[]    = (int)   $row['cantitate'];
}

// Paleta de culori pentru grafic donut
$chartColors = [
    '#e84700','#1a1a2e','#0d6efd','#198754',
    '#ffc107','#0dcaf0','#6f42c1','#fd7e14',
    '#20c997','#d63384'
];

// =====================================================
// PASUL 5: ABIA ACUM HTML
// =====================================================
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport Produse — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php require_once 'admin-style.php'; ?>

    <style>
        .stat-card { border-radius: 12px; transition: transform .2s; border: none; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .section-title {
            font-size: 11px; font-weight: 700;
            letter-spacing: 2px; text-transform: uppercase;
            color: #e84700; margin-bottom: 0;
        }
        .stoc-bar {
            height: 6px; border-radius: 3px;
            background: #f0f0f0; overflow: hidden;
        }
        .stoc-bar-fill { height: 100%; border-radius: 3px; }
        .tab-nav .nav-link {
            color: #888; font-size: 13px; font-weight: 600;
            border-bottom: 2px solid transparent; border-radius: 0;
            padding: 10px 20px;
        }
        .tab-nav .nav-link.active {
            color: #e84700; border-bottom-color: #e84700; background: none;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-box me-2" style="color:#e84700;"></i>
                Raport Produse
            </h4>
            <small class="text-muted">Analiza stocuri, vanzari si performanta produse</small>
        </div>
        <!-- Dropdown export CSV -->
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="?export=vandute">
                        <i class="fas fa-trophy me-2 text-warning"></i>Cele mai vandute
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="?export=stoc">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Stoc scazut
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="?export=inactive">
                        <i class="fas fa-ghost me-2 text-secondary"></i>Fara vanzari
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- ===== CARDURI SUMAR ===== -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-primary" style="background:rgba(13,110,253,0.1);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL PRODUSE</div>
                        <div class="fw-bold fs-5"><?= $totalProduse ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-success" style="background:rgba(25,135,84,0.1);">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">CATEGORII</div>
                        <div class="fw-bold fs-5"><?= $totalCategorii ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-warning" style="background:rgba(255,193,7,0.1);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">STOC SCAZUT (&le;10)</div>
                        <div class="fw-bold fs-5"><?= $prodStocScazut ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-danger" style="background:rgba(220,53,69,0.1);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">STOC EPUIZAT</div>
                        <div class="fw-bold fs-5"><?= $prodStocZero ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== GRAFIC VENIT PE CATEGORIE ===== -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card-box h-100">
                <div class="p-3 border-bottom">
                    <p class="section-title">VENIT PE CATEGORIE</p>
                </div>
                <div class="p-3 d-flex align-items-center justify-content-center" style="min-height:280px;">
                    <?php if (empty($graficCategorii)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-chart-pie fa-3x mb-3 opacity-25"></i>
                            <p>Nu exista date.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="graficCategorii" style="max-height:260px;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top 3 categorii text -->
        <div class="col-lg-7">
            <div class="card-box h-100">
                <div class="p-3 border-bottom">
                    <p class="section-title">PERFORMANTA PE CATEGORIE</p>
                </div>
                <div class="p-3">
                    <?php
                    $maxVenit = !empty($graficCategorii) ? max(array_column($graficCategorii, 'venit')) : 1;
                    foreach ($graficCategorii as $i => $cat):
                        $pct = $maxVenit > 0 ? round(($cat['venit'] / $maxVenit) * 100) : 0;
                        $color = $chartColors[$i % count($chartColors)];
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold" style="font-size:14px;">
                                    <?= htmlspecialchars($cat['categorie']) ?>
                                </span>
                                <span class="text-muted" style="font-size:13px;">
                                    <?= number_format($cat['venit'], 2) ?> RON
                                    <span class="ms-2 text-secondary">(<?= $cat['cantitate'] ?> buc.)</span>
                                </span>
                            </div>
                            <div class="stoc-bar">
                                <div class="stoc-bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($graficCategorii)): ?>
                        <div class="text-center text-muted py-4">Nu exista date de afisat.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TABELE CU TAB-URI ===== -->
    <div class="card-box">
        <!-- Tab navigatie -->
        <ul class="nav tab-nav border-bottom px-3">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-vandute">
                    <i class="fas fa-trophy me-1 text-warning"></i>
                    Cele mai vandute
                    <span class="badge bg-warning text-dark ms-1"><?= count($topProduse) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stoc">
                    <i class="fas fa-exclamation-triangle me-1 text-danger"></i>
                    Stoc scazut
                    <?php if ($prodStocScazut > 0 || $prodStocZero > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $prodStocScazut + $prodStocZero ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inactive">
                    <i class="fas fa-ghost me-1 text-secondary"></i>
                    Fara vanzari
                    <?php if (!empty($faraVanzari)): ?>
                        <span class="badge bg-secondary ms-1"><?= count($faraVanzari) ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content">

            <!-- TAB 1: Cele mai vandute -->
            <div class="tab-pane fade show active" id="tab-vandute">
                <?php if (empty($topProduse)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-trophy fa-3x mb-3 opacity-25"></i>
                        <p>Nu exista date de vanzari inca.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Loc</th>
                                    <th>Produs</th>
                                    <th>Categorie</th>
                                    <th class="text-end">Pret</th>
                                    <th class="text-center">Vandute</th>
                                    <th class="text-center">Stoc</th>
                                    <th class="text-end">Venit Generat</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProduse as $i => $p): ?>
                                    <tr>
                                        <td>
                                            <?php if ($i === 0): ?>
                                                <span class="fs-5">🥇</span>
                                            <?php elseif ($i === 1): ?>
                                                <span class="fs-5">🥈</span>
                                            <?php elseif ($i === 2): ?>
                                                <span class="fs-5">🥉</span>
                                            <?php else: ?>
                                                <span class="text-muted fw-bold">#<?= $i + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                        <td>
                                            <span class="badge bg-dark"><?= htmlspecialchars($p['category'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format($p['price'], 2) ?> RON</td>
                                        <td class="text-center fw-bold"><?= $p['total_vandute'] ?> buc.</td>
                                        <td class="text-center">
                                            <?php
                                            $stock = (int)$p['stock'];
                                            if ($stock == 0) {
                                                echo '<span class="badge bg-danger">Epuizat</span>';
                                            } elseif ($stock <= 10) {
                                                echo '<span class="badge bg-warning text-dark">' . $stock . ' buc.</span>';
                                            } else {
                                                echo '<span class="badge bg-success">' . $stock . ' buc.</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= number_format($p['venit_generat'], 2) ?> RON
                                        </td>
                                        <td>
                                            <a href="edit-product.php?id=<?= $p['id'] ?>"
                                               class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: Stoc scazut -->
            <div class="tab-pane fade" id="tab-stoc">
                <?php if (empty($stocScazut)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                        <p>Toate produsele au stoc suficient. Bravo!</p>
                    </div>
                <?php else: ?>
                    <div class="p-3 pb-0">
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>
                                <strong><?= count($stocScazut) ?> produse</strong> au stocul scazut sau epuizat.
                                Recomandati reaprovizionarea urgenta.
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Produs</th>
                                    <th>Categorie</th>
                                    <th class="text-end">Pret</th>
                                    <th class="text-center">Stoc Ramas</th>
                                    <th class="text-center">Urgenta</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stocScazut as $p): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                        <td>
                                            <span class="badge bg-dark"><?= htmlspecialchars($p['category'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format($p['price'], 2) ?> RON</td>
                                        <td class="text-center">
                                            <?php if ($p['stock'] == 0): ?>
                                                <span class="badge bg-danger fs-6">0 — EPUIZAT</span>
                                            <?php elseif ($p['stock'] <= 5): ?>
                                                <span class="badge bg-danger"><?= $p['stock'] ?> buc.</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><?= $p['stock'] ?> buc.</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($p['stock'] == 0): ?>
                                                <span class="text-danger fw-bold" style="font-size:12px;">🔴 CRITIC</span>
                                            <?php elseif ($p['stock'] <= 5): ?>
                                                <span class="text-danger" style="font-size:12px;">🟠 RIDICAT</span>
                                            <?php else: ?>
                                                <span class="text-warning" style="font-size:12px;">🟡 MODERAT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="edit-product.php?id=<?= $p['id'] ?>"
                                               class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 3: Fara vanzari -->
            <div class="tab-pane fade" id="tab-inactive">
                <?php if (empty($faraVanzari)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-star fa-3x mb-3 text-warning opacity-50"></i>
                        <p>Toate produsele au cel putin o vanzare!</p>
                    </div>
                <?php else: ?>
                    <div class="p-3 pb-0">
                        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-ghost"></i>
                            <span>
                                <strong><?= count($faraVanzari) ?> produse</strong> nu au nicio vanzare inca.
                                Considerati reduceri sau promovare.
                            </span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Produs</th>
                                    <th>Categorie</th>
                                    <th class="text-end">Pret</th>
                                    <th class="text-center">Stoc</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faraVanzari as $p): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                        <td>
                                            <span class="badge bg-dark"><?= htmlspecialchars($p['category'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="text-end"><?= number_format($p['price'], 2) ?> RON</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?= $p['stock'] ?> buc.</span>
                                        </td>
                                        <td>
                                            <a href="edit-product.php?id=<?= $p['id'] ?>"
                                               class="btn btn-sm btn-outline-dark">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /card-box taburi -->

</div><!-- /main-content -->

<!-- Chart.js Donut categorii -->
<?php if (!empty($graficCategorii)): ?>
<script>
const ctx2 = document.getElementById('graficCategorii').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartCatLabels) ?>,
        datasets: [{
            data: <?= json_encode($chartCatVenit) ?>,
            backgroundColor: <?= json_encode(array_slice($chartColors, 0, count($chartCatLabels))) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.parsed.toFixed(2) + ' RON'
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>