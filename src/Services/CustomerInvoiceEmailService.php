<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Services\UnifiedEmailService;

/**
 * Customer Invoice Email Service
 * 
 * Reads a CSV file of customer invoices and sends reminder emails.
 */
class CustomerInvoiceEmailService {
    private $emailService;
    private $logger;
    private $csvPath;
    private $template;

    public function __construct($csvPath = null) {
        $this->logger = Logger::getInstance();
        $this->emailService = new UnifiedEmailService();
        $this->csvPath = $csvPath ?: __DIR__ . '/../../uploads/email_customer_invoice.csv';
        $this->template = $this->getDefaultTemplate();
    }

    /**
     * Send emails to all customers in the CSV file
     * 
     * @return array Results of the operation
     */
    public function sendEmails() {
        if (!file_exists($this->csvPath)) {
            $this->logger->error("CSV file not found: {$this->csvPath}");
            return [
                'success' => false,
                'error' => "CSV file not found: {$this->csvPath}"
            ];
        }

        $handle = fopen($this->csvPath, 'r');
        if (!$handle) {
            $this->logger->error("Failed to open CSV file: {$this->csvPath}");
            return [
                'success' => false,
                'error' => "Failed to open CSV file"
            ];
        }

        // Read header and handle BOM if present
        $header = fgetcsv($handle);
        if ($header) {
            // Remove BOM from the first element if it exists
            $header[0] = preg_replace('/^[\xEF\xBB\xBF\xFE\xFF]+/', '', $header[0]);
            // Trim whitespace from all headers
            $header = array_map('trim', $header);
        }

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $data = array_combine($header, array_map('trim', $row));
            $results['total']++;

            $emailResult = $this->sendInvoiceEmail($data);
            
            if ($emailResult['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = [
                'customer' => $data['Customer'] ?? 'Unknown',
                'email' => $data['Email'] ?? 'Unknown',
                'success' => $emailResult['success'],
                'error' => $emailResult['error'] ?? null
            ];
        }

        fclose($handle);

        $this->logger->info("Completed sending customer invoice emails", [
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * Send a single invoice email
     * 
     * @param array $data Row data from CSV
     * @return array Result of sending the email
     */
    public function sendInvoiceEmail($data) {
        $customer = $data['Customer'] ?? '';
        $email = $data['Email'] ?? '';
        $amountDue = number_format((float)str_replace(',', '', $data['Amount Due'] ?? '0'), 2);
        $salesOrderNumber = $data['Sales Order Number'] ?? '';
        $paypalUrl = $data['Paypal Invoice URL'] ?? '';
        $ccEmail = $data['cc_email'] ?? '';

        if (empty($email)) {
            return [
                'success' => false,
                'error' => 'Missing email address'
            ];
        }

        $subject = "Outstanding Balance Reminder - {$salesOrderNumber}";
        
        $htmlContent = $this->template;
        $htmlContent = str_replace('{{Customer}}', $customer, $htmlContent);
        $htmlContent = str_replace('{{Sales Order Number}}', $salesOrderNumber, $htmlContent);
        $htmlContent = str_replace('{{Amount Due}}', $amountDue, $htmlContent);
        $htmlContent = str_replace('{{PayPal Invoice URL}}', $paypalUrl, $htmlContent);

        $ccRecipients = [];
        if (!empty($ccEmail)) {
            $ccRecipients = array_map('trim', explode(',', $ccEmail));
        }

        return $this->emailService->sendEmail($subject, $htmlContent, [$email], false, $ccRecipients);
    }

    /**
     * Get the default HTML email template
     */
    private function getDefaultTemplate() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Outstanding Balance Reminder</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif; color:#222222;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f4; margin:0; padding:0;">
    <tr>
      <td align="center" style="padding:30px 15px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background-color:#ffffff; border-collapse:collapse; border:1px solid #dddddd;">
          
          <!-- Header -->
          <tr>
            <td align="center" style="background-color:#000000; padding:24px 20px;">
              <img 
                src="https://lagunatools.com/wp-content/uploads/2024/11/Laguna-LogoGrey.svg" 
                alt="Laguna Tools" 
                style="display:block; max-width:220px; width:100%; height:auto; margin:0 auto;"
              />
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 32px 24px 32px; font-size:16px; line-height:1.6; color:#222222;">
              <p style="margin:0 0 16px 0;">Dear {{Customer}},</p>

              <p style="margin:0 0 16px 0;">We hope you are doing well.</p>

              <p style="margin:0 0 20px 0;">
                This is a friendly reminder that there is an outstanding balance on the following order:
              </p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0; border-collapse:collapse;">
                <tr>
                  <td style="padding:12px 16px; background-color:#f8f8f8; border:1px solid #e5e5e5; font-size:15px; line-height:1.6;">
                    <strong>Sales Order #:</strong> {{Sales Order Number}}<br />
                    <strong>Outstanding Amount:</strong> ${{Amount Due}}
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 16px 0;">
                To complete your payment securely, please use the link below:
              </p>

              <p style="margin:0 0 24px 0;">
                <a 
                  href="{{PayPal Invoice URL}}" 
                  style="display:inline-block; background-color:#000000; color:#ffffff; text-decoration:none; padding:14px 22px; font-size:15px; font-weight:bold; border-radius:4px;"
                >
                  Pay Outstanding Balance
                </a>
              </p>

              <p style="margin:0 0 16px 0;">
                Or copy and paste this link into your browser:
              </p>

              <p style="margin:0 0 24px 0; word-break:break-all;">
                <a href="{{PayPal Invoice URL}}" style="color:#000000; text-decoration:underline;">{{PayPal Invoice URL}}</a>
              </p>

              <p style="margin:0 0 16px 0;">
                If payment has already been made, please disregard this notice. Otherwise, we kindly ask that you submit payment at your earliest convenience.
              </p>

              <p style="margin:0 0 16px 0;">
                If you have any questions regarding this balance or need assistance, please contact Laguna Tools Customer Service. Our team is happy to help clarify any details.
              </p>

              <p style="margin:0 0 16px 0;">
                Thank you for your prompt attention to this matter and for choosing Laguna Tools.
              </p>

              <p style="margin:0;">
                Sincerely,<br />
                Laguna Tools<br />
                Customer Service Team<br />
                <a href="tel:18002341976" style="color:#000000; text-decoration:none;">1-800-234-1976</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 32px; background-color:#f8f8f8; border-top:1px solid #e5e5e5; font-size:12px; line-height:1.6; color:#666666; text-align:center;">
              Please do not reply to this email. This mailbox is unmonitored and replies will not be received. If you need assistance, please contact Laguna Tools Customer Service at
              <a href="tel:18002341976" style="color:#666666; text-decoration:underline;">1-800-234-1976</a>.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}
