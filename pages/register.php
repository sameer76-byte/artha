<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$redirect = htmlspecialchars(strip_tags($_GET['redirect'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    .auth-page {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .auth-left {
        background: var(--dark);
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 5rem;
        position: relative;
        overflow: hidden;
    }
    .auth-left::before {
        content: '';
        position: absolute;
        width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(212,175,55,0.12) 0%, transparent 70%);
        bottom: -100px; left: -100px;
        pointer-events: none;
    }
    .auth-brand {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        font-weight: 900;
        color: #fff;
        margin-bottom: 3rem;
        text-decoration: none;
        display: block;
    }
    .auth-left-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2rem, 3.5vw, 3rem);
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
        margin-bottom: 1rem;
    }
    .auth-left-title span { color: var(--accent); font-style: italic; }
    .auth-left-sub {
        color: rgba(255,255,255,0.5);
        font-size: 0.92rem;
        line-height: 1.75;
        max-width: 360px;
        margin-bottom: 3rem;
    }
    .auth-perks { display: flex; flex-direction: column; gap: 0.9rem; }
    .auth-perk {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.6);
    }
    .perk-icon {
        width: 32px; height: 32px;
        background: rgba(212,175,55,0.15);
        border: 1px solid rgba(212,175,55,0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    .auth-right {
        background: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
    }
    .auth-form-wrap { width: 100%; max-width: 440px; }
    .auth-form-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.9rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.4rem;
    }
    .auth-form-sub {
        color: var(--text-muted);
        font-size: 0.88rem;
        margin-bottom: 2rem;
    }
    .auth-form-sub a { color: var(--dark); font-weight: 600; text-decoration: underline; }
    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .input-icon-wrap { position: relative; }
    .input-icon {
        position: absolute;
        left: 1rem; top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.85rem;
        pointer-events: none;
    }
    .input-icon-wrap .form-input { padding-left: 2.8rem; }
    .input-toggle {
        position: absolute;
        right: 1rem; top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.85rem;
        background: none;
        border: none;
        padding: 0;
        transition: color 0.15s;
    }
    .input-toggle:hover { color: var(--dark); }

    /* Password strength meter */
    .strength-meter { margin-top: 0.5rem; }
    .strength-bar {
        height: 4px;
        background: var(--border);
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 0.3rem;
    }
    .strength-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.3s, background 0.3s;
        width: 0%;
    }
    .strength-label { font-size: 0.72rem; color: var(--text-muted); }

    /* Terms checkbox */
    .terms-wrap {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        line-height: 1.5;
    }
    .terms-wrap input { accent-color: var(--dark); margin-top: 2px; flex-shrink: 0; }
    .terms-wrap a { color: var(--dark); text-decoration: underline; }

    .btn-register {
        width: 100%;
        padding: 1rem;
        background: var(--dark);
        color: #fff;
        border: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: background 0.2s, transform 0.15s;
        margin-bottom: 1.5rem;
    }
    .btn-register:hover:not(:disabled) { background: var(--dark-2); transform: translateY(-1px); }
    .btn-register:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    .alert-error {
        background: #fde8e8;
        border: 1px solid #f5c6c6;
        color: var(--error);
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
        display: none;
        align-items: center;
        gap: 0.6rem;
    }
    .alert-error.show { display: flex; }

    .auth-switch {
        text-align: center;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    .auth-switch a { color: var(--dark); font-weight: 600; text-decoration: underline; }

    @media (max-width: 768px) {
        .auth-page  { grid-template-columns: 1fr; }
        .auth-left  { display: none; }
        .auth-right { min-height: 100vh; }
        .form-row-2 { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
<div id="page-loader">
    <span class="loader-dot"></span>
    <span class="loader-dot"></span>
    <span class="loader-dot"></span>
</div>
<div id="toast-container"></div>

<div class="auth-page">

    <!-- ── Left panel ──────────────────────────────────────── -->
    <div class="auth-left">
        <a href="../index.php" class="auth-brand"><?= SITE_NAME ?></a>
        <h1 class="auth-left-title">
            Join the<br><span>Style</span><br>Community
        </h1>
        <p class="auth-left-sub">
            Create your free account and unlock a world of curated fashion, exclusive deals and a seamless shopping experience.
        </p>
        <div class="auth-perks">
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-shipping-fast"></i></div>
                Free shipping on your first order
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-percent"></i></div>
                Members-only discounts & early access
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-history"></i></div>
                Full order history & easy tracking
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-heart"></i></div>
                Wishlist & save for later
            </div>
        </div>
    </div>

    <!-- ── Right panel ─────────────────────────────────────── -->
    <div class="auth-right">
        <div class="auth-form-wrap">

            <h2 class="auth-form-title">Create Account</h2>
            <p class="auth-form-sub">
                Already have an account?
                <a href="login.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">Sign in</a>
            </p>

            <div class="alert-error" id="regError">
                <i class="fas fa-exclamation-circle"></i>
                <span id="regErrorMsg">Something went wrong.</span>
            </div>

            <!-- Name row -->
            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input class="form-input" type="text" id="regFirst"
                               placeholder="First name" autocomplete="given-name">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input class="form-input" type="text" id="regLast"
                               placeholder="Last name" autocomplete="family-name">
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-envelope input-icon"></i>
                    <input class="form-input" type="email" id="regEmail"
                           placeholder="you@example.com" autocomplete="email">
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label">Password *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input class="form-input" type="password" id="regPassword"
                           placeholder="Min 8 characters"
                           autocomplete="new-password"
                           oninput="checkStrength(this.value)">
                    <button class="input-toggle" type="button"
                            onclick="togglePass('regPassword', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="strength-meter">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel"></span>
                </div>
            </div>

            <!-- Confirm password -->
            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input class="form-input" type="password" id="regConfirm"
                           placeholder="Re-enter your password"
                           autocomplete="new-password">
                    <button class="input-toggle" type="button"
                            onclick="togglePass('regConfirm', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Terms -->
            <label class="terms-wrap">
                <input type="checkbox" id="regTerms">
                I agree to the <a href="#" target="_blank">Terms of Service</a>
                and <a href="#" target="_blank">Privacy Policy</a>
            </label>

            <button class="btn-register" id="regBtn" onclick="doRegister()">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <div class="auth-switch">
                Already a member?
                <a href="login.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">
                    Sign in here
                </a>
            </div>

        </div>
    </div>
</div>

<script>
const REDIRECT = <?= json_encode($redirect ?: '../index.php') ?>;

// ── Password visibility ────────────────────────────────────
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Password strength ──────────────────────────────────────
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score   = 0;
    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const levels = [
        { pct: '0%',   color: 'transparent', text: '' },
        { pct: '25%',  color: '#e74c3c',     text: 'Weak' },
        { pct: '50%',  color: '#e67e22',     text: 'Fair' },
        { pct: '75%',  color: '#f1c40f',     text: 'Good' },
        { pct: '100%', color: '#2d7a4f',     text: 'Strong' },
    ];
    const l = levels[score];
    fill.style.width      = l.pct;
    fill.style.background = l.color;
    label.textContent     = l.text;
    label.style.color     = l.color;
}

// ── Register submit ────────────────────────────────────────
function doRegister() {
    const first    = document.getElementById('regFirst').value.trim();
    const last     = document.getElementById('regLast').value.trim();
    const email    = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirm  = document.getElementById('regConfirm').value;
    const terms    = document.getElementById('regTerms').checked;
    const btn      = document.getElementById('regBtn');
    const errBox   = document.getElementById('regError');
    const errMsg   = document.getElementById('regErrorMsg');

    errBox.classList.remove('show');

    // Client-side validation
    if (!first || !last) {
        return showErr(errBox, errMsg, 'Please enter your first and last name.');
    }
    if (!email) {
        return showErr(errBox, errMsg, 'Please enter a valid email address.');
    }
    if (password.length < 8) {
        return showErr(errBox, errMsg, 'Password must be at least 8 characters.');
    }
    if (password !== confirm) {
        return showErr(errBox, errMsg, 'Passwords do not match.');
    }
    if (!terms) {
        return showErr(errBox, errMsg, 'Please accept the Terms of Service to continue.');
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';

    fetch('../api/auth/register.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ first_name: first, last_name: last, email, password })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Account created!';
            setTimeout(() => window.location.href = REDIRECT, 800);
        } else {
            showErr(errBox, errMsg, d.message || 'Registration failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        }
    })
    .catch(() => {
        showErr(errBox, errMsg, 'Connection error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
    });
}

function showErr(box, msgEl, msg) {
    msgEl.textContent = msg;
    box.classList.add('show');
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Enter key ─────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doRegister();
});

// ── Page loader ────────────────────────────────────────────
function showToast(msg, type='info') {
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type==='success'?'✓':'✕'}</span><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('fade-out'); setTimeout(() => t.remove(), 300); }, 3000);
}
window.addEventListener('load', () => {
    const loader = document.getElementById('page-loader');
    if (loader) { loader.classList.add('hidden'); setTimeout(() => loader.remove(), 400); }
});
</script>
</body>
</html>