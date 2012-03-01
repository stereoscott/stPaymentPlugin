<?php
/**
 * Abandoned. Maybe we'll create an interface to create a truly flexible payment module.
 *
 * @package default
 */
class stPaymentProcessor {
  
  private 
    $gatewayUrl,
    $username,
    $password,
    $version,
    $testMode = false;
  
  private function getGatewayUrl()
  {
    return $this->gatewayUrl;
  }
  
  private function setGatewayUrl($gatewayUrl)
  {
    $this->gatewayUrl = $gatewayUrl;
  }
  
  private function getUsername()
  {
    return $this->username;
  }
  
  private function setUsername($username)
  {
    $this->username = $username;
  }
  
  private function getPassword()
  {
    return $this->password;
  }
  
  private function setPassword($password)
  {
    $this->password = $password;
  }
  
  private function getVersion()
  {
    return $this->version;
  }
  
  private function setVersion($version)
  {
    $this->version = $version;
  }
  
  private function getTestMode()
  {
    return (boolean) $this->testMode;
  }
  
  private function setTestMode($testMode)
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