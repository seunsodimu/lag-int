<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;

/**
 * AWS SES Email Service updated by lag-int
 * 
 * Docker-optimized implementation using AWS SDK as primary method.
 * Handles email sending via Amazon Simple Email Service (SES).
 */
class SESEmailService {
    private $credentials;
    private $config;
    private $logger;
    private $sesClient;
    private $method = 'sdk';
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->credentials = $credentials['email']['ses'];
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        
        $this->initializeClient();
    }
    
    /**
     * Initialize AWS SES client
     * Uses AWS SDK for PHP when available (primary method)
     */
    private function initializeClient() {
        if (!class_exists('Aws\Ses\SesClient')) {
            $this->logger->warning('AWS SDK not available - SES email service will not work', [
                'hint' => 'Install aws/aws-sdk-php: composer require aws/aws-sdk-php'
            ]);
            return;
        }
        
        try {
            $region = $this->credentials['region'] ?? 'us-east-1';
            
            $this->sesClient = new \Aws\Ses\SesClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $this->credentials['access_key'],
                    'secret' => $this->credentials['secret_key'],
                ]
            ]);
            
            $this->method = 'sdk';
            $this->logger->info('AWS SES SDK client initialized', ['region' => $region]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize AWS SES SDK client', [
                'error' => $e->getMessage()
            ]);
            $this->sesClient = null;
        }
    }
    
    /**
     * Send email via AWS SES
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        if (!$this->sesClient) {
            return [
                'success' => false,
                'error' => 'AWS SES client not initialized. Ensure AWS credentials are properly configured.',
                'provider' => 'ses'
            ];
        }
        
        try {
            $toAddresses = array_map('trim', (array)$recipients);
            
            $this->logger->info('Sending email via AWS SES SDK', [
                'subject' => $subject,
                'recipients' => $toAddresses,
                'is_test' => $isTest
            ]);
            
            $result = $this->sesClient->sendEmail([
                'Source' => $this->credentials['from_email'],
                'Destination' => [
                    'ToAddresses' => $toAddresses,
                ],
                'Message' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8',
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $htmlContent,
                            'Charset' => 'UTF-8',
                        ],
                    ],
                ],
            ]);
            
            $messageId = $result['MessageId'] ?? 'unknown';
            
            $this->logger->info('Email sent successfully via AWS SES SDK', [
                'subject' => $subject,
                'message_id' => $messageId,
                'recipients' => $toAddresses
            ]);
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'status_code' => 200,
                'provider' => 'ses',
                'method' => 'sdk'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via AWS SES SDK', [
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'ses',
                'method' => 'sdk'
            ];
        }
    }
    
    /**
     * Test AWS SES connection
     */
    public function testConnection() {
        if (!$this->sesClient) {
            return [
                'success' => false,
                'error' => 'AWS SES SDK client not initialized',
                'service' => 'AWS SES',
                'hint' => 'Check AWS credentials configuration'
            ];
        }
        
        try {
            $result = $this->sesClient->getSendQuota();
            
            $this->logger->info('AWS SES connection test successful', [
                'max_24_hour_send' => $result['Max24HourSend'] ?? 0,
                'sent_last_24_hour' => $result['SentLast24Hour'] ?? 0
            ]);
            
            return [
                'success' => true,
                'status_code' => 200,
                'service' => 'AWS SES (SDK)',
                'region' => $this->credentials['region'] ?? 'us-east-1',
                'quota' => [
                    'max_24_hour_send' => $result['Max24HourSend'] ?? 0,
                    'sent_last_24_hour' => $result['SentLast24Hour'] ?? 0,
                    'max_send_rate' => $result['MaxSendRate'] ?? 0
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
