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
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_start = $_GET['start'] ?? date('Y-m-01');
    $export_end   = $_GET['end']   ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            o.id            AS 'ID Comanda',
            u.name          AS 'Client',
            u.email         AS 'Email',
            o.total         AS 'Total (RON)',
            o.status        AS 'Status',
            o.address       AS 'Adresa',
            o.created_at    AS 'Data'
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$export_start, $export_end]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="raport-vanzari-' . $export_start . '-' . $export_end . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM pentru diacritice in Excel

    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
    } else {
        fputcsv($output, ['Nu exista comenzi in perioada selectata'], ';');
    }
    fclose($output);
    exit;
}

// =====================================================
// PASUL 4: Filtre perioada
// =====================================================
$filter = $_GET['filter'] ?? 'luna';

switch ($filter) {
    case 'azi':
        $start = date('Y-m-d');
        $end   = date('Y-m-d');
        $label = 'Astazi';
        break;
    case 'saptamana':
        $start = date('Y-m-d', strtotime('monday this week'));
        $end   = date('Y-m-d');
        $label = 'Aceasta saptamana';
        break;
    case 'an':
        $start = date('Y-01-01');
        $end   = date('Y-m-d');
        $label = 'Anul ' . date('Y');
        break;
    case 'custom':
        $start = $_GET['start'] ?? date('Y-m-01');
        $end   = $_GET['end']   ?? date('Y-m-d');
        $label = 'Perioada personalizata';
        break;
    default:
        $start = date('Y-m-01');
        $end   = date('Y-m-d');
        $label = 'Luna ' . date('F Y');
        break;
}

// =====================================================
// PASUL 5: Queries statistici
// =====================================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$venitTotal = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$nrComenzi = $stmt->fetchColumn();

$valoareMedie = $nrComenzi > 0 ? $venitTotal / $nrComenzi : 0;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$clientiUnici = $stmt->fetchColumn();

// Grafic zilnic
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as zi, SUM(total) as venit, COUNT(*) as nr_comenzi
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY zi ASC
");
$stmt->execute([$start, $end]);
$graficDate = $stmt->fetchAll();

$chartLabels = $chartVenit = $chartComenzi = [];
foreach ($graficDate as $row) {
    $chartLabels[]  = date('d M', strtotime($row['zi']));
    $chartVenit[]   = (float) $row['venit'];
    $chartComenzi[] = (int)   $row['nr_comenzi'];
}

// Tabel comenzi
$stmt = $pdo->prepare("
    SELECT o.id, u.name as client_name, u.email as client_email,
           o.total, o.status, o.created_at, COUNT(oi.id) as nr_produse
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$start, $end]);
$comenzi = $stmt->fetchAll();

function statusBadge($status) {
    return match($status) {
        'pending'      => '<span class="badge bg-warning text-dark">In asteptare</span>',
        'processing'   => '<span class="badge bg-info text-dark">In procesare</span>',
        'shipped'      => '<span class="badge bg-primary">Expediat</span>',
        'delivered'    => '<span class="badge bg-success">Livrat</span>',
        'in asteptare' => '<span class="badge bg-warning text-dark">In asteptare</span>',
        default        => '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>',
    };
}

// =====================================================
// PASUL 6: ABIA ACUM HTML - exact ca celelalte pagini admin
// =====================================================
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapoarte — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php require_once 'admin-style.php'; ?>

    <style>
        /* Stiluri extra doar pentru rapoarte */
        .stat-card { border: none; border-radius: 12px; transition: transform .2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            flex-shrink: 0;
        }
        .filter-btn.active {
            background: #1a1a2e !important;
            color: #fff !important;
            border-color: #1a1a2e !important;
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
                <i class="fas fa-chart-line me-2" style="color:#e84700;"></i>
                Rapoarte Vânzări
            </h4>
            <small class="text-muted"><?= $label ?> &nbsp;(<?= $start ?> → <?= $end ?>)</small>
        </div>
        <a href="?filter=<?= $filter ?>&start=<?= $start ?>&end=<?= $end ?>&export=csv"
           class="btn btn-success">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </a>
    </div>

    <!-- ===== FILTRE ===== -->
    <div class="card-box p-3 mb-4">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold me-1 text-muted" style="font-size:13px;">PERIOADA:</span>
            <?php foreach (['azi' => 'Azi', 'saptamana' => 'Saptamana', 'luna' => 'Luna curenta', 'an' => 'An curent'] as $val => $lab): ?>
                <a href="?filter=<?= $val ?>"
                   class="btn btn-outline-dark btn-sm filter-btn <?= $filter === $val ? 'active' : '' ?>">
                    <?= $lab ?>
                </a>
            <?php endforeach; ?>

            <form method="GET" class="d-flex gap-2 ms-2 align-items-center">
                <input type="hidden" name="filter" value="custom">
                <input type="date" name="start" class="form-control form-control-sm"
                       value="<?= $filter === 'custom' ? $start : date('Y-m-01') ?>"
                       max="<?= date('Y-m-d') ?>">
                <span class="text-muted small">—</span>
                <input type="date" name="end" class="form-control form-control-sm"
                       value="<?= $filter === 'custom' ? $end : date('Y-m-d') ?>"
                       max="<?= date('Y-m-d') ?>">
                <button type="submit" class="btn btn-dark btn-sm">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- ===== CARDURI STATISTICI ===== -->
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-success" style="background:rgba(25,135,84,0.1);">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
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
                    <div class="stat-icon text-primary" style="background:rgba(13,110,253,0.1);">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">COMENZI PLASATE</div>
                        <div class="fw-bold fs-5"><?= number_format($nrComenzi) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-warning" style="background:rgba(255,193,7,0.1);">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">MEDIE / COMANDA</div>
                        <div class="fw-bold fs-5"><?= number_format($valoareMedie, 2) ?> RON</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-info" style="background:rgba(13,202,240,0.1);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">CLIENTI UNICI</div>
                        <div class="fw-bold fs-5"><?= number_format($clientiUnici) ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== GRAFIC ===== -->
    <div class="card-box mb-4">
        <div class="p-3 border-bottom fw-bold" style="font-size:13px;">
            <i class="fas fa-chart-area me-2" style="color:#e84700;"></i>
            EVOLUTIE VÂNZĂRI — <?= strtoupper($label) ?>
        </div>
        <div class="p-3">
            <?php if (empty($graficDate)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-chart-area fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">Nu exista date pentru aceasta perioada.</p>
                </div>
            <?php else: ?>
                <canvas id="graficVanzari" height="90"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TABEL COMENZI ===== -->
    <div class="card-box">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold" style="font-size:13px;">
                <i class="fas fa-list me-2"></i>COMENZI IN PERIOADA SELECTATA
            </span>
            <span class="badge bg-dark"><?= count($comenzi) ?> comenzi</span>
        </div>

        <?php if (empty($comenzi)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">Nu exista comenzi in aceasta perioada.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Client</th>
                            <th>Produse</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comenzi as $c): ?>
                            <tr>
                                <td class="fw-bold">#<?= $c['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($c['client_name'] ?? 'N/A') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($c['client_email'] ?? '') ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= $c['nr_produse'] ?> prod.</span></td>
                                <td class="fw-bold text-success"><?= number_format($c['total'], 2) ?> RON</td>
                                <td><?= statusBadge($c['status']) ?></td>
                                <td><small><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></small></td>
                                <td>
                                    <a href="orders.php?view=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8f9fa;">
                            <td colspan="3" class="fw-bold text-end text-muted" style="font-size:12px;">
                                TOTAL PERIOADA:
                            </td>
                            <td class="fw-bold text-success"><?= number_format($venitTotal, 2) ?> RON</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- Chart.js -->
<?php if (!empty($graficDate)): ?>
<script>
const ctx = document.getElementById('graficVanzari').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Venit (RON)',
                data: <?= json_encode($chartVenit) ?>,
                borderColor: '#e84700',
                backgroundColor: 'rgba(232,71,0,0.08)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Nr. Comenzi',
                data: <?= json_encode($chartComenzi) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.08)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: {
            y: {
                type: 'linear', position: 'left',
                ticks: { callback: val => val.toFixed(0) + ' RON' }
            },
            y1: {
                type: 'linear', position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { stepSize: 1, callback: val => val + ' cmd' }
            }
        }
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>