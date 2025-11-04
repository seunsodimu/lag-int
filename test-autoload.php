<?php
require __DIR__ . '/vendor/autoload.php';

echo "Testing autoloader...\n";

if (class_exists('Laguna\Integration\Services\NetSuiteService')) {
    echo "✓ NetSuiteService found!\n";
} else {
    echo "✗ NetSuiteService NOT found\n";
}

if (class_exists('Laguna\Integration\Services\InventorySyncService')) {
    echo "✓ InventorySyncService found!\n";
} else {
    echo "✗ InventorySyncService NOT found\n";
}

// List PSR-4 paths
$autoloaders = spl_autoload_functions();
echo "\nAutoloaders registered: " . count($autoloaders) . "\n";

// Try to find what paths Composer is looking in
echo "\nChecking manual file include...\n";
$file = __DIR__ . '/src/Services/NetSuiteService.php';
if (file_exists($file)) {
    echo "✓ File exists at: $file\n";
    $content = file_get_contents($file, false, null, 0, 200);
    echo "First 200 chars:\n" . $content . "\n";
} else {
    echo "✗ File not found at: $file\n";
}