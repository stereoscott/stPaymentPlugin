<?php

class BasestPaymentBaseForm extends sfForm
{
  protected $amount, $orderNumber, $orderDescription;
  
  /**
   * Given the bound/cleaned form values, return an array of fields
   * for use with an AuthorizeNetAIM object.
   *
   * @param float $amount   The amount of the transaction
   * @return array $fields  An array containing amount, credit card and billing details
   */
  protected function getTransactionFields($amount = null)
  {
    
    $values = $this->getValues();
        
    if (null === $amount) {
      $amount = $this->getAmount();
    }
    
    $fields = array(
      'amount'                          => number_format($amount, 2),
      'card_num'                        => preg_replace('/[^0-9]/', '', $values['acct']),
      'exp_date'                        => date('my', strtotime($values['exp'])),
      'card_code'                       => $values['cvv2'],
      'first_name'                      => substr($values['fname'], 0, 50),
      'last_name'                       => substr($values['lname'], 0, 50),
      'email'                           => substr($this->getOption('billingEmail'), 0, 255),
      'address'                         => substr($values['street'], 0, 60),
      'city'                            => substr($values['city'], 0, 40),
      'state'                           => $values['state'], // ARB requires two-letter state
      'zip'                             => substr($values['zip'], 0, 20),
      'country'                         => substr($values['country'], 0, 60),
      'customer_ip'                     => $_SERVER['REMOTE_ADDR'],
      'cust_id'                         => substr(htmlentities($this->getAffiliateCode()), 0, 20), //$this->getOption('customerId') 20 chars max
      'description'                     => $this->getOrderDescription(),
      'invoice_num'                     => $this->getOrderNumber(),
      'email_customer'                  => 0
    );
        
    return $fields;
  }
  
  /**
   * Amount due at time of the transaction (includes trial price for trial offers).
   *
   * @param string $amount 
   * @return void
   */
  public function setAmount($amount)
  {
    $this->amount = $amount;
  }
  
  public function getAmount()
  {
    return $this->amount;
  }
 
  public function getOrderNumber() 
  {
    return $this->orderNumber; // $this->getAffiliateCode()...
  }

  public function setOrderNumber($orderNumber) 
  {
    $this->orderNumber = $orderNumber;
  }
  
  public function getOrderDescription() 
  {
    return substr($this->orderDescription, 0, 255);
  }

  public function setOrderDescription($orderDescription) 
  {
    $this->orderDescription = $orderDescription;
  }
  
  public function setAffiliateCode($v)
  {
    $this->setOption('affiliate_code', $v);
  }

  // is set to cust_id which has a limit of 20 chars
  public function getAffiliateCode()
  {
    return $this->getOption('affiliate_code', 'IFI');
  }  
  
  
}