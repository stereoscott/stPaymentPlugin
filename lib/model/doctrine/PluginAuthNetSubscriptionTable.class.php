<?php

/**
 * PluginAuthNetSubscriptionTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class PluginAuthNetSubscriptionTable extends Doctrine_Table
{
    /**
     * Returns an instance of this class.
     *
     * @return object PluginAuthNetSubscriptionTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('PluginAuthNetSubscription');
    }
    
    public function retrieveAdminList(Doctrine_Query $q)
    {
      $alias = $q->getRootAlias();
      $q->leftJoin($alias.'.Customer c');
      
      return $q;
    }
}