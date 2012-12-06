<?php

/**
 * The payment form is responsible for processing the transaction. 
 * @package stPaymentPlugin
 * @author Scott Meves
 */
class BasestPaymentForm extends sfForm
{
    
  protected static $creditCardOptions = array('Visa' => 'Visa', 'Mastercard' => 'Mastercard', 'American Express' => 'American Express', 'Discover' => 'Discover');
  
  protected $amount, $orderNumber, $orderDescription;
  
  protected $transactionResponse, $responseMessage, $validTransaction, $transactionId;
  
  protected $paymentProcessor;
  
  protected $expirationReminder;
  
  protected $transactions = array(); // store completed transaction identifiers so if something goes wrong we can void them all
  
  // Turn off CSRF protection, as on a split form the generated token on each page was different
  public function __construct($defaults = array(), $options = array(), $CSRFSecret = null)
  {
    parent::__construct($defaults, $options, false);
  }
  
  public function setup()
  {
    $years = range(date('Y'), date('Y') + 10);
    
    $this->setWidgets(array(
      'fname'   => new sfWidgetFormInput(array('label'=>'First Name')),
      'lname'   => new sfWidgetFormInput(array('label'=>'Last Name')),
      'street'  => new sfWidgetFormInput(),
      'city'    => new sfWidgetFormInput(),
      'state'   => new sfWidgetFormInput(),
      'zip'     => new sfWidgetFormInput(),
      'country' => new sfWidgetFormInput(),
      //'email'   => new sfWidgetFormInput(),
      'acct'    => new sfWidgetFormInput(array('label'=>'Credit Card Number')),
      'cvv2'    => new sfWidgetFormInput(array('label'=>'CVV')),
      'card'    => new sfWidgetFormSelect(array('choices' => self::$creditCardOptions)),
      'exp'     => new sfWidgetFormDate(array('format'=>'%month%/%year%', 'years' => array_combine($years, $years))),
    ));
    
    $regex = '/^[\w\s\.]+$/';
    $this->setValidators(array(
      'fname'   => new sfValidatorRegex(array('pattern' => $regex, 'max_length' => 50), array('invalid' => 'First name cannot contain symbols')),
      'lname'   => new sfValidatorRegex(array('pattern' => $regex, 'max_length' => 50), array('invalid' => 'Last name cannot contain symbols')),
      'street'  => new sfValidatorString(array('max_length' => 60)),
      'city'    => new sfValidatorRegex(array('pattern' => $regex, 'max_length' => 40), array('invalid' => 'City cannot contain symbols')),
      'state'   => new sfValidatorRegex(array('pattern' => $regex, 'max_length' => 40), array('invalid' => 'State cannot contain symbols')),
      'zip'     => new sfValidatorString(),
      'country' => new sfValidatorString(),
      //'email'   => new sfValidatorEmail(),
      'acct'    => new stValidatorCreditCardNumber(array(), array(
        'required' => 'Your credit card number is required.',
        'invalid' => 'This credit card number is not valid.')),
      'cvv2'    => new sfValidatorString(array(), array('required' => 'Your card\'s security code is required.')),
      'card'    => new sfValidatorString(array(), array('required' => 'Please select a card type')),
      'exp'     => new stValidatorExpirationDate(array(), array('required' => 'Card expiration date is required.')),
    ));
    
    $this->widgetSchema['state'] = new sfWidgetFormSelectUSState();
    $this->widgetSchema['country'] = new sfWidgetFormSelect(array('choices'=>array('US' => 'United States')));

    //$this->validatorSchema['email'] = new sfValidatorEmail(array('required' => false)); // set in form option
        
    $this->widgetSchema->setHelp('fname', 'Please use the name and address that matches your credit card account.');
    $this->widgetSchema->setHelp('cvv2', '3- or 4-digit code on printed on back of your card');
    
    /*
    $promoForm = new PromoCodeCheckoutForm();
    $this->embedForm('PromoCodeCheckout', $promoForm);    
    $this->widgetSchema->setLabel('PromoCodeCheckout', ' ');
    */
    
    $this->widgetSchema->setNameFormat('payment[%s]');
  
  } 
  
  
  /**
   * The Payment 
   *
   * @param string $shoppingCart 
   * @return void
   */
  public function configureWithShoppingCart($shoppingCart)
  {
    $this->setAmount($shoppingCart->getTotalWithTaxes()); // important!! must do this before binding
    $this->setTrialPeriod($shoppingCart->getTrialPeriod());
    $this->setAmountAfterTrial($shoppingCart->getTotalAfterTrial());
        
    // this will have to be rewritten as soon as the shopping cart handles 
    // multiple products at one time! The plan is to allow multiple single-term products
    // alongside only 0 or 1 recurring-term products.
    $item = $shoppingCart->getCurrentItem();
    $this->setPaymentPeriod($item->getParameter('term', 'yearly'));
    $this->setOrderDescription($item->getParameter('name'));    
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
    // which merchant account do we use?
    $merchantAccounts = sfConfig::get('app_stPayment_merchantAccount', array());
    
    $merchantAccountConfigKey = false;
    if ($cobrand && $cobrand['merchant_account_id']) {
      $this->setOption('merchant_account_id', $cobrand['merchant_account_id']);
      $merchantAccountConfigKey = $cobrand['MerchantAccount']['config_key'];
    } elseif ($site && $site['merchant_account_id']) {
      $this->setOption('merchant_account_id', $site['merchant_account_id']);
      $merchantAccountConfigKey = $site['MerchantAccount']['config_key'];
    }
    
    if (!$merchantAccountConfigKey) {
      $merchantAccountConfigKey = sfConfig::get('app_stPayment_default', 'idprotection');
      // note use of Doctrine rather than Doctrine_Core for backwards compatibility
      $merchantAccountId = Doctrine::getTable('MerchantAccount')->selectIdFromKey($merchantAccountConfigKey);
      $this->setOption('merchant_account_id', $merchantAccountId);
    }
  
    if (!$this->getMerchantAccountId() && ($logger = sfContext::getInstance()->getLogger())) {
      $logger->err('Missing merchant account ID for key: '.$merchantAccountConfigKey);
    }
    
    if (isset($merchantAccounts[$merchantAccountConfigKey])) {
      $this->setMerchantAccountCredentials($merchantAccounts[$merchantAccountConfigKey]);
    } else {
      throw new Exception("No Auth.Net Credentials for $merchantAccountConfigKey. Check app.yml.");
    }
  }
    
  /**
   * Return an array in the form: array('login' => 'xxx', 'key' => xxx)
   *
   * @return array
   */
  protected function getMerchantAccountCredentials()
  {
    $credentials = $this->getOption('merchantAccountCredentials');
    if (!is_array($credentials)) {
      throw new Exception('You must call initMerchantAccountCredentials before calling getMerchantAccountCredentials');
    }

    return $credentials;
  }
  
  /**
   * Make sure to pass an array with 'login' and 'key' values
   *
   * @param array $v 
   * @return void
   */
  public function setMerchantAccountCredentials(array $v)
  {
    $this->setOption('merchantAccountCredentials', $v);
  }
  
  /**
   * Process the transaction and return the response. Sets the response to 
   * the $transactionResponse instance variable.
   * Four possible transaction methods:
   * @see authorizeCaptureAndSubscribe, @see authorizeVoidAndSubscribe, @see authorizeAndCapture, @see processRecurringTransaction
   *
   * @see registerActions::processPaymentForm
   * @return mixed $response AuthorizeNetARB_Response (XML response) or a AuthorizeNetAIM_Response (simple object)
   */
  public function processTransaction()
  {
    define('AUTHORIZENET_SANDBOX', sfConfig::get('app_stPayment_sandbox'));
    
    if (sfConfig::get('sf_logging_enabled')) {
      define('AUTHORIZENET_LOG_FILE', sfConfig::get('sf_log_dir').'/authorizenet_'.sfConfig::get('sf_environment').'.log');
    }
        
    if (false and $trialLength = $this->getTrialPeriod()) {//this should not trigger on the SB site
      if ($trialAmount = $this->getAmount()) { // amount of first charge
        $response = $this->authorizeCaptureAndSubscribe($trialAmount, $this->getAmountAfterTrial(), $trialLength.' days');
      } else { // free trial
        $response = $this->authorizeVoidAndSubscribe($trialLength.' days', $this->getAmountAfterTrial());
      }
    } else { // no trial period
      $paymentPeriod = $this->getPaymentPeriod();
      if ($paymentPeriod == 'single' || $paymentPeriod == 'yearly') {
        $response = $this->authorizeAndCapture($this->getAmount());
      } elseif ($paymentPeriod == 'monthly'){
        // process a 'paid' trial with the first trial period equal to the payment term
        $trialLength = ($paymentPeriod == 'yearly') ? '1 year' : '1 month';//because we bill the first one.
        //Note: We no longer allow recuring billing longer than a year to be setup for new customer because AIG doesn't like auto-renewals
        $totalOccurances = '11';//since we're charging the first month automatically.
        $response = $this->authorizeCaptureAndSubscribe($this->getAmount(), $this->getAmount(), $trialLength, $totalOccurances);
      }
    }
        
    $this->transactionResponse = $response;
    
    $this->processTransactionResponse($this->transactionResponse);
    
    return $this->transactionResponse;
  }
  
  /**
   * process a single transaction in the amount of the trial price
   * and then set up ARB after the trial period for the regular amount
   *
   * @param string $firstAmount The auth_capture amount, aka trialAmount 
   * @param string $recurringAmount aka the amountAfterTrial
   * @param string $recurringStartDate in 'x days', or '1 year' or '1 month'
   * @return mixed AuthorizeNetARB_Response or AuthorizeNetAIM_Response
   */
  protected function authorizeCaptureAndSubscribe($firstAmount, $recurringAmount, $recurringStartDate, $totalOccurances='9999')
  {
    $response = $this->authorizeAndCapture($firstAmount);

    if ($response->approved) {  
    
      $arbResponse = $this->processRecurringTransaction($recurringAmount, $recurringStartDate, null, $response->transaction_id, $totalOccurances);
      
      if (!$arbResponse->isOk()) 
      {
        $this->processVoid($response->transaction_id);
      }
            
      return $arbResponse;
      
    } else {
      return $response; // cascade up the error to the action
    }
  }
  
  /**
   * Authorize the regular amount, and if it's approved schedule ARB
   *
   * @param string $trialLengthString string passed to new DateTime constructor to schedule ARB start date
   * @param string $amountAfterTrial recurring / ARB amount
   * @return mixed AuthorizeNetARB_Response or AuthorizeNetAIM_Response
   */
  protected function authorizeVoidAndSubscribe($trialLengthString, $amountAfterTrial, $totalOccurances = "9999")
  {
    // authorize regular amount
    $response = $this->authorizeOnly($amountAfterTrial);
    
    if ($response->approved) {
      $this->processVoid($response->transaction_id);
      $arbResponse = $this->processRecurringTransaction($amountAfterTrial, $trialLengthString, null, $response->transaction_id, $totalOccurances);
      
      return $arbResponse;
    } else {
      return $response; // cascade error
    }
  }
  
  
  /**
   * Given a trialLength (to determine start date) and amount,
   * set up a new ARB subscription using the forms parameters for 
   *
   * @param float $amount 
   * @param string $trialLengthString 
   * @param int $intervalLength in months 
   * @return @see AuthorizeNetARB::createSubscription()
   */
  protected function processRecurringTransaction($amount, $trialLengthString, $intervalLength = null, $invoiceNumber = null, $totalOccurances = "9999")
  {
    if (null === $intervalLength) {
      $intervalLength = ($this->getPaymentPeriod() == 'yearly') ? 12 : 1; // in months
    }
    
    $apiLogin = $this->getMerchantAccountCredentials();
    $arbRequest = new AuthorizeNetARB($apiLogin['login'], $apiLogin['key']);
    
    $startDate      = $this->getSubscriptionStartDate($trialLengthString);
    $subscription   = $this->getAuthorizeNetSubscription($startDate, $intervalLength, $amount, $invoiceNumber, $totalOccurances);
    
    $response = $arbRequest->createSubscription($subscription);
    
    if ($response->isOk()) {
      $this->logResponse($response);
    }
    
    return $response;
  }

  /**
   * Performs an authorize and capture.
   *
   * @return AuthorizeNetAIM_Response $response (extends AuthorizeNetResponse)
   */
  protected function authorizeAndCapture($amount)
  {
    $transaction = $this->getAuthorizeNetAIM($amount);
    
    /* var AuthorizeNetAIM_Response */
    $response = $transaction->authorizeAndCapture();
    
    if ($response->approved) {
      $this->logResponse($response);
    }
    
    return $response;
  }

  /**
   * Performs an authorize only
   *
   * @return AuthorizeNetAIM_Response $response (extends AuthorizeNetResponse)
   */
  protected function authorizeOnly($amount)
  {
    $transaction = $this->getAuthorizeNetAIM($amount);

    return $transaction->authorizeOnly();
  }
  
  /**
   * Initialize a new AuthorizeNetAIM object with our API credentials,
   * set the fields with the values from the form, and set up the line items
   * using the 'lineItems' option.
   *
   * @param string $amount 
   * @return AuthorizeNetAIM
   */
  protected function getAuthorizeNetAIM($amount)
  {
    
    $apiLogin = $this->getMerchantAccountCredentials();
    $transaction = new AuthorizeNetAIM($apiLogin['login'], $apiLogin['key']);

    $transaction->setFields($this->getTransactionFields($amount));
    $transaction->setCustomField("affiliate_code", $this->getOption('affiliate_code', 'IFI'));

    if ($items = $this->getOption('lineItems')) {
      foreach ($items as $item) {
        if (true || $item->getParameter('sku')) {
          $transaction->addLineItem(substr($item->getParameter('sku', '000'), 0, 31), substr($item->getParameter('name', 'Unnamed'), 0, 31), substr($item->getParameter('name'), 0, 255), $item->getQuantity(), $item->getPrice(), 'N');
          //addLineItem($item_id, $item_name, $item_description, $item_quantity, $item_unit_price, $item_taxable)
        }
      }
    }    
    
    return $transaction;
  }
  
  /**
   * Returns a date x-days from today
   *
   * @param string $trialLengthString 
   * @return string date in the format Y-m-d
   */
  protected function getSubscriptionStartDate($trialLengthString)
  {
    // for subscriptions starting in a matter of days
    // schedul the first ARB payment on the n + 1 day (7 day trial, first payment is on day 8)
    if (preg_match('/(\d+) day/i', $trialLengthString, $matches)) {
      $trialLengthString = $matches[1] + 1 . ' days';
    }
    
    $dateTime = new DateTime("+$trialLengthString"); // n days, 1 month, 1 year
    $dateTime->setTimezone(new DateTimezone('US/Mountain')); // Auth.net is in mountain time
    
    return $dateTime->format('Y-m-d');
  }
  
  /**
   * Given a response object, set instance variables for valid, response message,
   * and transaction ID
   *
   * @param mixed $response AuthorizeNetARB_Response or AuthorizeNetAIM_Response
   * @return void
   */
  protected function processTransactionResponse($response)
  {    
    if ($response instanceof AuthorizeNetARB_Response) {
      $this->validTransaction = $response->isOk();
      $this->responseMessage  = $response->getMessageText();
      $this->transactionId    = $response->getSubscriptionId();
    } else {
      $this->validTransaction = $response->approved;
      $this->responseMessage  = $response->response_reason_text;
      $this->transactionId    = $response->transaction_id;      
    }
  }
      
  /**
   * Set up subscription details to be passed to the 
   *
   * @param string $startDate In the format of Y-m-d
   * @param int $intervalLength in months
   * @param string $amount In the format of "000.00"
   * @return AuthorizeNet_Subscription $subscription
   */
  protected function getAuthorizeNetSubscription($startDate, $intervalLength, $amount, $defaultInvoiceNumber = null, $totalOccurances = "9999")
  {
    $subscription = new AuthorizeNet_Subscription;
    
    $subscription->name             = $this->getProfileName();

    $subscription->startDate        = $startDate;
    $subscription->amount           = $amount;
    $subscription->intervalLength   = $intervalLength;
    $subscription->intervalUnit     = 'months';    
    $subscription->totalOccurrences = $totalOccurances;
    
    $fields = $this->getTransactionFields();
    
    $invoiceNumber = ($fields['invoice_num'] ? $fields['invoice_num'] : $defaultInvoiceNumber);
    
    $subscription->creditCardCardNumber     = $fields['card_num'];
    
    // if the expiration date is going to expire before the start date
    // let's increment the expiration date by one year before creating the subscription
    // and we also need to make note of this in our database, perhaps by setting a reminder
    // that is set one week before the attempted charge will be made.
    
    $dateOfNextTransaction = strtotime($startDate);
    $ccExpirationDate = strtotime($this->getValue('exp'));

    if ($dateOfNextTransaction > $ccExpirationDate) {
      $ccExpirationDate = strtotime('+1 year', $ccExpirationDate);
      $this->expirationReminder = $dateOfNextTransaction;
    }
    
    $subscription->creditCardExpirationDate = date('Y-m', $ccExpirationDate);
    $subscription->creditCardCardCode       = $fields['card_code'];
    
    // the invoice number defaults to the single transaction id
    $subscription->orderInvoiceNumber       = substr($invoiceNumber, 0, 20);
    $subscription->orderDescription         = substr($fields['description'], 0, 255);
    
    $subscription->customerEmail            = $fields['email'];
    $subscription->customerId               = $fields['cust_id']; // already substr in getTransactionFields
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
   * Submit a void for a given transactionId.
   *
   * @param string $transactionId 
   * @return AuthorizeNetAIM_Response
   */
  protected function processVoid($transactionId)
  {
    $apiLogin = $this->getMerchantAccountCredentials();
    $void = new AuthorizeNetAIM($apiLogin['login'], $apiLogin['key']);
    
    return $void->void($transactionId);
  }
  
  protected function cancelSubscription($subscriptionId)
  {
    $apiLogin = $this->getMerchantAccountCredentials();
    $arb = new AuthorizeNetARB($apiLogin['login'], $apiLogin['key']);
    
    return $arb->cancelSubscription($subscriptionId);
  }
  /**
   * Given the bound/cleaned form values, return an array of fields
   * for use with an AuthorizeNetAIM object.
   *
   * @param float $amount   The amount of the transaction
   * @return array $fields  An array containing amount, credit card and billing details
   */
  protected function getTransactionFields($amount = null)
  {
    
    $values = $this->getValues();
        
    if (null === $amount) {
      $amount = $this->getAmount();
    }
    
    $fields = array(
      'amount'                          => number_format($amount, 2),
      'card_num'                        => preg_replace('/[^0-9]/', '', $values['acct']),
      'exp_date'                        => date('my', strtotime($values['exp'])),
      'card_code'                       => $values['cvv2'],
      'first_name'                      => substr($values['fname'], 0, 50),
      'last_name'                       => substr($values['lname'], 0, 50),
      'email'                           => substr($this->getOption('billingEmail'), 0, 255),
      'address'                         => substr($values['street'], 0, 60),
      'city'                            => substr($values['city'], 0, 40),
      'state'                           => $values['state'], // ARB requires two-letter state
      'zip'                             => substr($values['zip'], 0, 20),
      'country'                         => substr($values['country'], 0, 60),
      'customer_ip'                     => $_SERVER['REMOTE_ADDR'],
      'cust_id'                         => substr(htmlentities($this->getAffiliateCode()), 0, 20), //$this->getOption('customerId') 20 chars max
      'description'                     => $this->getOrderDescription(),
      'invoice_num'                     => $this->getOrderNumber(),
      'email_customer'                  => 0
    );
        
    return $fields;
  }
    
  /**
   * Return contents of $validTransaction, set during processTransactionResponse
   *
   * @return boolean $this->validTransaction;
   */
  public function isSuccessful()
  {
    return $this->validTransaction;
  }
  
  /**
   * Return contents of $responseMessage, set during processTransactionResponse
   *
   * @return string $responseMessage Message set in AIM or ARB response.
   */
  public function getResponseMessage()
  {
    return $this->responseMessage;
  }
  
  public function getTransactionId()
  {
    return $this->transactionId;
  }
  
  public function getMerchantAccountId()
  {
    return $this->getOption('merchant_account_id');
  }

  public function setAffiliateCode($v)
  {
    $this->setOption('affiliate_code', $v);
  }

  // is set to cust_id which has a limit of 20 chars
  public function getAffiliateCode()
  {
    return $this->getOption('affiliate_code', 'IFI');
  }  

  public function getProtectedValues()
  {
    $protectedValues = array();
    
    foreach ($this->values as $key => $value)
    {
      switch ($key)
      {
        case 'acct':
          $v = str_pad(substr($value, -4), strlen($value), '*', STR_PAD_LEFT);
          break;    
        case 'cvv2':
          $v = '****';
          break;
        default:
          $v = $value;
          break;
      }
      
      $protectedValues[$key] = $v;      
    }
    
    return $protectedValues;
  }

  public function getOrderNumber() 
  {
    return $this->orderNumber; // $this->getAffiliateCode()...
  }

  public function setOrderNumber($orderNumber) 
  {
    $this->orderNumber = $orderNumber;
  }
  
  public function getOrderDescription() 
  {
    return substr($this->orderDescription, 0, 255);
  }

  public function setOrderDescription($orderDescription) 
  {
    $this->orderDescription = $orderDescription;
  }
  
  public function getPaymentPeriod() 
  {
    return $this->getOption('paymentPeriod');
  }

  public function setPaymentPeriod($v) 
  {
    $this->setOption('paymentPeriod', $v);
  }

  public function getProfileName() 
  {
    return $this->getOption('profileName');
  }

  public function setProfileName($v) 
  {
    $this->setOption('profileName', $v);
  }
  
  /**
   * Set this when you want to charge a different amount after a trial
   *
   * @param string $v 
   * @return void
   */
  public function setAmountAfterTrial($v)
  {
    $this->setOption('amountAfterTrial', $v);
  }
  
  public function getAmountAfterTrial()
  {
    return $this->getOption('amountAfterTrial');
  }
  
  /**
   * Set the duration of the trial period in days.
   * Really, this setting the future date for the first
   * recurring billing occurrence
   *
   * @param string $inDays 
   * @return void
   */
  public function setTrialPeriod($inDays)
  {
    $this->setOption('trialPeriod', $inDays);
  }
  
  /**
   * Get the trial period, in days
   *
   * @return void
   */
  public function getTrialPeriod()
  {
    return $this->getOption('trialPeriod');
  }
  
  /**
   * Amount due at time of the transaction (includes trial price for trial offers).
   *
   * @param string $amount 
   * @return void
   */
  public function setAmount($amount)
  {
    $this->amount = $amount;
  }
  
  public function getAmount()
  {
    return $this->amount;
  }
  
  public function showBillingFields()
  {
    return $this->requiresTransaction();
  }
  
  public function requiresTransaction() 
  {
    // return true if there is a trial product or if the total due today > 0
    return ($this->getAmount() > 0 || $this->getAmountAfterTrial() > 0);
  }
  
  public function setBillingEmail($email)
  {
    if ($email) {
      try {
        $emailValidator = new sfValidatorEmail();
        $email = $emailValidator->clean($email);
      } catch (sfValidatorError $e) {
        $email = sfConfig::get('app_mail_memberservices', 'memberservices@identityfraud.com');
      }
    }
    
    $this->setOption('billingEmail', $email);
  }
  
  /**
   * If we processed a recurring subscription and the credit card is going to 
   * expire before the next transaction, we keep track of the timestamp of the
   * first transaction that will fail
   *
   * @return int $timestamp
   */
  public function getExpirationReminder()
  {
    return $this->expirationReminder;
  }
  
  public function updateValidatorsBasedOnTotals()
  {
    if (!$this->requiresTransaction()) {
      $this->prepareZeroTotal();
    }
  }
  
  /**
   * If we have a 0 total we don't require billing information, but we may still 
   * need to validate the embedded pii and terms forms (see action class for definition);
   *
   * @return void
   */
  private function prepareZeroTotal()
  {
    $paymentFields = array(
      'fname', 'lname', 'street', 'city', 'state', 'zip', 'country', 'acct', 'cvv2', 'card', 'exp',
    );
    
    foreach ($paymentFields as $field) {
      if (isset($this->validatorSchema[$field])) {
        $this->validatorSchema[$field] = new sfValidatorPass(array('required' => false));
      }
    }
  }
  
  private function logResponse($response)
  {
    if ($response instanceof AuthorizeNetARB_Response) {
      $this->transactions[$response->getSubscriptionId()] = 'ARB';
    } elseif ($response instanceof AuthorizeNetAIM_Response) {
      $this->transactions[$response->transaction_id] = 'AIM';
    }    
  }
  
  public function rollbackTransactions()
  {
    foreach ($this->transactions as $id => $type) {
      switch ($type) {
        case 'ARB':
          $this->cancelSubscription($id);
          break;
        case 'AIM':
          $this->processVoid($id);
          break;
      }
    }
  }

  
  /**
   * Convenience method.
   *
   * @see sfValidatorSchema::getPostValidator()
   */
  public function getPostValidator()
  {
    return $this->validatorSchema->getPostValidator();
  }
}


?>