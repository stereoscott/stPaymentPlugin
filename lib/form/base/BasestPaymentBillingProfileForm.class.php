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
  
  public function setChargedBalance($amount)
  {
    $this->setOption('amount', $amount);
  }
  
  public function getChargedBalance($amount)
  {
    return $this->getOption('amount');
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
    $authNetSubscription = $this->getOption('authNetSubscription');
    
    if (!$authNetSubscription) {
      sfContext::getInstance()->getLogger()->err('getPaymentProcessor called in BasestPaymentBillingProfileForm without the authNetSubscription option set at line: '.__LINE__);
      if (!defined('AUTHORIZENET_SANDBOX')) {
        define('AUTHORIZENET_SANDBOX', sfConfig::get('app_stPayment_sandbox'));
      }
      
      if (!defined('AUTHORIZENET_LOG_FILE') && sfConfig::get('sf_logging_enabled')) {
        define('AUTHORIZENET_LOG_FILE', stAuthorizeNet::getLogFilePath());
      }
      
      return stAuthorizeNet::getInstance();
    }

    return $authNetSubscription->getPaymentProcessor();
  }
  
  public function initMerchantAccountCredentials(CustomerSubscription $subscription)
  {
    // the merchant account is stored in the purchase line item
    $this->merchantAccountId = $subscription['Purchase']['merchant_account_id'];
    
    if ($this->merchantAccountId) {
      // init the merchant account credentials based on the subscription
      $this->getPaymentProcessor()->setMerchantAccountId($this->merchantAccountId);
    }
  }
  
  public function save()
  {
  	$this->dBg = true;//manually change this if you're stuck debugging. You'll get alot more info
    if(sfConfig::get('sf_environment') == 'prod') $this->dBg = false;//we never want to allow this to be true on production because we might output credit card info.
	  $this->dBg?sfContext::getInstance()->getLogger()->debug('Rechead inside form.save'):null;
    // get our customer subscription object so we know which merchant account to use
    try{
	    $customerSubscription = Doctrine::getTable('CustomerSubscription')
        ->retrieveOneByTransactionID($this->getValue('subscription_id'));
	     
       /* ->createQuery('cs')
	      ->innerJoin('cs.AuthNetSubscription ans')
	      ->andWhere('ans.subscription_id = ?', $this->getValue('subscription_id'))
//        ->orderBy('ans.created_at DESC')
	      ->fetchOne();*/
		  if($customerSubscription){$this->dBg?sfContext::getInstance()->getLogger()->debug('(Cust: '.$this->getCustomer()->getId().') Found Customer Subscription in save() of BasestPaymentBillingProfileForm at line: '.__LINE__):null;} 
		  else {$this->dBg?sfContext::getInstance()->getLogger()->err('(Cust: '.$this->getCustomer()->getId().') Failed Finding Customer Subscription in save() of BasestPaymentBillingProfileForm at line: '.__LINE__):null;}
	  } catch (Exception $e){$this->dBg?sfContext::getInstance()->getLogger()->crit('(Cust: '.$this->getCustomer()->getId().') Fatal Error Finding Customer Subscription: '.$e->getMessage(). 'in save() of BasestPaymentBillingProfileForm at line: '.__LINE__):null;}
      
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
        $authNetSubscription['bill_to_country']    = $this->subscription->billToCountry;
  	    $this->dBg?sfContext::getInstance()->getLogger()->debug('Trying to update Status and Save Subscription'):null;
        $authNetSubscription->updateStatusUsingAuthNet($message) or $authNetSubscription->save() && $this->dBg?sfContext::getInstance()->getLogger()->debug('Error Updating Status: '.$message.' in save() of BasestPaymentBillingProfileForm at line '.__LINE__):null;
        sfContext::getInstance()->getLogger()->debug('Subscription saved..');
      } else {
      	//sfContext::getInstance()->getLogger()->err('processUpdate failed, returned: '.$this->updateResponse->isError()?'true':'false');
      	//TODO add a check against the rebilling error process to this if we're in testing and might have run similar transactions
    		if(isset($this->billResponse) && !$this->billResponse->approved){
    		    if($this->billResponse->error === true && $this->billResponse->error_message){
    			     sfContext::getInstance()->getLogger()->crit('Found an Error when Billing AuthNet: ('.$this->billResponse->response_code.'-'.$this->billResponse->response_subcode.') '.$this->billResponse->error_message.' in save() of BasestPaymentBillingProfileForm at line '.__LINE__);
               throw new Exception("Update Failed, Please re-check your billing information.");
            } elseif(!$this->billResponse->approved) {
               sfContext::getInstance()->getLogger()->debug('Bad card Info sent to Authorize.net in save() of BasestPaymentBillingProfileForm at line '.__LINE__);
               throw new Exception("Update Failed, Please re-check your billing information.");
            }
    		}elseif(isset($this->updateResponse) && $this->updateResponse->isError()){//we need to cause an exception to be caught if the response
        		sfContext::getInstance()->getLogger()->crit('Found an Error when Updating AuthNet: ('.$this->updateResponse->getMessageCode().') '.$this->updateResponse->getMessageText().' in save() of BasestPaymentBillingProfileForm at line '.__LINE__);
        		throw new Exception("Update Failed, Please contact member services if you have already double checked your billing information.");
        } else {
        		sfContext::getInstance()->getLogger()->crit('Updated Failed with an unknown error. Please contact member services.');
        		throw new Exception("Updated failed with No Errors.");
        }
      }//end of else
    } catch(Exception $e){
      if(!$this->dbg){
        sfContext::getInstance()->getLogger()->crit('Error trying to Process update: '.$e->getMessage());
        throw $e;
      }else{
        sfContext::getInstance()->getLogger()->crit('Error trying to Process update: '.$e->getTraceAsString());
        throw $e;
      }
    }
  }
  protected function getAuthNetAIMBillingApiObject($transaction = null)
  {
  	$fields = $this->getTransactionFields();
    if($transaction === null) $transaction = new AuthorizeNetAIM();
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
    $this->subscriptionApiObject = $this->getAuthNetSubscriptionApiObject();
    
    sfContext::getInstance()->getLogger()->debug('Got a test amount of: '.$subscription->totalMissedPayments().' in processUpdate of BasestPaymentBillingProfileForm');
    
	  //check for back bills and settup a new AIM request so we can bill.
	  //if(($amount = $subscription->totalMissedPayments()) && $amount > 0 && $subscription->retrieveARBstatus()!='suspended'){
		  $billRequest = new AuthorizeNetAIM($processor->getUsername(), $processor->getPassword());
      $billRequest = $this->getAuthNetAIMBillingApiObject($billRequest);
      sfContext::getInstance()->getLogger()->debug('billRequest successfully set in processUpdate of BasestPaymentBillingProfileForm');
	  //}
    //TODO we should probably still initialize an AIM transaction so we can verify card validity? - 12/4/2012 Tyler
    //TODO note that even if we only do an authorize, we still pay the processing fee.
    
	
	  //$this->dBg?sfContext::getInstance()->getLogger()->err('Process Update 3 - Retrieved Request'):null;
	
  	//$this->dBg?sfContext::getInstance()->getLogger()->debug('Process Update 4, Sandbox set to: '.(defined('AUTHORIZENET_SANDBOX') ? AUTHORIZENET_SANDBOX : 'not_defined!')):null;
  	
  	if(sfConfig::get('sf_environment') == 'prod'){
  		$updateRequest->setSandbox(false);
      if($billRequest !== false) $billRequest->setSandbox(false);
  		//$this->dBg?sfContext::getInstance()->getLogger()->debug('Production Environment, Setting Sandbox to false'):null;
  	}
  	
    //Either bill the outstanding transaction, or make a temporary auth to verfiy the payment information. 
  	if(($amount = $subscription->totalMissedPayments()) && $amount > 0 && $subscription->retrieveARBstatus()!='suspended' && $subscription->updateARBStatus() && $subscription->retrieveARBstatus()!='suspended'){
      //The silly extra code on the end was added because sometimes getting an updated status after a
      //transaction error doesn't work. We need to make an API call here to make sure the ARB status we have is up to date.
      
      $this->billResponse = $billResponse = $billRequest->authorizeAndCapture($amount);
      
  		if(!$billResponse->approved){
  		  $this->dBg?sfContext::getInstance()->getLogger()->debug('Billing transaction failed, request: '.$billRequest->getPostString()):null; 
  			$this->dBg?sfContext::getInstance()->getLogger()->debug('Billing transaction failed, response: '.$billResponse->response):null; 
        return false;//if the billing fails, there was a problem with the card and we should stop processing.
  		}else{
  		  //save the transaction
  		  $transaction = AuthNetTransaction::fromAIMResponse($billResponse, array('subscription_id' => $this->getValue('subscription_id'), 'customer_id' => $this->getCustomer()->getId()));
        if(isset($this->merchantAccountId)) $transaction->configureMerchantAccountId($this->merchantAccountId);//this would be set in initMerchantAccountCredentials.
        $transaction->save();
        
  			//first get all the transaction errors TODO remove these lines, they are old
  			//$errors=$subscription->retrieveMissedPayments();
  			
  			//Set the amount we charged for display on the confirmation page.
  			$this->setChargedBalance($amount);
  			
  			//then get the original purchase so we can use it to pr
  			$purchase = $subscription->getPurchase();
  			if(!$purchase || !is_object($purchase)){//try this if a the other retrieval fails because there are inconsitencies in some older records
  				try{
  				      $existingPurchase = Doctrine::getTable('Purchase')->createQuery('p')
  				        ->where('p.transaction_id = ?', $response->subscription_id)
  				        ->orderBy('p.created_at DESC') // put newest (biggest date) at the top of the result set
  				        ->fetchOne();
  				} catch(Exception $e) {
  				      sfContext::getInstance()->getLogger()->crit('(Cust: '.$this->getCustomer()->getId().') Could not retrieve old Purchase. Error: '.$e->getMessage());
  				}
  			}
  			
  			//then if it's monthly, check the the paynum on all the transaction errors
  			$this->isRenewal=false;
  			if($purchase->isYearly()){
  				$this->isRenewal=true;
  			}elseif($purchase->isMonthly() && $subscription->isMonthlyRenewalMissedPayments()){
  				$this->isRenewal=true;
  			}
        
  			//if we have a renewal
  			
				$fields = $this->getTransactionFields();
				//we should generate the new purchase
				$newPurchase = $purchase->generateNewPurchase(array(
			      'bill_first_name'     => $fields['first_name'],
			      'bill_last_name'      => $fields['last_name'], 
			      'bill_street'         => $fields['address'],   
			      'bill_street_2'       => null,                 
			      'bill_city'           => $fields['city'],      
			      'bill_region'         => substr($fields['state'], 0, 2),     
			      'bill_region_other'   => $fields['zip'],  
			      'bill_country'        => $fields['country'],   
		  		));
  			if($this->isRenewal){
  				// we should run the renewal from that purchase
  				$purchase->processRenewal($newPurchase, sfContext::getInstance());
  				
  				// we should send the renewal email
  				$this->getCustomer()->sendRenewalEmail($newPurchase);
  				
  			} else {
          // we should run the renewal from that purchase
          $purchase->processNonRenewal($newPurchase, sfContext::getInstance());

        }//END OF if($this->isRenewal)
  			// we should clear the transaction errors
  			$subscription->markMissedPaymentsProcessed();
  		}//END OF else OF if(!$billResponse->isOk())
  	} else {//Now handle just a simple payment info check
  	  $this->billResponse = $billResponse = $billRequest->authorizeOnly('0.00');
      if($this->billResponse->response_reason_code == '289' || $this->billResponse->response_reason_code == 289) {
        //retry
        $this->dBg?sfContext::getInstance()->getLogger()->debug('[Cust: '.$this->getCustomer()->getId().'] 0.00 verification failed, retrying with amount of 0.00]1 '):null; 
        $retry = new AuthorizeNetAIM($processor->getUsername(), $processor->getPassword());
        $retry = $this->getAuthNetAIMBillingApiObject($retry);
        $this->billResponse = $billResponse = $retry->authorizeOnly('0.01');
      }

      if($this->billResponse->approved){
        $void = new AuthorizeNetAIM($processor->getUsername(), $processor->getPassword());
        $this->voidResponse = $voidResponse = $void->void($this->billResponse->transaction_id);
      } else {
        $this->dBg?sfContext::getInstance()->getLogger()->debug('(Cust: '.$this->getCustomer()->getId().') Billing transaction failed, request: '.$billRequest->getPostString()):null; 
        $this->dBg?sfContext::getInstance()->getLogger()->debug('(Cust: '.$this->getCustomer()->getId().') Billing transaction failed, response: '.$billResponse->response):null; 
        return false;//if the billing fails, there was a problem with the card and we should stop processing.
      }
    }

    //turn off for local
    if(sfConfig::get('sf_environment') != 'dev'){
      //Status before update
      $priorStatus = $subscription->retrieveARBstatus();
    	
    	//upate the billing info on the ARB with Auth.net Note this does not check if the card is good or not
      $this->updateResponse = $updateResponse = $updateRequest->updateSubscription($this->getValue('subscription_id'), $this->subscriptionApiObject);
    	
      $this->dBg?sfContext::getInstance()->getLogger()->debug('Process Update Complete, Response Code: '.$updateResponse->getResultCode()):null;
    	$this->dBg && sfConfig::get('sf_environment') != 'prod' ?sfContext::getInstance()->getLogger()->debug('Process Update Complete, Request: '.$updateRequest->getPostString()):null;
    } else {
      $priorStatus == 'active';
    }

    //We need to check to see if there is still a transaction to mark as processed and a status to update for suspended subscriptions
    if($priorStatus == 'suspended' && $updateResponse->isOk()){
      //update status so we can check that it's active again.
      $resultMessage = $subscription->updateARBstatus();
      if($resultMessage === true){
        if($subscription->retrieveARBstatus() == 'active'){
          sfContext::getInstance()->getLogger()->debug('Marking missed payment as procesed in processUpdate of BasestPaymentBillingProfileForm because our status has been updated');
          $subscription->markMissedPaymentsProcessed();
        } else sfContext::getInstance()->getLogger()->err('(Cust: '.$this->getCustomer()->getId().') Did not mark missed payment as processed in processUpdate of BasestPaymentBillingProfileForm because our status is not "active"');
        //mark transaction as processed
      } else {
        //We failed to get the current status, now what? Should we mark the transaction are processed or not?
        //Let's got with not marking it processed since I think the silent post listener should take care of this
        // if we do end up getting Auth.net to rebill. Otherwise, we're still gravy?
        sfContext::getInstance()->getLogger()->err('(Cust: '.$this->getCustomer()->getId().') Failed to retrieve status for ARB subscription in processUpdate of BasestPaymentBillingProfileForm: '.$resultMessage);
        //do nothing
      }
      
    } elseif(!$updateResponse->isOk()){
      sfContext::getInstance()->getLogger()->err('(Cust: '.$this->getCustomer()->getId().') Updating ARB subscription failed in processUpdate of BasestPaymentBillingProfileForm');
      
    }
  	//This check to see if the Bill Response is OK and then returns it.
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
      //if($subscriptionId === null)$subscriptionId=
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
