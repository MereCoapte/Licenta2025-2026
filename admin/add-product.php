<?php
session_start();
require_once '../includes/db.php';

// Block non-admins
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {


    // ── Creating a new category ───────────────────────
    if(isset($_POST['create_category'])) {
        $newCat = trim($_POST['new_category']);
        if(!empty($newCat)) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $check->execute([$newCat]);
            if($check->fetch()) {
                $error = 'Category "' . htmlspecialchars($newCat) . '" already exists!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$newCat]);
                $success = 'Category "' . htmlspecialchars($newCat) . '" created! Now select it below.';
            }
        } else {
            $error = 'Category name cannot be empty.';
        }
    }

    elseif(isset($_POST['add_product'])) {
        $name        = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price       = (float)$_POST['price'];
        $stock       = (int)$_POST['stock'];
        $category_id = (int)$_POST['category_id'];
        $imageName   = 'placeholder.jpg';

    // ── Image Upload ──────────────────────────────
    $imageName = 'placeholder.jpg'; // default if no image

    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext         = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $maxSize     = 2 * 1024 * 1024; // 2MB max

        if(!in_array($ext, $allowed)) {
            $error = 'Only JPG, PNG, GIF and WEBP images are allowed.';
        } elseif($_FILES['image']['size'] > $maxSize) {
            $error = 'Image must be smaller than 2MB.';
        } else {
            // Create unique filename so images never overwrite each other
            $imageName = uniqid('product_') . '.' . $ext;
            $uploadPath = '../assets/images/products/' . $imageName;
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath);
        }
    }

    // ── Save to DB if no errors ───────────────────
    if(!$error) {
        $stmt = $pdo->prepare("INSERT INTO products 
                               (name, description, price, stock, image, category_id)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $stock, $imageName, $category_id]);
        $success = 'Product added successfully!';
        }
    }
    // Refresh categories after any action
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
}

// Get categories for dropdown
$cats = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adauga Produs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Adauga un nou produs</h2>
                <a href="../index.php" class="btn btn-outline-dark btn-sm">
                    ← Inapoi in magazin
                </a>
            </div>

            <!-- Success/Error messages -->
            <?php if($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- Form -->
            <div class="card shadow-sm">
                <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_product" value="1">

                    <!-- Name -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Numele Produsului *</label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. Wireless Headphones" required>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descriere</label>
                        <textarea name="description" class="form-control"
                                  rows="3" placeholder="Describe the product..."></textarea>
                    </div>

                    <!-- Price + Stock side by side -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Pret (RON) *</label>
                            <input type="number" name="price" class="form-control"
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Stock *</label>
                            <input type="number" name="stock" class="form-control"
                                   min="0" placeholder="0" required>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category *</label>
                        <div class="d-flex gap-2">
                            <select name="category_id" id="categorySelect" class="form-select" required>
                                <option value="">-- Selecteaza o categorie --</option>
                                <?php foreach($cats as $cat): ?>
                                    <option value="<?= $cat['id'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-dark btn-sm"
                                    onclick="toggleNewCategory()">
                                + New
                            </button>
                        </div>
                    </div>

                    <!-- Hidden new category input — shows when clicking + New -->
                    <div class="mb-3" id="newCategoryBox" style="display:none;">
                        <label class="form-label fw-semibold">O noua categorie</label>
                        <div class="d-flex gap-2">
                            <input type="text" name="new_category" id="newCategoryInput"
                                class="form-control" placeholder="e.g. Sport & Outdoor">
                            <button type="submit" name="create_category" class="btn btn-dark">
                                Creaza
                            </button>
                        </div>
                        <small class="text-muted">
                            Dupa ce a fost creat, trebuie sa dai refresh la pagina pentru a putea selecta noua categorie din dropdown.
                        </small>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Imaginea produsului</label>
                        <input type="file" name="image" id="productImage"
                               class="form-control" accept="image/*">
                        <small class="text-muted">Max 2MB. JPG, PNG, GIF, WEBP allowed.</small>
                        <!-- Live preview -->
                        <div class="mt-2">
                            <img id="imagePreview"
                                 style="display:none; max-height:150px; border-radius:8px;"
                                 alt="Preview">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2">
                        Adauga Produs
                    </button>

                </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Image preview before upload
document.getElementById('productImage').addEventListener('change', function() {
    const file = this.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('imagePreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>

<script>
function toggleNewCategory() {
    let box   = document.getElementById('newCategoryBox');
    let input = document.getElementById('newCategoryInput');
    let select = document.getElementById('categorySelect');

    if(box.style.display === 'none') {
        box.style.display = 'block';
        // Make category select not required when creating new one
        select.removeAttribute('required');
        input.focus();
    } else {
        box.style.display = 'none';
        select.setAttribute('required', 'required');
    }
}
</script>

</body>
</html>