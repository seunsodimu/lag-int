<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * PayPal API Service
 * 
 * Handles interactions with the PayPal REST API.
 * Documentation: https://developer.paypal.com/docs/api/payment-links-buttons/v1/
 */
class PayPalService {
    private $client;
    private $credentials;
    private $logger;
    private $accessToken;
    private $tokenExpiresAt;
    private $baseUrl;

    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->credentials = $credentials['paypal'];
        $this->logger = Logger::getInstance();
        
        $this->baseUrl = $this->credentials['environment'] === 'production' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Get PayPal Access Token
     */
    private function getAccessToken() {
        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        try {
            $response = $this->client->request('POST', '/v1/oauth2/token', [
                'auth' => [$this->credentials['client_id'], $this->credentials['client_secret']],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->accessToken = $data['access_token'];
            $this->tokenExpiresAt = time() + $data['expires_in'] - 60; // Subtract 60 seconds for safety

            return $this->accessToken;
        } catch (RequestException $e) {
            $this->logger->error('PayPal Authentication Failed', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body'
            ]);
            throw new \Exception('Failed to authenticate with PayPal: ' . $e->getMessage());
        }
    }

    /**
     * Create a payment link for a Sales Order
     * 
     * @param array $orderData Must contain 'tranId' and 'totalAmount'
     * @return string|null The generated payment link URL
     */
    public function createPaymentLink($orderData) {
        try {
            $accessToken = $this->getAccessToken();
            $requestId = bin2hex(random_bytes(16));

            $payload = [
                'type' => 'BUY_NOW',
                'integration_mode' => 'LINK',
                'reusable' => 'MULTIPLE',
                'return_url' => $this->credentials['return_url'],
                'line_items' => [
                    [
                        'name' => 'Payment for Order #' . $orderData['tranId'],
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format((float)$orderData['totalAmount'], 2, '.', '')
                        ]
                    ]
                ]
            ];

            $this->logger->info('Creating PayPal payment link', [
                'order_id' => $orderData['tranId'],
                'amount' => $orderData['totalAmount']
            ]);

            $response = $this->client->request('POST', '/v1/checkout/payment-resources', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'PayPal-Request-Id' => $requestId
                ],
                'json' => $payload
            ]);

            $data = json_decode($response->getBody(), true);

            // The payment link can be at the top level or in the links array
            $paymentLink = $data['payment_link'] ?? null;

            if (!$paymentLink && isset($data['links'])) {
                foreach ($data['links'] as $link) {
                    if ($link['rel'] === 'payment_link') {
                        $paymentLink = $link['href'];
                        break;
                    }
                }
            }

            if ($paymentLink) {
                $this->logger->info('PayPal payment link created successfully', [
                    'order_id' => $orderData['tranId'],
                    'payment_link' => $paymentLink
                ]);
                return $paymentLink;
            }

            $this->logger->error('PayPal response missing payment_link', [
                'order_id' => $orderData['tranId'],
                'response' => $data
            ]);
            return null;

        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('Failed to create PayPal payment link', [
                'order_id' => $orderData['tranId'],
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating PayPal payment link', [
                'order_id' => $orderData['tranId'],
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
