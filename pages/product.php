<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$slug = htmlspecialchars(strip_tags(trim($_GET['slug'] ?? '')));
if (!$slug) { header('Location: shop.php'); exit; }

// ── Fetch product ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           b.name AS brand_name,
           ROUND(AVG(r.rating),1)       AS avg_rating,
           COUNT(DISTINCT r.review_id)  AS review_count
    FROM products p
    LEFT JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN brands b     ON b.brand_id    = p.brand_id
    LEFT JOIN reviews r    ON r.product_id  = p.product_id AND r.is_approved = 1
    WHERE p.slug = ? AND p.is_active = 1
    GROUP BY p.product_id LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();
if (!$product) { header('Location: shop.php'); exit; }

$pid = $product['product_id'];

// ── Images ─────────────────────────────────────────────────
$images = $pdo->prepare("
    SELECT image_id, image_url, alt_text, is_primary
    FROM product_images WHERE product_id = ?
    ORDER BY is_primary DESC, sort_order ASC
");
$images->execute([$pid]);
$images = $images->fetchAll();

// ── Variants ───────────────────────────────────────────────
$variants_stmt = $pdo->prepare("
    SELECT pv.variant_id, pv.sku, pv.price, pv.sale_price, pv.stock_qty, pv.image_url,
           GROUP_CONCAT(CONCAT(a.name,':',av.value) ORDER BY a.name SEPARATOR '||') AS combo
    FROM product_variants pv
    LEFT JOIN variant_attributes vat ON vat.variant_id = pv.variant_id
    LEFT JOIN attribute_values av    ON av.value_id    = vat.value_id
    LEFT JOIN attributes a           ON a.attribute_id = av.attribute_id
    WHERE pv.product_id = ? AND pv.is_active = 1
    GROUP BY pv.variant_id
");
$variants_stmt->execute([$pid]);
$variants = $variants_stmt->fetchAll();

// ── Attributes grouped ─────────────────────────────────────
$attr_stmt = $pdo->prepare("
    SELECT a.attribute_id, a.name AS attr_name,
           av.value_id, av.value
    FROM product_attributes pa
    JOIN attribute_values av ON av.value_id    = pa.value_id
    JOIN attributes a        ON a.attribute_id = av.attribute_id
    WHERE pa.product_id = ?
    ORDER BY a.attribute_id, av.value
");
$attr_stmt->execute([$pid]);
$attr_rows = $attr_stmt->fetchAll();
$attributes = [];
foreach ($attr_rows as $row) {
    $attributes[$row['attr_name']][] = ['value_id'=>$row['value_id'], 'value'=>$row['value']];
}

// ── Reviews ────────────────────────────────────────────────
$reviews_stmt = $pdo->prepare("
    SELECT r.review_id, r.rating, r.title, r.body, r.created_at,
           CONCAT(u.first_name,' ',LEFT(u.last_name,1),'.') AS reviewer
    FROM reviews r LEFT JOIN users u ON u.user_id = r.user_id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC LIMIT 8
");
$reviews_stmt->execute([$pid]);
$reviews = $reviews_stmt->fetchAll();

// ── Rating breakdown ───────────────────────────────────────
$breakdown = $pdo->prepare("
    SELECT rating, COUNT(*) AS cnt FROM reviews
    WHERE product_id = ? AND is_approved = 1
    GROUP BY rating ORDER BY rating DESC
");
$breakdown->execute([$pid]);
$breakdown   = $breakdown->fetchAll();
$rating_map  = array_column($breakdown, 'cnt', 'rating');
$total_reviews = (int)$product['review_count'];

// ── Related products ───────────────────────────────────────
$related_stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.slug, p.price, p.sale_price,
           img.image_url AS image
    FROM products p
    LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
    WHERE p.category_id = ? AND p.product_id != ? AND p.is_active = 1
    ORDER BY RAND() LIMIT 4
");
$related_stmt->execute([$product['category_id'], $pid]);
$related = $related_stmt->fetchAll();

$price     = (float)$product['price'];
$sale      = $product['sale_price'] ? (float)$product['sale_price'] : null;
$on_sale   = $sale && $sale < $price;
$discount  = $on_sale ? round((1 - $sale/$price)*100) : 0;
$in_stock  = (int)$product['stock_qty'] > 0;
$primary_img = $images ? $images[0]['image_url'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — <?= SITE_NAME ?></title>
    <meta name="description" content="<?= htmlspecialchars($product['meta_desc'] ?: $product['short_desc']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    /* ── Product layout ─────────────────────────────────────── */
    .product-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        padding: 2rem 0 5rem;
        align-items: start;
    }

    /* ── Gallery ─────────────────────────────────────────────── */
    .gallery { position: sticky; top: 120px; }
    .gallery-main {
        aspect-ratio: 1;
        overflow: hidden;
        background: #f5f0e8;
        margin-bottom: 0.75rem;
        position: relative;
        cursor: zoom-in;
    }
    .gallery-main img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    .gallery-main:hover img { transform: scale(1.06); }
    .gallery-main-placeholder {
        width: 100%; height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 5rem;
        color: rgba(0,0,0,0.1);
    }
    .gallery-thumbs {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .gallery-thumb {
        width: 70px; height: 70px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        transition: border-color 0.2s;
        background: #f5f0e8;
        flex-shrink: 0;
    }
    .gallery-thumb.active { border-color: var(--dark); }
    .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .gallery-badge {
        position: absolute;
        top: 1rem; left: 1rem;
        background: var(--accent);
        color: var(--dark);
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.3rem 0.7rem;
        letter-spacing: 0.05em;
    }

    /* ── Product info ─────────────────────────────────────────── */
    .product-info { padding-top: 0.5rem; }
    .product-breadcrumb {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 0.8rem;
        display: flex;
        gap: 0.4rem;
        align-items: center;
    }
    .product-breadcrumb a { color: var(--text-muted); text-decoration: none; }
    .product-breadcrumb a:hover { color: var(--accent); }
    .product-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.6rem, 3vw, 2.2rem);
        font-weight: 700;
        color: var(--dark);
        line-height: 1.25;
        margin-bottom: 0.75rem;
    }
    .product-meta {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        margin-bottom: 1.2rem;
        flex-wrap: wrap;
    }
    .product-rating {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .stars-lg { color: var(--accent); font-size: 0.85rem; }
    .rating-num { font-size: 0.85rem; font-weight: 600; color: var(--dark); }
    .rating-reviews { font-size: 0.8rem; color: var(--text-muted); text-decoration: underline; cursor: pointer; }
    .product-sku { font-size: 0.78rem; color: var(--text-muted); }
    .brand-tag {
        font-size: 0.75rem;
        background: var(--light);
        border: 1px solid var(--border);
        padding: 0.2rem 0.6rem;
        color: var(--text);
    }
    .product-price-block { margin-bottom: 1.5rem; }
    .price-main {
        font-family: 'DM Sans', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark);
        line-height: 1;
    }
    .price-main.sale { color: #c0392b; }
    .price-original-lg {
        font-size: 1.1rem;
        color: #bbb;
        text-decoration: line-through;
        margin-left: 0.75rem;
    }
    .price-saving {
        display: inline-block;
        background: #fde8e8;
        color: #c0392b;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.25rem 0.6rem;
        margin-top: 0.4rem;
    }
    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        margin-bottom: 1.5rem;
    }
    .stock-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--success);
        flex-shrink: 0;
    }
    .stock-dot.out { background: var(--error); }
    .stock-dot.low { background: #e67e22; }
    .stock-text { color: var(--text-muted); }

    /* ── Attributes ──────────────────────────────────────────── */
    .attr-group { margin-bottom: 1.25rem; }
    .attr-label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--dark);
        margin-bottom: 0.6rem;
        display: flex;
        gap: 0.4rem;
        align-items: center;
    }
    .attr-selected { font-weight: 400; color: var(--text-muted); text-transform: none; }
    .attr-options { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .attr-btn {
        padding: 0.4rem 1rem;
        border: 1.5px solid var(--border);
        background: #fff;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.15s;
        color: var(--text);
    }
    .attr-btn:hover { border-color: var(--dark); }
    .attr-btn.active { background: var(--dark); color: #fff; border-color: var(--dark); }
    .attr-btn.disabled { opacity: 0.35; cursor: not-allowed; text-decoration: line-through; }
    /* Color swatches */
    .swatch-btn {
        width: 32px; height: 32px;
        border-radius: 50%;
        border: 2px solid transparent;
        cursor: pointer;
        transition: border-color 0.15s;
        outline-offset: 2px;
    }
    .swatch-btn.active { border-color: var(--dark); outline: 2px solid var(--dark); }

    /* ── Quantity + CTA ──────────────────────────────────────── */
    .add-to-cart-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: stretch;
    }
    .qty-control {
        display: flex;
        align-items: center;
        border: 1.5px solid var(--border);
        background: #fff;
    }
    .qty-btn {
        width: 40px; height: 100%;
        background: none;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        color: var(--text);
        transition: background 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .qty-btn:hover { background: var(--light); }
    .qty-input {
        width: 50px;
        text-align: center;
        border: none;
        border-left: 1px solid var(--border);
        border-right: 1px solid var(--border);
        font-size: 0.95rem;
        font-family: 'DM Sans', sans-serif;
        outline: none;
        padding: 0.5rem 0;
    }
    .btn-add-cart {
        flex: 1;
        padding: 0 2rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.9rem;
        font-weight: 500;
        letter-spacing: 0.05em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: background 0.2s, transform 0.2s;
    }
    .btn-add-cart:hover:not(:disabled) {
        background: var(--dark-2);
        transform: translateY(-1px);
    }
    .btn-add-cart:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-wishlist {
        width: 52px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: 1rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-wishlist:hover, .btn-wishlist.active { background: #fff0f0; color: #e74c3c; border-color: #e74c3c; }

    /* ── Delivery info strip ─────────────────────────────────── */
    .delivery-strip {
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
        background: var(--light);
        padding: 1.2rem;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border);
    }
    .delivery-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.83rem;
    }
    .delivery-icon { color: var(--accent); width: 18px; text-align: center; }
    .delivery-label { font-weight: 500; color: var(--dark); }
    .delivery-value { color: var(--text-muted); }

    /* ── Tabs ─────────────────────────────────────────────────── */
    .tabs { margin-top: 4rem; }
    .tab-nav {
        display: flex;
        border-bottom: 2px solid var(--border);
        margin-bottom: 2rem;
    }
    .tab-btn {
        padding: 0.8rem 1.8rem;
        background: none;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 500;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-muted);
        cursor: pointer;
        position: relative;
        transition: color 0.2s;
    }
    .tab-btn.active {
        color: var(--dark);
    }
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px; left: 0; right: 0;
        height: 2px;
        background: var(--dark);
    }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Description tab */
    .description-body {
        font-size: 0.92rem;
        line-height: 1.85;
        color: var(--text);
        max-width: 720px;
    }
    .description-body p { margin-bottom: 1rem; }

    /* Reviews tab */
    .reviews-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 3rem;
    }
    .rating-summary { text-align: center; }
    .rating-big {
        font-family: 'Playfair Display', serif;
        font-size: 5rem;
        font-weight: 900;
        color: var(--dark);
        line-height: 1;
    }
    .rating-stars-lg { color: var(--accent); font-size: 1.1rem; margin: 0.5rem 0; }
    .rating-total { font-size: 0.8rem; color: var(--text-muted); }
    .rating-bars { margin-top: 1.5rem; }
    .rating-bar-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.78rem;
    }
    .bar-label { width: 30px; text-align: right; color: var(--text-muted); }
    .bar-track {
        flex: 1;
        height: 6px;
        background: var(--border);
        border-radius: 3px;
        overflow: hidden;
    }
    .bar-fill { height: 100%; background: var(--accent); border-radius: 3px; }
    .bar-count { width: 24px; color: var(--text-muted); }
    .review-list { display: flex; flex-direction: column; gap: 1.5rem; }
    .review-item {
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
    }
    .review-item:last-child { border-bottom: none; }
    .review-header { display: flex; justify-content: space-between; margin-bottom: 0.4rem; }
    .reviewer-name { font-weight: 600; font-size: 0.88rem; color: var(--dark); }
    .review-date { font-size: 0.75rem; color: var(--text-muted); }
    .review-stars { color: var(--accent); font-size: 0.75rem; margin-bottom: 0.4rem; }
    .review-title { font-weight: 600; font-size: 0.88rem; margin-bottom: 0.3rem; }
    .review-body { font-size: 0.85rem; color: var(--text); line-height: 1.7; }
    .no-reviews {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
    }

    /* Write review form */
    .write-review {
        background: var(--light);
        border: 1px solid var(--border);
        padding: 2rem;
        margin-top: 2rem;
    }
    .write-review-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem;
        margin-bottom: 1.2rem;
    }
    .star-picker { display: flex; gap: 0.3rem; margin-bottom: 1rem; }
    .star-pick {
        font-size: 1.6rem;
        color: var(--border);
        cursor: pointer;
        transition: color 0.1s;
    }
    .star-pick.filled, .star-pick:hover { color: var(--accent); }
    .review-submit-row { display: flex; justify-content: flex-end; }

    /* ── Related products ────────────────────────────────────── */
    .related { padding: 4rem 0; }
    .related-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
    }
    .related-card { text-decoration: none; color: inherit; display: block; }
    .related-img {
        aspect-ratio: 3/4;
        overflow: hidden;
        background: #f5f0e8;
        margin-bottom: 0.7rem;
    }
    .related-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
    .related-card:hover .related-img img { transform: scale(1.05); }
    .related-name { font-size: 0.88rem; font-weight: 500; color: var(--dark); margin-bottom: 0.25rem; }
    .related-price { font-size: 0.85rem; color: var(--text-muted); }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .product-layout { grid-template-columns: 1fr; gap: 2rem; }
        .gallery { position: static; }
        .reviews-layout { grid-template-columns: 1fr; }
        .related-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .add-to-cart-row { flex-wrap: wrap; }
        .btn-add-cart { min-height: 52px; }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a><span class="breadcrumb-sep">/</span>
        <a href="shop.php">Shop</a><span class="breadcrumb-sep">/</span>
        <?php if ($product['category_name']): ?>
        <a href="shop.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
        <span class="breadcrumb-sep">/</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>

    <div class="product-layout">

        <!-- ── Gallery ──────────────────────────────────────── -->
        <div class="gallery">
            <div class="gallery-main" id="galleryMain">
                <?php if ($primary_img): ?>
                    <img id="mainImg"
                         src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($primary_img) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                    <div class="gallery-main-placeholder"><i class="fas fa-image"></i></div>
                <?php endif; ?>
                <?php if ($on_sale): ?>
                    <div class="gallery-badge">-<?= $discount ?>% OFF</div>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="gallery-thumbs">
                <?php foreach ($images as $i => $img): ?>
                <div class="gallery-thumb <?= $i===0?'active':'' ?>"
                     onclick="switchImage('<?= UPLOAD_URL ?>/<?= htmlspecialchars($img['image_url']) ?>', this)">
                    <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($img['image_url']) ?>"
                         alt="<?= htmlspecialchars($img['alt_text'] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Product info ─────────────────────────────────── -->
        <div class="product-info">
            <div class="product-breadcrumb">
                <?php if ($product['category_name']): ?>
                <a href="shop.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
                <span>/</span>
                <?php endif; ?>
                <?php if ($product['brand_name']): ?>
                <span class="brand-tag"><?= htmlspecialchars($product['brand_name']) ?></span>
                <?php endif; ?>
            </div>

            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

            <div class="product-meta">
                <?php if ($product['avg_rating']): ?>
                <div class="product-rating">
                    <span class="stars-lg">
                        <?php for($s=1;$s<=5;$s++): ?>
                            <i class="fa<?= $s<=round($product['avg_rating'])?'s':'r' ?> fa-star"></i>
                        <?php endfor; ?>
                    </span>
                    <span class="rating-num"><?= $product['avg_rating'] ?></span>
                    <span class="rating-reviews" onclick="switchTab('reviews')">
                        (<?= $total_reviews ?> reviews)
                    </span>
                </div>
                <?php endif; ?>
                <span class="product-sku">SKU: <?= htmlspecialchars($product['sku']) ?></span>
            </div>

            <!-- Price -->
            <div class="product-price-block">
                <?php if ($on_sale): ?>
                    <div>
                        <span class="price-main sale"><?= CURRENCY_SYMBOL ?><?= number_format($sale, 2) ?></span>
                        <span class="price-original-lg"><?= CURRENCY_SYMBOL ?><?= number_format($price, 2) ?></span>
                    </div>
                    <span class="price-saving">You save <?= CURRENCY_SYMBOL ?><?= number_format($price - $sale, 2) ?> (<?= $discount ?>%)</span>
                <?php else: ?>
                    <span class="price-main"><?= CURRENCY_SYMBOL ?><?= number_format($price, 2) ?></span>
                <?php endif; ?>
            </div>

            <!-- Short description -->
            <?php if ($product['short_desc']): ?>
            <p style="font-size:0.92rem;color:var(--text);line-height:1.75;margin-bottom:1.5rem">
                <?= htmlspecialchars($product['short_desc']) ?>
            </p>
            <?php endif; ?>

            <!-- Stock -->
            <div class="stock-badge">
                <?php
                $qty = (int)$product['stock_qty'];
                $threshold = (int)$product['low_stock_threshold'];
                ?>
                <?php if ($qty <= 0): ?>
                    <span class="stock-dot out"></span>
                    <span class="stock-text">Out of stock</span>
                <?php elseif ($qty <= $threshold): ?>
                    <span class="stock-dot low"></span>
                    <span class="stock-text">Only <strong><?= $qty ?></strong> left in stock — order soon!</span>
                <?php else: ?>
                    <span class="stock-dot"></span>
                    <span class="stock-text">In stock</span>
                <?php endif; ?>
            </div>

            <!-- Attributes -->
            <?php foreach ($attributes as $attr_name => $values): ?>
            <div class="attr-group" data-attr="<?= htmlspecialchars($attr_name) ?>">
                <div class="attr-label">
                    <?= htmlspecialchars($attr_name) ?>:
                    <span class="attr-selected" id="selected-<?= htmlspecialchars($attr_name) ?>">— Select —</span>
                </div>
                <div class="attr-options">
                    <?php foreach ($values as $val): ?>
                    <?php $is_color = strtolower($attr_name) === 'color'; ?>
                    <?php if ($is_color): ?>
                        <button class="swatch-btn"
                                style="background:<?= strtolower($val['value']) ?>"
                                title="<?= htmlspecialchars($val['value']) ?>"
                                data-attr="<?= htmlspecialchars($attr_name) ?>"
                                data-value-id="<?= $val['value_id'] ?>"
                                data-value="<?= htmlspecialchars($val['value']) ?>"
                                onclick="selectAttr(this)"></button>
                    <?php else: ?>
                        <button class="attr-btn"
                                data-attr="<?= htmlspecialchars($attr_name) ?>"
                                data-value-id="<?= $val['value_id'] ?>"
                                data-value="<?= htmlspecialchars($val['value']) ?>"
                                onclick="selectAttr(this)">
                            <?= htmlspecialchars($val['value']) ?>
                        </button>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Quantity + Add to cart -->
            <div class="add-to-cart-row">
                <div class="qty-control">
                    <button class="qty-btn" onclick="changeQty(-1)"><i class="fas fa-minus"></i></button>
                    <input class="qty-input" type="number" id="qtyInput" value="1" min="1" max="<?= $qty ?: 1 ?>">
                    <button class="qty-btn" onclick="changeQty(1)"><i class="fas fa-plus"></i></button>
                </div>
                <button class="btn-add-cart" id="addCartBtn"
                        onclick="addToCart()"
                        <?= !$in_stock ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-bag"></i>
                    <?= $in_stock ? 'Add to Cart' : 'Out of Stock' ?>
                </button>
                <button class="btn-wishlist" id="wishlistBtn" onclick="toggleWishlist()" title="Add to Wishlist">
                    <i class="far fa-heart"></i>
                </button>
            </div>

            <!-- Delivery info -->
            <div class="delivery-strip">
                <div class="delivery-row">
                    <i class="fas fa-truck delivery-icon"></i>
                    <span class="delivery-label">Free Delivery</span>
                    <span class="delivery-value">On orders above <?= CURRENCY_SYMBOL ?>500</span>
                </div>
                <div class="delivery-row">
                    <i class="fas fa-undo delivery-icon"></i>
                    <span class="delivery-label">Easy Returns</span>
                    <span class="delivery-value">30-day hassle-free return</span>
                </div>
                <div class="delivery-row">
                    <i class="fas fa-shield-alt delivery-icon"></i>
                    <span class="delivery-label">Secure Checkout</span>
                    <span class="delivery-value">SSL encrypted payment</span>
                </div>
            </div>

            <!-- Share -->
            <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.82rem;color:var(--text-muted)">
                <span>Share:</span>
                <a href="https://wa.me/?text=<?= urlencode($product['name'].' - '.SITE_URL.'/pages/product.php?slug='.$product['slug']) ?>"
                   target="_blank" style="color:#25d366"><i class="fab fa-whatsapp fa-lg"></i></a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL.'/pages/product.php?slug='.$product['slug']) ?>"
                   target="_blank" style="color:#1877f2"><i class="fab fa-facebook fa-lg"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode(SITE_URL.'/pages/product.php?slug='.$product['slug']) ?>&text=<?= urlencode($product['name']) ?>"
                   target="_blank" style="color:#1da1f2"><i class="fab fa-twitter fa-lg"></i></a>
            </div>
        </div>
    </div>

    <!-- ── Tabs ─────────────────────────────────────────────── -->
    <div class="tabs">
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('description')">Description</button>
            <button class="tab-btn" onclick="switchTab('reviews')">
                Reviews (<?= $total_reviews ?>)
            </button>
        </div>

        <!-- Description -->
        <div class="tab-panel active" id="tab-description">
            <div class="description-body">
                <?php if ($product['description']): ?>
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                <?php else: ?>
                    <p style="color:var(--text-muted)">No description available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reviews -->
        <div class="tab-panel" id="tab-reviews">
            <div class="reviews-layout">
                <!-- Summary -->
                <div class="rating-summary">
                    <div class="rating-big"><?= $product['avg_rating'] ?: '—' ?></div>
                    <div class="rating-stars-lg">
                        <?php for($s=1;$s<=5;$s++): ?>
                            <i class="fa<?= $s<=round($product['avg_rating']??0)?'s':'r' ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-total"><?= $total_reviews ?> review<?= $total_reviews!==1?'s':'' ?></div>
                    <div class="rating-bars">
                        <?php for($r=5;$r>=1;$r--): ?>
                        <?php $cnt = (int)($rating_map[$r] ?? 0); $pct = $total_reviews ? round($cnt/$total_reviews*100) : 0; ?>
                        <div class="rating-bar-row">
                            <span class="bar-label"><?= $r ?>★</span>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="bar-count"><?= $cnt ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Review list + write form -->
                <div>
                    <?php if (empty($reviews)): ?>
                    <div class="no-reviews">
                        <i class="far fa-comment-dots" style="font-size:2.5rem;margin-bottom:0.75rem;display:block;opacity:.3"></i>
                        No reviews yet. Be the first to review this product!
                    </div>
                    <?php else: ?>
                    <div class="review-list">
                        <?php foreach ($reviews as $rv): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name"><?= htmlspecialchars($rv['reviewer'] ?? 'Anonymous') ?></span>
                                <span class="review-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                            </div>
                            <div class="review-stars">
                                <?php for($s=1;$s<=5;$s++): ?>
                                    <i class="fa<?= $s<=$rv['rating']?'s':'r' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if ($rv['title']): ?>
                            <div class="review-title"><?= htmlspecialchars($rv['title']) ?></div>
                            <?php endif; ?>
                            <?php if ($rv['body']): ?>
                            <div class="review-body"><?= htmlspecialchars($rv['body']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Write review -->
                    <?php if (is_logged_in()): ?>
                    <div class="write-review">
                        <div class="write-review-title">Write a Review</div>
                        <div class="form-group">
                            <label class="form-label">Your Rating *</label>
                            <div class="star-picker" id="starPicker">
                                <?php for($s=1;$s<=5;$s++): ?>
                                <i class="far fa-star star-pick" data-val="<?= $s ?>" onclick="pickStar(<?= $s ?>)"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-input" id="reviewTitle" placeholder="Summarise your review">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Review</label>
                            <textarea class="form-input" id="reviewBody" rows="4" placeholder="Share your experience with this product…"></textarea>
                        </div>
                        <div class="review-submit-row">
                            <button class="btn btn-dark" onclick="submitReview()">Submit Review</button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="write-review" style="text-align:center">
                        <p style="margin-bottom:1rem;color:var(--text-muted)">
                            <a href="login.php?redirect=product.php?slug=<?= $product['slug'] ?>" style="color:var(--dark);font-weight:600">Log in</a>
                            to write a review.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Related Products ──────────────────────────────────── -->
<?php if (!empty($related)): ?>
<div class="container related">
    <div style="margin-bottom:2rem">
        <div class="section-label">You may also like</div>
        <h2 class="section-title">Related Products</h2>
    </div>
    <div class="related-grid">
        <?php foreach ($related as $r): ?>
        <?php $r_sale = $r['sale_price'] && (float)$r['sale_price'] < (float)$r['price']; ?>
        <a href="product.php?slug=<?= htmlspecialchars($r['slug']) ?>" class="related-card">
            <div class="related-img">
                <?php if ($r['image']): ?>
                    <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['name']) ?>">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:#ede9e3;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-image" style="font-size:2rem;color:#ccc"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="related-name"><?= htmlspecialchars($r['name']) ?></div>
            <div class="related-price">
                <?php if ($r_sale): ?>
                    <span style="color:#c0392b;font-weight:600"><?= CURRENCY_SYMBOL ?><?= number_format($r['sale_price'],2) ?></span>
                    <span style="text-decoration:line-through;margin-left:0.4rem"><?= CURRENCY_SYMBOL ?><?= number_format($r['price'],2) ?></span>
                <?php else: ?>
                    <?= CURRENCY_SYMBOL ?><?= number_format($r['price'],2) ?>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/cart.js"></script>
<script>
const PRODUCT_ID = <?= $pid ?>;
const VARIANTS   = <?= json_encode($variants) ?>;
let selectedRating = 0;
let selectedAttrs  = {};

// ── Gallery ───────────────────────────────────────────────
function switchImage(src, thumbEl) {
    document.getElementById('mainImg').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumbEl.classList.add('active');
}

// ── Tabs ──────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        const panels = ['description','reviews'];
        b.classList.toggle('active', panels[i] === name);
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name)?.classList.add('active');
}

// ── Attributes ────────────────────────────────────────────
function selectAttr(btn) {
    const attr = btn.dataset.attr;
    // Deselect others in same group
    document.querySelectorAll(`[data-attr="${attr}"]`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedAttrs[attr] = { value_id: parseInt(btn.dataset.valueId), value: btn.dataset.value };
    document.getElementById('selected-' + attr).textContent = btn.dataset.value;
    updateVariant();
}

function updateVariant() {
    if (!VARIANTS.length) return;
    const selectedIds = Object.values(selectedAttrs).map(a => a.value_id).sort().join(',');
    const match = VARIANTS.find(v => {
        const vIds = v.combo ? v.combo.split('||').map(p => parseInt(p.split(':')[0])).sort().join(',') : '';
        return vIds === selectedIds;
    });
    if (match) {
        // Update price display
        const price = parseFloat(match.sale_price || match.price);
        // Could update DOM price here if needed
    }
}

// ── Quantity ─────────────────────────────────────────────
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max) || 99;
    const val   = Math.max(1, Math.min(max, parseInt(input.value) + delta));
    input.value = val;
}

// ── Add to cart ───────────────────────────────────────────
function addToCart() {
    const qty = parseInt(document.getElementById('qtyInput').value);
    const btn = document.getElementById('addCartBtn');

    // Check all required attributes are selected
    const attrGroups = document.querySelectorAll('[data-attr]');
    const required   = new Set([...attrGroups].map(el => el.dataset.attr));
    for (const attr of required) {
        if (!selectedAttrs[attr]) {
            showToast(`Please select a ${attr}.`, 'error');
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding…';

    const body = { product_id: PRODUCT_ID, quantity: qty };

    fetch('../api/cart/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Added to cart!', 'success');
            updateCartCount(d.data.count);
        } else {
            showToast(d.message, 'error');
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Add to Cart';
    });
}

// ── Wishlist ─────────────────────────────────────────────
function toggleWishlist() {
    const btn = document.getElementById('wishlistBtn');
    fetch('../api/user/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: PRODUCT_ID })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        btn.classList.toggle('active', d.success);
        btn.querySelector('i').className = btn.classList.contains('active') ? 'fas fa-heart' : 'far fa-heart';
    });
}

// ── Star picker ──────────────────────────────────────────
function pickStar(n) {
    selectedRating = n;
    document.querySelectorAll('.star-pick').forEach((s, i) => {
        s.className = 'fa-star star-pick ' + (i < n ? 'fas filled' : 'far');
    });
}

// ── Submit review ─────────────────────────────────────────
function submitReview() {
    if (!selectedRating) { showToast('Please select a rating.', 'error'); return; }
    fetch('../api/products/review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id : PRODUCT_ID,
            rating     : selectedRating,
            title      : document.getElementById('reviewTitle').value,
            body       : document.getElementById('reviewBody').value,
        })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) {
            document.getElementById('reviewTitle').value = '';
            document.getElementById('reviewBody').value  = '';
            pickStar(0);
        }
    });
}
</script>
</body>
</html>