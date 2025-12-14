<?php








require_once __DIR__ . '/../config.php';

requireAdmin();
$pdo = getPDO();

/**
 * Properties per status
 */
$stmt = $pdo->query('
    SELECT status, COUNT(*) AS cnt
    FROM properties
    GROUP BY status
');
$byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Properties per type (only approved, to reflect public inventory)
 */
$stmt = $pdo->query('
    SELECT listing_type, COUNT(*) AS cnt
    FROM properties
    WHERE status = "approved"
    GROUP BY listing_type
');
$byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Top cities by number of approved properties
 */
$stmt = $pdo->query('
    SELECT city, COUNT(*) AS cnt
    FROM properties
    WHERE status = "approved"
    GROUP BY city
    ORDER BY cnt DESC, city ASC
    LIMIT 10
');
$byCity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$maxCityCount = 0;
foreach ($byCity as $row) {
    if ($row['cnt'] > $maxCityCount) {
        $maxCityCount = (int)$row['cnt'];
    }
}
if ($maxCityCount <= 0) {
    $maxCityCount = 1;
}

$pageTitle = 'Admin · Analytics';
require_once __DIR__ . '/../partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Analytics</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                High-level view of listings on Othman Real Estate.
            </p>
        </div>
    </div>

    <div class="form-grid-2">
        <!-- Properties by status -->
        <div class="section-card" style="margin-bottom:0;">
            <h2 style="margin-top:0;margin-bottom:0.5rem;font-size:1rem;">By status</h2>
            <?php if (empty($byStatus)): ?>
                <p style="opacity:0.8;font-size:0.9rem;">No properties yet.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <tbody>
                        <?php foreach ($byStatus as $row): ?>
                            <tr>
                                <td style="padding:0.35rem 0.5rem;">
                                    <?= e(ucfirst($row['status'] ?? 'unknown')) ?>
                                </td>
                                <td style="padding:0.35rem 0.5rem;text-align:right;">
                                    <?= (int)$row['cnt'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Properties by listing type -->
        <div class="section-card" style="margin-bottom:0;">
            <h2 style="margin-top:0;margin-bottom:0.5rem;font-size:1rem;">By type (approved)</h2>
            <?php if (empty($byType)): ?>
                <p style="opacity:0.8;font-size:0.9rem;">No approved properties yet.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <tbody>
                        <?php foreach ($byType as $row): ?>
                            <tr>
                                <td style="padding:0.35rem 0.5rem;">
                                    <?= e(ucfirst($row['listing_type'] ?? 'unknown')) ?>
                                </td>
                                <td style="padding:0.35rem 0.5rem;text-align:right;">
                                    <?= (int)$row['cnt'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-card">
    <div class="page-header">
        <div>
            <h2 style="margin-bottom:0.25rem;">Top cities (approved properties)</h2>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Where most approved listings are located.
            </p>
        </div>
    </div>

    <?php if (empty($byCity)): ?>
        <p style="opacity:0.8;font-size:0.9rem;">No approved properties yet.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
            <tbody>
                <?php foreach ($byCity as $row): ?>
                    <?php
                    $count   = (int)$row['cnt'];
                    $percent = (int)round(($count / $maxCityCount) * 100);
                    ?>
                    <tr>
                        <td style="padding:0.35rem 0.5rem;white-space:nowrap;">
                            <?= e($row['city'] ?: 'Unknown') ?>
                        </td>
                        <td style="padding:0.35rem 0.5rem;width:100%;">
                            <div class="analytics-bar">
                                <div class="analytics-bar__fill" style="width:<?= $percent ?>%;"></div>
                            </div>

                        </td>
                        <td style="padding:0.35rem 0.5rem;text-align:right;white-space:nowrap;">
                            <?= $count ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>