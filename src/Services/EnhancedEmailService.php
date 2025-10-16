<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Services\EmailService;
use Laguna\Integration\Services\NotificationSettingsService;
use Laguna\Integration\Utils\Logger;

/**
 * Enhanced Email Service with Notification Settings Support
 * 
 * Extends the base EmailService to support different recipient lists
 * based on notification type and sync method (webhook vs manual)
 */
class EnhancedEmailService extends EmailService {
    private $notificationSettings;
    private $isWebhookContext;
    
    public function __construct($isWebhookContext = false) {
        parent::__construct();
        $this->notificationSettings = new NotificationSettingsService();
        $this->isWebhookContext = $isWebhookContext;
    }
    
    /**
     * Set the context (webhook or manual)
     */
    public function setWebhookContext($isWebhook) {
        $this->isWebhookContext = $isWebhook;
    }
    
    /**
     * Send 3DCart order notification with appropriate recipients
     */
    public function send3DCartOrderNotification($orderId, $status, $details = [], $isSuccess = true) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping 3DCart order notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        // Determine notification type based on context and status
        $notificationType = $this->get3DCartNotificationType($isSuccess);
        $recipients = $this->notificationSettings->getRecipients($notificationType);
        
        $statusText = $isSuccess ? 'Successfully Processed' : 'Processing Failed';
        $subject = $this->config['subject_prefix'] . "3DCart Order {$orderId} - {$statusText}";
        $content = $this->build3DCartOrderNotificationContent($orderId, $statusText, $details, $isSuccess);
        
        $this->logger->info('Sending 3DCart order notification', [
            'order_id' => $orderId,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients),
            'is_webhook' => $this->isWebhookContext,
            'is_success' => $isSuccess
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
        
        // Determine notification type based on context and status
        $notificationType = $this->getHubSpotNotificationType($isSuccess);
        $recipients = $this->notificationSettings->getRecipients($notificationType);
        
        $statusText = $isSuccess ? 'Successfully Synced' : 'Sync Failed';
        $subject = $this->config['subject_prefix'] . "HubSpot Contact {$contactId} - {$statusText}";
        $content = $this->buildHubSpotSyncNotificationContent($contactId, $statusText, $details, $isSuccess);
        
        $this->logger->info('Sending HubSpot sync notification', [
            'contact_id' => $contactId,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients),
            'is_webhook' => $this->isWebhookContext,
            'is_success' => $isSuccess
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
        
        // Determine notification type based on status and context
        $isSuccess = stripos($status, 'success') !== false || stripos($status, 'processed') !== false;
        $notificationType = $this->get3DCartNotificationType($isSuccess);
        $recipients = $this->notificationSettings->getRecipients($notificationType);
        
        $subject = $this->config['subject_prefix'] . "Order {$orderId} - {$status}";
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
        
        // Use failed webhook notification type for most failures
        $notificationType = NotificationSettingsService::TYPE_3DCART_FAILED_WEBHOOK;
        $recipients = $this->notificationSettings->getRecipients($notificationType);
        
        $subject = $this->config['subject_prefix'] . "Order Processing Failed: " . ucfirst(str_replace('_', ' ', $failureType));
        $content = $this->buildFailedOrderNotificationContent($failureType, $details);
        
        $this->logger->info('Sending failed order notification', [
            'failure_type' => $failureType,
            'notification_type' => $notificationType,
            'recipient_count' => count($recipients)
        ]);
        
        return $this->sendEmail($subject, $content, $recipients);
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
        $recipients = $this->notificationSettings->getRecipients($notificationType);
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