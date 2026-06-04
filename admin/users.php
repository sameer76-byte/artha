<?php
$page_title = 'Customers';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20; $offset = ($page-1)*$limit;
$search = htmlspecialchars(strip_tags(trim($_GET['search'] ?? '')));

$where=[]; $params=[];
if ($search) { $where[]='(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
$where[] = 'u.role_id=2';
$w = 'WHERE '.implode(' AND ',$where);

$cnt=$pdo->prepare("SELECT COUNT(*) FROM users u $w"); $cnt->execute($params);
$total=(int)$cnt->fetchColumn(); $total_pages=(int)ceil($total/$limit);

$stmt=$pdo->prepare("
    SELECT u.user_id,u.first_name,u.last_name,u.email,u.phone,u.is_active,u.created_at,
           COUNT(DISTINCT o.order_id) AS orders,
           COALESCE(SUM(o.total_amt),0) AS spent
    FROM users u LEFT JOIN orders o ON o.user_id=u.user_id AND o.status!='cancelled'
    $w GROUP BY u.user_id ORDER BY u.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params,[$limit,$offset]));
$users=$stmt->fetchAll();
?>

<div class="filter-row">
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input class="form-input" type="text" id="si" placeholder="Name or email…"
               value="<?=htmlspecialchars($search)?>"
               onkeydown="if(event.key==='Enter')go()">
    </div>
    <button class="btn btn-outline btn-sm" onclick="go()"><i class="fas fa-search"></i> Search</button>
    <?php if($search): ?><a href="users.php" class="btn btn-sm" style="background:#fde8e8;color:var(--error)"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
</div>

<div style="font-size:.78rem;color:var(--muted);margin-bottom:.9rem">
    <strong><?=$total?></strong> customers
</div>

<div class="card" style="margin-bottom:0">
    <table class="tbl">
        <thead>
            <tr><th>Customer</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach($users as $u): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:.65rem">
                    <div style="width:32px;height:32px;background:var(--accent);color:var(--dark);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0">
                        <?=strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1))?>
                    </div>
                    <div>
                        <div style="font-weight:600"><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></div>
                        <div class="muted">#<?=$u['user_id']?></div>
                    </div>
                </div>
            </td>
            <td><?=htmlspecialchars($u['email'])?></td>
            <td class="muted"><?=htmlspecialchars($u['phone']??'—')?></td>
            <td><?=(int)$u['orders']?></td>
            <td style="font-weight:600"><?=CURRENCY_SYMBOL?><?=number_format($u['spent'],0)?></td>
            <td class="muted"><?=date('d M Y',strtotime($u['created_at']))?></td>
            <td><span class="badge <?=$u['is_active']?'b-active':'b-inactive'?>"><?=$u['is_active']?'Active':'Inactive'?></span></td>
            <td>
                <div style="display:flex;gap:.4rem">
                    <button class="btn btn-sm btn-outline" onclick="viewUser(<?=$u['user_id']?>)" title="View orders">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm"
                            style="background:<?=$u['is_active']?'#fde8e8':'#d4f0e0'?>;color:<?=$u['is_active']?'var(--error)':'var(--success)'?>"
                            onclick="toggleUser(<?=$u['user_id']?>,<?=$u['is_active']?>)"
                            title="<?=$u['is_active']?'Deactivate':'Activate'?>">
                        <i class="fas fa-<?=$u['is_active']?'ban':'check'?>"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted)">No customers found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if($total_pages>1): ?>
    <div class="pager" style="padding:1rem">
        <?php if($page>1): ?><a class="pager-btn" href="?page=<?=$page-1?>&search=<?=urlencode($search)?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
        <a class="pager-btn <?=$i===$page?'active':''?>" href="?page=<?=$i?>&search=<?=urlencode($search)?>"><?=$i?></a>
        <?php endfor; ?>
        <?php if($page<$total_pages): ?><a class="pager-btn" href="?page=<?=$page+1?>&search=<?=urlencode($search)?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function go() {
    window.location.href=`users.php?search=${encodeURIComponent(document.getElementById('si').value.trim())}&page=1`;
}
function viewUser(id) {
    window.open(`../pages/account.php?user_id=${id}`,'_blank');
}
function toggleUser(id, current) {
    confirmDel(current?'Deactivate this customer?':'Reactivate this customer?', ()=>{
        fetch(`../api/admin/users.php?id=${id}`,{
            method:'PUT', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({is_active: current?0:1})
        }).then(r=>r.json()).then(d=>{
            showToast(d.message,d.success?'success':'error');
            if(d.success) setTimeout(()=>location.reload(),800);
        });
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>