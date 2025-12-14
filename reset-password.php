<?php

require_once __DIR__ . '/config.php';

$pdo     = getPDO();
$errors  = [];
$success = null;

// Token comes from email link
$token = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
}

if ($token === '') {
    $errors[] = 'Invalid or missing reset link.';
    $resetRow = null;
} else {
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare('
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used,
               u.email, u.name
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = :hash
        ORDER BY pr.id DESC
        LIMIT 1
    ');
    $stmt->execute([':hash' => $tokenHash]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRow) {
        $errors[] = 'This reset link is not valid.';
    } elseif ((int)$resetRow['used'] === 1) {
        $errors[] = 'This reset link has already been used.';
    } else {
        $now = new DateTimeImmutable('now');
        $exp = new DateTimeImmutable($resetRow['expires_at']);

        if ($exp < $now) {
            $errors[] = 'This reset link has expired. Please request a new one.';
        }
    }
}

/**
 * Handle POST: change password
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && $resetRow) {
    $csrf      = $_POST['csrf_token'] ?? '';
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            // Update user password
            $stmt = $pdo->prepare('
                UPDATE users
                SET password_hash = :password_hash
                WHERE id = :uid
            ');
            $stmt->execute([
                ':password_hash' => $hash,
                ':uid'           => $resetRow['user_id'],
            ]);

            // Mark token as used
            $stmt = $pdo->prepare('
                UPDATE password_resets
                SET used = 1
                WHERE id = :id
            ');
            $stmt->execute([':id' => $resetRow['id']]);

            $pdo->commit();

            $success = 'Your password has been updated. You can now log in.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Could not update password. Please try again.';
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Reset password';
require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Choose a new password</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Enter and confirm your new password for your Hijazi Real Estate account.
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">
            <?= e($success) ?>
        </div>

        <p style="margin-top:0.75rem;font-size:0.9rem;">
            <a href="<?= BASE_URL ?>/login.php">Go to login</a>
        </p>
    <?php endif; ?>

    <?php if (!$success && $resetRow && empty($errors)): ?>
        <form method="post" style="max-width:420px;margin-top:1rem;">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-field">
                <label class="form-label" for="password">New password</label>
                <div class="text-field">
                    <div class="password-field js-password-field">
                        <input
                            type="password"
                            id="password"
                            name="password"
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

            <div class="form-field">
                <label class="form-label" for="password_confirm">Confirm password</label>
                <div class="text-field">
                    <div class="password-field js-password-field">
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
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

            <button type="submit" class="btn btn-primary">
                Save new password
            </button>
        </form>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>