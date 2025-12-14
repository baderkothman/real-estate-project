<?php









require_once __DIR__ . '/../config.php';

requireAdmin();
$pdo = getPDO();


$status          = $_GET['status'] ?? 'pending';
$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}


$perPage = 25;
$page    = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$sqlBase = '
    FROM properties p
    JOIN users u ON u.id = p.user_id
';

$where   = '';
$params  = [];

if ($status !== 'all') {
    $where = ' WHERE p.status = :status';
    $params[':status'] = $status;
}


$countSql = 'SELECT COUNT(*) ' . $sqlBase . $where;
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalProperties = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalProperties / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;


$sql = '
    SELECT p.*,
           u.name  AS owner_name,
           u.email AS owner_email
    ' . $sqlBase . $where . '
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
';

$stmt = $pdo->prepare($sql);

if ($status !== 'all') {
    $stmt->bindValue(':status', $status);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);


$paginationBaseUrl = BASE_URL . '/admin/properties.php?status=' . urlencode($status) . '&';


$adminFlash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$pageTitle = 'Admin · Properties';
require_once __DIR__ . '/../partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Manage properties</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Approve, reject and feature listings.
            </p>
        </div>

        <!-- Status filter buttons -->
        <div>
            <a href="<?= BASE_URL ?>/admin/properties.php?status=pending"
                class="btn <?= $status === 'pending' ? 'btn-primary' : '' ?>"
                style="margin-right:0.25rem;">Pending</a>
            <a href="<?= BASE_URL ?>/admin/properties.php?status=approved"
                class="btn <?= $status === 'approved' ? 'btn-primary' : '' ?>"
                style="margin-right:0.25rem;">Approved</a>
            <a href="<?= BASE_URL ?>/admin/properties.php?status=rejected"
                class="btn <?= $status === 'rejected' ? 'btn-primary' : '' ?>"
                style="margin-right:0.25rem;">Rejected</a>
            <a href="<?= BASE_URL ?>/admin/properties.php?status=all"
                class="btn <?= $status === 'all' ? 'btn-primary' : '' ?>">All</a>
        </div>
    </div>

    <?php if ($adminFlash): ?>
        <div class="alert-success" style="margin-bottom:1rem;">
            <?= e($adminFlash) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($properties)): ?>
        <p style="opacity:0.8;font-size:0.95rem;">
            No properties in this view.
        </p>
    <?php else: ?>
        <div class="section-card" style="margin-bottom:0;">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">ID</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Title</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Owner</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">City</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Type</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Status</th>
                            <th style="text-align:left;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Featured</th>
                            <th style="text-align:right;padding:0.5rem;border-bottom:1px solid rgba(0,0,0,0.1);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($properties as $p): ?>
                            <tr>
                                <td style="padding:0.5rem;"><?= (int) $p['id'] ?></td>
                                <td style="padding:0.5rem;">
                                    <a href="<?= BASE_URL ?>/property.php?id=<?= (int) $p['id'] ?>" target="_blank">
                                        <?= e($p['title']) ?>
                                    </a>
                                </td>
                                <td style="padding:0.5rem;">
                                    <?= e($p['owner_name']) ?><br>
                                    <span style="opacity:0.7;font-size:0.8rem;"><?= e($p['owner_email']) ?></span>
                                </td>
                                <td style="padding:0.5rem;"><?= e($p['city']) ?></td>
                                <td style="padding:0.5rem;"><?= e(ucfirst($p['listing_type'])) ?></td>
                                <td style="padding:0.5rem;"><?= e(ucfirst($p['status'])) ?></td>
                                <td style="padding:0.5rem;"><?= $p['is_featured'] ? 'Yes' : 'No' ?></td>
                                <td style="padding:0.5rem;text-align:right;white-space:nowrap;">
                                    <?php $csrf = getCsrfToken(); ?>

                                    <?php if ($p['status'] === 'pending'): ?>
                                        <!-- Approve -->
                                        <form method="post"
                                            action="<?= BASE_URL ?>/admin/property-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <button type="submit" class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                Approve
                                            </button>
                                        </form>

                                        <!-- Reject -->
                                        <form method="post"
                                            action="<?= BASE_URL ?>/admin/property-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <button type="submit" class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                Reject
                                            </button>
                                        </form>

                                    <?php elseif ($p['status'] === 'approved'): ?>
                                        <!-- Reject (from approved) -->
                                        <form method="post"
                                            action="<?= BASE_URL ?>/admin/property-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <button type="submit" class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                Reject
                                            </button>
                                        </form>

                                    <?php elseif ($p['status'] === 'rejected'): ?>
                                        <!-- Approve (from rejected) -->
                                        <form method="post"
                                            action="<?= BASE_URL ?>/admin/property-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <button type="submit" class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Feature / Unfeature (only if approved) -->
                                    <?php if ($p['status'] === 'approved'): ?>
                                        <form method="post"
                                            action="<?= BASE_URL ?>/admin/property-actions.php"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="property_id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="action" value="<?= $p['is_featured'] ? 'unfeature' : 'feature' ?>">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <input type="hidden" name="feature_days" value="7">
                                            <button type="submit" class="btn"
                                                style="font-size:0.8rem;padding:0.25rem 0.7rem;">
                                                <?= $p['is_featured'] ? 'Unfeature' : 'Feature' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $from = $totalProperties ? $offset + 1 : 0;
                $to   = min($offset + $perPage, $totalProperties);
                ?>
                <nav aria-label="Pages"
                    style="margin-top:1rem;display:flex;align-items:center;justify-content:space-between;font-size:0.86rem;">
                    <span style="opacity:0.8;">
                        Showing <?= $from ?> – <?= $to ?> of <?= $totalProperties ?> properties
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
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>