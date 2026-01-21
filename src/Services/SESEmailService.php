<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laguna\Integration\Utils\Logger;

/**
 * AWS SES Email Service - Direct HTTP REST API Implementation
 * 
 * Uses direct HTTP calls to AWS SES API with Signature Version 4 signing.
 * No AWS SDK dependency required.
 */
class SESEmailService {
    private $credentials;
    private $config;
    private $logger;
    private $httpClient;
    private $region;
    private $endpoint;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->credentials = $credentials['email']['ses'];
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        $this->region = $this->credentials['region'] ?? 'us-east-1';
        $this->endpoint = "https://email.{$this->region}.amazonaws.com/";
        
        $this->initializeClient();
    }
    
    private function initializeClient() {
        if (empty($this->credentials['access_key']) || empty($this->credentials['secret_key'])) {
            $this->logger->warning('AWS SES credentials not configured', [
                'hint' => 'Set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY environment variables'
            ]);
            return;
        }
        
        try {
            $this->httpClient = new Client(['timeout' => 30]);
            $this->logger->info('AWS SES HTTP client initialized', ['region' => $this->region]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize AWS SES HTTP client', [
                'error' => $e->getMessage()
            ]);
            $this->httpClient = null;
        }
    }
    
    /**
     * Send email via AWS SES REST API
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        if (!$this->httpClient) {
            return [
                'success' => false,
                'error' => 'AWS SES client not initialized. Ensure AWS credentials are properly configured.',
                'provider' => 'ses'
            ];
        }
        
        try {
            $toAddresses = array_map('trim', (array)$recipients);
            
            $this->logger->info('Sending email via AWS SES REST API', [
                'subject' => $subject,
                'recipients' => $toAddresses,
                'is_test' => $isTest
            ]);
            
            $response = $this->makeSignedRequest('SendEmail', [
                'Source' => $this->credentials['from_email'],
                'Destination.ToAddresses.member.1' => $toAddresses[0],
            ] + $this->buildMultiAddressParams($toAddresses) + [
                'Message.Subject.Data' => $subject,
                'Message.Subject.Charset' => 'UTF-8',
                'Message.Body.Html.Data' => $htmlContent,
                'Message.Body.Html.Charset' => 'UTF-8',
            ]);
            
            $messageId = $this->extractXmlValue($response, 'MessageId');
            
            if (!$messageId) {
                throw new \Exception('No MessageId returned from SES');
            }
            
            $this->logger->info('Email sent successfully via AWS SES', [
                'subject' => $subject,
                'message_id' => $messageId,
                'recipients' => $toAddresses
            ]);
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'status_code' => 200,
                'provider' => 'ses',
                'method' => 'rest_api'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via AWS SES', [
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'ses',
                'method' => 'rest_api'
            ];
        }
    }
    
    /**
     * Send daily order status sync notification
     * 
     * @param array $syncResult Results from the daily status sync process
     * @param array $recipients List of email addresses to notify
     * @return array Send result with success status
     */
    public function sendDailyStatusSyncNotification($syncResult, $recipients = []) {
        if (!($this->config['enabled'] ?? true)) {
            $this->logger->info('Email notifications disabled, skipping daily status sync notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        if (empty($recipients)) {
            $this->logger->warning('No recipients provided for daily status sync notification');
            return ['success' => false, 'error' => 'No recipients provided'];
        }
        
        $subject = ($this->config['subject_prefix'] ?? '[3DCart Integration] ') . "Daily Order Status Sync Report - " . date('Y-m-d');
        $content = $this->buildDailyStatusSyncContent($syncResult);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Build daily order status sync email content
     */
    private function buildDailyStatusSyncContent($syncResult) {
        $date = date('Y-m-d H:i:s');
        $totalOrders = $syncResult['total_orders'] ?? 0;
        $updatedCount = $syncResult['updated_count'] ?? 0;
        $errorCount = $syncResult['error_count'] ?? 0;
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { background-color: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .summary-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .metric { display: inline-block; margin: 10px 20px 10px 0; }
                .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
                .metric-label { font-size: 14px; color: #6c757d; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .success-row { background-color: #f0f8f5; }
                .error-row { background-color: #fef5f5; }
                .skipped-row { background-color: #fffbf0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>📋 Daily Order Status Sync Report - {$date}</h2>
            </div>
            
            <div class='summary-box'>
                <h3>Synchronization Statistics</h3>
                <div class='metric'>
                    <div class='metric-value'>{$totalOrders}</div>
                    <div class='metric-label'>Total Orders</div>
                </div>
                <div class='metric'>
                    <div class='metric-value' style='color: #28a745;'>{$updatedCount}</div>
                    <div class='metric-label'>Updated</div>
                </div>
                <div class='metric'>
                    <div class='metric-value' style='color: #dc3545;'>{$errorCount}</div>
                    <div class='metric-label'>Errors</div>
                </div>
            </div>";
        
        // Add detailed results if available
        if (isset($syncResult['results']) && !empty($syncResult['results'])) {
            $html .= "
            <div class='summary-box'>
                <h3>Detailed Results</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            foreach ($syncResult['results'] as $result) {
                $orderId = $result['order_id'] ?? 'N/A';
                $statusClass = 'skipped-row';
                $statusBadge = '⏭️ SKIPPED';
                
                if ($result['updated'] ?? false) {
                    $statusClass = 'success-row';
                    $statusBadge = '✅ UPDATED';
                } elseif (!empty($result['error'])) {
                    $statusClass = 'error-row';
                    $statusBadge = '❌ ERROR';
                }
                
                $reason = $result['reason'] ?? $result['error'] ?? 'No details';
                $html .= "
                        <tr class='{$statusClass}'>
                            <td>Order #{$orderId}</td>
                            <td>{$statusBadge}</td>
                            <td>{$reason}</td>
                        </tr>";
            }
            
            $html .= "
                    </tbody>
                </table>
            </div>";
        }
        
        $html .= "
            <div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
                <p>This report was generated automatically by the Order Status Synchronization system.</p>
                <p>The synchronization process checks 3DCart orders with Processing status and updates them based on their NetSuite counterpart status.</p>
                <p>Generated: {$date}</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Test AWS SES connection
     */
    public function testConnection() {
        if (!$this->httpClient) {
            return [
                'success' => false,
                'error' => 'AWS SES HTTP client not initialized',
                'service' => 'AWS SES',
                'hint' => 'Check AWS credentials configuration'
            ];
        }
        
        try {
            $response = $this->makeSignedRequest('GetSendQuota', []);
            
            $max24Hour = (int)$this->extractXmlValue($response, 'Max24HourSend');
            $sentLast24Hour = (int)$this->extractXmlValue($response, 'SentLast24Hour');
            $maxSendRate = (int)$this->extractXmlValue($response, 'MaxSendRate');
            
            $this->logger->info('AWS SES connection test successful', [
                'max_24_hour_send' => $max24Hour,
                'sent_last_24_hour' => $sentLast24Hour
            ]);
            
            return [
                'success' => true,
                'status_code' => 200,
                'service' => 'AWS SES (REST API)',
                'region' => $this->region,
                'quota' => [
                    'max_24_hour_send' => $max24Hour,
                    'sent_last_24_hour' => $sentLast24Hour,
                    'max_send_rate' => $maxSendRate
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('AWS SES connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'AWS SES',
                'hint' => 'Verify AWS credentials and region configuration'
            ];
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($recipientEmail, $type = 'order') {
        $subject = match($type) {
            'order' => 'Test Email - Order Notification',
            'sync' => 'Test Email - Sync Report',
            default => 'Test Email - 3DCart Integration'
        };
        
        $htmlContent = $this->getTestEmailTemplate($type);
        
        return $this->sendEmail($subject, $htmlContent, [$recipientEmail], true);
    }
    
    /**
     * Send daily sync report
     */
    public function sendDailySyncReport($recipients, $reportData = []) {
        $subject = 'Daily 3DCart → NetSuite Sync Report';
        $htmlContent = $this->getSyncReportTemplate($reportData);
        
        return $this->sendEmail($subject, $htmlContent, $recipients);
    }
    
    /**
     * Make signed AWS request
     */
    private function makeSignedRequest($action, $params) {
        $timestamp = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $params['Action'] = $action;
        $params['Version'] = '2010-12-01';
        
        $canonicalRequest = $this->buildCanonicalRequest('POST', '/', $params, $timestamp, $datestamp);
        $signature = $this->calculateSignature($canonicalRequest, $datestamp);
        
        $authHeader = $this->buildAuthorizationHeader($signature, $datestamp, $timestamp);
        
        $body = http_build_query($params);
        
        try {
            $guzzleResponse = $this->httpClient->post($this->endpoint, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'X-Amz-Date' => $timestamp,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Host' => parse_url($this->endpoint, PHP_URL_HOST),
                ],
                'body' => $body,
                'allow_redirects' => false,
            ]);
            
            $responseBody = $guzzleResponse->getBody()->getContents();
            
            if ($guzzleResponse->getStatusCode() !== 200) {
                throw new \Exception("AWS SES API returned status {$guzzleResponse->getStatusCode()}: {$responseBody}");
            }
            
            return $responseBody;
        } catch (GuzzleException $e) {
            throw new \Exception('AWS SES request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Build canonical request for Signature Version 4
     */
    private function buildCanonicalRequest($method, $path, $params, $timestamp, $datestamp) {
        $canonicalQuerystring = '';
        $canonicalHeaders = "host:" . parse_url($this->endpoint, PHP_URL_HOST) . "\n"
                          . "x-amz-date:" . $timestamp . "\n";
        $signedHeaders = "host;x-amz-date";
        
        $payload = http_build_query($params);
        $payloadHash = hash('sha256', $payload);
        
        $canonicalRequest = "{$method}\n{$path}\n{$canonicalQuerystring}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        return $canonicalRequest;
    }
    
    /**
     * Calculate AWS Signature Version 4
     */
    private function calculateSignature($canonicalRequest, $datestamp) {
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$datestamp}/{$this->region}/email/aws4_request";
        $stringToSign = "{$algorithm}\n" . gmdate('Ymd\THis\Z') . "\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        $kSecret = 'AWS4' . $this->credentials['secret_key'];
        $kDate = hash_hmac('sha256', $datestamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 'email', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
    
    /**
     * Build Authorization header
     */
    private function buildAuthorizationHeader($signature, $datestamp, $timestamp) {
        $credentialScope = "{$datestamp}/{$this->region}/email/aws4_request";
        $signedHeaders = "host;x-amz-date";
        
        return "AWS4-HMAC-SHA256 Credential={$this->credentials['access_key']}/{$credentialScope}, "
             . "SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
    
    /**
     * Build multi-address parameters for email recipients
     */
    private function buildMultiAddressParams($addresses) {
        $params = [];
        foreach ($addresses as $index => $address) {
            $params["Destination.ToAddresses.member." . ($index + 1)] = $address;
        }
        return $params;
    }
    
    /**
     * Extract value from XML response
     */
    private function extractXmlValue($xml, $tag) {
        $pattern = "/<{$tag}>(.*?)<\/{$tag}>/s";
        if (preg_match($pattern, $xml, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    /**
     * Get test email template
     */
    private function getTestEmailTemplate($type = 'order') {
        $timestamp = date('Y-m-d H:i:s');
        
        return "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2>AWS SES Test Email</h2>
                <p><strong>Type:</strong> {$type}</p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
                <p><strong>From:</strong> {$this->credentials['from_name']} &lt;{$this->credentials['from_email']}&gt;</p>
                <hr>
                <p>This is a test email from the 3DCart to NetSuite integration system.</p>
                <p>If you received this email, AWS SES is properly configured and working.</p>
            </body>
        </html>";
    }
    
    /**
     * Get sync report template
     */
    private function getSyncReportTemplate($data = []) {
        $timestamp = date('Y-m-d H:i:s');
        $ordersProcessed = $data['orders_processed'] ?? 0;
        $syncStatus = $data['sync_status'] ?? 'Completed';
        
        return "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Daily Sync Report</h2>
                <p><strong>Report Date:</strong> {$timestamp}</p>
                <p><strong>Sync Status:</strong> {$syncStatus}</p>
                <p><strong>Orders Processed:</strong> {$ordersProcessed}</p>
                <hr>
                <p>This is an automated sync report from the 3DCart to NetSuite integration system.</p>
            </body>
        </html>";
    }
}
