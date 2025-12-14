<?php
// profile-edit.php
// --------------------------------------------------------------
// Edit profile information + account security (change password)
// --------------------------------------------------------------

require_once __DIR__ . '/config.php';

requireLogin();
$pdo    = getPDO();
$userId = currentUserId();

$errors  = [];
$success = null;

/**
 * Load current user (now includes phone)
 */
$stmt = $pdo->prepare('
    SELECT id, name, email, phone, bio, profile_image, created_at
    FROM users
    WHERE id = :id
    LIMIT 1
');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo 'User not found.';
    exit;
}

// Pre-fill form values
$name  = $user['name']  ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$bio   = $user['bio']   ?? '';

/**
 * Handle form submit
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $errors[] = 'Invalid session token. Please try again.';
    }

    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio   = trim($_POST['bio']   ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number (digits, optional +).';
    }

    // Check if email or phone is used by another account
    if (empty($errors)) {
        $stmtCheck = $pdo->prepare('
            SELECT id, email, phone
            FROM users
            WHERE (email = :email OR phone = :phone)
              AND id <> :id
            LIMIT 1
        ');
        $stmtCheck->execute([
            ':email' => $email,
            ':phone' => $phone,
            ':id'    => $userId,
        ]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (!empty($existing['email']) && $existing['email'] === $email) {
                $errors[] = 'This email is already used by another account.';
            }
            if (!empty($existing['phone']) && $existing['phone'] === $phone) {
                $errors[] = 'This phone number is already used by another account.';
            }
        }
    }

    // Handle profile image upload (optional)
    $newProfileImage = null;

    if (
        isset($_FILES['profile_image']) &&
        is_array($_FILES['profile_image']) &&
        $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file  = $_FILES['profile_image'];
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_OK) {
            $tmpName = $file['tmp_name'] ?? '';
            $size    = $file['size'] ?? 0;

            if ($size > 5 * 1024 * 1024) {
                $errors[] = 'Profile picture must be smaller than 5 MB.';
            } elseif (!is_uploaded_file($tmpName)) {
                $errors[] = 'Error uploading profile picture.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($tmpName);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowed[$mime])) {
                    $errors[] = 'Profile picture must be JPG, PNG or WEBP.';
                } else {
                    if (!is_dir(PROFILE_UPLOAD_DIR)) {
                        mkdir(PROFILE_UPLOAD_DIR, 0777, true);
                    }

                    $ext      = $allowed[$mime];
                    $fileName = 'u_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target   = PROFILE_UPLOAD_DIR . $fileName;

                    if (!move_uploaded_file($tmpName, $target)) {
                        $errors[] = 'Could not save profile picture.';
                    } else {
                        $newProfileImage = $fileName;
                    }
                }
            }
        } else {
            $errors[] = 'Error uploading profile picture.';
        }
    }

    if (empty($errors)) {
        $sql = '
            UPDATE users
            SET name  = :name,
                email = :email,
                phone = :phone,
                bio   = :bio
        ';

        $params = [
            ':name'  => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':bio'   => $bio,
            ':id'    => $userId,
        ];

        if ($newProfileImage !== null) {
            $sql .= ', profile_image = :profile_image';
            $params[':profile_image'] = $newProfileImage;
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Keep name in session in sync with profile
        $_SESSION['user_name'] = $name;

        // refresh user array for the view
        $user['name']  = $name;
        $user['email'] = $email;
        $user['phone'] = $phone;
        $user['bio']   = $bio;
        if ($newProfileImage !== null) {
            $user['profile_image'] = $newProfileImage;
        }

        $success = 'Profile updated successfully.';
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Edit profile';
require_once __DIR__ . '/partials/header.php';

$avatarUrl = !empty($user['profile_image'])
    ? PROFILE_UPLOAD_URL . '/' . e($user['profile_image'])
    : null;
?>

<section class="section-card">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom:0.25rem;">Edit profile</h1>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Update your personal information and profile photo.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/profile.php" class="btn">
            Back to profile
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($success): ?>
        <div class="alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <div class="form-grid-2">
            <div>
                <div class="form-field">
                    <label class="form-label" for="name">Full name</label>
                    <div class="text-field">
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e($name) ?>"
                            required>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="email">Email</label>
                    <div class="text-field">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e($email) ?>"
                            required>
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
                            placeholder="+961 70 123 456">
                    </div>
                </div>
            </div>

            <div>
                <div class="form-field">
                    <label class="form-label">Profile picture</label>
                    <div style="display:flex;align-items:center;gap:1rem;margin-top:0.5rem;">
                        <?php if ($avatarUrl): ?>
                            <img
                                src="<?= $avatarUrl ?>"
                                alt="Profile picture preview"
                                class="profile-avatar-lg"
                                style="border-radius:50%;border:1px solid rgba(0,0,0,0.1);">
                        <?php else: ?>
                            <div class="testimonial-avatar profile-avatar-lg">
                                <?= e(strtoupper($user['name'][0])) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-field" style="flex:1;">
                            <input type="file" name="profile_image" accept="image/*">
                            <p style="margin:0.25rem 0 0;font-size:0.78rem;opacity:0.7;">
                                JPG, PNG or WEBP, up to 5 MB.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="bio">Bio</label>
            <div class="text-field">
                <textarea
                    id="bio"
                    name="bio"
                    rows="4"
                    placeholder="Tell buyers a bit about yourself and the properties you manage."><?= e($bio) ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            Save changes
        </button>
    </form>
</section>

<!-- Account security: Change / Forgot password -->
<section class="section-card" style="margin-top:1.5rem;">
    <div class="page-header">
        <div>
            <h2 style="margin-bottom:0.25rem;">Account security</h2>
            <p style="margin:0;opacity:0.8;font-size:0.9rem;">
                Manage your password and recovery options.
            </p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <h3 style="margin:0 0 0.4rem;font-size:0.96rem;">Change password</h3>
            <p style="margin:0 0 0.7rem;font-size:0.86rem;opacity:0.8;">
                Update the password you use to sign in to Othman Real Estate.
            </p>
            <a href="<?= BASE_URL ?>/change-password.php" class="btn btn-primary">
                Change password
            </a>
        </div>

        <div>
            <h3 style="margin:0 0 0.4rem;font-size:0.96rem;">Forgot your password?</h3>
            <p style="margin:0 0 0.7rem;font-size:0.86rem;opacity:0.8;">
                If you ever lose access to your current password, you can request a reset
                link by email from the recovery page.
            </p>
            <a href="<?= BASE_URL ?>/forgot-password.php" class="btn">
                Open recovery page
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>