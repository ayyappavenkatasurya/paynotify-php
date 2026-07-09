# PayNotify PHP SDK

The official PHP Server SDK for **PayNotify** — the zero-MDR, automated, lifetime-free UPI payment gateway engine.

Designed for enterprise reliability, this SDK provides seamless integration with the PayNotify architecture, enabling **Dynamic Cent Masking** for payment concurrency management and **Cryptographic HMAC-SHA256 Webhook Verification** using stable string formatting to guarantee bank-grade security against replay and JSON mutation attacks.

---

## ✨ Features

### Dynamic Cent Masking (Penny Drop)

Automatically resolves payment concurrency (for example, multiple users checking out with ₹49 simultaneously) by generating unique fractional amounts through an atomic database lock.

### Cryptographic Webhook Security (Stable String)

Built-in logic validates the `X-PayNotify-Signature` using **HMAC-SHA256**. It securely signs a fixed string template:

```text
orderId:amount:status:timestamp
```

This completely prevents JSON parsing drift vulnerabilities and replay attacks (via a strict 5-minute expiry window).

### Client-Side Idempotency

Prevents duplicate orders and double-charging. The SDK requires you to pass a stable, unique string (like a cart ID or session UUID) during order creation.

---

# Installation

Using Composer:

```bash
composer require paynotify/paynotify-php
```

---

# Quick Start

## Initialization

Initialize the PayNotify client using your secret API key.

> **Security Warning:** Never expose your API key in frontend or client-side code.

```php
require 'vendor/autoload.php';

use PayNotify\PayNotifyClient;

$paynotify = new PayNotifyClient('your-secure-api-key');
```

---

## Creating a Payment Order

When a user initiates checkout, create an order on your backend. The SDK communicates with the PayNotify Engine to lock in a concurrency-safe amount.

```php
try {
    $orderData = $paynotify->createOrder(
        49.00, // baseAmount
        "Surya", // customerName
        "unique-cart-id-123" // idempotencyKey (STRICTLY REQUIRED)
    );

    // Returns: ['success' => true, 'orderId' => '...', 'amount' => 49.01, 'status' => 'PENDING']
    echo json_encode($orderData);

} catch (\PayNotify\Exceptions\PayNotifyException $e) {
    http_response_code(503);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

## Securing Webhooks

PayNotify sends real-time webhooks whenever a payment is verified. Every incoming request must be authenticated before processing.

```php
try {
    $signatureHeader = $_SERVER['HTTP_X_PAYNOTIFY_SIGNATURE'] ?? null;
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);

    // Automatically verifies signature and prevents replay attacks
    $paynotify->verifyWebhook($signatureHeader, $payload);

    if ($payload['status'] === 'VERIFIED') {
        // Unlock user content, update database, etc.
        echo json_encode(['success' => true]);
    }

} catch (\PayNotify\Exceptions\SignatureVerificationException $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

# API Reference

## `new PayNotifyClient(string $apiKey, string $gatewayUrl = 'https://paypager.vercel.app')`

Creates a new PayNotify client instance.

---

## `createOrder(float $baseAmount, string $customerName, string $idempotencyKey): array`

Creates a new payment order atomically.

### Parameters

| Parameter        | Type     | Required | Description                                                                               |
| ---------------- | -------- | -------- | ----------------------------------------------------------------------------------------- |
| `$baseAmount`    | `float`  | ✅        | Original payment amount                                                                   |
| `$customerName`  | `string` | ✅        | Customer name shown in dashboards                                                         |
| `$idempotencyKey`| `string` | ✅        | Prevents duplicate orders during retries. Must be a unique string per transaction.        |

---

## `verifyWebhook(?string $signatureHeader, array $payload, int $toleranceSeconds = 300): bool`

Validates webhook signatures using the stable string format: `orderId:amount:status:timestamp`

* Verifies `X-PayNotify-Signature` using HMAC-SHA256
* Prevents replay attacks using `$toleranceSeconds` (defaults to 5 minutes)
* Throws `SignatureVerificationException` on failure

---

# Security Best Practices

* Never expose API keys in frontend applications.
* Always verify webhook signatures using the SDK.
* Store `orderId` and assigned payment amounts in your database.
* Process only payments with status `VERIFIED`.
* Use HTTPS in all production environments.

---

# License

MIT License.

```text
Copyright (c) PayNotify.
```
