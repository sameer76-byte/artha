<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$order_id     = (int)($_GET['order_id']     ?? 0);
$order_number = htmlspecialchars(strip_tags($_GET['order_number'] ?? ''));

if (!$order_id) {
    header('Location: ../index.php');
    exit;
}

// ── Fetch order ────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.*, p.payment_method, p.status AS payment_status
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.order_id
    WHERE o.order_id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$order_id, current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ../index.php');
    exit;
}

// ── Fetch order items ──────────────────────────────────────
$items_stmt = $pdo->prepare("
    SELECT oi.*, img.image_url AS image
    FROM order_items oi
    LEFT JOIN product_images img
           ON img.product_id = oi.product_id AND img.is_primary = 1
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Estimated delivery (5–7 business days)
$estimated = date('d M Y', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .success-page { padding: 3rem 0 6rem; }

    /* ── Hero banner ─────────────────────────────────────── */
    .success-hero {
        text-align: center;
        padding: 3.5rem 2rem;
        background: #fff;
        border: 1px solid var(--border);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .success-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent), var(--success), var(--accent));
    }
    .success-icon-wrap {
        width: 80px; height: 80px;
        background: #d4f0e0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    .success-icon-wrap i {
        font-size: 2rem;
        color: var(--success);
    }
    @keyframes popIn {
        0%   { transform: scale(0); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .success-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.8rem, 3vw, 2.6rem);
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }
    .success-sub {
        color: var(--text-muted);
        font-size: 0.92rem;
        margin-bottom: 1.5rem;
    }
    .order-number-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        background: var(--light);
        border: 1px solid var(--border);
        padding: 0.65rem 1.4rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.88rem;
        color: var(--dark);
        margin-bottom: 1.5rem;
    }
    .order-number-badge strong { font-weight: 700; letter-spacing: 0.04em; }
    .success-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    /* ── Info cards row ──────────────────────────────────── */
    .info-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .info-card {
        background: #fff;
        border: 1px solid var(--border);
        padding: 1.3rem 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .info-card-icon {
        width: 40px; height: 40px;
        background: var(--light);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: var(--accent);
        flex-shrink: 0;
    }
    .info-card-label {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.3rem;
    }
    .info-card-value {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--dark);
        line-height: 1.4;
    }
    .info-card-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

    /* ── Main grid ───────────────────────────────────────── */
    .success-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 2rem;
        align-items: start;
    }

    /* ── Order items ─────────────────────────────────────── */
    .items-block {
        background: #fff;
        border: 1px solid var(--border);
    }
    .items-block-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }
    .items-block-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
    }
    .item-count-badge {
        background: var(--dark);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.15rem 0.5rem;
        border-radius: 10px;
    }
    .order-item {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        padding: 1.1rem 1.5rem;
        border-bottom: 1px solid var(--border);
    }
    .order-item:last-child { border-bottom: none; }
    .order-item-img {
        width: 60px; height: 75px;
        object-fit: cover;
        background: var(--light);
        border: 1px solid var(--border);
        flex-shrink: 0;
    }
    .order-item-img-placeholder {
        width: 60px; height: 75px;
        background: var(--light);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--border);
        flex-shrink: 0;
    }
    .order-item-name {
        font-weight: 500;
        font-size: 0.88rem;
        color: var(--dark);
        margin-bottom: 0.2rem;
    }
    .order-item-variant { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; }
    .order-item-qty { font-size: 0.78rem; color: var(--text-muted); }
    .order-item-total {
        margin-left: auto;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--dark);
        white-space: nowrap;
    }

    /* ── Totals summary ──────────────────────────────────── */
    .totals-block {
        border-top: 1px solid var(--border);
        padding: 1.2rem 1.5rem;
        background: var(--light);
    }
    .totals-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: var(--text);
        margin-bottom: 0.6rem;
    }
    .totals-row .label { color: var(--text-muted); }
    .totals-row.grand {
        font-size: 1rem;
        font-weight: 700;
        color: var(--dark);
        padding-top: 0.8rem;
        border-top: 1px solid var(--border);
        margin-bottom: 0;
    }

    /* ── Sidebar ─────────────────────────────────────────── */
    .sidebar-blocks { display: flex; flex-direction: column; gap: 1rem; }
    .detail-block {
        background: #fff;
        border: 1px solid var(--border);
    }
    .detail-block-title {
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
    .detail-block-title i { color: var(--accent); }
    .detail-block-body { padding: 1.1rem 1.2rem; font-size: 0.85rem; line-height: 1.7; }
    .detail-block-body strong { color: var(--dark); }

    /* ── Timeline ────────────────────────────────────────── */
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
        left: 11px; top: 24px;
        width: 2px;
        height: calc(100% - 12px);
        background: var(--border);
    }
    .tl-dot {
        width: 24px; height: 24px;
        border-radius: 50%;
        border: 2px solid var(--border);
        background: #fff;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        color: var(--text-muted);
        z-index: 1;
    }
    .tl-dot.done { background: var(--success); border-color: var(--success); color: #fff; }
    .tl-dot.active { background: var(--accent); border-color: var(--accent); color: var(--dark); }
    .tl-label { font-size: 0.82rem; font-weight: 600; color: var(--dark); }
    .tl-sub { font-size: 0.72rem; color: var(--text-muted); }

    /* ── What's next banner ───────────────────────────────── */
    .whats-next {
        background: var(--dark);
        padding: 2rem 2.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }
    .whats-next-text {
        font-family: 'Playfair Display', serif;
        font-size: 1.3rem;
        color: #fff;
    }
    .whats-next-sub { font-size: 0.82rem; color: rgba(255,255,255,0.5); margin-top: 0.3rem; }
    .btn-shop-more {
        padding: 0.85rem 2rem;
        background: var(--accent);
        color: var(--dark);
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.88rem;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        transition: opacity 0.2s;
        display: inline-block;
    }
    .btn-shop-more:hover { opacity: 0.85; }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
        .info-cards  { grid-template-columns: 1fr 1fr; }
        .success-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .info-cards { grid-template-columns: 1fr; }
        .success-hero { padding: 2.5rem 1.2rem; }
        .whats-next { padding: 1.5rem; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container success-page">

    <!-- ── Success hero ──────────────────────────────────── -->
    <div class="success-hero">
        <div class="success-icon-wrap">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="success-title">Order Placed Successfully!</h1>
        <p class="success-sub">
            Thank you, <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>!
            Your order has been confirmed and is being processed.
        </p>
        <div class="order-number-badge">
            <i class="fas fa-receipt" style="color:var(--accent)"></i>
            Order Number: <strong><?= htmlspecialchars($order['order_number']) ?></strong>
        </div>
        <div class="success-actions">
            <a href="order_detail.php?id=<?= $order_id ?>" class="btn btn-dark">
                <i class="fas fa-eye"></i> View Order Details
            </a>
            <a href="account.php" class="btn btn-outline">
                <i class="fas fa-user"></i> My Account
            </a>
        </div>
    </div>

    <!-- ── Info cards ────────────────────────────────────── -->
    <div class="info-cards">
        <div class="info-card">
            <div class="info-card-icon"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="info-card-label">Order Date</div>
                <div class="info-card-value"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
                <div class="info-card-sub"><?= date('h:i A', strtotime($order['created_at'])) ?></div>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card-icon"><i class="fas fa-truck"></i></div>
            <div>
                <div class="info-card-label">Estimated Delivery</div>
                <div class="info-card-value"><?= $estimated ?></div>
                <div class="info-card-sub">5–7 business days</div>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card-icon"><i class="fas fa-credit-card"></i></div>
            <div>
                <div class="info-card-label">Payment</div>
                <div class="info-card-value"><?= ucfirst(htmlspecialchars($order['payment_method'])) ?></div>
                <div class="info-card-sub">
                    <?php if ($order['payment_method'] === 'cod'): ?>
                        Pay on delivery
                    <?php else: ?>
                        <span style="color:var(--success)">Payment confirmed</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main grid ─────────────────────────────────────── -->
    <div class="success-grid">

        <!-- Items -->
        <div>
            <div class="items-block">
                <div class="items-block-header">
                    <span class="items-block-title">Order Items</span>
                    <span class="item-count-badge"><?= count($items) ?> item<?= count($items)!==1?'s':'' ?></span>
                </div>

                <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <?php if ($item['image']): ?>
                        <img class="order-item-img"
                             src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($item['image']) ?>"
                             alt="<?= htmlspecialchars($item['product_name']) ?>">
                    <?php else: ?>
                        <div class="order-item-img-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:0">
                        <div class="order-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <?php if ($item['variant_info']): ?>
                        <div class="order-item-variant"><?= htmlspecialchars($item['variant_info']) ?></div>
                        <?php endif; ?>
                        <div class="order-item-qty">Qty: <?= $item['quantity'] ?> × <?= CURRENCY_SYMBOL ?><?= number_format($item['unit_price'], 2) ?></div>
                    </div>
                    <div class="order-item-total">
                        <?= CURRENCY_SYMBOL ?><?= number_format($item['total_price'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Totals -->
                <div class="totals-block">
                    <div class="totals-row">
                        <span class="label">Subtotal</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="totals-row">
                        <span class="label">Shipping</span>
                        <span>
                            <?php if ((float)$order['shipping_amt'] == 0): ?>
                                <span style="color:var(--success)">Free</span>
                            <?php else: ?>
                                <?= CURRENCY_SYMBOL ?><?= number_format($order['shipping_amt'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="totals-row">
                        <span class="label">Tax (GST)</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['tax_amt'], 2) ?></span>
                    </div>
                    <?php if ((float)$order['discount_amt'] > 0): ?>
                    <div class="totals-row" style="color:#c0392b">
                        <span>Discount</span>
                        <span>− <?= CURRENCY_SYMBOL ?><?= number_format($order['discount_amt'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="totals-row grand">
                        <span>Grand Total</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($order['total_amt'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar-blocks">

            <!-- Shipping address -->
            <div class="detail-block">
                <div class="detail-block-title">
                    <i class="fas fa-map-marker-alt"></i> Shipping To
                </div>
                <div class="detail-block-body">
                    <strong><?= htmlspecialchars($order['shipping_name']) ?></strong><br>
                    <?= htmlspecialchars($order['shipping_addr1']) ?>
                    <?php if ($order['shipping_addr2']): ?>, <?= htmlspecialchars($order['shipping_addr2']) ?><?php endif; ?><br>
                    <?= htmlspecialchars($order['shipping_city']) ?>,
                    <?= htmlspecialchars($order['shipping_state']) ?> — <?= htmlspecialchars($order['shipping_postal']) ?><br>
                    <?= htmlspecialchars($order['shipping_country']) ?>
                    <?php if ($order['shipping_phone']): ?>
                    <br><i class="fas fa-phone fa-xs" style="color:var(--text-muted)"></i>
                    <?= htmlspecialchars($order['shipping_phone']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order timeline -->
            <div class="detail-block">
                <div class="detail-block-title">
                    <i class="fas fa-route"></i> Order Timeline
                </div>
                <div class="timeline">
                    <div class="tl-item">
                        <div class="tl-dot done"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="tl-label">Order Placed</div>
                            <div class="tl-sub"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="tl-item">
                        <div class="tl-dot active"><i class="fas fa-cog"></i></div>
                        <div>
                            <div class="tl-label">Processing</div>
                            <div class="tl-sub">Being prepared for dispatch</div>
                        </div>
                    </div>
                    <div class="tl-item">
                        <div class="tl-dot"><i class="fas fa-truck"></i></div>
                        <div>
                            <div class="tl-label">Shipped</div>
                            <div class="tl-sub">Awaiting dispatch</div>
                        </div>
                    </div>
                    <div class="tl-item">
                        <div class="tl-dot"><i class="fas fa-home"></i></div>
                        <div>
                            <div class="tl-label">Delivered</div>
                            <div class="tl-sub">Est. <?= $estimated ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Need help -->
            <div class="detail-block">
                <div class="detail-block-title">
                    <i class="fas fa-headset"></i> Need Help?
                </div>
                <div class="detail-block-body">
                    <p style="margin-bottom:0.8rem">Questions about your order? We're here to help.</p>
                    <a href="mailto:<?= SITE_EMAIL ?>"
                       style="display:flex;align-items:center;gap:0.5rem;color:var(--dark);font-weight:500;text-decoration:none;font-size:0.85rem">
                        <i class="fas fa-envelope" style="color:var(--accent)"></i>
                        <?= SITE_EMAIL ?>
                    </a>
                    <div style="margin-top:0.75rem">
                        <a href="orders/track.php?order_number=<?= urlencode($order['order_number']) ?>"
                           style="font-size:0.82rem;color:var(--text-muted);text-decoration:underline">
                            Track this order →
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── What's next banner ─────────────────────────────── -->
    <div class="whats-next">
        <div>
            <div class="whats-next-text">Keep Exploring</div>
            <div class="whats-next-sub">Discover more styles while your order is on its way.</div>
        </div>
        <a href="shop.php" class="btn-shop-more">
            <i class="fas fa-shopping-bag"></i> Continue Shopping
        </a>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Confetti burst on load
window.addEventListener('load', () => {
    const colors = ['#d4af37','#2d7a4f','#1a1612','#e8cc6a'];
    for (let i = 0; i < 60; i++) {
        const dot = document.createElement('div');
        const size = Math.random() * 8 + 4;
        dot.style.cssText = `
            position:fixed;
            width:${size}px;height:${size}px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            left:${Math.random()*100}vw;
            top:-10px;
            border-radius:${Math.random()>0.5?'50%':'2px'};
            z-index:9999;
            pointer-events:none;
            opacity:${Math.random()*0.8+0.2};
            animation:fall ${Math.random()*2+1.5}s ease-in ${Math.random()*0.8}s forwards;
        `;
        document.body.appendChild(dot);
        setTimeout(() => dot.remove(), 4000);
    }
});
</script>
<style>
@keyframes fall {
    0%   { transform: translateY(0) rotate(0deg); opacity: 1; }
    100% { transform: translateY(105vh) rotate(720deg); opacity: 0; }
}
</style>
</body>
</html>