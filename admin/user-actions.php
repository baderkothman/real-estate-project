<?php
// admin/user-actions.php
// --------------------------------------------------------------
// Handles admin actions on users:
// - ban
// - unban
//
// Expects POST:
//   - user_id
//   - action      ("ban" or "unban")
//   - csrf_token
//   - filter, q   (optional, for redirect back to same view)
// --------------------------------------------------------------

require_once __DIR__ . '/../config.php';

requireAdmin();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(400);
    echo 'Invalid CSRF token.';
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = $_POST['action'] ?? '';
$validActions = ['ban', 'unban'];

if ($userId <= 0 || !in_array($action, $validActions, true)) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

// Preserve filters in redirect
$filter = $_POST['filter'] ?? 'all';
$q      = $_POST['q']      ?? '';

// Do not allow banning yourself
if ($userId === currentUserId() && $action === 'ban') {
    $_SESSION['admin_flash'] = 'You cannot ban your own account.';
    header('Location: ' . BASE_URL . '/admin/users.php?filter=' . urlencode($filter) . '&q=' . urlencode($q));
    exit;
}

// Check user exists
$stmt = $pdo->prepare('SELECT id, is_banned FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['admin_flash'] = 'User not found.';
    header('Location: ' . BASE_URL . '/admin/users.php?filter=' . urlencode($filter) . '&q=' . urlencode($q));
    exit;
}

$message = 'Action completed.';

if ($action === 'ban') {
    $upd = $pdo->prepare('UPDATE users SET is_banned = 1 WHERE id = :id');
    $upd->execute([':id' => $userId]);
    $message = 'User has been banned.';
} elseif ($action === 'unban') {
    $upd = $pdo->prepare('UPDATE users SET is_banned = 0 WHERE id = :id');
    $upd->execute([':id' => $userId]);
    $message = 'User has been unbanned.';
}

$_SESSION['admin_flash'] = $message;

header('Location: ' . BASE_URL . '/admin/users.php?filter=' . urlencode($filter) . '&q=' . urlencode($q));
exit;
