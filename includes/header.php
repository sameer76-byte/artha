<?php
// includes/header.php
// Included at the top of every page
$cart_count = 0;
if (isset($pdo)) {
    require_once __DIR__ . '/cart_helper.php';
    $cart_id    = get_or_create_cart($pdo);
    $cart_items = get_cart_items($pdo, $cart_id);
    $cart_count = count($cart_items);
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!– page-loader –>
<div id="page-loader">
    <span class="loader-dot"></span>
    <span class="loader-dot"></span>
    <span class="loader-dot"></span>
</div>
<div id="toast-container"></div>

<header class="site-header">
    <div class="header-top">
        <div class="container header-top-inner">
            <span>📦 Free shipping on orders above <?= CURRENCY_SYMBOL ?>500</span>
            <span>
                <?php if (is_logged_in()): ?>
                    Hi, <?= htmlspecialchars($_SESSION['full_name']) ?> &nbsp;|&nbsp;
                    <a href="pages/account.php">My Account</a> &nbsp;|&nbsp;
                    <a href="api/auth/logout.php" onclick="logout(event)">Logout</a>
                <?php else: ?>
                    <a href="pages/login.php">Login</a> &nbsp;/&nbsp;
                    <a href="pages/register.php">Register</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <div class="container header-main">
        <a href="<?= SITE_URL ?>/index.php" class="site-logo">
            <span class="logo-text"><?= SITE_NAME ?></span>
        </a>

        <div class="search-bar" id="searchBar">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search products, categories…" autocomplete="off">
            <div class="search-results" id="searchResults"></div>
        </div>

        <nav class="header-actions">
            <a href="pages/account.php" class="header-action-btn" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="pages/wishlist.php" class="header-action-btn" title="Wishlist">
                <i class="fas fa-heart"></i>
            </a>
            <a href="pages/cart.php" class="header-action-btn cart-btn" title="Cart">
                <i class="fas fa-shopping-bag"></i>
                <span class="cart-count" id="cartCount"><?= $cart_count ?></span>
            </a>
        </nav>
    </div>

    <div class="container">
        <nav class="main-nav">
            <a href="<?= SITE_URL ?>/index.php" class="nav-link <?= $current_page==='index.php'?'active':'' ?>">Home</a>
            <a href="pages/shop.php" class="nav-link <?= $current_page==='shop.php'?'active':'' ?>">Shop</a>
            <a href="pages/shop.php?featured=1" class="nav-link">Featured</a>
            <a href="pages/shop.php?sort=newest" class="nav-link">New Arrivals</a>
        </nav>
    </div>
</header>

<style>
.site-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #fff;
    box-shadow: 0 1px 0 var(--border);
}
.header-top {
    background: var(--dark);
    padding: 0.45rem 0;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.65);
}
.header-top-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-top a { color: rgba(255,255,255,0.65); transition: color 0.2s; }
.header-top a:hover { color: var(--accent); }
.header-main {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 2rem;
    padding-top: 1.2rem;
    padding-bottom: 1.2rem;
}
.site-logo { text-decoration: none; }
.logo-text {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--dark);
    letter-spacing: -0.02em;
}
.search-bar {
    position: relative;
    max-width: 520px;
    width: 100%;
    margin: 0 auto;
}
.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.85rem;
}
#searchInput {
    width: 100%;
    padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 1.5px solid var(--border);
    background: var(--light);
    font-size: 0.88rem;
    outline: none;
    border-radius: 2px;
    transition: border-color 0.2s;
}
#searchInput:focus { border-color: var(--dark); background: #fff; }
.search-results {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    background: #fff;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
    max-height: 380px;
    overflow-y: auto;
    z-index: 200;
    display: none;
}
.search-results.open { display: block; }
.search-result-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem 1rem;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
    color: var(--text);
    border-bottom: 1px solid var(--border);
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover { background: var(--light); }
.search-result-img {
    width: 44px; height: 44px;
    object-fit: cover;
    background: var(--light);
    flex-shrink: 0;
}
.search-result-name { font-size: 0.88rem; font-weight: 500; }
.search-result-type { font-size: 0.72rem; color: var(--text-muted); text-transform: capitalize; }
.search-result-price { font-size: 0.85rem; color: var(--accent); font-weight: 600; margin-left: auto; }
.header-actions { display: flex; align-items: center; gap: 0.5rem; }
.header-action-btn {
    width: 40px; height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: var(--text);
    position: relative;
    transition: color 0.2s;
    text-decoration: none;
}
.header-action-btn:hover { color: var(--accent); }
.cart-count {
    position: absolute;
    top: 4px; right: 4px;
    min-width: 16px; height: 16px;
    background: var(--accent);
    color: var(--dark);
    font-size: 0.6rem;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
}
.main-nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding-bottom: 0;
    border-top: 1px solid var(--border);
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
}
.nav-link {
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 0.3rem 0.9rem;
    text-decoration: none;
    transition: color 0.2s;
    border-radius: 2px;
}
.nav-link:hover, .nav-link.active { color: var(--dark); }
.nav-link.active { border-bottom: 2px solid var(--accent); }
</style>

<script>
// Search
let searchTimeout;
const searchInput  = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');

searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { searchResults.classList.remove('open'); return; }
    searchTimeout = setTimeout(() => {
        fetch(`<?= SITE_URL ?>/api/products/search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data.length) {
                    searchResults.innerHTML = '<div style="padding:1rem;color:#999;font-size:.85rem">No results found</div>';
                } else {
                    searchResults.innerHTML = data.data.map(item => `
                        <a class="search-result-item"
                           href="${item.type === 'product'
                               ? '<?= SITE_URL ?>/pages/product.php?slug=' + item.slug
                               : '<?= SITE_URL ?>/pages/shop.php?category=' + item.id}">
                            ${item.image
                                ? `<img class="search-result-img" src="<?= UPLOAD_URL ?>/${item.image}" alt="">`
                                : `<div class="search-result-img" style="display:flex;align-items:center;justify-content:center;background:#ede9e3"><i class="fas fa-tag" style="color:#ccc"></i></div>`}
                            <div>
                                <div class="search-result-name">${item.label}</div>
                                <div class="search-result-type">${item.type}</div>
                            </div>
                            ${item.price ? `<span class="search-result-price"><?= CURRENCY_SYMBOL ?>${parseFloat(item.price).toFixed(2)}</span>` : ''}
                        </a>
                    `).join('');
                }
                searchResults.classList.add('open');
            });
    }, 300);
});

document.addEventListener('click', e => {
    if (!e.target.closest('#searchBar')) searchResults.classList.remove('open');
});

// Logout
function logout(e) {
    e.preventDefault();
    fetch('<?= SITE_URL ?>/api/auth/logout.php', { method: 'POST' })
        .then(() => window.location.href = '<?= SITE_URL ?>/index.php');
}

// Toast helper (global)
function showToast(msg, type = 'info') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    t.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 300); }, 3000);
}

// Update cart count globally
function updateCartCount(n) {
    const el = document.getElementById('cartCount');
    if (el) { el.textContent = n; el.style.display = n > 0 ? 'flex' : 'none'; }
}

// Page loader
window.addEventListener('load', () => {
    const loader = document.getElementById('page-loader');
    if (loader) { loader.classList.add('hidden'); setTimeout(() => loader.remove(), 400); }
});
</script>