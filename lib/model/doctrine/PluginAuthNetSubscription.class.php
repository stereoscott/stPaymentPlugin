<?php

/**
 * PluginAuthNetSubscription
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class PluginAuthNetSubscription extends BaseAuthNetSubscription
{
  public static function fromARBSubscriptionAndResponse(AuthorizeNet_Subscription $subscription, AuthorizeNetARB_Response $response) 
  {
    $self = new AuthNetSubscription();
     
    $values = array(
      'subscription_id'     => $response->getSubscriptionId(),
      'name'                => $subscription->name,
      'status'              => 'active',                                      # active, expired, suspended, canceled, terminated
      'interval_length'     => $subscription->intervalLength,                                   
      'interval_unit'       => $subscription->intervalUnit,                  # days or months
      'start_date'          => $subscription->startDate,                      # YYYY-MM-DD
      'total_occurrences'   => $subscription->totalOccurrences,               # no end date = 9999, if trial specified this should include trial
      'trial_occurrences'   => $subscription->trialOccurrences,         
      'amount'              => $subscription->amount,                   
      'trial_amount'        => $subscription->trialAmount,              
      'payment_method'      => 'cc',                                          # CC or ECHECK
      'cc_last_four_digits' => self::getLastFour($subscription->creditCardCardNumber),
      'cc_expiration_date'  => null, //$subscription->creditCardExpirationDate.'-00', #sent as YYYY-MM, stored as YYYY-MM-00, but lets not store this for security
      'bank_account_name'   => $subscription->bankAccountNameOnAccount,       # full name of individual associated with bank account number
      'invoice_number'      => $subscription->orderInvoiceNumber,      
      'order_description'   => $subscription->orderDescription,       
      'customer_id'         => $subscription->customerId,                     # merchant-assigned identifier for the customer
      'customer_email'      => $subscription->customerEmail,              
      'customer_phone'      => $subscription->customerPhoneNumber,        
      'customer_fax'        => $subscription->customerFaxNumber,                  
      'bill_to_first_name'  => $subscription->billToFirstName,                 
      'bill_to_last_name'   => $subscription->billToLastName,          
      'bill_to_company'     => $subscription->billToCompany,                  
      'bill_to_address'     => $subscription->billToAddress,                  
      'bill_to_city'        => $subscription->billToCity,                        
      'bill_to_state'       => $subscription->billToState,                      
      'bill_to_zip'         => $subscription->billToZip,                          
      'bill_to_country'     => $subscription->billToCountry,                  
      'ship_to_first_name'  => $subscription->shipToFirstName,              
      'ship_to_last_name'   => $subscription->shipToLastName,          
      'ship_to_company'     => $subscription->shipToCompany,                  
      'ship_to_address'     => $subscription->shipToAddress,                  
      'ship_to_city'        => $subscription->shipToCity,                        
      'ship_to_state'       => $subscription->shipToState,                      
      'ship_to_zip'         => $subscription->shipToZip,                          
      'ship_to_country'     => $subscription->shipToCountry,
    );
    
    $self->fromArray($values);
    
    return $self;                                        
  }
  
  public function getAuthNetSubscriptionApiObject($additionalFields = null, $modifiedOnly = false)
  {
    $subscription = new AuthorizeNet_Subscription;
    
    $fieldMap = array(
      // field_name => AuthNetSubscription API name
      'name'               =>      'name',
      'interval_length'    =>      'intervalLength',
      'interval_unit'      =>      'intervalUnit',
      'start_date'         =>      'startDate',
      'total_occurrences'  =>      'totalOccurrences',
      'trial_occurrences'  =>      'trialOccurrences',
      'amount'             =>      'amount',
      'trial_amount'       =>      'trialAmount',
      'cc_expiration_date' =>      'creditCardExpirationDate',
      'bank_account_name'  =>      'bankAccountNameOnAccount',
      'invoice_number'     =>      'orderInvoiceNumber',
      'order_description'  =>      'orderDescription',
      'customer_id'        =>      'customerId',
      'customer_email'     =>      'customerEmail',
      'customer_phone'     =>      'customerPhoneNumber',
      'customer_fax'       =>      'customerFaxNumber',
      'bill_to_first_name' =>      'billToFirstName',
      'bill_to_last_name'  =>      'billToLastName',
      'bill_to_company'    =>      'billToCompany',
      'bill_to_address'    =>      'billToAddress',
      'bill_to_city'       =>      'billToCity',
      'bill_to_state'      =>      'billToState',
      'bill_to_zip'        =>      'billToZip',
      'bill_to_country'    =>      'billToCountry',
      'ship_to_first_name' =>      'shipToFirstName',
      'ship_to_last_name'  =>      'shipToLastName',
      'ship_to_company'    =>      'shipToCompany',
      'ship_to_address'    =>      'shipToAddress',
      'ship_to_city'       =>      'shipToCity',
      'ship_to_state'      =>      'shipToState',
      'ship_to_zip'        =>      'shipToZip',
      'ship_to_country'    =>      'shipToCountry'
    );
    
    if ($modifiedOnly) {
      $modified = $this->getModified();
      foreach ($modified as $modifiedFieldName => $modifiedValue) {
        $subscriptionFieldName = $fieldMap[$modifiedFieldName];
        $subscription->$subscriptionFieldName = $modifiedValue;
      }
    } else {
      foreach ($fields as $localFieldName => $apiFieldName) {
        $subscription->$apiFieldName = $this[$localFieldName];
      }
    }

    // $subscription->creditCardCardNumber     = null;
    // $subscription->creditCardCardCode       = null;
    // $subscription->bankAccountAccountType   = null;
    // $subscription->bankAccountRoutingNumber = null;
    // $subscription->bankAccountAccountNumber = null;    
    // $subscription->bankAccountEcheckType    = null;
    // $subscription->bankAccountBankName      = null;
    
    if (is_array($additionalFields)) {
      foreach ($additionalFields as $key => $value) {
        $subscription->key = $value;
      }
    }
    
    return $subscription;
  }
  
  protected static function getLastFour($cc)
  {
    return substr(preg_replace('([^0-9])', '', $cc), -4);
  }
  
  protected function getPaymentProcessor()
  {
    if (!defined('AUTHORIZENET_SANDBOX')) {
      define('AUTHORIZENET_SANDBOX', sfConfig::get('app_stPayment_sandbox'));
    }
    
    if (!defined('AUTHORIZENET_LOG_FILE') && sfConfig::get('sf_logging_enabled')) {
      define('AUTHORIZENET_LOG_FILE', sfConfig::get('sf_log_dir').'/authorizenet_'.sfConfig::get('sf_environment').'.log');
    }
    
    $processor = stAuthorizeNet::getInstance();
    
    if ($merchantAccountId = $this->getMerchantAccountId()) {
      $processor->setMerchantAccountId($merchantAccountId);
    } else {
      if ($merchantAccountConfigKey = sfConfig::get('app_stPayment_default')) {
        $merchantAccountId = Doctrine::getTable('MerchantAccount')->selectIdFromKey($merchantAccountConfigKey);
        if ($merchantAccountId) {
          $processor->setMerchantAccountId($merchantAccountId);
        }
      }
    }
    
    return $processor;
  }
  
  public function updateStatusUsingAuthNet(&$error = false)
  {
    
    $processor = $this->getPaymentProcessor();
    
    $arbRequest = new AuthorizeNetARB($processor->getUsername(), $processor->getPassword());
    
    $response = $arbRequest->getSubscriptionStatus($this->getSubscriptionId());
  
    if ($response->isOk()) {
      $this->setStatus($response->getSubscriptionStatus());
      $this->save();
      return true;
    } else {
      $error = $response->getErrorMessage();
      return false;
    }
  }
  
  public function cancelSubscription(&$error = false){
    $processor = $this->getPaymentProcessor();
    
    $arbRequest = new AuthorizeNetARB($processor->getUsername(), $processor->getPassword());
    
    $response = $arbRequest->cancelSubscription($this->getSubscriptionId());
  
    if ($response->isOk()) {
      $this->setStatus($response->getSubscriptionStatus());
      $this->save();
      return true;
    } else {
      $error = $response->getErrorMessage();
      return false;
    }
  }
  

    
  public function doApiUpdate(&$error = false, $additionalFields = null)
  {
    $processor = $this->getPaymentProcessor();
    $updateRequest = new AuthorizeNetARB($processor->getUsername(), $processor->getPassword());    
    
    $subscription = $this->getAuthNetSubscriptionApiObject($additionalFields, $modifiedOnly = true);
    $response = $updateRequest->updateSubscription($this->getSubscriptionId(), $subscription);
    
    if ($response->isOk()) {
      return true;
    } else {
      $error = $response->getErrorMessage();
      return false;
    }
  }
  
}