<?php
// users.php
// Public directory of owners / agents (non-admin users) with pagination

require_once __DIR__ . '/config.php';

$pdo = getPDO();

/**
 * Build a proper URL for profile images based on how they are stored.
 *
 * Handles:
 * - Full URLs (https://...)
 * - Paths starting with "/" (root-relative under BASE_URL)
 * - Paths starting with "uploads/..."
 * - Plain filenames (stored in PROFILE_UPLOAD_DIR)
 */
if (!function_exists('hr_profile_image_url')) {
    function hr_profile_image_url(?string $fileName): string
    {
        if (!$fileName) {
            return '';
        }

        // Full URL already
        if (preg_match('#^https?://#i', $fileName)) {
            return $fileName;
        }

        // Starts with slash: treat as root-relative under BASE_URL
        if ($fileName[0] === '/') {
            return rtrim(BASE_URL, '/') . $fileName;
        }

        // Starts with uploads/... -> prepend BASE_URL
        if (strpos($fileName, 'uploads/') === 0) {
            return rtrim(BASE_URL, '/') . '/' . $fileName;
        }

        // Default: just a filename stored in profile table
        if (defined('PROFILE_UPLOAD_URL')) {
            return rtrim(PROFILE_UPLOAD_URL, '/') . '/' . ltrim($fileName, '/');
        }

        // Fallback
        return rtrim(BASE_URL, '/') . '/uploads/profile/' . ltrim($fileName, '/');
    }
}

/**
 * --------------------------------------------------------------
 * Filters (search + plan)
 * --------------------------------------------------------------
 */
$q    = trim($_GET['q']    ?? '');
$plan = trim($_GET['plan'] ?? 'all');

$allowedPlans = ['all', 'free', 'pro', 'agency'];
if (!in_array($plan, $allowedPlans, true)) {
    $plan = 'all';
}

/**
 * --------------------------------------------------------------
 * Pagination
 * --------------------------------------------------------------
 */
$perPage = 12;
$page    = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

/**
 * --------------------------------------------------------------
 * WHERE clause (shared)
 * --------------------------------------------------------------
 *
 * - Hide admins
 * - Hide banned users
 * - Optional search
 * - Optional plan filter
 */
$whereSql = 'WHERE u.role <> "admin" AND (u.is_banned IS NULL OR u.is_banned = 0)';
$params   = [];

// Search
if ($q !== '') {
    $whereSql .= ' AND (u.name LIKE :q OR u.email LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

// Plan filter
if ($plan !== 'all') {
    $whereSql .= ' AND u.plan = :plan';
    $params[':plan'] = $plan;
}

/**
 * --------------------------------------------------------------
 * Count
 * --------------------------------------------------------------
 */
$countSql = 'SELECT COUNT(*) FROM users u ' . $whereSql;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalUsers = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

/**
 * --------------------------------------------------------------
 * Fetch users + property count
 * --------------------------------------------------------------
 */
$sql = '
    SELECT 
        u.id,
        u.name,
        u.email,
        u.plan,
        u.bio,
        u.profile_image,
        u.created_at,
        COUNT(p.id) AS property_count
    FROM users u
    LEFT JOIN properties p ON p.user_id = u.id
    ' . $whereSql . '
    GROUP BY 
        u.id, u.name, u.email, u.plan, u.bio, u.profile_image, u.created_at
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
';

$stmt = $pdo->prepare($sql);

// Bind filters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * --------------------------------------------------------------
 * Pagination base URL
 * --------------------------------------------------------------
 */
$queryParams = $_GET;
unset($queryParams['page']);
$baseQuery = http_build_query($queryParams);
$paginationBaseUrl = BASE_URL . '/users.php';
if ($baseQuery !== '') {
    $paginationBaseUrl .= '?' . $baseQuery . '&';
} else {
    $paginationBaseUrl .= '?';
}

$pageTitle = 'Owners & agents';
require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Owners &amp; agents</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Browse people listing properties on Othman Real Estate.
            </p>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" style="margin-bottom:1.25rem;">
        <div class="form-grid-2">
            <div class="form-field">
                <label class="form-label" for="q">Search</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="q"
                        name="q"
                        placeholder="Name or email..."
                        value="<?= e($q) ?>">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="plan">Plan</label>
                <div class="text-field">
                    <select id="plan" name="plan">
                        <option value="all" <?= $plan === 'all'   ? 'selected' : '' ?>>All</option>
                        <option value="free" <?= $plan === 'free'  ? 'selected' : '' ?>>Free</option>
                        <option value="pro" <?= $plan === 'pro'   ? 'selected' : '' ?>>Pro owner</option>
                        <option value="agency" <?= $plan === 'agency' ? 'selected' : '' ?>>Agency</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            Apply filters
        </button>
    </form>

    <?php if ($totalUsers === 0): ?>
        <p style="opacity:0.8;font-size:0.95rem;">
            No users found with these filters.
        </p>
    <?php else: ?>
        <div class="user-grid">
            <?php foreach ($users as $u): ?>
                <?php
                $avatarUrl = '';
                if (!empty($u['profile_image'])) {
                    $avatarUrl = hr_profile_image_url($u['profile_image']);
                }

                $planValue  = $u['plan'] ?: 'free';
                $planLabel  = $planValue === 'agency'
                    ? 'Agency'
                    : ($planValue === 'pro' ? 'Pro owner' : 'Free');

                $planChipClass = 'chip--free';
                if ($planValue === 'pro') {
                    $planChipClass = 'chip--pro';
                } elseif ($planValue === 'agency') {
                    $planChipClass = 'chip--agency';
                }
                ?>
                <article class="user-card">
                    <div class="user-card__header">
                        <?php if ($avatarUrl): ?>
                            <img
                                src="<?= e($avatarUrl) ?>"
                                alt="<?= e($u['name']) ?>"
                                class="profile-avatar-sm">
                        <?php else: ?>
                            <div class="testimonial-avatar">
                                <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="user-card__name">
                                <a href="<?= BASE_URL ?>/user.php?id=<?= (int) $u['id'] ?>"
                                    style="color:inherit;text-decoration:none;">
                                    <?= e($u['name']) ?>
                                </a>
                            </div>
                            <div class="user-card__meta">
                                <?= e($u['email']) ?>
                            </div>
                        </div>
                    </div>

                    <p class="user-card__bio">
                        <?= $u['bio'] ? e($u['bio']) : 'No bio added yet.' ?>
                    </p>

                    <div class="profile-chip-row" style="margin-top:0.35rem;">
                        <span class="chip <?= $planChipClass ?>">
                            <?= e($planLabel) ?>
                        </span>
                        <span class="chip" style="background-color:rgba(15,23,42,0.04);">
                            <?= (int) $u['property_count'] ?> properties
                        </span>
                    </div>

                    <?php if (!empty($u['created_at'])): ?>
                        <p style="margin:0.35rem 0 0;font-size:0.78rem;opacity:0.75;">
                            Joined on <?= e(date('M j, Y', strtotime($u['created_at']))) ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php
        $from = $totalUsers ? $offset + 1 : 0;
        $to   = min($offset + $perPage, $totalUsers);
        ?>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Pages"
                style="margin-top:1.25rem;display:flex;align-items:center;justify-content:space-between;font-size:0.86rem;">
                <span style="opacity:0.8;">
                    Showing <?= $from ?> – <?= $to ?> of <?= $totalUsers ?> users
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