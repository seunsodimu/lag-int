<?php

namespace Laguna\Integration\Exceptions;

/**
 * Exception thrown when a store customer is not found for Store Shipment orders
 */
class StoreCustomerNotFoundException extends \Exception
{
    private $orderId;
    private $customerEmail;
    
    public function __construct($orderId, $customerEmail, $message = "", $code = 0, \Throwable $previous = null)
    {
        $this->orderId = $orderId;
        $this->customerEmail = $customerEmail;
        
        if (empty($message)) {
            $message = "Store customer not found for Store Shipment order. Email: {$customerEmail}";
        }
        
        parent::__construct($message, $code, $previous);
    }
    
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    public function getCustomerEmail()
    {
        return $this->customerEmail;
    }
}