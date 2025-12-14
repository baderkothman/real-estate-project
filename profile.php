<?php
require_once __DIR__ . '/config.php';

requireLogin();
$pdo    = getPDO();
$user   = currentUser();
$userId = currentUserId();

if (!$user || !$userId) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Active tab
$tab = $_GET['tab'] ?? 'mine';
if (!in_array($tab, ['mine', 'sold', 'saved'], true)) {
    $tab = 'mine';
}

// Pagination config
$perPage = 12;

// -----------------------------
// My (active) properties pagination (is_sold = 0)
// -----------------------------
$pageMine   = max(1, (int)($_GET['page_mine'] ?? 1));
$offsetMine = ($pageMine - 1) * $perPage;

// Count my active properties
$countMineSql = '
    SELECT COUNT(*)
    FROM properties
    WHERE user_id = :uid
      AND (is_sold = 0 OR is_sold IS NULL)
';
$stmtCountMine = $pdo->prepare($countMineSql);
$stmtCountMine->execute([':uid' => $userId]);
$totalMine      = (int)$stmtCountMine->fetchColumn();
$totalPagesMine = max(1, (int)ceil($totalMine / $perPage));
if ($pageMine > $totalPagesMine) {
    $pageMine   = $totalPagesMine;
    $offsetMine = ($pageMine - 1) * $perPage;
}

// Fetch my active properties with cover image
$sqlMine = '
    SELECT p.*,
           (
               SELECT file_name
               FROM property_images pi
               WHERE pi.property_id = p.id
               ORDER BY pi.id ASC
               LIMIT 1
           ) AS cover_image
    FROM properties p
    WHERE p.user_id = :uid
      AND (p.is_sold = 0 OR p.is_sold IS NULL)
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
';
$stmtMine = $pdo->prepare($sqlMine);
$stmtMine->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmtMine->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtMine->bindValue(':offset', $offsetMine, PDO::PARAM_INT);
$stmtMine->execute();
$myProperties = $stmtMine->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Sold properties pagination (is_sold = 1)
// -----------------------------
$pageSold   = max(1, (int)($_GET['page_sold'] ?? 1));
$offsetSold = ($pageSold - 1) * $perPage;

$countSoldSql = '
    SELECT COUNT(*)
    FROM properties
    WHERE user_id = :uid
      AND is_sold = 1
';
$stmtCountSold = $pdo->prepare($countSoldSql);
$stmtCountSold->execute([':uid' => $userId]);
$totalSold      = (int)$stmtCountSold->fetchColumn();
$totalPagesSold = max(1, (int)ceil($totalSold / $perPage));
if ($pageSold > $totalPagesSold) {
    $pageSold   = $totalPagesSold;
    $offsetSold = ($pageSold - 1) * $perPage;
}

// Fetch sold properties
$sqlSold = '
    SELECT p.*,
           (
               SELECT file_name
               FROM property_images pi
               WHERE pi.property_id = p.id
               ORDER BY pi.id ASC
               LIMIT 1
           ) AS cover_image
    FROM properties p
    WHERE p.user_id = :uid
      AND p.is_sold = 1
    ORDER BY p.sold_at DESC, p.created_at DESC
    LIMIT :limit OFFSET :offset
';
$stmtSold = $pdo->prepare($sqlSold);
$stmtSold->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmtSold->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtSold->bindValue(':offset', $offsetSold, PDO::PARAM_INT);
$stmtSold->execute();
$soldProperties = $stmtSold->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Saved properties pagination
// -----------------------------
$pageSaved   = max(1, (int)($_GET['page_saved'] ?? 1));
$offsetSaved = ($pageSaved - 1) * $perPage;

// Count saved
$countSavedSql = '
    SELECT COUNT(*)
    FROM saved_properties sp
    JOIN properties p ON p.id = sp.property_id
    WHERE sp.user_id = :uid
      AND p.status = "approved"
      AND p.is_sold = 0
';
$stmtCountSaved = $pdo->prepare($countSavedSql);
$stmtCountSaved->execute([':uid' => $userId]);
$totalSaved      = (int)$stmtCountSaved->fetchColumn();
$totalPagesSaved = max(1, (int)ceil($totalSaved / $perPage));
if ($pageSaved > $totalPagesSaved) {
    $pageSaved   = $totalPagesSaved;
    $offsetSaved = ($pageSaved - 1) * $perPage;
}

// Fetch saved properties with cover image
$sqlSaved = '
    SELECT p.*,
           (
               SELECT file_name
               FROM property_images pi
               WHERE pi.property_id = p.id
               ORDER BY pi.id ASC
               LIMIT 1
           ) AS cover_image
    FROM saved_properties sp
    JOIN properties p ON p.id = sp.property_id
    WHERE sp.user_id = :uid
      AND p.status = "approved"
      AND p.is_sold = 0
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
';
$stmtSavedProps = $pdo->prepare($sqlSaved);
$stmtSavedProps->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmtSavedProps->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtSavedProps->bindValue(':offset', $offsetSaved, PDO::PARAM_INT);
$stmtSavedProps->execute();
$savedProperties = $stmtSavedProps->fetchAll(PDO::FETCH_ASSOC);

// For marking Saved buttons
$savedPropertyIds = [];
$stmtSavedIds = $pdo->prepare('
    SELECT property_id
    FROM saved_properties
    WHERE user_id = :uid
');
$stmtSavedIds->execute([':uid' => $userId]);
$savedPropertyIds = $stmtSavedIds->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'My profile';
require_once __DIR__ . '/partials/header.php';

// Helper for pagination buttons (inline, simple)
function render_profile_pagination(
    string $tab,
    string $paramName,
    int $current,
    int $total
): void {
    if ($total <= 1) {
        return;
    }
?>
    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.35rem;">
        <?php if ($current > 1): ?>
            <a
                class="btn btn-secondary btn-sm"
                href="<?= BASE_URL ?>/profile.php?tab=<?= e($tab) ?>&<?= e($paramName) ?>=<?= $current - 1 ?>">
                ← Prev
            </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total; $i++): ?>
            <a
                class="btn btn-sm <?= $i === $current ? 'btn-primary' : '' ?>"
                href="<?= BASE_URL ?>/profile.php?tab=<?= e($tab) ?>&<?= e($paramName) ?>=<?= $i ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($current < $total): ?>
            <a
                class="btn btn-secondary btn-sm"
                href="<?= BASE_URL ?>/profile.php?tab=<?= e($tab) ?>&<?= e($paramName) ?>=<?= $current + 1 ?>">
                Next →
            </a>
        <?php endif; ?>
    </div>
<?php
}
?>

<section class="section-card">
    <!-- Profile header -->
    <div class="profile-header">
        <div class="profile-header__left">
            <?php if (!empty($user['profile_image'])): ?>
                <img
                    src="<?= PROFILE_UPLOAD_URL . '/' . e($user['profile_image']) ?>"
                    alt="<?= e($user['name']) ?>"
                    class="profile-avatar-lg">
            <?php else: ?>
                <div class="testimonial-avatar">
                    <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div class="profile-main">
                <div class="profile-name"><?= e($user['name']) ?></div>
                <?php if (!empty($user['email'])): ?>
                    <div class="profile-email"><?= e($user['email']) ?></div>
                <?php endif; ?>
                <div class="profile-meta">
                    Member since <?= isset($user['created_at'])
                                        ? e(date('M Y', strtotime($user['created_at'])))
                                        : '—' ?>
                </div>

                <div class="profile-chip-row">
                    <?php
                    $plan      = $user['plan'] ?? 'free';
                    $planClass = 'chip--' . $plan;
                    ?>
                    <span class="chip <?= e($planClass) ?>">
                        Plan: <?= e(ucfirst($plan)) ?>
                    </span>
                    <span class="chip">
                        Listings: <?= (int)$totalMine ?>
                    </span>
                    <span class="chip chip--free">
                        Sold: <?= (int)$totalSold ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($user['bio'])): ?>
            <p class="profile-bio">
                <?= nl2br(e($user['bio'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="profile-tabs">
        <a
            href="<?= BASE_URL ?>/profile.php?tab=mine"
            class="profile-tab <?= $tab === 'mine' ? 'profile-tab--active' : '' ?>">
            My properties
        </a>
        <a
            href="<?= BASE_URL ?>/profile.php?tab=sold"
            class="profile-tab <?= $tab === 'sold' ? 'profile-tab--active' : '' ?>">
            Sold
        </a>
        <a
            href="<?= BASE_URL ?>/profile.php?tab=saved"
            class="profile-tab <?= $tab === 'saved' ? 'profile-tab--active' : '' ?>">
            Saved
        </a>
    </div>

    <!-- Tab content -->
    <?php if ($tab === 'mine'): ?>
        <div style="margin-top:1.25rem;">
            <?php if (empty($myProperties)): ?>
                <p style="opacity:0.8;font-size:0.95rem;">
                    You don’t have any active properties yet.
                    <a href="<?= BASE_URL ?>/property-create.php">Post your first listing</a>.
                </p>
            <?php else: ?>
                <div class="properties-grid">
                    <?php foreach ($myProperties as $p): ?>
                        <?php
                        $isFeaturedNow = false;
                        if (!empty($p['is_featured']) && (int)$p['is_featured'] === 1) {
                            if (empty($p['featured_until'])) {
                                $isFeaturedNow = true;
                            } else {
                                $isFeaturedNow = ($p['featured_until'] > date('Y-m-d H:i:s'));
                            }
                        }
                        ?>
                        <article class="property-card">
                            <?php if (!empty($p['cover_image'])): ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <img
                                        src="<?= UPLOAD_URL . '/' . e($p['cover_image']) ?>"
                                        alt="<?= e($p['title']) ?>"
                                        class="property-card__image">
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <div class="property-card__image"></div>
                                </a>
                            <?php endif; ?>

                            <div class="property-card__body">
                                <h3 class="property-card__title">
                                    <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                        <?= e($p['title']) ?>
                                    </a>
                                </h3>
                                <div class="property-card__meta">
                                    <?= e(ucfirst($p['listing_type'])) ?> · <?= e($p['city']) ?>
                                </div>
                                <div class="property-card__price">
                                    $<?= number_format((float)$p['price'], 0) ?>
                                </div>

                                <div class="profile-chip-row" style="margin-top:0.3rem;">
                                    <span class="chip">
                                        Status: <?= e(ucfirst($p['status'])) ?>
                                    </span>
                                    <?php if ($isFeaturedNow): ?>
                                        <span class="chip">
                                            Featured
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="property-card__actions">
                                    <!-- Mark sold / available -->
                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/toggle-sold.php"
                                        style="display:inline;">
                                        <input type="hidden" name="property_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm">
                                            Mark as sold
                                        </button>
                                    </form>

                                    <a
                                        href="<?= BASE_URL ?>/property-edit.php?id=<?= (int)$p['id'] ?>"
                                        class="btn btn-sm">
                                        Edit
                                    </a>
                                    <a
                                        href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>"
                                        class="btn btn-sm">
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php render_profile_pagination('mine', 'page_mine', $pageMine, $totalPagesMine); ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'sold'): ?>
        <div style="margin-top:1.25rem;">
            <?php if (empty($soldProperties)): ?>
                <p style="opacity:0.8;font-size:0.95rem;">
                    You don’t have any sold properties yet.
                </p>
            <?php else: ?>
                <div class="properties-grid">
                    <?php foreach ($soldProperties as $p): ?>
                        <?php
                        $isFeaturedNow = false;
                        if (!empty($p['is_featured']) && (int)$p['is_featured'] === 1) {
                            if (empty($p['featured_until'])) {
                                $isFeaturedNow = true;
                            } else {
                                $isFeaturedNow = ($p['featured_until'] > date('Y-m-d H:i:s'));
                            }
                        }
                        ?>
                        <article class="property-card">
                            <?php if (!empty($p['cover_image'])): ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <img
                                        src="<?= UPLOAD_URL . '/' . e($p['cover_image']) ?>"
                                        alt="<?= e($p['title']) ?>"
                                        class="property-card__image">
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <div class="property-card__image"></div>
                                </a>
                            <?php endif; ?>

                            <div class="property-card__body">
                                <h3 class="property-card__title">
                                    <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                        <?= e($p['title']) ?>
                                    </a>
                                </h3>
                                <div class="property-card__meta">
                                    <?= e(ucfirst($p['listing_type'])) ?> · <?= e($p['city']) ?>
                                </div>
                                <div class="property-card__price">
                                    $<?= number_format((float)$p['price'], 0) ?>
                                </div>

                                <div class="profile-chip-row" style="margin-top:0.3rem;">
                                    <span class="chip chip--free">
                                        Sold
                                    </span>
                                    <?php if (!empty($p['sold_at'])): ?>
                                        <span class="chip">
                                            Sold on <?= e(date('M j, Y', strtotime($p['sold_at']))) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isFeaturedNow): ?>
                                        <span class="chip">
                                            Was featured
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="property-card__actions">
                                    <!-- Mark available again (toggle-sold handles plan limit) -->
                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/toggle-sold.php"
                                        style="display:inline;">
                                        <input type="hidden" name="property_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm">
                                            Mark as available
                                        </button>
                                    </form>

                                    <a
                                        href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>"
                                        class="btn btn-sm">
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php render_profile_pagination('sold', 'page_sold', $pageSold, $totalPagesSold); ?>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'saved'): ?>
        <div style="margin-top:1.25rem;">
            <?php if (empty($savedProperties)): ?>
                <p style="opacity:0.8;font-size:0.95rem;">
                    You haven’t saved any properties yet.
                    Browse the <a href="<?= BASE_URL ?>/properties.php">properties</a> and tap “Save”.
                </p>
            <?php else: ?>
                <div class="properties-grid">
                    <?php foreach ($savedProperties as $p): ?>
                        <?php
                        $isFeaturedNow = false;
                        if (!empty($p['is_featured']) && (int)$p['is_featured'] === 1) {
                            if (empty($p['featured_until'])) {
                                $isFeaturedNow = true;
                            } else {
                                $isFeaturedNow = ($p['featured_until'] > date('Y-m-d H:i:s'));
                            }
                        }
                        $isSaved = in_array($p['id'], $savedPropertyIds, true);
                        ?>
                        <article class="property-card">
                            <?php if (!empty($p['cover_image'])): ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <img
                                        src="<?= UPLOAD_URL . '/' . e($p['cover_image']) ?>"
                                        alt="<?= e($p['title']) ?>"
                                        class="property-card__image">
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                    <div class="property-card__image"></div>
                                </a>
                            <?php endif; ?>

                            <div class="property-card__body">
                                <h3 class="property-card__title">
                                    <a href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>">
                                        <?= e($p['title']) ?>
                                    </a>
                                </h3>
                                <div class="property-card__meta">
                                    <?= e(ucfirst($p['listing_type'])) ?> · <?= e($p['city']) ?>
                                </div>
                                <div class="property-card__price">
                                    $<?= number_format((float)$p['price'], 0) ?>
                                </div>

                                <?php if ($isFeaturedNow): ?>
                                    <div class="profile-chip-row" style="margin-top:0.3rem;">
                                        <span class="chip">
                                            Featured
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="property-card__actions">
                                    <!-- Toggle save -->
                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/toggle-save.php"
                                        class="js-save-form"
                                        style="margin:0;">
                                        <input type="hidden" name="ajax" value="1">
                                        <input type="hidden" name="property_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm"
                                            data-save-button="1"
                                            data-saved="<?= $isSaved ? '1' : '0' ?>">
                                            <?= $isSaved ? '★ Saved' : '☆ Save' ?>
                                        </button>
                                    </form>

                                    <a
                                        href="<?= BASE_URL ?>/property.php?id=<?= (int)$p['id'] ?>"
                                        class="btn btn-sm">
                                        View
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php render_profile_pagination('saved', 'page_saved', $pageSaved, $totalPagesSaved); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>