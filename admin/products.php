<?php
$page_title = 'Products';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

// ── Pagination & filters ───────────────────────────────────
$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$search = htmlspecialchars(strip_tags($_GET['search'] ?? ''));
$cat    = (int)($_GET['cat'] ?? 0);
$status = $_GET['status'] ?? '';

// ── Build WHERE ────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat)    { $where[] = 'p.category_id = ?';  $params[] = $cat; }
if ($status === 'active')   { $where[] = 'p.is_active = 1'; }
if ($status === 'inactive') { $where[] = 'p.is_active = 0'; }
if ($status === 'low')      { $where[] = 'p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold'; }
if ($status === 'out')      { $where[] = 'p.stock_qty = 0'; }
$w = 'WHERE ' . implode(' AND ', $where);

// ── Count ──────────────────────────────────────────────────
$cnt = $pdo->prepare("SELECT COUNT(*) FROM products p $w");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = (int)ceil($total / $limit);

// ── Fetch ──────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.sku, p.price, p.sale_price,
           p.stock_qty, p.is_active, p.is_featured, p.created_at,
           c.name AS cat_name, b.name AS brand_name,
           img.image_url AS image
    FROM products p
    LEFT JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN brands b     ON b.brand_id    = p.brand_id
    LEFT JOIN product_images img ON img.product_id = p.product_id AND img.is_primary = 1
    $w
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$products = $stmt->fetchAll();

// ── Sidebar data for modal ─────────────────────────────────
$categories = $pdo->query("SELECT category_id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$brands     = $pdo->query("SELECT brand_id, name FROM brands WHERE is_active=1 ORDER BY name")->fetchAll();
?>

<!-- Toolbar -->
<div class="filter-row">
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input class="form-input" type="text" id="searchInput"
               placeholder="Search name or SKU…"
               value="<?= $search ?>"
               onkeydown="if(event.key==='Enter') applyFilter()">
    </div>
    <select class="form-select" id="catFilter" onchange="applyFilter()" style="width:auto;min-width:140px">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['category_id'] ?>" <?= $cat==(int)$c['category_id']?'selected':'' ?>>
            <?= htmlspecialchars($c['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" id="statusFilter" onchange="applyFilter()" style="width:auto;min-width:140px">
        <option value="">All Status</option>
        <option value="active"   <?= $status==='active'  ?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        <option value="low"      <?= $status==='low'     ?'selected':'' ?>>Low Stock</option>
        <option value="out"      <?= $status==='out'     ?'selected':'' ?>>Out of Stock</option>
    </select>
    <button class="btn btn-dark" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Product
    </button>
</div>

<!-- Stats strip -->
<div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <?php
    $strip = [
        ['All', '', $total, '#fff', 'var(--dark)'],
        ['Active', 'active', (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(), '#d4f0e0', 'var(--success)'],
        ['Low Stock', 'low', (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty>0 AND stock_qty<=low_stock_threshold")->fetchColumn(), '#fef3d0', 'var(--warning)'],
        ['Out of Stock', 'out', (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty=0")->fetchColumn(), '#fde8e8', 'var(--error)'],
    ];
    foreach ($strip as [$label, $val, $count, $bg, $color]):
    ?>
    <a href="?status=<?= $val ?>"
       style="background:<?= $bg ?>;color:<?= $color ?>;border:1px solid var(--border);padding:.35rem .9rem;font-size:.75rem;font-weight:600;text-decoration:none;transition:opacity .15s"
       onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
        <?= $label ?> <strong>(<?= $count ?>)</strong>
    </a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="margin-bottom:0">
    <div class="card-head">
        <span class="card-title"><i class="fas fa-box"></i> Products
            <span style="font-weight:400;color:var(--muted);font-size:.75rem;letter-spacing:0">(<?= $total ?>)</span>
        </span>
    </div>
    <div style="overflow-x:auto">
    <table class="tbl">
        <thead>
            <tr>
                <th style="width:44px"></th>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td>
                <?php if ($p['image']): ?>
                <img src="<?= UPLOAD_URL ?>/<?= htmlspecialchars($p['image']) ?>"
                     style="width:38px;height:48px;object-fit:cover;border:1px solid var(--border)">
                <?php else: ?>
                <div style="width:38px;height:48px;background:var(--light);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--border)">
                    <i class="fas fa-image" style="font-size:.75rem"></i>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-weight:600;color:var(--dark)"><?= htmlspecialchars($p['name']) ?></div>
                <div class="muted"><?= htmlspecialchars($p['brand_name'] ?? '—') ?></div>
            </td>
            <td class="muted"><?= htmlspecialchars($p['sku']) ?></td>
            <td class="muted"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
            <td>
                <?php if ($p['sale_price']): ?>
                <div style="font-weight:600;color:var(--error)"><?= CURRENCY_SYMBOL ?><?= number_format($p['sale_price'],2) ?></div>
                <div class="muted" style="text-decoration:line-through"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'],2) ?></div>
                <?php else: ?>
                <div style="font-weight:600"><?= CURRENCY_SYMBOL ?><?= number_format($p['price'],2) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php
                $qty = (int)$p['stock_qty'];
                if ($qty === 0):
                ?><span style="color:var(--error);font-weight:700">Out</span>
                <?php elseif ($qty <= 5): ?>
                <span style="color:var(--warning);font-weight:700"><?= $qty ?></span>
                <?php else: ?>
                <span style="color:var(--success);font-weight:600"><?= $qty ?></span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $p['is_active'] ? 'b-active' : 'b-inactive' ?>">
                    <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
                <?php if ($p['is_featured']): ?>
                <span class="badge" style="background:#fef9e7;color:var(--accent);margin-left:.3rem">★</span>
                <?php endif; ?>
            </td>
            <td>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-sm btn-outline"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)"
                            title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="<?= SITE_URL ?>/pages/product.php?slug=" target="_blank"
                       class="btn btn-sm btn-outline" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button class="btn btn-sm btn-danger"
                            onclick="deleteProduct(<?= $p['product_id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted)">
            <i class="fas fa-box-open" style="font-size:1.5rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
            No products found
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pager" style="padding:1rem">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="pager-btn <?= $i===$page?'active':'' ?>"
           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $cat ?>&status=<?= $status ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Add Product Modal ──────────────────────────────────── -->
<div class="modal-wrap" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-head-title">Add New Product</span>
            <button class="modal-x" onclick="closeModal('addModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input class="form-input" type="text" id="add_name" placeholder="Product name">
                </div>
                <div class="form-group">
                    <label class="form-label">SKU *</label>
                    <input class="form-input" type="text" id="add_sku" placeholder="Unique SKU">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select class="form-select" id="add_category">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand</label>
                    <select class="form-select" id="add_brand">
                        <option value="">No brand</option>
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">Price *</label>
                    <input class="form-input" type="number" step="0.01" id="add_price" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Sale Price</label>
                    <input class="form-input" type="number" step="0.01" id="add_sale_price" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Qty</label>
                    <input class="form-input" type="number" id="add_stock" placeholder="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Short Description</label>
                <input class="form-input" type="text" id="add_short_desc" placeholder="One line summary">
            </div>
            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-input" id="add_desc" rows="4" placeholder="Detailed description…"></textarea>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Meta Title</label>
                    <input class="form-input" type="text" id="add_meta_title" placeholder="SEO title">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Description</label>
                    <input class="form-input" type="text" id="add_meta_desc" placeholder="SEO description">
                </div>
            </div>
            <div style="display:flex;gap:1.5rem">
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;cursor:pointer">
                    <input type="checkbox" id="add_active" checked> Active
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;cursor:pointer">
                    <input type="checkbox" id="add_featured"> Featured
                </label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline btn-sm" onclick="closeModal('addModal')">Cancel</button>
            <button class="btn btn-dark btn-sm" onclick="saveProduct()">
                <i class="fas fa-save"></i> Save Product
            </button>
        </div>
    </div>
</div>

<!-- ── Edit Product Modal ─────────────────────────────────── -->
<div class="modal-wrap" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-head">
            <span class="modal-head-title">Edit Product</span>
            <button class="modal-x" onclick="closeModal('editModal')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_id">
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input class="form-input" type="text" id="edit_name">
                </div>
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input class="form-input" type="text" id="edit_sku" disabled style="opacity:.6">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select class="form-select" id="edit_category">
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand</label>
                    <select class="form-select" id="edit_brand">
                        <option value="">No brand</option>
                        <?php foreach ($brands as $b): ?>
                        <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">Price *</label>
                    <input class="form-input" type="number" step="0.01" id="edit_price">
                </div>
                <div class="form-group">
                    <label class="form-label">Sale Price</label>
                    <input class="form-input" type="number" step="0.01" id="edit_sale_price">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Qty</label>
                    <input class="form-input" type="number" id="edit_stock">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Short Description</label>
                <input class="form-input" type="text" id="edit_short_desc">
            </div>
            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-input" id="edit_desc" rows="4"></textarea>
            </div>
            <div style="display:flex;gap:1.5rem">
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;cursor:pointer">
                    <input type="checkbox" id="edit_active"> Active
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;cursor:pointer">
                    <input type="checkbox" id="edit_featured"> Featured
                </label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline btn-sm" onclick="closeModal('editModal')">Cancel</button>
            <button class="btn btn-dark btn-sm" onclick="updateProduct()">
                <i class="fas fa-save"></i> Update Product
            </button>
        </div>
    </div>
</div>

<script>
// ── Filter ────────────────────────────────────────────────
function applyFilter() {
    const s  = document.getElementById('searchInput').value;
    const c  = document.getElementById('catFilter').value;
    const st = document.getElementById('statusFilter').value;
    window.location.href = `products.php?search=${encodeURIComponent(s)}&cat=${c}&status=${st}`;
}

// ── Add modal ─────────────────────────────────────────────
function openAddModal() {
    ['add_name','add_sku','add_short_desc','add_desc','add_meta_title','add_meta_desc'].forEach(id => document.getElementById(id).value = '');
    ['add_price','add_sale_price','add_stock'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('add_category').value = '';
    document.getElementById('add_brand').value    = '';
    document.getElementById('add_active').checked   = true;
    document.getElementById('add_featured').checked = false;
    openModal('addModal');
}

function saveProduct() {
    const name  = document.getElementById('add_name').value.trim();
    const sku   = document.getElementById('add_sku').value.trim();
    const price = document.getElementById('add_price').value;
    const cat   = document.getElementById('add_category').value;
    if (!name || !sku || !price || !cat) { showToast('Please fill required fields.','error'); return; }

    fetch('../api/admin/products.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            name, sku,
            category_id  : parseInt(cat),
            brand_id     : document.getElementById('add_brand').value || null,
            price        : parseFloat(price),
            sale_price   : document.getElementById('add_sale_price').value || null,
            stock_qty    : parseInt(document.getElementById('add_stock').value) || 0,
            short_desc   : document.getElementById('add_short_desc').value,
            description  : document.getElementById('add_desc').value,
            meta_title   : document.getElementById('add_meta_title').value,
            meta_desc    : document.getElementById('add_meta_desc').value,
            is_active    : document.getElementById('add_active').checked ? 1 : 0,
            is_featured  : document.getElementById('add_featured').checked ? 1 : 0,
        })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { closeModal('addModal'); setTimeout(() => location.reload(), 700); }
    });
}

// ── Edit modal ────────────────────────────────────────────
function openEditModal(p) {
    document.getElementById('edit_id').value         = p.product_id;
    document.getElementById('edit_name').value       = p.name;
    document.getElementById('edit_sku').value        = p.sku;
    document.getElementById('edit_category').value   = p.category_id || '';
    document.getElementById('edit_brand').value      = p.brand_id    || '';
    document.getElementById('edit_price').value      = p.price;
    document.getElementById('edit_sale_price').value = p.sale_price  || '';
    document.getElementById('edit_stock').value      = p.stock_qty;
    document.getElementById('edit_short_desc').value = p.short_desc  || '';
    document.getElementById('edit_desc').value       = p.description || '';
    document.getElementById('edit_active').checked   = p.is_active == 1;
    document.getElementById('edit_featured').checked = p.is_featured == 1;
    openModal('editModal');
}

function updateProduct() {
    const id   = document.getElementById('edit_id').value;
    const name = document.getElementById('edit_name').value.trim();
    if (!name) { showToast('Product name is required.','error'); return; }

    fetch(`../api/admin/products.php?id=${id}`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            name,
            category_id : parseInt(document.getElementById('edit_category').value),
            brand_id    : document.getElementById('edit_brand').value || null,
            price       : parseFloat(document.getElementById('edit_price').value),
            sale_price  : document.getElementById('edit_sale_price').value || null,
            stock_qty   : parseInt(document.getElementById('edit_stock').value) || 0,
            short_desc  : document.getElementById('edit_short_desc').value,
            description : document.getElementById('edit_desc').value,
            is_active   : document.getElementById('edit_active').checked   ? 1 : 0,
            is_featured : document.getElementById('edit_featured').checked ? 1 : 0,
        })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (d.success) { closeModal('editModal'); setTimeout(() => location.reload(), 700); }
    });
}

// ── Delete ─────────────────────────────────────────────────
function deleteProduct(id, name) {
    confirmDel(`Deactivate "${name}"?`, () => {
        fetch(`../api/admin/products.php?id=${id}`, { method:'DELETE' })
            .then(r => r.json())
            .then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
                if (d.success) setTimeout(() => location.reload(), 700);
            });
    });
}

// Close modals on overlay click
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>