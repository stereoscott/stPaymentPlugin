<?php

class stAuthorizeNet extends stPaymentProcessor
{
  private $instance;
  
  public static function getLogFilePath()
  {
    if (sfConfig::get('sf_logging_enabled')) {
      return sfConfig::get('sf_log_dir').'/authorizenet_'.sfConfig::get('sf_environment').'.log';
    } else {
      return false;
    }
  }
} 