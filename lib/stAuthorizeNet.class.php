<?php

class stAuthorizeNet extends stPaymentProcessor
{  
  
  protected $merchantAccountId; // if you store merchant accounts in your db
  
  public static function getInstance($username = null, $password = null, $options = array())
  {
    $key = (null === $username) ? '___DEFAULT_INSTANCE___' : $username;
    
    if (!isset(self::$instances[$key])) {
      return new self($username, $password, $options);
    } else {
      return self::$instances[$key];
    }
    
  }
  
  public static function getLogFilePath()
  {
    if (sfConfig::get('sf_logging_enabled')) {
      return sfConfig::get('sf_log_dir').'/authorizenet_'.sfConfig::get('sf_environment').'.log';
    } else {
      return false;
    }
  }
  
  /**
   * Set up a form option to store the payment gateway API credentials.
   * Note that SB does not currently have the concept of $site objects. This
   * is just a carry over from the Consumer application.
   *
   * @param mixed $cobrand False if no cobrand, otherwise an instance of Cobrand
   * @param mixed $site False if no site, otherwise an instance of Site
   * @return void
   */
  public function initMerchantAccountCredentials($cobrand = null, $site = null)
  {
    $merchantAccountConfigKey = false;
    
    if ($cobrand && $cobrand['merchant_account_id']) {
      $this->merchantAccountId = $cobrand['merchant_account_id'];
      $merchantAccountConfigKey = $cobrand['MerchantAccount']['config_key'];
    } elseif ($site && $site['merchant_account_id']) {
      $this->merchantAccountId = $site['merchant_account_id'];
      $merchantAccountConfigKey = $site['MerchantAccount']['config_key'];
    }
    
    if (!$merchantAccountConfigKey) {
      $merchantAccountConfigKey = sfConfig::get('app_stPayment_default', 'idprotection');
      $merchantAccountId = Doctrine::getTable('MerchantAccount')->selectIdFromKey($merchantAccountConfigKey);
      $this->merchantAccountId = $merchantAccountId;
    }
  
    if (!$this->merchantAccountId && ($logger = sfContext::getInstance()->getLogger())) {
      $logger->err('Missing merchant account ID for key: '.$merchantAccountConfigKey);
    }

    $this->initUsernameAndPasswordUsingConfigKey($merchantAccountConfigKey);
  }
  
  public function setMerchantAccountId($merchantAccountId = null)
  {
    if ($merchantAccountId === null) {
      $this->merchantAccountId = null;
      $this->setUsername(null);
      $this->setPassword(null);
      return;
    }
    
    $merchantAccount = Doctrine::getTable('MerchantAccount')->find($merchantAccountId);
    if (!$merchantAccount) {
      throw new sfException($merchantAccountId.' is not a valid merchant account id');
    }

    $merchantAccounts = sfConfig::get('app_stPayment_merchantAccount', array());
    $merchantAccountConfigKey = false;
    
    $this->merchantAccountId = $merchantAccountId;
    $this->initUsernameAndPasswordUsingConfigKey($merchantAccount['config_key']);
  }
  
  protected function initUsernameAndPasswordUsingConfigKey($configKey)
  {
    // which merchant account do we use?
    $merchantAccounts = sfConfig::get('app_stPayment_merchantAccount', array());

    if (isset($merchantAccounts[$configKey])) {
      $credentials = $merchantAccounts[$configKey];
      $this->setUsername($credentials['login']);
      $this->setPassword($credentials['key']);
    } else {
      throw new Exception("No Auth.Net Credentials for $configKey. Check app.yml.");
    }
  }
  /**
   * Return an array in the form: array('login' => 'xxx', 'key' => xxx)
   *
   * @return integer $merchantAccountId
   * @throws Exception Error if you call this without initializing the merch account credentials
   */
  public function getMerchantAccountId()
  {     
    if (null === $this->merchantAccountId) {
      throw new Exception('You must call initMerchantAccountCredentials before calling '.__FUNCTION__);
    }

    return $this->merchantAccountId;
  }
  
} 