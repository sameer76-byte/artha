<?php
$page_title = 'Coupons';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
    <button class="btn btn-dark btn-sm" onclick="openAdd()">
        <i class="fas fa-plus"></i> New Coupon
    </button>
</div>

<div class="card" style="margin-bottom:0">
    <table class="tbl">
        <thead>
            <tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Used</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach($coupons as $c): ?>
        <?php $expired = $c['expires_at'] && strtotime($c['expires_at']) < time(); ?>
        <tr>
            <td>
                <div style="font-family:monospace;font-weight:700;font-size:.9rem;letter-spacing:.05em"><?=htmlspecialchars($c['code'])?></div>
                <?php if($c['description']): ?><div class="muted"><?=htmlspecialchars($c['description'])?></div><?php endif; ?>
            </td>
            <td><?=$c['discount_type']==='percent'?'Percent':'Fixed'?></td>
            <td style="font-weight:600">
                <?=$c['discount_type']==='percent'
                    ? $c['discount_value'].'%'
                    : CURRENCY_SYMBOL.number_format($c['discount_value'],2)?>
            </td>
            <td class="muted"><?=CURRENCY_SYMBOL?><?=number_format($c['min_order_amt'],0)?></td>
            <td>
                <?=(int)$c['used_count']?>
                <?php if($c['usage_limit']): ?><span class="muted">/ <?=$c['usage_limit']?></span><?php endif; ?>
            </td>
            <td class="muted">
                <?=$c['expires_at'] ? date('d M Y',strtotime($c['expires_at'])) : '—'?>
                <?php if($expired): ?><span class="badge b-cancelled" style="margin-left:.3rem">Expired</span><?php endif; ?>
            </td>
            <td><span class="badge <?=$c['is_active']&&!$expired?'b-active':'b-inactive'?>"><?=$c['is_active']&&!$expired?'Active':'Inactive'?></span></td>
            <td>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-sm btn-outline" onclick='openEdit(<?=htmlspecialchars(json_encode($c))?>)'><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="delCoupon(<?=$c['coupon_id']?>)"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($coupons)): ?>
        <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted)">No coupons yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal-wrap" id="couponModal">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-head-title" id="cModalTitle">New Coupon</span>
            <button class="modal-x" onclick="closeModal('couponModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cId">
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Code *</label>
                    <input class="form-input" type="text" id="cCode" placeholder="SUMMER20" style="text-transform:uppercase">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input class="form-input" type="text" id="cDesc" placeholder="Optional note">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select class="form-select" id="cType">
                        <option value="percent">Percent (%)</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input class="form-input" type="number" id="cValue" placeholder="e.g. 20" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Min Order (<?=CURRENCY_SYMBOL?>)</label>
                    <input class="form-input" type="number" id="cMin" placeholder="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Discount (<?=CURRENCY_SYMBOL?>)</label>
                    <input class="form-input" type="number" id="cMax" placeholder="Cap for % discounts" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input class="form-input" type="number" id="cLimit" placeholder="Blank = unlimited" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Per User Limit</label>
                    <input class="form-input" type="number" id="cPerUser" placeholder="1" min="1" value="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-input" type="datetime-local" id="cStart">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input class="form-input" type="datetime-local" id="cExpiry">
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;cursor:pointer;margin-top:.25rem">
                <input type="checkbox" id="cActive" checked style="accent-color:var(--dark)"> Active
            </label>
        </div>
        <div class="modal-foot">
            <button class="btn btn-sm btn-outline" onclick="closeModal('couponModal')">Cancel</button>
            <button class="btn btn-sm btn-dark" id="cSaveBtn" onclick="saveCoupon()">
                <i class="fas fa-save"></i> Save Coupon
            </button>
        </div>
    </div>
</div>

<script>
function openAdd() {
    document.getElementById('cModalTitle').textContent='New Coupon';
    document.getElementById('cId').value='';
    ['cCode','cDesc','cValue','cMin','cMax','cLimit'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('cType').value='percent';
    document.getElementById('cPerUser').value='1';
    document.getElementById('cStart').value='';
    document.getElementById('cExpiry').value='';
    document.getElementById('cActive').checked=true;
    openModal('couponModal');
}
function openEdit(c) {
    document.getElementById('cModalTitle').textContent='Edit Coupon';
    document.getElementById('cId').value=c.coupon_id;
    document.getElementById('cCode').value=c.code||'';
    document.getElementById('cDesc').value=c.description||'';
    document.getElementById('cType').value=c.discount_type||'percent';
    document.getElementById('cValue').value=c.discount_value||'';
    document.getElementById('cMin').value=c.min_order_amt||'';
    document.getElementById('cMax').value=c.max_discount||'';
    document.getElementById('cLimit').value=c.usage_limit||'';
    document.getElementById('cPerUser').value=c.per_user_limit||1;
    document.getElementById('cStart').value=c.starts_at?c.starts_at.replace(' ','T'):'';
    document.getElementById('cExpiry').value=c.expires_at?c.expires_at.replace(' ','T'):'';
    document.getElementById('cActive').checked=!!parseInt(c.is_active);
    openModal('couponModal');
}
function saveCoupon() {
    const id   = document.getElementById('cId').value;
    const code = document.getElementById('cCode').value.trim().toUpperCase();
    const val  = document.getElementById('cValue').value;
    if (!code||!val) { showToast('Code and value are required.','error'); return; }
    const body = {
        code, description:document.getElementById('cDesc').value.trim(),
        discount_type:document.getElementById('cType').value,
        discount_value:parseFloat(val),
        min_order_amt:parseFloat(document.getElementById('cMin').value)||0,
        max_discount:document.getElementById('cMax').value?parseFloat(document.getElementById('cMax').value):null,
        usage_limit:document.getElementById('cLimit').value?parseInt(document.getElementById('cLimit').value):null,
        per_user_limit:parseInt(document.getElementById('cPerUser').value)||1,
        starts_at:document.getElementById('cStart').value||null,
        expires_at:document.getElementById('cExpiry').value||null,
        is_active:document.getElementById('cActive').checked?1:0,
    };
    const btn=document.getElementById('cSaveBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
    const url = id ? `../api/admin/coupons.php?id=${id}` : '../api/admin/coupons.php';
    fetch(url,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})
    .then(r=>r.json()).then(d=>{
        showToast(d.message,d.success?'success':'error');
        if(d.success){closeModal('couponModal');setTimeout(()=>location.reload(),800);}
    }).finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save Coupon';});
}
function delCoupon(id) {
    confirmDel('Delete this coupon?',()=>{
        fetch(`../api/admin/coupons.php?id=${id}`,{method:'DELETE'})
        .then(r=>r.json()).then(d=>{
            showToast(d.message,d.success?'success':'error');
            if(d.success)setTimeout(()=>location.reload(),800);
        });
    });
}
if(new URLSearchParams(window.location.search).get('action')==='new') openAdd();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>