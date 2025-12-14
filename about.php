<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/partials/testimonials-data.php';
?>

<section class="section-card">
    <div class="page-header" style="margin-bottom:1.25rem;">
        <div>
            <h1 style="margin-bottom:0.25rem;">About Othman Real Estate</h1>
            <p style="margin:0;opacity:0.8;font-size:0.95rem;">
                A Lebanese-focused platform for discovering and managing real-estate listings.
            </p>
        </div>
    </div>

    <p style="line-height:1.6;font-size:0.95rem;">
        Othman Real Estate is a senior project built to explore how a modern web platform can
        support the Lebanese real-estate market. The goal is to provide a clean, secure, and
        localized experience for people who want to <strong>rent</strong>, <strong>buy</strong>,
        or <strong>list</strong> properties across Lebanon.
    </p>

    <p style="line-height:1.6;font-size:0.95rem;">
        The website is designed with the Material 3 design system, using a PHP &amp; MySQL backend.
        It focuses on performance, security (no hard-coded credentials, prepared statements),
        and a smooth UI/UX that works in both light and dark modes.
    </p>

    <h2 style="margin-top:1.75rem;margin-bottom:0.75rem;">What you can do on this website</h2>
    <ul style="line-height:1.6;font-size:0.95rem;padding-left:1.2rem;">
        <li>Search properties by city, type (sale / rent), and price range.</li>
        <li>Create an account and securely log in to manage your own listings.</li>
        <li>Post properties with multiple photos and detailed information.</li>
        <li>Edit your listings at any time as prices or details change.</li>
        <li>Browse a feed of the latest properties added to the platform.</li>
    </ul>
</section>

<section class="testimonials-section">
    <div class="section-card">
        <div class="page-header">
            <div>
                <h2 style="margin-bottom:0.25rem;">People&rsquo;s feedback</h2>
                <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                    Some fake feedback to demonstrate how real users might experience the platform.
                </p>
            </div>
        </div>

        <div class="testimonials-grid">
            <?php foreach ($TESTIMONIALS as $t): ?>
                <article class="testimonial-card">
                    <div class="testimonial-header">
                        <div class="testimonial-avatar">
                            <?= e(strtoupper($t['name'][0])) ?>
                        </div>
                        <div>
                            <div class="testimonial-name"><?= e($t['name']) ?></div>
                            <div class="testimonial-meta">
                                <?= e($t['location']) ?> · <?= e($t['role']) ?>
                            </div>
                        </div>
                    </div>

                    <p class="testimonial-text">
                        “<?= e($t['text']) ?>”
                    </p>

                    <div class="testimonial-rating">
                        <?php $stars = str_repeat('★', (int)$t['rating']); ?>
                        <span><?= $stars ?></span>
                        <span style="opacity:0.7;font-size:0.8rem;">(<?= (int)$t['rating'] ?>/5)</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>