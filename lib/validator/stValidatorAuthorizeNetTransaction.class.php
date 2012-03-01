<?php
/**
 * Incomplete.
 *
 * @package default
 */
class stValidatorPayFlowProTransaction extends sfValidatorSchema
{
  protected 
    $paymentForm = null,
    $responseArray = null,
    $testMode = true;
  
  public function __construct(stPaymentForm $form, $options = array(), $messages = array())
  {
    $this->paymentForm = $form;
    
    //$this->setTestMode(sfConfig::get('app_payflowpro_env') == 'Live' ? false : true);
        
    parent::__construct(null, $options, $messages);
  }
  
  public function getTestMode() 
  {
    return $this->testMode;
  }

  public function setTestMode($v) 
  {
    $this->testMode = $v;
  }

  public function isValidTransaction($value = null)
  {
    return $this->payFlowProForm->isValidTransaction($value);
  }
  
  public function getResponseArray()
  {
    return $this->responseArray;
  }
  
  protected function doClean($values)
  {
    
    if (is_null($values))
    {
      $values = array();
    }

    if (!is_array($values))
    {
      throw new InvalidArgumentException('You must pass an array parameter to the clean() method');
    }

    $this->doDirectPaymentTransaction($values);

    if (!$this->isValidTransaction())
    {
      throw new sfValidatorError($this, 'invalid', array('myval'=>'something'));      
    }

    return $values;
  }
  
  protected function doDirectPaymentTransaction($values)
  {
    
    if (strtolower(sfConfig::get('app_stPayment_env')) == 'simulation')
    {
      $result = $this->simulateTransaction(true);
    }
    else
    {
      $result = $this->performTransaction($values);
    }
    
    return $result;
    
  }
  
  protected function performTransaction($values)
  {
    // post the transaction
    //require_once 'anet_php_sdk/AuthorizeNet.php'; // Make sure this path is correct.
    $transaction = new AuthorizeNetAIM(sfConfig::get('stPayment_login'), sfConfig::get('stPayment_key'));

    $transaction->setFields($this->prepareFields($values));
    
    $transaction->amount = $this->shoppingCart->getTotalWithTaxes();
    
    $response = $transaction->authorizeAndCapture();

    if ($response->approved) {
      return $response;
      //echo "<h1>Success! The test credit card has been charged!</h1>";
      //echo "Transaction ID: " . $response->transaction_id;
      //exit();
    } else {
      return false;
    }    
  }
  
  protected function prepareFields($values)
  {
    $values = array(
      'amount' => number_format($this->paymentForm->getAmount(),2)
      'card_num' => $this->paymentForm->getValue('acct'),
      'exp_date' => date('my', strtotime($this->paymentForm->getValue('exp'))),
    )

  }
  
  
  
  protected function simulateTransaction($valid = true)
  {
    if ($valid)
    {
      $result = "RESULT=0&PNREF=V18A1D85FB14&RESPMSG=Approved&AUTHCODE=586PNI&AVSADDR=Y&AVSZIP=Y&CVV2MATCH=Y&HOSTCODE=A&PROCAVS=Y&PROCCVV2=M&IAVS=N";  
    } else {
      $result = "RESULT=4&PNREF=V53A0A30B542&RESPMSG=Invalid amount";
    }
    
    return $result;
  }
  
  protected function evaluateTransactionResponse(array $response)
  {
    $this->isValidTransaction(false);
      
    $resultCode = $response['RESULT'];

    if ($resultCode == 1 || $resultCode == 26) 
    {
      /*
        This is just checking for invalid login credentials.  You normally would not display a custom message
        for this error.
        
        Result code 26 will be issued if you do not provide both the <vendor> and <user> fields.
        Remember: <vendor> = your merchant (login id), <user> = <vendor> unless you created a seperate <user> for Payflow Pro.
      
        Result code 1, user authentication failed, usually due to invalid account information or ip restriction on the account.
        You can verify ip restriction by logging into Manager.  See Service Settings >> Allowed IP Addresses.  
        Lastly it could be you forgot the path "/transaction" on the URL.
      */
      $this->throwError('account_config');
    } 
    else if ($resultCode == 0)
    {
      $this->isValidTransaction(true);
     
      /*
        Even though the transaction was approved, you still might want to check for AVS or CVV2(CSC) prior to
        accepting the order.  Do realize that credit cards are approved (charged) regardless of the AVS/CVV2 results.
        Should you decline (void) the transaction, the card will still have a temporary charge (approval) on it.

        Check AVS - Street/Zip
        In the default errors messages it shows what failed, ie street, zip or cvv2.  To prevent fraud, it is suggested
        you only give a generic billing error message and not tell the card-holder what is actually wrong.

        Also, it is totally up to you on if you accept only "Y" or allow "N" or "X".  You need to decide what
        business logic and liability you want to accept with cards that either don't pass the check or where
        the bank does not participate or return a result.  Remember, AVS is mostly used in the US but some foreign
        banks do participate.
      
        Remember, this just an example of what you might want to do.
        There should be some type of 3 strikes your out check
        Here you might want to put in code to flag or void the transaction depending on your needs.      
      */
      if (isset($response['AVSADDR']) && $response['AVSADDR'] != "Y") 
      {
        $this->throwError('avsaddr');
      }
      if (isset($response['AVSZIP']) && $response['AVSZIP'] != "Y") 
      {
        $this->throwError('avszip');
      }
      if (isset($response['CVV2MATCH']) && $response['CVV2MATCH'] != "Y") 
      {
        $this->throwError('cvv2match');
      }
    }
    else if ($resultCode == 12) 
    {
      // Hard decline from bank.
      $this->throwError('declined');
    }
    else if ($resultCode == 13) 
    {
      // Voice authorization required.
      $this->throwError('voice_authorization');
    }
    else if ($resultCode == 23 || $resultCode == 24) 
    {
      // Issue with credit card number or expiration date.
      $this->throwError('invalid_cc');
    }
    
    // Using the Fraud Protection Service.
    // This portion of code would be is you are using the Fraud Protection Service, this is for US merchants only.
    if (sfConfig::get('app_payflow_fraud_protection')) 
    {
      // 125, 126 and 127 are Fraud Responses.
      // Refer to the Payflow Pro Fraud Protection Services User's Guide or
      // Website Payments Pro Payflow Edition - Fraud Protection Services User's Guide.
      if ($resultCode == 125) 
      {
        // 125 = Fraud Filters set to Decline.
        $this->throwError('fraud_125');
      }
      else if ($resultCode == 126) 
      {
        /*
          126 = One of more filters were triggered.  Here you would check the fraud message returned if you
          want to validate data.  For example, you might have 3 filters set, but you'll allow 2 out of the
          3 to consider this a valid transaction.  You would then send the request to the server to modify the
          status of the transaction.  This outside the scope of this sample.  Refer to the Fraud Developer's Guide.
        */
        $this->throwError('fraud_126');
      }
      else if ($resultCode == 127) 
      {
        // 127 = Issue with fraud service.  Manually approve?
        $this->throwError('fraud_127');
      }
    }
    
    if (!$this->isValidTransaction())
    {
      $this->throwError('invalid');
    }
  }
  
  protected function throwError($code, $placeholders = array())
  {
    $placeholders = array_merge($placeholders, $this->responseArray);
    throw new sfValidatorError($this, $code, $placeholders);
  }
  
}


