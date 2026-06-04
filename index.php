<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Fetch featured products
$featured = $pdo->query("
    SELECT p.product_id, p.name, p.slug, p.price, p.sale_price, p.short_desc,
           img.image_url AS image,
           ROUND(AVG(r.rating),1) AS avg_rating,
           COUNT(DISTINCT r.review_id) AS review_count
    FROM products p
    LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
    LEFT JOIN reviews r ON r.product_id = p.product_id AND r.is_approved = 1
    WHERE p.is_active = 1 AND p.is_featured = 1
    GROUP BY p.product_id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch top-level categories
$categories = $pdo->query("
    SELECT category_id, name, slug, image_url,
           (SELECT COUNT(*) FROM products WHERE category_id = c.category_id AND is_active = 1) AS product_count
    FROM categories c
    WHERE is_active = 1 AND parent_id IS NULL
    ORDER BY sort_order ASC
    LIMIT 6
")->fetchAll();

// Fetch newest arrivals
$new_arrivals = $pdo->query("
    SELECT p.product_id, p.name, p.slug, p.price, p.sale_price,
           img.image_url AS image
    FROM products p
    LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 4
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — Shop the Latest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        /* ── Hero ─────────────────────────────────────────── */
        .hero {
            min-height: 92vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            position: relative;
        }
        .hero-left {
            background: var(--dark);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 6rem 5rem;
            position: relative;
            z-index: 1;
        }
        .hero-left::after {
            content: '';
            position: absolute;
            right: -60px;
            top: 0; bottom: 0;
            width: 120px;
            background: var(--dark);
            clip-path: polygon(0 0, 0% 100%, 100% 100%);
            z-index: 2;
        }
        .hero-tag {
            display: inline-block;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 0.35rem 1rem;
            margin-bottom: 2rem;
            width: fit-content;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(3rem, 5vw, 5.5rem);
            font-weight: 900;
            line-height: 1.05;
            color: #fff;
            margin-bottom: 1.5rem;
        }
        .hero-title span { color: var(--accent); font-style: italic; }
        .hero-sub {
            font-family: 'DM Sans', sans-serif;
            color: rgba(255,255,255,0.55);
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 380px;
            margin-bottom: 3rem;
        }
        .hero-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-primary {
            background: var(--accent);
            color: var(--dark);
            padding: 1rem 2.2rem;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 0.05em;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(212,175,55,0.4);
        }
        .btn-ghost {
            color: rgba(255,255,255,0.7);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }
        .btn-ghost:hover { color: #fff; }
        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .hero-stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
        }
        .hero-stat-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .hero-right {
            position: relative;
            background: #f5f0e8;
            overflow: hidden;
        }
        .hero-right img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .hero-badge {
            position: absolute;
            bottom: 3rem; left: 3rem;
            background: var(--dark);
            color: #fff;
            padding: 1.2rem 1.8rem;
            font-family: 'DM Sans', sans-serif;
        }
        .hero-badge-sale {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }
        .hero-badge-text { font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-top: 0.2rem; }

        /* ── Marquee ticker ────────────────────────────────── */
        .ticker {
            background: var(--accent);
            padding: 0.7rem 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .ticker-inner {
            display: inline-block;
            animation: ticker 30s linear infinite;
        }
        .ticker-item {
            display: inline-block;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--dark);
            padding: 0 2rem;
        }
        .ticker-item::after { content: '✦'; margin-left: 2rem; }
        @keyframes ticker { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }

        /* ── Section titles ────────────────────────────────── */
        .section { padding: 5rem 0; }
        .section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 3rem;
        }
        .section-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 700;
            color: var(--dark);
            line-height: 1.2;
        }
        .section-link {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            color: var(--dark);
            text-decoration: none;
            border-bottom: 1px solid var(--dark);
            padding-bottom: 2px;
            white-space: nowrap;
            transition: color 0.2s, border-color 0.2s;
        }
        .section-link:hover { color: var(--accent); border-color: var(--accent); }

        /* ── Category grid ─────────────────────────────────── */
        .cat-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
        }
        .cat-card {
            position: relative;
            aspect-ratio: 3/4;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
        }
        .cat-card img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .cat-card:hover img { transform: scale(1.06); }
        .cat-card-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
        }
        .cat-card-info {
            position: absolute;
            bottom: 1.2rem; left: 1.2rem; right: 1.2rem;
        }
        .cat-card-name {
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            color: #fff;
            font-size: 0.9rem;
        }
        .cat-card-count {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.6);
        }
        /* Placeholder if no image */
        .cat-placeholder {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, #e8e0d0, #d5c9b5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: rgba(0,0,0,0.15);
        }

        /* ── Product grid ──────────────────────────────────── */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        .product-card {
            position: relative;
            text-decoration: none;
            color: inherit;
            group: true;
        }
        .product-card-img {
            aspect-ratio: 3/4;
            overflow: hidden;
            background: #f5f0e8;
            position: relative;
            margin-bottom: 1rem;
        }
        .product-card-img img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .product-card:hover .product-card-img img { transform: scale(1.05); }
        .product-card-badge {
            position: absolute;
            top: 1rem; left: 1rem;
            background: var(--accent);
            color: var(--dark);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.25rem 0.6rem;
        }
        .product-card-actions {
            position: absolute;
            top: 1rem; right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            opacity: 0;
            transform: translateX(10px);
            transition: opacity 0.3s, transform 0.3s;
        }
        .product-card:hover .product-card-actions {
            opacity: 1;
            transform: translateX(0);
        }
        .action-btn {
            width: 36px; height: 36px;
            background: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--dark);
            transition: background 0.2s, color 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .action-btn:hover { background: var(--dark); color: #fff; }
        .product-card-name {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card-rating {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: 0.4rem;
        }
        .stars { color: var(--accent); font-size: 0.7rem; }
        .rating-count { font-family: 'DM Sans', sans-serif; font-size: 0.72rem; color: #999; }
        .product-card-price {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-family: 'DM Sans', sans-serif;
        }
        .price-current { font-weight: 600; color: var(--dark); font-size: 1rem; }
        .price-original { color: #aaa; font-size: 0.85rem; text-decoration: line-through; }
        .price-sale { color: #c0392b; font-weight: 600; }

        /* ── Banner strip ──────────────────────────────────── */
        .banner-strip {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .banner-card {
            position: relative;
            height: 320px;
            overflow: hidden;
            text-decoration: none;
        }
        .banner-card img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .banner-card:hover img { transform: scale(1.04); }
        .banner-card-content {
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(0,0,0,0.65) 0%, transparent 70%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem;
        }
        .banner-card-tag {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.7rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 0.6rem;
        }
        .banner-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            margin-bottom: 1.2rem;
        }
        .banner-card-btn {
            display: inline-block;
            background: #fff;
            color: var(--dark);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.6rem 1.4rem;
            text-decoration: none;
            width: fit-content;
            transition: background 0.2s, color 0.2s;
        }
        .banner-card-btn:hover { background: var(--accent); color: var(--dark); }

        /* ── Features bar ──────────────────────────────────── */
        .features-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border: 1px solid #e8e2d8;
            margin: 3rem 0;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            padding: 1.8rem 2rem;
            border-right: 1px solid #e8e2d8;
        }
        .feature-item:last-child { border-right: none; }
        .feature-icon {
            font-size: 1.4rem;
            color: var(--accent);
            flex-shrink: 0;
        }
        .feature-title {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--dark);
            margin-bottom: 0.15rem;
        }
        .feature-desc {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.75rem;
            color: #999;
        }

        /* ── New arrivals (horizontal scroll) ─────────────── */
        .arrivals-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        /* ── Newsletter ────────────────────────────────────── */
        .newsletter {
            background: var(--dark);
            padding: 5rem;
            text-align: center;
            margin: 4rem 0 0;
        }
        .newsletter-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.72rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        .newsletter-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            color: #fff;
            margin-bottom: 0.8rem;
        }
        .newsletter-sub {
            font-family: 'DM Sans', sans-serif;
            color: rgba(255,255,255,0.5);
            margin-bottom: 2rem;
        }
        .newsletter-form {
            display: flex;
            max-width: 460px;
            margin: 0 auto;
        }
        .newsletter-form input {
            flex: 1;
            padding: 1rem 1.5rem;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-right: none;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            outline: none;
        }
        .newsletter-form input::placeholder { color: rgba(255,255,255,0.3); }
        .newsletter-form button {
            padding: 1rem 2rem;
            background: var(--accent);
            color: var(--dark);
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .newsletter-form button:hover { opacity: 0.85; }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 1024px) {
            .hero { grid-template-columns: 1fr; min-height: auto; }
            .hero-right { height: 50vh; }
            .hero-left { padding: 4rem 2rem; }
            .hero-left::after { display: none; }
            .cat-grid { grid-template-columns: repeat(3, 1fr); }
            .product-grid, .arrivals-grid { grid-template-columns: repeat(2, 1fr); }
            .features-bar { grid-template-columns: repeat(2, 1fr); }
            .feature-item:nth-child(2) { border-right: none; }
            .feature-item:nth-child(3) { border-top: 1px solid #e8e2d8; }
            .feature-item:nth-child(4) { border-top: 1px solid #e8e2d8; }
        }
        @media (max-width: 640px) {
            .cat-grid { grid-template-columns: repeat(2, 1fr); }
            .product-grid, .arrivals-grid { grid-template-columns: repeat(2, 1fr); }
            .banner-strip { grid-template-columns: 1fr; }
            .hero-stats { gap: 1.5rem; }
            .newsletter { padding: 3rem 1.5rem; }
            .newsletter-form { flex-direction: column; }
            .newsletter-form input { border-right: 1px solid rgba(255,255,255,0.15); border-bottom: none; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="hero">
    <div class="hero-left">
        <span class="hero-tag">New Collection 2024</span>
        <h1 class="hero-title">
            Style That<br>
            <span>Speaks</span><br>
            For Itself
        </h1>
        <p class="hero-sub">
            Discover handpicked pieces crafted for those who believe fashion is a form of self-expression.
        </p>
        <div class="hero-actions">
            <a href="pages/shop.php" class="btn-primary">Shop Now</a>
            <a href="pages/shop.php?featured=1" class="btn-ghost">
                <i class="fas fa-play-circle"></i> View Lookbook
            </a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-num">12K+</div>
                <div class="hero-stat-label">Happy Customers</div>
            </div>
            <div>
                <div class="hero-stat-num">800+</div>
                <div class="hero-stat-label">Products</div>
            </div>
            <div>
                <div class="hero-stat-num">4.9★</div>
                <div class="hero-stat-label">Avg Rating</div>
            </div>
        </div>
    </div>
    <div class="hero-right">
        <!-- Replace with your actual hero image -->
        <div style="width:100%;height:100%;background:linear-gradient(135deg,#e8dfd0 0%,#c9b99a 100%);display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-image" style="font-size:5rem;color:rgba(0,0,0,0.15)"></i>
        </div>
        <div class="hero-badge">
            <div class="hero-badge-sale">30% OFF</div>
            <div class="hero-badge-text">On first order</div>
        </div>
    </div>
</section>

<!-- ── Ticker ─────────────────────────────────────────────── -->
<div class="ticker">
    <div class="ticker-inner">
        <?php for($i=0;$i<2;$i++): ?>
// Change generic messages to Artha-branded ones:
<span class="ticker-item">Free shipping above ₹500</span>
<span class="ticker-item">Proudly Indian 🇮🇳</span>
<span class="ticker-item">Easy 30-day returns</span>
<span class="ticker-item">Use code ARTHA10 for 10% off</span>
<span class="ticker-item">COD available across India</span>
        <?php endfor; ?>
    </div>
</div>

<div class="container">

    <!-- ── Categories ──────────────────────────────────────── -->
    <?php if (!empty($categories)): ?>
    <section class="section">
        <div class="section-header">
            <div>
                <div class="section-label">Browse</div>
                <h2 class="section-title">Shop by Category</h2>
            </div>
            <a href="pages/shop.php" class="section-link">All Categories →</a>
        </div>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
            <a href="pages/shop.php?category=<?= $cat['category_id'] ?>" class="cat-card">
                <?php if ($cat['image_url']): ?>
                    <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($cat['image_url']) ?>"
                         alt="<?= htmlspecialchars($cat['name']) ?>">
                <?php else: ?>
                    <div class="cat-placeholder"><i class="fas fa-tag"></i></div>
                <?php endif; ?>
                <div class="cat-card-overlay"></div>
                <div class="cat-card-info">
                    <div class="cat-card-name"><?= htmlspecialchars($cat['name']) ?></div>
                    <div class="cat-card-count"><?= $cat['product_count'] ?> items</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Features bar ────────────────────────────────────── -->
    <div class="features-bar">
        <div class="feature-item">
            <i class="fas fa-truck feature-icon"></i>
            <div>
                <div class="feature-title">Free Shipping</div>
                <div class="feature-desc">On orders above ₹500</div>
            </div>
        </div>
        <div class="feature-item">
            <i class="fas fa-undo feature-icon"></i>
            <div>
                <div class="feature-title">Easy Returns</div>
                <div class="feature-desc">30-day return policy</div>
            </div>
        </div>
        <div class="feature-item">
            <i class="fas fa-shield-alt feature-icon"></i>
            <div>
                <div class="feature-title">Secure Payment</div>
                <div class="feature-desc">100% safe & encrypted</div>
            </div>
        </div>
        <div class="feature-item">
            <i class="fas fa-headset feature-icon"></i>
            <div>
                <div class="feature-title">24/7 Support</div>
                <div class="feature-desc">We're always here for you</div>
            </div>
        </div>
    </div>

    <!-- ── Featured products ───────────────────────────────── -->
    <?php if (!empty($featured)): ?>
    <section class="section">
        <div class="section-header">
            <div>
                <div class="section-label">Handpicked</div>
                <h2 class="section-title">Featured Products</h2>
            </div>
            <a href="pages/shop.php?featured=1" class="section-link">View All →</a>
        </div>
        <div class="product-grid">
            <?php foreach ($featured as $p): ?>
            <?php $on_sale = $p['sale_price'] && $p['sale_price'] < $p['price']; ?>
            <a href="pages/product.php?slug=<?= htmlspecialchars($p['slug']) ?>" class="product-card">
                <div class="product-card-img">
                    <?php if ($p['image']): ?>
                        <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($p['image']) ?>"
                             alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;background:#e8e0d0;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-image" style="font-size:2rem;color:#ccc"></i>
                        </div>
                    <?php endif; ?>
                    <?php if ($on_sale): ?>
                        <span class="product-card-badge">Sale</span>
                    <?php endif; ?>
                    <div class="product-card-actions">
                        <button class="action-btn" onclick="addToWishlist(event, <?= $p['product_id'] ?>)" title="Wishlist">
                            <i class="fas fa-heart"></i>
                        </button>
                        <button class="action-btn" onclick="quickAddToCart(event, <?= $p['product_id'] ?>)" title="Add to Cart">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <?php if ($p['avg_rating']): ?>
                <div class="product-card-rating">
                    <span class="stars">
                        <?php for($s=1;$s<=5;$s++): ?>
                            <i class="fa<?= $s <= round($p['avg_rating']) ? 's' : 'r' ?> fa-star"></i>
                        <?php endfor; ?>
                    </span>
                    <span class="rating-count">(<?= $p['review_count'] ?>)</span>
                </div>
                <?php endif; ?>
                <div class="product-card-price">
                    <?php if ($on_sale): ?>
                        <span class="price-current price-sale"><?= CURRENCY_SYMBOL ?><?= number_format($p['sale_price'], 2) ?></span>
                        <span class="price-original"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'], 2) ?></span>
                    <?php else: ?>
                        <span class="price-current"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'], 2) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Promo banners ───────────────────────────────────── -->
    <div class="banner-strip">
        <a href="pages/shop.php" class="banner-card">
            <div style="width:100%;height:100%;background:linear-gradient(135deg,#2c2c2c,#4a3728)"></div>
            <div class="banner-card-content">
                <div class="banner-card-tag">Limited Time</div>
                <div class="banner-card-title">Summer<br>Collection</div>
                <span class="banner-card-btn">Shop Now</span>
            </div>
        </a>
        <a href="pages/shop.php?sort=price_asc" class="banner-card">
            <div style="width:100%;height:100%;background:linear-gradient(135deg,#3d4a3a,#2c3b29)"></div>
            <div class="banner-card-content">
                <div class="banner-card-tag">Best Deals</div>
                <div class="banner-card-title">Up to 50%<br>Off Today</div>
                <span class="banner-card-btn">Grab Deal</span>
            </div>
        </a>
    </div>

    <!-- ── New Arrivals ─────────────────────────────────────── -->
    <?php if (!empty($new_arrivals)): ?>
    <section class="section">
        <div class="section-header">
            <div>
                <div class="section-label">Just In</div>
                <h2 class="section-title">New Arrivals</h2>
            </div>
            <a href="pages/shop.php?sort=newest" class="section-link">See All →</a>
        </div>
        <div class="arrivals-grid">
            <?php foreach ($new_arrivals as $p): ?>
            <?php $on_sale = $p['sale_price'] && $p['sale_price'] < $p['price']; ?>
            <a href="pages/product.php?slug=<?= htmlspecialchars($p['slug']) ?>" class="product-card">
                <div class="product-card-img">
                    <?php if ($p['image']): ?>
                        <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($p['image']) ?>"
                             alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;background:#e8e0d0;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-image" style="font-size:2rem;color:#ccc"></i>
                        </div>
                    <?php endif; ?>
                    <span class="product-card-badge" style="background:var(--dark);color:#fff;">New</span>
                    <div class="product-card-actions">
                        <button class="action-btn" onclick="quickAddToCart(event, <?= $p['product_id'] ?>)" title="Add to Cart">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="product-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-card-price">
                    <?php if ($on_sale): ?>
                        <span class="price-current price-sale"><?= CURRENCY_SYMBOL ?><?= number_format($p['sale_price'], 2) ?></span>
                        <span class="price-original"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'], 2) ?></span>
                    <?php else: ?>
                        <span class="price-current"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'], 2) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div><!-- /container -->

<!-- ── Newsletter ─────────────────────────────────────────── -->
<div class="newsletter">
    <div class="newsletter-label">Stay in the loop</div>
    <h2 class="newsletter-title">Join the Artha Family</h2>
<p class="newsletter-sub">Be the first to know about new arrivals, exclusive deals and festive offers.</p>
    <form class="newsletter-form" onsubmit="subscribeNewsletter(event)">
        <input type="email" placeholder="Enter your email address" required>
        <button type="submit">Subscribe</button>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/cart.js"></script>
<script>
function quickAddToCart(e, productId) {
    e.preventDefault();
    fetch('api/cart/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Added to cart!', 'success');
            updateCartCount(data.data.count);
        } else {
            showToast(data.message, 'error');
        }
    });
}

function addToWishlist(e, productId) {
    e.preventDefault();
    fetch('api/user/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(data => showToast(data.message, data.success ? 'success' : 'error'));
}

function subscribeNewsletter(e) {
    e.preventDefault();
    showToast('Thanks for subscribing!', 'success');
    e.target.reset();
}
</script>
</body>
</html>