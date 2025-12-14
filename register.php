<?php

/**
 * register.php
 * -------------------------------------------
 * User registration for Othman Real Estate.
 *
 * - Name, email, phone, password.
 * - Email + phone must be unique.
 * - Phone is required and can be used for login.
 */

require_once __DIR__ . '/config.php';

$errors = [];
$name   = '';
$email  = '';
$phone  = '';

// ---------------------------------------------------------
// Handle POST (form submission)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    // 1) CSRF protection
    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    // 2) Basic validation
    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (digits, optional +).';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // 3) Create user if everything is valid
    if (!$errors) {
        $pdo = getPDO();

        // Check if email OR phone already exists
        $stmt = $pdo->prepare('
            SELECT email, phone
            FROM users
            WHERE email = :email OR phone = :phone
            LIMIT 1
        ');
        $stmt->execute([
            ':email' => $email,
            ':phone' => $phone,
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (!empty($existing['email']) && $existing['email'] === $email) {
                $errors[] = 'This email is already registered.';
            }
            if (!empty($existing['phone']) && $existing['phone'] === $phone) {
                $errors[] = 'This phone number is already registered.';
            }
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, phone, password_hash)
                 VALUES (:name, :email, :phone, :password_hash)'
            );
            $stmt->execute([
                ':name'          => $name,
                ':email'         => $email,
                ':phone'         => $phone,
                ':password_hash' => $passwordHash,
            ]);

            $userId = (int) $pdo->lastInsertId();

            // Auto-login new user
            session_regenerate_id(true);
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'user';
            setCurrentUserPlan('free');

            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

// CSRF token for the form
$csrfToken = getCsrfToken();

// Page title for header.php
$pageTitle = 'Sign up';

require_once __DIR__ . '/partials/header.php';
?>

<div class="auth-layout">
    <section class="auth-card">
        <h1 class="auth-card__title">Create an account</h1>
        <p class="auth-card__subtitle">
            Save your favorite properties, track your listings, and chat with buyers.
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
                <label class="form-label" for="name">Full name</label>
                <div class="text-field">
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        required
                        autocomplete="name">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="email">Email address</label>
                <div class="text-field">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        required
                        autocomplete="email">
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="phone">Phone number</label>
                <div class="text-field">
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= e($phone) ?>"
                        required
                        autocomplete="tel"
                        placeholder="+961 70 123 456">
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

            <div class="form-field">
                <label class="form-label" for="password_confirm">Confirm password</label>
                <div class="text-field password-field js-password-field">
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

            <div style="margin-top:1.2rem;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    Sign up
                </button>
            </div>

            <p style="margin-top:0.75rem;font-size:0.9rem;">
                Already have an account?
                <a href="<?= BASE_URL ?>/login.php">Log in</a>
            </p>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>