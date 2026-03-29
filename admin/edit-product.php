<?php
session_start();
require_once '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if(!$id) {
    header('Location: products.php');
    exit;
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if(!$product) {
    header('Location: products.php');
    exit;
}

$error   = '';
$success = '';
$cats    = $pdo->query("SELECT * FROM categories")->fetchAll();

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price       = (float)$_POST['price'];
    $stock       = (int)$_POST['stock'];
    $category_id = (int)$_POST['category_id'];
    $imageName   = $product['image']; // keep existing image by default

    // New image uploaded?
    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $maxSize = 2 * 1024 * 1024;

        if(!in_array($ext, $allowed)) {
            $error = 'Only JPG, PNG, GIF and WEBP allowed.';
        } elseif($_FILES['image']['size'] > $maxSize) {
            $error = 'Image must be smaller than 2MB.';
        } else {
            $imageName  = uniqid('product_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'],
                '../assets/images/products/' . $imageName);
        }
    }

    if(!$error) {
        $stmt = $pdo->prepare("UPDATE products 
                               SET name=?, description=?, price=?, stock=?, 
                                   image=?, category_id=?
                               WHERE id=?");
        $stmt->execute([$name, $description, $price, $stock,
                        $imageName, $category_id, $id]);
        header('Location: products.php?updated=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Produse - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php require_once 'admin-style.php'; ?>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">Edit Produse</h3>
        <a href="products.php" class="btn btn-outline-dark btn-sm">
            ← Inapoi la Produse
        </a>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label fw-semibold">Numele Produsului *</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($product['name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Descriere</label>
                <textarea name="description" class="form-control" rows="3">
                    <?= htmlspecialchars($product['description']) ?>
                </textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Pret (RON) *</label>
                    <input type="number" name="price" class="form-control"
                           step="0.01" min="0"
                           value="<?= $product['price'] ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Stock *</label>
                    <input type="number" name="stock" class="form-control"
                           min="0"
                           value="<?= $product['stock'] ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Categorie *</label>
                <select name="category_id" class="form-select" required>
                    <?php foreach($cats as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= $cat['id'] == $product['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Imaginea produsului</label>
                <!-- Show current image -->
                <div class="mb-2">
                    <img src="../assets/images/products/<?= htmlspecialchars($product['image']) ?>"
                         id="imagePreview"
                         style="height:100px; border-radius:8px; object-fit:cover;"
                         onerror="this.src='../assets/images/placeholder.jpg'">
                </div>
                <input type="file" name="image" id="productImage"
                       class="form-control" accept="image/*">
                <small class="text-muted">
                    Lasa liber pentru a pastra imaginea curenta. Max 2MB.
                </small>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2">
                Salveaza schimbarile
            </button>

        </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Image preview
let imageInput   = document.getElementById('productImage');
let imagePreview = document.getElementById('imagePreview');
if(imageInput && imagePreview) {
    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if(file) {
            const reader = new FileReader();
            reader.onload = e => {
                imagePreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>
</body>
</html>