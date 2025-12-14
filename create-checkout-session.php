<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    http_response_code(400);
    exit('Invalid session token.');
}

$plan    = $_POST['plan']    ?? '';
$billing = $_POST['billing'] ?? 'month'; // 'month', 'quarter', 'year'

if (!in_array($plan, ['pro', 'agency'], true)) {
    http_response_code(400);
    exit('Invalid plan.');
}





$priceId = null;

if ($plan === 'pro') {
    switch ($billing) {
        case 'month':
            $priceId = STRIPE_PRICE_PRO_MONTH;
            break;
        case 'quarter':
            $priceId = STRIPE_PRICE_PRO_QUARTER;
            break;
        case 'year':
            $priceId = STRIPE_PRICE_PRO_YEAR;
            break;
    }
} elseif ($plan === 'agency') {
    switch ($billing) {
        case 'month':
            $priceId = STRIPE_PRICE_AGENCY_MONTH;
            break;
        case 'quarter':
            $priceId = STRIPE_PRICE_AGENCY_QUARTER;
            break;
        case 'year':
            $priceId = STRIPE_PRICE_AGENCY_YEAR;
            break;
    }
}

if ($priceId === null) {
    http_response_code(400);
    exit('Invalid billing period for this plan.');
}


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$domain   = $_SERVER['HTTP_HOST'];
$baseUrl  = $protocol . $domain . BASE_URL;

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'subscription',
        'line_items' => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'success_url' => $baseUrl . '/stripe-success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $baseUrl . '/pricing.php?canceled=1',
        'metadata' => [
            'user_id' => currentUserId(),
            'plan'    => $plan,
            'billing' => $billing, // nice to keep for info
        ],
    ]);

    header('Location: ' . $session->url, true, 303);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Stripe error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
