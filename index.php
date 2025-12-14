<?php

/**
 * index.php
 * --------------------------------------------------------------
 * Public home page for Hijazi Real Estate.
 *
 * Sections:
 *  - Hero + search form (guest vs logged-in layout)
 *  - Featured properties (admin-marked, optional expiry date)
 *  - Latest properties (recent approved listings)
 *  - Testimonials (fake feedback from partials/testimonials-data.php)
 *
 * Notes:
 *  - Real search logic is implemented in properties.php
 *    (this page only forwards GET parameters to that page).
 *  - For logged-in users, own properties are excluded from
 *    featured + latest sections (feels more like recommendations).
 */

require_once __DIR__ . '/config.php';

$pdo = getPDO();

/**
 * --------------------------------------------------------------
 * Load saved property IDs for the logged-in user
 * --------------------------------------------------------------
 *
 * - Used to render "★ Saved" vs "☆ Save" on property cards.
 * - If the user is not logged in, we leave the array empty.
 */
$savedPropertyIds = [];

if (isLoggedIn()) {
    $stmtSaved = $pdo->prepare(
        'SELECT property_id 
         FROM saved_properties 
         WHERE user_id = :uid'
    );
    $stmtSaved->execute([':uid' => currentUserId()]);
    $savedPropertyIds = $stmtSaved->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * --------------------------------------------------------------
 * Featured properties (admin-chosen, with optional expiry)
 * --------------------------------------------------------------
 *
 * Rules:
 *  - status = "approved"
 *  - is_featured = 1
 *  - featured_until is NULL OR in the future
 *  - If user is logged in, we can optionally hide their own
 *    properties from this section (more useful recommendations).
 */
$sqlFeatured = '
    SELECT 
        p.*,
        u.name AS owner_name,
        (
            SELECT file_name 
            FROM property_images pi
            WHERE pi.property_id = p.id
            ORDER BY pi.id ASC
            LIMIT 1
        ) AS cover_image
    FROM properties p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = "approved"
  AND p.is_sold = 0

      AND p.is_featured = 1
      AND (p.featured_until IS NULL OR p.featured_until > NOW())
';

$paramsFeatured = [];

if (isLoggedIn()) {
    $sqlFeatured .= ' AND p.user_id <> :current_user_id';
    $paramsFeatured[':current_user_id'] = currentUserId();
}

$sqlFeatured .= '
    ORDER BY p.featured_until DESC, p.created_at DESC
    LIMIT 6
';

$stmtFeatured = $pdo->prepare($sqlFeatured);
$stmtFeatured->execute($paramsFeatured);
$featuredProperties = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC);

/**
 * --------------------------------------------------------------
 * Latest properties (home page section)
 * --------------------------------------------------------------
 *
 * Rules:
 *  - status = "approved"
 *  - Ordered by newest first
 *  - If the user is logged in, we exclude their own properties,
 *    because they can manage those from the profile page.
 */
$sqlLatest = '
    SELECT 
        p.*,
        u.name AS owner_name,
        (
            SELECT file_name
            FROM property_images pi
            WHERE pi.property_id = p.id
            ORDER BY pi.id ASC
            LIMIT 1
        ) AS cover_image
    FROM properties p
    JOIN users u ON u.id = p.user_id
    WHERE p.status = "approved"
  AND p.is_sold = 0

';

$paramsLatest = [];

if (isLoggedIn()) {
    $sqlLatest .= ' AND p.user_id <> :current_user_id';
    $paramsLatest[':current_user_id'] = currentUserId();
}

$sqlLatest .= '
    ORDER BY p.created_at DESC
    LIMIT 8
';

$stmtLatest = $pdo->prepare($sqlLatest);
$stmtLatest->execute($paramsLatest);
$properties = $stmtLatest->fetchAll(PDO::FETCH_ASSOC);

/**
 * --------------------------------------------------------------
 * Search form values (only for pre-filling fields)
 * --------------------------------------------------------------
 *
 * - When the user submits the form, they are redirected to
 *   properties.php where the actual filtering is applied.
 */
$city     = $_GET['city']      ?? '';
$type     = $_GET['type']      ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';

$pageTitle = 'Home';

require_once __DIR__ . '/partials/header.php';
?>

<?php if (!isLoggedIn()): ?>
    <!-- ================= GUEST VIEW: intro + search ================= -->
    <section class="hero">
        <div>
            <h1 class="hero__title">
                Find your next home in Lebanon.
            </h1>
            <p class="hero__subtitle">
                Search apartments, houses, and commercial spaces for sale and rent —
                tailored to Lebanese lifestyle and cities.
            </p>
            <div class="hero__actions">
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">
                    Get started
                </a>
                <a href="<?= BASE_URL ?>/login.php" class="btn">
                    I already have an account
                </a>
            </div>
        </div>

        <div class="search-card">
            <h2 style="margin-top:0;margin-bottom:0.75rem;font-size:1.15rem;">Search properties</h2>

            <!-- 🔍 Search is handled by properties.php -->
            <form method="get" action="<?= BASE_URL ?>/properties.php">
                <div class="search-grid">
                    <div>
                        <label class="form-label" for="city">City / Area</label>
                        <div class="text-field">
                            <input
                                type="text"
                                id="city"
                                name="city"
                                placeholder="Tripoli, Beirut..."
                                value="<?= e($city) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="type">Type</label>
                        <div class="text-field">
                            <select id="type" name="type">
                                <option value="">Any</option>
                                <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For sale</option>
                                <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>>For rent</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="min_price">Min price</label>
                        <div class="text-field">
                            <input
                                type="number"
                                id="min_price"
                                name="min_price"
                                min="0"
                                step="100"
                                value="<?= e($minPrice) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="max_price">Max price</label>
                        <div class="text-field">
                            <input
                                type="number"
                                id="max_price"
                                name="max_price"
                                min="0"
                                step="100"
                                value="<?= e($maxPrice) ?>">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    Search
                </button>
            </form>
        </div>
    </section>
<?php else: ?>
    <!-- ================= LOGGED-IN VIEW: centered search only ================= -->
    <section class="hero hero--center">
        <div class="search-card">
            <h2 style="margin-top:0;margin-bottom:0.75rem;font-size:1.15rem;">Search properties</h2>

            <!-- 🔍 Search is handled by properties.php -->
            <form method="get" action="<?= BASE_URL ?>/properties.php">
                <div class="search-grid">
                    <div>
                        <label class="form-label" for="city">City / Area</label>
                        <div class="text-field">
                            <input
                                type="text"
                                id="city"
                                name="city"
                                placeholder="Tripoli, Beirut..."
                                value="<?= e($city) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="type">Type</label>
                        <div class="text-field">
                            <select id="type" name="type">
                                <option value="">Any</option>
                                <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For sale</option>
                                <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>>For rent</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="min_price">Min price</label>
                        <div class="text-field">
                            <input
                                type="number"
                                id="min_price"
                                name="min_price"
                                min="0"
                                step="100"
                                value="<?= e($minPrice) ?>">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" for="max_price">Max price</label>
                        <div class="text-field">
                            <input
                                type="number"
                                id="max_price"
                                name="max_price"
                                min="0"
                                step="100"
                                value="<?= e($maxPrice) ?>">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    Search
                </button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($featuredProperties)): ?>
    <!-- ================= FEATURED PROPERTIES ================= -->
    <section>
        <div class="page-header">
            <div>
                <h2 style="margin-bottom:0.25rem;">Featured properties</h2>
                <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                    Hand-picked listings highlighted by the platform.
                </p>
            </div>
        </div>

        <div class="properties-grid">
            <?php foreach ($featuredProperties as $property): ?>
                <article class="property-card">
                    <?php if (!empty($property['cover_image'])): ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                            <img src="<?= UPLOAD_URL . '/' . e($property['cover_image']) ?>"
                                class="property-card__image"
                                alt="<?= e($property['title']) ?>">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                            <div class="property-card__image"></div>
                        </a>
                    <?php endif; ?>

                    <div class="property-card__body">
                        <h3 class="property-card__title">
                            <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                                <?= e($property['title']) ?>
                            </a>
                        </h3>
                        <div class="property-card__meta">
                            <?= e(ucfirst($property['listing_type'])) ?> · <?= e($property['city']) ?>
                        </div>
                        <div class="property-card__price">
                            $<?= number_format((float)$property['price'], 0) ?>
                        </div>

                        <?php if (isLoggedIn() && (int)$property['user_id'] !== currentUserId()): ?>
                            <?php $isSaved = in_array($property['id'], $savedPropertyIds ?? [], true); ?>
                            <form method="post"
                                action="<?= BASE_URL ?>/toggle-save.php"
                                class="js-save-form"
                                style="margin-top:0.35rem;">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit"
                                    class="btn"
                                    data-save-button="1"
                                    data-saved="<?= $isSaved ? '1' : '0' ?>"
                                    style="font-size:0.8rem;padding:0.3rem 0.9rem;">
                                    <?= $isSaved ? '★ Saved' : '☆ Save' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ================= LATEST PROPERTIES ================= -->
<section>
    <div class="page-header">
        <div>
            <h2 style="margin-bottom:0.25rem;">Latest properties</h2>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                A selection of the newest listings.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/properties.php" class="btn">
            View all properties
        </a>
    </div>

    <?php if (empty($properties)): ?>
        <p style="opacity:0.8;">No properties found yet. Be the first to post a property.</p>
    <?php else: ?>
        <div class="properties-grid">
            <?php foreach ($properties as $property): ?>
                <article class="property-card">
                    <?php if (!empty($property['cover_image'])): ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                            <img src="<?= UPLOAD_URL . '/' . e($property['cover_image']) ?>"
                                class="property-card__image"
                                alt="<?= e($property['title']) ?>">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                            <div class="property-card__image"></div>
                        </a>
                    <?php endif; ?>

                    <div class="property-card__body">
                        <h3 class="property-card__title">
                            <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$property['id'] ?>">
                                <?= e($property['title']) ?>
                            </a>
                        </h3>
                        <div class="property-card__meta">
                            <?= e(ucfirst($property['listing_type'])) ?> · <?= e($property['city']) ?>
                        </div>
                        <div class="property-card__price">
                            $<?= number_format((float)$property['price'], 0) ?>
                        </div>

                        <?php if (isLoggedIn() && (int)$property['user_id'] !== currentUserId()): ?>
                            <?php $isSaved = in_array($property['id'], $savedPropertyIds ?? [], true); ?>
                            <form method="post"
                                action="<?= BASE_URL ?>/toggle-save.php"
                                class="js-save-form"
                                style="margin-top:0.35rem;">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit"
                                    class="btn"
                                    data-save-button="1"
                                    data-saved="<?= $isSaved ? '1' : '0' ?>"
                                    style="font-size:0.8rem;padding:0.3rem 0.9rem;">
                                    <?= $isSaved ? '★ Saved' : '☆ Save' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/testimonials-data.php'; ?>

<section class="testimonials-section">
    <div class="section-card">
        <div class="page-header">
            <div>
                <h2 style="margin-bottom:0.25rem;">What people say</h2>
                <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                    A few words from users who tried Hijazi Real Estate.
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