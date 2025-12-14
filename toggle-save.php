<?php

/**
 * toggle-save.php
 * --------------------------------------------------------------
 * Toggle the "saved" status of a property for the current user.
 *
 * Behavior:
 *  - If the property is not saved yet → insert into saved_properties.
 *  - If the property is already saved → remove from saved_properties.
 *
 * Supports:
 *  - AJAX mode:
 *      - Frontend sends:  ajax=1
 *      - Returns JSON:
 *          {
 *            success: bool,
 *            saved:   bool,
 *            counts:  { saved: int } // total saved by this user
 *          }
 *  - Non-AJAX fallback:
 *      - Redirects back to the URL sent in `redirect`, or to profile.php.
 *
 * Security / rules:
 *  - User must be logged in (requireLogin()).
 *  - Property must exist.
 *  - User is not allowed to save their own property (extra guard).
 *  - When a property is saved (not unsaved), we record a "saves" stat
 *    via incrementPropertyStat($propertyId, 'saves', $pdo).
 */

require_once __DIR__ . '/config.php';

requireLogin();
$pdo = getPDO();

$userId     = currentUserId();
$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$isAjax     = !empty($_POST['ajax']);
$redirect   = $_POST['redirect'] ?? (BASE_URL . '/profile.php');

/**
 * Helper for JSON/non-JSON responses
 */
$respond = function (bool $success, string $message, ?bool $saved = null, ?int $savedCount = null) use ($isAjax, $redirect) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'saved'   => $saved,
            'counts'  => [
                'saved' => $savedCount,
            ],
        ]);
        exit;
    }


    header('Location: ' . $redirect);
    exit;
};

/**
 * --------------------------------------------------------------
 * Basic validation: property_id must be a positive integer
 * --------------------------------------------------------------
 */
if ($propertyId <= 0) {
    $respond(false, 'Invalid property id.');
}

/**
 * --------------------------------------------------------------
 * Safety: ensure the property actually exists
 * --------------------------------------------------------------
 */
$stmtProp = $pdo->prepare('
    SELECT id, user_id, status
    FROM properties
    WHERE id = :pid
    LIMIT 1
');
$stmtProp->execute([':pid' => $propertyId]);
$propRow = $stmtProp->fetch(PDO::FETCH_ASSOC);

if (!$propRow) {
    $respond(false, 'Property not found.');
}

/**
 * Optional rule: prevent saving your own properties
 */
if ((int) $propRow['user_id'] === $userId) {
    $respond(false, 'You cannot save your own property.');
}

/**
 * --------------------------------------------------------------
 * Check if this property is already saved by this user
 * --------------------------------------------------------------
 */
$stmt = $pdo->prepare('
    SELECT 1
    FROM saved_properties
    WHERE user_id = :uid
      AND property_id = :pid
');
$stmt->execute([
    ':uid' => $userId,
    ':pid' => $propertyId,
]);
$alreadySaved = (bool) $stmt->fetchColumn();

/**
 * --------------------------------------------------------------
 * Toggle state:
 *  - If saved → delete row from saved_properties (unsave).
 *  - If not saved → insert row (save) and increment "saves" stat.
 * --------------------------------------------------------------
 */
if ($alreadySaved) {
    $del = $pdo->prepare('
        DELETE FROM saved_properties
        WHERE user_id = :uid
          AND property_id = :pid
    ');
    $del->execute([
        ':uid' => $userId,
        ':pid' => $propertyId,
    ]);
    $savedNow = false;
    $msg      = 'Property removed from saved.';
} else {
    $ins = $pdo->prepare('
        INSERT IGNORE INTO saved_properties (user_id, property_id)
        VALUES (:uid, :pid)
    ');
    $ins->execute([
        ':uid' => $userId,
        ':pid' => $propertyId,
    ]);
    $savedNow = true;
    $msg      = 'Property added to saved.';


    incrementPropertyStat($propertyId, 'saves', $pdo);
}

/**
 * Recalculate "saved" count for current user (for the profile chip)
 */
$stmtSavedCount = $pdo->prepare('
    SELECT COUNT(*)
    FROM saved_properties
    WHERE user_id = :uid
');
$stmtSavedCount->execute([':uid' => $userId]);
$savedCount = (int) $stmtSavedCount->fetchColumn();

/**
 * --------------------------------------------------------------
 * AJAX / non-AJAX response
 * --------------------------------------------------------------
 */
$respond(true, $msg, $savedNow, $savedCount);
