<?php

/**
 * login.php
 * -----------------------------
 * User login for Othman Real Estate.
 *
 * - User can log in using EMAIL or PHONE.
 * - Validates CSRF + basic fields.
 * - Blocks banned users.
 */

require_once __DIR__ . '/config.php';

$pdo    = getPDO();
$errors = [];
$login  = '';   // email or phone




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['email'] ?? ''); // form field name kept as "email"
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';


    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Your session has expired. Please reload the page and try again.';
    }


    if ($login === '' || $password === '') {
        $errors[] = 'Please enter your email/phone and password.';
    }


    if (!$errors) {
        $stmt = $pdo->prepare('
            SELECT id, name, email, phone, password_hash, role, is_banned, plan
            FROM users
            WHERE email = :identifier OR phone = :identifier
            LIMIT 1
        ');
        $stmt->execute([':identifier' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid credentials.';
        } elseif ((int)$user['is_banned'] === 1) {
            $errors[] = 'This account has been banned. Please contact support if you think this is a mistake.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            setCurrentUserPlan($user['plan'] ?? 'free');

            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}


if (isset($_GET['banned']) && empty($errors)) {
    $errors[] = 'Your session was closed because your account is currently banned.';
}

$csrfToken = getCsrfToken();
$pageTitle = 'Login';

require_once __DIR__ . '/partials/header.php';
?>

<div class="auth-layout">
    <section class="auth-card">
        <h1 class="auth-card__title">Welcome back</h1>
        <p class="auth-card__subtitle">
            Log in to manage your listings and explore properties across Lebanon.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <?php foreach ($errors as $msg): ?>
                    <div><?= e($msg) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-field">
                <label class="form-label" for="email">Email or phone</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="email"
                        name="email"
                        value="<?= e($login) ?>"
                        required
                        autocomplete="username"
                        placeholder="email@example.com or +96170...">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="password">Password</label>
                <div class="text-field password-field js-password-field">
                    <input
                        type="password"
                        id="password"
                        name="password"
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

            <div style="margin-top:1.2rem;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    Log in
                </button>
            </div>

            <p style="margin-top:0.75rem;font-size:0.9rem;">
                New here?
                <a href="<?= BASE_URL ?>/register.php">Create an account</a>
            </p>

            <p style="margin-top:0.75rem;font-size:0.85rem;text-align:center;">
                <a href="<?= BASE_URL ?>/forgot-password.php">Forgot your password?</a>
            </p>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>