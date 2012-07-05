<?php

/**
 * This is a client facing form that allows them to update their billing subscription.
 * This could potentiall be refactored to combine with the admin AuthNetSubscription form, 
 * but this deals with Customer Subscriptions
 * @package stPaymentPlugin
 * @author Scott Meves
 */
class BasestPaymentBillingProfileForm extends BasestPaymentBaseForm
{
  
  public function setup()
  {
    $paymentForm = new stPaymentForm();
    $this->mergeForm($paymentForm);
    
    $this->widgetSchema['subscription_id'] = new sfWidgetFormInputHidden();
    $this->validatorSchema['subscription_id'] = new sfValidatorCallback(array('callback' => array($this, 'validateSubscriptionId')));
    
    $this->widgetSchema->setHelp('fname', null);
    $this->widgetSchema->setNameFormat('payment[%s]');
  }
  
  public function validateSubscriptionId($validator, $value)
  {
    $customer = $this->getOption('customer');
    if (!$customer) {
      throw new sfValidatorError($validator, 'Could not validate the subscription id (customer not found)');
    }
    
    $valid = $this->getAuthNetSubscription($value);
    
    if (!$valid) {
      throw new sfValidatorError($validator, 'Invalid subscription id: '.$value.' '.$this->getCustomer()->getId());
    }
    
    return $value;
  }
  
  public function setCustomer(Customer $customer)
  {
    $this->setOption('customer', $customer);
  }
  
  public function initDefaultsWithAuthNetSubscription(AuthNetSubscription $authNetSubscription)
  {
    $this->setAuthNetSubscription($authNetSubscription);
    
    $this->setDefaults(array_merge($this->getDefaults(), array(
      'subscription_id' => $authNetSubscription['subscription_id'],
      'fname'   => $authNetSubscription['bill_to_first_name'],
      'lname'   => $authNetSubscription['bill_to_last_name'],
      'street'  => $authNetSubscription['bill_to_address'],
      'city'    => $authNetSubscription['bill_to_city'],
      'state'   => $authNetSubscription['bill_to_state'],
      'zip'     => $authNetSubscription['bill_to_zip'],
      'country' => $authNetSubscription['bill_to_country'],
    )));
  }
  
  public function getPaymentProcessor()
  {
    return stAuthorizeNet::getInstance();
  }
  
  public function initMerchantAccountCredentials(CustomerSubscription $subscription)
  {
    // the merchant account is stored in the purchase line item
    $merchantAccountId = $subscription['Purchase']['merchant_account_id'];
    
    if ($merchantAccountId) {
      // init the merchant account credentials based on the subscription
      $this->getPaymentProcessor()->setMerchantAccountId($merchantAccountId);
    }
  }
  
  public function save()
  {
	sfContext::getInstance()->getLogger()->err('Rechead inside form.save');
    // get our customer subscription object so we know which merchant account to use
    try{
    $customerSubscription = Doctrine::getTable('CustomerSubscription')
      ->createQuery('cs')
      ->innerJoin('cs.AuthNetSubscription ans')
      ->andWhere('ans.subscription_id = ?', $this->getValue('subscription_id'))
      ->fetchOne();
	if($customerSubscription){sfContext::getInstance()->getLogger()->err('Found Customer Subscription');} 
	else {sfContext::getInstance()->getLogger()->err('Failed Finding Customer Subscription');}
	} catch (Exception $e){sfContext::getInstance()->getLogger()->err('Fatal Error Finding Customer Subscription'.$e->getMessage,'err');}
      
    $this->initMerchantAccountCredentials($customerSubscription);
	sfContext::getInstance()->getLogger()->err('Got Credentials');
    
    if ($this->processUpdate()) {
      // API update was good, so what else do we do? 
      // clear pending errors (in action)
      // update authnetsubscription object using $this->subscription
      $authNetSubscription = $this->getAuthNetSubscription($this->getValue('subscription_id'));

      $authNetSubscription['bill_to_first_name'] = $this->subscription->billToFirstName;
      $authNetSubscription['bill_to_last_name']  = $this->subscription->billToLastName;         
      $authNetSubscription['bill_to_address']    = $this->subscription->billToAddress;          
      $authNetSubscription['bill_to_city']       = $this->subscription->billToCity;           
      $authNetSubscription['bill_to_state']      = $this->subscription->billToState;
      $authNetSubscription['bill_to_zip']        = $this->subscription->billToZip;
      $authNetSubscription['bill_to_contry']     = $this->subscription->billToCountry;
	  sfContext::getInstance()->getLogger()->err('Trying to Save Subscription');
      $authNetSubscription->save();
	  sfContext::getInstance()->getLogger()->err('Saved Subscription');
    } else {
    	sfContext::getInstance()->getLogger()->err('processUpdate returned false');
    	if($this->isError()){//we need to cause an exception to be caught if the response
    		sfContext::getInstance()->getLogger()->err('Found an Error when Saving');
    		throw new Exception("Update Failed, Recheck Info");//TODO log what error message we recieved?
    	} else {
    		sfContext::getInstance()->getLogger()->err('Updated failed with No Errors');
    		throw new Exception("Something went wrong.");}
    }
  }
  
  protected function getAuthNetSubscriptionApiObject()
  {
    $fields = $this->getTransactionFields();
    $subscription = new AuthorizeNet_Subscription;
    $subscription->creditCardCardNumber     = $fields['card_num'];
    $ccExpirationDate = strtotime($this->getValue('exp'));
    $subscription->creditCardExpirationDate = date('Y-m', $ccExpirationDate);
    $subscription->creditCardCardCode       = $fields['card_code'];    
    $subscription->billToFirstName          = $fields['first_name'];
    $subscription->billToLastName           = $fields['last_name'];
    $subscription->billToAddress            = $fields['address'];
    $subscription->billToCity               = $fields['city'];
    $subscription->billToState              = substr($fields['state'], 0, 2); // ARB requires 2 char state
    $subscription->billToZip                = $fields['zip'];
    $subscription->billToCountry            = $fields['country'];

    return $subscription;
  }

  /**
   * We should refactor these form methods with the methods found in the AuthNetSubscription model.
   */
  protected function processUpdate()
  {
  	sfContext::getInstance()->getLogger()->err('Process Update 1');
    $processor = $this->getPaymentProcessor();
	sfContext::getInstance()->getLogger()->err('Process Update 2');
    $updateRequest = new AuthorizeNetARB($processor->getUsername(), $processor->getPassword());
	sfContext::getInstance()->getLogger()->err('Process Update 3 - Retrieved Request');
	sfContext::getInstance()->getLogger()->err('Process Update 4 - Got Status'.$updateRequest->getSubscriptionStatus($this->getValue('subscription_id'))->xml);    
    $this->subscription = $this->getAuthNetSubscriptionApiObject();
	sfContext::getInstance()->getLogger()->err('Process Update 5');
    $updateResponse = $updateRequest->updateSubscription($this->getValue('subscription_id'), $this->subscription);
	sfContext::getInstance()->getLogger()->err('Process Update Complete, Response Code: '.$updateResponse->getResultCode());
	sfContext::getInstance()->getLogger()->err('Process Update Complete, Response: '.$updateResponse->xml);
  
    return $updateResponse->isOk();
  }  
  
  public function getCustomer()
  {
    return $this->getOption('customer');
  }
  
  public function setAuthNetSubscription(AuthNetSubscription $subscription)
  {
    $this->setOption('authNetSubscription', $subscription);
  }
  
  public function getAuthNetSubscription($subscriptionId = null)
  {
    $authNetSubscription = $this->getOption('authNetSubscription');
    
    if (!$authNetSubscription) {
      if (!$this->isBound()) {
        throw new sfException('Only call this after you bind the form so that we can get a subscription_id');
      }
      
      $customer = $this->getCustomer();
      
      $authNetSubscription = Doctrine::getTable('AuthNetSubscription')
        ->createQuery('ans')
        ->innerJoin('ans.CustomerSubscriptions cs')
        ->andWhere('ans.subscription_id = ?', $subscriptionId)
        ->andWhere('cs.customer_id = ?', $customer['id'])
        ->fetchOne();
      
      $this->setOption('authNetSubscription', $authNetSubscription);
    }
    
    return $authNetSubscription;
  }
  
}
