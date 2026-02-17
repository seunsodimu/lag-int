<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Services\EmailServiceFactory;
use Laguna\Integration\Services\NotificationSettingsService;
use Laguna\Integration\Utils\Logger;

/**
 * Enhanced Email Service with Notification Settings Support
 * 
 * Uses the configured email provider via EmailServiceFactory to support 
 * different recipient lists based on notification type and sync method.
 */
class EnhancedEmailService {
    private $notificationSettings;
    private $isWebhookContext;
    private $emailService;
    private $config;
    private $logger;
    
    public function __construct($isWebhookContext = false) {
        $config = require __DIR__ . '/../../config/config.php';
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        $this->emailService = EmailServiceFactory::create();
        
        try {
            $this->notificationSettings = new NotificationSettingsService();
        } catch (\Exception $e) {
            $this->logger->warning('NotificationSettingsService initialization failed, will use default recipients', [
                'error' => $e->getMessage()
            ]);
            $this->notificationSettings = null;
        }
        $this->isWebhookContext = $isWebhookContext;
    }
    
    /**
     * Delegate email sending to the underlying provider
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        return $this->emailService->sendEmail($subject, $htmlContent, $recipients, $isTest);
    }
    
    /**
     * Delegate test email sending to the underlying provider
     */
    public function sendTestEmail($toEmail, $testType = 'basic') {
        $subject = ($this->config['subject_prefix'] ?? '') . "Test Email - " . ucfirst($testType);
        $content = $this->buildTestEmailContent($toEmail, $testType);
        return $this->sendEmail($subject, $content, [$toEmail], true);
    }
    
    /**
     * Delegate connection testing to the underlying provider
     */
    public function testConnection() {
        return $this->emailService->testConnection();
    }
    
    /**
     * Get current provider information
     */
    public function getProviderInfo() {
        return EmailServiceFactory::getCurrentProvider();
    }
    
    /**
     * Check account status using the configured provider
     */
    public function checkAccountStatus() {
        if (method_exists($this->emailService, 'checkAccountStatus')) {
            $result = $this->emailService->checkAccountStatus();
            $provider = EmailServiceFactory::getCurrentProvider();
            $result['provider'] = $provider['name'];
            return $result;
        }
        
        $provider = EmailServiceFactory::getCurrentProvider();
        return [
            'success' => false,
            'error' => 'Account status check not supported by ' . $provider['name'],
            'provider' => $provider['name']
        ];
    }
    
    /**
     * Get all available providers and their status
     */
    public function getAllProvidersStatus() {
        return EmailServiceFactory::testAllProviders();
    }
    
    /**
     * Send employee lookup failure notification
     */
    public function sendEmployeeLookupFailureNotification($hubspotOwnerId, $ownerEmail, $error, $context = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping employee lookup failure notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $subject = ($this->config['subject_prefix'] ?? '') . "Employee Lookup Failed - Action Required";
        $content = $this->buildEmployeeLookupFailureContent($hubspotOwnerId, $ownerEmail, $error, $context);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }

    /**
     * Build employee lookup failure email content
     */
    private function buildEmployeeLookupFailureContent($hubspotOwnerId, $ownerEmail, $error, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { background-color: #f8d7da; padding: 20px; border-radius: 5px; margin-bottom: 20px; color: #721c24; }
                .alert { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .highlight { font-weight: bold; color: #007bff; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                th { background-color: #f2f2f2; width: 30%; }
                .action { background-color: #e2f0fe; padding: 15px; border-left: 4px solid #007bff; margin-top: 20px; }
                pre { background-color: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; white-space: pre-wrap; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>⚠️ HubSpot-NetSuite Integration Alert</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='alert'>
                <h3>Issue Summary</h3>
                <p>Failed to map HubSpot owner to NetSuite employee. Lead creation has been <strong>halted</strong> pending manual resolution.</p>
            </div>
            
            <div class='details'>
                <h3>Lookup Details</h3>
                <table>
                    <tr><th>HubSpot Owner ID</th><td class='highlight'>" . htmlspecialchars($hubspotOwnerId) . "</td></tr>
                    <tr><th>Owner Email</th><td class='highlight'>" . htmlspecialchars($ownerEmail) . "</td></tr>
                    <tr><th>Error</th><td>" . htmlspecialchars($error) . "</td></tr>
                </table>
            </div>";
        
        if (!empty($context)) {
            $html .= "<div class='details'>
                <h3>Additional Context</h3>
                <table>";
            
            foreach ($context as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td><pre>" . htmlspecialchars($displayValue) . "</pre></td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        $html .= "
            <div class='action'>
                <h3>🔧 Required Actions</h3>
                <ol>
                    <li><strong>Verify NetSuite Employee:</strong> Check if an employee exists with email <code>" . htmlspecialchars($ownerEmail) . "</code></li>
                    <li><strong>Create Employee if Missing:</strong> If the employee doesn't exist, create them in NetSuite</li>
                    <li><strong>Check Permissions:</strong> Ensure the integration has permission to access employee records</li>
                    <li><strong>Retry Processing:</strong> Once resolved, the webhook can be reprocessed</li>
                </ol>
                
                <p><strong>Impact:</strong> Lead creation is currently blocked for this HubSpot contact until the employee mapping is resolved.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Send connection status alert
     */
    public function sendConnectionAlert($service, $status, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping connection alert');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $statusText = $status ? 'Connected' : 'Connection Failed';
        $subject = ($this->config['subject_prefix'] ?? '') . "{$service} - {$statusText}";
        $content = $this->buildConnectionAlertContent($service, $status, $details);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }

    /**
     * Build connection alert email content
     */
    private function buildConnectionAlertContent($service, $status, $details = []) {
        $timestamp = date('Y-m-d H:i:s');
        $statusText = $status ? 'Connected' : 'Connection Failed';
        $statusColor = $status ? '#28a745' : '#dc3545';
        $bgColor = $status ? '#d4edda' : '#f8d7da';
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { background-color: {$bgColor}; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .status { color: {$statusColor}; font-weight: bold; font-size: 18px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🔗 Connection Status Alert</h2>
                <p><strong>Service:</strong> {$service}</p>
                <p><strong>Status:</strong> <span class='status'>{$statusText}</span></p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>";
        
        if (!empty($details)) {
            $html .= "
            <div class='details'>
                <h3>Details</h3>
                <table>";
            foreach ($details as $key => $value) {
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            $html .= "</table>
            </div>";
        }
        
        $html .= "
        </body>
        </html>";
        
        return $html;
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
     * Set the context (webhook or manual)
     */
    public function setWebhookContext($isWebhook) {
        $this->isWebhookContext = $isWebhook;
    }
    
    /**
     * Get recipients for a specific notification type
     */
    private function getRecipientsForType($notificationType) {
        if ($this->notificationSettings !== null) {
            $recipients = $this->notificationSettings->getRecipients($notificationType);
        } else {
            $recipients = $this->config['to_emails'] ?? ['web_dev@lagunatools.com'];
        }
        
        if (empty($recipients)) {
            $recipients = ['web_dev@lagunatools.com'];
        }
        
        return $recipients;
    }
    
    /**
     * Send 3DCart order notification with appropriate recipients
     */
    public function send3DCartOrderNotification($orderId, $status, $details = [], $isSuccess = true) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping 3DCart order notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $notificationType = $this->get3DCartNotificationType($isSuccess);
        $recipients = $this->getRecipientsForType($notificationType);
        
        $statusText = $isSuccess ? 'Successfully Processed' : 'Processing Failed';
        $subject = ($this->config['subject_prefix'] ?? '') . "3DCart Order {$orderId} - {$statusText}";
        $content = $this->build3DCartOrderNotificationContent($orderId, $statusText, $details, $isSuccess);
        
        $this->logger->info('Sending 3DCart order notification', [
            'order_id' => $orderId,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients)
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Send HubSpot sync notification with appropriate recipients
     */
    public function sendHubSpotSyncNotification($contactId, $status, $details = [], $isSuccess = true) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping HubSpot sync notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $notificationType = $this->getHubSpotNotificationType($isSuccess);
        $recipients = $this->getRecipientsForType($notificationType);
        
        $statusText = $isSuccess ? 'Successfully Synced' : 'Sync Failed';
        $subject = ($this->config['subject_prefix'] ?? '') . "HubSpot Contact {$contactId} - {$statusText}";
        $content = $this->buildHubSpotSyncNotificationContent($contactId, $statusText, $details, $isSuccess);
        
        $this->logger->info('Sending HubSpot sync notification', [
            'contact_id' => $contactId,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients)
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Send daily summary notification (always to all recipients)
     */
    public function sendDailySummary($summary) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping daily summary');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        // Daily summaries go to all recipients (use default config)
        $subject = $this->config['subject_prefix'] . "Daily Integration Summary - " . date('Y-m-d');
        $content = $this->buildDailySummaryContent($summary);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send error notification (always to all recipients for critical errors)
     */
    public function sendErrorNotification($error, $context = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping error notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        // Critical errors go to all recipients
        $subject = $this->config['subject_prefix'] . "Critical Integration Error";
        $content = $this->buildErrorNotificationContent($error, $context);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send order notification with appropriate recipients based on context
     */
    public function sendOrderNotification($orderId, $status, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping order notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $isSuccess = stripos($status, 'success') !== false || stripos($status, 'processed') !== false;
        $notificationType = $this->get3DCartNotificationType($isSuccess);
        $recipients = $this->getRecipientsForType($notificationType);
        
        $subject = ($this->config['subject_prefix'] ?? '') . "Order {$orderId} - {$status}";
        $content = $this->buildOrderNotificationContent($orderId, $status, $details);
        
        $this->logger->info('Sending order notification', [
            'order_id' => $orderId,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients),
            'status' => $status
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Send failed order notification with appropriate recipients
     */
    public function sendFailedOrderNotification($failureType, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping failed order notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $notificationType = NotificationSettingsService::TYPE_3DCART_FAILED_WEBHOOK;
        $recipients = $this->getRecipientsForType($notificationType);
        
        $subject = ($this->config['subject_prefix'] ?? '') . "Order Processing Failed: " . ucfirst(str_replace('_', ' ', $failureType));
        $content = $this->buildFailedOrderNotificationContent($failureType, $details);
        
        $this->logger->info('Sending failed order notification', [
            'failure_type' => $failureType,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients)
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Send inventory sync notification with appropriate recipients
     */
    public function sendInventorySyncNotification($syncResult) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping inventory sync notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $isSuccess = ($syncResult['success'] ?? false) && ($syncResult['error_count'] ?? 0) == 0;
        $notificationType = $isSuccess 
            ? NotificationSettingsService::TYPE_INVENTORY_SYNC_SUCCESS 
            : NotificationSettingsService::TYPE_INVENTORY_SYNC_FAILED;
        $recipients = $this->getRecipientsForType($notificationType);
        
        $subject = ($this->config['subject_prefix'] ?? '[3DCart Integration] ') . "Inventory Sync " . ($isSuccess ? 'SUCCESS' : 'FAILED') . " - " . date('Y-m-d H:i:s');
        $content = $this->buildInventorySyncContent($syncResult, $isSuccess);
        
        $this->logger->info('Sending inventory sync notification', [
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients),
            'is_success' => $isSuccess
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }

    /**
     * Send password reset email to a specific user
     */
    public function sendPasswordResetEmail($userEmail, $username, $resetUrl, $expiresAt) {
        $subject = ($this->config['subject_prefix'] ?? '[3DCart Integration] ') . "Password Reset Request";
        $content = $this->buildPasswordResetContent($username, $resetUrl, $expiresAt);
        
        $this->logger->info('Sending password reset email', [
            'user' => $username,
            'email' => $userEmail
        ]);
        
        return $this->sendEmail($subject, $content, [$userEmail]);
    }

    /**
     * Generic notification router based on notification type
     * Handles all transaction notification types and routes to appropriate methods
     */
    public function sendNotification($notificationType, $subject, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping notification', [
                'notification_type' => $notificationType
            ]);
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        $this->logger->info('Routing notification', [
            'notification_type' => $notificationType,
            'subject' => $subject
        ]);
        
        // Route to appropriate handler based on notification type
        switch ($notificationType) {
            case '3dcart_success_webhook':
            case '3dcart_success_manual':
                return $this->sendNotificationByType($notificationType, $subject, $details, true);
                
            case '3dcart_failed_webhook':
            case '3dcart_failed_manual':
                return $this->sendNotificationByType($notificationType, $subject, $details, false);
                
            case 'store_customer_not_found':
                return $this->sendNotificationByType($notificationType, $subject, $details, false);
                
            case 'hubspot_success_webhook':
            case 'hubspot_success_manual':
                return $this->sendNotificationByType($notificationType, $subject, $details, true);
                
            case 'hubspot_failed_webhook':
            case 'hubspot_failed_manual':
                return $this->sendNotificationByType($notificationType, $subject, $details, false);
                
            default:
                return $this->sendDefaultNotification($notificationType, $subject, $details);
        }
    }
    
    /**
     * Send notification with appropriate recipients based on type
     */
    private function sendNotificationByType($notificationType, $subject, $details = [], $isSuccess = true) {
        $recipients = $this->getRecipientsForType($notificationType);
        $content = $this->buildGenericNotificationContent($subject, $details, $isSuccess, $notificationType);
        
        $this->logger->info('Sending notification by type', [
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients),
            'is_success' => $isSuccess
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Send default notification (for unmapped types)
     */
    private function sendDefaultNotification($notificationType, $subject, $details = []) {
        $recipients = $this->config['to_emails']; // Use default recipients
        $content = $this->buildGenericNotificationContent($subject, $details, false, $notificationType);
        
        $this->logger->info('Sending default notification', [
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients)
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
    }
    
    /**
     * Build generic notification content
     */
    private function buildGenericNotificationContent($subject, $details, $isSuccess = true, $notificationType = '') {
        $timestamp = date('Y-m-d H:i:s');
        $statusClass = $isSuccess ? 'success' : 'error';
        $statusIcon = $isSuccess ? '✅' : '❌';
        
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<div class="details"><h4>Details:</h4><ul>';
            foreach ($details as $key => $value) {
                // Handle multi-line values (like stack traces)
                $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                $displayValue = nl2br(htmlspecialchars($displayValue));
                $detailsHtml .= "<li><strong>{$key}:</strong> {$displayValue}</li>";
            }
            $detailsHtml .= '</ul></div>';
        }
        
        $typeText = ucfirst(str_replace('_', ' ', $notificationType));
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .success { background-color: #d4edda; color: #155724; }
                .error { background-color: #f8d7da; color: #721c24; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; overflow-x: auto; }
                .context { background-color: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
                ul { margin: 10px 0; }
                li { margin: 8px 0; word-break: break-word; }
                pre { background-color: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
                .timestamp { color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='header {$statusClass}'>
                <h2>{$statusIcon} {$subject}</h2>
                <p class='timestamp'><strong>Timestamp:</strong> {$timestamp}</p>
                <p><strong>Notification Type:</strong> {$typeText}</p>
            </div>
            
            <div class='content'>
                <p>An integration event has been processed. Please review the details below.</p>
            </div>
            
            {$detailsHtml}
            
            <div class='content'>
                <p><em>This is an automated notification from the 3DCart-NetSuite integration system.</em></p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get 3DCart notification type based on context and success status
     */
    private function get3DCartNotificationType($isSuccess) {
        if ($this->isWebhookContext) {
            return $isSuccess ? 
                NotificationSettingsService::TYPE_3DCART_SUCCESS_WEBHOOK : 
                NotificationSettingsService::TYPE_3DCART_FAILED_WEBHOOK;
        } else {
            return $isSuccess ? 
                NotificationSettingsService::TYPE_3DCART_SUCCESS_MANUAL : 
                NotificationSettingsService::TYPE_3DCART_FAILED_MANUAL;
        }
    }
    
    /**
     * Get HubSpot notification type based on context and success status
     */
    private function getHubSpotNotificationType($isSuccess) {
        if ($this->isWebhookContext) {
            return $isSuccess ? 
                NotificationSettingsService::TYPE_HUBSPOT_SUCCESS_WEBHOOK : 
                NotificationSettingsService::TYPE_HUBSPOT_FAILED_WEBHOOK;
        } else {
            return $isSuccess ? 
                NotificationSettingsService::TYPE_HUBSPOT_SUCCESS_MANUAL : 
                NotificationSettingsService::TYPE_HUBSPOT_FAILED_MANUAL;
        }
    }
    
    /**
     * Build 3DCart order notification content
     */
    private function build3DCartOrderNotificationContent($orderId, $status, $details, $isSuccess) {
        $timestamp = date('Y-m-d H:i:s');
        $statusClass = $isSuccess ? 'success' : 'error';
        $statusIcon = $isSuccess ? '✅' : '❌';
        $contextText = $this->isWebhookContext ? 'Webhook' : 'Manual';
        
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<div class="details"><h4>Order Details:</h4><ul>';
            foreach ($details as $key => $value) {
                $detailsHtml .= "<li><strong>{$key}:</strong> {$value}</li>";
            }
            $detailsHtml .= '</ul></div>';
        }
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .success { background-color: #d4edda; color: #155724; }
                .error { background-color: #f8d7da; color: #721c24; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .context { background-color: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
                ul { margin: 10px 0; }
                li { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class='header {$statusClass}'>
                <h2>{$statusIcon} 3DCart Order {$status}</h2>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='context'>
                <p><strong>Processing Method:</strong> {$contextText}</p>
            </div>
            
            <div class='content'>
                <h3>Order Processing " . ($isSuccess ? 'Completed Successfully' : 'Failed') . "</h3>
                <p>The 3DCart order has been " . ($isSuccess ? 'successfully processed and synced to NetSuite.' : 'failed to process. Please review the details below.') . "</p>
            </div>
            
            {$detailsHtml}
            
            <div class='content'>
                <p><em>This notification was sent to recipients configured for 3DCart " . ($isSuccess ? 'success' : 'failure') . " notifications via {$contextText} processing.</em></p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Build HubSpot sync notification content
     */
    private function buildHubSpotSyncNotificationContent($contactId, $status, $details, $isSuccess) {
        $timestamp = date('Y-m-d H:i:s');
        $statusClass = $isSuccess ? 'success' : 'error';
        $statusIcon = $isSuccess ? '✅' : '❌';
        $contextText = $this->isWebhookContext ? 'Webhook' : 'Manual';
        
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<div class="details"><h4>Contact Details:</h4><ul>';
            foreach ($details as $key => $value) {
                $detailsHtml .= "<li><strong>{$key}:</strong> {$value}</li>";
            }
            $detailsHtml .= '</ul></div>';
        }
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .success { background-color: #d4edda; color: #155724; }
                .error { background-color: #f8d7da; color: #721c24; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .context { background-color: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
                ul { margin: 10px 0; }
                li { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class='header {$statusClass}'>
                <h2>{$statusIcon} HubSpot Contact {$status}</h2>
                <p><strong>Contact ID:</strong> {$contactId}</p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='context'>
                <p><strong>Processing Method:</strong> {$contextText}</p>
            </div>
            
            <div class='content'>
                <h3>Contact Sync " . ($isSuccess ? 'Completed Successfully' : 'Failed') . "</h3>
                <p>The HubSpot contact has been " . ($isSuccess ? 'successfully synced to NetSuite.' : 'failed to sync. Please review the details below.') . "</p>
            </div>
            
            {$detailsHtml}
            
            <div class='content'>
                <p><em>This notification was sent to recipients configured for HubSpot " . ($isSuccess ? 'success' : 'failure') . " notifications via {$contextText} processing.</em></p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Build order notification email content
     */
    private function buildOrderNotificationContent($orderId, $status, $details) {
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                .status-success { color: #28a745; font-weight: bold; }
                .status-error { color: #dc3545; font-weight: bold; }
                .status-warning { color: #ffc107; font-weight: bold; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>3DCart to NetSuite Integration - Order Update</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='content'>
                <h3>Order Information</h3>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Status:</strong> <span class='status-" . $this->getStatusClass($status) . "'>{$status}</span></p>
            </div>";
        
        if (!empty($details)) {
            $html .= "<div class='details'>
                <h3>Details</h3>
                <table>";
            
            foreach ($details as $key => $value) {
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        $html .= "
            <div class='content'>
                <p><em>This is an automated notification from the 3DCart to NetSuite integration system.</em></p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build error notification email content
     */
    private function buildErrorNotificationContent($error, $context) {
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8d7da; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
                .error { color: #721c24; background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .context { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🚨 Integration Error Alert</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='error'>
                <h3>Error Details</h3>
                <p><strong>Error:</strong> " . htmlspecialchars($error) . "</p>
            </div>";
        
        if (!empty($context)) {
            $html .= "<div class='context'>
                <h3>Context Information</h3>
                <table>";
            
            foreach ($context as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td><pre>" . htmlspecialchars($displayValue) . "</pre></td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        $html .= "
            <div style='margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 5px;'>
                <p><strong>Action Required:</strong> Please review the error and take appropriate action to resolve the issue.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Build daily summary email content
     */
    private function buildDailySummaryContent($summary) {
        $date = date('Y-m-d');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .summary-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .metric { display: inline-block; margin: 10px 20px 10px 0; }
                .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
                .metric-label { font-size: 14px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>📊 Daily Integration Summary - {$date}</h2>
            </div>
            
            <div class='summary-box'>
                <h3>Order Processing Statistics</h3>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_processed'] ?? 0) . "</div>
                    <div class='metric-label'>Orders Processed</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_successful'] ?? 0) . "</div>
                    <div class='metric-label'>Successful</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_failed'] ?? 0) . "</div>
                    <div class='metric-label'>Failed</div>
                </div>
            </div>
            
            <div class='summary-box'>
                <h3>Customer Management</h3>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['customers_created'] ?? 0) . "</div>
                    <div class='metric-label'>New Customers Created</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['customers_existing'] ?? 0) . "</div>
                    <div class='metric-label'>Existing Customers</div>
                </div>
            </div>";
        
        if (isset($summary['errors']) && !empty($summary['errors'])) {
            $html .= "<div class='summary-box' style='background-color: #f8d7da;'>
                <h3>Errors Encountered</h3>
                <ul>";
            
            foreach ($summary['errors'] as $error) {
                $html .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            
            $html .= "</ul></div>";
        }
        
        $html .= "
            <div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
                <p>This summary covers the period from 00:00 to 23:59 on {$date}.</p>
                <p>Generated automatically by the 3DCart to NetSuite integration system.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Get CSS class for status
     */
    private function getStatusClass($status) {
        $status = strtolower($status);
        
        if (in_array($status, ['success', 'completed', 'processed'])) {
            return 'success';
        } elseif (in_array($status, ['error', 'failed', 'rejected'])) {
            return 'error';
        } else {
            return 'warning';
        }
    }

    /**
     * Build test email content
     */
    private function buildTestEmailContent($toEmail, $testType = 'basic') {
        $timestamp = date('Y-m-d H:i:s');
        $provider = get_class($this->emailService);
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                .header { background-color: #e9ecef; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { margin-bottom: 20px; line-height: 1.5; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
                table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
                th { background-color: #f1f3f5; width: 30%; }
                .footer { margin-top: 30px; font-size: 12px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🧪 Integration Test Email</h2>
                <p>This is a test email from the 3DCart to NetSuite integration system.</p>
            </div>
            
            <div class='content'>
                <p>If you are seeing this email, the email transport provider is correctly configured and able to send messages.</p>
            </div>
            
            <div class='details'>
                <h3>Test Details</h3>
                <table>
                    <tr><th>Test Type</th><td>" . ucfirst($testType) . "</td></tr>
                    <tr><th>Recipient</th><td>{$toEmail}</td></tr>
                    <tr><th>Timestamp</th><td>{$timestamp}</td></tr>
                    <tr><th>Provider</th><td>{$provider}</td></tr>
                </table>
            </div>
            
            <div class='footer'>
                <p>This is an automated test notification. No further action is required.</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Build inventory sync notification content
     */
    private function buildInventorySyncContent($syncResult, $isSuccess) {
        $statusColor = $isSuccess ? '#28a745' : '#dc3545';
        $statusLabel = $isSuccess ? 'SUCCESS' : 'FAILED';
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; margin: 20px; }
                .header { background: {$statusColor}; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .summary { background: #f8f9fa; padding: 15px; border-left: 4px solid {$statusColor}; margin: 20px 0; }
                .stats { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
                .stat-box { background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd; border-left: 4px solid #007bff; min-width: 120px; text-align: center; }
                .stat-value { font-size: 24px; font-weight: bold; color: #333; }
                .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
                th { background-color: #f1f3f5; font-weight: bold; }
                .error-text { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🔄 Inventory Sync {$statusLabel}</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='summary'>
                <h3>Sync Summary</h3>
                <p>The inventory synchronization process has completed with the following results.</p>
            </div>
            
            <div class='stats'>
                <div class='stat-box'>
                    <div class='stat-value'>" . ($syncResult['total_count'] ?? 0) . "</div>
                    <div class='stat-label'>Total Items</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-value' style='color: #28a745;'>" . ($syncResult['synced_count'] ?? 0) . "</div>
                    <div class='stat-label'>Synced</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-value' style='color: #dc3545;'>" . ($syncResult['error_count'] ?? 0) . "</div>
                    <div class='stat-label'>Errors</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-value' style='color: #ffc107;'>" . ($syncResult['skipped_count'] ?? 0) . "</div>
                    <div class='stat-label'>Skipped</div>
                </div>
            </div>";
        
        if (!empty($syncResult['errors'])) {
            $html .= "
            <h3>Error Details</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product ID / SKU</th>
                        <th>Error Message</th>
                    </tr>
                </thead>
                <tbody>";
            
            foreach ($syncResult['errors'] as $error) {
                $html .= "
                    <tr>
                        <td><strong>" . htmlspecialchars($error['id'] ?? 'Unknown') . "</strong></td>
                        <td class='error-text'>" . htmlspecialchars($error['message'] ?? 'Unknown error') . "</td>
                    </tr>";
            }
            
            $html .= "
                </tbody>
            </table>";
        }
        
        $html .= "
            <div style='margin-top: 30px; font-size: 12px; color: #6c757d; border-top: 1px solid #eee; padding-top: 10px;'>
                <p>This is an automated notification from the 3DCart-NetSuite integration system.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }

    /**
     * Build password reset email content
     */
    private function buildPasswordResetContent($username, $resetUrl, $expiresAt) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 20px; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
                .header { background-color: #4a6cf7; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .button-container { text-align: center; margin: 30px 0; }
                .button { background-color: #4a6cf7; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .footer { background-color: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .url-box { background-color: #f1f1f1; padding: 10px; border-radius: 5px; word-break: break-all; font-family: monospace; font-size: 11px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    <p>We received a request to reset the password for your account in the 3DCart Integration System. If you didn't make this request, you can safely ignore this email.</p>
                    
                    <div class='button-container'>
                        <a href='{$resetUrl}' class='button'>Reset Password</a>
                    </div>
                    
                    <p>This password reset link will expire at: <strong>" . htmlspecialchars($expiresAt) . "</strong></p>
                    
                    <p>If the button above doesn't work, copy and paste the following URL into your browser:</p>
                    <div class='url-box'>{$resetUrl}</div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Build failed order notification content
     */
    private function buildFailedOrderNotificationContent($failureType, $details) {
        $timestamp = date('Y-m-d H:i:s');
        $typeLabel = ucfirst(str_replace('_', ' ', $failureType));
        
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<div class="details"><h4>Failure Details:</h4><ul>';
            foreach ($details as $key => $value) {
                // Handle special formatting for certain fields
                if ($key === 'order_link') {
                    $detailsHtml .= "<li><strong>{$key}:</strong> <a href='{$value}'>{$value}</a></li>";
                } elseif ($key === 'action_required') {
                    $detailsHtml .= "<li><strong>{$key}:</strong> <span style='color: #d9534f;'>{$value}</span></li>";
                } else {
                    $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                    $displayValue = nl2br(htmlspecialchars($displayValue));
                    $detailsHtml .= "<li><strong>{$key}:</strong> {$displayValue}</li>";
                }
            }
            $detailsHtml .= '</ul></div>';
        }
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { padding: 20px; border-radius: 5px; margin-bottom: 20px; background-color: #f8d7da; color: #721c24; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; overflow-x: auto; }
                .action { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 15px; }
                ul { margin: 10px 0; }
                li { margin: 8px 0; word-break: break-word; }
                a { color: #0066cc; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .timestamp { color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>❌ Order Processing Failed: {$typeLabel}</h2>
                <p class='timestamp'><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='content'>
                <p>An order processing operation has failed. Please review the details below and take appropriate action.</p>
            </div>
            
            {$detailsHtml}
            
            <div class='action'>
                <h3>⚠️ Action Required</h3>
                <p>Please review the failure details above and take corrective action. If this is a recurring issue, please contact support.</p>
            </div>
            
            <div class='content'>
                <p><em>This is an automated notification from the 3DCart-NetSuite integration system.</em></p>
            </div>
        </body>
        </html>";
    }
}