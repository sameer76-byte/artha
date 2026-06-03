<?php
// admin/includes/header.php
// Included at the top of every admin page
// $page_title must be set before including this

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Guard — admin only
if (!is_admin()) {
    header('Location: ' . SITE_URL . '/pages/login.php?redirect=admin/index.php');
    exit;
}

$admin_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin') ?> — <?= SITE_NAME ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.css">
    <style>
    /* ── Reset & vars ─────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --dark:    #1a1612;
        --dark-2:  #2c2620;
        --accent:  #d4af37;
        --light:   #faf8f4;
        --border:  #e8e2d8;
        --text:    #3a3530;
        --muted:   #9a9088;
        --success: #2d7a4f;
        --error:   #c0392b;
        --warning: #b7791f;
        --info:    #1d4ed8;
        --sidebar-w: 240px;
    }
    html { font-size: 15px; }
    body {
        font-family: 'DM Sans', sans-serif;
        background: #f4f1ec;
        color: var(--text);
        min-height: 100vh;
        display: flex;
    }
    a { text-decoration: none; color: inherit; }
    button { cursor: pointer; font-family: inherit; }
    img { max-width: 100%; display: block; }

    /* ── Sidebar ──────────────────────────────────────────── */
    .admin-sidebar {
        width: var(--sidebar-w);
        background: var(--dark);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0;
        z-index: 100;
        transition: transform 0.3s ease;
    }
    .sidebar-logo {
        padding: 1.5rem 1.4rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .logo-icon {
        width: 32px; height: 32px;
        background: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: var(--dark);
        font-weight: 700;
        flex-shrink: 0;
    }
    .logo-text {
        font-family: 'Playfair Display', serif;
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.1;
    }
    .logo-sub { font-size: 0.62rem; color: rgba(255,255,255,0.35); letter-spacing: 0.12em; text-transform: uppercase; }
    .sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }
    .nav-section-label {
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.25);
        padding: 1rem 1.4rem 0.4rem;
    }
    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.7rem 1.4rem;
        font-size: 0.82rem;
        color: rgba(255,255,255,0.55);
        transition: all 0.15s;
        position: relative;
        border-left: 3px solid transparent;
        text-decoration: none;
    }
    .nav-item:hover { color: #fff; background: rgba(255,255,255,0.05); }
    .nav-item.active {
        color: #fff;
        background: rgba(255,255,255,0.08);
        border-left-color: var(--accent);
    }
    .nav-item i { width: 16px; text-align: center; font-size: 0.85rem; }
    .nav-badge {
        margin-left: auto;
        background: var(--accent);
        color: var(--dark);
        font-size: 0.6rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    .nav-badge.red { background: var(--error); color: #fff; }
    .sidebar-footer {
        padding: 1rem 1.4rem;
        border-top: 1px solid rgba(255,255,255,0.08);
        font-size: 0.78rem;
        color: rgba(255,255,255,0.35);
    }
    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: rgba(255,255,255,0.45);
        transition: color 0.15s;
        margin-bottom: 0.5rem;
        font-size: 0.8rem;
    }
    .sidebar-footer a:hover { color: #fff; }

    /* ── Main content ─────────────────────────────────────── */
    .admin-main {
        margin-left: var(--sidebar-w);
        flex: 1;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ── Top bar ──────────────────────────────────────────── */
    .admin-topbar {
        background: #fff;
        border-bottom: 1px solid var(--border);
        padding: 0 2rem;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 99;
        gap: 1rem;
    }
    .topbar-left { display: flex; align-items: center; gap: 1rem; }
    .page-title-bar {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--dark);
    }
    .topbar-right { display: flex; align-items: center; gap: 1rem; }
    .topbar-btn {
        width: 36px; height: 36px;
        border: 1px solid var(--border);
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: var(--muted);
        cursor: pointer;
        position: relative;
        transition: all 0.15s;
        text-decoration: none;
    }
    .topbar-btn:hover { border-color: var(--dark); color: var(--dark); }
    .topbar-notif {
        position: absolute;
        top: -4px; right: -4px;
        width: 14px; height: 14px;
        background: var(--error);
        color: #fff;
        font-size: 0.55rem;
        font-weight: 700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .admin-user {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.4rem 0.8rem;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: border-color 0.15s;
        font-size: 0.82rem;
        color: var(--dark);
    }
    .admin-user:hover { border-color: var(--dark); }
    .admin-avatar {
        width: 28px; height: 28px;
        background: var(--accent);
        color: var(--dark);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    /* ── Page content wrapper ─────────────────────────────── */
    .admin-content { padding: 2rem; flex: 1; }

    /* ── Cards ────────────────────────────────────────────── */
    .card {
        background: #fff;
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }
    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.4rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
        gap: 1rem;
        flex-wrap: wrap;
    }
    .card-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .card-title i { color: var(--accent); }
    .card-body { padding: 1.4rem; }

    /* ── Stat cards ───────────────────────────────────────── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        background: #fff;
        border: 1px solid var(--border);
        padding: 1.3rem 1.4rem;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    .stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 0.5rem;
    }
    .stat-value {
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark);
        line-height: 1;
        margin-bottom: 0.3rem;
    }
    .stat-change {
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .stat-change.up   { color: var(--success); }
    .stat-change.down { color: var(--error); }
    .stat-icon {
        width: 44px; height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        border-radius: 2px;
    }

    /* ── Table ────────────────────────────────────────────── */
    .admin-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
    .admin-table th {
        text-align: left;
        padding: 0.7rem 1rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
        background: var(--light);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    .admin-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        color: var(--text);
    }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table tr:hover td { background: #fdfcfa; }
    .admin-table .td-muted { color: var(--muted); font-size: 0.78rem; }

    /* ── Status badges ────────────────────────────────────── */
    .badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border-radius: 2px;
        white-space: nowrap;
    }
    .badge-pending    { background:#fef3d0;color:#b7791f; }
    .badge-confirmed  { background:#dbeafe;color:#1d4ed8; }
    .badge-processing { background:#ede9fe;color:#6d28d9; }
    .badge-shipped    { background:#d1fae5;color:#065f46; }
    .badge-delivered  { background:#d4f0e0;color:var(--success); }
    .badge-cancelled  { background:#fde8e8;color:var(--error); }
    .badge-refunded   { background:#f3f4f6;color:#6b7280; }
    .badge-active     { background:#d4f0e0;color:var(--success); }
    .badge-inactive   { background:#fde8e8;color:var(--error); }
    .badge-success    { background:#d4f0e0;color:var(--success); }
    .badge-warning    { background:#fef3d0;color:var(--warning); }
    .badge-info       { background:#dbeafe;color:var(--info); }

    /* ── Form elements ────────────────────────────────────── */
    .form-group { margin-bottom: 1.1rem; }
    .form-label { display:block; font-size:0.78rem; font-weight:600; color:var(--text); margin-bottom:0.4rem; }
    .form-input {
        width:100%; padding:0.65rem 0.9rem;
        border:1px solid var(--border); background:#fff;
        font-size:0.85rem; color:var(--text);
        outline:none; font-family:'DM Sans',sans-serif;
        transition:border-color 0.15s;
        border-radius: 2px;
    }
    .form-input:focus { border-color:var(--dark); }
    .form-select {
        width:100%; padding:0.65rem 0.9rem;
        border:1px solid var(--border); background:#fff;
        font-size:0.85rem; color:var(--text);
        outline:none; cursor:pointer;
        font-family:'DM Sans',sans-serif;
        appearance:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%233a3530' d='M5 7L0 2h10z'/%3E%3C/svg%3E");
        background-repeat:no-repeat;
        background-position:right 0.75rem center;
        border-radius: 2px;
    }

    /* ── Buttons ──────────────────────────────────────────── */
    .btn {
        display:inline-flex; align-items:center; justify-content:center;
        gap:0.45rem; padding:0.6rem 1.3rem;
        font-size:0.82rem; font-weight:500;
        border:none; cursor:pointer;
        font-family:'DM Sans',sans-serif;
        transition:all 0.15s; text-decoration:none;
        white-space:nowrap;
    }
    .btn-sm { padding:0.4rem 0.9rem; font-size:0.75rem; }
    .btn-dark   { background:var(--dark);    color:#fff; }
    .btn-dark:hover { background:var(--dark-2); }
    .btn-accent { background:var(--accent);  color:var(--dark); font-weight:600; }
    .btn-outline { background:transparent; color:var(--dark); border:1.5px solid var(--dark); }
    .btn-outline:hover { background:var(--dark); color:#fff; }
    .btn-danger { background:var(--error);   color:#fff; }
    .btn-success { background:var(--success); color:#fff; }
    .btn-warning { background:#b7791f; color:#fff; }

    /* ── Search / filter row ──────────────────────────────── */
    .filter-row {
        display:flex; align-items:center;
        gap:0.75rem; flex-wrap:wrap;
        margin-bottom:1.2rem;
    }
    .search-input-wrap { position:relative; flex:1; min-width:200px; }
    .search-input-wrap i {
        position:absolute; left:0.8rem; top:50%;
        transform:translateY(-50%);
        color:var(--muted); font-size:0.8rem;
        pointer-events:none;
    }
    .search-input-wrap input { padding-left:2.3rem; }

    /* ── Pagination ───────────────────────────────────────── */
    .pagination {
        display:flex; align-items:center;
        justify-content:center; gap:0.3rem;
        padding:1.2rem 0 0;
    }
    .page-btn {
        width:34px; height:34px;
        display:flex; align-items:center; justify-content:center;
        border:1px solid var(--border); background:#fff;
        font-size:0.8rem; cursor:pointer;
        transition:all 0.15s; text-decoration:none;
        color:var(--text);
    }
    .page-btn:hover, .page-btn.active { background:var(--dark); color:#fff; border-color:var(--dark); }

    /* ── Modal ────────────────────────────────────────────── */
    .modal-overlay {
        position:fixed; inset:0;
        background:rgba(0,0,0,0.5);
        z-index:3000;
        display:none; align-items:center; justify-content:center;
        padding:1rem;
    }
    .modal-overlay.show { display:flex; }
    .modal {
        background:#fff; width:100%;
        max-width:540px; max-height:90vh;
        overflow-y:auto;
    }
    .modal-lg { max-width:720px; }
    .modal-header {
        display:flex; align-items:center; justify-content:space-between;
        padding:1rem 1.4rem;
        border-bottom:1px solid var(--border);
        background:var(--light);
        position:sticky; top:0; z-index:1;
    }
    .modal-title { font-size:0.78rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; }
    .modal-close { background:none; border:none; font-size:1.1rem; cursor:pointer; color:var(--muted); }
    .modal-close:hover { color:var(--dark); }
    .modal-body { padding:1.4rem; }
    .modal-footer {
        padding:1rem 1.4rem; border-top:1px solid var(--border);
        background:var(--light);
        display:flex; justify-content:flex-end; gap:0.6rem;
        position:sticky; bottom:0;
    }
    .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }

    /* ── Toast ────────────────────────────────────────────── */
    #toast-container {
        position:fixed; bottom:1.5rem; right:1.5rem;
        z-index:9999; display:flex; flex-direction:column; gap:0.6rem;
    }
    .toast {
        min-width:240px; padding:0.85rem 1.2rem;
        background:var(--dark); color:#fff;
        font-size:0.82rem;
        display:flex; align-items:center; gap:0.65rem;
        box-shadow:0 8px 24px rgba(0,0,0,0.15);
        animation:slideIn 0.3s ease;
        border-left:3px solid var(--accent);
    }
    .toast.success { border-left-color:var(--success); }
    .toast.error   { border-left-color:var(--error); }
    .toast.fade-out { animation:fadeOut 0.3s ease forwards; }
    @keyframes slideIn { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }
    @keyframes fadeOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(20px)} }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2,1fr); }
    }
    @media (max-width: 768px) {
        .admin-sidebar { transform:translateX(-100%); }
        .admin-sidebar.open { transform:translateX(0); }
        .admin-main { margin-left:0; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .admin-content { padding:1rem; }
    }
    </style>
</head>
<body>

<div id="toast-container"></div>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><?= strtoupper(substr(SITE_NAME,0,1)) ?></div>
        <div>
            <div class="logo-text"><?= SITE_NAME ?></div>
            <div class="logo-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="index.php"    class="nav-item <?= $admin_page==='index'   ?'active':'' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <div class="nav-section-label">Store</div>
        <a href="products.php" class="nav-item <?= $admin_page==='products'?'active':'' ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="orders.php"   class="nav-item <?= $admin_page==='orders'  ?'active':'' ?>">
            <i class="fas fa-shopping-bag"></i> Orders
        </a>
        <a href="users.php"    class="nav-item <?= $admin_page==='users'   ?'active':'' ?>">
            <i class="fas fa-users"></i> Customers
        </a>
        <a href="coupons.php"  class="nav-item <?= $admin_page==='coupons' ?'active':'' ?>">
            <i class="fas fa-tag"></i> Coupons
        </a>
        <a href="reviews.php"  class="nav-item <?= $admin_page==='reviews' ?'active':'' ?>">
            <i class="fas fa-star"></i> Reviews
        </a>

        <div class="nav-section-label">Site</div>
        <a href="../index.php" class="nav-item" target="_blank">
            <i class="fas fa-external-link-alt"></i> View Store
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../api/auth/logout.php" onclick="doLogout(event)">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        <div><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
    </div>
</aside>

<!-- ── Main ───────────────────────────────────────────────── -->
<div class="admin-main">
    <header class="admin-topbar">
        <div class="topbar-left">
            <button onclick="toggleSidebar()"
                    style="background:none;border:none;font-size:1.1rem;color:var(--muted);cursor:pointer;display:none"
                    id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <span class="page-title-bar"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></span>
        </div>
        <div class="topbar-right">
            <a href="../pages/cart.php" class="topbar-btn" title="View store">
                <i class="fas fa-store"></i>
            </a>
            <div class="admin-user">
                <div class="admin-avatar">
                    <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                </div>
                <?= htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]) ?>
            </div>
        </div>
    </header>

    <div class="admin-content">