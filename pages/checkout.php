<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart_helper.php';

// Must be logged in
if (!is_logged_in()) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$user_id = current_user_id();

// ── Load cart ──────────────────────────────────────────────
$cart_id = get_or_create_cart($pdo);
$items   = get_cart_items($pdo, $cart_id);

// Redirect if cart is empty
if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$totals  = calculate_totals($items);
$coupon  = $_SESSION['coupon'] ?? null;
if ($coupon) {
    $totals['discount']    = $coupon['discount_amt'];
    $totals['coupon_code'] = $coupon['code'];
    $totals['total']       = max(0, round($totals['total'] - $coupon['discount_amt'], 2));
}

// ── Load saved addresses ───────────────────────────────────
$addr_stmt = $pdo->prepare("
    SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id ASC
");
$addr_stmt->execute([$user_id]);
$addresses = $addr_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .checkout-page { padding: 2rem 0 6rem; }
    .checkout-heading {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.8rem, 3vw, 2.4rem);
        font-weight: 700;
        margin-bottom: 2.5rem;
    }

    /* ── Steps bar ─────────────────────────────────────────── */
    .steps-bar {
        display: flex;
        align-items: center;
        margin-bottom: 2.5rem;
        gap: 0;
    }
    .step {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-muted);
    }
    .step.active { color: var(--dark); }
    .step.done   { color: var(--success); }
    .step-num {
        width: 26px; height: 26px;
        border-radius: 50%;
        border: 1.5px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .step.active .step-num { background: var(--dark); color: #fff; border-color: var(--dark); }
    .step.done   .step-num { background: var(--success); color: #fff; border-color: var(--success); }
    .step-line {
        flex: 1;
        height: 1px;
        background: var(--border);
        margin: 0 0.75rem;
        max-width: 80px;
    }

    /* ── Layout ────────────────────────────────────────────── */
    .checkout-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 2.5rem;
        align-items: start;
    }

    /* ── Section blocks ────────────────────────────────────── */
    .checkout-block {
        background: #fff;
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }
    .block-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }
    .block-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .block-title i { color: var(--accent); }
    .block-body { padding: 1.5rem; }

    /* ── Address cards ─────────────────────────────────────── */
    .address-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.2rem;
    }
    .address-card {
        border: 1.5px solid var(--border);
        padding: 1rem 1.1rem;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        position: relative;
    }
    .address-card:hover { border-color: var(--dark); }
    .address-card.selected {
        border-color: var(--dark);
        background: #faf8f4;
    }
    .address-card.selected::after {
        content: '✓';
        position: absolute;
        top: 0.6rem; right: 0.7rem;
        width: 20px; height: 20px;
        background: var(--dark);
        color: #fff;
        font-size: 0.65rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .address-card input[type="radio"] { display: none; }
    .address-label-tag {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 0.4rem;
    }
    .address-name { font-weight: 600; font-size: 0.88rem; color: var(--dark); margin-bottom: 0.2rem; }
    .address-line { font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; }
    .address-phone { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }

    /* Add new address form */
    .add-address-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: none;
        border: 1.5px dashed var(--border);
        width: 100%;
        padding: 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
        margin-bottom: 1.2rem;
    }
    .add-address-toggle:hover { border-color: var(--dark); color: var(--dark); }
    .new-address-form {
        display: none;
        background: var(--light);
        border: 1px solid var(--border);
        padding: 1.5rem;
        margin-bottom: 1.2rem;
    }
    .new-address-form.show { display: block; }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .form-row.triple { grid-template-columns: 1fr 1fr 1fr; }

    /* ── Payment methods ───────────────────────────────────── */
    .payment-options { display: flex; flex-direction: column; gap: 0.75rem; }
    .payment-option {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.2rem;
        border: 1.5px solid var(--border);
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        position: relative;
    }
    .payment-option:hover { border-color: var(--dark); }
    .payment-option.selected { border-color: var(--dark); background: #faf8f4; }
    .payment-option input[type="radio"] { accent-color: var(--dark); }
    .payment-icon-wrap {
        width: 44px; height: 30px;
        border: 1px solid var(--border);
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        background: #fff;
        flex-shrink: 0;
    }
    .payment-name { font-weight: 600; font-size: 0.88rem; color: var(--dark); }
    .payment-desc { font-size: 0.75rem; color: var(--text-muted); }
    .payment-badge {
        margin-left: auto;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        background: #d4f0e0;
        color: var(--success);
        letter-spacing: 0.05em;
    }

    /* ── Order summary sidebar ─────────────────────────────── */
    .order-summary {
        background: #fff;
        border: 1px solid var(--border);
        position: sticky;
        top: 120px;
    }
    .os-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--light);
    }
    .os-title {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
    }
    .os-edit { font-size: 0.78rem; color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
    .os-edit:hover { color: var(--accent); }
    .os-items { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); }
    .os-item {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        margin-bottom: 1rem;
    }
    .os-item:last-child { margin-bottom: 0; }
    .os-item-img {
        width: 52px; height: 64px;
        object-fit: cover;
        background: var(--light);
        flex-shrink: 0;
        position: relative;
        border: 1px solid var(--border);
    }
    .os-item-qty {
        position: absolute;
        top: -6px; right: -6px;
        width: 18px; height: 18px;
        background: var(--dark);
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .os-item-name { font-size: 0.82rem; font-weight: 500; color: var(--dark); margin-bottom: 0.15rem; }
    .os-item-variant { font-size: 0.72rem; color: var(--text-muted); }
    .os-item-price { margin-left: auto; font-size: 0.88rem; font-weight: 600; color: var(--dark); white-space: nowrap; }
    .os-totals { padding: 1.2rem 1.5rem; }
    .os-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        margin-bottom: 0.7rem;
        color: var(--text);
    }
    .os-row .label { color: var(--text-muted); }
    .os-row.discount { color: #c0392b; font-weight: 500; }
    .os-row.grand {
        font-size: 1rem;
        font-weight: 700;
        color: var(--dark);
        padding-top: 0.9rem;
        border-top: 1px solid var(--border);
        margin-top: 0.5rem;
        margin-bottom: 0;
    }

    /* ── Place order button ────────────────────────────────── */
    .place-order-wrap { padding: 1.5rem; border-top: 1px solid var(--border); }
    .btn-place-order {
        width: 100%;
        padding: 1.1rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: background 0.2s, transform 0.15s;
        margin-bottom: 0.9rem;
    }
    .btn-place-order:hover:not(:disabled) {
        background: var(--dark-2);
        transform: translateY(-1px);
    }
    .btn-place-order:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
    .order-terms {
        font-size: 0.72rem;
        color: var(--text-muted);
        text-align: center;
        line-height: 1.6;
    }
    .order-terms a { color: var(--text-muted); text-decoration: underline; }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 900px) {
        .checkout-layout { grid-template-columns: 1fr; }
        .order-summary { position: static; }
        .address-grid { grid-template-columns: 1fr; }
        .form-row { grid-template-columns: 1fr; }
        .form-row.triple { grid-template-columns: 1fr; }
    }
    @media (max-width: 500px) {
        .steps-bar .step span { display: none; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container checkout-page">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a><span class="breadcrumb-sep">/</span>
        <a href="cart.php">Cart</a><span class="breadcrumb-sep">/</span>
        <span>Checkout</span>
    </div>

    <h1 class="checkout-heading">Checkout</h1>

    <!-- Steps -->
    <div class="steps-bar">
        <div class="step done">
            <div class="step-num"><i class="fas fa-check" style="font-size:.6rem"></i></div>
            <span>Cart</span>
        </div>
        <div class="step-line"></div>
        <div class="step active">
            <div class="step-num">2</div>
            <span>Checkout</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-num">3</div>
            <span>Confirmation</span>
        </div>
    </div>

    <div class="checkout-layout">

        <!-- ── LEFT: Address + Payment ──────────────────────── -->
        <div>

            <!-- Shipping address -->
            <div class="checkout-block">
                <div class="block-header">
                    <div class="block-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Shipping Address
                    </div>
                </div>
                <div class="block-body">

                    <?php if (!empty($addresses)): ?>
                    <div class="address-grid" id="addressGrid">
                        <?php foreach ($addresses as $i => $addr): ?>
                        <label class="address-card <?= $i===0?'selected':'' ?>"
                               onclick="selectAddress(this, <?= $addr['address_id'] ?>)">
                            <input type="radio" name="address_id"
                                   value="<?= $addr['address_id'] ?>"
                                   <?= $i===0?'checked':'' ?>>
                            <div class="address-label-tag">
                                <?= htmlspecialchars($addr['label'] ?? 'Address') ?>
                                <?php if ($addr['is_default']): ?> · Default<?php endif; ?>
                            </div>
                            <div class="address-name"><?= htmlspecialchars($addr['full_name']) ?></div>
                            <div class="address-line">
                                <?= htmlspecialchars($addr['address_line1']) ?><?= $addr['address_line2'] ? ', '.htmlspecialchars($addr['address_line2']) : '' ?><br>
                                <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> — <?= htmlspecialchars($addr['postal_code']) ?><br>
                                <?= htmlspecialchars($addr['country']) ?>
                            </div>
                            <?php if ($addr['phone']): ?>
                            <div class="address-phone"><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($addr['phone']) ?></div>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Add new address -->
                    <button class="add-address-toggle" onclick="toggleNewAddress()">
                        <i class="fas fa-plus"></i>
                        <?= empty($addresses) ? 'Add a shipping address' : 'Add a new address' ?>
                    </button>

                    <div class="new-address-form <?= empty($addresses)?'show':'' ?>" id="newAddressForm">
                        <div class="form-row" style="margin-bottom:1rem">
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">Full Name *</label>
                                <input class="form-input" id="na_full_name" type="text" placeholder="Your full name">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">Phone</label>
                                <input class="form-input" id="na_phone" type="tel" placeholder="+91 XXXXX XXXXX">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 1 *</label>
                            <input class="form-input" id="na_addr1" type="text" placeholder="House no., Street, Area">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address Line 2</label>
                            <input class="form-input" id="na_addr2" type="text" placeholder="Landmark, Apartment (optional)">
                        </div>
                        <div class="form-row triple" style="margin-bottom:1rem">
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">City *</label>
                                <input class="form-input" id="na_city" type="text" placeholder="City">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">State *</label>
                                <input class="form-input" id="na_state" type="text" placeholder="State">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">PIN Code *</label>
                                <input class="form-input" id="na_postal" type="text" placeholder="PIN" maxlength="10">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Label</label>
                            <select class="form-select" id="na_label">
                                <option value="Home">Home</option>
                                <option value="Work">Work</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <button class="btn btn-dark" onclick="saveAddress()">
                            <i class="fas fa-save"></i> Save & Use This Address
                        </button>
                    </div>

                </div>
            </div>

            <!-- Payment method -->
            <div class="checkout-block">
                <div class="block-header">
                    <div class="block-title">
                        <i class="fas fa-credit-card"></i>
                        Payment Method
                    </div>
                </div>
                <div class="block-body">
                    <div class="payment-options">

                        <label class="payment-option selected" onclick="selectPayment(this,'razorpay')">
                            <input type="radio" name="payment" value="razorpay" checked>
                            <div class="payment-icon-wrap" style="color:#072654">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <div class="payment-name">Razorpay</div>
                                <div class="payment-desc">Cards, UPI, Net Banking, Wallets</div>
                            </div>
                            <span class="payment-badge">RECOMMENDED</span>
                        </label>

                        <label class="payment-option" onclick="selectPayment(this,'paypal')">
                            <input type="radio" name="payment" value="paypal">
                            <div class="payment-icon-wrap">
                                <i class="fab fa-paypal" style="color:#003087"></i>
                            </div>
                            <div>
                                <div class="payment-name">PayPal</div>
                                <div class="payment-desc">Pay via your PayPal account</div>
                            </div>
                        </label>

                        <label class="payment-option" onclick="selectPayment(this,'cod')">
                            <input type="radio" name="payment" value="cod">
                            <div class="payment-icon-wrap">
                                <i class="fas fa-money-bill-wave" style="color:var(--success)"></i>
                            </div>
                            <div>
                                <div class="payment-name">Cash on Delivery</div>
                                <div class="payment-desc">Pay when your order arrives</div>
                            </div>
                        </label>

                    </div>
                </div>
            </div>

        </div>

        <!-- ── RIGHT: Order summary ──────────────────────────── -->
        <div>
            <div class="order-summary">
                <div class="os-header">
                    <span class="os-title">Your Order</span>
                    <a href="cart.php" class="os-edit"><i class="fas fa-edit"></i> Edit cart</a>
                </div>

                <!-- Items -->
                <div class="os-items">
                    <?php foreach ($items as $item): ?>
                    <div class="os-item">
                        <div style="position:relative;flex-shrink:0">
                            <?php if ($item['image']): ?>
                                <img class="os-item-img"
                                     src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($item['image']) ?>"
                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                            <?php else: ?>
                                <div class="os-item-img" style="display:flex;align-items:center;justify-content:center">
                                    <i class="fas fa-image" style="color:#ccc;font-size:.9rem"></i>
                                </div>
                            <?php endif; ?>
                            <div class="os-item-qty"><?= $item['quantity'] ?></div>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div class="os-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <?php if ($item['variant_label']): ?>
                            <div class="os-item-variant"><?= htmlspecialchars($item['variant_label']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="os-item-price">
                            <?= CURRENCY_SYMBOL ?><?= number_format($item['line_total'], 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totals -->
                <div class="os-totals">
                    <div class="os-row">
                        <span class="label">Subtotal</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($totals['subtotal'], 2) ?></span>
                    </div>
                    <div class="os-row">
                        <span class="label">Shipping</span>
                        <span>
                            <?php if ($totals['shipping'] == 0): ?>
                                <span style="color:var(--success)">Free</span>
                            <?php else: ?>
                                <?= CURRENCY_SYMBOL ?><?= number_format($totals['shipping'], 2) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="os-row">
                        <span class="label">Tax (GST 18%)</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($totals['tax'], 2) ?></span>
                    </div>
                    <?php if ($coupon): ?>
                    <div class="os-row discount">
                        <span>Coupon (<?= htmlspecialchars($coupon['code']) ?>)</span>
                        <span>− <?= CURRENCY_SYMBOL ?><?= number_format($coupon['discount_amt'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="os-row grand">
                        <span>Total</span>
                        <span><?= CURRENCY_SYMBOL ?><?= number_format($totals['total'], 2) ?></span>
                    </div>
                </div>

                <!-- Place order -->
                <div class="place-order-wrap">
                    <button class="btn-place-order" id="placeOrderBtn" onclick="placeOrder()">
                        <i class="fas fa-lock"></i>
                        Place Order · <?= CURRENCY_SYMBOL ?><?= number_format($totals['total'], 2) ?>
                    </button>
                    <p class="order-terms">
                        By placing your order you agree to our
                        <a href="#">Terms of Service</a> and
                        <a href="#">Privacy Policy</a>.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
let selectedAddressId = <?= !empty($addresses) ? $addresses[0]['address_id'] : 'null' ?>;
let selectedPayment   = 'razorpay';
let savingAddress     = false;

// ── Select address card ───────────────────────────────────
function selectAddress(card, id) {
    document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedAddressId = id;
}

// ── Select payment option ─────────────────────────────────
function selectPayment(label, method) {
    document.querySelectorAll('.payment-option').forEach(l => l.classList.remove('selected'));
    label.classList.add('selected');
    selectedPayment = method;
}

// ── Toggle new address form ───────────────────────────────
function toggleNewAddress() {
    const form = document.getElementById('newAddressForm');
    form.classList.toggle('show');
}

// ── Save new address then use it ──────────────────────────
function saveAddress() {
    const full_name    = document.getElementById('na_full_name').value.trim();
    const phone        = document.getElementById('na_phone').value.trim();
    const address_line1= document.getElementById('na_addr1').value.trim();
    const address_line2= document.getElementById('na_addr2').value.trim();
    const city         = document.getElementById('na_city').value.trim();
    const state        = document.getElementById('na_state').value.trim();
    const postal_code  = document.getElementById('na_postal').value.trim();
    const label        = document.getElementById('na_label').value;

    if (!full_name || !address_line1 || !city || !state || !postal_code) {
        showToast('Please fill in all required fields.', 'error');
        return;
    }

    fetch('../api/user/address.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ full_name, phone, address_line1, address_line2,
                               city, state, postal_code, label })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            selectedAddressId = d.data.address_id;
            showToast('Address saved!', 'success');
            // Inject new address card
            const grid = document.getElementById('addressGrid');
            const card = document.createElement('label');
            card.className = 'address-card selected';
            card.onclick   = () => selectAddress(card, d.data.address_id);
            card.innerHTML = `
                <div class="address-label-tag">${label}</div>
                <div class="address-name">${full_name}</div>
                <div class="address-line">${address_line1}${address_line2?', '+address_line2:''}<br>${city}, ${state} — ${postal_code}</div>
                ${phone ? `<div class="address-phone"><i class="fas fa-phone fa-xs"></i> ${phone}</div>` : ''}
            `;
            document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
            if (grid) grid.appendChild(card);
            document.getElementById('newAddressForm').classList.remove('show');
        } else {
            showToast(d.message, 'error');
        }
    });
}

// ── Place order ───────────────────────────────────────────
function placeOrder() {
    if (!selectedAddressId) {
        showToast('Please select or add a shipping address.', 'error');
        return;
    }

    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing order…';

    fetch('../api/orders/place.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            address_id     : selectedAddressId,
            payment_method : selectedPayment,
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // Redirect to success page
            window.location.href = `order_success.php?order_id=${d.data.order_id}&order_number=${encodeURIComponent(d.data.order_number)}`;
        } else {
            showToast(d.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Place Order · <?= CURRENCY_SYMBOL ?><?= number_format($totals['total'], 2) ?>';
        }
    })
    .catch(() => {
        showToast('Something went wrong. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Place Order';
    });
}
</script>
</body>
</html>