<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------------------------------
// Stripe configuration (fill with your real keys from Stripe dashboard)
// --------------------------------------------------------------------------
const STRIPE_SECRET_KEY = 'YOUR_STRIPE_SECRET_KEY_HERE';
const STRIPE_PUBLISHABLE_KEY = 'YOUR_STRIPE_PUBLISHABLE_KEY_HERE';
// Pro product
const STRIPE_PRICE_PRO_MONTH   = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';   // $30 / month (default)
const STRIPE_PRICE_PRO_QUARTER = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // $60 every 3 months
const STRIPE_PRICE_PRO_YEAR    = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';   // $300 / year

// Agency product
const STRIPE_PRICE_AGENCY_MONTH   = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';   // $60 / month
const STRIPE_PRICE_AGENCY_QUARTER = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // $120 every 3 months
const STRIPE_PRICE_AGENCY_YEAR    = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';   // $600 / year

// If later you want agency subscriptions, add another price ID:
// const STRIPE_PRICE_AGENCY_MONTH = 'price_YOUR_AGENCY_PRICE_ID_HERE';

/**
 * --------------------------------------------------------------------------
 *  Application constants
 * --------------------------------------------------------------------------
 */

// Project name (used in <title> or header if needed)
const APP_NAME = 'Othman Real Estate';

/**
 * Base URL of the project.
 *
 * IMPORTANT:
 * - This should match the folder name under htdocs.
 * - Example: if your project is in C:\xampp\htdocs\real-estate-project
 *   then use: '/real-estate-project'
 */
const BASE_URL = '/REAL-ESTATE-PROJECT'; // change case/name if needed

/**
 * Public URLs for uploaded files.
 * UPLOAD_DIR / PROFILE_UPLOAD_DIR are absolute paths on disk.
 * UPLOAD_URL / PROFILE_UPLOAD_URL are the URLs used in <img src="...">
 */
const UPLOAD_DIR         = __DIR__ . '';
const UPLOAD_URL         = BASE_URL . '';
const PROFILE_UPLOAD_DIR = __DIR__ . '/uploads/profile/';
const PROFILE_UPLOAD_URL = BASE_URL . '/uploads/profile';

/**
 * --------------------------------------------------------------------------
 *  Database configuration (XAMPP defaults)
 * --------------------------------------------------------------------------
 */

$DB_CONFIG = [
    'host'     => '127.0.0.1',
    'dbname'   => 'Othman_real_estate',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];

/**
 * Returns a shared PDO instance (singleton).
 *
 * - Uses the global $DB_CONFIG values.
 * - Throws PDOException on error (error mode: EXCEPTION).
 */
function getPDO(): PDO
{
    static $pdo = null;
    global $DB_CONFIG;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $DB_CONFIG['host'],
            $DB_CONFIG['dbname'],
            $DB_CONFIG['charset']
        );

        $pdo = new PDO($dsn, $DB_CONFIG['username'], $DB_CONFIG['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

/**
 * --------------------------------------------------------------------------
 *  Global current user + ban guard
 * --------------------------------------------------------------------------
 */

$currentUser = null;

if (!empty($_SESSION['user_id'])) {
    $stmt = getPDO()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser) {
        // Keep session plan in sync with DB
        if (!empty($currentUser['plan'])) {
            $_SESSION['user_plan'] = $currentUser['plan'];
        } else {
            $_SESSION['user_plan'] = $_SESSION['user_plan'] ?? 'free';
        }
    }

    if (!$currentUser || (int)($currentUser['is_banned'] ?? 0) === 1) {
        // user deleted or banned while logged-in
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash_error'] = 'Your account has been banned. Please contact support.';
        header('Location: ' . BASE_URL . '/login.php?banned=1');
        exit;
    }
}

/**
 * Helper to get the full current user row anywhere.
 */
function currentUser(): ?array
{
    global $currentUser;
    return $currentUser;
}

/**
 * --------------------------------------------------------------------------
 *  Output / HTML helpers
 * --------------------------------------------------------------------------
 */

/**
 * Escapes a string for safe HTML output.
 *
 * Usage: <?= e($row['title']) ?>
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * --------------------------------------------------------------------------
 *  Authentication helpers
 * --------------------------------------------------------------------------
 */

/**
 * Returns true if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Returns the current logged-in user's ID, or null if not logged in.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Returns the current logged-in user's display name if stored in session.
 */
function currentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

/**
 * Returns true if the current user has the "admin" role.
 *
 * Assumes $_SESSION['user_role'] is set during login
 * and contains either "user" or "admin".
 */
function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['user_role'] ?? 'user') === 'admin');
}

/**
 * Redirects to login page if user is not authenticated.
 * Use at the top of pages that require a logged-in user.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Aborts the request with 403 if the current user is not an admin.
 * Use at the top of admin-only pages.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        http_response_code(403);
        echo 'Forbidden: admin only.';
        exit;
    }
}

/**
 * --------------------------------------------------------------------------
 *  CSRF protection helpers
 * --------------------------------------------------------------------------
 */

/**
 * Returns the CSRF token stored in the session.
 * Generates a new one if it does not exist.
 *
 * Use in forms:
 *   <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verifies a submitted CSRF token against the one stored in the session.
 *
 * Usage in POST handlers:
 *   if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { ... }
 */
function verifyCsrfToken(?string $token): bool
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * --------------------------------------------------------------------------
 *  File upload helpers
 * --------------------------------------------------------------------------
 */

/**
 * Stores uploaded images for a given property.
 *
 * - Enforces per-plan max_images limit (free / pro / agency).
 * - Accepts multiple files from <input type="file" name="images[]" multiple>
 * - Saves into UPLOAD_DIR and records in property_images.
 *
 * @param int   $propertyId
 * @param array $files      $_FILES['images']
 * @param PDO   $pdo
 *
 * @return string[] stored file names
 */
function storePropertyImages(int $propertyId, array $files, PDO $pdo): array
{
    $stored = [];

    // No files? nothing to do
    if (!isset($files['name']) || !is_array($files['name'])) {
        return $stored;
    }

    // --------------------------------------------------
    // 1) Get owner plan + per-plan image limit
    // --------------------------------------------------
    try {
        $stmtPlan = $pdo->prepare('
            SELECT COALESCE(u.plan, "free") AS owner_plan
            FROM properties p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = :pid
            LIMIT 1
        ');
        $stmtPlan->execute([':pid' => $propertyId]);
        $ownerPlan = $stmtPlan->fetchColumn() ?: 'free';
    } catch (Throwable $e) {
        // Fallback if something goes wrong: treat as free
        $ownerPlan = 'free';
    }

    $limits    = getPlanLimits($ownerPlan);
    $maxImages = $limits['max_images'] ?? 5;

    // --------------------------------------------------
    // 2) Count how many images this property already has
    // --------------------------------------------------
    $stmtCount = $pdo->prepare('
        SELECT COUNT(*) 
        FROM property_images 
        WHERE property_id = :pid
    ');
    $stmtCount->execute([':pid' => $propertyId]);
    $currentCount   = (int) $stmtCount->fetchColumn();
    $remainingSlots = $maxImages - $currentCount;

    if ($remainingSlots <= 0) {
        // Already at or above limit – nothing more allowed
        return $stored;
    }

    // --------------------------------------------------
    // 3) Standard upload logic, but stop at remainingSlots
    // --------------------------------------------------
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($files['name'] as $idx => $originalName) {
        if ($remainingSlots <= 0) {
            // Per-plan image limit reached, ignore extra files
            break;
        }

        $error   = $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
        $tmpName = $files['tmp_name'][$idx] ?? '';
        $size    = $files['size'][$idx] ?? 0;

        // Skip empty / invalid uploads
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
            continue;
        }

        // Limit size to 5 MB per file
        if ($size > 5 * 1024 * 1024) {
            continue;
        }

        $mime = $finfo->file($tmpName);
        if (!isset($allowedMime[$mime])) {
            // Unsupported file type
            continue;
        }

        $ext      = $allowedMime[$mime];
        $fileName = 'p_' . $propertyId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target   = rtrim(UPLOAD_DIR, '/\\') . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $target)) {
            // Failed to move uploaded file
            continue;
        }

        // Record in property_images table
        $stmt = $pdo->prepare('
            INSERT INTO property_images (property_id, file_name) 
            VALUES (:property_id, :file_name)
        ');
        $stmt->execute([
            ':property_id' => $propertyId,
            ':file_name'   => $fileName,
        ]);

        $stored[] = $fileName;
        $remainingSlots--;
    }

    return $stored;
}


/**
 * Return current user's plan (free, pro, agency).
 */
function currentUserPlan(): string
{
    return $_SESSION['user_plan'] ?? 'free';
}

/**
 * Set plan in session after login.
 */
function setCurrentUserPlan(string $plan): void
{
    $_SESSION['user_plan'] = $plan;
}

/**
 * Plan limits – central place to tweak.
 */
function getPlanLimits(string $plan): array
{
    switch ($plan) {
        case 'pro':
            return [
                'max_properties' => 12,
                'max_images'     => 8,
            ];
        case 'agency':
            return [
                'max_properties' => 100,
                'max_images'     => 20,
            ];
        case 'free':
        default:
            return [
                'max_properties' => 3,
                'max_images'     => 5,
            ];
    }
}

/**
 * Check how many active properties a user has (any status except 'deleted', if you add that).
 */
/**
 * Check how many active properties a user has.
 * "Active" = status pending/approved AND not sold.
 */
function getUserActivePropertyCount(int $userId, ?PDO $pdo = null): int
{
    $pdo ??= getPDO();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM properties
        WHERE user_id = :uid
          AND status IN ('pending', 'approved')
          AND is_sold = 0
    ");
    $stmt->execute([':uid' => $userId]);

    return (int)$stmt->fetchColumn();
}


/**
 * Increment stats for a property (views, saves, contact_clicks).
 */
function incrementPropertyStat(int $propertyId, string $field, ?PDO $pdo = null): void
{
    $allowed = ['views', 'saves', 'contact_clicks'];
    if (!in_array($field, $allowed, true)) {
        return;
    }

    $pdo ??= getPDO();
    $today = date('Y-m-d');

    // Insert or update row for today
    $sql = "
        INSERT INTO property_stats (property_id, stat_date, {$field})
        VALUES (:pid, :d, 1)
        ON DUPLICATE KEY UPDATE {$field} = {$field} + 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pid' => $propertyId,
        ':d'   => $today,
    ]);
}

/**
 * Send password reset email with link.
 *
 * NOTE: mail() needs SMTP to be configured in XAMPP.
 * For local development you will still see the link on the page.
 */
function sendPasswordResetEmail(string $email, string $name, string $resetUrl): void
{
    $subject = 'Reset your Othman Real Estate password';

    $message = "Hi {$name},\n\n"
        . "We received a request to reset the password for your Othman Real Estate account.\n\n"
        . "To choose a new password, click the link below (valid for 1 hour):\n"
        . "{$resetUrl}\n\n"
        . "If you didn’t request this, you can ignore this email.\n\n"
        . "Othman Real Estate";

    $headers = "From: Othman Real Estate <no-reply@Othman-realestate.test>\r\n";

    // Suppress warnings if mail is not configured
    @mail($email, $subject, $message, $headers);
}
