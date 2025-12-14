<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Plans & pricing';
require_once __DIR__ . '/partials/header.php';

$free   = getPlanLimits('free');
$pro    = getPlanLimits('pro');
$agency = getPlanLimits('agency');

$csrfToken   = getCsrfToken();
$currentPlan = currentUserPlan(); // 'free', 'pro', 'agency'
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 class="hero__title" style="margin-bottom:0.25rem;font-size:1.6rem;">
                Plans &amp; pricing
            </h1>
            <p class="hero__subtitle" style="margin:0;">
                Choose the plan that matches how you use Othman Real Estate.
            </p>
            <?php if (isLoggedIn()): ?>
                <p style="margin-top:0.4rem;font-size:var(--md-font-size-body-sm);color:var(--md-sys-color-on-surface-variant);">
                    Current plan:
                    <span class="chip chip--<?= e($currentPlan) ?>" style="margin-left:0.25rem;">
                        <?= e(ucfirst($currentPlan)) ?>
                    </span>
                </p>
            <?php else: ?>
                <p style="margin-top:0.4rem;font-size:var(--md-font-size-body-sm);color:var(--md-sys-color-on-surface-variant);">
                    <a href="<?= BASE_URL ?>/login.php">Log in</a> or
                    <a href="<?= BASE_URL ?>/register.php">create an account</a> to upgrade.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="properties-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">

        <!-- Free -->
        <article class="property-card">
            <div class="property-card__body">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.25rem;">
                    <div>
                        <h2 class="property-card__title" style="font-size:var(--md-font-size-title);margin-bottom:0.15rem;">
                            Free
                        </h2>
                        <p class="property-card__meta">
                            For individuals posting occasionally.
                        </p>
                    </div>
                    <span class="chip chip--free">Starter</span>
                </div>

                <ul style="font-size:var(--md-font-size-body-sm);line-height:1.5;padding-left:1.1rem;margin:0.4rem 0 0.75rem;">
                    <li><?= $free['max_properties'] ?> active listings</li>
                    <li>Up to <?= $free['max_images'] ?> photos per property</li>
                    <li>Appear in search &amp; latest listings</li>
                    <li>Save properties to your profile</li>
                </ul>

                <p class="property-card__price" style="margin-top:0.25rem;font-size:1rem;">
                    $0 <span style="font-size:0.86rem;color:var(--md-sys-color-on-surface-variant);">/ month</span>
                </p>

                <?php if (isLoggedIn() && $currentPlan === 'free'): ?>
                    <p style="margin-top:0.35rem;font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);">
                        This is your current plan.
                    </p>
                <?php endif; ?>
            </div>
        </article>

        <!-- Pro -->
        <article class="property-card" style="border-width:2px;">
            <div class="property-card__body">

                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.25rem;">
                    <div>
                        <h2 class="property-card__title" style="font-size:var(--md-font-size-title);margin-bottom:0.15rem;">
                            Pro owner
                        </h2>
                        <p class="property-card__meta">
                            For active owners and small investors.
                        </p>
                    </div>
                    <span class="chip chip--pro">
                        <?php if ($currentPlan === 'pro'): ?>
                            Current plan
                        <?php else: ?>
                            Most popular
                        <?php endif; ?>
                    </span>
                </div>

                <ul style="font-size:var(--md-font-size-body-sm);line-height:1.5;padding-left:1.1rem;margin:0.4rem 0 0.75rem;">
                    <li><?= $pro['max_properties'] ?> active listings</li>
                    <li>Up to <?= $pro['max_images'] ?> photos per property</li>
                    <li>Detailed listing statistics</li>
                    <li>Discount on featured boosts</li>
                </ul>

                <div style="margin-top:0.25rem;margin-bottom:0.75rem;">
                    <p class="property-card__price" style="margin:0;font-size:1.05rem;">
                        $30 <span style="font-size:0.86rem;color:var(--md-sys-color-on-surface-variant);">/ month</span>
                    </p>
                    <p style="margin:0.15rem 0 0;font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);">
                        or $60 every 3 months · $300 per year
                    </p>
                </div>

                <p style="font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);margin-bottom:0.75rem;">
                    Secure payment via Stripe.
                </p>

                <?php if (!isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="width:100%;justify-content:center;">
                        Log in to upgrade
                    </a>
                <?php elseif ($currentPlan === 'pro'): ?>
                    <p style="margin:0;font-size:var(--md-font-size-label);color:var(--md-sys-color-success);">
                        ✅ You’re already on the Pro owner plan.
                    </p>
                <?php elseif ($currentPlan === 'agency'): ?>
                    <p style="margin:0;font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);">
                        You already have a higher plan (Agency).
                    </p>
                <?php else: ?>
                    <!-- Upgrade to Pro via Stripe with selected billing -->
                    <form method="post" action="<?= BASE_URL ?>/create-checkout-session.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="plan" value="pro">

                        <div class="form-field" style="margin-bottom:0.8rem;">
                            <div class="form-label">Billing period</div>
                            <div class="text-field">
                                <select name="billing">
                                    <option value="month">Monthly – $30 / month</option>
                                    <option value="quarter">Every 3 months – $60 / 3 months</option>
                                    <option value="year">Yearly – $300 / year</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            Upgrade to Pro
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </article>

        <!-- Agency -->
        <article class="property-card">
            <div class="property-card__body">

                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.25rem;">
                    <div>
                        <h2 class="property-card__title" style="font-size:var(--md-font-size-title);margin-bottom:0.15rem;">
                            Agency
                        </h2>
                        <p class="property-card__meta">
                            For real estate agencies and brokers.
                        </p>
                    </div>
                    <span class="chip chip--agency">For teams</span>
                </div>

                <ul style="font-size:var(--md-font-size-body-sm);line-height:1.5;padding-left:1.1rem;margin:0.4rem 0 0.75rem;">
                    <li>Up to <?= $agency['max_properties'] ?> active listings</li>
                    <li>Up to <?= $agency['max_images'] ?> photos per property</li>
                    <li>Agency branding &amp; badge</li>
                    <li>Priority placement in owner search</li>
                </ul>

                <div style="margin-top:0.25rem;margin-bottom:0.75rem;">
                    <p class="property-card__price" style="margin:0;font-size:1.05rem;">
                        $60 <span style="font-size:0.86rem;color:var(--md-sys-color-on-surface-variant);">/ month</span>
                    </p>
                    <p style="margin:0.15rem 0 0;font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);">
                        or $120 every 3 months · $600 per year
                    </p>
                </div>

                <p style="font-size:var(--md-font-size-label);color:var(--md-sys-color-on-surface-variant);margin-bottom:0.75rem;">
                    Secure payment via Stripe.
                </p>

                <?php if (!isLoggedIn()): ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="width:100%;justify-content:center;">
                        Log in to upgrade
                    </a>
                <?php elseif ($currentPlan === 'agency'): ?>
                    <p style="margin:0;font-size:var(--md-font-size-label);color:var(--md-sys-color-success);">
                        ✅ You’re already on the Agency plan.
                    </p>
                <?php else: ?>
                    <!-- Upgrade to Agency via Stripe with selected billing -->
                    <form method="post" action="<?= BASE_URL ?>/create-checkout-session.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="plan" value="agency">

                        <div class="form-field" style="margin-bottom:0.8rem;">
                            <div class="form-label">Billing period</div>
                            <div class="text-field">
                                <select name="billing">
                                    <option value="month">Monthly – $60 / month</option>
                                    <option value="quarter">Every 3 months – $120 / 3 months</option>
                                    <option value="year">Yearly – $600 / year</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                            Upgrade to Agency
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>