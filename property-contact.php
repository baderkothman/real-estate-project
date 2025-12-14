<?php
require_once __DIR__ . '/config.php';

requireLogin();
$pdo = getPDO();

$propertyId = isset($_POST['property_id']) ? (int)$_POST['property_id'] : 0;
if ($propertyId <= 0) {
    header('Location: ' . BASE_URL . '/properties.php');
    exit;
}


incrementPropertyStat($propertyId, 'contact_clicks', $pdo);



header('Location: ' . BASE_URL . '/property.php?id=' . $propertyId . '#contact');
exit;
