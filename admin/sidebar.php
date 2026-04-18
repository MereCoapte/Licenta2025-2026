    <?php
    // Detecteaza ce pagina e activa
    $currentPage = basename($_SERVER['PHP_SELF']);
    ?>
    <div class="sidebar">
        <div class="brand">
            <div>MarketHub<br>
                <small style="font-size:10px; color:#aaa; font-weight:400;">Panou Admin</small>
            </div>
        </div>
        <nav class="nav flex-column mt-2">
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="rapoarte_vanzari.php" class="nav-link <?= $currentPage === 'rapoarte_vanzari.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Rapoarte Vânzări
            </a>
            <a href="rapoarte_produse.php" class="nav-link <?= $currentPage === 'rapoarte_produse.php' ? 'active' : '' ?>">
                <i class="fas fa-archive"></i> Rapoarte Produse
            </a>
            <a href="rapoarte_clienti.php" class="nav-link <?= $currentPage === 'rapoarte_clienti.php' ? 'active' : '' ?>">
                <i class="fas fa-address-card"></i> Rapoarte Clienti
            </a>
            <a href="products.php" class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i> Produse
            </a>
            <a href="add-product.php" class="nav-link <?= $currentPage === 'add-product.php' ? 'active' : '' ?>">
                <i class="fas fa-plus"></i> Adauga Produs
            </a>
            <a href="orders.php" class="nav-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-bag"></i> Comenzi
            </a>
            <a href="users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Utilizatori
            </a>
            <hr style="border-color:rgba(255,255,255,0.08); margin:12px 20px;">
            <a href="../index.php" class="nav-link">
                <i class="fas fa-store"></i> Vezi magazinul
            </a>
            <a href="../logout.php" class="nav-link" style="color:#ff6b6b;">
                <i class="fas fa-sign-out-alt"></i> Iesi din cont
            </a>
        </nav>
    </div>