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
  
  public function isAuthorizeNet()
  {
    
    if(!count($_POST)){
      sfContext::getInstance()->getLogger()->debug('Recieved SilentPost with empty POST in isAuthorizeNet() of stAuthorizeNetSilentPostResponse at line: '.__LINE__);
    } elseif(!$this->md5_hash) {
      sfContext::getInstance()->getLogger()->err('Recieved SilentPost with empty md5_hash in isAuthorizeNet() of stAuthorizeNetSilentPostResponse at line: '.__LINE__);
    } elseif($this->generateHash() != $this->md5_hash && !$this->checkAllProcessorHashes()){
      //TODO: Must generateHash for all the possible accounts

      sfContext::getInstance()->getLogger()->crit('Recieved SilentPost with BAD md5_hash in isAuthorizeNet() of stAuthorizeNetSilentPostResponse at line: '.__LINE__);
    }
    //parent returns:
    //return count($_POST) && $this->md5_hash && ($this->generateHash() == $this->md5_hash);
    return parent::isAuthorizeNet();
    
  }
  
  public function checkAllProcessorHashes(){
    //first step is to get a DB list
    $merchantAccounts = sfConfig::get('app_stPayment_merchantAccount', array());
    foreach ($merchantAccounts as $key => $merchantAccount) {
      //$merchantAccount['login']
      //$merchantAccount['key'];

      //TODO Do we neet to find other md5_settings someplace

      $amount = ($this->amount ? $this->amount : "0.00");
      if(strtoupper(md5($this->md5_setting . $merchantAccount['login'] . $this->transaction_id . $amount))){

      }
    }
  }

  public function generateHash()
  {
    //parent does:
    sfContext::getInstance()->getLogger()->debug('Using api_login_id of '.$this->api_login_id.' to generate md5_hash in generateHash of stAuthorizeNetSilentPostResponse at line: '.__LINE__);
    
    //$amount = ($this->amount ? $this->amount : "0.00");
    //return strtoupper(md5($this->md5_setting . $this->api_login_id . $this->transaction_id . $amount));
    return parent::generateHash();
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
