<?php










require_once __DIR__ . '/config.php';

requireLogin();

$pdo    = getPDO();
$userId = currentUserId();


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/profile.php?tab=mine');
    exit;
}

$isAjax     = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$redirect   = $_POST['redirect'] ?? (BASE_URL . '/profile.php?tab=mine');


$respond = function (bool $ok, string $message, ?int $newSold = null) use ($isAjax, $redirect) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'is_sold' => $newSold,   // 0 or 1, or null on error
        ]);
        exit;
    }

    $_SESSION['flash_message'] = $message;
    header('Location: ' . $redirect);
    exit;
};

if ($propertyId <= 0) {
    $respond(false, 'Invalid property.');
}


$stmt = $pdo->prepare('
    SELECT id, user_id, is_sold, status
    FROM properties
    WHERE id = :id
    LIMIT 1
');
$stmt->execute([':id' => $propertyId]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property || (int)$property['user_id'] !== $userId) {
    $respond(false, 'Property not found.');
}

$currentSold = (int) $property['is_sold'];
$newSold     = $currentSold ? 0 : 1;




if ($currentSold === 1 && $newSold === 0) {

    if (in_array($property['status'], ['pending', 'approved'], true)) {
        $limits      = getPlanLimits(currentUserPlan());
        $maxProps    = (int) ($limits['max_properties'] ?? 0);
        $activeCount = getUserActivePropertyCount($userId, $pdo);





        if ($activeCount >= $maxProps) {
            $respond(
                false,
                'You reached the active listing limit for your plan. You can’t mark this property as available again unless you free a slot.'
            );
        }
    }
}




if ($newSold === 1) {

    $sql = '
        UPDATE properties
        SET is_sold = 1,
            sold_at = NOW()
        WHERE id = :id
          AND user_id = :uid
    ';
} else {

    $sql = '
        UPDATE properties
        SET is_sold = 0,
            sold_at = NULL
        WHERE id = :id
          AND user_id = :uid
    ';
}

$stmtUpdate = $pdo->prepare($sql);
$stmtUpdate->execute([
    ':id'  => $propertyId,
    ':uid' => $userId,
]);

$respond(
    true,
    $newSold ? 'Property marked as sold.' : 'Property marked as available.',
    $newSold
);
