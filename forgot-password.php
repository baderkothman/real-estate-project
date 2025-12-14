<?php
require_once __DIR__ . '/config.php';

$pdo    = getPDO();
$errors = [];
$sent   = false;
$debugResetUrl = null; // shown only in dev

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf  = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Look up user by email
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Always set sent=true to avoid revealing if email exists
        $sent = true;

        if ($user) {
            // 1) Generate token
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            // 2) Store in DB
            $stmt = $pdo->prepare('
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (:uid, :token_hash, :expires_at)
            ');
            $stmt->execute([
                ':uid'        => $user['id'],
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
            ]);

            // 3) Build reset URL (absolute)
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

            $path      = BASE_URL . '/reset-password.php?token=' . urlencode($token);
            $resetUrl  = $scheme . '://' . $host . $path;
            $debugResetUrl = $resetUrl; // show on screen for local dev

            // 4) Send email
            sendPasswordResetEmail($user['email'], $user['name'], $resetUrl);
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Forgot password';
require_once __DIR__ . '/partials/header.php';
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Forgot password</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Enter the email associated with your account and we’ll send you a reset link.
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

    <?php if ($sent && empty($errors)): ?>
        <div class="alert-success">
            If an account exists with that email, you will receive a message with a link
            to reset your password within a few minutes.
        </div>

        <?php if ($debugResetUrl): ?>
            <p style="margin-top:0.75rem;font-size:0.85rem;opacity:0.8;">
                <strong>Development only:</strong> since email may not be configured locally,
                you can use this link directly:
                <br>
                <a href="<?= e($debugResetUrl) ?>"><?= e($debugResetUrl) ?></a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" style="margin-top:1rem;max-width:420px;">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-field">
            <label class="form-label" for="email">Email</label>
            <div class="text-field">
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= isset($email) ? e($email) : '' ?>"
                    required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            Send reset link
        </button>

        <p style="margin-top:0.75rem;font-size:0.86rem;">
            Remember your password?
            <a href="<?= BASE_URL ?>/login.php">Back to login</a>
        </p>
    </form>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>