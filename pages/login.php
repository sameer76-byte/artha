<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in — redirect
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
    <title>Login — <?= SITE_NAME ?></title>
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

    /* ── Left panel ──────────────────────────────────────── */
    .auth-left {
        background: var(--dark);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        padding: 5rem;
        position: relative;
        overflow: hidden;
    }
    .auth-left::before {
        content: '';
        position: absolute;
        width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(212,175,55,0.12) 0%, transparent 70%);
        top: -100px; right: -100px;
        pointer-events: none;
    }
    .auth-left::after {
        content: '';
        position: absolute;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(212,175,55,0.08) 0%, transparent 70%);
        bottom: -50px; left: 100px;
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
    .auth-brand span { color: var(--accent); }
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

    /* ── Right panel (form) ──────────────────────────────── */
    .auth-right {
        background: var(--light);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
    }
    .auth-form-wrap {
        width: 100%;
        max-width: 420px;
    }
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
        margin-bottom: 2.2rem;
    }
    .auth-form-sub a { color: var(--dark); font-weight: 600; text-decoration: underline; }

    /* Input with icon */
    .input-icon-wrap {
        position: relative;
    }
    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.85rem;
        pointer-events: none;
    }
    .input-icon-wrap .form-input {
        padding-left: 2.8rem;
    }
    .input-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
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

    /* Remember + forgot row */
    .form-row-inline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        font-size: 0.83rem;
    }
    .remember-wrap {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        color: var(--text);
    }
    .remember-wrap input { accent-color: var(--dark); }
    .forgot-link {
        color: var(--text-muted);
        text-decoration: none;
        transition: color 0.15s;
    }
    .forgot-link:hover { color: var(--dark); text-decoration: underline; }

    /* Submit button */
    .btn-login {
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
    .btn-login:hover:not(:disabled) {
        background: var(--dark-2);
        transform: translateY(-1px);
    }
    .btn-login:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* Divider */
    .or-divider {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        font-size: 0.78rem;
        color: var(--text-muted);
    }
    .or-divider::before,
    .or-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    /* Error alert */
    .alert-error {
        background: #fde8e8;
        border: 1px solid #f5c6c6;
        color: var(--error);
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        display: none;
    }
    .alert-error.show { display: flex; }
    .alert-success {
        background: #d4f0e0;
        border: 1px solid #a8ddc0;
        color: var(--success);
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
        display: none;
    }
    .alert-success.show { display: block; }

    /* Register link at bottom */
    .auth-switch {
        text-align: center;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    .auth-switch a { color: var(--dark); font-weight: 600; text-decoration: underline; }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 768px) {
        .auth-page { grid-template-columns: 1fr; }
        .auth-left  { display: none; }
        .auth-right { min-height: 100vh; }
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

    <!-- ── Left panel ──────────────────────────────────── -->
    <div class="auth-left">
        <a href="../index.php" class="auth-brand"><?= SITE_NAME ?></a>
        <h1 class="auth-left-title">
            Welcome<br>Back to<br><span>Your Style</span>
        </h1>
        <p class="auth-left-sub">
            Log in to access your orders, saved addresses, wishlist and exclusive member offers.
        </p>
        <div class="auth-perks">
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-box"></i></div>
                Track your orders in real time
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-heart"></i></div>
                Save items to your wishlist
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-tag"></i></div>
                Get exclusive member discounts
            </div>
            <div class="auth-perk">
                <div class="perk-icon"><i class="fas fa-undo"></i></div>
                Easy one-click reorders
            </div>
        </div>
    </div>

    <!-- ── Right panel (form) ──────────────────────────── -->
    <div class="auth-right">
        <div class="auth-form-wrap">

            <h2 class="auth-form-title">Sign In</h2>
            <p class="auth-form-sub">
                Don't have an account?
                <a href="register.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">Create one free</a>
            </p>

            <!-- Alerts -->
            <div class="alert-error" id="loginError">
                <i class="fas fa-exclamation-circle"></i>
                <span id="loginErrorMsg">Invalid email or password.</span>
            </div>
            <div class="alert-success" id="loginSuccess">
                Login successful! Redirecting…
            </div>

            <!-- Form -->
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-envelope input-icon"></i>
                    <input class="form-input"
                           type="email"
                           id="loginEmail"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input class="form-input"
                           type="password"
                           id="loginPassword"
                           placeholder="Your password"
                           autocomplete="current-password"
                           required>
                    <button class="input-toggle" type="button"
                            onclick="togglePass('loginPassword', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-row-inline">
                <label class="remember-wrap">
                    <input type="checkbox" id="rememberMe"> Remember me
                </label>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button class="btn-login" id="loginBtn" onclick="doLogin()">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>

            <div class="or-divider">or continue as</div>

            <a href="../index.php"
               style="display:flex;align-items:center;justify-content:center;gap:0.5rem;
                      padding:0.85rem;border:1.5px solid var(--border);background:#fff;
                      font-size:0.85rem;color:var(--text);text-decoration:none;
                      transition:border-color 0.2s;font-family:'DM Sans',sans-serif"
               onmouseover="this.style.borderColor='var(--dark)'"
               onmouseout="this.style.borderColor='var(--border)'">
                <i class="fas fa-user-slash" style="color:var(--text-muted)"></i>
                Guest — Browse without logging in
            </a>

            <div class="auth-switch">
                New to <?= SITE_NAME ?>?
                <a href="register.php<?= $redirect ? '?redirect='.urlencode($redirect) : '' ?>">
                    Create a free account
                </a>
            </div>

        </div>
    </div>
</div>

<script>
const REDIRECT = <?= json_encode($redirect ?: '../index.php') ?>;

// ── Toggle password visibility ────────────────────────────
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Login submit ──────────────────────────────────────────
function doLogin() {
    const email    = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const btn      = document.getElementById('loginBtn');
    const errBox   = document.getElementById('loginError');
    const errMsg   = document.getElementById('loginErrorMsg');
    const sucBox   = document.getElementById('loginSuccess');

    // Basic client validation
    if (!email || !password) {
        showAlert(errBox, errMsg, 'Please enter your email and password.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
    errBox.classList.remove('show');

    fetch('../api/auth/login.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ email, password })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            sucBox.classList.add('show');
            btn.innerHTML = '<i class="fas fa-check"></i> Success!';
            setTimeout(() => {
                window.location.href = REDIRECT;
            }, 800);
        } else {
            showAlert(errBox, errMsg, d.message || 'Invalid email or password.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    })
    .catch(() => {
        showAlert(errBox, errMsg, 'Connection error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    });
}

function showAlert(box, msgEl, msg) {
    msgEl.textContent = msg;
    box.classList.add('show');
}

// ── Enter key submits form ─────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
});

// ── Toast + page loader (from header) ─────────────────────
function showToast(msg, type = 'info') {
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