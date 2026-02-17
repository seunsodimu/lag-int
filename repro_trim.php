<?php
$recipients = ['web_dev@lagunatools.com '];
$default = 'web_dev@lagunatools.com';

if (!in_array($default, $recipients)) {
    $recipients[] = $default;
}

echo "Recipients before trim:\n";
print_r($recipients);

$final = [];
foreach ($recipients as $r) {
    $final[] = trim($r);
}

echo "Recipients after trim:\n";
print_r($final);
