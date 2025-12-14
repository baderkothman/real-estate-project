<?php
// admin/index.php
// --------------------------------------------------------------
// Admin dashboard
// - Shows key counters for users & properties
// - Accessible only to admin users (requireAdmin())
// --------------------------------------------------------------

require_once __DIR__ . '/../config.php';

requireAdmin();
$pdo = getPDO();

// Basic counters
$totalUsers      = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalProperties = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$approvedProps   = (int) $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'approved'")->fetchColumn();
$pendingProps    = (int) $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'pending'")->fetchColumn();
$featuredProps   = (int) $pdo->query("
    SELECT COUNT(*)
    FROM properties
    WHERE is_featured = 1
      AND status = 'approved'
")->fetchColumn();

// Optional: you can set a page title if later header uses it
$pageTitle = 'Admin dashboard';

require_once __DIR__ . '/../partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Admin dashboard</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Overview of users and listings on Othman Real Estate.
            </p>
        </div>
    </div>

    <div class="form-grid-2">
        <!-- Users card -->
        <div class="section-card" style="margin-bottom:0;">
            <h2 style="margin-top:0;margin-bottom:0.5rem;font-size:1rem;">Users</h2>
            <p style="margin:0;font-size:0.9rem;opacity:0.8;">
                Total registered users
            </p>
            <p style="font-size:1.6rem;margin:0.75rem 0 0;">
                <?= $totalUsers ?>
            </p>
        </div>

        <!-- Properties card -->
        <div class="section-card" style="margin-bottom:0;">
            <h2 style="margin-top:0;margin-bottom:0.5rem;font-size:1rem;">Properties</h2>
            <p style="margin:0;font-size:0.9rem;opacity:0.8;">
                Total properties (all statuses)
            </p>
            <p style="font-size:1.6rem;margin:0.75rem 0 0;">
                <?= $totalProperties ?>
            </p>
            <p style="margin:0.25rem 0 0;font-size:0.85rem;opacity:0.8;">
                Approved: <?= $approvedProps ?> · Pending: <?= $pendingProps ?>
            </p>
        </div>

        <!-- Featured card -->
        <div class="section-card" style="margin-bottom:0;">
            <h2 style="margin-top:0;margin-bottom:0.5rem;font-size:1rem;">Featured</h2>
            <p style="margin:0;font-size:0.9rem;opacity:0.8;">
                Featured approved properties
            </p>
            <p style="font-size:1.6rem;margin:0.75rem 0 0;">
                <?= $featuredProps ?>
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>