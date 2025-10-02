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
}