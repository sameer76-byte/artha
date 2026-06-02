<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=account.php');
    exit;
}

$user_id = current_user_id();

// ── Fetch user ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = ? LIMIT 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ── Fetch addresses ────────────────────────────────────────
$addr_stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$addr_stmt->execute([$user_id]);
$addresses = $addr_stmt->fetchAll();

// ── Fetch recent orders ────────────────────────────────────
$ord_stmt = $pdo->prepare("
    SELECT o.order_id, o.order_number, o.status, o.total_amt, o.created_at,
           COUNT(oi.order_item_id) AS item_count,
           p.payment_method
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.order_id
    LEFT JOIN payments p     ON p.order_id  = o.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$ord_stmt->execute([$user_id]);
$orders = $ord_stmt->fetchAll();

// ── Stats ──────────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT
        COUNT(*)                                                AS total_orders,
        COALESCE(SUM(CASE WHEN status='delivered' THEN total_amt END), 0) AS total_spent,
        COALESCE(SUM(CASE WHEN status='pending' OR status='confirmed' OR status='processing' THEN 1 END), 0) AS active_orders
    FROM orders WHERE user_id = ?
");
$stats->execute([$user_id]);
$stats = $stats->fetch();

// ── Wishlist count ─────────────────────────────────────────
$wl_count = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
$wl_count->execute([$user_id]);
$wl_count = (int)$wl_count->fetchColumn();

$active_tab = $_GET['tab'] ?? 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .account-page { padding: 2rem 0 6rem; }

    /* ── Profile hero ─────────────────────────────────────── */
    .profile-hero {
        background: var(--dark);
        padding: 2.5rem;
        display: flex;
        align-items: center;
        gap: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .profile-hero::after {
        content: '';
        position: absolute;
        right: -60px; top: -60px;
        width: 240px; height: 240px;
        background: radial-gradient(circle, rgba(212,175,55,0.15) 0%, transparent 70%);
        pointer-events: none;
    }
    .profile-avatar {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: rgba(212,175,55,0.2);
        border: 2px solid var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent);
        flex-shrink: 0;
    }
    .profile-name {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.2rem;
    }
    .profile-email { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-bottom: 0.5rem; }
    .profile-role {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 0.2rem 0.65rem;
        background: rgba(212,175,55,0.2);
        color: var(--accent);
        border: 1px solid rgba(212,175,55,0.3);
    }
    .profile-stats {
        margin-left: auto;
        display: flex;
        gap: 2.5rem;
        flex-shrink: 0;
    }
    .ps-item { text-align: center; }
    .ps-num {
        font-family: 'Playfair Display', serif;
        font-size: 1.6rem;
        font-weight: 700;
        color: #fff;
        line-height: 1;
    }
    .ps-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.2rem; }

    /* ── Layout ───────────────────────────────────────────── */
    .account-layout {
        display: grid;
        grid-template-columns: 220px 1fr;
        gap: 2rem;
        align-items: start;
    }

    /* ── Sidebar nav ──────────────────────────────────────── */
    .account-nav {
        background: #fff;
        border: 1px solid var(--border);
        position: sticky;
        top: 120px;
    }
    .account-nav-item {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.9rem 1.2rem;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: all 0.15s;
        text-decoration: none;
        border-bottom: 1px solid var(--border);
        background: none;
        width: 100%;
        font-family: 'DM Sans', sans-serif;
        text-align: left;
    }
    .account-nav-item:last-child { border-bottom: none; }
    .account-nav-item:hover { color: var(--dark); background: var(--light); }
    .account-nav-item.active {
        color: var(--dark);
        border-left-color: var(--accent);
        background: var(--light);
        font-weight: 600;
    }
    .account-nav-item i { width: 16px; text-align: center; }
    .nav-badge {
        margin-left: auto;
        background: var(--dark);
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 10px;
    }
    .nav-badge.accent { background: var(--accent); color: var(--dark); }

    /* ── Tab panels ───────────────────────────────────────── */
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Panel header */
    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .panel-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--dark);
    }

    /* ── Orders tab ───────────────────────────────────────── */
    .orders-list { display: flex; flex-direction: column; gap: 1rem; }
    .order-card {
        background: #fff;
        border: 1px solid var(--border);
        transition: border-color 0.2s;
    }
    .order-card:hover { border-color: var(--dark); }
    .order-card-header {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        padding: 1rem 1.3rem;
        border-bottom: 1px solid var(--border);
        gap: 1rem;
        background: var(--light);
    }
    .order-num {
        font-weight: 700;
        font-size: 0.88rem;
        color: var(--dark);
        margin-bottom: 0.15rem;
    }
    .order-meta { font-size: 0.75rem; color: var(--text-muted); }
    .order-status {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 0.3rem 0.75rem;
        border-radius: 2px;
        white-space: nowrap;
    }
    .status-pending    { background: #fef3d0; color: #b7791f; }
    .status-confirmed  { background: #dbeafe; color: #1d4ed8; }
    .status-processing { background: #ede9fe; color: #6d28d9; }
    .status-shipped    { background: #d1fae5; color: #065f46; }
    .status-delivered  { background: #d4f0e0; color: var(--success); }
    .status-cancelled  { background: #fde8e8; color: var(--error); }
    .status-refunded   { background: #f3f4f6; color: #6b7280; }
    .order-card-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.3rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .order-info-row { display: flex; gap: 2rem; }
    .order-detail-item { font-size: 0.82rem; }
    .order-detail-label { color: var(--text-muted); font-size: 0.72rem; margin-bottom: 0.15rem; }
    .order-detail-val { font-weight: 600; color: var(--dark); }
    .order-actions { display: flex; gap: 0.6rem; }
    .btn-sm-action {
        padding: 0.45rem 1rem;
        font-size: 0.78rem;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        border: 1.5px solid var(--border);
        background: #fff;
        color: var(--text);
        text-decoration: none;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .btn-sm-action:hover { border-color: var(--dark); color: var(--dark); }
    .btn-sm-action.primary { background: var(--dark); color: #fff; border-color: var(--dark); }
    .btn-sm-action.primary:hover { background: var(--dark-2); }

    /* ── Profile tab ──────────────────────────────────────── */
    .profile-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }
    .profile-card {
        background: #fff;
        border: 1px solid var(--border);
        padding: 1.8rem;
        margin-bottom: 1.5rem;
    }
    .profile-card-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
        margin-bottom: 1.5rem;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .profile-card-title i { color: var(--accent); }

    /* ── Addresses tab ────────────────────────────────────── */
    .addresses-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .address-card {
        background: #fff;
        border: 1px solid var(--border);
        padding: 1.2rem;
        position: relative;
        transition: border-color 0.2s;
    }
    .address-card:hover { border-color: var(--dark); }
    .address-card.default-card { border-color: var(--accent); }
    .default-badge {
        position: absolute;
        top: 0.7rem; right: 0.7rem;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.15rem 0.5rem;
        background: rgba(212,175,55,0.15);
        color: var(--accent);
        border: 1px solid rgba(212,175,55,0.3);
    }
    .address-label-tag {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .address-name { font-weight: 600; font-size: 0.88rem; color: var(--dark); margin-bottom: 0.3rem; }
    .address-text { font-size: 0.82rem; color: var(--text-muted); line-height: 1.6; }
    .address-phone { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem; }
    .address-card-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.9rem;
        padding-top: 0.9rem;
        border-top: 1px solid var(--border);
    }

    /* Add address card */
    .add-address-card {
        background: #fff;
        border: 1.5px dashed var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 2rem;
        cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
        color: var(--text-muted);
        font-size: 0.85rem;
        min-height: 180px;
        text-align: center;
    }
    .add-address-card:hover { border-color: var(--dark); color: var(--dark); }
    .add-address-card i { font-size: 1.5rem; }

    /* New address modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 3000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .modal-overlay.show { display: flex; }
    .modal {
        background: #fff;
        width: 100%;
        max-width: 520px;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }
    .modal-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.1rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: color 0.15s;
    }
    .modal-close:hover { color: var(--dark); }
    .modal-body { padding: 1.5rem; }
    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        background: var(--light);
    }
    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }

    /* ── Empty state ──────────────────────────────────────── */
    .empty-panel {
        background: #fff;
        border: 1px solid var(--border);
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }
    .empty-panel i { font-size: 2.5rem; opacity: 0.2; margin-bottom: 1rem; display: block; }
    .empty-panel h3 { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--dark); margin-bottom: 0.5rem; }
    .empty-panel p { font-size: 0.88rem; margin-bottom: 1.5rem; }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
        .account-layout { grid-template-columns: 1fr; }
        .account-nav { position: static; display: flex; overflow-x: auto; }
        .account-nav-item { border-left: none; border-bottom: 3px solid transparent; white-space: nowrap; }
        .account-nav-item.active { border-bottom-color: var(--accent); border-left-color: transparent; }
        .profile-hero { flex-wrap: wrap; }
        .profile-stats { margin-left: 0; }
        .addresses-grid { grid-template-columns: 1fr; }
        .profile-form-grid { grid-template-columns: 1fr; }
        .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container account-page">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a>
        <span class="breadcrumb-sep">/</span>
        <span>My Account</span>
    </div>

    <!-- Profile hero -->
    <div class="profile-hero">
        <div class="profile-avatar">
            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
        </div>
        <div>
            <div class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
            <span class="profile-role"><?= htmlspecialchars($user['role_name']) ?></span>
        </div>
        <div class="profile-stats">
            <div class="ps-item">
                <div class="ps-num"><?= (int)$stats['total_orders'] ?></div>
                <div class="ps-label">Orders</div>
            </div>
            <div class="ps-item">
                <div class="ps-num"><?= (int)$stats['active_orders'] ?></div>
                <div class="ps-label">Active</div>
            </div>
            <div class="ps-item">
                <div class="ps-num"><?= $wl_count ?></div>
                <div class="ps-label">Wishlist</div>
            </div>
            <div class="ps-item">
                <div class="ps-num"><?= CURRENCY_SYMBOL ?><?= number_format($stats['total_spent'], 0) ?></div>
                <div class="ps-label">Spent</div>
            </div>
        </div>
    </div>

    <div class="account-layout">

        <!-- ── Sidebar nav ────────────────────────────────── -->
        <nav class="account-nav">
            <button class="account-nav-item <?= $active_tab==='orders'?'active':'' ?>"
                    onclick="switchTab('orders')">
                <i class="fas fa-box"></i> My Orders
                <?php if ($stats['active_orders']): ?>
                <span class="nav-badge accent"><?= (int)$stats['active_orders'] ?></span>
                <?php endif; ?>
            </button>
            <button class="account-nav-item <?= $active_tab==='addresses'?'active':'' ?>"
                    onclick="switchTab('addresses')">
                <i class="fas fa-map-marker-alt"></i> Addresses
                <span class="nav-badge"><?= count($addresses) ?></span>
            </button>
            <button class="account-nav-item <?= $active_tab==='profile'?'active':'' ?>"
                    onclick="switchTab('profile')">
                <i class="fas fa-user"></i> Profile
            </button>
            <button class="account-nav-item <?= $active_tab==='password'?'active':'' ?>"
                    onclick="switchTab('password')">
                <i class="fas fa-lock"></i> Password
            </button>
            <a href="wishlist.php" class="account-nav-item">
                <i class="fas fa-heart"></i> Wishlist
                <?php if ($wl_count): ?>
                <span class="nav-badge"><?= $wl_count ?></span>
                <?php endif; ?>
            </a>
            <button class="account-nav-item" onclick="doLogout()"
                    style="color:var(--error)">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </nav>

        <!-- ── Tab panels ─────────────────────────────────── -->
        <div>

            <!-- ORDERS ──────────────────────────────────── -->
            <div class="tab-panel <?= $active_tab==='orders'?'active':'' ?>" id="tab-orders">
                <div class="panel-header">
                    <h2 class="panel-title">My Orders</h2>
                </div>

                <?php if (empty($orders)): ?>
                <div class="empty-panel">
                    <i class="fas fa-box-open"></i>
                    <h3>No orders yet</h3>
                    <p>You haven't placed any orders. Start shopping!</p>
                    <a href="shop.php" class="btn btn-dark">Shop Now</a>
                </div>
                <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $o):
                        $status_class = 'status-' . $o['status'];
                    ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <div class="order-num"><?= htmlspecialchars($o['order_number']) ?></div>
                                <div class="order-meta">
                                    <?= date('d M Y', strtotime($o['created_at'])) ?>
                                    &nbsp;·&nbsp;
                                    <?= $o['item_count'] ?> item<?= $o['item_count']!=1?'s':'' ?>
                                    &nbsp;·&nbsp;
                                    <?= ucfirst($o['payment_method'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <span class="order-status <?= $status_class ?>">
                                <?= ucfirst($o['status']) ?>
                            </span>
                        </div>
                        <div class="order-card-body">
                            <div class="order-info-row">
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Total</div>
                                    <div class="order-detail-val"><?= CURRENCY_SYMBOL ?><?= number_format($o['total_amt'], 2) ?></div>
                                </div>
                                <div class="order-detail-item">
                                    <div class="order-detail-label">Placed on</div>
                                    <div class="order-detail-val"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="order-actions">
                                <a href="order_detail.php?id=<?= $o['order_id'] ?>"
                                   class="btn-sm-action primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (in_array($o['status'], ['pending','confirmed'])): ?>
                                <button class="btn-sm-action"
                                        onclick="cancelOrder(<?= $o['order_id'] ?>, '<?= htmlspecialchars($o['order_number']) ?>')">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                                <?php if ($o['status'] === 'delivered'): ?>
                                <a href="product.php" class="btn-sm-action">
                                    <i class="fas fa-redo"></i> Reorder
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ADDRESSES ───────────────────────────────── -->
            <div class="tab-panel <?= $active_tab==='addresses'?'active':'' ?>" id="tab-addresses">
                <div class="panel-header">
                    <h2 class="panel-title">Saved Addresses</h2>
                    <button class="btn btn-dark btn-sm" onclick="openAddressModal()">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>

                <div class="addresses-grid" id="addressesGrid">
                    <?php foreach ($addresses as $addr): ?>
                    <div class="address-card <?= $addr['is_default']?'default-card':'' ?>"
                         id="addr-<?= $addr['address_id'] ?>">
                        <?php if ($addr['is_default']): ?>
                        <span class="default-badge">Default</span>
                        <?php endif; ?>
                        <div class="address-label-tag">
                            <i class="fas fa-<?= strtolower($addr['label'])==='work'?'briefcase':'home' ?>"></i>
                            <?= htmlspecialchars($addr['label'] ?? 'Address') ?>
                        </div>
                        <div class="address-name"><?= htmlspecialchars($addr['full_name']) ?></div>
                        <div class="address-text">
                            <?= htmlspecialchars($addr['address_line1']) ?>
                            <?= $addr['address_line2'] ? '<br>'.htmlspecialchars($addr['address_line2']) : '' ?><br>
                            <?= htmlspecialchars($addr['city']) ?>,
                            <?= htmlspecialchars($addr['state']) ?> — <?= htmlspecialchars($addr['postal_code']) ?><br>
                            <?= htmlspecialchars($addr['country']) ?>
                        </div>
                        <?php if ($addr['phone']): ?>
                        <div class="address-phone">
                            <i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($addr['phone']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="address-card-actions">
                            <?php if (!$addr['is_default']): ?>
                            <button class="btn-sm-action"
                                    onclick="setDefaultAddress(<?= $addr['address_id'] ?>)">
                                <i class="fas fa-check"></i> Set Default
                            </button>
                            <?php endif; ?>
                            <button class="btn-sm-action"
                                    style="color:var(--error);border-color:var(--error)"
                                    onclick="deleteAddress(<?= $addr['address_id'] ?>)">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Add new card -->
                    <div class="add-address-card" onclick="openAddressModal()">
                        <i class="fas fa-plus"></i>
                        Add a new address
                    </div>
                </div>
            </div>

            <!-- PROFILE ─────────────────────────────────── -->
            <div class="tab-panel <?= $active_tab==='profile'?'active':'' ?>" id="tab-profile">
                <div class="panel-header">
                    <h2 class="panel-title">Profile Details</h2>
                </div>

                <div class="profile-card">
                    <div class="profile-card-title">
                        <i class="fas fa-user"></i> Personal Information
                    </div>
                    <div class="profile-form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input class="form-input" type="text" id="pFirst"
                                   value="<?= htmlspecialchars($user['first_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input class="form-input" type="text" id="pLast"
                                   value="<?= htmlspecialchars($user['last_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input class="form-input" type="email" id="pEmail"
                                   value="<?= htmlspecialchars($user['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input class="form-input" type="tel" id="pPhone"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="+91 XXXXX XXXXX">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end">
                        <button class="btn btn-dark" onclick="updateProfile()">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>

            <!-- PASSWORD ────────────────────────────────── -->
            <div class="tab-panel <?= $active_tab==='password'?'active':'' ?>" id="tab-password">
                <div class="panel-header">
                    <h2 class="panel-title">Change Password</h2>
                </div>
                <div class="profile-card" style="max-width:460px">
                    <div class="profile-card-title">
                        <i class="fas fa-lock"></i> Update Password
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input class="form-input" type="password" id="pwCurrent" placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input class="form-input" type="password" id="pwNew" placeholder="Min 8 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input class="form-input" type="password" id="pwConfirm" placeholder="Re-enter new password">
                    </div>
                    <div style="display:flex;justify-content:flex-end">
                        <button class="btn btn-dark" onclick="changePassword()">
                            <i class="fas fa-lock"></i> Update Password
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Add Address Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="addressModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New Address</span>
            <button class="modal-close" onclick="closeAddressModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-row-2" style="margin-bottom:1rem">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Full Name *</label>
                    <input class="form-input" id="m_name" type="text" placeholder="Full name">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Phone</label>
                    <input class="form-input" id="m_phone" type="tel" placeholder="+91 XXXXX XXXXX">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address Line 1 *</label>
                <input class="form-input" id="m_addr1" type="text" placeholder="House no., Street">
            </div>
            <div class="form-group">
                <label class="form-label">Address Line 2</label>
                <input class="form-input" id="m_addr2" type="text" placeholder="Landmark (optional)">
            </div>
            <div class="form-row-3" style="margin-bottom:1rem">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">City *</label>
                    <input class="form-input" id="m_city" type="text" placeholder="City">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">State *</label>
                    <input class="form-input" id="m_state" type="text" placeholder="State">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">PIN Code *</label>
                    <input class="form-input" id="m_postal" type="text" placeholder="PIN" maxlength="10">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Label</label>
                <select class="form-select" id="m_label">
                    <option value="Home">Home</option>
                    <option value="Work">Work</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeAddressModal()">Cancel</button>
            <button class="btn btn-dark btn-sm" onclick="saveAddress()">
                <i class="fas fa-save"></i> Save Address
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// ── Tab switching ─────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.account-nav-item').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name)?.classList.add('active');
    event.currentTarget.classList.add('active');
}

// ── Logout ────────────────────────────────────────────────
function doLogout() {
    fetch('../api/auth/logout.php', { method: 'POST' })
        .then(() => window.location.href = '../index.php');
}

// ── Cancel order ──────────────────────────────────────────
function cancelOrder(orderId, orderNum) {
    if (!confirm(`Cancel order ${orderNum}? This cannot be undone.`)) return;
    fetch('../api/orders/cancel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, reason: 'Cancelled by customer.' })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.reload(), 1000);
    });
}

// ── Address modal ─────────────────────────────────────────
function openAddressModal() {
    document.getElementById('addressModal').classList.add('show');
}
function closeAddressModal() {
    document.getElementById('addressModal').classList.remove('show');
    ['m_name','m_phone','m_addr1','m_addr2','m_city','m_state','m_postal'].forEach(id => {
        document.getElementById(id).value = '';
    });
}

// ── Save address ──────────────────────────────────────────
function saveAddress() {
    const body = {
        full_name    : document.getElementById('m_name').value.trim(),
        phone        : document.getElementById('m_phone').value.trim(),
        address_line1: document.getElementById('m_addr1').value.trim(),
        address_line2: document.getElementById('m_addr2').value.trim(),
        city         : document.getElementById('m_city').value.trim(),
        state        : document.getElementById('m_state').value.trim(),
        postal_code  : document.getElementById('m_postal').value.trim(),
        label        : document.getElementById('m_label').value,
    };
    if (!body.full_name || !body.address_line1 || !body.city || !body.state || !body.postal_code) {
        showToast('Please fill in all required fields.', 'error');
        return;
    }
    fetch('../api/user/address.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { closeAddressModal(); location.reload(); }
    });
}

// ── Delete address ────────────────────────────────────────
function deleteAddress(id) {
    if (!confirm('Delete this address?')) return;
    fetch('../api/user/address.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address_id: id })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) document.getElementById('addr-' + id)?.remove();
    });
}

// ── Set default address ────────────────────────────────────
function setDefaultAddress(id) {
    fetch('../api/user/address.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address_id: id, is_default: 1 })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) location.reload();
    });
}

// ── Update profile ────────────────────────────────────────
function updateProfile() {
    fetch('../api/user/profile.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            first_name: document.getElementById('pFirst').value.trim(),
            last_name : document.getElementById('pLast').value.trim(),
            email     : document.getElementById('pEmail').value.trim(),
            phone     : document.getElementById('pPhone').value.trim(),
        })
    })
    .then(r => r.json())
    .then(d => showToast(d.message, d.success ? 'success' : 'error'));
}

// ── Change password ────────────────────────────────────────
function changePassword() {
    const current = document.getElementById('pwCurrent').value;
    const newPw   = document.getElementById('pwNew').value;
    const confirm = document.getElementById('pwConfirm').value;
    if (!current || !newPw) { showToast('Please fill in all fields.', 'error'); return; }
    if (newPw !== confirm)  { showToast('Passwords do not match.', 'error'); return; }
    if (newPw.length < 8)   { showToast('Password must be at least 8 characters.', 'error'); return; }
    fetch('../api/user/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current_password: current, new_password: newPw })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            document.getElementById('pwCurrent').value = '';
            document.getElementById('pwNew').value     = '';
            document.getElementById('pwConfirm').value = '';
        }
    });
}

// Close modal on overlay click
document.getElementById('addressModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddressModal();
});
</script>
</body>
</html>