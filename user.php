<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();


$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(404);
    $pageTitle = 'User not found';
    require_once __DIR__ . '/partials/header.php';
?>
    <section class="section-card">
        <p style="opacity:0.8;">User not found.</p>
    </section>
<?php
    require_once __DIR__ . '/partials/footer.php';
    exit;
}


$stmtUser = $pdo->prepare('
    SELECT id, name, email, phone, profile_image, bio, plan, created_at, is_banned
    FROM users
    WHERE id = :id
    LIMIT 1
');
$stmtUser->execute([':id' => $userId]);
$owner = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$owner || !empty($owner['is_banned'])) {
    http_response_code(404);
    $pageTitle = 'User not found';
    require_once __DIR__ . '/partials/header.php';
?>
    <section class="section-card">
        <p style="opacity:0.8;">User not found.</p>
    </section>
<?php
    require_once __DIR__ . '/partials/footer.php';
    exit;
}


$savedPropertyIds = [];
if (isLoggedIn()) {
    $stmtSavedIds = $pdo->prepare('
        SELECT property_id
        FROM saved_properties
        WHERE user_id = :uid
    ');
    $stmtSavedIds->execute([':uid' => currentUserId()]);
    $savedPropertyIds = $stmtSavedIds->fetchAll(PDO::FETCH_COLUMN);
}


$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;


$countSql = '
    SELECT COUNT(*)
    FROM properties
    WHERE user_id = :uid
      AND status = "approved"
      AND is_sold = 0
';
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute([':uid' => $owner['id']]);
$totalProps = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalProps / $perPage));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}


$sqlProps = '
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
      AND p.status = "approved"
      AND p.is_sold = 0
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
';
$stmtProps = $pdo->prepare($sqlProps);
$stmtProps->bindValue(':uid', $owner['id'], PDO::PARAM_INT);
$stmtProps->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtProps->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtProps->execute();
$properties = $stmtProps->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = e($owner['name']) . ' · Owner profile';
require_once __DIR__ . '/partials/header.php';


function render_user_pagination(int $ownerId, int $current, int $total): void
{
    if ($total <= 1) {
        return;
    }
?>
    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.35rem;">
        <?php if ($current > 1): ?>
            <a
                class="btn btn-secondary btn-sm"
                href="<?= BASE_URL ?>/user.php?id=<?= $ownerId ?>&page=<?= $current - 1 ?>">
                ← Prev
            </a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total; $i++): ?>
            <a
                class="btn btn-sm <?= $i === $current ? 'btn-primary' : '' ?>"
                href="<?= BASE_URL ?>/user.php?id=<?= $ownerId ?>&page=<?= $i ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($current < $total): ?>
            <a
                class="btn btn-secondary btn-sm"
                href="<?= BASE_URL ?>/user.php?id=<?= $ownerId ?>&page=<?= $current + 1 ?>">
                Next →
            </a>
        <?php endif; ?>
    </div>
<?php
}
?>

<section class="section-card">
    <!-- Public profile header (reusing profile styles) -->
    <div class="profile-header">
        <div class="profile-header__left">
            <?php if (!empty($owner['profile_image'])): ?>
                <img
                    src="<?= PROFILE_UPLOAD_URL . '/' . e($owner['profile_image']) ?>"
                    alt="<?= e($owner['name']) ?>"
                    class="profile-avatar-lg">
            <?php else: ?>
                <div class="testimonial-avatar">
                    <?= strtoupper(mb_substr($owner['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div class="profile-main">
                <div class="profile-name"><?= e($owner['name']) ?></div>
                <?php if (!empty($owner['email'])): ?>
                    <div class="profile-email"><?= e($owner['email']) ?></div>
                <?php endif; ?>
                <div class="profile-meta">
                    Member since <?= isset($owner['created_at'])
                                        ? e(date('M Y', strtotime($owner['created_at'])))
                                        : '—' ?>
                </div>

                <div class="profile-chip-row">
                    <?php
                    $plan = $owner['plan'] ?? 'free';
                    $planClass = 'chip--' . $plan;
                    ?>
                    <span class="chip <?= e($planClass) ?>">
                        <?= e(ucfirst($plan)) ?> plan
                    </span>
                    <span class="chip">
                        Active listings: <?= (int)$totalProps ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (!empty($owner['bio'])): ?>
            <p class="profile-bio">
                <?= nl2br(e($owner['bio'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <div style="margin-top:1.25rem;">
        <h2 style="font-size:1rem;margin-bottom:0.5rem;">
            Properties by <?= e($owner['name']) ?>
        </h2>

        <?php if (empty($properties)): ?>
            <p style="opacity:0.8;font-size:0.95rem;">
                This owner doesn’t have any public listings at the moment.
            </p>
        <?php else: ?>
            <div class="properties-grid">
                <?php foreach ($properties as $p): ?>
                    <?php
                    $isFeaturedNow = false;
                    if (!empty($p['is_featured']) && (int)$p['is_featured'] === 1) {
                        if (empty($p['featured_until'])) {
                            $isFeaturedNow = true;
                        } else {
                            $isFeaturedNow = ($p['featured_until'] > date('Y-m-d H:i:s'));
                        }
                    }

                    $viewerId = currentUserId();
                    $isOwner  = $viewerId && $viewerId === (int)$owner['id'];
                    $isSaved  = $viewerId
                        ? in_array($p['id'], $savedPropertyIds, true)
                        : false;
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
                                <?php if (isLoggedIn() && !$isOwner): ?>
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
                                <?php endif; ?>

                                <?php if ($isOwner): ?>
                                    <a
                                        href="<?= BASE_URL ?>/property-edit.php?id=<?= (int)$p['id'] ?>"
                                        class="btn btn-sm">
                                        Edit
                                    </a>
                                <?php endif; ?>

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

            <?php render_user_pagination((int)$owner['id'], $page, $totalPages); ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>