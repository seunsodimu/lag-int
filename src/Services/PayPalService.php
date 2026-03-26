<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Services\DatabaseService;

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

    public function __construct($environment = null) {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->credentials = $credentials['paypal'] ?? [];
        $this->logger = Logger::getInstance();
        
        if (empty($this->credentials)) {
            $this->logger->error("PayPal credentials not found in config/credentials.php");
        }
        
        // Allow switch between paypal sandbox and paypal live
        $env = $environment ?: ($this->credentials['environment'] ?? 'production');
        
        if ($env === 'sandbox') {
            $this->baseUrl = 'https://api-m.sandbox.paypal.com';
            // Use Sandbox credentials if available in .env, otherwise use defaults from config
            $this->credentials['client_id'] = $_ENV['PAYPAL_SB_CLIENT_ID'] ?? ($this->credentials['client_id'] ?? '');
            $this->credentials['client_secret'] = $_ENV['PAYPAL_SB_CLIENT_SECRET'] ?? ($this->credentials['client_secret'] ?? '');
            $this->credentials['return_url'] = $_ENV['PAYPAL_SB_PAYMENT_RETURN_URL'] ?? ($this->credentials['return_url'] ?? '');
        } else {
            $this->baseUrl = 'https://api-m.paypal.com';
            // Use Live credentials if available in .env
            $this->credentials['client_id'] = $_ENV['PAYPAL_CLIENT_ID'] ?? ($this->credentials['client_id'] ?? '');
            $this->credentials['client_secret'] = $_ENV['PAYPAL_CLIENT_SECRET'] ?? ($this->credentials['client_secret'] ?? '');
            $this->credentials['return_url'] = $_ENV['PAYPAL_PAYMENT_RETURN_URL'] ?? ($this->credentials['return_url'] ?? '');
        }

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

        if (empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            $this->logger->error("PayPal credentials missing. Cannot get access token.");
            return null;
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
     * Create a PayPal Invoice from NetSuite Sales Order data
     * 
     * @param array $nsData NetSuite Sales Order data
     * @return array|null The generated invoice data or null on failure
     */
    public function createInvoice($nsData) {
        try {
            $accessToken = $this->getAccessToken();
            $requestId = bin2hex(random_bytes(16));

            // Calculate due date based on terms from database
            $tranDate = $nsData['tranDate'] ?? date('Y-m-d');
            $dueDate = $tranDate;
            $termId = $nsData['terms']['id'] ?? null;
            
            if ($termId) {
                try {
                    $db = DatabaseService::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT term, invoice_due_days FROM terms WHERE id = ?");
                    $stmt->execute([$termId]);
                    $termData = $stmt->fetch();
                    
                    if ($termData && isset($termData['invoice_due_days'])) {
                        $days = (int)$termData['invoice_due_days'];
                        $dueDate = date('Y-m-d', strtotime("$tranDate +$days days"));
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to retrieve term data from database', [
                        'term_id' => $termId,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Fallback to regex if database fails
                    $termName = $nsData['terms']['refName'] ?? '';
                    if (preg_match('/Net\s+(\d+)/i', $termName, $matches)) {
                        $days = (int)$matches[1];
                        $dueDate = date('Y-m-d', strtotime("$tranDate +$days days"));
                    }
                }
            }

            $billingInfo = [
                'name' => [
                    'given_name' => $nsData['custbody_ava_customerentityid'] ?? ($nsData['entity']['refName'] ?? 'Customer')
                ],
                'address' => [
                    'address_line_1' => $nsData['billingAddress']['addr1'] ?? '',
                    'address_line_2' => $nsData['billingAddress']['addr2'] ?? '',
                    'admin_area_2' => $nsData['billingAddress']['city'] ?? '',
                    'admin_area_1' => $nsData['billingAddress']['state'] ?? '',
                    'postal_code' => $nsData['billingAddress']['zip'] ?? '',
                    'country_code' => $nsData['billingAddress']['country']['id'] ?? 'US'
                ],
                'email_address' => $nsData['email'] ?? '',
                'additional_info_value' => $nsData['entity']['id'] ?? ($nsData['otherRefNum'] ?? '')
            ];

            if (!empty($nsData['billingAddress']['addrPhone'])) {
                $billingInfo['phones'] = [
                    [
                        'country_code' => '001',
                        'national_number' => $this->stripLeadingCountryCode($nsData['billingAddress']['addrPhone']),
                        'phone_type' => 'HOME'
                    ]
                ];
            }

            $payload = [
                'detail' => [
                    'invoice_number' => $nsData['tranid'] ?? null,
                    'reference' => $nsData['transactionnumber'] ?? null,
                    'invoice_date' => $tranDate,
                    'currency_code' => 'USD',
                    'note' => $nsData['custbody2'] ?? 'Thank you for your business.',
                    'memo' => $nsData['memo'] ?? "Generated from NetSuite Sales Order " . ($nsData['tranid'] ?? ''),
                    'payment_term' => [
                        'term_type' => 'DUE_ON_DATE_SPECIFIED',
                        'due_date' => $dueDate
                    ]
                ],
                'invoicer' => [
                    'name' => [
                        'given_name' => 'Laguna Tools'
                    ],
                    'address' => [
                        'address_line_1' => '744 Refuge Way',
                        'address_line_2' => 'Suite 200',
                        'admin_area_2' => 'Grand Prairie',
                        'admin_area_1' => 'TX',
                        'postal_code' => '75050',
                        'country_code' => 'US'
                    ],
                    'email_address' => 'Ar@lagunatools.com',
                    'phones' => [
                        [
                            'country_code' => '001',
                            'national_number' => '8002341976',
                            'phone_type' => 'MOBILE'
                        ]
                    ],
                    'website' => 'www.lagunatools.com',
                    'tax_id' => '',
                    'logo_url' => 'https://lagunatools.com/wp-content/uploads/2024/01/LAGUNA_DECAL_LOGO.png'
                ],
                'primary_recipients' => [
                    [
                        'billing_info' => $billingInfo,
                        'shipping_info' => [
                            'name' => [
                                'given_name' => $nsData['shippingAddress']['addressee'] ?? ($nsData['entity']['refName'] ?? 'Customer')
                            ],
                            'address' => [
                                'address_line_1' => $nsData['shippingAddress']['addr1'] ?? '',
                                'address_line_2' => $nsData['shippingAddress']['addr2'] ?? '',
                                'admin_area_2' => $nsData['shippingAddress']['city'] ?? '',
                                'admin_area_1' => $nsData['shippingAddress']['state'] ?? '',
                                'postal_code' => $nsData['shippingAddress']['zip'] ?? '',
                                'country_code' => $nsData['shippingAddress']['country']['id'] ?? 'US'
                            ]
                        ]
                    ]
                ],
                'items' => $this->mapItems($nsData['item']['items'] ?? []),
                'configuration' => [
                    'allow_tip' => false,
                    'tax_calculated_after_discount' => true,
                    'tax_inclusive' => $nsData['custbody_ava_taxinclude'] ?? false
                ],
                'amount' => [
                    'breakdown' => [
                        'shipping' => [
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => number_format((float)($nsData['shippingCost'] ?? 0), 2, '.', '')
                            ]
                        ],
                        'custom' => [
                            'label' => 'Handling Charges',
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => number_format((float)($nsData['handlingCost'] ?? ($nsData['altHandlingCost'] ?? 0)), 2, '.', '')
                            ]
                        ]
                    ]
                ]
            ];

            // Add discount breakdown if discountTotal exists and is negative (NetSuite discounts are usually negative)
            $discountTotal = (float)($nsData['discountTotal'] ?? 0);
            if ($discountTotal != 0) {
                $payload['amount']['breakdown']['discount'] = [
                    'invoice_discount' => [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format(abs($discountTotal), 2, '.', '')
                        ]
                    ]
                ];
            }

            $this->logger->info('Creating PayPal invoice', [
                'order_id' => $nsData['tranId'] ?? 'N/A',
                'amount' => $nsData['total'] ?? 0,
                'payload' => $payload
            ]);

            $response = $this->client->request('POST', '/v2/invoicing/invoices', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'PayPal-Request-Id' => $requestId
                ],
                'json' => $payload
            ]);

            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            // Extract ID if not directly present (it might be in a link)
            if (!isset($data['id'])) {
                if (isset($data['href'])) {
                    $parts = explode('/', $data['href']);
                    $data['id'] = end($parts);
                } elseif (isset($data['rel']) && $data['rel'] === 'self' && isset($data['href'])) {
                    $parts = explode('/', $data['href']);
                    $data['id'] = end($parts);
                }
            }

            $this->logger->info('PayPal invoice created successfully', [
                'order_id' => $nsData['tranId'] ?? 'N/A',
                'invoice_id' => $data['id'] ?? 'N/A'
            ]);

            return $data;

        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('Failed to create PayPal invoice', [
                'order_id' => $nsData['tranId'] ?? 'N/A',
                'error' => $e->getMessage(),
                'response' => $responseBody,
                'payload' => $payload
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error creating PayPal invoice', [
                'order_id' => $nsData['tranId'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get a PayPal Invoice by ID
     * 
     * @param string $invoiceId
     * @return array|null
     */
    public function getInvoice($invoiceId) {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->request('GET', "/v2/invoicing/invoices/{$invoiceId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('Failed to get PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error getting PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update a PayPal Invoice
     * 
     * @param string $invoiceId
     * @param array $payload
     * @return array|null
     */
    public function updateInvoice($invoiceId, $payload) {
        try {
            $accessToken = $this->getAccessToken();

            $this->logger->info('Updating PayPal invoice', [
                'invoice_id' => $invoiceId,
                'payload' => $payload
            ]);

            $response = $this->client->request('PUT', "/v2/invoicing/invoices/{$invoiceId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $payload
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('Failed to update PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error updating PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send a PayPal Invoice
     * 
     * @param string $invoiceId
     * @param bool $sendToInvoicer
     * @param bool $sendToRecipient
     * @return bool Success status
     */
    public function sendInvoice($invoiceId, $sendToInvoicer = false, $sendToRecipient = false) {
        try {
            $accessToken = $this->getAccessToken();

            $this->logger->info('Sending PayPal invoice', [
                'invoice_id' => $invoiceId,
                'send_to_invoicer' => 'false',
                'send_to_recipient' => 'false'
            ]);

            $response = $this->client->request('POST', "/v2/invoicing/invoices/{$invoiceId}/send", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'send_to_invoicer' => 'false',
                    'send_to_recipient' => 'false'
                ]
            ]);

            return $response->getStatusCode() === 204 || $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('Failed to send PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'response' => $responseBody,
                'payload' => [
                    'send_to_invoicer' => 'false',
                    'send_to_recipient' => 'false'
                ]
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error sending PayPal invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Map NetSuite items to PayPal items
     */
    private function mapItems($nsItems) {
        $items = [];
        foreach ($nsItems as $nsItem) {
            // Skip items without name or description
            if (empty($nsItem['custcol26']) && empty($nsItem['description'])) continue;

            $quantity = (float)($nsItem['quantity'] ?? 1);
            if ($quantity == 0) $quantity = 1; // PayPal requires quantity > 0

            $amount = (float)($nsItem['amount'] ?? 0);
            $unitAmount = round($amount / $quantity, 2);

            $items[] = [
                'name' => substr($nsItem['custcol26'] ?? ($nsItem['description'] ?? 'Item'), 0, 200),
                'description' => substr($nsItem['description'] ?? '', 0, 1000),
                'quantity' => (string)$quantity,
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($unitAmount, 2, '.', '')
                ],
                'unit_of_measure' => 'QUANTITY'
            ];
        }
        return $items;
    }

    /**
     * Strip leading country code from phone number
     */
    private function stripLeadingCountryCode($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 10 && (str_starts_with($phone, '1') || str_starts_with($phone, '01'))) {
            return substr($phone, -10);
        }
        return $phone;
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
