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
      'cc_expiration_date'  => $subscription->creditCardExpirationDate.'-00', #sent as YYYY-MM, stored as YYYY-MM-00
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
  
  protected static function getLastFour($cc)
  {
    return substr(preg_replace('([^0-9])', '', $cc), -4);
  }
}