<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

// ── Pull all dashboard data in one API call ────────────────
$ch = curl_init(SITE_URL . '/api/admin/dashboard.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIE => session_name().'='.session_id(),
]);
$res  = curl_exec($ch);
curl_close($ch);
$json = json_decode($res, true);
$d    = $json['data'] ?? [];

$sales   = $d['sales']   ?? [];
$today   = $d['today']   ?? [];
$monthly = $d['monthly_chart'] ?? [];
$users   = $d['users']   ?? [];
$prods   = $d['products'] ?? [];
$low     = $d['low_stock_items'] ?? [];
$recent  = $d['recent_orders']  ?? [];
$top     = $d['top_products']   ?? [];
$pend_rev = (int)($d['pending_reviews'] ?? 0);
?>

<!-- ── Stat cards ─────────────────────────────────────────── -->
<div class="stats-row">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-val"><?= CURRENCY_SYMBOL ?><?= number_format($sales['total_revenue'] ?? 0, 0) ?></div>
            <div class="stat-sub up"><i class="fas fa-arrow-up"></i> Today: <?= CURRENCY_SYMBOL ?><?= number_format($today['revenue_today'] ?? 0, 0) ?></div>
        </div>
        <div class="stat-icon" style="background:#fef9e7;color:var(--accent)"><i class="fas fa-rupee-sign"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-val"><?= (int)($sales['total_orders'] ?? 0) ?></div>
            <div class="stat-sub up"><i class="fas fa-arrow-up"></i> Today: <?= (int)($today['orders_today'] ?? 0) ?></div>
        </div>
        <div class="stat-icon" style="background:#dbeafe;color:var(--info)"><i class="fas fa-shopping-bag"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Customers</div>
            <div class="stat-val"><?= (int)($users['total_users'] ?? 0) ?></div>
            <div class="stat-sub up"><i class="fas fa-user-plus"></i> New today: <?= (int)($users['new_today'] ?? 0) ?></div>
        </div>
        <div class="stat-icon" style="background:#d4f0e0;color:var(--success)"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Products</div>
            <div class="stat-val"><?= (int)($prods['total_products'] ?? 0) ?></div>
            <div class="stat-sub <?= ($prods['out_of_stock']??0) > 0 ? 'down' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <?= (int)($prods['out_of_stock'] ?? 0) ?> out of stock
            </div>
        </div>
        <div class="stat-icon" style="background:#ede9fe;color:#6d28d9"><i class="fas fa-box"></i></div>
    </div>
</div>

<!-- ── Order status row ───────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1.5rem">
    <?php
    $statuses = [
        ['pending',    'Pending',    '#fef3d0','#b7791f', 'fa-clock'],
        ['confirmed',  'Confirmed',  '#dbeafe','#1d4ed8', 'fa-check-circle'],
        ['processing', 'Processing', '#ede9fe','#6d28d9', 'fa-cog'],
        ['shipped',    'Shipped',    '#d1fae5','#065f46', 'fa-truck'],
        ['delivered',  'Delivered',  '#d4f0e0','#2d7a4f', 'fa-home'],
    ];
    foreach ($statuses as [$key, $label, $bg, $color, $icon]):
    ?>
    <a href="orders.php?status=<?= $key ?>"
       style="background:#fff;border:1px solid var(--border);padding:.9rem 1rem;text-decoration:none;display:flex;align-items:center;gap:.75rem;transition:border-color .15s"
       onmouseover="this.style.borderColor='var(--dark)'"
       onmouseout="this.style.borderColor='var(--border)'">
        <div style="width:34px;height:34px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0">
            <i class="fas <?= $icon ?>"></i>
        </div>
        <div>
            <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:var(--dark);line-height:1">
                <?= (int)($sales[$key] ?? 0) ?>
            </div>
            <div style="font-size:.7rem;color:var(--muted)"><?= $label ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Charts row ─────────────────────────────────────────── -->
<div class="grid-2">
    <!-- Revenue chart -->
    <div class="card">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-chart-line"></i> Revenue This Month</span>
        </div>
        <div class="card-body">
            <canvas id="revenueChart" height="200"></canvas>
        </div>
    </div>
    <!-- Orders chart -->
    <div class="card">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-chart-bar"></i> Orders This Month</span>
        </div>
        <div class="card-body">
            <canvas id="ordersChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- ── Recent orders + top products ──────────────────────── -->
<div class="grid-2">

    <!-- Recent orders -->
    <div class="card" style="margin-bottom:0">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-shopping-bag"></i> Recent Orders</span>
            <a href="orders.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $o): ?>
                <tr>
                    <td>
                        <a href="orders.php?search=<?= urlencode($o['order_number']) ?>"
                           style="font-weight:600;color:var(--dark)">
                            <?= htmlspecialchars($o['order_number']) ?>
                        </a>
                        <div class="muted"><?= date('d M', strtotime($o['created_at'])) ?></div>
                    </td>
                    <td><?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?></td>
                    <td><?= CURRENCY_SYMBOL ?><?= number_format($o['total_amt'], 2) ?></td>
                    <td><span class="badge b-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--muted)">No orders yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Top products -->
    <div class="card" style="margin-bottom:0">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-fire"></i> Top Selling Products</span>
            <a href="products.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Units</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight:500"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="muted"><?= htmlspecialchars($p['sku']) ?></div>
                    </td>
                    <td><?= (int)$p['units_sold'] ?></td>
                    <td><?= CURRENCY_SYMBOL ?><?= number_format($p['revenue'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($top)): ?>
                <tr><td colspan="3" style="text-align:center;padding:2rem;color:var(--muted)">No sales yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Low stock + pending reviews ───────────────────────── -->
<div class="grid-2" style="margin-top:1.5rem">

    <!-- Low stock -->
    <div class="card" style="margin-bottom:0">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</span>
            <a href="products.php" class="btn btn-sm btn-outline">Manage</a>
        </div>
        <?php if (empty($low)): ?>
        <div style="padding:2rem;text-align:center;color:var(--muted)">
            <i class="fas fa-check-circle" style="font-size:1.5rem;color:var(--success);margin-bottom:.5rem;display:block"></i>
            All products well stocked!
        </div>
        <?php else: ?>
        <table class="tbl">
            <thead><tr><th>Product</th><th>SKU</th><th>Stock</th></tr></thead>
            <tbody>
                <?php foreach ($low as $p): ?>
                <tr>
                    <td style="font-weight:500"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="muted"><?= htmlspecialchars($p['sku']) ?></td>
                    <td>
                        <span style="color:<?= $p['stock_qty']==0?'var(--error)':'var(--warning)' ?>;font-weight:700">
                            <?= $p['stock_qty']==0 ? 'Out' : $p['stock_qty'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Quick actions -->
    <div class="card" style="margin-bottom:0">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-bolt"></i> Quick Actions</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
            <a href="products.php?action=new" class="btn btn-dark" style="justify-content:flex-start">
                <i class="fas fa-plus"></i> Add New Product
            </a>
            <a href="orders.php?status=pending" class="btn btn-outline" style="justify-content:flex-start">
                <i class="fas fa-clock"></i> View Pending Orders
                <?php if (($sales['pending']??0) > 0): ?>
                <span style="margin-left:auto;background:var(--error);color:#fff;font-size:.6rem;padding:.1rem .45rem;border-radius:10px">
                    <?= (int)$sales['pending'] ?>
                </span>
                <?php endif; ?>
            </a>
            <a href="reviews.php?approved=0" class="btn btn-outline" style="justify-content:flex-start">
                <i class="fas fa-star"></i> Review Approvals
                <?php if ($pend_rev > 0): ?>
                <span style="margin-left:auto;background:var(--warning);color:#fff;font-size:.6rem;padding:.1rem .45rem;border-radius:10px">
                    <?= $pend_rev ?>
                </span>
                <?php endif; ?>
            </a>
            <a href="coupons.php?action=new" class="btn btn-outline" style="justify-content:flex-start">
                <i class="fas fa-tag"></i> Create Coupon
            </a>
            <a href="users.php" class="btn btn-outline" style="justify-content:flex-start">
                <i class="fas fa-users"></i> Manage Customers
            </a>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const monthly = <?= json_encode(array_values($monthly)) ?>;
const labels  = monthly.map(m => m.date ? new Date(m.date).toLocaleDateString('en-IN',{day:'numeric',month:'short'}) : '');
const revenues = monthly.map(m => parseFloat(m.revenue));
const orders   = monthly.map(m => parseInt(m.orders));

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#9a9088';

// Revenue chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Revenue (<?= CURRENCY_SYMBOL ?>)',
            data: revenues,
            borderColor: '#d4af37',
            backgroundColor: 'rgba(212,175,55,0.08)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#d4af37',
            pointRadius: 3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: {
                grid: { color: '#f0ece6' },
                ticks: { callback: v => '<?= CURRENCY_SYMBOL ?>' + v.toLocaleString() }
            }
        }
    }
});

// Orders chart
new Chart(document.getElementById('ordersChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Orders',
            data: orders,
            backgroundColor: '#1a1612',
            borderRadius: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { color: '#f0ece6' }, ticks: { stepSize: 1 } }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>