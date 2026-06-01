<!-- includes/footer.php -->
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <div class="footer-logo"><?= SITE_NAME ?></div>
            <p class="footer-tagline">Fashion that speaks for itself. Curated styles delivered to your door.</p>
            <div class="footer-socials">
                <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-btn"><i class="fab fa-pinterest"></i></a>
            </div>
        </div>
        <div>
            <div class="footer-heading">Shop</div>
            <ul class="footer-links">
                <li><a href="pages/shop.php">All Products</a></li>
                <li><a href="pages/shop.php?featured=1">Featured</a></li>
                <li><a href="pages/shop.php?sort=newest">New Arrivals</a></li>
                <li><a href="pages/shop.php?sort=price_asc">Best Deals</a></li>
            </ul>
        </div>
        <div>
            <div class="footer-heading">Account</div>
            <ul class="footer-links">
                <li><a href="pages/login.php">Login</a></li>
                <li><a href="pages/register.php">Register</a></li>
                <li><a href="pages/account.php">My Orders</a></li>
                <li><a href="pages/wishlist.php">Wishlist</a></li>
            </ul>
        </div>
        <div>
            <div class="footer-heading">Help</div>
            <ul class="footer-links">
                <li><a href="#">FAQ</a></li>
                <li><a href="#">Shipping Policy</a></li>
                <li><a href="#">Return Policy</a></li>
                <li><a href="#">Contact Us</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <span>© <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
            <div class="payment-icons">
                <i class="fab fa-cc-visa"></i>
                <i class="fab fa-cc-mastercard"></i>
                <i class="fab fa-cc-paypal"></i>
                <i class="fab fa-google-pay"></i>
            </div>
        </div>
    </div>
</footer>

<style>
.site-footer { background: var(--dark); color: rgba(255,255,255,0.6); margin-top: 0; }
.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 3rem;
    padding: 4rem 2rem;
}
.footer-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 0.75rem;
}
.footer-tagline { font-size: 0.85rem; line-height: 1.7; margin-bottom: 1.5rem; max-width: 260px; }
.footer-socials { display: flex; gap: 0.6rem; }
.social-btn {
    width: 34px; height: 34px;
    border: 1px solid rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: all 0.2s;
}
.social-btn:hover { border-color: var(--accent); color: var(--accent); }
.footer-heading {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: 1.2rem;
}
.footer-links { display: flex; flex-direction: column; gap: 0.6rem; }
.footer-links a {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: color 0.2s;
}
.footer-links a:hover { color: var(--accent); }
.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 1.2rem 0;
}
.footer-bottom-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
}
.payment-icons { display: flex; gap: 0.8rem; font-size: 1.5rem; color: rgba(255,255,255,0.4); }
@media (max-width: 768px) {
    .footer-grid { grid-template-columns: 1fr 1fr; gap: 2rem; }
    .footer-brand { grid-column: 1/-1; }
    .footer-bottom-inner { flex-direction: column; gap: 0.75rem; text-align: center; }
}
</style>