<?php

namespace Laguna\Integration\Services;

use SendGrid\Mail\Mail;
use SendGrid\Mail\To;
use Laguna\Integration\Utils\Logger;

/**
 * Email Service using SendGrid
 * 
 * Handles all email notifications for the integration system.
 * Documentation: https://www.twilio.com/docs/sendgrid/api-reference
 */
class EmailService {
    protected $sendgrid;
    protected $credentials;
    protected $config;
    protected $logger;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        // Support both old and new configuration structures
        if (isset($credentials['email']['sendgrid'])) {
            $this->credentials = $credentials['email']['sendgrid'];
        } else {
            // Fallback to old structure for backward compatibility
            $this->credentials = $credentials['sendgrid'] ?? [];
        }
        
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        
        // Configure SendGrid with SSL verification disabled for development environments
        // In production, you should set verify_ssl to true and ensure proper SSL certificates
        $options = [
            'verify_ssl' => false,  // Disable SSL verification for local development
            'curl' => [
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]
        ];
        
        $this->sendgrid = new \SendGrid($this->credentials['api_key'], $options);
    }
    
    /**
     * Test SendGrid connection
     */
    public function testConnection() {
        try {
            // Test by attempting to get API key info
            $response = $this->sendgrid->client->api_keys()->get();
            
            $this->logger->info('SendGrid connection test successful', [
                'status_code' => $response->statusCode()
            ]);
            
            return [
                'success' => true,
                'status_code' => $response->statusCode(),
                'service' => 'SendGrid'
            ];
        } catch (\Exception $e) {
            $this->logger->error('SendGrid connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'SendGrid'
            ];
        }
    }
    
    /**
     * Check SendGrid account status and quota
     */
    public function checkAccountStatus() {
        try {
            // Get account stats to check quota usage
            $response = $this->sendgrid->client->stats()->get();
            
            $statusCode = $response->statusCode();
            $responseBody = $response->body();
            
            if ($statusCode == 200) {
                $stats = json_decode($responseBody, true);
                
                $this->logger->info('SendGrid account status check successful', [
                    'status_code' => $statusCode,
                    'stats_available' => !empty($stats)
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'stats' => $stats,
                    'quota_exceeded' => false
                ];
            } else {
                // Check if it's a quota issue
                $responseData = json_decode($responseBody, true);
                $isQuotaExceeded = ($statusCode == 401 && 
                    isset($responseData['errors']) && 
                    strpos(json_encode($responseData['errors']), 'Maximum credits exceeded') !== false);
                
                $this->logger->warning('SendGrid account status check failed', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'quota_exceeded' => $isQuotaExceeded
                ]);
                
                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'quota_exceeded' => $isQuotaExceeded,
                    'error' => $responseBody
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('SendGrid account status check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'quota_exceeded' => false
            ];
        }
    }
    
    /**
     * Send generic email
     */
    public function sendEmail($subject, $content, $recipients, $isTest = false) {
        try {
            $email = new Mail();
            
            // Set from address
            $email->setFrom(
                $this->credentials['from_email'],
                $this->credentials['from_name']
            );
            
            // Set subject
            $email->setSubject($subject);
            
            // Add recipients
            foreach ($recipients as $recipient) {
                $email->addTo(new To(trim($recipient)));
            }
            
            // Set content
            $email->addContent("text/html", $content);
            
            // Send email
            $response = $this->sendgrid->send($email);
            
            $statusCode = $response->statusCode();
            $responseBody = $response->body();
            $responseHeaders = $response->headers();
            
            // SendGrid returns 202 for accepted emails
            $isSuccess = in_array($statusCode, [200, 202]);
            
            if ($isSuccess) {
                $this->logger->info('Email sent successfully', [
                    'subject' => $subject,
                    'recipients' => $recipients,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'is_test' => $isTest
                ]);
            } else {
                // Parse error message for better logging
                $errorMessage = 'Unknown error';
                if (!empty($responseBody)) {
                    $responseData = json_decode($responseBody, true);
                    if (isset($responseData['errors']) && is_array($responseData['errors'])) {
                        $errors = array_map(function($error) {
                            return $error['message'] ?? 'Unknown error';
                        }, $responseData['errors']);
                        $errorMessage = implode(', ', $errors);
                    }
                }
                
                $isQuotaExceeded = ($statusCode == 401 && strpos($errorMessage, 'Maximum credits exceeded') !== false);
                
                $logData = [
                    'subject' => $subject,
                    'recipients' => $recipients,
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage,
                    'response_body' => $responseBody,
                    'is_test' => $isTest,
                    'quota_exceeded' => $isQuotaExceeded
                ];
                
                if ($isQuotaExceeded) {
                    $this->logger->warning('Email sending failed - quota exceeded', $logData);
                } else {
                    $this->logger->error('Email sending failed', $logData);
                }
            }
            
            return [
                'success' => $isSuccess,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'message' => $isSuccess ? 'Email sent successfully' : 'Email sending failed'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email - exception thrown', [
                'subject' => $subject,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'is_test' => $isTest
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Email sending failed: ' . $e->getMessage()
            ];
        }
    }
}
