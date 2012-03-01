<?php

class stAuthorizeNetSilentPostResponse extends AuthorizeNetSIM
{
  public $subscription_id;
  public $subscription_paynum;
  
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
}
