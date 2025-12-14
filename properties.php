<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

/**
 * --------------------------------------------------------------
 * Load saved property IDs for logged-in user
 * --------------------------------------------------------------
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
 * Read filter parameters from query string
 * --------------------------------------------------------------
 */
$city     = trim($_GET['city']      ?? '');
$type     = trim($_GET['type']      ?? '');
$minPrice = trim($_GET['min_price'] ?? '');
$maxPrice = trim($_GET['max_price'] ?? '');

/**
 * --------------------------------------------------------------
 * Pagination settings
 * --------------------------------------------------------------
 */
$perPage = 12;
$page    = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

/**
 * --------------------------------------------------------------
 * Build WHERE + params (shared between COUNT and SELECT)
 * --------------------------------------------------------------
 */
$whereSql = 'WHERE p.status = "approved" AND p.is_sold = 0';
$params   = [];

// Exclude current user's properties from the search results
if (isLoggedIn()) {
    $whereSql .= ' AND p.user_id <> :current_user_id';
    $params[':current_user_id'] = currentUserId();
}

// City filter (partial match)
if ($city !== '') {
    $whereSql .= ' AND p.city LIKE :city';
    $params[':city'] = '%' . $city . '%';
}

// Listing type filter (sale / rent)
if ($type !== '') {
    $whereSql .= ' AND p.listing_type = :type';
    $params[':type'] = $type;
}

// Minimum price filter
if ($minPrice !== '' && is_numeric($minPrice)) {
    $whereSql .= ' AND p.price >= :min_price';
    $params[':min_price'] = (float) $minPrice;
}

// Maximum price filter
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $whereSql .= ' AND p.price <= :max_price';
    $params[':max_price'] = (float) $maxPrice;
}

/**
 * --------------------------------------------------------------
 * Count total matching properties (for pagination)
 * --------------------------------------------------------------
 */
$countSql = 'SELECT COUNT(*) FROM properties p ' . $whereSql;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalProperties = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalProperties / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

/**
 * --------------------------------------------------------------
 * Select properties with cover image + ordering + LIMIT/OFFSET
 * --------------------------------------------------------------
 */
$sql = '
    SELECT p.*,
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
    ' . $whereSql . '
    ORDER BY
        p.is_featured DESC,
        p.featured_until DESC,
        p.created_at DESC
    LIMIT :limit OFFSET :offset
';

$stmt = $pdo->prepare($sql);

// Bind filter params
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination params
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * --------------------------------------------------------------
 * Pagination links base URL (preserve filters)
 * --------------------------------------------------------------
 */
$queryParams = $_GET;
unset($queryParams['page']);
$baseQuery = http_build_query($queryParams);
$paginationBaseUrl = BASE_URL . '/properties.php';
if ($baseQuery !== '') {
    $paginationBaseUrl .= '?' . $baseQuery . '&';
} else {
    $paginationBaseUrl .= '?';
}

$pageTitle = 'Properties';
require_once __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1 style="margin-bottom:0.25rem;">All properties</h1>
        <p style="margin:0;opacity:0.8;font-size:0.9rem;">
            Browse every property currently listed.
        </p>
    </div>

    <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/property-create.php" class="btn btn-primary">
            Post property
        </a>
    <?php endif; ?>
</div>

<section class="section-card">
    <!-- Search / filter form -->
    <form method="get" style="margin-bottom:1.25rem;">
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

        <button type="submit" class="btn btn-primary">
            Filter
        </button>
    </form>

    <?php if (empty($properties)): ?>
        <p style="opacity:0.8;">No properties found. Try changing your filters or post a new property.</p>
    <?php else: ?>
        <div class="properties-grid">
            <?php foreach ($properties as $property): ?>
                <?php
                // Compute "currently featured" state for the badge
                $isFeaturedNow = false;
                if (!empty($property['is_featured']) && (int) $property['is_featured'] === 1) {
                    if (empty($property['featured_until'])) {
                        $isFeaturedNow = true;
                    } else {
                        $isFeaturedNow = ($property['featured_until'] > date('Y-m-d H:i:s'));
                    }
                }
                ?>
                <article class="property-card">
                    <?php if (!empty($property['cover_image'])): ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int) $property['id'] ?>">
                            <img
                                src="<?= UPLOAD_URL . '/' . e($property['cover_image']) ?>"
                                class="property-card__image"
                                alt="<?= e($property['title']) ?>">
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/property.php?id=<?= (int) $property['id'] ?>">
                            <div class="property-card__image"></div>
                        </a>
                    <?php endif; ?>

                    <div class="property-card__body">
                        <h3 class="property-card__title">
                            <a href="<?= BASE_URL ?>/property.php?id=<?= (int) $property['id'] ?>">
                                <?= e($property['title']) ?>
                            </a>
                        </h3>
                        <div class="property-card__meta">
                            <?= e(ucfirst($property['listing_type'])) ?> · <?= e($property['city']) ?>
                        </div>
                        <div class="property-card__price">
                            $<?= number_format((float) $property['price'], 0) ?>
                        </div>

                        <!-- Featured chip -->
                        <?php if ($isFeaturedNow): ?>
                            <div class="profile-chip-row" style="margin-top:0.3rem;">
                                <span class="chip">Featured</span>
                            </div>
                        <?php endif; ?>

                        <!-- Save / Unsave button (for non-owners) -->
                        <?php if (isLoggedIn() && (int) $property['user_id'] !== currentUserId()): ?>
                            <?php
                            $isSaved = in_array($property['id'], $savedPropertyIds ?? [], true);
                            ?>
                            <form
                                method="post"
                                action="<?= BASE_URL ?>/toggle-save.php"
                                class="js-save-form"
                                style="margin-top:0.35rem;">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" name="property_id" value="<?= (int) $property['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                <button
                                    type="submit"
                                    class="btn"
                                    data-save-button="1"
                                    data-saved="<?= $isSaved ? '1' : '0' ?>"
                                    style="font-size:0.8rem;padding:0.3rem 0.9rem;">
                                    <?= $isSaved ? '★ Saved' : '☆ Save' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Edit button for owner -->
                        <?php if (isLoggedIn() && (int) $property['user_id'] === currentUserId()): ?>
                            <div style="margin-top:0.5rem;">
                                <a
                                    href="<?= BASE_URL ?>/property-edit.php?id=<?= (int) $property['id'] ?>"
                                    class="btn"
                                    style="font-size:0.8rem;padding:0.4rem 0.9rem;">
                                    Edit listing
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php
            $from = $totalProperties ? $offset + 1 : 0;
            $to   = min($offset + $perPage, $totalProperties);
            ?>
            <nav aria-label="Pages"
                style="margin-top:1.25rem;display:flex;align-items:center;justify-content:space-between;font-size:0.86rem;">
                <span style="opacity:0.8;">
                    Showing <?= $from ?> – <?= $to ?> of <?= $totalProperties ?> properties
                </span>
                <div style="display:flex;gap:0.4rem;">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-secondary btn-sm"
                            href="<?= e($paginationBaseUrl . 'page=' . ($page - 1)) ?>">&larr; Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-secondary btn-sm"
                            href="<?= e($paginationBaseUrl . 'page=' . ($page + 1)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>