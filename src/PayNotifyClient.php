<?php

namespace PayNotify;

use PayNotify\Exceptions\PayNotifyException;
use PayNotify\Exceptions\SignatureVerificationException;

class PayNotifyClient
{
    private string $apiKey;
    private string $gatewayUrl;

    /**
     * @param string $apiKey Your PayNotify API Key
     * @param string $gatewayUrl The Base URL of the PayNotify engine
     * @throws PayNotifyException
     */
    public function __construct(string $apiKey, string $gatewayUrl = 'https://paypager.vercel.app')
    {
        if (empty($apiKey)) {
            throw new PayNotifyException("PayNotify: API Key is strictly required.");
        }
        $this->apiKey = $apiKey;
        $this->gatewayUrl = rtrim($gatewayUrl, '/');
    }

    /**
     * Initializes a payment order.
     *
     * @param float $baseAmount Original payment amount
     * @param string $customerName Customer's name
     * @param string $idempotencyKey Required idempotency key to prevent duplicate orders
     * @return array Response from the gateway containing orderId, amount, status
     * @throws PayNotifyException
     */
    public function createOrder(float $baseAmount, string $customerName, string $idempotencyKey): array
    {
        if (empty($idempotencyKey)) {
            throw new PayNotifyException("PayNotify: idempotencyKey is strictly required to prevent duplicate orders during network retries.");
        }

        $payload = json_encode([
            'api_key' => $this->apiKey,
            'base_amount' => $baseAmount,
            'customer_name' => $customerName,
            'idempotency_key' => $idempotencyKey
        ]);

        $ch = curl_init($this->gatewayUrl . '/api/init-order');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new PayNotifyException("PayNotify Gateway Network Error: " . $error);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PayNotifyException("Invalid JSON response from PayNotify Gateway");
        }

        if ($httpCode >= 400) {
            $errorMsg = $data['error'] ?? "Failed to create order (HTTP $httpCode)";
            throw new PayNotifyException($errorMsg);
        }

        return $data;
    }

    /**
     * Verifies the webhook signature using HMAC-SHA256 and Stable String Formatting.
     *
     * @param string|null $signatureHeader The 'X-PayNotify-Signature' header
     * @param array $payload The parsed JSON body as an associative array
     * @param int $toleranceSeconds Replay attack prevention window (default 5 mins)
     * @return bool True if valid
     * @throws SignatureVerificationException
     */
    public function verifyWebhook(?string $signatureHeader, array $payload, int $toleranceSeconds = 300): bool
    {
        if (empty($signatureHeader)) {
            throw new SignatureVerificationException("Missing Signature Header");
        }

        if (!isset($payload['orderId'], $payload['amount'], $payload['status'], $payload['timestamp'])) {
            throw new SignatureVerificationException("Malformed Webhook Payload: Missing required fields");
        }

        $currentTime = (int)(microtime(true) * 1000);
        $payloadTime = (int)$payload['timestamp'];
        
        // Replay Attack Prevention
        if (abs($currentTime - $payloadTime) > ($toleranceSeconds * 1000)) {
            throw new SignatureVerificationException("Webhook Expired (Replay Attack Detected)");
        }

        // Stable String Formatting
        $payloadToSign = sprintf(
            "%s:%s:%s:%s",
            $payload['orderId'],
            $payload['amount'],
            $payload['status'],
            $payload['timestamp']
        );

        $expectedSignature = hash_hmac('sha256', $payloadToSign, $this->apiKey);

        // Timing-safe comparison to prevent side-channel attacks
        if (!hash_equals($expectedSignature, $signatureHeader)) {
            throw new SignatureVerificationException("Invalid Signature");
        }

        return true;
    }
}