<?php

/**
 * stPaymentPlugin configuration.
 * 
 * @package     stPaymentPlugin
 * @subpackage  config
 * @author      Scott Meves <scott@stereointeractive.com>
 * @version     SVN: $Id: PluginConfiguration.class.php 17207 2009-04-10 15:36:26Z Kris.Wallsmith $
 */
class stPaymentPluginConfiguration extends sfPluginConfiguration
{
  const VERSION = '1.0.0-DEV';

  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $this->dispatcher->connect('routing.load_configuration', array(__CLASS__, 'listenToRoutingLoadConfigurationEvent'));
  }
  
  static public function listenToRoutingLoadConfigurationEvent(sfEvent $event)
  {
    $r = $event->getSubject();
    $enabledModules = array_flip(sfConfig::get('sf_enabled_modules', array()));
    if (isset($enabledModules['authNetSubscriptionAdmin']))
    {
      $r->prependRoute('authNetSubscriptionAdmin', new sfDoctrineRouteCollection(array('name' => 'authNetSubscriptionAdmin',
        'model' => 'AuthNetSubscription',
        'module' => 'authNetSubscriptionAdmin',
        'prefix_path' => '/admin/payment/subscription',
        'column' => 'id',
        'with_wildcard_routes' => true)));
    }
  }
}
