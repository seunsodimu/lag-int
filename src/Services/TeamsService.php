<?php
/**
 * Microsoft Teams Notification Service
 * Sends notifications to Microsoft Teams channels via incoming webhooks
 */

namespace LagunaPartners;

class TeamsService {
    private $db;
    private $logFile;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logFile = __DIR__ . '/../logs/teams-' . date('Y-m-d') . '.log';
        $this->ensureLogDirectory();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            try {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("TeamsService: Failed to create log directory: $logDir");
                }
            } catch (\Throwable $e) {
                error_log("TeamsService: Exception creating log directory: " . $e->getMessage());
            }
        }
        
        if (is_dir($logDir) && !is_writable($logDir)) {
            error_log("TeamsService: Log directory is not writable: $logDir");
        }
    }

    /**
     * Log Teams notification activity
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        try {
            if (!file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX)) {
                error_log("TeamsService: Failed to write to log file: {$this->logFile}. Message: $message");
            }
        } catch (\Throwable $e) {
            error_log("TeamsService: Exception writing to log: " . $e->getMessage());
        }
    }

    /**
     * Get webhook URL for a notification type
     */
    private function getWebhookUrl($notificationType) {
        try {
            $setting = $this->db->fetchOne(
                "SELECT * FROM teams_webhook_config WHERE notification_type = ? AND is_active = 1",
                [$notificationType]
            );
            
            return $setting ? $setting['webhook_url'] : null;
        } catch (\Exception $e) {
            $this->log("Error retrieving webhook URL for type '$notificationType': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send notification to Teams for vendor PO update
     */
    public function sendVendorPOUpdate($poData, $changes, $vendorName) {
        $webhookUrl = $this->getWebhookUrl('po_vendor_update');
        
        if (!$webhookUrl) {
            $this->log("No webhook URL configured for 'po_vendor_update' notification type");
            return false;
        }

        try {
            $message = $this->buildPOUpdateMessage($poData, $changes, $vendorName);
            return $this->sendToTeams($webhookUrl, $message, 'po_vendor_update');
        } catch (\Exception $e) {
            $this->log("Error sending vendor PO update: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to Teams for invoice submission
     */
    public function sendInvoiceSubmitted($invoiceData, $vendorName) {
        $webhookUrl = $this->getWebhookUrl('invoice_submitted');
        
        if (!$webhookUrl) {
            $this->log("No webhook URL configured for 'invoice_submitted' notification type");
            return false;
        }

        try {
            $message = $this->buildInvoiceSubmitMessage($invoiceData, $vendorName);
            return $this->sendToTeams($webhookUrl, $message, 'invoice_submitted');
        } catch (\Exception $e) {
            $this->log("Error sending invoice submitted notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build Teams message for PO vendor update
     */
    private function buildPOUpdateMessage($poData, $changes, $vendorName) {
        $changesText = '';
        foreach ($changes as $field => $change) {
            $oldValue = $change['old'] ?? 'N/A';
            $newValue = $change['new'] ?? 'N/A';
            $changesText .= "- **" . ucfirst(str_replace('_', ' ', $field)) . "**: $oldValue → $newValue\n";
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "PO Update from {$vendorName}",
            'themeColor' => '0078D4',
            'sections' => [
                [
                    'activityTitle' => "Purchase Order Update",
                    'activitySubtitle' => "Vendor: {$vendorName}",
                    'facts' => [
                        [
                            'name' => 'PO Number:',
                            'value' => $poData['tran_id'] ?? $poData['tranid'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Vendor:',
                            'value' => $vendorName
                        ],
                        [
                            'name' => 'Total Amount:',
                            'value' => '$' . number_format($poData['total_amount'] ?? 0, 2)
                        ],
                        [
                            'name' => 'Status:',
                            'value' => $this->getStatusText($poData['status'] ?? 'Unknown')
                        ]
                    ],
                    'markdown' => true
                ],
                [
                    'activityTitle' => 'Changes Made:',
                    'text' => $changesText ?: 'No detailed changes recorded'
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View PO',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $this->getPortalLink('po', $poData['id'] ?? 0)
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Send notification to Teams for PO rejection
     */
    public function sendPOAcceptance($poData) {
        $webhookUrl = $this->getWebhookUrl('po_vendor_update');
        
        if (!$webhookUrl) {
            $this->log("No webhook URL configured for 'po_vendor_update' notification type");
            return false;
        }

        try {
            $message = $this->buildPOAcceptanceMessage($poData);
            return $this->sendToTeams($webhookUrl, $message, 'po_acceptance');
        } catch (\Exception $e) {
            $this->log("Error sending PO acceptance notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build Teams message for PO acceptance
     */
    private function buildPOAcceptanceMessage($poData) {
        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "PO Accepted by Vendor",
            'themeColor' => '107C10',
            'sections' => [
                [
                    'activityTitle' => "Purchase Order Accepted",
                    'activitySubtitle' => "Vendor: {$poData['vendor_name']}",
                    'facts' => [
                        [
                            'name' => 'PO Number:',
                            'value' => $poData['tran_id'] ?? $poData['tranid'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Vendor:',
                            'value' => $poData['vendor_name']
                        ],
                        [
                            'name' => 'Total Amount:',
                            'value' => '$' . number_format($poData['total_amount'] ?? 0, 2)
                        ],
                        [
                            'name' => 'Accepted At:',
                            'value' => date('m/d/Y H:i:s')
                        ]
                    ],
                    'markdown' => true
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View PO',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $this->getPortalLink('po', $poData['id'] ?? 0)
                        ]
                    ]
                ]
            ]
        ];
    }

    public function sendPORejection($poData, $rejectionReason) {
        $webhookUrl = $this->getWebhookUrl('po_vendor_update');
        
        if (!$webhookUrl) {
            $this->log("No webhook URL configured for 'po_vendor_update' notification type");
            return false;
        }

        try {
            $message = $this->buildPORejectionMessage($poData, $rejectionReason);
            return $this->sendToTeams($webhookUrl, $message, 'po_rejection');
        } catch (\Exception $e) {
            $this->log("Error sending PO rejection notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build Teams message for PO rejection
     */
    private function buildPORejectionMessage($poData, $rejectionReason) {
        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "PO Rejected by Vendor",
            'themeColor' => 'E81123',
            'sections' => [
                [
                    'activityTitle' => "Purchase Order Rejected",
                    'activitySubtitle' => "Vendor: {$poData['vendor_name']}",
                    'facts' => [
                        [
                            'name' => 'PO Number:',
                            'value' => $poData['tran_id'] ?? $poData['tranid'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Vendor:',
                            'value' => $poData['vendor_name']
                        ],
                        [
                            'name' => 'Total Amount:',
                            'value' => '$' . number_format($poData['total_amount'] ?? 0, 2)
                        ],
                        [
                            'name' => 'Rejected At:',
                            'value' => date('m/d/Y H:i:s')
                        ]
                    ],
                    'markdown' => true
                ],
                [
                    'activityTitle' => 'Rejection Reason:',
                    'text' => $rejectionReason
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View PO',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $this->getPortalLink('po', $poData['id'] ?? 0)
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Build Teams message for invoice submission
     */
    private function buildInvoiceSubmitMessage($invoiceData, $vendorName) {
        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "Invoice Submitted by {$vendorName}",
            'themeColor' => '107C10',
            'sections' => [
                [
                    'activityTitle' => 'Invoice Submitted',
                    'activitySubtitle' => "Vendor: {$vendorName}",
                    'facts' => [
                        [
                            'name' => 'Invoice Number:',
                            'value' => $invoiceData['invoice_number'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Vendor:',
                            'value' => $vendorName
                        ],
                        [
                            'name' => 'Total Amount:',
                            'value' => '$' . number_format($invoiceData['amount_total'] ?? 0, 2)
                        ],
                        [
                            'name' => 'Invoice Date:',
                            'value' => $invoiceData['invoice_date'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Status:',
                            'value' => 'Submitted'
                        ]
                    ],
                    'markdown' => true
                ],
                [
                    'text' => 'Invoice is awaiting buyer review and approval.'
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'Review Invoice',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $this->getPortalLink('invoice', $invoiceData['id'] ?? 0)
                        ]
                    ]
                ]
            ]
        ];
    }

    public function sendVendorCommentNotification($recordType, $recordId, $comment, $vendorName) {
        $webhookUrl = ($recordType=="Purchase Order") ? $this->getWebhookUrl('message_buyer') : $this->getWebhookUrl('message_accounting');
        if (!$webhookUrl) return false;

        $message = [
            "@type" => "MessageCard",
            "@context" => "http://schema.org/extensions",
            "themeColor" => "0076D7",
            "summary" => "New Comment from Vendor",
            "sections" => [[
                "activityTitle" => "New Comment on $recordType #$recordId",
                "activitySubtitle" => "From $vendorName",
                "text" => $comment,
                "potentialAction" => [[
                    "@type" => "OpenUri",
                    "name" => "View $recordType",
                    "targets" => [["os" => "default", "uri" => $this->getPortalLink($recordType, $recordId)]]
                ]]
            ]]
        ];

        return $this->sendToTeams($webhookUrl, $message, 'vendor_activity');
    }

    public function sendVendorFileNotification($recordType, $recordId, $fileName, $vendorName) {
        $webhookUrl = ($recordType=="Purchase Order") ? $this->getWebhookUrl('message_buyer') : $this->getWebhookUrl('message_accounting');
        if (!$webhookUrl) return false;

        $message = [
            "@type" => "MessageCard",
            "@context" => "http://schema.org/extensions",
            "themeColor" => "0076D7",
            "summary" => "New File Uploaded by Vendor",
            "sections" => [[
                "activityTitle" => "New File Uploaded to $recordType #$recordId",
                "activitySubtitle" => "From $vendorName",
                "text" => "File: **$fileName**",
                "potentialAction" => [[
                    "@type" => "OpenUri",
                    "name" => "View $recordType",
                    "targets" => [["os" => "default", "uri" => $this->getPortalLink($recordType, $recordId)]]
                ]]
            ]]
        ];

        return $this->sendToTeams($webhookUrl, $message, 'vendor_activity');
    }

    /**
     * Send message to Teams via webhook
     */
    private function sendToTeams($webhookUrl, $message, $notificationType) {
        $this->log("Sending Teams notification for type: $notificationType");
        
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->log("Teams notification sent successfully (HTTP $httpCode) for type: $notificationType");
            return true;
        } else {
            $this->log("Teams notification failed (HTTP $httpCode) for type: $notificationType. Error: $curlError. Response: $response");
            return false;
        }
    }

    /**
     * Get status text
     */
    private function getStatusText($statusCode) {
        $statuses = [
            'B' => 'Pending Received',
            'E' => 'Partially Received',
            'F' => 'Received',
            'H' => 'Cancelled',
            'C' => 'Closed'
        ];
        
        return $statuses[$statusCode] ?? ucfirst($statusCode);
    }

    /**
     * Get portal link
     */
    private function getPortalLink($type, $id) {
        $baseUrl = $_ENV['APP_BASE_URL'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = (strpos($baseUrl, 'http') === 0) ? $baseUrl : 'https://' . $baseUrl;
        
        $type = strtolower(str_replace(' ', '_', $type));

        if ($type === 'po' || $type === 'purchase_order') {
            return "$baseUrl/buyer/purchase-orders.php?id=$id";
        } elseif ($type === 'invoice') {
            return "$baseUrl/buyer/invoices.php?id=$id";
        } elseif ($type === 'messages') {
            return "$baseUrl/vendor/messages.php?conversation_id=$id";
        }
        
        return $baseUrl;
    }

    /**
     * Test webhook URL
     */
    public function testWebhook($webhookUrl) {
        $this->log("Testing webhook URL");
        
        $testMessage = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => 'Test Notification',
            'themeColor' => '0078D4',
            'sections' => [
                [
                    'activityTitle' => 'Test Notification',
                    'text' => 'This is a test notification from Laguna Partners Portal. If you see this message, your Teams webhook is configured correctly!'
                ]
            ]
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($testMessage),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->log("Webhook test successful (HTTP $httpCode)");
            return ['success' => true, 'message' => 'Webhook test successful'];
        } else {
            $this->log("Webhook test failed (HTTP $httpCode): $curlError");
            return ['success' => false, 'error' => "Webhook test failed (HTTP $httpCode): $response"];
        }
    }

    /**
     * Send notification to Teams for new message
     */
    public function sendNewMessage($conversation, $message, $senderName, $senderType) {
        $webhookType = $conversation['conversation_type'] === 'vendor_to_accounting' ? 'message_accounting' : 'message_buyer';
        $webhookUrl = $this->getWebhookUrl($webhookType);
        
        if (!$webhookUrl) {
            $this->log("No webhook URL configured for '$webhookType' notification type");
            return false;
        }

        try {
            $messageObj = $this->buildNewMessageCard($conversation, $message, $senderName, $senderType);
            return $this->sendToTeams($webhookUrl, $messageObj, $webhookType);
        } catch (\Exception $e) {
            $this->log("Error sending new message notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build Teams card for new message
     */
    private function buildNewMessageCard($conversation, $message, $senderName, $senderType) {
        $conversationTitle = $conversation['conversation_type'] === 'vendor_to_accounting' 
            ? 'New Accounting Message' 
            : 'New Buyer Message';

        $messagePreview = substr($message['message_text'], 0, 200);
        if (strlen($message['message_text']) > 200) {
            $messagePreview .= '...';
        }

        return [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "$senderName: $conversationTitle",
            'themeColor' => '0078D4',
            'sections' => [
                [
                    'activityTitle' => $conversationTitle,
                    'activitySubtitle' => "From: $senderName",
                    'facts' => [
                        [
                            'name' => 'Vendor:',
                            'value' => $conversation['vendor_name'] ?? 'N/A'
                        ],
                        [
                            'name' => 'Time:',
                            'value' => date('m/d/Y H:i:s')
                        ]
                    ],
                    'markdown' => true
                ],
                [
                    'activityTitle' => 'Message:',
                    'text' => $messagePreview
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View Conversation',
                    'targets' => [
                        [
                            'os' => 'default',
                            'uri' => $this->getPortalLink('messages', $conversation['id'])
                        ]
                    ]
                ]
            ]
        ];
    }

}
