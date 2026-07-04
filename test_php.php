<?php
require 'vendor/autoload.php';

use PayNotify\PayNotifyClient;

$apiKey = "5f634f45-ee32-41d2-817e-d0614ddd5865";
$client = new PayNotifyClient($apiKey);

echo "--- TESTING CREATE ORDER ---\n";
try {
    $order = $client->createOrder(49.00, 'Test PHP User');
    print_r($order);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- TESTING WEBHOOK VERIFICATION ---\n";
$timestamp = (string)intval(microtime(true) * 1000);
$payload = [
    "orderId" => "test_order_123",
    "amount" => 49.01,
    "status" => "VERIFIED",
    "timestamp" => $timestamp
];

$payloadToSign = sprintf("%s:%s:%s:%s", $payload['orderId'], $payload['amount'], $payload['status'], $payload['timestamp']);
$validSignature = hash_hmac('sha256', $payloadToSign, $apiKey);

try {
    $isValid = $client->verifyWebhook($validSignature, $payload);
    echo "Webhook Signature Valid? " . ($isValid ? "YES" : "NO") . "\n";
} catch (\Exception $e) {
    echo "Webhook Error: " . $e->getMessage() . "\n";
}