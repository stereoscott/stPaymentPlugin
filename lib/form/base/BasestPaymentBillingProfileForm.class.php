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
    try{
      throw new Exception('Manual Error in set of BaststPaymentBillingProfileForm');
    } catch (Exception $e){
      sfContext::getInstance()->getLogger()->debug($e->getTraceAsString());
    }
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
  
  public function setChargedBalance($amount)
  {
    $this->setOption('amount', $amount);
  }
  
  public function getChargedBalance($amount)
  {
    $this->getOption('amount');
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
  	$this->dBg = true;
	  $this->dBg?sfContext::getInstance()->getLogger()->debug('Rechead inside form.save'):null;
    // get our customer subscription object so we know which merchant account to use
    try{
	    $customerSubscription = Doctrine::getTable('CustomerSubscription')
	      ->createQuery('cs')
	      ->innerJoin('cs.AuthNetSubscription ans')
	      ->andWhere('ans.subscription_id = ?', $this->getValue('subscription_id'))
	      ->fetchOne();
		  if($customerSubscription){$this->dBg?sfContext::getInstance()->getLogger()->debug('Found Customer Subscription'):null;} 
		  else {$this->dBg?sfContext::getInstance()->getLogger()->err('Failed Finding Customer Subscription'):null;}
	  } catch (Exception $e){$this->dBg?sfContext::getInstance()->getLogger()->crit('Fatal Error Finding Customer Subscription'.$e->getMessage,'err'):null;}
      
    $this->initMerchantAccountCredentials($customerSubscription);
	  $this->dBg?sfContext::getInstance()->getLogger()->debug('Got Credentials'):null;
    try{
      if ($this->processUpdate($customerSubscription)) {
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
        $authNetSubscription['bill_to_country']     = $this->subscription->billToCountry;
  	    $this->dBg?sfContext::getInstance()->getLogger()->debug('Trying to Save Subscription'):null;
        $authNetSubscription->save();
        sfContext::getInstance()->getLogger()->debug('Subscription saved.');
        //
  	  //sfContext::getInstance()->getLogger()->debug('Saved Subscription');
      } else {
      	//sfContext::getInstance()->getLogger()->err('processUpdate failed, returned: '.$this->updateResponse->isError()?'true':'false');
      	//TODO add a check against the rebilling error process to this if
    		if(isset($this->billResponse) && $this->billResponse->isError()){
    			  sfContext::getInstance()->getLogger()->err('Found an Error when Billing AuthNet: '.$this->updateResponse->getErrorMessage());
        		throw new Exception("Update Failed, Recheck Info");
    		}elseif($this->updateResponse->isError()){//we need to cause an exception to be caught if the response
        		sfContext::getInstance()->getLogger()->crit('Found an Error when Updating AuthNet: '.$this->updateResponse->getErrorMessage());
        		throw new Exception("Update Failed, Recheck Info");
        } else {
        		sfContext::getInstance()->getLogger()->crit('Updated failed with No Errors');
        		throw new Exception("Updated failed with No Errors.");}
      }//end of else
    } catch(Exception $e){
      if(!$this->dbg){
        sfContext::getInstance()->getLogger()->crit('Error trying to Process update: '.$e->getMessage());
      }else{
        sfContext::getInstance()->getLogger()->crit('Error trying to Process update: '.$e->getTraceAsString());
      }
    }
  }
  protected function getAuthNetAIMBillingApiObject($transaction = null)
  {
  	$fields = $this->getTransactionFields();
    if($transaction) $transaction = new AuthorizeNetAIM();
	  $ccExpirationDate = strtotime($this->getValue('exp'));//TODO fix
    $transaction->setField('card_num',		$fields['card_num']);//TODO fix
    $transaction->setField('exp_date',		date('Y-m', $ccExpirationDate));
    $transaction->setField('card_code',		$fields['card_code']);    
    $transaction->setField('first_name',	$fields['first_name']);
    $transaction->setField('last_name',		$fields['last_name']);
    $transaction->setField('address',		$fields['address']);
    $transaction->setField('city',			$fields['city']);
    $transaction->setField('state',			substr($fields['state'], 0, 2)); // ARB requires 2 char state
    $transaction->setField('zip',			$fields['zip']);
    $transaction->setField('country',		$fields['country']);

    return $transaction;
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
  protected function processUpdate($subscription)
  {
  	$billRequest = false; //this stays false so we don't try to bill people who haven't missed
  	//$this->dBg?sfContext::getInstance()->getLogger()->err('Process Update 1'):null;
	
    $processor = $this->getPaymentProcessor();
	  //$this->dBg?sfContext::getInstance()->getLogger()->err('Process Update 2'):null;
	
	  //get the Auth.net object and the API object we pass to it
    $updateRequest = new AuthorizeNetARB($processor->getUsername(), $processor->getPassword());
    $this->subscriptionApiObject = $this->getAuthNetSubscriptionApiObject($updateRequest);
    
	  //check for back bills and settup a new AIM request so we can bill.
	  if(($amount = $subscription->totalMissedPayments()) && $amount > 0){
		  $billRequest = new AuthorizeNetAIM($processor->getUsername(), $processor->getPassword());
      $billRequest = $this->getAuthNetAIMBillingApiObject($billRequest);
      sfContext::getInstance()->getLogger()->debug('billRequest successfully set in processUpdate of BasestPaymentBillingProfileForm for amount: '.$amount);
	  }
	
	  //$this->dBg?sfContext::getInstance()->getLogger()->err('Process Update 3 - Retrieved Request'):null;
	
  	//$this->dBg?sfContext::getInstance()->getLogger()->debug('Process Update 4, Sandbox set to: '.(defined('AUTHORIZENET_SANDBOX') ? AUTHORIZENET_SANDBOX : 'not_defined!')):null;
  	
  	if(sfConfig::get('sf_environment') == 'prod'){
  		$updateRequest->setSandbox(false);
      $billRequest->setSandbox(false);
  		//$this->dBg?sfContext::getInstance()->getLogger()->debug('Production Environment, Setting Sandbox to false'):null;
  	}
  	
  	if($billRequest !==false){
  		$this->billResponse = $billResponse = $billRequest->authorizeAndCapture($amount);
  		if(!$billResponse->isOk()){
  			return false;//if the billing fails, there was a problem with the card and we should stop processing.
  		}else{
  			//first get all the transaction errors
  			$errors=$subscription->retrieveMissedPayments();
  			$this->setChargedBalance($amount);
  			
  			//then get the original purchase
  			$purchase = $subscription->getPurchase();
  			if(!$purchase || !is_object($purchase)){//try this if a the other retrieval fails because there are inconsitencies in some older records
  				try{
  				      $existingPurchase = Doctrine::getTable('Purchase')->createQuery('p')
  				        ->where('p.transaction_id = ?', $response->subscription_id)
  				        ->orderBy('p.created_at DESC') // put newest (biggest date) at the top of the result set
  				        ->fetchOne();
  				} catch(Exception $e) {
  				      sfContext::getInstance()->getLogger()->crit('Could not retrieve old Purchase. Error: '.$e->getMessage());
  				}
  			}
  			
  			
  			//then if it's monthly, check the the paynum on all the transaction errors
  			$isRenewal=false;
  			if($purchase->isYearly()){
  				$isRenewal=true;
  			}elseif($purchase->isMonthly() && $subscription->isMonthlyRenewalMissedPayments()){
  				$isRenewal=true;
  			}
  			//if we have a renewal
  			if($isRenewal){
  				$fields = $this->getTransactionFields();
  				//we should generate the new purchase
  				$newPurchase = $purchase->generatePurchase(array(
  			      'bill_first_name'     => $fields['first_name'],
  			      'bill_last_name'      => $fields['last_name'], 
  			      'bill_street'         => $fields['address'],   
  			      'bill_street_2'       => null,                 
  			      'bill_city'           => $fields['city'],      
  			      'bill_region'         => substr($fields['state'], 0, 2),     
  			      'bill_region_other'   => $fields['zip'],  
  			      'bill_country'        => $fields['country'],   
  		  		));
  				
  				// we should run the renewal from that purchase
  				$purchase->processRenewal($newPurchase, sfContext::getInstance());
  				
  				// we should send the renewal email
  				$this->getCustomer()->sendRenewalEmail();
  				
  			}//END OF if($isRenewal)
  			// we should clear the transaction errors
  			$subscription->markMissedPaymentsProcessed();
  		}//END OF else OF if(!$billResponse->isOk())
  	} else {
  	  sfContext::getInstance()->getLogger()->debug('if($billRequest && $this->transaction) returned false because '.($billRequest?'The transaction was not set':'$billRequest was false').' in processUpdate of BasestPaymentBillingProfileForm');
    }
  	
  	//upate the billing info on the ARB with Auth.net Note this does not check if the card is good or not
    $this->updateResponse = $updateResponse = $updateRequest->updateSubscription($this->getValue('subscription_id'), $this->subscriptionApiObject);
  	$this->dBg?sfContext::getInstance()->getLogger()->debug('Process Update Complete, Response Code: '.$updateResponse->getResultCode()):null;
  	$this->dBg?sfContext::getInstance()->getLogger()->debug('Process Update Complete, Request: '.$updateRequest->getPostString()):null;
  
  	//This check to see if we need the Bill Response to be OK and then returns it.
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
