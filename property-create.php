<?php

/**
 * property-create.php
 * --------------------------------------------------------------
 * Create a new property listing.
 *
 * - Requires login
 * - Enforces plan limits (free / pro / agency)
 *   - max_properties (pending + approved)
 *   - max_images per listing
 * - New listings are created with status = "pending"
 *   and must be approved by an admin.
 * --------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

requireLogin();
$pdo    = getPDO();
$userId = currentUserId();

// Plan + limits
$plan   = currentUserPlan();        // e.g. "free", "pro", "agency"
$limits = getPlanLimits($plan);     // from config.php

// How many active properties the user already has
$activeCount     = getUserActivePropertyCount($userId, $pdo);
$remainingSlots  = max(0, $limits['max_properties'] - $activeCount);

$errors = [];

// Form values
$title = $city = $address = $description = '';
$type  = '';
$price = $bedrooms = $bathrooms = $area = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    // Re-check listing limit on POST
    $activeCount = getUserActivePropertyCount($userId, $pdo);
    if ($activeCount >= $limits['max_properties']) {
        $errors[] =
            'You have reached the limit of ' . $limits['max_properties'] .
            ' active listings for your ' . ucfirst($plan) . ' plan. ' .
            'Please upgrade to post more properties.';
    }

    // Read inputs
    $title       = trim($_POST['title'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $type        = $_POST['listing_type'] ?? '';
    $price       = $_POST['price'] ?? '';
    $bedrooms    = $_POST['bedrooms'] ?? '';
    $bathrooms   = $_POST['bathrooms'] ?? '';
    $area        = $_POST['area_sq_m'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // Basic validation (all required now)
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($city === '') {
        $errors[] = 'City is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if (!in_array($type, ['sale', 'rent'], true)) {
        $errors[] = 'Listing type must be sale or rent.';
    }
    if (!is_numeric($price) || (float) $price <= 0) {
        $errors[] = 'Please enter a valid price.';
    }

    // Bedrooms: required, numeric, >= 0
    if ($bedrooms === '' || !is_numeric($bedrooms) || (int)$bedrooms < 0) {
        $errors[] = 'Please enter a valid number of bedrooms (0 or more).';
    }

    // Bathrooms: required, numeric, >= 0
    if ($bathrooms === '' || !is_numeric($bathrooms) || (int)$bathrooms < 0) {
        $errors[] = 'Please enter a valid number of bathrooms (0 or more).';
    }

    // Area: required, numeric, > 0
    if ($area === '' || !is_numeric($area) || (int)$area <= 0) {
        $errors[] = 'Please enter a valid area in square meters.';
    }

    // Description required
    if ($description === '') {
        $errors[] = 'Description is required.';
    }

    // Images: at least 1 required, and must respect plan limit
    $countImages = 0;
    if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        foreach ($_FILES['images']['name'] as $name) {
            if ($name !== '') {
                $countImages++;
            }
        }
    }

    if ($countImages === 0) {
        $errors[] = 'Please upload at least one image for your listing.';
    } elseif ($countImages > $limits['max_images']) {
        $errors[] =
            'Your ' . ucfirst($plan) . ' plan allows up to ' . $limits['max_images'] .
            ' images per listing. You selected ' . $countImages . '.';
    }

    // If everything is valid -> insert
    if (empty($errors)) {
        $stmt = $pdo->prepare('
            INSERT INTO properties (
                user_id, title, city, address, listing_type, price,
                bedrooms, bathrooms, area_sq_m, description, status
            ) VALUES (
                :user_id, :title, :city, :address, :listing_type, :price,
                :bedrooms, :bathrooms, :area_sq_m, :description, :status
            )
        ');
        $stmt->execute([
            ':user_id'      => $userId,
            ':title'        => $title,
            ':city'         => $city,
            ':address'      => $address,
            ':listing_type' => $type,
            ':price'        => (float) $price,
            ':bedrooms'     => (int) $bedrooms,
            ':bathrooms'    => (int) $bathrooms,
            ':area_sq_m'    => (int) $area,
            ':description'  => $description,
            ':status'       => 'pending', // new listings must be approved
        ]);

        $propertyId = (int) $pdo->lastInsertId();

        // Upload images (helper from config.php)
        if (!empty($_FILES['images'])) {
            storePropertyImages($propertyId, $_FILES['images'], $pdo);
        }

        // Redirect to the property details page
        header('Location: ' . BASE_URL . '/property.php?id=' . $propertyId);
        exit;
    }

    // Recalculate remaining slots for the info text
    $activeCount    = getUserActivePropertyCount($userId, $pdo);
    $remainingSlots = max(0, $limits['max_properties'] - $activeCount);
}

$csrfToken = getCsrfToken();
$pageTitle = 'Post new property';

require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Post a new listing</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Plan: <strong><?= e(ucfirst($plan)) ?></strong> ·
                Active listings: <?= $activeCount ?> / <?= $limits['max_properties'] ?> ·
                Max images per listing: <?= $limits['max_images'] ?>
            </p>
            <?php if ($remainingSlots <= 0): ?>
                <p style="margin:0.3rem 0 0;font-size:0.85rem;color:#b3261e;">
                    You have reached your active listing limit. Upgrade your plan to post more properties.
                </p>
            <?php else: ?>
                <p style="margin:0.3rem 0 0;font-size:0.85rem;opacity:0.8;">
                    You can still publish <strong><?= $remainingSlots ?></strong> more active listing<?= $remainingSlots === 1 ? '' : 's' ?>.
                </p>
            <?php endif; ?>
        </div>

        <a href="<?= BASE_URL ?>/pricing.php" class="btn">
            View plans
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Only show the form if user still has slots -->
    <?php if ($remainingSlots > 0 || !empty($errors)): ?>
        <form method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-grid-2">
                <div class="form-field">
                    <label class="form-label" for="title">Title</label>
                    <div class="text-field">
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="<?= e($title) ?>"
                            placeholder="Spacious 3-bedroom apartment in Tripoli"
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="city">City</label>
                    <div class="text-field">
                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= e($city) ?>"
                            placeholder="Tripoli, Beirut..."
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="address">Address</label>
                    <div class="text-field">
                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="<?= e($address) ?>"
                            placeholder="Street, building, nearby landmark"
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="listing_type">Type</label>
                    <div class="text-field">
                        <select id="listing_type" name="listing_type" required>
                            <option value="">Select...</option>
                            <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>For sale</option>
                            <option value="rent" <?= $type === 'rent' ? 'selected' : '' ?>>For rent</option>
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
                            value="<?= e($price) ?>"
                            required>
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
                            value="<?= e($bedrooms) ?>"
                            required>
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
                            value="<?= e($bathrooms) ?>"
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="area_sq_m">Area (m²)</label>
                    <div class="text-field">
                        <input
                            type="number"
                            id="area_sq_m"
                            name="area_sq_m"
                            min="0"
                            step="1"
                            value="<?= e($area) ?>"
                            required>
                    </div>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="description">Description</label>
                <div class="text-field">
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe the property, neighborhood, services, etc."
                        required><?= e($description) ?></textarea>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="images">Images (up to <?= $limits['max_images'] ?>)</label>
                <div class="text-field">
                    <input
                        type="file"
                        id="images"
                        name="images[]"
                        accept="image/*"
                        multiple
                        required>
                </div>
                <p style="margin:0.3rem 0 0;font-size:0.8rem;opacity:0.75;">
                    You must upload at least one image. Max size: 5 MB per image.
                </p>
            </div>

            <div style="margin-top:1.2rem;">
                <button type="submit" class="btn btn-primary">
                    Submit for approval
                </button>
            </div>
        </form>
    <?php else: ?>
        <p style="margin-top:1rem;font-size:0.9rem;opacity:0.8;">
            You cannot create more active listings on your current plan.
            Please <a href="<?= BASE_URL ?>/pricing.php">upgrade your plan</a> to continue.
        </p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>