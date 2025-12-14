<?php
// change-password.php
require_once __DIR__ . '/config.php';

requireLogin();

$pdo         = getPDO();
$currentUser = currentUser(); // from config.php
$errors      = [];
$successMsg  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf          = $_POST['csrf_token'] ?? '';
    $currentPass   = $_POST['current_password'] ?? '';
    $newPass       = $_POST['new_password'] ?? '';
    $newPassRepeat = $_POST['new_password_confirm'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    if ($currentPass === '' || $newPass === '' || $newPassRepeat === '') {
        $errors[] = 'Please fill in all fields.';
    }

    if (strlen($newPass) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($newPass !== $newPassRepeat) {
        $errors[] = 'New passwords do not match.';
    }

    if (!password_verify($currentPass, $currentUser['password_hash'] ?? '')) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => $hash,
            ':id'   => $currentUser['id'],
        ]);

        $successMsg = 'Your password has been updated.';
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Change password';

require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Change password</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Update the password for your Hijazi Real Estate account.
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($successMsg): ?>
        <div class="alert-success">
            <?= e($successMsg) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="max-width:420px;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <!-- Current password -->
        <div class="form-field">
            <label class="form-label" for="current_password">Current password</label>
            <div class="text-field">
                <div class="password-field js-password-field">
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="js-password-input"
                        required
                        autocomplete="current-password">
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="1"
                        data-visible="0"
                        aria-label="Show password">
                        <!-- Eye icon -->
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5C7 5 3.3 8.1 2 12c1.3 3.9 5 7 10 7s8.7-3.1 10-7c-1.3-3.9-5-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                        <!-- Eye off icon -->
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M9.5 5.3A9.9 9.9 0 0 1 12 5c5 0 8.7 3.1 10 7a11.7 11.7 0 0 1-3.1 4.5M6.1 6.1A11.9 11.9 0 0 0 2 12c1.3 3.9 5 7 10 7a9.7 9.7 0 0 0 3.1-.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- New password -->
        <div class="form-field">
            <label class="form-label" for="new_password">New password</label>
            <div class="text-field">
                <div class="password-field js-password-field">
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="js-password-input"
                        required
                        minlength="8"
                        autocomplete="new-password">
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="1"
                        data-visible="0"
                        aria-label="Show password">
                        <!-- Eye icon -->
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5C7 5 3.3 8.1 2 12c1.3 3.9 5 7 10 7s8.7-3.1 10-7c-1.3-3.9-5-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                        <!-- Eye off icon -->
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M9.5 5.3A9.9 9.9 0 0 1 12 5c5 0 8.7 3.1 10 7a11.7 11.7 0 0 1-3.1 4.5M6.1 6.1A11.9 11.9 0 0 0 2 12c1.3 3.9 5 7 10 7a9.7 9.7 0 0 0 3.1-.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirm new password -->
        <div class="form-field">
            <label class="form-label" for="new_password_confirm">Confirm new password</label>
            <div class="text-field">
                <div class="password-field js-password-field">
                    <input
                        type="password"
                        id="new_password_confirm"
                        name="new_password_confirm"
                        class="js-password-input"
                        required
                        minlength="8"
                        autocomplete="new-password">
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle="1"
                        data-visible="0"
                        aria-label="Show password">
                        <!-- Eye icon -->
                        <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5C7 5 3.3 8.1 2 12c1.3 3.9 5 7 10 7s8.7-3.1 10-7c-1.3-3.9-5-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                        <!-- Eye off icon -->
                        <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="1.7" />
                            <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M9.5 5.3A9.9 9.9 0 0 1 12 5c5 0 8.7 3.1 10 7a11.7 11.7 0 0 1-3.1 4.5M6.1 6.1A11.9 11.9 0 0 0 2 12c1.3 3.9 5 7 10 7a9.7 9.7 0 0 0 3.1-.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:0.75rem;">
            Save new password
        </button>
    </form>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>