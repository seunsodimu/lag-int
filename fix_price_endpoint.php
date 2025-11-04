<?php
$file = 'c:\xampp\htdocs\lag-int\src\Services\NetSuiteService.php';
$content = file_get_contents($file);

// Replace the endpoint format
$old = <<<'EOT'
            // Fetch price using the price endpoint with query parameters
            $params = [
                'quantity' => 0,
                'currencypage' => 1,
                'pricelevel' => 1
            ];
            
            $startTime = microtime(true);
            $endpoint = "/inventoryitem/{$itemId}/price";
            $response = $this->makeRequest('GET', $endpoint, null, $params);
EOT;

$new = <<<'EOT'
            $startTime = microtime(true);
            // Format: /inventoryitem/{id}/price/quantity=0,currencypage=1,pricelevel=1
            $endpoint = "/inventoryitem/{$itemId}/price/quantity=0,currencypage=1,pricelevel=1";
            $response = $this->makeRequest('GET', $endpoint, null);
EOT;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed!\n";
?>