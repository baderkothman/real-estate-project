<?php
require_once __DIR__ . '/config.php';

requireLogin();
$pdo    = getPDO();
$userId = currentUserId();

// Property ID (from GET or POST)
$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $propertyId = isset($_POST['id']) ? (int) $_POST['id'] : $propertyId;
}

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

// --------------------------------------------------------------
// Load property + owner plan
// --------------------------------------------------------------
$stmt = $pdo->prepare('
    SELECT 
        p.*,
        u.id   AS owner_id,
        u.plan AS owner_plan
    FROM properties p
    JOIN users u ON u.id = p.user_id
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

$ownerId   = (int) $property['owner_id'];
$ownerPlan = $property['owner_plan'] ?: 'free';

// Only owner or admin can edit
if ($ownerId !== $userId && !isAdmin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// --------------------------------------------------------------
// Load existing images
// --------------------------------------------------------------
$stmtImg = $pdo->prepare('
    SELECT id, file_name
    FROM property_images
    WHERE property_id = :pid
    ORDER BY id ASC
');
$stmtImg->execute([':pid' => $propertyId]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// Plan limits (for info / UI)
$limits        = getPlanLimits($ownerPlan);
$maxImages     = $limits['max_images'] ?? 5;
$currentImages = count($images);
$remaining     = max(0, $maxImages - $currentImages);

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please reload the page and try again.';
    } else {
        // ------------------------------------------------------
        // Read form fields
        // ------------------------------------------------------
        $title        = trim($_POST['title'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $listingType  = $_POST['listing_type'] ?? 'sale';
        $price        = trim($_POST['price'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $bedrooms     = trim($_POST['bedrooms'] ?? '');
        $bathrooms    = trim($_POST['bathrooms'] ?? '');
        $areaM2       = trim($_POST['area_m2'] ?? '');

        // Basic validation
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($city === '') {
            $errors[] = 'City is required.';
        }
        if (!in_array($listingType, ['sale', 'rent'], true)) {
            $listingType = 'sale';
        }
        if ($price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors[] = 'Price must be a positive number.';
        }

        $bedrooms  = ($bedrooms !== '' && is_numeric($bedrooms)) ? (int) $bedrooms : null;
        $bathrooms = ($bathrooms !== '' && is_numeric($bathrooms)) ? (int) $bathrooms : null;
        $areaM2    = ($areaM2 !== '' && is_numeric($areaM2)) ? (int) $areaM2 : null;

        if (empty($errors)) {
            // --------------------------------------------------
            // 1) Update main property data
            // --------------------------------------------------
            $stmtUpdate = $pdo->prepare('
                UPDATE properties
                SET
                    title        = :title,
                    city         = :city,
                    address      = :address,
                    listing_type = :listing_type,
                    price        = :price,
                    description  = :description,
                    bedrooms     = :bedrooms,
                    bathrooms    = :bathrooms,
                    area_m2      = :area_m2
                WHERE id = :id
                  AND user_id = :uid
            ');
            $stmtUpdate->execute([
                ':title'        => $title,
                ':city'         => $city,
                ':address'      => $address,
                ':listing_type' => $listingType,
                ':price'        => (float) $price,
                ':description'  => $description,
                ':bedrooms'     => $bedrooms,
                ':bathrooms'    => $bathrooms,
                ':area_m2'      => $areaM2,
                ':id'           => $propertyId,
                ':uid'          => $ownerId,
            ]);

            // --------------------------------------------------
            // 2) Delete selected images (if any)
            // --------------------------------------------------
            if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $idsToDelete = array_map('intval', $_POST['delete_images']);
                $idsToDelete = array_filter($idsToDelete, fn($v) => $v > 0);

                if (!empty($idsToDelete)) {
                    // Fetch file names to remove from disk
                    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));

                    $params = array_merge([$propertyId], $idsToDelete);

                    $stmtFiles = $pdo->prepare("
                        SELECT id, file_name
                        FROM property_images
                        WHERE property_id = ?
                          AND id IN ($placeholders)
                    ");
                    $stmtFiles->execute($params);
                    $rowsToDelete = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

                    // Delete physical files
                    foreach ($rowsToDelete as $row) {
                        $filePath = rtrim(UPLOAD_DIR, '/\\') . '/' . $row['file_name'];
                        if (is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }

                    // Delete DB rows
                    $stmtDel = $pdo->prepare("
                        DELETE FROM property_images
                        WHERE property_id = ?
                          AND id IN ($placeholders)
                    ");
                    $stmtDel->execute($params);
                }
            }

            // --------------------------------------------------
            // 3) Handle new uploads (plan-aware limit is enforced
            //    inside storePropertyImages())
            // --------------------------------------------------
            if (!empty($_FILES['images']) && isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
                storePropertyImages($propertyId, $_FILES['images'], $pdo);
            }

            // --------------------------------------------------
            // 4) Reload property + images + limits for display
            // --------------------------------------------------
            $stmt = $pdo->prepare('
                SELECT 
                    p.*,
                    u.id   AS owner_id,
                    u.plan AS owner_plan
                FROM properties p
                JOIN users u ON u.id = p.user_id
                WHERE p.id = :id
                LIMIT 1
            ');
            $stmt->execute([':id' => $propertyId]);
            $property = $stmt->fetch(PDO::FETCH_ASSOC);

            $ownerPlan = $property['owner_plan'] ?: 'free';

            $stmtImg = $pdo->prepare('
                SELECT id, file_name
                FROM property_images
                WHERE property_id = :pid
                ORDER BY id ASC
            ');
            $stmtImg->execute([':pid' => $propertyId]);
            $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

            $limits        = getPlanLimits($ownerPlan);
            $maxImages     = $limits['max_images'] ?? 5;
            $currentImages = count($images);
            $remaining     = max(0, $maxImages - $currentImages);

            $success = 'Property updated successfully.';
        }
    }
}

$pageTitle = 'Edit property';
require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Edit property</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Update your listing details and manage photos.
            </p>
        </div>

        <a href="<?= BASE_URL ?>/property.php?id=<?= (int) $propertyId ?>" class="btn btn-secondary">
            View listing
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <ul style="margin:0;padding-left:1.1rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($success): ?>
        <div class="alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <input type="hidden" name="id" value="<?= (int) $propertyId ?>">

        <div class="form-grid-2">
            <div class="form-field">
                <label class="form-label" for="title">Title</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        value="<?= e($property['title'] ?? '') ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="city">City</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="city"
                        name="city"
                        required
                        value="<?= e($property['city'] ?? '') ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="address">Address</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="address"
                        name="address"
                        value="<?= e($property['address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="listing_type">Type</label>
                <div class="text-field">
                    <select id="listing_type" name="listing_type">
                        <option value="sale" <?= ($property['listing_type'] ?? '') === 'sale' ? 'selected' : '' ?>>
                            For sale
                        </option>
                        <option value="rent" <?= ($property['listing_type'] ?? '') === 'rent' ? 'selected' : '' ?>>
                            For rent
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="price">Price (USD)</label>
                <div class="text-field">
                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0"
                        step="any"
                        required
                        value="<?= e((string) ($property['price'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="bedrooms">Bedrooms</label>
                <div class="text-field">
                    <input
                        type="number"
                        id="bedrooms"
                        name="bedrooms"
                        min="0"
                        step="1"
                        value="<?= e((string) ($property['bedrooms'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="bathrooms">Bathrooms</label>
                <div class="text-field">
                    <input
                        type="number"
                        id="bathrooms"
                        name="bathrooms"
                        min="0"
                        step="1"
                        value="<?= e((string) ($property['bathrooms'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="area_m2">Area (m²)</label>
                <div class="text-field">
                    <input
                        type="number"
                        id="area_m2"
                        name="area_m2"
                        min="0"
                        step="1"
                        value="<?= e((string) ($property['area_m2'] ?? '')) ?>">
                </div>
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="description">Description</label>
            <div class="text-field">
                <textarea
                    id="description"
                    name="description"
                    rows="4"><?= e($property['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Images management -->
        <div class="form-field">
            <label class="form-label">Current photos</label>
            <?php if (empty($images)): ?>
                <p style="margin:0.4rem 0;opacity:0.8;font-size:0.9rem;">
                    No photos yet.
                </p>
            <?php else: ?>
                <div class="gallery-row">
                    <?php foreach ($images as $img): ?>
                        <div class="gallery-thumb" style="position:relative;">
                            <img
                                src="<?= UPLOAD_URL . '/' . e($img['file_name']) ?>"
                                alt=""
                                style="width:100%;height:100%;object-fit:cover;">
                            <label
                                style="
                                    position:absolute;
                                    inset:auto 0 0 auto;
                                    margin:0.2rem;
                                    background:rgba(15,23,42,0.8);
                                    color:#fff;
                                    font-size:0.7rem;
                                    padding:0.1rem 0.4rem;
                                    border-radius:999px;
                                    display:inline-flex;
                                    align-items:center;
                                    gap:0.2rem;
                                ">
                                <input
                                    type="checkbox"
                                    name="delete_images[]"
                                    value="<?= (int) $img['id'] ?>"
                                    style="margin:0 0.2rem 0 0;">
                                Delete
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label class="form-label" for="images">
                Add photos
                <span style="font-weight:normal;opacity:0.75;">
                    (<?= $currentImages ?>/<?= $maxImages ?> used)
                </span>
            </label>
            <div class="text-field">
                <input
                    type="file"
                    id="images"
                    name="images[]"
                    accept="image/*"
                    multiple
                    <?= $remaining <= 0 ? 'disabled' : '' ?>>
            </div>
            <?php if ($remaining <= 0): ?>
                <p style="margin:0.25rem 0 0;font-size:0.8rem;color:#b3261e;">
                    You’ve reached the maximum number of photos for your plan (<?= (int) $maxImages ?>).
                    Delete some existing photos to upload new ones.
                </p>
            <?php else: ?>
                <p style="margin:0.25rem 0 0;font-size:0.8rem;opacity:0.8;">
                    You can upload up to <?= (int) $remaining ?> more photo<?= $remaining > 1 ? 's' : '' ?>.
                </p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">
            Save changes
        </button>
    </form>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>