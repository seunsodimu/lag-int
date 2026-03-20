<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\PayPalService;
use Laguna\Integration\Services\EnhancedEmailService;
use Laguna\Integration\Services\CustomerInvoiceEmailService;
use Laguna\Integration\Utils\Logger;

class PayPalController {
    private $netsuiteService;
    private $paypalService;
    private $emailService;
    private $customerinvoiceemailservice;
    private $logger;

    public function __construct() {
        $this->netsuiteService = new NetSuiteService();
        $this->paypalService = new PayPalService();
        $this->emailService = new EnhancedEmailService();
        $this->customerinvoiceemailservice = new CustomerInvoiceEmailService();
        $this->logger = Logger::getInstance();
    }

    /**
     * Handle the request to update Sales Orders with PayPal links
     */
    public function updateSalesOrdersWithPayPalLinks() {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderIds = $input['order_ids'] ?? null;

        if (!$orderIds) {
            $this->sendResponse(false, 'Missing order_ids in request body', [], 400);
            return;
        }

        // Convert single ID to array
        if (!is_array($orderIds)) {
            $orderIds = [$orderIds];
        }

        $results = [];
        foreach ($orderIds as $orderId) {
            $results[] = $this->processOrder($orderId);
        }

        // Send summary report email
        $this->sendPaymentLinkReportEmail($results);

        $this->sendResponse(true, 'Processing complete', $results);
    }

    /**
     * Handle the request to create PayPal Invoices from Sales Orders
     */
    public function createInvoicesForSalesOrders() {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderIds = $input['order_ids'] ?? null;
        $environment = $input['environment'] ?? null; // 'sandbox' or 'production'
        $sendToInvoicer = $input['send_to_invoicer'] ?? false;
        $sendToRecipient = $input['send_to_recipient'] ?? true;

        if (!$orderIds) {
            $this->sendResponse(false, 'Missing order_ids in request body', [], 400);
            return;
        }

        // Convert single ID to array
        if (!is_array($orderIds)) {
            $orderIds = [$orderIds];
        }

        // If environment is provided, re-initialize PayPalService with that environment
        if ($environment) {
            $this->paypalService = new PayPalService($environment);
        }

        $results = [];
        foreach ($orderIds as $orderId) {
            $results[] = $this->processInvoice($orderId, $sendToInvoicer, $sendToRecipient);
        }

        // Send summary report email
        $this->sendReportEmail($results);
        $newheadmessage = $results['invoice_status']." Check integration logs";

        $this->sendResponse(true, $newheadmessage, $results);
    }

    /**
     * Process a single Sales Order for Invoice creation
     */
    private function processInvoice($orderId, $sendToInvoicer = false, $sendToRecipient = true) {
        $this->logger->info("Processing PayPal invoice for Sales Order: $orderId");

        $result = [
            'order_id' => $orderId,
            'tran_id' => 'N/A',
            'success' => false,
            'invoice_status' => 'Pending',
            'invoice_error' => null,
            'netsuite_status' => 'Pending',
            'netsuite_error' => null,
            'invoice_id' => 'N/A',
            'invoice_number' => 'N/A',
            'invoice_url' => 'N/A',
            'sent' => false
        ];

        try {
            // 1. Get Sales Order from NetSuite
            $order = $this->netsuiteService->getSalesOrderById($orderId);
            
            if (!$order) {
                $result['error'] = 'Sales Order not found in NetSuite';
                $result['invoice_status'] = 'Error';
                $result['invoice_error'] = 'Sales Order not found';
                return $result;
            }

            $result['tran_id'] = $order['tranId'] ?? 'N/A';
            
            // get sales rep details
            $salesRepId = $order['salesRep']['id'];
            $salesrep = $this->netsuiteService->getEmployeeById($salesRepId);
            $salesrep_email = $salesrep['email'] ?? 'web_dev@lagunatools.com';
            
            // Check if Sales Order already has a PayPal invoice URL
            $existingInvoiceUrl = $order['custbody_paypal_invoice_url'] ?? null;
            
            if ($existingInvoiceUrl) {
                $this->logger->info("Sales Order $orderId already has a PayPal invoice URL: $existingInvoiceUrl. Skipping creation and update.");
                
                $result['invoice_status'] = 'Skipped (Already exists)';
                $result['netsuite_status'] = 'Skipped';
                $result['invoice_url'] = $existingInvoiceUrl;
                $result['success'] = true;
                
                // Send email to customer and cc salesrep
                $send_data = [
                    'Customer' => $order['entity']['refName'] ?? '',
                    'Email' => $order['email'] ?? '',
                    'Amount Due' => $order['total'] ?? '',
                    'Sales Order Number' => $order['tranId'],
                    'Paypal Invoice URL' => $existingInvoiceUrl,
                    'cc_email' => $salesrep_email
                ];
                
                $this->logger->info("Sending invoice email for existing URL to customer: " . ($send_data['Email'] ?? 'Unknown'));
                $this->customerinvoiceemailservice->sendInvoiceEmail($send_data);
                
                return $result;
            }

            // 2. Create PayPal invoice
            $invoice = $this->paypalService->createInvoice($order);

            if (!$invoice) {
                $result['error'] = 'Failed to create PayPal invoice';
                $result['invoice_status'] = 'Error';
                $result['invoice_error'] = 'PayPal service returned null';
                return $result;
            }

            $invoiceId = $invoice['id'] ?? 'N/A';
            $result['invoice_id'] = $invoiceId;
            $result['invoice_number'] = $invoice['detail']['invoice_number'] ?? 'N/A';

            if ($invoiceId === 'N/A') {
                $result['error'] = 'Invoice created but ID missing in response';
                $result['invoice_status'] = 'Error';
                $result['invoice_error'] = 'Missing ID in PayPal response';
                return $result;
            }

            $result['invoice_status'] = 'Success';

            // 3. Send PayPal invoice
            $sendResult = $this->paypalService->sendInvoice($invoiceId, $sendToInvoicer, $sendToRecipient);
            $result['sent'] = $sendResult;
            
            if (!$sendResult) {
                $this->logger->warning("Failed to send PayPal invoice $invoiceId, but will still update NetSuite", [
                    'order_id' => $orderId
                ]);
            }

            // 4. Update NetSuite Sales Order
            $invoiceId = str_replace("INV2", "", $invoiceId);
            $invoiceId = str_replace("-", "", $invoiceId);
            $invoiceUrl = "https://www.paypal.com/invoice/p/#" . $invoiceId;
            $result['invoice_url'] = $invoiceUrl;
            
            $updateData = [
                'custbody_paypal_invoice_url' => $invoiceUrl
            ];

            // Add terms if not populated (ID 18)
            if (empty($order['terms'])) {
                $updateData['terms'] = ['id' => 18];
                $this->logger->info("Adding default terms (ID: 18) to Sales Order $orderId update");
            }

            // Add custbodyship_immediate if not populated (ID 2)
            if (empty($order['custbodyship_immediate'])) {
                $updateData['custbodyship_immediate'] = ['id' => 2];
                $this->logger->info("Adding default custbodyship_immediate (ID: 2) to Sales Order $orderId update");
            }

        $nsUpdateResult = $this->netsuiteService->updateSalesOrder($orderId, $updateData);
        // send email to customer and cc salesrep
        $send_data['Customer'] = $order['entity']['refName'] ?? '';
        $send_data['Email'] = $order['email']?? '';
        $send_data['Amount Due'] = $order['total'] ?? '';
        $send_data['Sales Order Number'] =$order['tranId'];
        $send_data['Paypal Invoice URL'] = $invoiceUrl;
        $send_data['cc_email'] = $salesrep_email;
        
        $this->logger->info("Sending invoice email for new URL to customer: " . ($send_data['Email'] ?? 'Unknown'));
        $sendinginvoice = $this->customerinvoiceemailservice->sendInvoiceEmail($send_data);
                        


            if ($nsUpdateResult['success']) {
                $result['netsuite_status'] = 'Success';
                $result['success'] = true;

            } else {
                $result['netsuite_status'] = 'Error';
                $result['netsuite_error'] = $nsUpdateResult['error'] ?? 'Unknown error';
                $this->logger->error("Failed to update NetSuite Sales Order $orderId with invoice URL", [
                    'error' => $nsUpdateResult['error'] ?? 'Unknown error'
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Error processing PayPal invoice for Order $orderId", [
                'error' => $e->getMessage()
            ]);
            $result['error'] = $e->getMessage();
            if ($result['invoice_status'] === 'Pending') {
                $result['invoice_status'] = 'Error';
                $result['invoice_error'] = $e->getMessage();
            } else if ($result['netsuite_status'] === 'Pending') {
                $result['netsuite_status'] = 'Error';
                $result['netsuite_error'] = $e->getMessage();
            }
            return $result;
        }
    }

    /**
     * Send summary report email
     */
    private function sendReportEmail($results) {
        $subject = "PayPal Invoice Creation Report - " . date('Y-m-d H:i:s');
        
        $html = "<h2>PayPal Invoice Processing Report</h2>";
        $html .= "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        $html .= "<tr style='background-color: #f2f2f2;'>
                    <th>SO ID</th>
                    <th>SO Number</th>
                    <th>Invoice Creation</th>
                    <th>NetSuite Update</th>
                    <th>Invoice Details</th>
                  </tr>";
        
        foreach ($results as $result) {
            $invoiceCreationStatus = "<b>" . $result['invoice_status'] . "</b>";
            if ($result['invoice_error']) {
                $invoiceCreationStatus .= "<br><small style='color: red;'>" . $result['invoice_error'] . "</small>";
            }

            $netsuiteUpdateStatus = "<b>" . $result['netsuite_status'] . "</b>";
            if ($result['netsuite_error']) {
                $netsuiteUpdateStatus .= "<br><small style='color: red;'>" . $result['netsuite_error'] . "</small>";
            }

            $invoiceDetails = "ID: " . $result['invoice_id'] . "<br>";
            $invoiceDetails .= "Num: " . $result['invoice_number'] . "<br>";
            if ($result['invoice_url'] !== 'N/A') {
                $invoiceDetails .= "<a href='" . $result['invoice_url'] . "'>View Invoice</a>";
            }

            $html .= "<tr>";
            $html .= "<td>" . $result['order_id'] . "</td>";
            $html .= "<td>" . $result['tran_id'] . "</td>";
            $html .= "<td>" . $invoiceCreationStatus . "</td>";
            $html .= "<td>" . $netsuiteUpdateStatus . "</td>";
            $html .= "<td>" . $invoiceDetails . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</table>";
        
        $recipients = ['web_dev@lagunatools.com'];
        $this->emailService->sendEmail($subject, $html, $recipients);
    }

    /**
     * Process a single Sales Order
     */
    private function processOrder($orderId) {
        $this->logger->info("Processing PayPal link for Sales Order: $orderId");

        $result = [
            'order_id' => $orderId,
            'tran_id' => 'N/A',
            'success' => false,
            'paypal_status' => 'Pending',
            'paypal_error' => null,
            'netsuite_status' => 'Pending',
            'netsuite_error' => null,
            'payment_link' => 'N/A'
        ];

        try {
            // 1. Get Sales Order from NetSuite
            $order = $this->netsuiteService->getSalesOrderById($orderId);
            
            if (!$order) {
                $result['paypal_status'] = 'Error';
                $result['paypal_error'] = 'Sales Order not found';
                return $result;
            }

            $tranId = $order['tranId'] ?? $orderId;
            $result['tran_id'] = $tranId;
            $totalAmount = $order['total'] ?? 0;

            if ($totalAmount <= 0) {
                $result['paypal_status'] = 'Error';
                $result['paypal_error'] = 'Order total amount is 0 or negative';
                return $result;
            }

            // 2. Generate PayPal link
            $paymentLink = $this->paypalService->createPaymentLink([
                'tranId' => $tranId,
                'totalAmount' => $totalAmount
            ]);

            if (!$paymentLink) {
                $result['paypal_status'] = 'Error';
                $result['paypal_error'] = 'Failed to generate PayPal payment link';
                return $result;
            }

            $result['paypal_status'] = 'Success';
            $result['payment_link'] = $paymentLink;

            // 3. Update NetSuite Sales Order
            $updateData = [
                'custbody_paypal_payment_url' => $paymentLink
            ];

            // Add terms if not populated (ID 18)
            if (empty($order['terms'])) {
                $updateData['terms'] = ['id' => 18];
                $this->logger->info("Adding default terms (ID: 18) to Sales Order $orderId update (payment link)");
            }

            // Add custbodyship_immediate if not populated (ID 2)
            if (empty($order['custbodyship_immediate'])) {
                $updateData['custbodyship_immediate'] = ['id' => 2];
                $this->logger->info("Adding default custbodyship_immediate (ID: 2) to Sales Order $orderId update (payment link)");
            }

            $updateResult = $this->netsuiteService->updateSalesOrder($orderId, $updateData);

            if ($updateResult['success']) {
                $result['netsuite_status'] = 'Success';
                $result['success'] = true;
            } else {
                $result['netsuite_status'] = 'Error';
                $result['netsuite_error'] = $updateResult['error'] ?? 'Unknown error';
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Error processing PayPal link for Order $orderId", [
                'error' => $e->getMessage()
            ]);
            if ($result['paypal_status'] === 'Pending') {
                $result['paypal_status'] = 'Error';
                $result['paypal_error'] = $e->getMessage();
            } else if ($result['netsuite_status'] === 'Pending') {
                $result['netsuite_status'] = 'Error';
                $result['netsuite_error'] = $e->getMessage();
            }
            return $result;
        }
    }

    /**
     * Send summary report email for payment links
     */
    private function sendPaymentLinkReportEmail($results) {
        $subject = "PayPal Payment Link Generation Report - " . date('Y-m-d H:i:s');
        
        $html = "<h2>PayPal Payment Link Processing Report</h2>";
        $html .= "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        $html .= "<tr style='background-color: #f2f2f2;'>
                    <th>SO ID</th>
                    <th>SO Number</th>
                    <th>PayPal Link Generation</th>
                    <th>NetSuite Update</th>
                    <th>Payment Link</th>
                  </tr>";
        
        foreach ($results as $result) {
            $paypalStatus = "<b>" . $result['paypal_status'] . "</b>";
            if ($result['paypal_error']) {
                $paypalStatus .= "<br><small style='color: red;'>" . $result['paypal_error'] . "</small>";
            }

            $netsuiteStatus = "<b>" . $result['netsuite_status'] . "</b>";
            if ($result['netsuite_error']) {
                $netsuiteStatus .= "<br><small style='color: red;'>" . $result['netsuite_error'] . "</small>";
            }

            $html .= "<tr>";
            $html .= "<td>" . $result['order_id'] . "</td>";
            $html .= "<td>" . $result['tran_id'] . "</td>";
            $html .= "<td>" . $paypalStatus . "</td>";
            $html .= "<td>" . $netsuiteStatus . "</td>";
            $html .= "<td>" . ($result['payment_link'] !== 'N/A' ? "<a href='" . $result['payment_link'] . "'>PayPal Link</a>" : 'N/A') . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</table>";
        
        $recipients = ['web_dev@lagunatools.com'];
        $this->emailService->sendEmail($subject, $html, $recipients);
    }

    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $data = [], $statusCode = 200) {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
    }
}
