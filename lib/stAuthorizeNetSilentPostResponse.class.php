<?php

class stAuthorizeNetSilentPostResponse extends AuthorizeNetSIM
{
  public $subscription_id;
  public $subscription_paynum;
  public $test_request;
  
  public function isARB()
  {
    return ($this->subscription_id ? true : false);
  }
  
  public function getPaymentStatus()
  {
    if ($this->approved) {
      return 'approved';
    } elseif ($this->declined) {
      return 'declined';
    } elseif ($this->error) {
      return 'error';
    } elseif ($this->held) {
      return 'held';
    }
  }
  
  public function isTestRequest()
  {    
    if ($this->test_request === null) 
    {
      $this->test_request = false;
    } 
    elseif ($this->test_request !== true && $this->test_request !== false) 
    {
      $trues = array('true', 't', 'yes', 'y', 'on', '1');
      $falses = array('false', 'f', 'no', 'n', 'off', '0');

      if (in_array($value, $trues))
      {
        $this->test_request = true;
      }

      if (in_array($value, $falses))
      {
        $this->test_request = false;
      }
    }
    
    return $this->test_request;
  }
  
  public function getAuthNetTransaction()
  {
    $transaction = AuthNetTransaction::fromSilentPost($this);
    
    return $transaction;
  }
}
