<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart_helper.php';

$cart_id = get_or_create_cart($pdo);
$items   = get_cart_items($pdo, $cart_id);
$totals  = calculate_totals($items);
$coupon  = $_SESSION['coupon'] ?? null;
if ($coupon) {
    $totals['discount']    = $coupon['discount_amt'];
    $totals['coupon_code'] = $coupon['code'];
    $totals['total']       = max(0, round($totals['total'] - $coupon['discount_amt'], 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .cart-page { padding: 2rem 0 6rem; }
    .cart-heading {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.8rem, 3vw, 2.4rem);
        font-weight: 700;
        margin-bottom: 0.4rem;
    }
    .cart-sub { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 2.5rem; }

    /* ── Layout ───────────────────────────────────────────── */
    .cart-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 2rem;
        align-items: start;
    }

    /* ── Items table ──────────────────────────────────────── */
    .cart-table-wrap { background: #fff; border: 1px solid var(--border); }
    .cart-table-head {
        display: grid;
        grid-template-columns: 1fr 120px 120px 80px;
        padding: 0.9rem 1.5rem;
        background: var(--light);
        border-bottom: 1px solid var(--border);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .cart-item {
        display: grid;
        grid-template-columns: 1fr 120px 120px 80px;
        align-items: center;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        gap: 1rem;
        transition: background 0.15s;
    }
    .cart-item:last-child { border-bottom: none; }
    .cart-item:hover { background: #fdfcfa; }
    .item-info { display: flex; align-items: center; gap: 1rem; }
    .item-img {
        width: 72px; height: 90px;
        object-fit: cover;
        background: var(--light);
        flex-shrink: 0;
        border: 1px solid var(--border);
    }
    .item-img-placeholder {
        width: 72px; height: 90px;
        background: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--border);
        font-size: 1.2rem;
        border: 1px solid var(--border);
        flex-shrink: 0;
    }
    .item-name {
        font-weight: 500;
        font-size: 0.92rem;
        color: var(--dark);
        margin-bottom: 0.2rem;
        text-decoration: none;
        display: block;
        transition: color 0.15s;
    }
    .item-name:hover { color: var(--accent); }
    .item-variant { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.4rem; }
    .item-remove {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 0.75rem;
        cursor: pointer;
        padding: 0;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        transition: color 0.15s;
    }
    .item-remove:hover { color: var(--error); }

    /* Qty control inline */
    .item-qty {
        display: flex;
        align-items: center;
        border: 1px solid var(--border);
        width: fit-content;
    }
    .iqty-btn {
        width: 30px; height: 34px;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 0.75rem;
        transition: color 0.15s, background 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .iqty-btn:hover { color: var(--dark); background: var(--light); }
    .iqty-val {
        width: 36px;
        text-align: center;
        font-size: 0.88rem;
        font-weight: 500;
        border-left: 1px solid var(--border);
        border-right: 1px solid var(--border);
        padding: 0.4rem 0;
        color: var(--dark);
        font-family: 'DM Sans', sans-serif;
        background: none;
        outline: none;
        border-top: none;
        border-bottom: none;
    }
    .item-price {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    .item-total {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--dark);
        text-align: right;
    }

    /* ── Cart footer ──────────────────────────────────────── */
    .cart-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem 1.5rem;
        background: var(--light);
        border-top: 1px solid var(--border);
        gap: 1rem;
        flex-wrap: wrap;
    }
    .btn-clear {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 0.82rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-family: 'DM Sans', sans-serif;
        transition: color 0.15s;
    }
    .btn-clear:hover { color: var(--error); }
    .btn-continue {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--dark);
        font-size: 0.82rem;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s;
    }
    .btn-continue:hover { color: var(--accent); }

    /* ── Order summary ────────────────────────────────────── */
    .summary-box {
        background: #fff;
        border: 1px solid var(--border);
        position: sticky;
        top: 120px;
    }
    .summary-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid var(--border);
        color: var(--dark);
    }
    .summary-body { padding: 1.5rem; }
    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.88rem;
        margin-bottom: 0.85rem;
        color: var(--text);
    }
    .summary-row.total {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--dark);
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
        margin-bottom: 0;
    }
    .summary-row.saving { color: #c0392b; font-weight: 500; }
    .summary-label { color: var(--text-muted); }
    .free-ship-bar {
        background: var(--light);
        border: 1px solid var(--border);
        padding: 0.8rem 1rem;
        margin-bottom: 1.2rem;
        font-size: 0.8rem;
    }
    .free-ship-track {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }
    .free-ship-fill {
        height: 100%;
        background: var(--success);
        border-radius: 2px;
        transition: width 0.4s ease;
    }

    /* ── Coupon ───────────────────────────────────────────── */
    .coupon-section { margin-bottom: 1.2rem; }
    .coupon-toggle {
        background: none;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        color: var(--dark);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0;
        margin-bottom: 0.7rem;
        text-decoration: underline;
    }
    .coupon-form {
        display: flex;
        gap: 0;
        display: none;
    }
    .coupon-form.show { display: flex; }
    .coupon-input {
        flex: 1;
        padding: 0.65rem 0.9rem;
        border: 1px solid var(--border);
        border-right: none;
        font-size: 0.85rem;
        font-family: 'DM Sans', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        outline: none;
        background: var(--light);
    }
    .coupon-input:focus { border-color: var(--dark); background: #fff; }
    .coupon-apply {
        padding: 0.65rem 1.1rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-size: 0.82rem;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: opacity 0.2s;
        white-space: nowrap;
    }
    .coupon-apply:hover { opacity: 0.85; }
    .coupon-applied {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #d4f0e0;
        color: var(--success);
        padding: 0.6rem 0.9rem;
        font-size: 0.82rem;
        font-weight: 500;
        margin-bottom: 1.2rem;
    }
    .coupon-remove {
        background: none;
        border: none;
        color: var(--success);
        cursor: pointer;
        font-size: 0.9rem;
    }

    /* ── Checkout button ──────────────────────────────────── */
    .btn-checkout {
        width: 100%;
        padding: 1rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 500;
        letter-spacing: 0.05em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: background 0.2s, transform 0.2s;
        text-decoration: none;
        margin-bottom: 0.8rem;
    }
    .btn-checkout:hover { background: var(--dark-2); transform: translateY(-1px); }
    .btn-checkout:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .secure-note {
        text-align: center;
        font-size: 0.74rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
    }

    /* ── Empty cart ───────────────────────────────────────── */
    .empty-cart {
        text-align: center;
        padding: 6rem 2rem;
        background: #fff;
        border: 1px solid var(--border);
    }
    .empty-cart-icon { font-size: 4rem; color: var(--border); margin-bottom: 1.2rem; }
    .empty-cart-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.6rem;
        margin-bottom: 0.5rem;
    }
    .empty-cart-sub { color: var(--text-muted); margin-bottom: 2rem; font-size: 0.9rem; }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
        .cart-layout { grid-template-columns: 1fr; }
        .summary-box { position: static; }
        .cart-table-head { display: none; }
        .cart-item { grid-template-columns: 1fr auto; grid-template-rows: auto auto; }
        .item-info { grid-column: 1; }
        .item-total { grid-column: 2; grid-row: 1; align-self: start; padding-top: 0.2rem; }
        .item-qty { grid-column: 1; }
        .item-price { display: none; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container cart-page">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a><span class="breadcrumb-sep">/</span>
        <span>Cart</span>
    </div>

    <h1 class="cart-heading">Shopping Cart</h1>
    <p class="cart-sub">
        <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> in your cart
    </p>

    <?php if (empty($items)): ?>
    <!-- ── Empty cart ─────────────────────────────────────── -->
    <div class="empty-cart">
        <div class="empty-cart-icon"><i class="fas fa-shopping-bag"></i></div>
        <h2 class="empty-cart-title">Your cart is empty</h2>
        <p class="empty-cart-sub">Looks like you haven't added anything yet.</p>
        <a href="shop.php" class="btn btn-dark btn-lg">Start Shopping</a>
    </div>

    <?php else: ?>
    <!-- ── Cart layout ────────────────────────────────────── -->
    <div class="cart-layout">

        <!-- Items -->
        <div>
            <div class="cart-table-wrap" id="cartTable">
                <div class="cart-table-head">
                    <span>Product</span>
                    <span>Quantity</span>
                    <span>Price</span>
                    <span style="text-align:right">Total</span>
                </div>

                <div id="cartItems">
                <?php foreach ($items as $item): ?>
                <div class="cart-item" id="item-<?= $item['cart_item_id'] ?>">
                    <!-- Product info -->
                    <div class="item-info">
                        <?php if ($item['image']): ?>
                            <img class="item-img"
                                 src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($item['image']) ?>"
                                 alt="<?= htmlspecialchars($item['product_name']) ?>">
                        <?php else: ?>
                            <div class="item-img-placeholder"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                        <div>
                            <a class="item-name"
                               href="product.php?slug=<?= htmlspecialchars($item['product_slug']) ?>">
                                <?= htmlspecialchars($item['product_name']) ?>
                            </a>
                            <?php if ($item['variant_label']): ?>
                            <div class="item-variant"><?= htmlspecialchars($item['variant_label']) ?></div>
                            <?php endif; ?>
                            <button class="item-remove" onclick="removeItem(<?= $item['cart_item_id'] ?>)">
                                <i class="fas fa-trash-alt"></i> Remove
                            </button>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="item-qty">
                        <button class="iqty-btn" onclick="updateQty(<?= $item['cart_item_id'] ?>, -1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input class="iqty-val"
                               type="number"
                               id="qty-<?= $item['cart_item_id'] ?>"
                               value="<?= $item['quantity'] ?>"
                               min="1"
                               max="<?= $item['available_stock'] ?>"
                               onchange="setQty(<?= $item['cart_item_id'] ?>, this.value)">
                        <button class="iqty-btn" onclick="updateQty(<?= $item['cart_item_id'] ?>, 1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Unit price -->
                    <div class="item-price">
                        <?= CURRENCY_SYMBOL ?><?= number_format($item['unit_price'], 2) ?>
                    </div>

                    <!-- Line total -->
                    <div class="item-total" id="total-<?= $item['cart_item_id'] ?>">
                        <?= CURRENCY_SYMBOL ?><?= number_format($item['line_total'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

                <!-- Cart footer row -->
                <div class="cart-footer">
                    <button class="btn-clear" onclick="clearCart()">
                        <i class="fas fa-trash"></i> Clear Cart
                    </button>
                    <a href="shop.php" class="btn-continue">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>

        <!-- Order summary -->
        <div>
            <div class="summary-box">
                <div class="summary-title">Order Summary</div>
                <div class="summary-body">

                    <!-- Free shipping progress -->
                    <?php
                    $subtotal = $totals['subtotal'];
                    $remaining = max(0, FREE_SHIPPING_ABOVE - $subtotal);
                    $pct = min(100, round($subtotal / FREE_SHIPPING_ABOVE * 100));
                    ?>
                    <div class="free-ship-bar">
                        <?php if ($remaining > 0): ?>
                            <span>Add <strong><?= CURRENCY_SYMBOL ?><?= number_format($remaining, 2) ?></strong> more for free shipping!</span>
                        <?php else: ?>
                            <span>🎉 You've unlocked <strong>free shipping!</strong></span>
                        <?php endif; ?>
                        <div class="free-ship-track">
                            <div class="free-ship-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>

                    <!-- Coupon -->
                    <div class="coupon-section">
                        <?php if ($coupon): ?>
                        <div class="coupon-applied">
                            <span><i class="fas fa-tag"></i> &nbsp;<?= htmlspecialchars($coupon['code']) ?> applied</span>
                            <button class="coupon-remove" onclick="removeCoupon()" title="Remove">✕</button>
                        </div>
                        <?php else: ?>
                        <button class="coupon-toggle" onclick="toggleCoupon()">
                            <i class="fas fa-tag"></i> Have a coupon code?
                        </button>
                        <div class="coupon-form" id="couponForm">
                            <input class="coupon-input" id="couponInput"
                                   type="text" placeholder="Enter code">
                            <button class="coupon-apply" onclick="applyCoupon()">Apply</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Totals -->
                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span id="summarySubtotal"><?= CURRENCY_SYMBOL ?><?= number_format($totals['subtotal'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Shipping</span>
                        <span id="summaryShipping">
                            <?php if ($totals['shipping'] == 0): ?>
                                <span style="color:var(--success)">Free</span>
                            <?php else: ?>
                                <?= CURRENCY_SYMBOL ?><?= number_format($totals['shipping'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Tax (GST 18%)</span>
                        <span id="summaryTax"><?= CURRENCY_SYMBOL ?><?= number_format($totals['tax'], 2) ?></span>
                    </div>
                    <?php if ($coupon): ?>
                    <div class="summary-row saving">
                        <span>Discount (<?= htmlspecialchars($coupon['code']) ?>)</span>
                        <span>− <?= CURRENCY_SYMBOL ?><?= number_format($coupon['discount_amt'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="summaryTotal"><?= CURRENCY_SYMBOL ?><?= number_format($totals['total'], 2) ?></span>
                    </div>

                    <div style="height:1.5rem"></div>

                    <?php if (is_logged_in()): ?>
                        <a href="checkout.php" class="btn-checkout">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <a href="login.php?redirect=cart.php" class="btn-checkout">
                            <i class="fas fa-user"></i> Login to Checkout
                        </a>
                    <?php endif; ?>

                    <div class="secure-note">
                        <i class="fas fa-shield-alt"></i>
                        Secure & encrypted checkout
                    </div>
                </div>
            </div>

            <!-- Accepted payments -->
            <div style="display:flex;justify-content:center;gap:1rem;margin-top:1rem;font-size:1.8rem;color:#bbb">
                <i class="fab fa-cc-visa" title="Visa"></i>
                <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                <i class="fab fa-cc-paypal" title="PayPal"></i>
                <i class="fab fa-google-pay" title="Google Pay"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
const SYM = '<?= CURRENCY_SYMBOL ?>';

// ── Update quantity via +/- buttons ───────────────────────
function updateQty(itemId, delta) {
    const input = document.getElementById('qty-' + itemId);
    const newQty = Math.max(1, parseInt(input.value) + delta);
    input.value = newQty;
    patchQty(itemId, newQty);
}

// ── Update quantity via direct input ──────────────────────
function setQty(itemId, val) {
    const qty = Math.max(1, parseInt(val) || 1);
    patchQty(itemId, qty);
}

// ── AJAX call to update API ───────────────────────────────
function patchQty(itemId, qty) {
    fetch('../api/cart/update.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_item_id: itemId, quantity: qty })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            refreshSummary(d.data);
            // Update line total for this item
            const item = d.data.items.find(i => i.cart_item_id == itemId);
            if (item) {
                document.getElementById('total-' + itemId).textContent =
                    SYM + parseFloat(item.line_total).toFixed(2);
            }
        } else {
            showToast(d.message, 'error');
            location.reload();
        }
    });
}

// ── Remove single item ────────────────────────────────────
function removeItem(itemId) {
    fetch('../api/cart/remove.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart_item_id: itemId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('item-' + itemId)?.remove();
            refreshSummary(d.data);
            updateCartCount(d.data.count);
            if (d.data.count === 0) location.reload();
            showToast('Item removed.', 'success');
        }
    });
}

// ── Clear entire cart ─────────────────────────────────────
function clearCart() {
    if (!confirm('Remove all items from your cart?')) return;
    fetch('../api/cart/clear.php', { method: 'DELETE' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                updateCartCount(0);
                location.reload();
            }
        });
}

// ── Coupon toggle ─────────────────────────────────────────
function toggleCoupon() {
    document.getElementById('couponForm').classList.toggle('show');
    document.getElementById('couponInput')?.focus();
}

// ── Apply coupon ──────────────────────────────────────────
function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    if (!code) { showToast('Enter a coupon code.', 'error'); return; }
    fetch('../api/cart/coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) location.reload();
    });
}

// ── Remove coupon ─────────────────────────────────────────
function removeCoupon() {
    fetch('../api/cart/coupon.php', { method: 'DELETE' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Coupon removed.', 'success');
                location.reload();
            }
        });
}

// ── Refresh summary totals ────────────────────────────────
function refreshSummary(data) {
    const t = data.totals;
    const sym = SYM;

    document.getElementById('summarySubtotal').textContent = sym + parseFloat(t.subtotal).toFixed(2);
    document.getElementById('summaryTax').textContent      = sym + parseFloat(t.tax).toFixed(2);
    document.getElementById('summaryShipping').innerHTML   =
        t.shipping == 0
            ? '<span style="color:var(--success)">Free</span>'
            : sym + parseFloat(t.shipping).toFixed(2);

    const disc = t.discount || 0;
    const total = Math.max(0, t.total - disc);
    document.getElementById('summaryTotal').textContent = sym + parseFloat(total).toFixed(2);

    // Free shipping progress bar
    const pct = Math.min(100, Math.round(t.subtotal / <?= FREE_SHIPPING_ABOVE ?> * 100));
    document.querySelector('.free-ship-fill').style.width = pct + '%';
    const remaining = Math.max(0, <?= FREE_SHIPPING_ABOVE ?> - t.subtotal);
    document.querySelector('.free-ship-bar span').innerHTML = remaining > 0
        ? `Add <strong>${sym}${remaining.toFixed(2)}</strong> more for free shipping!`
        : '🎉 You\'ve unlocked <strong>free shipping!</strong>';

    updateCartCount(data.count);
}

// ── Enter key on coupon input ─────────────────────────────
document.getElementById('couponInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') applyCoupon();
});
</script>
</body>
</html>