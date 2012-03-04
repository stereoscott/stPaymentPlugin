<?php
/**
 * Abandoned. Maybe we'll create an interface to create a truly flexible payment module.
 *
 * @package default
 */
class stPaymentProcessor {
  
  public static $instances = array();
  
  private 
    $gatewayUrl,
    $username,
    $password,
    $version,
    $testMode = false;
    
  protected function getGatewayUrl()
  {
    return $this->gatewayUrl;
  }
  
  protected function setGatewayUrl($gatewayUrl)
  {
    $this->gatewayUrl = $gatewayUrl;
  }
  
  public function getUsername()
  {
    return $this->username;
  }
  
  public function setUsername($username)
  {
    $this->username = $username;
  }
  
  public function getPassword()
  {
    return $this->password;
  }
  
  public function setPassword($password)
  {
    $this->password = $password;
  }
  
  protected function getVersion()
  {
    return $this->version;
  }
  
  protected function setVersion($version)
  {
    $this->version = $version;
  }
  
  protected function getTestMode()
  {
    return (boolean) $this->testMode;
  }
  
  protected function setTestMode($testMode)
  {
    $this->testMode = (boolean) $testMode;
  }
  
  public function __construct($username, $password = null, $options = array())
  {
    $this->setUsername($username);
    
    if (isset($password)) $this->setPassword($password);
    
    if (isset($options['version'])) $this->setVersion($options['version']);
    
    if (isset($options['url'])) $this->setGatewayUrl($options['url']);

    if (isset($options['test'])) $this->setTestMode($options['test']);
    
    /*
    if (isset($options['hash'])) $this->setAuthorizeHash($options['hash']);
    if (isset($options['method'])) $this->setAuthorizeMethod($options['method']);
    if (isset($options['type'])) $this->setAuthorizeType($options['type']);
    */
  }
  
  
  
  
}