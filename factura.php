<?php
// =====================================================
// PASUL 1: session_start + db - INTOTDEAUNA PRIMELE
// =====================================================
session_start();
require_once 'includes/db.php';

// =====================================================
// PASUL 2: Verificare acces
// - Adminii pot vedea orice factura
// - Clientii pot vedea doar propriile facturi
// =====================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// =====================================================
// PASUL 3: Preluam datele comenzii
// =====================================================
$stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.total,
        o.status,
        o.address,
        o.created_at,
        o.user_id,
        u.name      AS client_name,
        u.email     AS client_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Daca comanda nu exista
if (!$order) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Clientii nu pot vedea comenzile altora
if (!$isAdmin && $order['user_id'] !== $_SESSION['user_id']) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// =====================================================
// PASUL 4: Preluam produsele din comanda
// =====================================================
$stmt = $pdo->prepare("
    SELECT 
        oi.quantity,
        oi.price,
        (oi.quantity * oi.price) AS subtotal,
        p.name AS product_name,
        c.name AS category_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = ?
    ORDER BY p.name ASC
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Calculam TVA (19%)
$totalFaraTVA = round($order['total'] / 1.19, 2);
$tva          = round($order['total'] - $totalFaraTVA, 2);

// Numar factura formatat
$nrFactura = 'MH-' . date('Y') . '-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
$dataFactura = date('d.m.Y', strtotime($order['created_at']));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura <?= $nrFactura ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }

        /* Bara de actiuni (nu apare la print) */
        .action-bar {
            background: #1a1a2e;
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .action-bar .btn { min-width: 130px; }

        /* Containerul facturii */
        .invoice-wrapper {
            max-width: 820px;
            margin: 32px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* Header factura */
        .invoice-header {
            background: #1a1a2e;
            color: #fff;
            padding: 36px 40px 28px;
        }
        .invoice-header .brand-name {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .invoice-header .brand-name span { color: #e84700; }
        .invoice-header .invoice-title {
            font-size: 13px;
            color: #aaa;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .invoice-nr {
            text-align: right;
        }
        .invoice-nr .nr {
            font-size: 22px;
            font-weight: 700;
            color: #e84700;
        }
        .invoice-nr .data {
            font-size: 13px;
            color: #aaa;
            margin-top: 4px;
        }

        /* Body factura */
        .invoice-body { padding: 36px 40px; }

        /* Sectiunea furnizor / client */
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 36px; }
        .party-box { background: #f8f9fa; border-radius: 10px; padding: 20px 24px; }
        .party-box .party-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #e84700;
            margin-bottom: 10px;
        }
        .party-box .party-name { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .party-box .party-detail { font-size: 13px; color: #555; line-height: 1.7; }

        /* Tabel produse */
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .invoice-table thead tr {
            background: #1a1a2e;
            color: #fff;
        }
        .invoice-table thead th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .invoice-table tbody tr { border-bottom: 1px solid #f0f0f0; }
        .invoice-table tbody tr:last-child { border-bottom: none; }
        .invoice-table tbody td { padding: 14px 16px; font-size: 14px; vertical-align: middle; }
        .invoice-table tbody tr:hover { background: #fafafa; }
        .product-name { font-weight: 600; }
        .product-cat { font-size: 12px; color: #aaa; }

        /* Totaluri */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 0;
            padding: 24px 40px;
            background: #f8f9fa;
        }
        .totals-box { min-width: 280px; }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #555;
        }
        .totals-row.total-final {
            border-top: 2px solid #1a1a2e;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 800;
            color: #1a1a2e;
        }
        .totals-row.total-final .amount { color: #e84700; }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Footer factura */
        .invoice-footer {
            padding: 28px 40px;
            border-top: 1px solid #f0f0f0;
        }
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 28px;
        }
        .signature-box { text-align: center; }
        .signature-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 48px;
        }
        .signature-line {
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 12px;
            color: #aaa;
        }
        .legal-text {
            font-size: 11px;
            color: #bbb;
            text-align: center;
            line-height: 1.8;
        }

        /* ===== PRINT ===== */
        @media print {
            body { background: #fff !important; }
            .action-bar { display: none !important; }
            .invoice-wrapper {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<!-- ===== BARA ACTIUNI (nu apare la print) ===== -->
<div class="action-bar">
    <div class="text-white fw-bold">
        <i class="fas fa-file-invoice me-2" style="color:#e84700;"></i>
        Factura <?= $nrFactura ?>
    </div>
    <div class="d-flex gap-2">
        <!-- Buton inapoi - diferit pentru admin vs client -->
        <?php if ($isAdmin): ?>
            <a href="orders.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Inapoi la Comenzi
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>profile.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Profilul Meu
            </a>
        <?php endif; ?>

        <button onclick="window.print()" class="btn btn-sm" style="background:#e84700; color:#fff;">
            <i class="fas fa-print me-1"></i> Printeaza / PDF
        </button>
    </div>
</div>

<!-- ===== FACTURA ===== -->
<div class="invoice-wrapper">

    <!-- Header -->
    <div class="invoice-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="brand-name">Market<span>Hub</span></div>
                <div class="invoice-title">Factura Fiscala</div>
            </div>
            <div class="invoice-nr">
                <div class="nr"><?= $nrFactura ?></div>
                <div class="data">Data: <?= $dataFactura ?></div>
                <div class="data mt-1">
                    <?php
                    $statusColors = [
                        'pending'      => '#ffc107',
                        'processing'   => '#0dcaf0',
                        'shipped'      => '#0d6efd',
                        'delivered'    => '#198754',
                        'in asteptare' => '#ffc107',
                    ];
                    $statusLabels = [
                        'pending'      => 'In asteptare',
                        'processing'   => 'In procesare',
                        'shipped'      => 'Expediat',
                        'delivered'    => 'Livrat',
                        'in asteptare' => 'In asteptare',
                    ];
                    $sc = $statusColors[$order['status']] ?? '#6c757d';
                    $sl = $statusLabels[$order['status']]  ?? $order['status'];
                    ?>
                    <span class="status-badge" style="background:<?= $sc ?>22; color:<?= $sc ?>; border:1px solid <?= $sc ?>44;">
                        <?= $sl ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="invoice-body">

        <!-- Furnizor & Client -->
        <div class="parties">
            <div class="party-box">
                <div class="party-label">Furnizor</div>
                <div class="party-name">MarketHub S.R.L.</div>
                <div class="party-detail">
                    CUI: RO12345678<br>
                    Reg. Com.: J40/1234/2024<br>
                    Str. Exemplu nr. 1, Bucuresti<br>
                    contact@markethub.ro<br>
                    Tel: 0700 000 000
                </div>
            </div>
            <div class="party-box">
                <div class="party-label">Client</div>
                <div class="party-name"><?= htmlspecialchars($order['client_name'] ?? 'N/A') ?></div>
                <div class="party-detail">
                    <?= htmlspecialchars($order['client_email'] ?? '') ?><br>
                    <?= nl2br(htmlspecialchars($order['address'] ?? '')) ?><br>
                    <span style="color:#aaa; font-size:12px;">
                        Comanda #<?= $order['id'] ?> din <?= $dataFactura ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabel produse -->
        <div style="border-radius:10px; overflow:hidden; border:1px solid #f0f0f0;">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Produs</th>
                        <th style="width:100px;" class="text-center">Cantitate</th>
                        <th style="width:120px;" class="text-end">Pret Unit.</th>
                        <th style="width:130px;" class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td>
                                <div class="product-name"><?= htmlspecialchars($item['product_name'] ?? 'Produs sters') ?></div>
                                <?php if ($item['category_name']): ?>
                                    <div class="product-cat"><?= htmlspecialchars($item['category_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['quantity'] ?> buc.</td>
                            <td class="text-end"><?= number_format($item['price'], 2) ?> RON</td>
                            <td class="text-end fw-bold"><?= number_format($item['subtotal'], 2) ?> RON</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /invoice-body -->

    <!-- Totaluri -->
    <div class="totals-section">
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal (fara TVA):</span>
                <span><?= number_format($totalFaraTVA, 2) ?> RON</span>
            </div>
            <div class="totals-row">
                <span>TVA (19%):</span>
                <span><?= number_format($tva, 2) ?> RON</span>
            </div>
            <div class="totals-row">
                <span>Transport:</span>
                <span class="text-success fw-bold">GRATUIT</span>
            </div>
            <div class="totals-row total-final">
                <span>TOTAL DE PLATA:</span>
                <span class="amount"><?= number_format($order['total'], 2) ?> RON</span>
            </div>
        </div>
    </div>

    <!-- Footer cu semnaturi -->
    <div class="invoice-footer">
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Emitent — MarketHub S.R.L.</div>
                <div class="signature-line">Semnatura si stampila</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Client — <?= htmlspecialchars($order['client_name'] ?? '') ?></div>
                <div class="signature-line">Semnatura de primire</div>
            </div>
        </div>
        <div class="legal-text">
            Aceasta factura a fost generata electronic de sistemul MarketHub.<br>
            Factura este valabila fara semnatura si stampila conform art. 319 din Codul Fiscal.<br>
            Va multumim pentru comanda! Pentru intrebari: contact@markethub.ro
        </div>
    </div>

</div><!-- /invoice-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>