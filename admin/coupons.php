<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$errors  = [];
$success = '';

// =====================================================
// POST: Creare / Toggle activ / Stergere
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $type       = $_POST['type']       ?? 'percent';
        $value      = (float)($_POST['value'] ?? 0);
        $min_order  = (float)($_POST['min_order'] ?? 0);
        $uses_limit = $_POST['uses_limit'] !== '' ? (int)$_POST['uses_limit'] : null;
        $expires_at = $_POST['expires_at'] !== '' ? $_POST['expires_at'] : null;

        if (empty($code))   $errors[] = 'Codul este obligatoriu.';
        if ($value <= 0)    $errors[] = 'Valoarea trebuie sa fie mai mare ca 0.';
        if ($type === 'percent' && $value > 100) $errors[] = 'Procentul nu poate depasi 100%.';

        if (empty($errors)) {
            try {
                $pdo->prepare("
                    INSERT INTO coupons (code, type, value, min_order, uses_limit, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$code, $type, $value, $min_order, $uses_limit, $expires_at]);
                $success = 'Cuponul "' . $code . '" a fost creat cu succes!';
            } catch (PDOException $e) {
                $errors[] = 'Codul "' . $code . '" exista deja.';
            }
        }

    } elseif ($action === 'toggle') {
        $id = (int)$_POST['coupon_id'];
        $pdo->prepare("UPDATE coupons SET active = NOT active WHERE id = ?")->execute([$id]);
        header('Location: coupons.php?updated=1');
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['coupon_id'];
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        header('Location: coupons.php?deleted=1');
        exit;

    } elseif ($action === 'reset') {
        $id = (int)$_POST['coupon_id'];
        $pdo->prepare("UPDATE coupons SET uses_count = 0 WHERE id = ?")->execute([$id]);
        header('Location: coupons.php?reset=1');
        exit;
    }
}

// Toate cupoanele
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

// Statistici rapide
$totalCoupons  = count($coupons);
$activeCoupons = count(array_filter($coupons, fn($c) => $c['active']));
$totalUses     = array_sum(array_column($coupons, 'uses_count'));
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupoane — Admin MarketHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'admin-style.php'; ?>
    <style>
        .stat-icon { width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0; }
        .coupon-code { font-family:monospace;font-size:15px;font-weight:700;letter-spacing:2px;
                       background:#1a1a2e;color:#e84700;padding:4px 10px;border-radius:6px; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-tag me-2" style="color:#e84700;"></i>Cupoane de Reducere
            </h4>
            <small class="text-muted">Creeaza si gestioneaza coduri promotionale</small>
        </div>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalCoupon">
            <i class="fas fa-plus me-2"></i>Cupon Nou
        </button>
    </div>

    <!-- Alerte -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-info alert-dismissible fade show">Status cupon actualizat.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CARDURI SUMAR -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card-box p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-primary" style="background:rgba(13,110,253,0.1);"><i class="fas fa-tags"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL CUPOANE</div>
                        <div class="fw-bold fs-5"><?= $totalCoupons ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card-box p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon text-success" style="background:rgba(25,135,84,0.1);"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">ACTIVE</div>
                        <div class="fw-bold fs-5"><?= $activeCoupons ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card-box p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:rgba(232,71,0,0.1);color:#e84700;"><i class="fas fa-fire"></i></div>
                    <div>
                        <div class="text-muted" style="font-size:12px;">TOTAL UTILIZARI</div>
                        <div class="fw-bold fs-5"><?= $totalUses ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL CUPOANE -->
    <div class="card-box">
        <div class="p-3 border-bottom fw-bold" style="font-size:13px;">
            <i class="fas fa-list me-2"></i>TOATE CUPOANELE
        </div>
        <?php if (empty($coupons)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-tag fa-3x mb-3 opacity-25"></i>
                <p>Nu exista cupoane inca. Creeaza primul!</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Cod</th>
                        <th>Tip</th>
                        <th class="text-end">Valoare</th>
                        <th class="text-end">Comanda Min.</th>
                        <th class="text-center">Utilizari</th>
                        <th>Expira</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c):
                        $expired = $c['expires_at'] && $c['expires_at'] < date('Y-m-d');
                        $limitReached = $c['uses_limit'] && $c['uses_count'] >= $c['uses_limit'];
                    ?>
                    <tr class="<?= (!$c['active'] || $expired) ? 'opacity-50' : '' ?>">
                        <td><span class="coupon-code"><?= htmlspecialchars($c['code']) ?></span></td>
                        <td>
                            <?php if ($c['type'] === 'percent'): ?>
                                <span class="badge bg-info text-dark">Procent</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Suma Fixa</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?= $c['type'] === 'percent'
                                ? $c['value'] . '%'
                                : number_format($c['value'], 2) . ' RON' ?>
                        </td>
                        <td class="text-end text-muted">
                            <?= $c['min_order'] > 0 ? number_format($c['min_order'], 2) . ' RON' : '—' ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $limitReached ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $c['uses_count'] ?><?= $c['uses_limit'] ? ' / ' . $c['uses_limit'] : '' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($c['expires_at']): ?>
                                <span class="<?= $expired ? 'text-danger fw-bold' : 'text-muted' ?>" style="font-size:13px;">
                                    <?= date('d.m.Y', strtotime($c['expires_at'])) ?>
                                    <?= $expired ? ' ⚠️' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Fara expirare</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($expired): ?>
                                <span class="badge bg-danger">Expirat</span>
                            <?php elseif ($limitReached): ?>
                                <span class="badge bg-warning text-dark">Epuizat</span>
                            <?php elseif ($c['active']): ?>
                                <span class="badge bg-success">Activ</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactiv</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <!-- Toggle activ -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $c['active'] ? 'btn-warning' : 'btn-success' ?>"
                                            title="<?= $c['active'] ? 'Dezactiveaza' : 'Activeaza' ?>">
                                        <i class="fas fa-<?= $c['active'] ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <!-- Reset utilizari -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-info" title="Reseteaza utilizari">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </form>
                                <!-- Sterge -->
                                <form method="POST" onsubmit="return confirm('Stergi cuponul <?= $c['code'] ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
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

<!-- MODAL Creare cupon -->
<div class="modal fade" id="modalCoupon" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-tag me-2"></i>Cupon Nou</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cod Cupon *</label>
                        <input type="text" name="code" class="form-control"
                               placeholder="Ex: SUMMER20" style="text-transform:uppercase;" required>
                        <div class="form-text">Doar litere mari si cifre, fara spatii.</div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Tip Reducere *</label>
                            <select name="type" class="form-select" id="couponType" onchange="toggleType()">
                                <option value="percent">Procent (%)</option>
                                <option value="fixed">Suma Fixa (RON)</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Valoare *</label>
                            <div class="input-group">
                                <input type="number" name="value" class="form-control"
                                       step="0.01" min="0.01" placeholder="10" required>
                                <span class="input-group-text" id="typeLabel">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comanda Minima (RON)</label>
                        <input type="number" name="min_order" class="form-control"
                               step="0.01" min="0" value="0" placeholder="0 = fara minim">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Limita Utilizari</label>
                            <input type="number" name="uses_limit" class="form-control"
                                   min="1" placeholder="Gol = nelimitat">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Data Expirare</label>
                            <input type="date" name="expires_at" class="form-control"
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuleaza</button>
                    <button type="submit" class="btn btn-dark">
                        <i class="fas fa-plus me-1"></i>Creeaza Cupon
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleType() {
    const type  = document.getElementById('couponType').value;
    document.getElementById('typeLabel').textContent = type === 'percent' ? '%' : 'RON';
}
// Deschide modal automat daca sunt erori la creare
<?php if (!empty($errors)): ?>
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('modalCoupon')).show();
    });
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>