<?php

namespace Laguna\Integration\Services;

use PDO;
use Laguna\Integration\Utils\Logger;

/**
 * Notification Settings Service
 * 
 * Manages email notification recipients by notification type
 */
class NotificationSettingsService {
    private $pdo;
    private $logger;
    
    // Notification types
    const TYPE_3DCART_SUCCESS_WEBHOOK = '3dcart_success_webhook';
    const TYPE_3DCART_FAILED_WEBHOOK = '3dcart_failed_webhook';
    const TYPE_HUBSPOT_SUCCESS_WEBHOOK = 'hubspot_success_webhook';
    const TYPE_HUBSPOT_FAILED_WEBHOOK = 'hubspot_failed_webhook';
    const TYPE_3DCART_SUCCESS_MANUAL = '3dcart_success_manual';
    const TYPE_3DCART_FAILED_MANUAL = '3dcart_failed_manual';
    const TYPE_HUBSPOT_SUCCESS_MANUAL = 'hubspot_success_manual';
    const TYPE_HUBSPOT_FAILED_MANUAL = 'hubspot_failed_manual';
    const TYPE_INVENTORY_SYNC_SUCCESS = 'inventory_sync_success';
    const TYPE_INVENTORY_SYNC_FAILED = 'inventory_sync_failed';
    
    // Default recipient (always included)
    const DEFAULT_RECIPIENT = 'web_dev@lagunatools.com';
    
    public function __construct() {
        $this->pdo = null; // Lazy initialization
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Lazy load database connection
     */
    private function getConnection() {
        if ($this->pdo === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $dbConfig = $config['database'];
            
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return $this->pdo;
    }
    
    /**
     * Get all notification types with descriptions
     */
    public function getNotificationTypes() {
        return [
            self::TYPE_3DCART_SUCCESS_WEBHOOK => [
                'label' => '3DCart Success (Webhook)',
                'description' => 'Successful order processing from 3DCart webhooks'
            ],
            self::TYPE_3DCART_FAILED_WEBHOOK => [
                'label' => '3DCart Failed (Webhook)', 
                'description' => 'Failed order processing from 3DCart webhooks'
            ],
            self::TYPE_HUBSPOT_SUCCESS_WEBHOOK => [
                'label' => 'HubSpot Success (Webhook)',
                'description' => 'Successful contact sync from HubSpot webhooks'
            ],
            self::TYPE_HUBSPOT_FAILED_WEBHOOK => [
                'label' => 'HubSpot Failed (Webhook)',
                'description' => 'Failed contact sync from HubSpot webhooks'
            ],
            self::TYPE_3DCART_SUCCESS_MANUAL => [
                'label' => '3DCart Success (Manual)',
                'description' => 'Successful manual order processing'
            ],
            self::TYPE_3DCART_FAILED_MANUAL => [
                'label' => '3DCart Failed (Manual)',
                'description' => 'Failed manual order processing'
            ],
            self::TYPE_HUBSPOT_SUCCESS_MANUAL => [
                'label' => 'HubSpot Success (Manual)',
                'description' => 'Successful manual contact sync'
            ],
            self::TYPE_HUBSPOT_FAILED_MANUAL => [
                'label' => 'HubSpot Failed (Manual)',
                'description' => 'Failed manual contact sync'
            ],
            self::TYPE_INVENTORY_SYNC_SUCCESS => [
                'label' => 'Inventory Sync Success',
                'description' => 'Successful inventory synchronization between 3DCart and NetSuite'
            ],
            self::TYPE_INVENTORY_SYNC_FAILED => [
                'label' => 'Inventory Sync Failed',
                'description' => 'Failed inventory synchronization between 3DCart and NetSuite'
            ]
        ];
    }
    
    /**
     * Get recipients for a specific notification type
     */
    public function getRecipients($notificationType) {
        try {
            $stmt = $this->getConnection()->prepare("
                SELECT recipient_email 
                FROM notification_settings 
                WHERE notification_type = ? AND is_active = 1
                ORDER BY recipient_email
            ");
            $stmt->execute([$notificationType]);
            
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Always include default recipient if not already in list
            if (!in_array(self::DEFAULT_RECIPIENT, $recipients)) {
                $recipients[] = self::DEFAULT_RECIPIENT;
            }
            
            $this->logger->info('Retrieved notification recipients', [
                'notification_type' => $notificationType,
                'recipient_count' => count($recipients)
            ]);
            
            return $recipients;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get notification recipients', [
                'notification_type' => $notificationType,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to default recipient
            return [self::DEFAULT_RECIPIENT];
        }
    }
    
    /**
     * Get all notification settings grouped by type
     */
    public function getAllSettings() {
        try {
            $stmt = $this->getConnection()->prepare("
                SELECT 
                    notification_type,
                    recipient_email,
                    is_active,
                    created_at,
                    updated_at
                FROM notification_settings 
                ORDER BY notification_type, recipient_email
            ");
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['notification_type']][] = $row;
            }
            
            return $settings;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get all notification settings', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Add a recipient to a notification type
     */
    public function addRecipient($notificationType, $email, $createdBy = null) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'Invalid email address'
                ];
            }
            
            // Validate notification type
            $validTypes = array_keys($this->getNotificationTypes());
            if (!in_array($notificationType, $validTypes)) {
                return [
                    'success' => false,
                    'error' => 'Invalid notification type'
                ];
            }
            
            $stmt = $this->getConnection()->prepare("
                INSERT INTO notification_settings (notification_type, recipient_email, created_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$notificationType, $email, $createdBy]);
            
            $this->logger->info('Added notification recipient', [
                'notification_type' => $notificationType,
                'recipient_email' => $email,
                'created_by' => $createdBy
            ]);
            
            return [
                'success' => true,
                'message' => 'Recipient added successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to add notification recipient', [
                'notification_type' => $notificationType,
                'recipient_email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to add recipient: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Remove a recipient from a notification type
     */
    public function removeRecipient($notificationType, $email) {
        try {
            // Prevent removal of default recipient
            if ($email === self::DEFAULT_RECIPIENT) {
                return [
                    'success' => false,
                    'error' => 'Cannot remove default recipient'
                ];
            }
            
            $stmt = $this->getConnection()->prepare("
                UPDATE notification_settings 
                SET is_active = 0, updated_at = CURRENT_TIMESTAMP
                WHERE notification_type = ? AND recipient_email = ?
            ");
            
            $stmt->execute([$notificationType, $email]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->info('Removed notification recipient', [
                    'notification_type' => $notificationType,
                    'recipient_email' => $email
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Recipient removed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Recipient not found'
                ];
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove notification recipient', [
                'notification_type' => $notificationType,
                'recipient_email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to remove recipient: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Toggle recipient status (active/inactive)
     */
    public function toggleRecipient($notificationType, $email) {
        try {
            // Prevent deactivation of default recipient
            if ($email === self::DEFAULT_RECIPIENT) {
                return [
                    'success' => false,
                    'error' => 'Cannot deactivate default recipient'
                ];
            }
            
            $stmt = $this->getConnection()->prepare("
                UPDATE notification_settings 
                SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP
                WHERE notification_type = ? AND recipient_email = ?
            ");
            
            $stmt->execute([$notificationType, $email]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->info('Toggled notification recipient status', [
                    'notification_type' => $notificationType,
                    'recipient_email' => $email
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Recipient status updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Recipient not found'
                ];
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to toggle notification recipient', [
                'notification_type' => $notificationType,
                'recipient_email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update recipient: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk add recipients to multiple notification types
     */
    public function bulkAddRecipient($email, $notificationTypes, $createdBy = null) {
        try {
            $results = [];
            
            foreach ($notificationTypes as $type) {
                $result = $this->addRecipient($type, $email, $createdBy);
                $results[$type] = $result;
            }
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk add notification recipient', [
                'recipient_email' => $email,
                'notification_types' => $notificationTypes,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to bulk add recipient: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get notification statistics
     */
    public function getStatistics() {
        try {
            $stmt = $this->getConnection()->prepare("
                SELECT 
                    notification_type,
                    COUNT(*) as total_recipients,
                    SUM(is_active) as active_recipients
                FROM notification_settings 
                GROUP BY notification_type
                ORDER BY notification_type
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get notification statistics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get daily status sync notification recipients from .env
     * 
     * Retrieves email recipients configured in NOTIFICATION_TO_EMAILS environment variable.
     * Emails should be comma-separated.
     * 
     * @return array List of email addresses
     */
    public function getDailyStatusSyncRecipients() {
        try {
            // Use $_ENV to read variables loaded by vlucas/phpdotenv
            $recipientsEnv = $_ENV['NOTIFICATION_TO_EMAILS'] ?? null;
            
            if (empty($recipientsEnv)) {
                $this->logger->warning('No daily status sync recipients configured in .env (NOTIFICATION_TO_EMAILS)');
                return [];
            }
            
            // Parse comma-separated emails
            $emails = array_map('trim', explode(',', $recipientsEnv));
            
            // Filter out empty strings and validate email format
            $validEmails = array_filter($emails, function($email) {
                return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            });
            
            $this->logger->info('Retrieved daily status sync recipients from .env', [
                'recipient_count' => count($validEmails)
            ]);
            
            return array_values($validEmails); // Reindex array
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get daily status sync recipients from .env', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}