<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Read filters from URL ──────────────────────────────────
$page       = max(1, (int)($_GET['page']      ?? 1));
$category   = (int)($_GET['category']  ?? 0);
$brand      = (int)($_GET['brand']     ?? 0);
$search     = htmlspecialchars(strip_tags(trim($_GET['search']   ?? '')));
$min_price  = (float)($_GET['min_price'] ?? 0);
$max_price  = (float)($_GET['max_price'] ?? 0);
$featured   = !empty($_GET['featured']) ? 1 : 0;
$allowed_sorts = ['newest','price_asc','price_desc','popular'];
$sort       = in_array($_GET['sort'] ?? '', $allowed_sorts) ? $_GET['sort'] : 'newest';
$limit      = PRODUCTS_PER_PAGE;
$offset     = ($page - 1) * $limit;

// ── Build WHERE ────────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];

if ($category) { $where[] = 'p.category_id = ?';  $params[] = $category; }
if ($brand)    { $where[] = 'p.brand_id = ?';      $params[] = $brand; }
if ($featured) { $where[] = 'p.is_featured = 1'; }
if ($search)   {
    $where[] = '(p.name LIKE ? OR p.short_desc LIKE ?)';
    $params  = array_merge($params, ["%$search%", "%$search%"]);
}
if ($min_price > 0) { $where[] = 'COALESCE(p.sale_price,p.price) >= ?'; $params[] = $min_price; }
if ($max_price > 0) { $where[] = 'COALESCE(p.sale_price,p.price) <= ?'; $params[] = $max_price; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sort_sql = match($sort) {
    'price_asc'  => 'COALESCE(p.sale_price,p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price,p.price) DESC',
    'popular'    => 'avg_rating DESC',
    default      => 'p.created_at DESC',
};

// ── Count total ────────────────────────────────────────────
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
$total_stmt->execute($params);
$total       = (int)$total_stmt->fetchColumn();
$total_pages = (int)ceil($total / $limit);

// ── Fetch products ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.slug, p.price, p.sale_price,
           p.stock_qty, p.is_featured,
           c.name AS category_name,
           img.image_url AS image,
           ROUND(AVG(r.rating),1) AS avg_rating,
           COUNT(DISTINCT r.review_id) AS review_count
    FROM products p
    LEFT JOIN categories c    ON c.category_id = p.category_id
    LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
    LEFT JOIN reviews r        ON r.product_id = p.product_id AND r.is_approved = 1
    $where_sql
    GROUP BY p.product_id
    ORDER BY $sort_sql
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$products = $stmt->fetchAll();

// ── Sidebar: all categories with counts ───────────────────
$cats = $pdo->query("
    SELECT c.category_id, c.name, c.parent_id,
           COUNT(p.product_id) AS cnt
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.category_id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.category_id
    ORDER BY c.sort_order, c.name
")->fetchAll();

// ── Sidebar: all brands with counts ───────────────────────
$brands = $pdo->query("
    SELECT b.brand_id, b.name,
           COUNT(p.product_id) AS cnt
    FROM brands b
    LEFT JOIN products p ON p.brand_id = b.brand_id AND p.is_active = 1
    WHERE b.is_active = 1
    GROUP BY b.brand_id
    ORDER BY b.name
")->fetchAll();

// ── Price range bounds ─────────────────────────────────────
$price_bounds = $pdo->query("
    SELECT MIN(COALESCE(sale_price,price)) AS min_p,
           MAX(COALESCE(sale_price,price)) AS max_p
    FROM products WHERE is_active = 1
")->fetch();

// ── Active category name ───────────────────────────────────
$active_cat_name = '';
if ($category) {
    foreach ($cats as $c) {
        if ((int)$c['category_id'] === $category) { $active_cat_name = $c['name']; break; }
    }
}
$page_title = $search ? "Search: $search" : ($active_cat_name ?: 'All Products');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    /* ── Shop layout ───────────────────────────────────────── */
    .shop-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 2.5rem;
        padding: 2rem 0 5rem;
        align-items: start;
    }

    /* ── Sidebar ───────────────────────────────────────────── */
    .sidebar { position: sticky; top: 120px; }
    .sidebar-block {
        border: 1px solid var(--border);
        background: #fff;
        margin-bottom: 1rem;
    }
    .sidebar-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--dark);
        padding: 1rem 1.2rem 0.8rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }
    .sidebar-title i { font-size: 0.7rem; transition: transform 0.2s; }
    .sidebar-title.collapsed i { transform: rotate(-90deg); }
    .sidebar-body { padding: 1rem 1.2rem; }
    .filter-list { display: flex; flex-direction: column; gap: 0.4rem; }
    .filter-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.35rem 0;
        cursor: pointer;
        transition: color 0.15s;
    }
    .filter-item:hover .filter-name { color: var(--accent); }
    .filter-item input[type="checkbox"] { margin-right: 0.6rem; accent-color: var(--dark); }
    .filter-name { font-size: 0.85rem; color: var(--text); }
    .filter-count {
        font-size: 0.72rem;
        color: var(--text-muted);
        background: var(--light);
        padding: 0.1rem 0.45rem;
        border-radius: 10px;
    }
    .filter-item.active .filter-name { color: var(--dark); font-weight: 600; }

    /* Price slider */
    .price-range-inputs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .price-input-wrap { flex: 1; position: relative; }
    .price-prefix {
        position: absolute;
        left: 0.6rem; top: 50%;
        transform: translateY(-50%);
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    .price-input {
        width: 100%;
        padding: 0.5rem 0.5rem 0.5rem 1.4rem;
        border: 1px solid var(--border);
        font-size: 0.85rem;
        outline: none;
        background: var(--light);
    }
    .price-input:focus { border-color: var(--dark); }
    .btn-apply-price {
        width: 100%;
        padding: 0.6rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-size: 0.82rem;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        letter-spacing: 0.05em;
        transition: opacity 0.2s;
    }
    .btn-apply-price:hover { opacity: 0.85; }

    /* Active filters pills */
    .active-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.2rem;
    }
    .filter-pill {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        background: var(--dark);
        color: #fff;
        font-size: 0.75rem;
        padding: 0.3rem 0.75rem;
        border-radius: 2px;
    }
    .filter-pill a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.7rem; margin-left: 2px; }
    .filter-pill a:hover { color: #fff; }

    /* ── Shop toolbar ──────────────────────────────────────── */
    .shop-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .result-count {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    .result-count strong { color: var(--dark); }
    .toolbar-right { display: flex; align-items: center; gap: 1rem; }
    .sort-select {
        padding: 0.55rem 2rem 0.55rem 0.9rem;
        border: 1px solid var(--border);
        background: #fff;
        font-size: 0.83rem;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%233a3530' d='M5 7L0 2h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        cursor: pointer;
        outline: none;
        color: var(--text);
        font-family: 'DM Sans', sans-serif;
    }
    .view-toggle { display: flex; gap: 0.3rem; }
    .view-btn {
        width: 32px; height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
        background: #fff;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.82rem;
        transition: all 0.15s;
    }
    .view-btn.active, .view-btn:hover { background: var(--dark); color: #fff; border-color: var(--dark); }

    /* ── Product grid ──────────────────────────────────────── */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    .products-grid.list-view {
        grid-template-columns: 1fr;
    }
    .product-card {
        text-decoration: none;
        color: inherit;
        display: block;
        transition: transform 0.2s;
    }
    .product-card:hover { transform: translateY(-3px); }
    .product-card-img {
        aspect-ratio: 3/4;
        overflow: hidden;
        background: #f5f0e8;
        position: relative;
        margin-bottom: 0.9rem;
    }
    .product-card-img img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    .product-card:hover .product-card-img img { transform: scale(1.05); }
    .product-img-placeholder {
        width: 100%; height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #ede9e3, #e0d9cf);
        color: rgba(0,0,0,0.15);
        font-size: 2.5rem;
    }
    .card-badge {
        position: absolute;
        top: 0.75rem; left: 0.75rem;
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 0.25rem 0.6rem;
        background: var(--accent);
        color: var(--dark);
    }
    .card-badge.new { background: var(--dark); color: #fff; }
    .card-badge.out { background: #ccc; color: #666; }
    .card-actions {
        position: absolute;
        top: 0.75rem; right: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        opacity: 0;
        transform: translateX(8px);
        transition: opacity 0.25s, transform 0.25s;
    }
    .product-card:hover .card-actions { opacity: 1; transform: translateX(0); }
    .card-action-btn {
        width: 34px; height: 34px;
        background: #fff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: var(--dark);
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        transition: background 0.15s, color 0.15s;
    }
    .card-action-btn:hover { background: var(--dark); color: #fff; }
    .product-category {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }
    .product-name {
        font-size: 0.92rem;
        font-weight: 500;
        color: var(--dark);
        margin-bottom: 0.3rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .product-stars { display: flex; align-items: center; gap: 0.3rem; margin-bottom: 0.35rem; }
    .stars { color: var(--accent); font-size: 0.65rem; }
    .review-count { font-size: 0.72rem; color: var(--text-muted); }
    .product-price { display: flex; align-items: center; gap: 0.5rem; font-family: 'DM Sans', sans-serif; }
    .price-now  { font-weight: 600; font-size: 0.95rem; color: var(--dark); }
    .price-was  { font-size: 0.8rem; color: #bbb; text-decoration: line-through; }
    .price-sale { color: #c0392b; }
    .out-of-stock-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

    /* ── List view card ────────────────────────────────────── */
    .products-grid.list-view .product-card {
        display: grid;
        grid-template-columns: 180px 1fr auto;
        gap: 1.5rem;
        align-items: center;
        border: 1px solid var(--border);
        background: #fff;
        padding: 1rem;
        transform: none;
    }
    .products-grid.list-view .product-card:hover { border-color: var(--dark); transform: none; }
    .products-grid.list-view .product-card-img {
        aspect-ratio: 1;
        margin-bottom: 0;
    }
    .products-grid.list-view .card-actions {
        position: static;
        opacity: 1;
        transform: none;
        flex-direction: row;
    }
    .list-add-btn {
        padding: 0.65rem 1.4rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-size: 0.82rem;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .list-add-btn:hover { opacity: 0.85; }

    /* ── No results ────────────────────────────────────────── */
    .no-results {
        grid-column: 1/-1;
        text-align: center;
        padding: 5rem 2rem;
    }
    .no-results i { font-size: 3rem; color: var(--border); margin-bottom: 1rem; display: block; }
    .no-results h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.4rem;
        margin-bottom: 0.5rem;
    }
    .no-results p { color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem; }

    /* ── Mobile filter toggle ──────────────────────────────── */
    .mobile-filter-btn {
        display: none;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-size: 0.82rem;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
    }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 1024px) {
        .shop-layout { grid-template-columns: 220px 1fr; gap: 1.5rem; }
        .products-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .shop-layout { grid-template-columns: 1fr; }
        .sidebar {
            position: fixed;
            top: 0; left: -100%;
            width: 80%; max-width: 320px;
            height: 100vh;
            background: #fff;
            z-index: 2000;
            overflow-y: auto;
            transition: left 0.3s ease;
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
        }
        .sidebar.open { left: 0; }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1999;
        }
        .sidebar-overlay.show { display: block; }
        .mobile-filter-btn { display: flex; }
        .sidebar-close {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Home</a>
        <span class="breadcrumb-sep">/</span>
        <?php if ($active_cat_name): ?>
            <a href="shop.php">Shop</a>
            <span class="breadcrumb-sep">/</span>
            <span><?= htmlspecialchars($active_cat_name) ?></span>
        <?php else: ?>
            <span><?= htmlspecialchars($page_title) ?></span>
        <?php endif; ?>
    </div>

    <div class="shop-layout">

        <!-- ── Sidebar ────────────────────────────────────── -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-close">
                <button onclick="toggleSidebar()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted)">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Categories -->
            <div class="sidebar-block">
                <div class="sidebar-title" onclick="toggleBlock(this)">
                    Categories <i class="fas fa-chevron-down"></i>
                </div>
                <div class="sidebar-body">
                    <div class="filter-list">
                        <label class="filter-item <?= !$category ? 'active' : '' ?>">
                            <a href="shop.php?<?= http_build_query(array_merge($_GET, ['category'=>'','page'=>1])) ?>"
                               style="display:flex;justify-content:space-between;width:100%;text-decoration:none;color:inherit">
                                <span class="filter-name">All Categories</span>
                                <span class="filter-count"><?= $total ?></span>
                            </a>
                        </label>
                        <?php foreach ($cats as $c): if ($c['parent_id']) continue; ?>
                        <label class="filter-item <?= (int)$c['category_id'] === $category ? 'active' : '' ?>">
                            <a href="shop.php?<?= http_build_query(array_merge($_GET, ['category'=>$c['category_id'],'page'=>1])) ?>"
                               style="display:flex;justify-content:space-between;width:100%;text-decoration:none;color:inherit">
                                <span class="filter-name"><?= htmlspecialchars($c['name']) ?></span>
                                <span class="filter-count"><?= $c['cnt'] ?></span>
                            </a>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Brands -->
            <?php if (!empty($brands)): ?>
            <div class="sidebar-block">
                <div class="sidebar-title" onclick="toggleBlock(this)">
                    Brands <i class="fas fa-chevron-down"></i>
                </div>
                <div class="sidebar-body">
                    <div class="filter-list">
                        <?php foreach ($brands as $b): ?>
                        <label class="filter-item <?= (int)$b['brand_id'] === $brand ? 'active' : '' ?>">
                            <a href="shop.php?<?= http_build_query(array_merge($_GET, ['brand'=>$b['brand_id'],'page'=>1])) ?>"
                               style="display:flex;justify-content:space-between;width:100%;text-decoration:none;color:inherit">
                                <span class="filter-name"><?= htmlspecialchars($b['name']) ?></span>
                                <span class="filter-count"><?= $b['cnt'] ?></span>
                            </a>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Price Range -->
            <div class="sidebar-block">
                <div class="sidebar-title" onclick="toggleBlock(this)">
                    Price Range <i class="fas fa-chevron-down"></i>
                </div>
                <div class="sidebar-body">
                    <form method="GET" action="shop.php">
                        <?php foreach ($_GET as $k => $v): ?>
                            <?php if (!in_array($k, ['min_price','max_price','page'])): ?>
                            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="price-range-inputs">
                            <div class="price-input-wrap">
                                <span class="price-prefix"><?= CURRENCY_SYMBOL ?></span>
                                <input class="price-input" type="number" name="min_price"
                                       placeholder="Min" min="0"
                                       value="<?= $min_price ?: '' ?>">
                            </div>
                            <div class="price-input-wrap">
                                <span class="price-prefix"><?= CURRENCY_SYMBOL ?></span>
                                <input class="price-input" type="number" name="max_price"
                                       placeholder="Max" min="0"
                                       value="<?= $max_price ?: '' ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn-apply-price">Apply</button>
                    </form>
                    <?php if ($price_bounds): ?>
                    <p style="font-size:0.72rem;color:var(--text-muted);margin-top:0.6rem">
                        Range: <?= CURRENCY_SYMBOL ?><?= number_format($price_bounds['min_p'],0) ?>
                        — <?= CURRENCY_SYMBOL ?><?= number_format($price_bounds['max_p'],0) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clear filters -->
            <?php if ($category || $brand || $search || $min_price || $max_price || $featured): ?>
            <a href="shop.php" class="btn btn-outline btn-full" style="justify-content:center">
                <i class="fas fa-times"></i> Clear All Filters
            </a>
            <?php endif; ?>
        </aside>

        <!-- ── Main content ───────────────────────────────── -->
        <div>
            <!-- Toolbar -->
            <div class="shop-toolbar">
                <div style="display:flex;align-items:center;gap:1rem">
                    <button class="mobile-filter-btn" onclick="toggleSidebar()">
                        <i class="fas fa-sliders-h"></i> Filters
                    </button>
                    <span class="result-count">
                        Showing <strong><?= count($products) ?></strong> of <strong><?= $total ?></strong> products
                    </span>
                </div>
                <div class="toolbar-right">
                    <select class="sort-select" onchange="applySort(this.value)">
                        <option value="newest"     <?= $sort==='newest'     ?'selected':'' ?>>Newest</option>
                        <option value="popular"    <?= $sort==='popular'    ?'selected':'' ?>>Most Popular</option>
                        <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':'' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort==='price_desc' ?'selected':'' ?>>Price: High to Low</option>
                    </select>
                    <div class="view-toggle">
                        <button class="view-btn active" id="gridViewBtn" onclick="setView('grid')" title="Grid view">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" id="listViewBtn" onclick="setView('list')" title="List view">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active filter pills -->
            <?php
            $active_filters = [];
            if ($search)    $active_filters[] = ['label'=>"Search: $search",     'remove'=>array_diff_key($_GET,['search'=>1,'page'=>1])];
            if ($category)  $active_filters[] = ['label'=>$active_cat_name,      'remove'=>array_diff_key($_GET,['category'=>1,'page'=>1])];
            if ($brand) {
                $bn = '';
                foreach ($brands as $b) { if ((int)$b['brand_id']==$brand) { $bn=$b['name']; break; } }
                $active_filters[] = ['label'=>$bn, 'remove'=>array_diff_key($_GET,['brand'=>1,'page'=>1])];
            }
            if ($min_price) $active_filters[] = ['label'=>"Min: ".CURRENCY_SYMBOL.$min_price, 'remove'=>array_diff_key($_GET,['min_price'=>1,'page'=>1])];
            if ($max_price) $active_filters[] = ['label'=>"Max: ".CURRENCY_SYMBOL.$max_price, 'remove'=>array_diff_key($_GET,['max_price'=>1,'page'=>1])];
            if ($featured)  $active_filters[] = ['label'=>'Featured',             'remove'=>array_diff_key($_GET,['featured'=>1,'page'=>1])];
            ?>
            <?php if ($active_filters): ?>
            <div class="active-filters">
                <?php foreach ($active_filters as $af): ?>
                <span class="filter-pill">
                    <?= htmlspecialchars($af['label']) ?>
                    <a href="shop.php?<?= http_build_query($af['remove']) ?>">✕</a>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Products -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your filters or search term.</p>
                    <a href="shop.php" class="btn btn-dark">Clear Filters</a>
                </div>
                <?php else: ?>
                <?php foreach ($products as $p):
                    $on_sale  = $p['sale_price'] && (float)$p['sale_price'] < (float)$p['price'];
                    $in_stock = (int)$p['stock_qty'] > 0;
                    $discount = $on_sale ? round((1 - $p['sale_price']/$p['price'])*100) : 0;
                ?>
                <a href="product.php?slug=<?= htmlspecialchars($p['slug']) ?>" class="product-card">
                    <div class="product-card-img">
                        <?php if ($p['image']): ?>
                            <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($p['image']) ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="product-img-placeholder"><i class="fas fa-image"></i></div>
                        <?php endif; ?>

                        <?php if (!$in_stock): ?>
                            <span class="card-badge out">Out of Stock</span>
                        <?php elseif ($on_sale): ?>
                            <span class="card-badge">-<?= $discount ?>%</span>
                        <?php elseif ($p['is_featured']): ?>
                            <span class="card-badge new">Featured</span>
                        <?php endif; ?>

                        <div class="card-actions">
                            <button class="card-action-btn"
                                    onclick="addWishlist(event,<?= $p['product_id'] ?>)"
                                    title="Add to Wishlist">
                                <i class="far fa-heart"></i>
                            </button>
                            <?php if ($in_stock): ?>
                            <button class="card-action-btn"
                                    onclick="quickCart(event,<?= $p['product_id'] ?>)"
                                    title="Quick Add">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($p['category_name']): ?>
                    <div class="product-category"><?= htmlspecialchars($p['category_name']) ?></div>
                    <?php endif; ?>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>

                    <?php if ($p['avg_rating']): ?>
                    <div class="product-stars">
                        <span class="stars">
                            <?php for ($s=1;$s<=5;$s++): ?>
                                <i class="fa<?= $s<=round($p['avg_rating'])?'s':'r' ?> fa-star"></i>
                            <?php endfor; ?>
                        </span>
                        <span class="review-count">(<?= $p['review_count'] ?>)</span>
                    </div>
                    <?php endif; ?>

                    <div class="product-price">
                        <?php if ($on_sale): ?>
                            <span class="price-now price-sale"><?= CURRENCY_SYMBOL ?><?= number_format($p['sale_price'],2) ?></span>
                            <span class="price-was"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'],2) ?></span>
                        <?php else: ?>
                            <span class="price-now"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'],2) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$in_stock): ?>
                        <div class="out-of-stock-label">Currently unavailable</div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a class="page-btn" href="shop.php?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                if ($start > 1)           echo '<span class="page-btn" style="border:none;background:none;cursor:default">…</span>';
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a class="page-btn <?= $i===$page?'active':'' ?>"
                   href="shop.php?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                <?php if ($end < $total_pages) echo '<span class="page-btn" style="border:none;background:none;cursor:default">…</span>'; ?>

                <?php if ($page < $total_pages): ?>
                <a class="page-btn" href="shop.php?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/cart.js"></script>
<script>
// Sort
function applySort(val) {
    const params = new URLSearchParams(window.location.search);
    params.set('sort', val);
    params.set('page', 1);
    window.location.search = params.toString();
}

// Grid / List toggle
function setView(mode) {
    const grid = document.getElementById('productsGrid');
    const gBtn  = document.getElementById('gridViewBtn');
    const lBtn  = document.getElementById('listViewBtn');
    if (mode === 'list') {
        grid.classList.add('list-view');
        lBtn.classList.add('active');
        gBtn.classList.remove('active');
        localStorage.setItem('shopView','list');
    } else {
        grid.classList.remove('list-view');
        gBtn.classList.add('active');
        lBtn.classList.remove('active');
        localStorage.setItem('shopView','grid');
    }
}
// Restore saved view
if (localStorage.getItem('shopView') === 'list') setView('list');

// Sidebar toggle (mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

// Collapse sidebar blocks
function toggleBlock(titleEl) {
    titleEl.classList.toggle('collapsed');
    const body = titleEl.nextElementSibling;
    body.style.display = titleEl.classList.contains('collapsed') ? 'none' : 'block';
}

// Quick add to cart
function quickCart(e, productId) {
    e.preventDefault();
    fetch('../api/cart/add.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.success ? 'Added to cart!' : d.message, d.success ? 'success' : 'error');
        if (d.success) updateCartCount(d.data.count);
    });
}

// Wishlist
function addWishlist(e, productId) {
    e.preventDefault();
    fetch('../api/user/wishlist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(d => showToast(d.message, d.success ? 'success' : 'error'));
}
</script>
</body>
</html>