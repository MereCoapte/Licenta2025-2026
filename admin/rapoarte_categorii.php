<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Export CSV
if (isset($_GET['export'])) {
    $stmt = $pdo->query("
        SELECT 
            c.name                                          AS 'Categorie',
            COUNT(DISTINCT p.id)                            AS 'Nr Produse',
            COALESCE(SUM(oi.quantity), 0)                   AS 'Total Vandute (buc)',
            COALESCE(SUM(oi.quantity * oi.price), 0)        AS 'Venit Generat (RON)',
            COALESCE(AVG(p.price), 0)                       AS 'Pret Mediu (RON)',
            COALESCE(SUM(p.stock), 0)                       AS 'Stoc Total Ramas'
        FROM categories c
        LEFT JOIN products p     ON p.category_id  = c.id
        LEFT JOIN order_items oi ON oi.product_id  = p.id
        GROUP BY c.id
        ORDER BY `Venit Generat (RON)` DESC
    ");
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="raport-categorii-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]), ';');
        foreach ($rows as $r) fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

// =====================================================
// QUERIES
// =====================================================

// Date complete per categorie
$categorii = $pdo->query("
    SELECT 
        c.id,
        c.name,
        COUNT(DISTINCT p.id)                            AS nr_produse,
        COALESCE(SUM(oi.quantity), 0)                   AS total_vandute,
        COALESCE(SUM(oi.quantity * oi.price), 0)        AS venit_generat,
        COALESCE(AVG(p.price), 0)                       AS pret_mediu,
        COALESCE(SUM(p.stock), 0)                       AS stoc_total,
        COUNT(DISTINCT o.id)                            AS nr_comenzi
    FROM categories c
    LEFT JOIN products p     ON p.category_id  = c.id
    LEFT JOIN order_items oi ON oi.product_id  = p.id
    LEFT JOIN orders o       ON o.id           = oi.order_id
    GROUP BY c.id
    ORDER BY venit_generat DESC
")->fetchAll();

// Carduri sumar
$totalCategorii = count($categorii);
$venitTotal     = array_sum(array_column($categorii, 'venit_generat'));
$produsTotal    = array_sum(array_column($categorii, 'nr_produse'));
$vandutTotal    = array_sum(array_column($categorii, 'total_vandute'));

// Grafic bare - venit per categorie
$chartLabels = array_column($categorii, 'name');
$chartVenit  = array_map('floatval', array_column($categorii, 'venit_generat'));
$chartQty    = array_map('intval',   array_column($categorii, 'total_vandute'));
$chartProduse = array_map('intval',  array_column($categorii, 'nr_produse'));

// Culori pentru grafice
$chartColors = [
    'rgba(232,71,0,0.8)',   'rgba(26,26,46,0.8)',  'rgba(13,110,253,0.8)',
    'rgba(25,135,84,0.8)',  'rgba(255,193,7,0.8)', 'rgba(13,202,240,0.8)',
    'rgba(111,66,193,0.8)', 'rgba(253,126,20,0.8)','rgba(32,201,151,0.8)',
    'rgba(214,51,132,0.8)'
];

// Top produs per categorie
$topProduse = $pdo->query("
    SELECT 
        c.id as cat_id,
        p.name as prod_name,
        COALESCE(SUM(oi.quantity), 0) as vandute
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY c.id, p.id
    ORDER BY c.id, vandute DESC
")->fetchAll();

// Grupam top produs pe categorie
$topPerCat = [];
foreach ($topProduse as $row) {
    if (!isset($topPerCat[$row['cat_id']])) {
        $topPerCat[$row['cat_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport Categorii — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets\images\FavIcon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php require_once 'admin-style.php'; ?>
    <style>
        .stat-icon { width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0; }
        .section-title { font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#e84700;margin-bottom:0; }
        .cat-rank { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0; }
        .progress-thin { height:6px;border-radius:3px; }
        .cat-card { border-radius:12px;border:none;transition:transform .15s; }
        .cat-card:hover { transform:translateY(-2px); }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-tags me-2" style="color:#e84700;"></i>
                Raport Categorii
            </h4>
            <small class="text-muted">Performanta vanzarilor pe fiecare categorie de produse</small>
        </div>
        <a href="?export=csv" class="btn btn-success">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </a>
    </div>

    <!-- CARDURI SUMAR -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-primary" style="background:rgba(13,110,253,0.1);"><i class="fas fa-tags"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL CATEGORII</div>
                        <div class="fw-bold fs-5"><?= $totalCategorii ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-success" style="background:rgba(25,135,84,0.1);"><i class="fas fa-money-bill-wave"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">VENIT TOTAL</div>
                        <div class="fw-bold fs-5"><?= number_format($venitTotal, 2) ?> RON</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-warning" style="background:rgba(255,193,7,0.1);"><i class="fas fa-box"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL PRODUSE</div>
                        <div class="fw-bold fs-5"><?= $produsTotal ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(232,71,0,0.1);color:#e84700;"><i class="fas fa-shopping-cart"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL VANDUTE</div>
                        <div class="fw-bold fs-5"><?= number_format($vandutTotal) ?> buc.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRAFICE -->
    <div class="row g-3 mb-4">

        <!-- Grafic bare - Venit per categorie -->
        <div class="col-lg-7">
            <div class="card-box h-100">
                <div class="p-3 border-bottom"><p class="section-title">VENIT GENERAT PE CATEGORIE (RON)</p></div>
                <div class="p-3">
                    <?php if (empty($categorii)): ?>
                        <div class="text-center text-muted py-4">Nu exista date.</div>
                    <?php else: ?>
                        <canvas id="graficVenit" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Grafic donut - Distributie produse -->
        <div class="col-lg-5">
            <div class="card-box h-100">
                <div class="p-3 border-bottom"><p class="section-title">DISTRIBUTIE PRODUSE PE CATEGORII</p></div>
                <div class="p-3 d-flex align-items-center justify-content-center" style="min-height:240px;">
                    <?php if (empty($categorii)): ?>
                        <div class="text-center text-muted">Nu exista date.</div>
                    <?php else: ?>
                        <canvas id="graficProduse" style="max-height:220px;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- CARDURI CATEGORII -->
    <div class="card-box mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <p class="section-title mb-0">PERFORMANTA DETALIATA PE CATEGORII</p>
            <small class="text-muted">Sortate dupa venit generat</small>
        </div>
        <div class="p-3">
            <?php if (empty($categorii)): ?>
                <div class="text-center text-muted py-4">Nu exista categorii.</div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($categorii as $i => $cat):
                    $color = $chartColors[$i % count($chartColors)];
                    $pctVenit = $venitTotal > 0 ? round(($cat['venit_generat'] / $venitTotal) * 100, 1) : 0;
                    $topProd = $topPerCat[$cat['id']] ?? null;
                    // Extragem culoarea fara opacity pentru border
                    $borderColor = str_replace('0.8)', '1)', $color);
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card cat-card shadow-sm h-100" style="border-left:4px solid <?= $borderColor ?> !important;">
                        <div class="card-body">

                            <!-- Header card -->
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="cat-rank text-white" style="background:<?= $borderColor ?>;">
                                    <?= $i + 1 ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($cat['name']) ?></div>
                                    <small class="text-muted"><?= $cat['nr_produse'] ?> produse in catalog</small>
                                </div>
                            </div>

                            <!-- Statistici -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="bg-light rounded p-2 text-center">
                                        <div class="fw-bold text-success" style="font-size:15px;">
                                            <?= number_format($cat['venit_generat'], 2) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:10px;">RON VENIT</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2 text-center">
                                        <div class="fw-bold" style="font-size:15px;color:#e84700;">
                                            <?= number_format($cat['total_vandute']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:10px;">BUC. VANDUTE</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2 text-center">
                                        <div class="fw-bold text-primary" style="font-size:15px;">
                                            <?= number_format($cat['pret_mediu'], 2) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:10px;">RON PRET MEDIU</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2 text-center">
                                        <div class="fw-bold text-secondary" style="font-size:15px;">
                                            <?= number_format($cat['stoc_total']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:10px;">BUC. STOC</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bara progres venit -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Cota din venit total</small>
                                    <small class="fw-bold"><?= $pctVenit ?>%</small>
                                </div>
                                <div class="progress progress-thin">
                                    <div class="progress-bar" style="width:<?= $pctVenit ?>%;background:<?= $borderColor ?>;"></div>
                                </div>
                            </div>

                            <!-- Top produs -->
                            <?php if ($topProd && $topProd['prod_name']): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted">
                                        <i class="fas fa-trophy text-warning me-1"></i>
                                        Top produs: <strong><?= htmlspecialchars($topProd['prod_name']) ?></strong>
                                        (<?= $topProd['vandute'] ?> buc.)
                                    </small>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABEL DETALIAT -->
    <div class="card-box">
        <div class="p-3 border-bottom"><p class="section-title">TABEL COMPLET CATEGORII</p></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Loc</th>
                        <th>Categorie</th>
                        <th class="text-center">Produse</th>
                        <th class="text-center">Vandute</th>
                        <th class="text-center">Comenzi</th>
                        <th class="text-end">Pret Mediu</th>
                        <th class="text-end">Venit Generat</th>
                        <th class="text-end">Cota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorii as $i => $cat):
                        $pct = $venitTotal > 0 ? round(($cat['venit_generat'] / $venitTotal) * 100, 1) : 0;
                        $color = str_replace('0.8)', '1)', $chartColors[$i % count($chartColors)]);
                    ?>
                    <tr>
                        <td>
                            <?php if ($i===0): ?>🥇
                            <?php elseif ($i===1): ?>🥈
                            <?php elseif ($i===2): ?>🥉
                            <?php else: ?><span class="text-muted">#<?= $i+1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;"></div>
                                <span class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></span>
                            </div>
                        </td>
                        <td class="text-center"><?= $cat['nr_produse'] ?></td>
                        <td class="text-center fw-bold"><?= number_format($cat['total_vandute']) ?> buc.</td>
                        <td class="text-center"><?= $cat['nr_comenzi'] ?></td>
                        <td class="text-end"><?= number_format($cat['pret_mediu'], 2) ?> RON</td>
                        <td class="text-end fw-bold text-success"><?= number_format($cat['venit_generat'], 2) ?> RON</td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <div style="width:60px;height:6px;border-radius:3px;background:#f0f0f0;overflow:hidden;">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px;"></div>
                                </div>
                                <span class="fw-bold" style="font-size:12px;"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($venitTotal > 0): ?>
                <tfoot>
                    <tr style="background:#f8f9fa;">
                        <td colspan="3" class="fw-bold text-end text-muted" style="font-size:12px;">TOTAL:</td>
                        <td class="text-center fw-bold"><?= number_format($vandutTotal) ?> buc.</td>
                        <td></td>
                        <td></td>
                        <td class="text-end fw-bold text-success"><?= number_format($venitTotal, 2) ?> RON</td>
                        <td class="text-end fw-bold">100%</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>

<!-- CHART.JS -->
<?php if (!empty($categorii)): ?>
<script>
const colors = <?= json_encode(array_slice($chartColors, 0, count($categorii))) ?>;

// Grafic bare - venit
new Chart(document.getElementById('graficVenit').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Venit (RON)',
            data: <?= json_encode($chartVenit) ?>,
            backgroundColor: colors,
            borderRadius: 6,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.parsed.y.toFixed(2) + ' RON'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => v + ' RON' }
            }
        }
    }
});

// Grafic donut - produse
new Chart(document.getElementById('graficProduse').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            data: <?= json_encode($chartProduse) ?>,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        cutout: '60%',
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' produse'
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