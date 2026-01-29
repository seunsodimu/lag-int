<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Services\EmailService;
use Laguna\Integration\Services\EnhancedEmailService;

/**
 * HubSpot Service
 * 
 * Handles all HubSpot API interactions including contact retrieval and webhook processing.
 */
class HubSpotService {
    private $client;
    private $config;
    private $credentials;
    private $logger;
    private $emailService;
    private $enhancedEmailService;
    
    public function __construct($isWebhookContext = false) {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->credentials = require __DIR__ . '/../../config/credentials.php';
        $this->logger = Logger::getInstance();
        $this->enhancedEmailService = new EnhancedEmailService($isWebhookContext);
        
        $this->client = new Client([
            'base_uri' => $this->credentials['hubspot']['base_url'],
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->credentials['hubspot']['access_token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }
    
    /**
     * Test HubSpot API connection
     */
    public function testConnection() {
        try {
            $startTime = microtime(true);
            
            // Test with account details endpoint
            $response = $this->client->get('/account-info/v3/details');
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response->getStatusCode() === 200) {
                $this->logger->info('HubSpot connection test successful');
                
                return [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'response_time' => $responseTime . 'ms',
                    'message' => 'Connected successfully'
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'HubSpot API connection failed: ' . $e->getMessage();
            $this->logger->error($error);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'HubSpot connection error: ' . $e->getMessage();
            $this->logger->error($error);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Get contact information by ID
     */
    public function getContact($contactId) {
        try {
            $properties = [
                'avenue',
                'company',
                'hs_analytics_first_referrer',
                'hs_analytics_source',
                'hs_analytics_source_data_1',
                'hs_analytics_source_data_2',
                'hubspot_owner_id',
                'lead_source_netsuite',
                'lifecyclestage',
                'message',
                'ns_customer_id',
                'ns_entity_id',
                'phone',
                'promo_code',
                'email',
                'firstname',
                'lastname'
            ];
            
            $queryParams = http_build_query([
                'properties' => implode('&properties=', $properties),
                'archived' => 'false'
            ]);
            
            $queryParams = urldecode($queryParams);
            
            $response = $this->client->get("/crm/v3/objects/contacts/{$contactId}?{$queryParams}");
          
            if ($response->getStatusCode() === 200) {
                $contactData = json_decode($response->getBody()->getContents(), true);
                $this->logger->info('HubSpot contact retrieved successfully', [
                    'contact_id' => $contactId,
                    'email' => $contactData['properties']['email'] ?? 'N/A'
                ]);
                
                return [
                    'success' => true,
                    'data' => $contactData
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'Failed to retrieve HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, ['contact_id' => $contactId]);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'HubSpot contact retrieval error: ' . $e->getMessage();
            $this->logger->error($error, ['contact_id' => $contactId]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Update HubSpot contact with NetSuite customer ID
     */
    public function updateContactNetSuiteId($contactId, $netsuiteCustomerId) {
        try {
            $url = "/crm/v3/objects/contacts/{$contactId}";
            
            $data = [
                'properties' => [
                    'ns_customer_id' => (string)$netsuiteCustomerId
                ]
            ];
            
            $response = $this->client->patch($url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getBody()->getContents(), true);
                $this->logger->info('Successfully updated HubSpot contact with NetSuite ID', [
                    'contact_id' => $contactId,
                    'netsuite_id' => $netsuiteCustomerId
                ]);
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Contact updated with NetSuite customer ID'
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'Failed to update HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contactId,
                'netsuite_id' => $netsuiteCustomerId
            ]);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'Error updating HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contactId,
                'netsuite_id' => $netsuiteCustomerId
            ]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Process webhook payload
     */
    public function processWebhook($payload) {
        try {
            $this->logger->info('Processing HubSpot webhook', ['payload' => $payload]);
            
            // Validate required fields
            if (!isset($payload['objectId']) || !isset($payload['subscriptionType'])) {
                throw new \Exception('Invalid webhook payload: missing required fields');
            }
            
            // Only process contact property changes
            if ($payload['subscriptionType'] !== 'contact.propertyChange') {
                $this->logger->info('Ignoring non-contact webhook', [
                    'subscription_type' => $payload['subscriptionType']
                ]);
                return [
                    'success' => true,
                    'message' => 'Webhook ignored - not a contact property change'
                ];
            }

            $propertyName = $payload['propertyName'] ?? '';
            $propertyValue = $payload['propertyValue'] ?? '';

            // Check if property synchronization is enabled
            if (!$this->isPropertySyncEnabled($propertyName)) {
                $this->logger->info('Property synchronization disabled', ['property' => $propertyName]);
                return [
                    'success' => true,
                    'message' => "Property {$propertyName} synchronization is disabled"
                ];
            }

            // Handle different property changes
            if ($propertyName === 'hubspot_owner_id') {
                // Current logic: Create/Update lead in NetSuite when owner is assigned
                
                // Get contact information
                $contactResult = $this->getContact($payload['objectId']);
                if (!$contactResult['success']) {
                    throw new \Exception('Failed to retrieve contact: ' . $contactResult['error']);
                }
                
                $contact = $contactResult['data'];
                
                // Check if lifecycle stage is 'lead'
                $lifecycleStage = strtolower($contact['properties']['lifecyclestage'] ?? '');
                if ($lifecycleStage !== 'lead')  {
                    $this->logger->info('Contact is not a lead, skipping processing. But its a '.($contact['properties']['lifecyclestage'] ?? 'N/A'), [
                        'contact_id' => $payload['objectId'],
                        'lifecyclestage' => $lifecycleStage
                    ]);
                    return [
                        'success' => true,
                        'message' => 'Contact is not a lead - processing skipped'
                    ];
                }
                
                // Process the lead
                $netSuiteService = new NetSuiteService();
                return $this->processLead($contact, $payload, $netSuiteService);

            } elseif (in_array($propertyName, ['sales_readiness', 'projected_value', 'buying_reason', 'buying_time_frame'])) {
                // New logic: Update specific fields in NetSuite
                
                $mapping = $this->mapHubSpotToNetSuite($propertyName, $propertyValue);
                if (!$mapping) {
                    $this->logger->warning('No mapping found for HubSpot property change', [
                        'property' => $propertyName,
                        'value' => $propertyValue
                    ]);
                    return [
                        'success' => false,
                        'message' => "No mapping found for property {$propertyName} with value {$propertyValue}"
                    ];
                }

                $netSuiteService = new NetSuiteService();
                
                // Lookup NetSuite customer ID using HubSpot objectId
                $hubspotObjectId = $payload['objectId'];
                $netsuiteCustomerId = $this->findNetSuiteCustomerByHubSpotId($hubspotObjectId, $netSuiteService);
                
                if (!$netsuiteCustomerId) {
                    $this->logger->warning('NetSuite customer not found for HubSpot property change', [
                        'hubspot_id' => $hubspotObjectId,
                        'property' => $propertyName
                    ]);
                    return [
                        'success' => false,
                        'message' => "NetSuite customer not found for HubSpot ID {$hubspotObjectId}"
                    ];
                }
                
                $updateData = [
                    $mapping['field'] => $mapping['value']
                ];
                
                $this->logger->info('Updating NetSuite customer from HubSpot property change', [
                    'hubspot_id' => $hubspotObjectId,
                    'netsuite_customer_id' => $netsuiteCustomerId,
                    'property' => $propertyName,
                    'mapped_field' => $mapping['field'],
                    'mapped_value' => $mapping['value']
                ]);
                
                return $netSuiteService->updateCustomer($netsuiteCustomerId, $updateData);

            } else {
                $this->logger->info('Ignoring property change', ['property' => $propertyName]);
                return [
                    'success' => true,
                    'message' => "Property {$propertyName} ignored"
                ];
            }
            
        } catch (\Exception $e) {
            $error = 'HubSpot webhook processing failed: ' . $e->getMessage();
            $this->logger->error($error, ['payload' => $payload]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Process lead and create in NetSuite
     */
    private function processLead($contact, $webhookPayload, $netSuiteService) {
        try {
            // Format campaign ID
            $formattedCampaignId = $this->formatCampaignId(
                $contact['properties']['hs_analytics_source_data_1'] ?? null
            );
            
            // Check if campaign exists in NetSuite
            $campaignResult = $this->findOrCreateCampaign(
                $formattedCampaignId,
                $contact['properties']['hs_analytics_source_data_1'] ?? null,
                $netSuiteService
            );
            
            if (!$campaignResult['success']) {
                throw new \Exception('Failed to handle campaign: ' . $campaignResult['error']);
            }
            
            $contactCampaignId = $campaignResult['campaign_id'];
            
            // Get NetSuite employee ID from HubSpot owner
            $netsuiteEmployeeId = $this->getNetSuiteEmployeeId($webhookPayload, $netSuiteService);
            
            // Create lead payload for NetSuite
            $leadPayload = $this->buildLeadPayload($contact, $webhookPayload, $contactCampaignId, $formattedCampaignId, $netsuiteEmployeeId);
            
            // Create lead in NetSuite
            $leadResult = $netSuiteService->createLead($leadPayload);
            
            if ($leadResult['success']) {
                $this->logger->info('Lead created successfully in NetSuite', [
                    'hubspot_contact_id' => $contact['id'],
                    'netsuite_lead_id' => $leadResult['lead_id'] ?? 'N/A',
                    'email' => $contact['properties']['email']
                ]);
                
                // Send success notification
                $this->enhancedEmailService->sendHubSpotSyncNotification($contact['id'], 'Successfully Synced', [
                    'NetSuite Lead ID' => $leadResult['lead_id'] ?? 'N/A',
                    'Email' => $contact['properties']['email'] ?? 'N/A',
                    'First Name' => $contact['properties']['firstname'] ?? 'N/A',
                    'Last Name' => $contact['properties']['lastname'] ?? 'N/A',
                    'Company' => $contact['properties']['company'] ?? 'N/A',
                    'Lifecycle Stage' => $contact['properties']['lifecyclestage'] ?? 'N/A'
                ], true);
                
                // Update HubSpot contact with NetSuite customer ID
                if (!empty($leadResult['lead_id'])) {
                    $updateResult = $this->updateContactNetSuiteId($contact['id'], $leadResult['lead_id']);
                    
                    if ($updateResult['success']) {
                        $this->logger->info('HubSpot contact updated with NetSuite ID', [
                            'hubspot_contact_id' => $contact['id'],
                            'netsuite_lead_id' => $leadResult['lead_id']
                        ]);
                        
                        // Add update info to the result
                        $leadResult['hubspot_updated'] = true;
                        $leadResult['hubspot_update_result'] = $updateResult;
                    } else {
                        $this->logger->warning('Failed to update HubSpot contact with NetSuite ID', [
                            'hubspot_contact_id' => $contact['id'],
                            'netsuite_lead_id' => $leadResult['lead_id'],
                            'error' => $updateResult['error'] ?? 'Unknown error'
                        ]);
                        
                        // Add update failure info to the result
                        $leadResult['hubspot_updated'] = false;
                        $leadResult['hubspot_update_error'] = $updateResult['error'] ?? 'Unknown error';
                    }
                }
            } else {
                // Send failure notification
                $this->enhancedEmailService->sendHubSpotSyncNotification($contact['id'], 'Sync Failed', [
                    'Error' => $leadResult['error'] ?? 'Unknown error',
                    'Email' => $contact['properties']['email'] ?? 'N/A',
                    'First Name' => $contact['properties']['firstname'] ?? 'N/A',
                    'Last Name' => $contact['properties']['lastname'] ?? 'N/A',
                    'Company' => $contact['properties']['company'] ?? 'N/A',
                    'Lifecycle Stage' => $contact['properties']['lifecyclestage'] ?? 'N/A'
                ], false);
            }
            
            return $leadResult;
            
        } catch (\Exception $e) {
            $error = 'Lead processing failed: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contact['id'] ?? 'N/A',
                'email' => $contact['properties']['email'] ?? 'N/A'
            ]);
            
            // Send failure notification
            $this->enhancedEmailService->sendHubSpotSyncNotification($contact['id'] ?? 'Unknown', 'Sync Failed', [
                'Error' => $e->getMessage(),
                'Email' => $contact['properties']['email'] ?? 'N/A',
                'First Name' => $contact['properties']['firstname'] ?? 'N/A',
                'Last Name' => $contact['properties']['lastname'] ?? 'N/A',
                'Company' => $contact['properties']['company'] ?? 'N/A',
                'Lifecycle Stage' => $contact['properties']['lifecyclestage'] ?? 'N/A'
            ], false);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Format campaign ID according to requirements
     */
    private function formatCampaignId($sourceData) {
        if (empty($sourceData)) {
            return 'None';
        }
        
        // Replace all characters that are not letters, numbers, or underscores with underscore
        $formatted = preg_replace('/[^a-zA-Z0-9_]/', '_', $sourceData);
        
        // Truncate to maximum of 60 characters
        $formatted = substr($formatted, 0, 60);
        
        return $formatted;
    }
    
    /**
     * Find existing campaign or create new one in NetSuite
     */
    private function findOrCreateCampaign($formattedCampaignId, $originalTitle, $netSuiteService) {
        try {
            // Search for existing campaign
            $searchResult = $netSuiteService->searchCampaign($formattedCampaignId);
            
            if ($searchResult['success'] && !empty($searchResult['campaigns'])) {
                // Campaign exists
                $campaign = $searchResult['campaigns'][0];
                return [
                    'success' => true,
                    'campaign_id' => $campaign['id'],
                    'existing' => true
                ];
            }
            
            // Campaign doesn't exist, create new one
            $campaignPayload = [
                'title' => $originalTitle ?: $formattedCampaignId,
                'campaignid' => $formattedCampaignId,
                'category' => ['id' => '-5'],
                'owner' => ['id' => '124']
            ];
            
            $createResult = $netSuiteService->createCampaign($campaignPayload);
            
            if ($createResult['success']) {
                return [
                    'success' => true,
                    'campaign_id' => $createResult['campaign_id'],
                    'existing' => false
                ];
            } else {
                throw new \Exception('Failed to create campaign: ' . $createResult['error']);
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get NetSuite employee ID from HubSpot owner
     * Returns employee ID on success, throws exception on failure
     */
    private function getNetSuiteEmployeeId($webhookPayload, $netSuiteService) {
        // Get HubSpot owner ID from webhook payload
        $hubspotOwnerId = $webhookPayload['propertyValue'] ?? null;
        
        if (empty($hubspotOwnerId)) {
            $error = 'No HubSpot owner ID in webhook payload';
            $this->logger->error($error, ['webhook_payload' => $webhookPayload]);
            
            $this->emailService->sendEmployeeLookupFailureNotification(
                'N/A',
                'N/A',
                $error,
                ['webhook_payload' => $webhookPayload]
            );
            
            throw new \Exception($error);
        }
        
        // Get HubSpot owner details
        $ownerResult = $this->getHubSpotOwner($hubspotOwnerId);
        if (!$ownerResult['success']) {
            $error = 'Failed to get HubSpot owner details: ' . $ownerResult['error'];
            $this->logger->error($error, [
                'owner_id' => $hubspotOwnerId,
                'error' => $ownerResult['error']
            ]);
            
            $this->emailService->sendEmployeeLookupFailureNotification(
                $hubspotOwnerId,
                'Unknown',
                $error,
                [
                    'hubspot_owner_id' => $hubspotOwnerId,
                    'api_error' => $ownerResult['error'],
                    'webhook_payload' => $webhookPayload
                ]
            );
            
            throw new \Exception($error);
        }
        
        $ownerEmail = $ownerResult['data']['email'] ?? null;
        if (empty($ownerEmail)) {
            $error = 'No email found for HubSpot owner';
            $this->logger->error($error, [
                'owner_id' => $hubspotOwnerId,
                'owner_data' => $ownerResult['data']
            ]);
            
            $this->emailService->sendEmployeeLookupFailureNotification(
                $hubspotOwnerId,
                'No email found',
                $error,
                [
                    'hubspot_owner_id' => $hubspotOwnerId,
                    'owner_data' => $ownerResult['data'],
                    'webhook_payload' => $webhookPayload
                ]
            );
            
            throw new \Exception($error);
        }
        
        // Find corresponding NetSuite employee
        $employeeResult = $netSuiteService->findEmployeeByEmail($ownerEmail);
        if (!$employeeResult['success']) {
            $error = 'Failed to find NetSuite employee by email: ' . $employeeResult['error'];
            $this->logger->error($error, [
                'email' => $ownerEmail,
                'hubspot_owner_id' => $hubspotOwnerId,
                'error' => $employeeResult['error']
            ]);
            
            $this->emailService->sendEmployeeLookupFailureNotification(
                $hubspotOwnerId,
                $ownerEmail,
                $error,
                [
                    'hubspot_owner_id' => $hubspotOwnerId,
                    'owner_email' => $ownerEmail,
                    'netsuite_error' => $employeeResult['error'],
                    'webhook_payload' => $webhookPayload,
                    'owner_data' => $ownerResult['data']
                ]
            );
            
            throw new \Exception($error);
        }
        
        $netsuiteEmployeeId = $employeeResult['employee_id'];
        $this->logger->info('Successfully mapped HubSpot owner to NetSuite employee', [
            'hubspot_owner_id' => $hubspotOwnerId,
            'owner_email' => $ownerEmail,
            'netsuite_employee_id' => $netsuiteEmployeeId
        ]);
        
        return $netsuiteEmployeeId;
    }
    
    /**
     * Get HubSpot owner details by ID
     */
    private function getHubSpotOwner($ownerId) {
        try {
            $response = $this->client->get("/crm/v3/owners/{$ownerId}");
            
            if ($response->getStatusCode() === 200) {
                $ownerData = json_decode($response->getBody()->getContents(), true);
                
                return [
                    'success' => true,
                    'data' => $ownerData
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'Failed to retrieve HubSpot owner: ' . $e->getMessage();
            return [
                'success' => false,
                'error' => $error
            ];
        } catch (\Exception $e) {
            $error = 'HubSpot owner retrieval error: ' . $e->getMessage();
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Build lead payload for NetSuite
     */
    private function buildLeadPayload($contact, $webhookPayload, $contactCampaignId, $formattedCampaignId, $netsuiteEmployeeId) {
        $properties = $contact['properties'];
        
        // Helper function to convert empty strings to null
        $nullIfEmpty = function($value) {
            return empty($value) ? null : $value;
        };
        
        return [
            'customform' => 259,
            'entityStatus' => ['id' => 19],
            'email' => $properties['email'] ?? null,
            'custentity_hs_vid' => $contact['id'],
            'phone' => $nullIfEmpty($properties['phone'] ?? ''),
            'custentity_comments' => $nullIfEmpty($properties['message'] ?? ''),
            'subsidiary' => 1,
            'entityid' => ($properties['firstname'] ?? '') . ' ' . ($properties['lastname'] ?? '') . ' ' . ($properties['email'] ?? ''),
            'autoname' => false,
            'isperson' => true,
            'custentity_hsoriginalsource' => $contactCampaignId,
            'custentity_hsorigsourdrilldown2' => $formattedCampaignId,
            'custentity_firstreferringsite' => $nullIfEmpty($properties['hs_analytics_first_referrer'] ?? ''),
            'custentity_add_promo_code' => $nullIfEmpty($properties['promo_code'] ?? ''),
            'companyname' => $nullIfEmpty($properties['company'] ?? ''),
            'lastname' => $properties['firstname'] ?? null,  // Note: swapped as per successful example
            'firstname' => $properties['lastname'] ?? null,   // Note: swapped as per successful example
            'leadsource' => ['id' => 625], // Using the working leadsource ID from successful example
            'salesTeam' => [
                'items' => [
                    [
                        'employee' => ['id' => intval($netsuiteEmployeeId)], // Use mapped NetSuite employee ID
                        'isprimary' => true,
                        'contribution' => 100,
                        'salesrole' => ['id' => -2]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Find NetSuite customer ID by HubSpot object ID using SuiteQL
     */
    private function findNetSuiteCustomerByHubSpotId($hubspotId, $netSuiteService) {
        try {
            $query = "SELECT id FROM customer WHERE custentity_celigo_hubspot_id = " . intval($hubspotId);
            $result = $netSuiteService->executeSuiteQLQuery($query);
            
            if (isset($result['items']) && count($result['items']) > 0) {
                return $result['items'][0]['id'];
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to lookup NetSuite customer by HubSpot ID', [
                'hubspot_id' => $hubspotId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if synchronization is enabled for a specific property
     */
    private function isPropertySyncEnabled($propertyName) {
        $syncProperties = $this->config['integrations']['hubspot_netsuite']['sync_properties'] ?? [];
        
        // If the property is not in the list, we assume it's disabled 
        // OR we can default to true for backward compatibility.
        // Given the request, we should check specifically for the provided properties.
        return $syncProperties[$propertyName] ?? true;
    }

    /**
     * Map HubSpot property and value to NetSuite field and ID
     */
    private function mapHubSpotToNetSuite($propertyName, $propertyValue) {
        $mappingFile = __DIR__ . '/../../docs/netsuite_hubspot_mapping.csv';
        if (!file_exists($mappingFile)) {
            $this->logger->error('Mapping file not found', ['file' => $mappingFile]);
            return null;
        }

        $handle = fopen($mappingFile, 'r');
        if ($handle === false) {
            $this->logger->error('Failed to open mapping file', ['file' => $mappingFile]);
            return null;
        }

        // Skip header
        fgetcsv($handle);

        $mappedField = null;
        $mappedValue = null;

        while (($data = fgetcsv($handle)) !== false) {
            // CSV structure: Values, NetSuite Value Internal ID, Label, NetSuite field ID, HubSpot field ID
            // Index 0: Value (HubSpot value)
            // Index 1: NetSuite Internal ID
            // Index 3: NetSuite field ID
            // Index 4: HubSpot field ID
            
            // Check for property name match (index 4) and value match (index 0)
            if (isset($data[4]) && $data[4] === $propertyName && isset($data[0]) && $data[0] === $propertyValue) {
                $mappedField = $data[3];
                $mappedValue = $data[1];
                break;
            }
        }

        fclose($handle);

        if ($mappedField && $mappedValue) {
            // Special handling for salesreadines -> salesreadiness if needed, 
            // but we follow the CSV as requested.
            return [
                'field' => $mappedField,
                'value' => $mappedValue
            ];
        }

        return null;
    }

    /**
     * Verify webhook signature (if needed)
     */
    public function verifyWebhookSignature($payload, $signature) {
        $expectedSignature = hash_hmac('sha256', $payload, $this->credentials['hubspot']['webhook_secret']);
        return hash_equals($expectedSignature, $signature);
    }
}