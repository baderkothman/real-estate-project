<?php


declare(strict_types=1);

require_once __DIR__ . '/config.php';

requireLogin(); // keep it simple for now

$sessionId = $_GET['session_id'] ?? '';
if ($sessionId === '') {
    http_response_code(400);
    exit('Missing session_id');
}

require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    $session = \Stripe\Checkout\Session::retrieve($sessionId);


    $userIdFromMetadata = isset($session->metadata['user_id'])
        ? (int) $session->metadata['user_id']
        : null;

    $planFromMetadata = $session->metadata['plan'] ?? null;


    if (
        $userIdFromMetadata !== currentUserId()
        || !in_array($planFromMetadata, ['pro', 'agency'], true)
    ) {
        http_response_code(403);
        exit('Not allowed.');
    }


    if ($session->status !== 'complete') {
        http_response_code(400);
        exit('Payment not completed yet.');
    }


    $newPlan = $planFromMetadata; // 'pro' or 'agency'

    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET plan = :plan WHERE id = :id');
    $stmt->execute([
        ':plan' => $newPlan,
        ':id'   => currentUserId(),
    ]);


    setCurrentUserPlan($newPlan);

    $planLabel = ($newPlan === 'agency') ? 'Agency' : 'Pro owner';

    $pageTitle = 'Plan upgraded';
    require_once __DIR__ . '/partials/header.php';
?>
    <section class="section-card">
        <h1 style="margin-bottom:0.5rem;">Your plan has been upgraded 🎉</h1>
        <p style="margin:0 0 0.75rem;font-size:0.95rem;opacity:0.9;">
            You are now on the <strong><?= e($planLabel) ?></strong> plan.
        </p>
        <p style="margin:0 0 1.25rem;font-size:0.9rem;opacity:0.85;">
            You can now create more listings and upload more photos per property.
        </p>
        <a href="<?= BASE_URL ?>/property-create.php" class="btn btn-primary">
            Post a new property
        </a>
    </section>
<?php
    require_once __DIR__ . '/partials/footer.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Stripe error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
