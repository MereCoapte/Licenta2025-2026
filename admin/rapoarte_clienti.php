<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Export CSV
if (isset($_GET['export'])) {
    $tip = $_GET['export'];
    if ($tip === 'toti') {
        $stmt = $pdo->query("
            SELECT u.id AS 'ID', u.name AS 'Nume', u.email AS 'Email', u.role AS 'Rol',
                   COUNT(o.id) AS 'Nr Comenzi',
                   COALESCE(SUM(o.total), 0) AS 'Total Cheltuit (RON)',
                   u.created_at AS 'Data Inregistrare'
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            GROUP BY u.id
            ORDER BY 6 DESC
        ");
        $filename = 'raport-clienti-' . date('Y-m-d') . '.csv';
    } else {
        $stmt = $pdo->query("
            SELECT u.name AS 'Nume', u.email AS 'Email', u.created_at AS 'Data Inregistrare'
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            WHERE u.role = 'customer'
            GROUP BY u.id
            HAVING COUNT(o.id) = 0
        ");
        $filename = 'raport-clienti-inactivi-' . date('Y-m-d') . '.csv';
    }
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]), ';');
        foreach ($rows as $r) fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

// Carduri
$totalClienti  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$clientiNoi    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$clientiActivi = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Top clienti
$topClienti = $pdo->query("
    SELECT u.id, u.name, u.email, u.created_at,
           COUNT(o.id) AS nr_comenzi,
           COALESCE(SUM(o.total), 0) AS total_cheltuit,
           MAX(o.created_at) AS ultima_comanda
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY total_cheltuit DESC
    LIMIT 10
")->fetchAll();

// Clienti inactivi
$clientiInactivi = $pdo->query("
    SELECT u.id, u.name, u.email, u.created_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'customer'
    GROUP BY u.id
    HAVING COUNT(o.id) = 0
    ORDER BY u.created_at DESC
")->fetchAll();

// Grafic clienti noi pe 6 luni
$graficLuni = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS luna,
           DATE_FORMAT(created_at, '%Y-%m') AS luna_sort,
           COUNT(*) AS nr
    FROM users
    WHERE role='customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY luna_sort ORDER BY luna_sort ASC
")->fetchAll();

$chartLuni    = array_column($graficLuni, 'luna');
$chartClienti = array_column($graficLuni, 'nr');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport Clienti — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets\images\FavIcon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php require_once 'admin-style.php'; ?>
    <style>
        .stat-icon { width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0; }
        .section-title { font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#e84700;margin-bottom:0; }
        .tab-nav .nav-link { color:#888;font-size:13px;font-weight:600;border-bottom:2px solid transparent;border-radius:0;padding:10px 20px; }
        .tab-nav .nav-link.active { color:#e84700;border-bottom-color:#e84700;background:none; }
        .avatar { width:36px;height:36px;border-radius:50%;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-users me-2" style="color:#e84700;"></i>Raport Clienti
            </h4>
            <small class="text-muted">Activitate, cheltuieli si inregistrari clienti</small>
        </div>
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?export=toti"><i class="fas fa-users me-2 text-primary"></i>Toti clientii</a></li>
                <li><a class="dropdown-item" href="?export=inactivi"><i class="fas fa-ghost me-2 text-secondary"></i>Clienti inactivi</a></li>
            </ul>
        </div>
    </div>

    <!-- CARDURI -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-primary" style="background:rgba(13,110,253,0.1);"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL CLIENTI</div>
                        <div class="fw-bold fs-5"><?= $totalClienti ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-success" style="background:rgba(25,135,84,0.1);"><i class="fas fa-user-plus"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">NOI (30 ZILE)</div>
                        <div class="fw-bold fs-5"><?= $clientiNoi ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-warning" style="background:rgba(255,193,7,0.1);"><i class="fas fa-fire"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">ACTIVI (30 ZILE)</div>
                        <div class="fw-bold fs-5"><?= $clientiActivi ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card-box p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-secondary" style="background:rgba(108,117,125,0.1);"><i class="fas fa-ghost"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">FARA COMENZI</div>
                        <div class="fw-bold fs-5"><?= count($clientiInactivi) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRAFIC -->
    <div class="card-box mb-4">
        <div class="p-3 border-bottom"><p class="section-title">CLIENTI NOI — ULTIMELE 6 LUNI</p></div>
        <div class="p-3">
            <?php if (empty($graficLuni)): ?>
                <div class="text-center text-muted py-4">Nu exista date.</div>
            <?php else: ?>
                <canvas id="graficClienti" height="80"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABELE -->
    <div class="card-box">
        <ul class="nav tab-nav border-bottom px-3">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-top">
                    <i class="fas fa-crown me-1 text-warning"></i>Top Clienti
                    <span class="badge bg-warning text-dark ms-1"><?= count($topClienti) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inactivi">
                    <i class="fas fa-ghost me-1 text-secondary"></i>Inactivi
                    <?php if (!empty($clientiInactivi)): ?>
                        <span class="badge bg-secondary ms-1"><?= count($clientiInactivi) ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content">

            <!-- Top clienti -->
            <div class="tab-pane fade show active" id="tab-top">
                <?php if (empty($topClienti)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-users fa-3x mb-3 opacity-25"></i><p>Nu exista clienti inca.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Loc</th><th>Client</th>
                                    <th class="text-center">Comenzi</th>
                                    <th class="text-end">Total Cheltuit</th>
                                    <th>Ultima Comanda</th>
                                    <th>Inregistrat</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topClienti as $i => $c): ?>
                                <tr>
                                    <td><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':'<span class="text-muted fw-bold">#'.($i+1).'</span>')) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($c['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold"><?= $c['nr_comenzi'] ?></td>
                                    <td class="text-end fw-bold text-success"><?= number_format($c['total_cheltuit'],2) ?> RON</td>
                                    <td><small><?= $c['ultima_comanda'] ? date('d.m.Y', strtotime($c['ultima_comanda'])) : '—' ?></small></td>
                                    <td><small><?= date('d.m.Y', strtotime($c['created_at'])) ?></small></td>
                                    <td><a href="users.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="fas fa-eye"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Inactivi -->
            <div class="tab-pane fade" id="tab-inactivi">
                <?php if (empty($clientiInactivi)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-star fa-3x mb-3 text-warning opacity-50"></i>
                        <p>Toti clientii au cel putin o comanda!</p>
                    </div>
                <?php else: ?>
                    <div class="p-3 pb-0">
                        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-ghost"></i>
                            <span><strong><?= count($clientiInactivi) ?> clienti</strong> s-au inregistrat dar nu au plasat nicio comanda.</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Client</th><th>Email</th><th>Inregistrat</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientiInactivi as $c): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar" style="background:#6c757d;"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                                            <span class="fw-semibold"><?= htmlspecialchars($c['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($c['email']) ?></td>
                                    <td><small><?= date('d.m.Y', strtotime($c['created_at'])) ?></small></td>
                                    <td><a href="users.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="fas fa-eye"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<?php if (!empty($graficLuni)): ?>
<script>
new Chart(document.getElementById('graficClienti').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLuni) ?>,
        datasets: [{
            label: 'Clienti noi',
            data: <?= json_encode($chartClienti) ?>,
            backgroundColor: 'rgba(232,71,0,0.15)',
            borderColor: '#e84700',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>