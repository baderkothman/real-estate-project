<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

// Property ID from query
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($propertyId <= 0) {
    http_response_code(404);
    $pageTitle = 'Property not found';
    require_once __DIR__ . '/partials/header.php';
?>
    <section class="section-card">
        <p style="opacity:0.8;">Property not found.</p>
    </section>
<?php
    require_once __DIR__ . '/partials/footer.php';
    exit;
}

/**
 * --------------------------------------------------------------
 * Load property + owner
 * --------------------------------------------------------------
 */
$stmt = $pdo->prepare('
    SELECT 
        p.*,
        u.id            AS owner_id,
        u.name          AS owner_name,
        u.email         AS owner_email,
        u.phone         AS owner_phone,
        u.profile_image AS owner_profile_image,
        u.plan          AS owner_plan
    FROM properties p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id
    LIMIT 1
');
$stmt->execute([':id' => $propertyId]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    http_response_code(404);
    $pageTitle = 'Property not found';
    require_once __DIR__ . '/partials/header.php';
?>
    <section class="section-card">
        <p style="opacity:0.8;">Property not found.</p>
    </section>
<?php
    require_once __DIR__ . '/partials/footer.php';
    exit;
}

$propertyId = (int) $property['id'];
$ownerId    = (int) $property['owner_id'];

/**
 * --------------------------------------------------------------
 * Load property images (gallery)
 * --------------------------------------------------------------
 */
$stmtImg = $pdo->prepare('
    SELECT id, file_name
    FROM property_images
    WHERE property_id = :pid
    ORDER BY id ASC
');
$stmtImg->execute([':pid' => $propertyId]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

$coverImage = null;
if (!empty($images)) {
    $coverImage = $images[0]['file_name'];
}

/**
 * --------------------------------------------------------------
 * Saved state (for logged-in non-owner)
 * --------------------------------------------------------------
 */
$isSaved = false;

if (isLoggedIn() && currentUserId() !== $ownerId) {
    $stmtSaved = $pdo->prepare('
        SELECT 1
        FROM saved_properties
        WHERE user_id = :uid AND property_id = :pid
        LIMIT 1
    ');
    $stmtSaved->execute([
        ':uid' => currentUserId(),
        ':pid' => $propertyId,
    ]);
    $isSaved = (bool) $stmtSaved->fetchColumn();
}

/**
 * --------------------------------------------------------------
 * Featured state
 * --------------------------------------------------------------
 */
$isFeaturedNow = false;
if (!empty($property['is_featured']) && (int) $property['is_featured'] === 1) {
    if (empty($property['featured_until'])) {
        $isFeaturedNow = true;
    } else {
        $isFeaturedNow = ($property['featured_until'] > date('Y-m-d H:i:s'));
    }
}

$pageTitle = $property['title'];
require_once __DIR__ . '/partials/header.php';
?>

<style>
    /* Tiny tweak for clickable thumbs */
    .gallery-thumb {
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease, outline-color 0.12s ease;
    }

    .gallery-thumb.is-active-thumb {
        outline: 2px solid var(--md-sys-color-primary);
        outline-offset: 0;
        box-shadow: var(--md-elevation-2);
        transform: translateY(-1px);
    }
</style>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;"><?= e($property['title']) ?></h1>
            <p style="margin:0;opacity:0.85;font-size:0.9rem;">
                <?= e(ucfirst($property['listing_type'])) ?> · <?= e($property['city']) ?>
                <?php if (!empty($property['address'])): ?>
                    · <?= e($property['address']) ?>
                <?php endif; ?>
            </p>
            <p style="margin:0.3rem 0 0;font-weight:600;font-size:1.1rem;color:var(--md-sys-color-primary);">
                $<?= number_format((float) $property['price'], 0) ?>
            </p>

            <div class="profile-chip-row" style="margin-top:0.4rem;">
                <?php if ($isFeaturedNow): ?>
                    <span class="chip">Featured</span>
                <?php endif; ?>
                <?php if (!empty($property['status'])): ?>
                    <span class="chip">
                        Status: <?= e(ucfirst($property['status'])) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($property['bedrooms'])): ?>
                    <span class="chip">
                        <?= (int) $property['bedrooms'] ?> bd
                    </span>
                <?php endif; ?>
                <?php if (!empty($property['bathrooms'])): ?>
                    <span class="chip">
                        <?= (int) $property['bathrooms'] ?> ba
                    </span>
                <?php endif; ?>
                <?php if (!empty($property['area_m2'])): ?>
                    <span class="chip">
                        <?= (int) $property['area_m2'] ?> m²
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:0.5rem;align-items:flex-end;">
            <?php if (isLoggedIn() && currentUserId() === $ownerId): ?>
                <a href="<?= BASE_URL ?>/property-edit.php?id=<?= $propertyId ?>"
                    class="btn btn-primary"
                    style="font-size:0.9rem;">
                    Edit listing
                </a>
            <?php elseif (isLoggedIn()): ?>
                <form method="post"
                    action="<?= BASE_URL ?>/toggle-save.php"
                    class="js-save-form"
                    style="margin:0;">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="property_id" value="<?= $propertyId ?>">
                    <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                    <button
                        type="submit"
                        class="btn"
                        data-save-button="1"
                        data-saved="<?= $isSaved ? '1' : '0' ?>"
                        style="font-size:0.85rem;padding:0.35rem 1rem;">
                        <?= $isSaved ? '★ Saved' : '☆ Save' ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($property['created_at'])): ?>
                <span style="opacity:0.7;font-size:0.8rem;">
                    Posted on <?= e(date('M j, Y', strtotime($property['created_at']))) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="property-detail-row" style="margin-top:1rem;align-items:flex-start;">
        <!-- Gallery -->
        <div style="flex:2;min-width:0;">
            <?php if ($coverImage): ?>
                <div class="gallery-main">
                    <img
                        src="<?= UPLOAD_URL . '/' . e($coverImage) ?>"
                        alt="<?= e($property['title']) ?>"
                        class="gallery-main__img"
                        data-gallery-main>
                </div>
            <?php else: ?>
                <div class="gallery-main">
                    <div class="gallery-main__img"
                        style="background-color:var(--md-sys-color-surface-variant);"
                        data-gallery-main></div>
                </div>
            <?php endif; ?>

            <?php if (count($images) > 1): ?>
                <div class="gallery-row">
                    <?php foreach ($images as $img): ?>
                        <?php
                        $thumbUrl = UPLOAD_URL . '/' . $img['file_name'];
                        $isActive = ($img['file_name'] === $coverImage);
                        ?>
                        <div
                            class="gallery-thumb <?= $isActive ? 'is-active-thumb' : '' ?>"
                            data-gallery-thumb
                            data-full="<?= e($thumbUrl) ?>">
                            <img
                                src="<?= e($thumbUrl) ?>"
                                alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($property['description'])): ?>
                <div style="margin-top:1.2rem;">
                    <h2 style="font-size:1rem;margin-bottom:0.4rem;">Description</h2>
                    <p style="margin:0;font-size:0.95rem;line-height:1.6;">
                        <?= nl2br(e($property['description'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Owner / contact sidebar -->
        <!-- Owner / contact sidebar -->
        <aside style="flex:1;min-width:260px;">
            <div class="user-card">
                <!-- CLICKABLE OWNER HEADER -->
                <a href="<?= BASE_URL ?>/user.php?id=<?= $ownerId ?>"
                    class="user-card__header"
                    style="text-decoration:none;color:inherit;">
                    <?php if (!empty($property['owner_profile_image'])): ?>
                        <img
                            src="<?= PROFILE_UPLOAD_URL . '/' . e($property['owner_profile_image']) ?>"
                            alt="<?= e($property['owner_name']) ?>"
                            class="profile-avatar-sm">
                    <?php else: ?>
                        <div class="testimonial-avatar">
                            <?= strtoupper(mb_substr($property['owner_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div class="user-card__name">
                            <?= e($property['owner_name']) ?>
                        </div>
                        <div class="user-card__meta">
                            Owner<?= $property['owner_plan'] ? ' · ' . e(ucfirst($property['owner_plan'])) . ' plan' : '' ?>
                        </div>
                    </div>
                </a>

                <div style="margin-top:0.6rem;font-size:0.9rem;">
                    <?php if (!empty($property['owner_email'])): ?>
                        <div style="margin-bottom:0.25rem;">
                            <strong>Email:</strong><br>
                            <a href="mailto:<?= e($property['owner_email']) ?>">
                                <?= e($property['owner_email']) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($property['owner_phone'])): ?>
                        <div style="margin-bottom:0.25rem;">
                            <strong>Phone:</strong><br>
                            <a href="tel:<?= e($property['owner_phone']) ?>">
                                <?= e($property['owner_phone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mainImg = document.querySelector('[data-gallery-main]');
        const thumbs = document.querySelectorAll('[data-gallery-thumb]');

        if (!mainImg || !thumbs.length) return;

        thumbs.forEach(function(thumb) {
            thumb.addEventListener('click', function() {
                const full = thumb.getAttribute('data-full');
                if (!full) return;

                if (mainImg.tagName.toLowerCase() === 'img') {
                    mainImg.src = full;
                } else {
                    // Fallback if main is a div (no cover image originally)
                    mainImg.style.backgroundImage = 'url(' + full + ')';
                    mainImg.style.backgroundSize = 'cover';
                    mainImg.style.backgroundPosition = 'center';
                }

                thumbs.forEach(function(t) {
                    t.classList.remove('is-active-thumb');
                });
                thumb.classList.add('is-active-thumb');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>