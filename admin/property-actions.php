<?php






















require_once __DIR__ . '/../config.php';

requireAdmin();              // only admins allowed
$pdo = getPDO();             // get shared PDO connection


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/properties.php');
    exit;
}


if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: ' . BASE_URL . '/admin/properties.php');
    exit;
}

$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$action     = trim($_POST['action'] ?? '');


$redirectStatus = $_POST['status'] ?? null;
$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
if ($redirectStatus !== null && !in_array($redirectStatus, $allowedStatuses, true)) {
    $redirectStatus = null;
}


$redirectUrl = BASE_URL . '/admin/properties.php';
if ($redirectStatus !== null) {
    $redirectUrl .= '?status=' . urlencode($redirectStatus);
}


if ($propertyId <= 0 || $action === '') {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: ' . $redirectUrl);
    exit;
}


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

            $stmt = $pdo->prepare('
                UPDATE properties
                SET status = "approved"
                WHERE id = :id
            ');
            $stmt->execute([':id' => $propertyId]);

            $_SESSION['flash_success'] = 'Property approved.';
            break;

        case 'reject':

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

    $_SESSION['flash_error'] = 'Database error. Please try again.';
}


header('Location: ' . $redirectUrl);
exit;
