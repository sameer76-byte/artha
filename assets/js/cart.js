// ============================================================
//  assets/js/cart.js
//  Global cart & UI helpers — included on every page
// ============================================================

// ── Toast notifications ───────────────────────────────────
function showToast(msg, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    toast.innerHTML = `<span style="font-weight:700">${icon}</span><span>${msg}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ── Update cart icon count ────────────────────────────────
function updateCartCount(n) {
    const el = document.getElementById('cartCount');
    if (!el) return;
    el.textContent = n;
    el.style.display = n > 0 ? 'flex' : 'none';
    // Animate bump
    el.style.transform = 'scale(1.4)';
    setTimeout(() => el.style.transform = 'scale(1)', 200);
}

// ── Fetch & sync cart count on page load ─────────────────
function syncCartCount() {
    fetch('/api/cart/get.php')
        .then(r => r.json())
        .then(d => { if (d.success) updateCartCount(d.data.count); })
        .catch(() => {});
}

// ── Add to cart (used by product cards) ───────────────────
function addToCart(productId, quantity = 1, variantId = null, btn = null) {
    if (btn) {
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('/api/cart/add.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                product_id: productId,
                quantity,
                ...(variantId && { variant_id: variantId })
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('Added to cart!', 'success');
                updateCartCount(d.data.count);
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.disabled  = false;
                }, 1500);
            } else {
                showToast(d.message, 'error');
                btn.innerHTML = original;
                btn.disabled  = false;
            }
        })
        .catch(() => {
            showToast('Something went wrong.', 'error');
            btn.innerHTML = original;
            btn.disabled  = false;
        });
    } else {
        // No button reference — fire and forget
        fetch('/api/cart/add.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ product_id: productId, quantity })
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.success ? 'Added to cart!' : d.message, d.success ? 'success' : 'error');
            if (d.success) updateCartCount(d.data.count);
        });
    }
}

// ── Toggle wishlist ───────────────────────────────────────
function toggleWishlist(productId, btn = null) {
    fetch('/api/user/wishlist.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success ? 'success' : 'error');
        if (btn && d.success) {
            const inWl = d.data?.in_wishlist;
            btn.classList.toggle('active', inWl);
            const icon = btn.querySelector('i');
            if (icon) icon.className = inWl ? 'fas fa-heart' : 'far fa-heart';
        }
    })
    .catch(() => showToast('Something went wrong.', 'error'));
}

// ── Format currency ───────────────────────────────────────
function formatPrice(amount) {
    const sym = window.CURRENCY_SYMBOL || '₹';
    return sym + parseFloat(amount).toFixed(2);
}

// ── Debounce helper ───────────────────────────────────────
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

// ── Confirm dialog wrapper ────────────────────────────────
function confirmAction(msg, callback) {
    if (window.confirm(msg)) callback();
}

// ── Scroll to top ─────────────────────────────────────────
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Back to top button ────────────────────────────────────
(function initBackToTop() {
    const btn = document.createElement('button');
    btn.id = 'backToTop';
    btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
    btn.style.cssText = `
        position:fixed; bottom:2rem; right:6rem;
        width:40px; height:40px;
        background:var(--dark); color:#fff;
        border:none; cursor:pointer;
        display:none; align-items:center; justify-content:center;
        font-size:0.85rem; z-index:500;
        transition:opacity 0.2s, transform 0.2s;
        box-shadow:0 4px 12px rgba(0,0,0,0.15);
    `;
    btn.addEventListener('click', scrollToTop);
    document.body.appendChild(btn);

    window.addEventListener('scroll', () => {
        const show = window.scrollY > 400;
        btn.style.display = show ? 'flex' : 'none';
    });
})();

// ── Lazy load images ──────────────────────────────────────
(function initLazyLoad() {
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '100px' });

        document.querySelectorAll('img[data-src]').forEach(img => observer.observe(img));
    }
})();

// ── Page loader hide on load ──────────────────────────────
window.addEventListener('load', () => {
    const loader = document.getElementById('page-loader');
    if (loader) {
        loader.classList.add('hidden');
        setTimeout(() => loader.remove(), 400);
    }
});

// ── Active nav highlight ──────────────────────────────────
(function highlightNav() {
    const path = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') && path.includes(link.getAttribute('href').replace('../',''))) {
            link.classList.add('active');
        }
    });
})();