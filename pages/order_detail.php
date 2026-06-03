<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php?redirect=account.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) { header('Location: account.php'); exit; }

// ── Fetch order ────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.*, c.code AS coupon_code
    FROM orders o
    LEFT JOIN coupons c ON c.coupon_id = o.coupon_id
    WHERE o.order_id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$order_id, current_user_id()]);
$order = $stmt->fetch();
if (!$order) { header('Location: account.php'); exit; }

// ── Items ──────────────────────────────────────────────────
$items = $pdo->prepare("
    SELECT oi.*, img.image_url AS image, p.slug AS product_slug
    FROM order_items oi
    LEFT JOIN products p ON p.product_id = oi.product_id
    LEFT JOIN product_images img ON img.product_id = oi.product_id AND img.is_primary = 1
    WHERE oi.order_id = ?
");
$items->execute([$order_id]);
$items = $items->fetchAll();

// ── Payment ────────────────────────────────────────────────
$payment = $pdo->prepare("
    SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1
");
$payment->execute([$order_id]);
$payment = $payment->fetch();

// ── Shipment ───────────────────────────────────────────────
$shipment = $pdo->prepare("
    SELECT * FROM shipments WHERE order_id = ? LIMIT 1
");
$shipment->execute([$order_id]);
$shipment = $shipment->fetch();

// ── Status history ─────────────────────────────────────────
$history = $pdo->prepare("
    SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC
");
$history->execute([$order_id]);
$history = $history->fetchAll();

$status_steps = ['pending','confirmed','processing','shipped','delivered'];
$current_step = array_search($order['status'], $status_steps);
$is_cancelled = in_array($order['status'], ['cancelled','refunded']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= htmlspecialchars($order['order_number']) ?> — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .detail-page { padding: 2rem 0 6rem; }

    /* ── Page header ──────────────────────────────────────── */
    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 2rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .page-header-left h1 {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.5rem, 2.5vw, 2rem);
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.3rem;
    }
    .page-header-left p { color: var(--text-muted); font-size: 0.85rem; }
    .order-status-lg {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1.2rem;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .status-pending    { background:#fef3d0;color:#b7791f; }
    .status-confirmed  { background:#dbeafe;color:#1d4ed8; }
    .status-processing { background:#ede9fe;color:#6d28d9; }
    .status-shipped    { background:#d1fae5;color:#065f46; }
    .status-delivered  { background:#d4f0e0;color:var(--success); }
    .status-cancelled  { background:#fde8e8;color:var(--error); }
    .status-refunded   { background:#f3f4f6;color:#6b7280; }

    /* ── Layout ───────────────────────────────────────────── */
    .detail-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 2rem;
        align-items: start;
    }

    /* ── Progress bar ─────────────────────────────────────── */
    .progress-block {
        background: #fff;
        border: 1px solid var(--border);
        padding: 1.8rem 2rem;
        margin-bottom: 1.5rem;
    }
    .progress-steps {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }
    .progress-steps::before {
        content: '';
        position: absolute;
        top: 18px;
        left: 18px;
        right: 18px;
        height: 2px;
        background: var(--border);
        z-index: 0;
    }
    .progress-fill {
        position: absolute;
        top: 18px;
        left: 18px;
        height: 2px;
        background: var(--success);
        z-index: 1;
        transition: width 0.6s ease;
    }
    .ps-step { display: flex; flex-direction: column; align-items: center; gap: 0.6rem; z-index: 2; }
    .ps-dot {
        width: 36px; height: 36px;
        border-radius: 50%;
        border: 2px solid var(--border);
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: var(--text-muted);
        transition: all 0.3s;
    }
    .ps-dot.done   { background: var(--success); border-color: var(--success); color: #fff; }
    .ps-dot.active { background: var(--accent);  border-color: var(--accent);  color: var(--dark); }
    .ps-dot.cancelled { background: var(--error); border-color: var(--error); color: #fff; }
    .ps-label { font-size: 0.72rem; color: var(--text-muted); text-align: center; white-space: nowrap; }
    .ps-label.done   { color: var(--success); font-weight: 600; }
    .ps-label.active { color: var(--dark);    font-weight: 600; }

    /* ── Items block ──────────────────────────────────────── */
    .items-block {
        background: #fff;
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }
    .block-title-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.4rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
    }
    .item-row {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        padding: 1.1rem 1.4rem;
        border-bottom: 1px solid var(--border);
    }
    .item-row:last-child { border-bottom: none; }
    .item-img {
        width: 64px; height: 80px;
        object-fit: cover;
        background: var(--light);
        border: 1px solid var(--border);
        flex-shrink: 0;
    }
    .item-img-placeholder {
        width: 64px; height: 80px;
        background: var(--light);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--border);
        flex-shrink: 0;
    }
    .item-name {
        font-weight: 500;
        font-size: 0.88rem;
        color: var(--dark);
        margin-bottom: 0.2rem;
        text-decoration: none;
        display: block;
    }
    .item-name:hover { color: var(--accent); }
    .item-variant { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.15rem; }
    .item-qty-price { font-size: 0.78rem; color: var(--text-muted); }
    .item-total {
        margin-left: auto;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--dark);
        white-space: nowrap;
    }

    /* Review button on delivered items */
    .btn-review {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.5rem;
        padding: 0.3rem 0.75rem;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--accent);
        border: 1px solid var(--accent);
        background: none;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        transition: background 0.15s, color 0.15s;
    }
    .btn-review:hover { background: var(--accent); color: var(--dark); }

    /* ── Totals ───────────────────────────────────────────── */
    .totals-block {
        background: var(--light);
        padding: 1.2rem 1.4rem;
        border-top: 1px solid var(--border);
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        margin-bottom: 0.6rem;
        color: var(--text);
    }
    .total-row .lbl { color: var(--text-muted); }
    .total-row.grand {
        font-size: 1rem;
        font-weight: 700;
        color: var(--dark);
        padding-top: 0.8rem;
        border-top: 1px solid var(--border);
        margin-bottom: 0;
    }

    /* ── Sidebar cards ────────────────────────────────────── */
    .sidebar-card {
        background: #fff;
        border: 1px solid var(--border);
        margin-bottom: 1rem;
    }
    .sidebar-card-title {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
        padding: 0.9rem 1.2rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .sidebar-card-title i { color: var(--accent); }
    .sidebar-card-body {
        padding: 1.1rem 1.2rem;
        font-size: 0.85rem;
        color: var(--text);
        line-height: 1.7;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.6rem;
        gap: 1rem;
    }
    .info-row:last-child { margin-bottom: 0; }
    .info-lbl { color: var(--text-muted); font-size: 0.78rem; flex-shrink: 0; }
    .info-val { font-weight: 500; color: var(--dark); font-size: 0.82rem; text-align: right; }

    /* ── Timeline ─────────────────────────────────────────── */
    .timeline { padding: 1.1rem 1.2rem; }
    .tl-item {
        display: flex;
        gap: 0.9rem;
        position: relative;
        padding-bottom: 1.2rem;
    }
    .tl-item:last-child { padding-bottom: 0; }
    .tl-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 11px; top: 26px;
        width: 2px;
        height: calc(100% - 14px);
        background: var(--border);
    }
    .tl-dot {
        width: 24px; height: 24px;
        border-radius: 50%;
        background: var(--success);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        flex-shrink: 0;
        z-index: 1;
    }
    .tl-dot.cancelled { background: var(--error); }
    .tl-status { font-size: 0.82rem; font-weight: 600; color: var(--dark); text-transform: capitalize; }
    .tl-comment { font-size: 0.75rem; color: var(--text-muted); }
    .tl-date { font-size: 0.72rem; color: var(--text-muted); }

    /* ── Tracking ─────────────────────────────────────────── */
    .tracking-info {
        background: var(--light);
        border: 1px solid var(--border);
        padding: 1rem 1.2rem;
        margin-bottom: 1rem;
        font-size: 0.85rem;
    }
    .tracking-carrier { font-weight: 600; color: var(--dark); margin-bottom: 0.3rem; }
    .tracking-num {
        font-family: monospace;
        font-size: 0.88rem;
        color: var(--dark);
        background: #fff;
        border: 1px solid var(--border);
        padding: 0.3rem 0.6rem;
        display: inline-block;
        margin-bottom: 0.5rem;
        letter-spacing: 0.05em;
    }

    /* ── Action bar ───────────────────────────────────────── */
    .action-bar {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
        .detail-layout { grid-template-columns: 1fr; }
        .progress-steps::before { display: none; }
        .progress-fill { display: none; }
        .progress-steps { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .ps-step { flex-direction: row; gap: 0.75rem; }
    }
    @media (max-width: 600px) {
        .page-header { flex-direction: column; }
        .item-row { flex-wrap: wrap; }
        .item-total { margin-left: 0; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container detail-page">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a><span class="breadcrumb-sep">/</span>
        <a href="account.php">My Account</a><span class="breadcrumb-sep">/</span>
        <span><?= htmlspecialchars($order['order_number']) ?></span>
    </div>

    <!-- Page header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Order <?= htmlspecialchars($order['order_number']) ?></h1>
            <p>Placed on <?= date('d M Y \a\t h:i A', strtotime($order['created_at'])) ?></p>
        </div>
        <span class="order-status-lg status-<?= $order['status'] ?>">
            <i class="fas fa-circle" style="font-size:0.5rem"></i>
            <?= ucfirst($order['status']) ?>
        </span>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <a href="account.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
        <?php if (in_array($order['status'], ['pending','confirmed'])): ?>
        <button class="btn btn-sm"
                style="background:#fde8e8;color:var(--error);border:1px solid #f5c6c6"
                onclick="cancelOrder()">
            <i class="fas fa-times"></i> Cancel Order
        </button>
        <?php endif; ?>
        <?php if ($order['status'] === 'shipped' && $shipment && $shipment['tracking_url']): ?>
        <a href="<?= htmlspecialchars($shipment['tracking_url']) ?>"
           target="_blank" class="btn btn-dark btn-sm">
            <i class="fas fa-truck"></i> Track Shipment
        </a>
        <?php endif; ?>
        <button class="btn btn-outline btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Print Invoice
        </button>
    </div>

    <div class="detail-layout">

        <!-- ── Left column ──────────────────────────────────── -->
        <div>

            <!-- Progress bar -->
            <?php if (!$is_cancelled): ?>
            <div class="progress-block">
                <?php
                $step_icons  = ['fa-clock','fa-check-circle','fa-cog','fa-truck','fa-home'];
                $step_labels = ['Placed','Confirmed','Processing','Shipped','Delivered'];
                $fill_pct    = $current_step !== false
                    ? round(($current_step / (count($status_steps) - 1)) * 100) . '%'
                    : '0%';
                ?>
                <div class="progress-steps" id="progressSteps">
                    <div class="progress-fill" style="width:<?= $fill_pct ?>"></div>
                    <?php foreach ($status_steps as $i => $step): ?>
                    <?php
                    $cls = '';
                    if ($current_step !== false) {
                        if ($i < $current_step)      $cls = 'done';
                        elseif ($i === $current_step) $cls = 'active';
                    }
                    ?>
                    <div class="ps-step">
                        <div class="ps-dot <?= $cls ?>">
                            <i class="fas <?= $step_icons[$i] ?>"></i>
                        </div>
                        <span class="ps-label <?= $cls ?>"><?= $step_labels[$i] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="progress-block" style="display:flex;align-items:center;gap:1rem;background:#fde8e8;border-color:#f5c6c6">
                <div class="ps-dot cancelled" style="width:36px;height:36px;flex-shrink:0">
                    <i class="fas fa-times"></i>
                </div>
                <div>
                    <div style="font-weight:600;color:var(--error)">Order <?= ucfirst($order['status']) ?></div>
                    <div style="font-size:0.82rem;color:var(--text-muted)">
                        This order has been <?= $order['status'] ?>.
                        <?php if ($order['status'] === 'refunded'): ?>
                            Your refund will be processed within 5–7 business days.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tracking info (if shipped) -->
            <?php if ($shipment && $shipment['tracking_number']): ?>
            <div class="tracking-info">
                <div class="tracking-carrier">
                    <i class="fas fa-shipping-fast" style="color:var(--accent)"></i>
                    <?= htmlspecialchars($shipment['carrier'] ?? 'Courier') ?>
                </div>
                <div>Tracking Number:</div>
                <div class="tracking-num"><?= htmlspecialchars($shipment['tracking_number']) ?></div>
                <?php if ($shipment['estimated_delivery']): ?>
                <div style="font-size:0.8rem;color:var(--text-muted)">
                    Estimated delivery: <strong><?= date('d M Y', strtotime($shipment['estimated_delivery'])) ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($shipment['tracking_url']): ?>
                <a href="<?= htmlspecialchars($shipment['tracking_url']) ?>"
                   target="_blank"
                   style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.6rem;font-size:0.8rem;color:var(--dark);font-weight:600;text-decoration:underline">
                    <i class="fas fa-external-link-alt"></i> Track on carrier website
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Order items -->
            <div class="items-block">
                <div class="block-title-bar">
                    <span>Order Items</span>
                    <span><?= count($items) ?> item<?= count($items)!==1?'s':'' ?></span>
                </div>

                <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <?php if ($item['image']): ?>
                        <img class="item-img"
                             src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($item['image']) ?>"
                             alt="<?= htmlspecialchars($item['product_name']) ?>">
                    <?php else: ?>
                        <div class="item-img-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1;min-width:0">
                        <?php if ($item['product_slug']): ?>
                        <a class="item-name"
                           href="product.php?slug=<?= htmlspecialchars($item['product_slug']) ?>">
                            <?= htmlspecialchars($item['product_name']) ?>
                        </a>
                        <?php else: ?>
                        <span class="item-name"><?= htmlspecialchars($item['product_name']) ?></span>
                        <?php endif; ?>

                        <?php if ($item['variant_info']): ?>
                        <div class="item-variant"><?= htmlspecialchars($item['variant_info']) ?></div>
                        <?php endif; ?>
                        <div class="item-qty-price">
                            Qty: <?= $item['quantity'] ?> ×
                            <?= CURRENCY_SYMBOL ?><?= number_format($item['unit_price'], 2) ?>
                        </div>
                        <?php if ($order['status'] === 'delivered'): ?>
                        <button class="btn-review"
                                onclick="openReview(<?= $item['product_id'] ?>, '<?= htmlspecialchars(addslashes($item['product_name'])) ?>')">
                            <i class="fas fa-star"></i> Write a Review
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="item-total">
                        <?= CURRENCY_SYMBOL ?><?= number_format($item['total_price'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Totals -->
                <div class="totals-block">
                    <div class="total-row">
                        <span class="lbl">Subtotal</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="lbl">Shipping</span>
                        <span>
                            <?php if ((float)$order['shipping_amt'] == 0): ?>
                                <span style="color:var(--success)">Free</span>
                            <?php else: ?>
                                <?= CURRENCY_SYMBOL ?><?= number_format($order['shipping_amt'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="total-row">
                        <span class="lbl">Tax (GST 18%)</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['tax_amt'], 2) ?></span>
                    </div>
                    <?php if ((float)$order['discount_amt'] > 0): ?>
                    <div class="total-row" style="color:#c0392b">
                        <span>
                            Discount<?= $order['coupon_code'] ? ' ('.$order['coupon_code'].')' : '' ?>
                        </span>
                        <span>− <?= CURRENCY_SYMBOL ?><?= number_format($order['discount_amt'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row grand">
                        <span>Grand Total</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['total_amt'], 2) ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Right column (sidebar) ───────────────────────── -->
        <div>

            <!-- Shipping address -->
            <div class="sidebar-card">
                <div class="sidebar-card-title">
                    <i class="fas fa-map-marker-alt"></i> Shipping Address
                </div>
                <div class="sidebar-card-body">
                    <strong><?= htmlspecialchars($order['shipping_name']) ?></strong><br>
                    <?= htmlspecialchars($order['shipping_addr1']) ?>
                    <?php if ($order['shipping_addr2']): ?>
                        , <?= htmlspecialchars($order['shipping_addr2']) ?>
                    <?php endif; ?><br>
                    <?= htmlspecialchars($order['shipping_city']) ?>,
                    <?= htmlspecialchars($order['shipping_state']) ?>
                    — <?= htmlspecialchars($order['shipping_postal']) ?><br>
                    <?= htmlspecialchars($order['shipping_country']) ?>
                    <?php if ($order['shipping_phone']): ?>
                    <br><br>
                    <i class="fas fa-phone fa-xs" style="color:var(--text-muted)"></i>
                    <?= htmlspecialchars($order['shipping_phone']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment info -->
            <div class="sidebar-card">
                <div class="sidebar-card-title">
                    <i class="fas fa-credit-card"></i> Payment Details
                </div>
                <div class="sidebar-card-body">
                    <div class="info-row">
                        <span class="info-lbl">Method</span>
                        <span class="info-val"><?= ucfirst(htmlspecialchars($payment['payment_method'] ?? 'N/A')) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Status</span>
                        <span class="info-val">
                            <?php
                            $ps = $payment['status'] ?? 'pending';
                            $ps_color = match($ps) {
                                'completed' => 'var(--success)',
                                'failed'    => 'var(--error)',
                                'refunded'  => '#6b7280',
                                default     => '#b7791f'
                            };
                            ?>
                            <span style="color:<?= $ps_color ?>;font-weight:700">
                                <?= ucfirst($ps) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Amount</span>
                        <span class="info-val">
                            <?= CURRENCY_SYMBOL ?><?= number_format($order['total_amt'], 2) ?>
                        </span>
                    </div>
                    <?php if ($payment && $payment['transaction_id']): ?>
                    <div class="info-row">
                        <span class="info-lbl">Txn ID</span>
                        <span class="info-val" style="font-family:monospace;font-size:0.75rem">
                            <?= htmlspecialchars($payment['transaction_id']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($payment && $payment['paid_at']): ?>
                    <div class="info-row">
                        <span class="info-lbl">Paid on</span>
                        <span class="info-val"><?= date('d M Y', strtotime($payment['paid_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status history timeline -->
            <div class="sidebar-card">
                <div class="sidebar-card-title">
                    <i class="fas fa-history"></i> Order Timeline
                </div>
                <div class="timeline">
                    <?php foreach (array_reverse($history) as $h): ?>
                    <div class="tl-item">
                        <div class="tl-dot <?= $h['status']==='cancelled'||$h['status']==='refunded'?'cancelled':'' ?>">
                            <i class="fas fa-check" style="font-size:0.55rem"></i>
                        </div>
                        <div>
                            <div class="tl-status"><?= ucfirst(htmlspecialchars($h['status'])) ?></div>
                            <?php if ($h['comment']): ?>
                            <div class="tl-comment"><?= htmlspecialchars($h['comment']) ?></div>
                            <?php endif; ?>
                            <div class="tl-date"><?= date('d M Y, h:i A', strtotime($h['changed_at'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Need help -->
            <div class="sidebar-card">
                <div class="sidebar-card-title">
                    <i class="fas fa-headset"></i> Need Help?
                </div>
                <div class="sidebar-card-body">
                    <p style="margin-bottom:0.75rem;color:var(--text-muted)">
                        Having an issue with this order?
                    </p>
                    <a href="mailto:<?= SITE_EMAIL ?>?subject=Order <?= urlencode($order['order_number']) ?>"
                       style="display:flex;align-items:center;gap:0.5rem;color:var(--dark);font-weight:600;text-decoration:none;font-size:0.85rem">
                        <i class="fas fa-envelope" style="color:var(--accent)"></i>
                        Email Support
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Quick Review Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="reviewModal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;display:none;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;width:100%;max-width:460px">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);background:var(--light)">
            <span style="font-size:0.75rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase">Write a Review</span>
            <button onclick="closeReview()" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--text-muted)">×</button>
        </div>
        <div style="padding:1.5rem">
            <p id="reviewProductName" style="font-weight:600;margin-bottom:1rem;color:var(--dark)"></p>
            <div style="margin-bottom:1rem">
                <label class="form-label">Rating *</label>
                <div style="display:flex;gap:0.4rem" id="ratingPicker">
                    <?php for($s=1;$s<=5;$s++): ?>
                    <i class="far fa-star" style="font-size:1.6rem;color:var(--border);cursor:pointer"
                       data-val="<?= $s ?>" onclick="pickRating(<?= $s ?>)"></i>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input class="form-input" type="text" id="rvTitle" placeholder="Summarise your experience">
            </div>
            <div class="form-group">
                <label class="form-label">Review</label>
                <textarea class="form-input" id="rvBody" rows="3" placeholder="What did you think?"></textarea>
            </div>
        </div>
        <div style="padding:1rem 1.5rem;border-top:1px solid var(--border);background:var(--light);display:flex;justify-content:flex-end;gap:.75rem">
            <button class="btn btn-outline btn-sm" onclick="closeReview()">Cancel</button>
            <button class="btn btn-dark btn-sm" onclick="submitReview()">Submit Review</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
const ORDER_ID = <?= $order_id ?>;
let reviewProductId = null;
let selectedRating  = 0;

// ── Cancel order ──────────────────────────────────────────
function cancelOrder() {
    if (!confirm('Are you sure you want to cancel this order?')) return;
    fetch('../api/orders/cancel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: ORDER_ID, reason: 'Cancelled by customer.' })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.reload(), 1000);
    });
}

// ── Review modal ──────────────────────────────────────────
function openReview(productId, productName) {
    reviewProductId = productId;
    selectedRating  = 0;
    document.getElementById('reviewProductName').textContent = productName;
    document.getElementById('rvTitle').value = '';
    document.getElementById('rvBody').value  = '';
    pickRating(0);
    document.getElementById('reviewModal').style.display = 'flex';
}
function closeReview() {
    document.getElementById('reviewModal').style.display = 'none';
}
function pickRating(n) {
    selectedRating = n;
    document.querySelectorAll('#ratingPicker i').forEach((s, i) => {
        s.className = (i < n ? 'fas' : 'far') + ' fa-star';
        s.style.color = i < n ? 'var(--accent)' : 'var(--border)';
    });
}
function submitReview() {
    if (!selectedRating) { showToast('Please select a rating.', 'error'); return; }
    fetch('../api/products/review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id : reviewProductId,
            rating     : selectedRating,
            title      : document.getElementById('rvTitle').value,
            body       : document.getElementById('rvBody').value,
        })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) closeReview();
    });
}

// Close modal on outside click
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeReview();
});
</script>
</body>
</html>