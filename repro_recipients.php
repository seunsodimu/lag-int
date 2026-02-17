<?php
require_once __DIR__ . '/vendor/autoload.php';
use Laguna\Integration\Services\NotificationSettingsService;

$service = new NotificationSettingsService();
$type = NotificationSettingsService::TYPE_3DCART_SUCCESS_WEBHOOK;
$recipients = $service->getRecipients($type);

echo "Recipients for $type:\n";
print_r($recipients);
