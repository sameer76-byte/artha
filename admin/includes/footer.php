</div><!-- /page-content -->
</div><!-- /main -->

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
document.addEventListener('click', e => {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 768 && sb.classList.contains('open') && !sb.contains(e.target) && !e.target.closest('.hamburger')) {
        sb.classList.remove('open');
    }
});
function doLogout(e) {
    e && e.preventDefault();
    fetch('../api/auth/logout.php', { method:'POST' }).then(() => window.location.href='../pages/login.php');
}
function showToast(msg, type='info') {
    const box = document.getElementById('toast-box');
    const t   = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span style="font-weight:700">${type==='success'?'✓':type==='error'?'✕':'ℹ'}</span><span>${msg}</span>`;
    box.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 300); }, 3000);
}
function confirmDel(msg, cb) { if (confirm(msg)) cb(); }
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
</script>
</body>
</html>