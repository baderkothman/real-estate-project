<?php
// property-stats.php
require_once __DIR__ . '/config.php';

requireLogin();
$pdo = getPDO();

$propertyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($propertyId <= 0) {
    http_response_code(400);
    echo 'Invalid property id.';
    exit;
}

// Make sure the current user owns this property (or is admin)
$stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $propertyId]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    http_response_code(404);
    echo 'Property not found.';
    exit;
}

if (!isAdmin() && (int)$property['user_id'] !== currentUserId()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Stats for last 30 days
$stmtStats = $pdo->prepare('
    SELECT stat_date, views, saves, contact_clicks
    FROM property_stats
    WHERE property_id = :pid
      AND stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY stat_date ASC
');
$stmtStats->execute([':pid' => $propertyId]);
$stats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Stats · ' . $property['title'];

require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Listing performance</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Last 30 days for: <strong><?= e($property['title']) ?></strong>
            </p>
        </div>
        <a href="<?= BASE_URL ?>/profile.php?tab=mine" class="btn">Back to my properties</a>
    </div>

    <?php if (empty($stats)): ?>
        <p style="opacity:0.8;font-size:0.95rem;">
            No stats yet for this listing. Share it to get more views!
        </p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:0.4rem;">Date</th>
                    <th style="text-align:right;padding:0.4rem;">Views</th>
                    <th style="text-align:right;padding:0.4rem;">Saves</th>
                    <th style="text-align:right;padding:0.4rem;">Contact clicks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalViews = $totalSaves = $totalContacts = 0;
                foreach ($stats as $row):
                    $totalViews    += (int)$row['views'];
                    $totalSaves    += (int)$row['saves'];
                    $totalContacts += (int)$row['contact_clicks'];
                ?>
                    <tr>
                        <td style="padding:0.35rem;"><?= e($row['stat_date']) ?></td>
                        <td style="padding:0.35rem;text-align:right;"><?= (int)$row['views'] ?></td>
                        <td style="padding:0.35rem;text-align:right;"><?= (int)$row['saves'] ?></td>
                        <td style="padding:0.35rem;text-align:right;"><?= (int)$row['contact_clicks'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th style="padding:0.45rem;">Totals (30 days)</th>
                    <th style="padding:0.45rem;text-align:right;"><?= $totalViews ?></th>
                    <th style="padding:0.45rem;text-align:right;"><?= $totalSaves ?></th>
                    <th style="padding:0.45rem;text-align:right;"><?= $totalContacts ?></th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>