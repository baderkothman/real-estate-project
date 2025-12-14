<?php

/**
 * partials/header.php
 *
 * Global page header + top navigation.
 *
 * IMPORTANT:
 * - config.php MUST be required before including this file.
 *   Example:
 *       require_once __DIR__ . '/config.php';
 *       require_once __DIR__ . '/partials/header.php';
 *
 * config.php should:
 *   - call session_start()
 *   - define APP_NAME, BASE_URL
 *   - provide auth helpers: isLoggedIn(), isAdmin(), ...
 *   - provide e() (HTML escaping helper)
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <!-- Page title -->
    <title>
        <?= isset($pageTitle)
            ? e($pageTitle . ' · ' . APP_NAME)
            : e(APP_NAME) ?>
    </title>

    <!-- Responsive layout -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Browser can use light/dark; actual colors come from CSS variables -->
    <meta name="color-scheme" content="light dark">

    <!-- Roboto font (Material-ish) -->
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap"
        rel="stylesheet">

    <!-- Main stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/logo.svg">
</head>

<body>
    <!-- Top application bar -->
    <header class="app-bar">
        <div class="container">
            <div class="app-bar__content">
                <!-- Left: brand (also acts as Home) -->
                <a href="<?= BASE_URL ?>/index.php" class="app-bar__brand">
                    <img
                        src="<?= BASE_URL ?>/assets/img/logo.svg"
                        alt="<?= e(APP_NAME) ?> logo"
                        class="app-bar__logo">
                    <span class="app-bar__title"><?= e(APP_NAME) ?></span>
                </a>


                <!-- Hamburger toggle (visible on tablet / mobile) -->
                <button
                    class="app-bar__menu-toggle"
                    id="navToggle"
                    type="button"
                    aria-label="Toggle navigation"
                    aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <!-- Center + right navigation -->
                <nav class="app-bar__nav" id="mainNav">
                    <!-- Center: main site links -->
                    <div class="app-bar__nav-main">
                        <a href="<?= BASE_URL ?>/properties.php" class="nav-link">Properties</a>
                        <a href="<?= BASE_URL ?>/users.php" class="nav-link">Owners</a>
                        <a href="<?= BASE_URL ?>/pricing.php" class="nav-link">Pricing</a>
                        <a href="<?= BASE_URL ?>/about.php" class="nav-link">About</a>
                    </div>

                    <!-- Right: user / auth links -->
                    <div class="app-bar__nav-auth">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?= BASE_URL ?>/property-create.php" class="nav-link">Post property</a>
                            <a href="<?= BASE_URL ?>/profile.php" class="nav-link">Profile</a>
                            <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-link--primary">Logout</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/login.php" class="nav-link">Sign in</a>
                            <a href="<?= BASE_URL ?>/register.php" class="nav-link nav-link--primary">Sign up</a>
                        <?php endif; ?>
                    </div>

                    <!-- Right-most: admin area (only for admins) -->
                    <?php if (isAdmin()): ?>
                        <div class="app-bar__nav-admin">
                            <span class="nav-label">Admin</span>
                            <a href="<?= BASE_URL ?>/admin/index.php" class="nav-link nav-link--chip">Dashboard</a>
                            <a href="<?= BASE_URL ?>/admin/properties.php?status=pending" class="nav-link nav-link--chip">
                                Approvals
                            </a>
                            <a href="<?= BASE_URL ?>/admin/users.php" class="nav-link nav-link--chip">Users</a>
                            <a href="<?= BASE_URL ?>/admin/analytics.php" class="nav-link nav-link--chip">Analytics</a>
                        </div>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main content wrapper -->
    <main class="page-content container">