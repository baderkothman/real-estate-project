<?php
// admin/users.php
// --------------------------------------------------------------
// Admin view: manage users
// - Lists users (non-admin), with property count
// - Paginated (20 per page)
// - Sortable columns via ?sort=&dir=
// - Shows if user is banned or not
// - Allows ban / unban actions
// - Allows changing user plan (free / pro / agency)
// --------------------------------------------------------------

require_once __DIR__ . '/../config.php';

requireAdmin();
$pdo = getPDO();

// Allowed plans for controls
$allowedPlans = ['free', 'pro', 'agency'];

// -------------------------------------------------------------------------
// Small helper: build URL keeping current filters/sort/page
// -------------------------------------------------------------------------
function adminUsersUrl(array $overrides = []): string
{
    $params = [
        'q'      => $_GET['q']      ?? '',
        'filter' => $_GET['filter'] ?? 'all',
        'sort'   => $_GET['sort']   ?? 'created_at',
        'dir'    => $_GET['dir']    ?? 'desc',
        'page'   => $_GET['page']   ?? 1,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    return BASE_URL . '/admin/users.php?' . http_build_query($params);
}

// -------------------------------------------------------------------------
// Sorting
// -------------------------------------------------------------------------
$allowedSorts = [
    'id'          => 'u.id',
    'name'        => 'u.name',
    'email'       => 'u.email',
    'created_at'  => 'u.created_at',
    'plan'        => 'u.plan',
    'role'        => 'u.role',
    'properties'  => 'property_count',
    'status'      => 'u.is_banned',
];

$sort = $_GET['sort'] ?? 'created_at';
if (!isset($allowedSorts[$sort])) {
    $sort = 'created_at';
}

$dir = strtolower($_GET['dir'] ?? 'desc');
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

// -------------------------------------------------------------------------
// Pagination
// -------------------------------------------------------------------------
$perPage = 20;
$page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// -------------------------------------------------------------------------
// Handle POST: change plan
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_plan') {
    $csrf   = $_POST['csrf_token'] ?? '';
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    // Helper to respond as JSON if ajax, else redirect with flash
    $respond = function (bool $ok, string $message, ?string $plan = null, ?int $userId = null) use ($isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok'      => $ok,
                'message' => $message,
                'plan'    => $plan,
                'user_id' => $userId,
            ]);
            exit;
        }

        $_SESSION['admin_flash'] = $message;

        $filter = urlencode($_POST['filter'] ?? 'all');
        $q      = urlencode($_POST['q'] ?? '');
        $sort   = urlencode($_POST['sort'] ?? 'created_at');
        $dir    = urlencode($_POST['dir'] ?? 'desc');
        $page   = max(1, (int) ($_POST['page'] ?? 1));

        header('Location: ' . BASE_URL . "/admin/users.php?filter={$filter}&q={$q}&sort={$sort}&dir={$dir}&page={$page}");
        exit;
    };

    if (!verifyCsrfToken($csrf)) {
        $respond(false, 'Error: invalid session token.');
    }

    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $plan   = $_POST['plan'] ?? 'free';

    if ($userId <= 0 || !in_array($plan, $allowedPlans, true)) {
        $respond(false, 'Error: invalid user or plan.');
    }

    // Update user plan
    $stmt = $pdo->prepare('
        UPDATE users
        SET plan = :plan
        WHERE id = :id
    ');
    $stmt->execute([
        ':plan' => $plan,
        ':id'   => $userId,
    ]);

    $respond(true, 'User plan updated to ' . ucfirst($plan) . '.', $plan, $userId);
}

// -------------------------------------------------------------------------
// Filters (search + banned)
// -------------------------------------------------------------------------
$q = trim($_GET['q'] ?? '');

$filter         = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'active', 'banned'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// -------------------------------------------------------------------------
// Build base WHERE and params (used for both count & data queries)
// -------------------------------------------------------------------------
$where  = ' WHERE 1=1';
$params = [];

// Exclude admins entirely
$where .= " AND u.role <> 'admin'";

// Filter by search term
if ($q !== '') {
    $where        .= ' AND (u.name LIKE :q OR u.email LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

// Filter by banned state
if ($filter === 'active') {
    $where .= ' AND u.is_banned = 0';
} elseif ($filter === 'banned') {
    $where .= ' AND u.is_banned = 1';
}

// -------------------------------------------------------------------------
// Total count for pagination
// -------------------------------------------------------------------------
$countSql = '
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN properties p ON p.user_id = u.id
' . $where;

$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalUsers = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// -------------------------------------------------------------------------
// Data query (with GROUP BY, ORDER BY, LIMIT/OFFSET)
// -------------------------------------------------------------------------
$orderBy = $allowedSorts[$sort] . ' ' . strtoupper($dir);

$sql = '
    SELECT
        u.id,
        u.name,
        u.email,
        u.role,
        u.plan,
        u.bio,
        u.profile_image,
        u.created_at,
        u.is_banned,
        COUNT(p.id) AS property_count
    FROM users u
    LEFT JOIN properties p ON p.user_id = u.id
' . $where . '
    GROUP BY
        u.id, u.name, u.email, u.role, u.plan,
        u.bio, u.profile_image, u.created_at, u.is_banned
    ORDER BY ' . $orderBy . '
    LIMIT :limit OFFSET :offset
';

$paramsData              = $params;
$paramsData[':limit']    = $perPage;
$paramsData[':offset']   = $offset;

$stmt = $pdo->prepare($sql);
foreach ($paramsData as $key => $value) {
    if ($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash message (e.g., from user-actions or NON-AJAX plan changes)
$adminFlash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$pageTitle = 'Admin · Users';
require_once __DIR__ . '/../partials/header.php';

// Helper for sort headers
function sortHeader(string $label, string $key, string $currentSort, string $currentDir): string
{
    $isActive = ($currentSort === $key);
    $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow    = '';

    if ($isActive) {
        $arrow = $currentDir === 'asc' ? '↑' : '↓';
    }

    $url = adminUsersUrl([
        'sort' => $key,
        'dir'  => $nextDir,
        'page' => 1, // reset to first page on new sort
    ]);

    return sprintf(
        '<a href="%s" style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:0.2rem;">%s%s</a>',
        htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $arrow ? ' <span style="font-size:0.75em;opacity:0.7;">' . $arrow . '</span>' : ''
    );
}
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Manage users</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                View users, their activity, change their plan and ban/unban accounts.
            </p>
        </div>
    </div>

    <?php if ($adminFlash): ?>
        <div class="alert-success" style="margin-bottom:1rem;">
            <?= e($adminFlash) ?>
        </div>
    <?php endif; ?>

    <!-- Filters & search -->
    <form method="get" style="margin-bottom:1rem;">
        <div class="form-grid-2">
            <div class="form-field">
                <label class="form-label" for="q">Search (name or email)</label>
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
                <label class="form-label" for="filter">Status</label>
                <div class="text-field">
                    <select id="filter" name="filter">
                        <option value="all" <?= $filter === 'all'    ? 'selected' : '' ?>>All</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="banned" <?= $filter === 'banned' ? 'selected' : '' ?>>Banned</option>
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
        <div class="section-card" style="margin-bottom:0;">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('ID', 'id', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Name', 'name', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Email', 'email', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Role', 'role', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Plan', 'plan', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Properties', 'properties', $sort, $dir) ?>
                            </th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Status', 'status', $sort, $dir) ?>
                            </th>
                            <th style="text-align:right;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                <?= sortHeader('Created', 'created_at', $sort, $dir) ?>
                            </th>
                            <th style="text-align:right;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php $csrf = getCsrfToken(); ?>
                            <tr data-user-row="<?= (int) $u['id'] ?>">
                                <td style="padding:0.5rem;"><?= (int) $u['id'] ?></td>
                                <td style="padding:0.5rem;">
                                    <a href="<?= BASE_URL ?>/user.php?id=<?= (int) $u['id'] ?>" target="_blank">
                                        <?= e($u['name']) ?>
                                    </a>
                                </td>
                                <td style="padding:0.5rem;"><?= e($u['email']) ?></td>
                                <td style="padding:0.5rem;"><?= e(ucfirst($u['role'] ?? 'user')) ?></td>

                                <!-- Plan column: row of small buttons -->
                                <td style="padding:0.5rem;">
                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/admin/users.php"
                                        class="js-plan-form"
                                        data-user-id="<?= (int) $u['id'] ?>"
                                        style="margin:0;display:flex;gap:0.35rem;flex-wrap:wrap;align-items:center;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <input type="hidden" name="action" value="change_plan">
                                        <!-- preserve current filters/sort/page on redirect (for non-AJAX fallback) -->
                                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                        <input type="hidden" name="q" value="<?= e($q) ?>">
                                        <input type="hidden" name="sort" value="<?= e($sort) ?>">
                                        <input type="hidden" name="dir" value="<?= e($dir) ?>">
                                        <input type="hidden" name="page" value="<?= (int) $page ?>">

                                        <?php
                                        $currentPlan = $u['plan'] ?? 'free';
                                        foreach ($allowedPlans as $plan):
                                            $isActive = ($currentPlan === $plan);
                                        ?>
                                            <button
                                                type="submit"
                                                name="plan"
                                                value="<?= e($plan) ?>"
                                                class="btn <?= $isActive ? 'btn-primary' : '' ?>"
                                                style="
                                                    font-size:0.75rem;
                                                    padding:0.15rem 0.6rem;
                                                    border-radius:999px;
                                                    <?= $isActive ? '' : 'background:transparent;opacity:0.8;' ?>
                                                ">
                                                <?= ucfirst($plan) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </form>
                                </td>

                                <td style="padding:0.5rem;"><?= (int) $u['property_count'] ?></td>

                                <td style="padding:0.5rem;">
                                    <?php if (!empty($u['is_banned'])): ?>
                                        <span style="color:#b3261e;font-weight:500;">Banned</span>
                                    <?php else: ?>
                                        <span style="color:#1e7f34;font-weight:500;">Active</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding:0.5rem;text-align:right;">
                                    <span style="opacity:0.7;font-size:0.8rem;">
                                        <?= e(date('Y-m-d', strtotime($u['created_at']))) ?>
                                    </span>
                                </td>

                                <td style="padding:0.5rem;text-align:right;white-space:nowrap;">
                                    <?php if ((int) $u['id'] !== currentUserId()): ?>
                                        <form
                                            method="post"
                                            action="<?= BASE_URL ?>/admin/user-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                            <input type="hidden" name="q" value="<?= e($q) ?>">
                                            <input type="hidden" name="sort" value="<?= e($sort) ?>">
                                            <input type="hidden" name="dir" value="<?= e($dir) ?>">
                                            <input type="hidden" name="page" value="<?= (int) $page ?>">
                                            <input type="hidden" name="action"
                                                value="<?= $u['is_banned'] ? 'unban' : 'ban' ?>">
                                            <button type="submit"
                                                class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                <?= $u['is_banned'] ? 'Unban' : 'Ban' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="opacity:0.7;font-size:0.8rem;">(You)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination controls -->
            <div style="margin-top:0.75rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;font-size:0.85rem;">
                <div>
                    Showing
                    <strong>
                        <?= $totalUsers === 0 ? 0 : ($offset + 1) ?>
                        –
                        <?= min($offset + $perPage, $totalUsers) ?>
                    </strong>
                    of <strong><?= $totalUsers ?></strong> users
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav style="display:flex;align-items:center;gap:0.35rem;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm"
                                href="<?= e(adminUsersUrl(['page' => $page - 1])) ?>">
                                ‹ Prev
                            </a>
                        <?php endif; ?>

                        <span style="opacity:0.8;">
                            Page <?= $page ?> of <?= $totalPages ?>
                        </span>

                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm"
                                href="<?= e(adminUsersUrl(['page' => $page + 1])) ?>">
                                Next ›
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>