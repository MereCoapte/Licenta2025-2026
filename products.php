<?php
session_start();
require_once 'includes/db.php';

$pageTitle  = "Produse";
$activePage = "products";

$categoryId  = isset($_GET['category']) ? (int)$_GET['category'] : null;
$sortBy      = $_GET['sort'] ?? 'newest';
$searchTag   = trim($_GET['tag'] ?? '');
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage     = 8;

$sortOptions = [
    'newest'     => ['label' => 'Cele mai noi',      'sql' => 'p.id DESC'],
    'price_asc'  => ['label' => 'Pret: mic → mare',  'sql' => 'p.price ASC'],
    'price_desc' => ['label' => 'Pret: mare → mic',  'sql' => 'p.price DESC'],
    'name_asc'   => ['label' => 'Nume: A → Z',       'sql' => 'p.name ASC'],
    'name_desc'  => ['label' => 'Nume: Z → A',       'sql' => 'p.name DESC'],
    'popular'    => ['label' => 'Cele mai populare', 'sql' => 'total_vandute DESC'],
];
if (!array_key_exists($sortBy, $sortOptions)) $sortBy = 'newest';
$orderSQL = $sortOptions[$sortBy]['sql'];

$cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Taguri dinamice din numele produselor din categoria selectata
$subcatOptions = [];
if ($categoryId) {
    $stmt = $pdo->prepare("SELECT name FROM products WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $prodNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stopWords = ['pro','plus','max','mini','ultra','new','the','and','for','with',
                  'de','si','cu','la','in','din','un','o','ii','the','rgb','gen'];
    $wordCount = [];
    foreach ($prodNames as $name) {
        $words = preg_split('/[\s\-\/]+/', strtolower($name));
        foreach ($words as $word) {
            $word = trim($word, '.,!?()[]0123456789');
            if (strlen($word) >= 4 && !in_array($word, $stopWords) && !is_numeric($word)) {
                $wordCount[$word] = ($wordCount[$word] ?? 0) + 1;
            }
        }
    }
    arsort($wordCount);
    $subcatOptions = array_keys($wordCount);
}

// WHERE dinamic
$conditions = [];
$params = [];
if ($categoryId) {
    $conditions[] = 'p.category_id = :cat';
    $params[':cat'] = $categoryId;
}
if (!empty($searchTag)) {
    $conditions[] = 'p.name LIKE :tag';
    $params[':tag'] = '%' . $searchTag . '%';
}
$whereSQL = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSQL");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = max(1, ceil($totalProducts / $perPage));
$currentPage   = min($currentPage, $totalPages);
$offset        = ($currentPage - 1) * $perPage;

// Query produse
if ($sortBy === 'popular') {
    $sql = "SELECT p.*, c.name as category_name, COALESCE(SUM(oi.quantity),0) as total_vandute
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            LEFT JOIN order_items oi ON oi.product_id=p.id
            $whereSQL GROUP BY p.id ORDER BY $orderSQL LIMIT :limit OFFSET :offset";
} else {
    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id=c.id
            $whereSQL ORDER BY $orderSQL LIMIT :limit OFFSET :offset";
}
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Wishlist
$userWishlist = [];
if (isset($_SESSION['user_id'])) {
    $wStmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wStmt->execute([$_SESSION['user_id']]);
    $userWishlist = array_column($wStmt->fetchAll(), 'product_id');
}

$selectedCat = null;
if ($categoryId) {
    foreach ($cats as $c) { if ($c['id'] == $categoryId) { $selectedCat = $c; break; } }
}

function buildUrl(array $override = []): string {
    $params = array_merge($_GET, $override);
    if (($params['page'] ?? 1) == 1) unset($params['page']);
    if (empty($params['category'])) unset($params['category']);
    if (empty($params['tag'])) unset($params['tag']);
    if (empty($params['sort']) || $params['sort'] === 'newest') unset($params['sort']);
    return 'products.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="fw-bold mb-0">
            <?= $selectedCat ? htmlspecialchars($selectedCat['name']) : 'Toate Produsele' ?>
            <?php if (!empty($searchTag)): ?>
                <span class="text-muted fs-5">— <?= htmlspecialchars(ucfirst($searchTag)) ?></span>
            <?php endif; ?>
        </h2>
        <small class="text-muted">
            <?= $totalProducts ?> produse<?= $totalPages > 1 ? ' · Pagina ' . $currentPage . ' din ' . $totalPages : '' ?>
        </small>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-dark btn-sm dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-sort me-1"></i><?= $sortOptions[$sortBy]['label'] ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <?php foreach ($sortOptions as $val => $opt): ?>
                <li><a class="dropdown-item <?= $sortBy===$val?'active':'' ?>"
                       href="<?= buildUrl(['sort'=>$val,'page'=>1]) ?>"><?= $opt['label'] ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- BARA FILTRE -->
<div class="filter-bar">
    <div class="row g-2 align-items-center">

        <div class="col-12 col-md-auto">
            <label class="form-label mb-1 small text-muted fw-bold">CATEGORIE</label>
            <select class="form-select form-select-sm" style="min-width:170px;"
                    onchange="window.location=this.value">
                <option value="<?= buildUrl(['category'=>null,'tag'=>null,'page'=>1]) ?>"
                        <?= !$categoryId?'selected':'' ?>>🏠 Toate Categoriile</option>
                <?php foreach ($cats as $cat): ?>
                    <option value="<?= buildUrl(['category'=>$cat['id'],'tag'=>null,'page'=>1]) ?>"
                            <?= $categoryId==$cat['id']?'selected':'' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($categoryId && !empty($subcatOptions)): ?>
        <div class="col-12 col-md-auto">
            <label class="form-label mb-1 small text-muted fw-bold">TIP PRODUS</label>
            <select class="form-select form-select-sm" style="min-width:170px;"
                    onchange="window.location=this.value">
                <option value="<?= buildUrl(['tag'=>null,'page'=>1]) ?>"
                        <?= empty($searchTag)?'selected':'' ?>>🔍 Toate tipurile</option>
                <?php foreach ($subcatOptions as $tag): ?>
                    <option value="<?= buildUrl(['tag'=>$tag,'page'=>1]) ?>"
                            <?= $searchTag===$tag?'selected':'' ?>>
                        <?= htmlspecialchars(ucfirst($tag)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Filtre active ca badges -->
        <div class="col active-filters d-flex flex-wrap gap-2 align-items-end pb-1">
            <?php if ($categoryId && $selectedCat): ?>
                <a href="<?= buildUrl(['category'=>null,'tag'=>null,'page'=>1]) ?>"
                   class="badge bg-dark text-decoration-none">
                    <?= htmlspecialchars($selectedCat['name']) ?> ✕
                </a>
            <?php endif; ?>
            <?php if (!empty($searchTag)): ?>
                <a href="<?= buildUrl(['tag'=>null,'page'=>1]) ?>"
                   class="badge bg-secondary text-decoration-none">
                    <?= htmlspecialchars(ucfirst($searchTag)) ?> ✕
                </a>
            <?php endif; ?>
            <?php if ($categoryId || !empty($searchTag)): ?>
                <a href="products.php" class="text-muted small text-decoration-none">
                    Sterge filtrele
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- GRID PRODUSE -->
<?php if (count($products) > 0): ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4">
        <?php foreach ($products as $product):
            $inWishlist = in_array($product['id'], $userWishlist);
        ?>
        <div class="col">
            <div class="card product-card h-100 shadow-sm">
                <div class="position-relative">
                    <img src="assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                         class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>"
                         style="height:300px;object-fit:cover;"
                         onerror="this.src='assets/images/placeholder.jpg'">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="wishlist-toggle position-absolute top-0 end-0 m-2"
                                data-product-id="<?= $product['id'] ?>"
                                data-in-wishlist="<?= $inWishlist?'1':'0' ?>"
                                title="<?= $inWishlist?'Sterge din favorite':'Adauga la favorite' ?>"
                                style="background:rgba(0,0,0,0.55);border:none;border-radius:50%;width:36px;height:36px;cursor:pointer;">
                            <i class="fa<?= $inWishlist?'s':'r' ?> fa-heart"
                               style="color:<?= $inWishlist?'#ff4757':'#fff' ?>;"></i>
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="position-absolute top-0 end-0 m-2 d-flex align-items-center justify-content-center"
                           style="background:rgba(0,0,0,0.55);border-radius:50%;width:36px;height:36px;">
                            <i class="far fa-heart" style="color:#fff;"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column bg-dark text-white">
                    <span class="badge bg-secondary mb-2 align-self-start">
                        <?= htmlspecialchars($product['category_name']??'General') ?>
                    </span>
                    <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                    <p class="card-text text-white small flex-grow-1">
                        <?= htmlspecialchars(substr($product['description'],0,60)) ?>...
                    </p>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="fw-bold text-danger fs-5"><?= number_format($product['price'],2) ?> RON</span>
                        <?php if ($product['stock']>0): ?>
                            <span class="badge bg-success">In Stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Stock Epuizat</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-dark border-0 pb-3">
                    <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-outline-light btn-sm me-1">Afiseaza</a>
                    <?php if ($product['stock']>0): ?>
                    <form method="POST" action="cart.php" class="d-inline">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="btn btn-light btn-sm">Adauga in Cos</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center align-items-center flex-wrap gap-1 mt-2 mb-2">
        <?php if ($currentPage>1): ?>
            <a href="<?= buildUrl(['page'=>1]) ?>" class="btn btn-outline-dark btn-sm"><i class="fas fa-angle-double-left"></i></a>
            <a href="<?= buildUrl(['page'=>$currentPage-1]) ?>" class="btn btn-outline-dark btn-sm"><i class="fas fa-angle-left"></i></a>
        <?php endif; ?>
        <?php
        $pStart=max(1,$currentPage-2); $pEnd=min($totalPages,$pStart+4); $pStart=max(1,$pEnd-4);
        ?>
        <?php if ($pStart>1): ?><a href="<?= buildUrl(['page'=>1]) ?>" class="btn btn-outline-dark btn-sm">1</a><?php if($pStart>2): ?><span class="px-1 text-muted">…</span><?php endif; ?><?php endif; ?>
        <?php for ($p=$pStart;$p<=$pEnd;$p++): ?>
            <a href="<?= buildUrl(['page'=>$p]) ?>" class="btn btn-sm <?= $p===$currentPage?'btn-dark':'btn-outline-dark' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($pEnd<$totalPages): ?><?php if($pEnd<$totalPages-1): ?><span class="px-1 text-muted">…</span><?php endif; ?><a href="<?= buildUrl(['page'=>$totalPages]) ?>" class="btn btn-outline-dark btn-sm"><?= $totalPages ?></a><?php endif; ?>
        <?php if ($currentPage<$totalPages): ?>
            <a href="<?= buildUrl(['page'=>$currentPage+1]) ?>" class="btn btn-outline-dark btn-sm"><i class="fas fa-angle-right"></i></a>
            <a href="<?= buildUrl(['page'=>$totalPages]) ?>" class="btn btn-outline-dark btn-sm"><i class="fas fa-angle-double-right"></i></a>
        <?php endif; ?>
    </nav>
    <div class="text-center text-muted small mb-4">
        Pagina <strong><?= $currentPage ?></strong> din <strong><?= $totalPages ?></strong> · <?= $totalProducts ?> produse total
    </div>
    <?php endif; ?>

<?php else: ?>
    <div class="text-center py-5">
        <div style="font-size:64px;">🔍</div>
        <h4 class="text-muted mt-3">Niciun produs gasit.</h4>
        <?php if ($categoryId||!empty($searchTag)): ?>
            <a href="products.php" class="btn btn-dark mt-2">Vezi toate produsele</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.wishlist-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.dataset.productId;
        const inWishlist = this.dataset.inWishlist === '1';
        const icon = this.querySelector('i');
        const self = this;
        fetch('wishlist.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'product_id='+productId+'&action='+(inWishlist?'remove_wishlist':'add_wishlist')
        }).then(r=>r.json()).then(data=>{
            if (data.in_wishlist) {
                icon.className='fas fa-heart'; icon.style.color='#ff4757';
                self.dataset.inWishlist='1'; self.title='Sterge din favorite';
            } else {
                icon.className='far fa-heart'; icon.style.color='#fff';
                self.dataset.inWishlist='0'; self.title='Adauga la favorite';
            }
            const badge=document.getElementById('wishlist-count');
            if(badge){badge.textContent=data.count;badge.style.display=data.count>0?'':'none';}
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>