<?php
// admin/property-actions.php
// --------------------------------------------------------------
// Handles admin actions on a single property:
//
//  Supported actions (via POST "action"):
//   - approve     → sets status = "approved"
//   - reject      → sets status = "rejected" and clears any featured flags
//   - feature     → sets is_featured = 1 and featured_until = +N days
//   - unfeature   → sets is_featured = 0 and featured_until = NULL
//
//  Expected POST fields:
//   - property_id  (int, required)
//   - action       (string, required)
//   - csrf_token   (string, required)
//   - status       (optional, current filter on list page: pending/approved/...)
//   - feature_days (optional, only for "feature" action, e.g. 7 or 30)
//
//  Redirect:
//   - After performing the action, we redirect back to admin/properties.php
//     preserving the selected status filter if provided.
// --------------------------------------------------------------

require_once __DIR__ . '/../config.php';

requireAdmin();              // only admins allowed
$pdo = getPDO();             // get shared PDO connection

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/properties.php');
    exit;
}

// CSRF protection
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: ' . BASE_URL . '/admin/properties.php');
    exit;
}

$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$action     = trim($_POST['action'] ?? '');

// For redirect: keep current status filter if present and valid
$redirectStatus = $_POST['status'] ?? null;
$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
if ($redirectStatus !== null && !in_array($redirectStatus, $allowedStatuses, true)) {
    $redirectStatus = null;
}

// Build redirect URL
$redirectUrl = BASE_URL . '/admin/properties.php';
if ($redirectStatus !== null) {
    $redirectUrl .= '?status=' . urlencode($redirectStatus);
}

// Basic validation
if ($propertyId <= 0 || $action === '') {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: ' . $redirectUrl);
    exit;
}

// Make sure property exists
$stmt = $pdo->prepare('SELECT id, status, is_featured FROM properties WHERE id = :id');
$stmt->execute([':id' => $propertyId]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    $_SESSION['flash_error'] = 'Property not found.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    switch ($action) {
        case 'approve':
            // Approve property (keep existing featured flags if any)
            $stmt = $pdo->prepare('
                UPDATE properties
                SET status = "approved"
                WHERE id = :id
            ');
            $stmt->execute([':id' => $propertyId]);

            $_SESSION['flash_success'] = 'Property approved.';
            break;

        case 'reject':
            // Reject property and also remove any featured status
            $stmt = $pdo->prepare('
                UPDATE properties
                SET status = "rejected",
                    is_featured = 0,
                    featured_until = NULL
                WHERE id = :id
            ');
            $stmt->execute([':id' => $propertyId]);

            $_SESSION['flash_success'] = 'Property rejected and removed from featured.';
            break;

        case 'feature':
            // Feature property for a certain number of days
            // feature_days is expected from the form (e.g. 7, 30).
            // We compute featured_until in PHP and store it.
            $days = (int) ($_POST['feature_days'] ?? 7);
            if ($days <= 0 || $days > 365) {
                $days = 7; // safe default
            }

            $now     = new DateTimeImmutable('now');
            $expires = $now->modify('+' . $days . ' days')->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('
                UPDATE properties
                SET is_featured = 1,
                    featured_until = :until
                WHERE id = :id
            ');
            $stmt->execute([
                ':id'    => $propertyId,
                ':until' => $expires,
            ]);

            $_SESSION['flash_success'] = 'Property marked as featured for ' . $days . ' day(s).';
            break;

        case 'unfeature':
            // Remove featured flag and expiry
            $stmt = $pdo->prepare('
                UPDATE properties
                SET is_featured = 0,
                    featured_until = NULL
                WHERE id = :id
            ');
            $stmt->execute([':id' => $propertyId]);

            $_SESSION['flash_success'] = 'Property removed from featured.';
            break;

        default:
            $_SESSION['flash_error'] = 'Unknown action.';
            break;
    }
} catch (Exception $e) {
    // In production you might log $e->getMessage()
    $_SESSION['flash_error'] = 'Database error. Please try again.';
}

// Final redirect back to properties admin list
header('Location: ' . $redirectUrl);
exit;
