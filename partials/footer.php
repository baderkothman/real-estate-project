<?php
// partials/footer.php
?>
</main>

<footer class="app-footer">
    <div class="container footer">
        <!-- Top: brand + columns -->
        <div class="footer__top">
            <!-- Brand -->
            <div class="footer__brand">
                <a href="<?= BASE_URL ?>/index.php" class="footer__brand-link">
                    <img
                        src="<?= BASE_URL ?>/assets/img/logo.svg"
                        alt="<?= e(APP_NAME) ?> logo"
                        class="footer__logo">
                    <div>
                        <div class="footer__title"><?= e(APP_NAME) ?></div>
                        <p class="footer__tagline">
                            Modern listings and owner profiles tailored to the Lebanese real estate market.
                        </p>
                    </div>
                </a>
            </div>

            <!-- Columns -->
            <div class="footer__columns">
                <div class="footer__column">
                    <h3 class="footer__heading">Explore</h3>
                    <ul class="footer__list">
                        <li><a href="<?= BASE_URL ?>/properties.php">Properties</a></li>
                        <li><a href="<?= BASE_URL ?>/users.php">Owners</a></li>
                        <li><a href="<?= BASE_URL ?>/pricing.php">Pricing</a></li>
                        <li><a href="<?= BASE_URL ?>/about.php">About</a></li>
                    </ul>
                </div>

                <div class="footer__column">
                    <h3 class="footer__heading">Account</h3>
                    <ul class="footer__list">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?= BASE_URL ?>/profile.php">My profile</a></li>
                            <li><a href="<?= BASE_URL ?>/property-create.php">Post a property</a></li>
                            <li><a href="<?= BASE_URL ?>/profile.php?tab=saved">Saved properties</a></li>
                        <?php else: ?>
                            <li><a href="<?= BASE_URL ?>/login.php">Log in</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">Sign up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer__column">
                    <h3 class="footer__heading">Contact</h3>
                    <ul class="footer__list footer__list--contact">
                        <li>
                            <span>Phone</span>
                            <a href="tel:+96171234567">+961 71 234 567</a>
                        </li>
                        <li>
                            <span>Email</span>
                            <a href="mailto:support@Othman-realestate.com">
                                support@Othman-realestate.com
                            </a>
                        </li>
                        <li>
                            <span>Location</span>
                            <span>Tripoli · Lebanon</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom: social + meta -->
        <div class="footer__bottom">
            <div class="footer__social">
                <!-- replace # with real URLs when you have them -->
                <a href="#" class="footer__social-link" aria-label="Instagram">
                    <img src="<?= BASE_URL ?>/assets/icons/instagram.svg" alt="">
                </a>
                <a href="#" class="footer__social-link" aria-label="Facebook">
                    <img src="<?= BASE_URL ?>/assets/icons/facebook.svg" alt="">
                </a>
                <a href="#" class="footer__social-link" aria-label="LinkedIn">
                    <img src="<?= BASE_URL ?>/assets/icons/linkedin.svg" alt="">
                </a>
                <a href="#" class="footer__social-link" aria-label="WhatsApp">
                    <img src="<?= BASE_URL ?>/assets/icons/whatsapp.svg" alt="">
                </a>
            </div>

            <div class="footer__meta">
                &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
            </div>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/script.js"></script>

</body>

</html>