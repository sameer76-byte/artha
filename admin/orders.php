<?php
$page_title = 'Orders';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

$page   = max(1,(int)($_GET['page']   ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;
$search = htmlspecialchars(strip_tags(trim($_GET['search'] ?? '')));
$status = $_GET['status'] ?? '';
$allowed = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded'];
if (!in_array($status,$allowed)) $status = '';

$where=[]; $params=[];
if ($status) { $where[]='o.status=?'; $params[]=$status; }
if ($search) { $where[]='(o.order_number LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.user_id=o.user_id $w");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$total_pages = (int)ceil($total/$limit);

$stmt = $pdo->prepare("
    SELECT o.order_id,o.order_number,o.status,o.total_amt,o.created_at,
           CONCAT(u.first_name,' ',u.last_name) AS customer,
           u.email,
           p.payment_method, p.status AS pay_status,
           COUNT(oi.order_item_id) AS items
    FROM orders o
    LEFT JOIN users u ON u.user_id=o.user_id
    LEFT JOIN payments p ON p.order_id=o.order_id
    LEFT JOIN order_items oi ON oi.order_id=o.order_id
    $w GROUP BY o.order_id
    ORDER BY o.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params,[$limit,$offset]));
$orders = $stmt->fetchAll();
?>

<!-- Filter row -->
<div class="filter-row">
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input class="form-input" type="text" id="si" placeholder="Order number or customer…"
               value="<?= htmlspecialchars($search) ?>"
               onkeydown="if(event.key==='Enter')go()">
    </div>
    <select class="form-select" id="ss" onchange="go()" style="width:160px">
        <option value="">All Statuses</option>
        <?php foreach($allowed as $s): ?>
        <option value="<?=$s?>" <?=$s===$status?'selected':''?>><?=ucfirst($s)?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm" onclick="go()"><i class="fas fa-filter"></i> Filter</button>
    <?php if($search||$status): ?>
    <a href="orders.php" class="btn btn-sm" style="background:#fde8e8;color:var(--error)"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
</div>

<div style="font-size:.78rem;color:var(--muted);margin-bottom:.9rem">
    Showing <strong><?=count($orders)?></strong> of <strong><?=$total?></strong> orders
</div>

<div class="card" style="margin-bottom:0">
    <table class="tbl">
        <thead>
            <tr>
                <th>Order</th><th>Customer</th><th>Items</th>
                <th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($orders as $o): ?>
        <tr>
            <td><a href="../pages/order_detail.php?id=<?=$o['order_id']?>" target="_blank"
                   style="font-weight:600;color:var(--dark)"><?=htmlspecialchars($o['order_number'])?></a></td>
            <td>
                <div style="font-weight:500"><?=htmlspecialchars($o['customer']??'Guest')?></div>
                <div class="muted"><?=htmlspecialchars($o['email']??'')?></div>
            </td>
            <td><?=(int)$o['items']?></td>
            <td style="font-weight:600"><?=CURRENCY_SYMBOL?><?=number_format($o['total_amt'],2)?></td>
            <td>
                <div><?=ucfirst($o['payment_method']??'—')?></div>
                <span class="badge <?=$o['pay_status']==='completed'?'b-delivered':($o['pay_status']==='failed'?'b-cancelled':'b-pending')?>" style="margin-top:.2rem">
                    <?=ucfirst($o['pay_status']??'pending')?>
                </span>
            </td>
            <td><span class="badge b-<?=$o['status']?>"><?=ucfirst($o['status'])?></span></td>
            <td class="muted"><?=date('d M Y',strtotime($o['created_at']))?></td>
            <td>
                <button class="btn btn-sm btn-dark" onclick='openUpdate(<?=htmlspecialchars(json_encode($o))?>)'>
                    <i class="fas fa-edit"></i> Update
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($orders)): ?>
        <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted)">No orders found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if($total_pages>1): ?>
    <div class="pager" style="padding:1rem">
        <?php if($page>1): ?><a class="pager-btn" href="?page=<?=$page-1?>&status=<?=$status?>&search=<?=urlencode($search)?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
        <a class="pager-btn <?=$i===$page?'active':''?>" href="?page=<?=$i?>&status=<?=$status?>&search=<?=urlencode($search)?>"><?=$i?></a>
        <?php endfor; ?>
        <?php if($page<$total_pages): ?><a class="pager-btn" href="?page=<?=$page+1?>&status=<?=$status?>&search=<?=urlencode($search)?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Update status modal -->
<div class="modal-wrap" id="updateModal">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-head-title">Update Order</span>
            <button class="modal-x" onclick="closeModal('updateModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="uOrderId">
            <div id="uOrderNum" style="font-weight:700;color:var(--dark);margin-bottom:1rem;font-size:.95rem"></div>
            <div class="form-group">
                <label class="form-label">New Status *</label>
                <select class="form-select" id="uStatus">
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Comment</label>
                <input class="form-input" type="text" id="uComment" placeholder="Optional status note">
            </div>
            <!-- Shipping fields (shown when status = shipped) -->
            <div id="shippingFields" style="display:none">
                <hr style="border:none;border-top:1px solid var(--border);margin:1rem 0">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:.8rem">Tracking Info</div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Carrier</label>
                        <input class="form-input" type="text" id="uCarrier" placeholder="e.g. BlueDart">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tracking Number</label>
                        <input class="form-input" type="text" id="uTracking" placeholder="AWB number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tracking URL</label>
                        <input class="form-input" type="url" id="uTrackUrl" placeholder="https://…">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Est. Delivery Date</label>
                        <input class="form-input" type="date" id="uEstDelivery">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm btn-outline" onclick="closeModal('updateModal')">Cancel</button>
            <button class="btn btn-sm btn-dark" id="uSaveBtn" onclick="saveUpdate()">
                <i class="fas fa-save"></i> Update Order
            </button>
        </div>
    </div>
</div>

<script>
function go() {
    const s = document.getElementById('si').value.trim();
    const st = document.getElementById('ss').value;
    window.location.href = `orders.php?search=${encodeURIComponent(s)}&status=${st}&page=1`;
}

function openUpdate(o) {
    document.getElementById('uOrderId').value  = o.order_id;
    document.getElementById('uOrderNum').textContent = 'Order: ' + o.order_number;
    document.getElementById('uStatus').value   = o.status;
    document.getElementById('uComment').value  = '';
    ['uCarrier','uTracking','uTrackUrl','uEstDelivery'].forEach(id => document.getElementById(id).value='');
    toggleShippingFields();
    openModal('updateModal');
}

document.getElementById('uStatus').addEventListener('change', toggleShippingFields);

function toggleShippingFields() {
    document.getElementById('shippingFields').style.display =
        document.getElementById('uStatus').value === 'shipped' ? 'block' : 'none';
}

function saveUpdate() {
    const id  = document.getElementById('uOrderId').value;
    const btn = document.getElementById('uSaveBtn');
    const body = {
        status  : document.getElementById('uStatus').value,
        comment : document.getElementById('uComment').value.trim(),
        carrier          : document.getElementById('uCarrier').value.trim(),
        tracking_number  : document.getElementById('uTracking').value.trim(),
        tracking_url     : document.getElementById('uTrackUrl').value.trim(),
        estimated_delivery: document.getElementById('uEstDelivery').value,
    };

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    fetch(`../api/admin/orders.php?id=${id}`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(body)
    })
    .then(r=>r.json())
    .then(d => {
        showToast(d.message, d.success?'success':'error');
        if (d.success) { closeModal('updateModal'); setTimeout(()=>location.reload(),800); }
    })
    .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Update Order'; });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>