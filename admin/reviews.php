<?php
$page_title = 'Reviews';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';

$approved = isset($_GET['approved']) ? (int)$_GET['approved'] : 0;

$stmt = $pdo->prepare("
    SELECT r.review_id,r.rating,r.title,r.body,r.is_approved,r.created_at,
           p.name AS product, p.product_id,
           CONCAT(u.first_name,' ',u.last_name) AS reviewer, u.email
    FROM reviews r
    JOIN products p ON p.product_id=r.product_id
    LEFT JOIN users u ON u.user_id=r.user_id
    WHERE r.is_approved=?
    ORDER BY r.created_at DESC
");
$stmt->execute([$approved]);
$reviews = $stmt->fetchAll();

$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved=0")->fetchColumn();
$approved_count= (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved=1")->fetchColumn();
?>

<!-- Tabs -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem">
    <a href="reviews.php?approved=0"
       class="btn btn-sm <?=$approved===0?'btn-dark':'btn-outline'?>">
        Pending
        <?php if($pending_count): ?>
        <span style="background:var(--error);color:#fff;font-size:.6rem;padding:.1rem .4rem;border-radius:10px;margin-left:.3rem"><?=$pending_count?></span>
        <?php endif; ?>
    </a>
    <a href="reviews.php?approved=1"
       class="btn btn-sm <?=$approved===1?'btn-dark':'btn-outline'?>">
        Approved (<?=$approved_count?>)
    </a>
</div>

<?php if(empty($reviews)): ?>
<div style="background:#fff;border:1px solid var(--border);text-align:center;padding:4rem;color:var(--muted)">
    <i class="fas fa-star" style="font-size:2rem;opacity:.2;margin-bottom:.75rem;display:block"></i>
    <?=$approved?'No approved reviews yet.':'No pending reviews — all caught up!'?>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem">
<?php foreach($reviews as $r): ?>
<div class="card" style="margin-bottom:0">
    <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;padding:1.1rem 1.3rem;border-bottom:1px solid var(--border);background:var(--light);align-items:start">
        <div>
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.3rem">
                <span style="font-weight:700;color:var(--dark)"><?=htmlspecialchars($r['reviewer']??'Anonymous')?></span>
                <span style="font-size:.72rem;color:var(--muted)"><?=htmlspecialchars($r['email']??'')?></span>
                <span style="color:var(--accent);font-size:.78rem">
                    <?php for($s=1;$s<=5;$s++) echo $s<=$r['rating']?'★':'☆'; ?>
                </span>
                <span style="font-size:.75rem;color:var(--muted)"><?=date('d M Y',strtotime($r['created_at']))?></span>
            </div>
            <div style="font-size:.8rem;color:var(--muted)">
                Product: <strong style="color:var(--dark)"><?=htmlspecialchars($r['product'])?></strong>
            </div>
        </div>
        <div style="display:flex;gap:.4rem">
            <?php if(!$approved): ?>
            <button class="btn btn-sm btn-success" onclick="updateReview(<?=$r['review_id']?>,1)">
                <i class="fas fa-check"></i> Approve
            </button>
            <?php else: ?>
            <button class="btn btn-sm" style="background:#fef3d0;color:var(--warning)" onclick="updateReview(<?=$r['review_id']?>,0)">
                <i class="fas fa-undo"></i> Unapprove
            </button>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger" onclick="deleteReview(<?=$r['review_id']?>)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    <div style="padding:1.1rem 1.3rem">
        <?php if($r['title']): ?>
        <div style="font-weight:600;margin-bottom:.3rem"><?=htmlspecialchars($r['title'])?></div>
        <?php endif; ?>
        <?php if($r['body']): ?>
        <div style="font-size:.85rem;color:var(--text);line-height:1.65"><?=htmlspecialchars($r['body'])?></div>
        <?php else: ?>
        <div style="font-size:.82rem;color:var(--muted);font-style:italic">No written review.</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function updateReview(id, approve) {
    fetch(`../api/admin/reviews.php?id=${id}`,{
        method:'PUT', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({is_approved:approve})
    }).then(r=>r.json()).then(d=>{
        showToast(d.message,d.success?'success':'error');
        if(d.success) setTimeout(()=>location.reload(),800);
    });
}
function deleteReview(id) {
    confirmDel('Permanently delete this review?',()=>{
        fetch(`../api/admin/reviews.php?id=${id}`,{method:'DELETE'})
        .then(r=>r.json()).then(d=>{
            showToast(d.message,d.success?'success':'error');
            if(d.success) setTimeout(()=>location.reload(),800);
        });
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>