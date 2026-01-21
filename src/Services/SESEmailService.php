<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laguna\Integration\Utils\Logger;

/**
 * AWS SES Email Service - Direct HTTP REST API Implementation
 * 
 * Uses direct HTTP calls to AWS SES API with Signature Version 4 signing.
 * No AWS SDK dependency required.
 */
class SESEmailService {
    private $credentials;
    private $config;
    private $logger;
    private $httpClient;
    private $region;
    private $endpoint;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->credentials = $credentials['email']['ses'];
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        $this->region = $this->credentials['region'] ?? 'us-east-1';
        $this->endpoint = "https://email.{$this->region}.amazonaws.com/";
        
        $this->initializeClient();
    }
    
    private function initializeClient() {
        if (empty($this->credentials['access_key']) || empty($this->credentials['secret_key'])) {
            $this->logger->warning('AWS SES credentials not configured', [
                'hint' => 'Set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY environment variables'
            ]);
            return;
        }
        
        try {
            $this->httpClient = new Client(['timeout' => 30]);
            $this->logger->info('AWS SES HTTP client initialized', ['region' => $this->region]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize AWS SES HTTP client', [
                'error' => $e->getMessage()
            ]);
            $this->httpClient = null;
        }
    }
    
    /**
     * Send email via AWS SES REST API
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        if (!$this->httpClient) {
            return [
                'success' => false,
                'error' => 'AWS SES client not initialized. Ensure AWS credentials are properly configured.',
                'provider' => 'ses'
            ];
        }
        
        try {
            $toAddresses = array_map('trim', (array)$recipients);
            
            $this->logger->info('Sending email via AWS SES REST API', [
                'subject' => $subject,
                'recipients' => $toAddresses,
                'is_test' => $isTest
            ]);
            
            $response = $this->makeSignedRequest('SendEmail', [
                'Source' => $this->credentials['from_email'],
                'Destination.ToAddresses.member.1' => $toAddresses[0],
            ] + $this->buildMultiAddressParams($toAddresses) + [
                'Message.Subject.Data' => $subject,
                'Message.Subject.Charset' => 'UTF-8',
                'Message.Body.Html.Data' => $htmlContent,
                'Message.Body.Html.Charset' => 'UTF-8',
            ]);
            
            $messageId = $this->extractXmlValue($response, 'MessageId');
            
            if (!$messageId) {
                throw new \Exception('No MessageId returned from SES');
            }
            
            $this->logger->info('Email sent successfully via AWS SES', [
                'subject' => $subject,
                'message_id' => $messageId,
                'recipients' => $toAddresses
            ]);
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'status_code' => 200,
                'provider' => 'ses',
                'method' => 'rest_api'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via AWS SES', [
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'ses',
                'method' => 'rest_api'
            ];
        }
    }
    
    /**
     * Test AWS SES connection
     */
    public function testConnection() {
        if (!$this->httpClient) {
            return [
                'success' => false,
                'error' => 'AWS SES HTTP client not initialized',
                'service' => 'AWS SES',
                'hint' => 'Check AWS credentials configuration'
            ];
        }
        
        try {
            $response = $this->makeSignedRequest('GetSendQuota', []);
            
            $max24Hour = (int)$this->extractXmlValue($response, 'Max24HourSend');
            $sentLast24Hour = (int)$this->extractXmlValue($response, 'SentLast24Hour');
            $maxSendRate = (int)$this->extractXmlValue($response, 'MaxSendRate');
            
            $this->logger->info('AWS SES connection test successful', [
                'max_24_hour_send' => $max24Hour,
                'sent_last_24_hour' => $sentLast24Hour
            ]);
            
            return [
                'success' => true,
                'status_code' => 200,
                'service' => 'AWS SES (REST API)',
                'region' => $this->region,
                'quota' => [
                    'max_24_hour_send' => $max24Hour,
                    'sent_last_24_hour' => $sentLast24Hour,
                    'max_send_rate' => $maxSendRate
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('AWS SES connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'AWS SES',
                'hint' => 'Verify AWS credentials and region configuration'
            ];
        }
    }
    
    /**
     * Make signed AWS request
     */
    private function makeSignedRequest($action, $params) {
        $timestamp = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $params['Action'] = $action;
        $params['Version'] = '2010-12-01';
        
        $canonicalRequest = $this->buildCanonicalRequest('POST', '/', $params, $timestamp, $datestamp);
        $signature = $this->calculateSignature($canonicalRequest, $datestamp);
        
        $authHeader = $this->buildAuthorizationHeader($signature, $datestamp, $timestamp);
        
        $body = http_build_query($params);
        
        try {
            $guzzleResponse = $this->httpClient->post($this->endpoint, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'X-Amz-Date' => $timestamp,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Host' => parse_url($this->endpoint, PHP_URL_HOST),
                ],
                'body' => $body,
                'allow_redirects' => false,
            ]);
            
            $responseBody = $guzzleResponse->getBody()->getContents();
            
            if ($guzzleResponse->getStatusCode() !== 200) {
                throw new \Exception("AWS SES API returned status {$guzzleResponse->getStatusCode()}: {$responseBody}");
            }
            
            return $responseBody;
        } catch (GuzzleException $e) {
            throw new \Exception('AWS SES request failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Build canonical request for Signature Version 4
     */
    private function buildCanonicalRequest($method, $path, $params, $timestamp, $datestamp) {
        $canonicalQuerystring = '';
        $canonicalHeaders = "host:" . parse_url($this->endpoint, PHP_URL_HOST) . "\n"
                          . "x-amz-date:" . $timestamp . "\n";
        $signedHeaders = "host;x-amz-date";
        
        $payload = http_build_query($params);
        $payloadHash = hash('sha256', $payload);
        
        $canonicalRequest = "{$method}\n{$path}\n{$canonicalQuerystring}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        return $canonicalRequest;
    }
    
    /**
     * Calculate AWS Signature Version 4
     */
    private function calculateSignature($canonicalRequest, $datestamp) {
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$datestamp}/{$this->region}/email/aws4_request";
        $stringToSign = "{$algorithm}\n" . gmdate('Ymd\THis\Z') . "\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        $kSecret = 'AWS4' . $this->credentials['secret_key'];
        $kDate = hash_hmac('sha256', $datestamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 'email', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
    
    /**
     * Build Authorization header
     */
    private function buildAuthorizationHeader($signature, $datestamp, $timestamp) {
        $credentialScope = "{$datestamp}/{$this->region}/email/aws4_request";
        $signedHeaders = "host;x-amz-date";
        
        return "AWS4-HMAC-SHA256 Credential={$this->credentials['access_key']}/{$credentialScope}, "
             . "SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
    
    /**
     * Build multi-address parameters for email recipients
     */
    private function buildMultiAddressParams($addresses) {
        $params = [];
        foreach ($addresses as $index => $address) {
            $params["Destination.ToAddresses.member." . ($index + 1)] = $address;
        }
        return $params;
    }
    
    /**
     * Extract value from XML response
     */
    private function extractXmlValue($xml, $tag) {
        $pattern = "/<{$tag}>(.*?)<\/{$tag}>/s";
        if (preg_match($pattern, $xml, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
