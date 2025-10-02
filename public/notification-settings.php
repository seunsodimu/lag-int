<?php
/**
 * Notification Settings Management Page (Admin Only)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Services\NotificationSettingsService;
use Laguna\Integration\Utils\UrlHelper;

$auth = new AuthMiddleware();
$notificationService = new NotificationSettingsService();

// Require admin access
$currentUser = $auth->requireAdmin();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_recipient':
            $notificationType = $_POST['notification_type'] ?? '';
            $email = trim($_POST['email'] ?? '');
            
            if (empty($notificationType) || empty($email)) {
                $error = 'Please provide both notification type and email address.';
            } else {
                $result = $notificationService->addRecipient($notificationType, $email, $currentUser['id']);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['error'];
                }
            }
            break;
            
        case 'remove_recipient':
            $notificationType = $_POST['notification_type'] ?? '';
            $email = $_POST['email'] ?? '';
            
            $result = $notificationService->removeRecipient($notificationType, $email);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'toggle_recipient':
            $notificationType = $_POST['notification_type'] ?? '';
            $email = $_POST['email'] ?? '';
            
            $result = $notificationService->toggleRecipient($notificationType, $email);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'bulk_add':
            $email = trim($_POST['bulk_email'] ?? '');
            $selectedTypes = $_POST['notification_types'] ?? [];
            
            if (empty($email) || empty($selectedTypes)) {
                $error = 'Please provide email address and select at least one notification type.';
            } else {
                $results = $notificationService->bulkAddRecipient($email, $selectedTypes, $currentUser['id']);
                $successCount = 0;
                $errorMessages = [];
                
                foreach ($results as $type => $result) {
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorMessages[] = "Failed to add to {$type}: " . $result['error'];
                    }
                }
                
                if ($successCount > 0) {
                    $success = "Successfully added recipient to {$successCount} notification type(s).";
                }
                if (!empty($errorMessages)) {
                    $error = implode('<br>', $errorMessages);
                }
            }
            break;
    }
}

// Get current settings
$allSettings = $notificationService->getAllSettings();
$notificationTypes = $notificationService->getNotificationTypes();
$statistics = $notificationService->getStatistics();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - Laguna Integrations</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .nav {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
        }
        .nav a {
            color: #495057;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        .nav a:hover {
            color: #007bff;
        }
        .content {
            padding: 30px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .table tr:hover {
            background-color: #f8f9fa;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        .badge-primary {
            background-color: #007bff;
            color: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 2em;
        }
        .stat-card p {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }
        .notification-type {
            background: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .notification-type h3 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        .notification-type p {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 14px;
        }
        .recipients-list {
            margin-top: 15px;
        }
        .recipient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .recipient-email {
            font-weight: 500;
        }
        .recipient-actions {
            display: flex;
            gap: 5px;
        }
        .default-recipient {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .checkbox-item input {
            margin-right: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 15px;
            align-items: end;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Notification Settings</h1>
            <p>Manage email notification recipients by type and sync method</p>
        </div>
        
        <div class="nav">
            <a href="<?php echo UrlHelper::url('index.php'); ?>">🏠 Dashboard</a>
            <a href="<?php echo UrlHelper::url('user-management.php'); ?>">👥 Users</a>
            <a href="<?php echo UrlHelper::url('notification-settings.php'); ?>" style="color: #007bff;">📧 Notifications</a>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="section">
                <h2>📊 Notification Statistics</h2>
                <div class="stats-grid">
                    <?php 
                    $totalTypes = count($notificationTypes);
                    $totalRecipients = array_sum(array_column($statistics, 'total_recipients'));
                    $activeRecipients = array_sum(array_column($statistics, 'active_recipients'));
                    ?>
                    <div class="stat-card">
                        <h3><?php echo $totalTypes; ?></h3>
                        <p>Notification Types</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalRecipients; ?></h3>
                        <p>Total Recipients</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $activeRecipients; ?></h3>
                        <p>Active Recipients</p>
                    </div>
                    <div class="stat-card">
                        <h3>1</h3>
                        <p>Default Recipient</p>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Add Form -->
            <div class="section">
                <h2>➕ Bulk Add Recipient</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_add">
                    
                    <div class="form-group">
                        <label for="bulk_email">Email Address:</label>
                        <input type="email" id="bulk_email" name="bulk_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Notification Types:</label>
                        <div class="checkbox-group">
                            <?php foreach ($notificationTypes as $type => $info): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="type_<?php echo $type; ?>" name="notification_types[]" value="<?php echo $type; ?>">
                                    <label for="type_<?php echo $type; ?>">
                                        <strong><?php echo htmlspecialchars($info['label']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($info['description']); ?></small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add to Selected Types</button>
                </form>
            </div>
            
            <!-- Individual Add Form -->
            <div class="section">
                <h2>📝 Add Individual Recipient</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_recipient">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address:</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notification_type">Notification Type:</label>
                            <select id="notification_type" name="notification_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <?php foreach ($notificationTypes as $type => $info): ?>
                                    <option value="<?php echo $type; ?>"><?php echo htmlspecialchars($info['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Recipient</button>
                </form>
            </div>
            
            <!-- Current Settings -->
            <div class="section">
                <h2>⚙️ Current Notification Settings</h2>
                
                <?php foreach ($notificationTypes as $type => $info): ?>
                    <div class="notification-type">
                        <h3><?php echo htmlspecialchars($info['label']); ?></h3>
                        <p><?php echo htmlspecialchars($info['description']); ?></p>
                        
                        <div class="recipients-list">
                            <?php if (isset($allSettings[$type]) && !empty($allSettings[$type])): ?>
                                <?php foreach ($allSettings[$type] as $setting): ?>
                                    <div class="recipient-item <?php echo $setting['recipient_email'] === NotificationSettingsService::DEFAULT_RECIPIENT ? 'default-recipient' : ''; ?>">
                                        <div>
                                            <span class="recipient-email"><?php echo htmlspecialchars($setting['recipient_email']); ?></span>
                                            <?php if ($setting['recipient_email'] === NotificationSettingsService::DEFAULT_RECIPIENT): ?>
                                                <span class="badge badge-primary">Default</span>
                                            <?php endif; ?>
                                            <span class="badge <?php echo $setting['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo $setting['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="recipient-actions">
                                            <?php if ($setting['recipient_email'] !== NotificationSettingsService::DEFAULT_RECIPIENT): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_recipient">
                                                    <input type="hidden" name="notification_type" value="<?php echo $type; ?>">
                                                    <input type="hidden" name="email" value="<?php echo $setting['recipient_email']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        <?php echo $setting['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this recipient?');">
                                                    <input type="hidden" name="action" value="remove_recipient">
                                                    <input type="hidden" name="notification_type" value="<?php echo $type; ?>">
                                                    <input type="hidden" name="email" value="<?php echo $setting['recipient_email']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-primary">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="recipient-item">
                                    <span>No recipients configured for this notification type.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Help Section -->
            <div class="section">
                <h2>❓ Help & Information</h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h4>Notification Types Explained:</h4>
                    <ul>
                        <li><strong>Webhook Notifications:</strong> Sent when orders/contacts are processed automatically via webhooks from 3DCart or HubSpot</li>
                        <li><strong>Manual Notifications:</strong> Sent when orders/contacts are processed manually through the admin interface</li>
                        <li><strong>Success Notifications:</strong> Sent when processing completes successfully</li>
                        <li><strong>Failed Notifications:</strong> Sent when processing encounters errors</li>
                    </ul>
                    
                    <h4>Important Notes:</h4>
                    <ul>
                        <li><strong>Default Recipient:</strong> web_dev@lagunatools.com is always included in all notifications and cannot be removed</li>
                        <li><strong>Email Validation:</strong> All email addresses are validated before being added</li>
                        <li><strong>Bulk Operations:</strong> Use the bulk add feature to quickly add a recipient to multiple notification types</li>
                        <li><strong>Active/Inactive:</strong> Inactive recipients won't receive notifications but remain in the system</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>