<style>
    body { background: #f0f2f5; }
    .sidebar {
        min-height: 100vh;
        background: #1a1a2e;
        width: 240px;
        position: fixed;
        top: 0; left: 0;
        padding-top: 20px;
        z-index: 100;
    }
    .sidebar .brand {
        color: white;
        font-size: 17px;
        font-weight: 700;
        padding: 0 24px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 10px;
    }
    .sidebar .nav-link {
        color: #aaa;
        padding: 11px 24px;
        border-left: 3px solid transparent;
        transition: all 0.2s;
        font-size: 14px;
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        color: #fff;
        background: rgba(255,255,255,0.06);
        border-left-color: #e84700;
    }
    .sidebar .nav-link i { width: 20px; }
    .main-content {
        margin-left: 240px;
        padding: 28px 32px;
        min-height: 100vh;
    }
    .table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
        font-weight: 600;
    }
    .table td { vertical-align: middle; font-size: 13.5px; }
    .card-box {
        background: white;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
</style>