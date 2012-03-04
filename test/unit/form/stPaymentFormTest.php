<?php

/**
 * stPaymentForm tests.
 */
include dirname(__FILE__).'/../../../../../test/bootstrap/unit.php';

require_once(dirname(__FILE__).'/../../../lib/validator/stValidatorExpirationDate.class.php');

// need to load in configuration to get merchant account credentials

$configuration = ProjectConfiguration::getApplicationConfiguration( 'frontend', 'test', true);

$t = new lime_test();

class TestPaymentForm extends stPaymentForm
{
  public function getTransactionFields($amount = null) {
    return parent::getTransactionFields($amount);
  }
  
  public function getSubscriptionStartDate($trialLengthString)
  {
    return parent::getSubscriptionStartDate($trialLengthString);
  }
}

$values = array(
  'fname'   => 'Automated',
  'lname'   => 'Testing',
  'street'  => '123 Main St',
  'city'    => 'New York',
  'state'   => 'NY',
  'zip'     => '10024',
  'country' => 'US',
  'acct'    => '4111111111111111',
  'cvv2'    => '123',
  'card'    => 'Visa',
  'exp'     => array('month' => 10, 'year' => 2015),
);

$f = new TestPaymentForm();

$t->ok(!$f->requiresTransaction(), 'requiresTransaction return false if amounts are empty');

$f->setAmount(10);
$t->is($f->getAmount(), 10, 'getAmount works (10)');

$f->setTrialPeriod(7);
$t->is($f->getTrialPeriod(), 7, 'getTrialPeriod works (7)');

$t->ok($f->requiresTransaction(), 'requiresTransaction return true if amount is positive');

$f->setAmountAfterTrial(20);
$t->is($f->getAmountAfterTrial(), 20, 'getAmountAfterTrial works (20)');

$f->setPaymentPeriod('monthly');
$t->is($f->getPaymentPeriod(), 'monthly', 'getPaymentPeriod works (monthly)');

$t->comment('initMerchantAccountCredentials');
// set up merchant account credits without db connection
$credentials = sfConfig::get('app_stPayment_merchantAccount');

$processor = stAuthorizeNet::getInstance();
$processor->setUsername($credentials['ifi']['login']);
$processor->setPassword($credentials['ifi']['key']);

$f->bind($values);

$t->ok($f->isBound(), '->isBound() returns true if the form is bound');

$cleanedValues = array_merge($values, array('exp' => '2015-10-01'));

$t->is($f->getValues(), $cleanedValues, '->getValues() returns an array of cleaned values if the form is bound');

$t->ok(!$f->hasErrors(), '->hasErrors() returns false if the form passes the validation');

// getSubscriptionStartDate
$t->comment('getSubscriptionStartDate');
$t->is($f->getSubscriptionStartDate('1 days'), date('Y-m-d', strtotime('+2 days')), 'if provided in days, adds an additional day');
$t->is($f->getSubscriptionStartDate('1 month'), date('Y-m-d', strtotime('+1 month')), 'if provided in months, no change on date');
$t->is($f->getSubscriptionStartDate('1 year'), date('Y-m-d', strtotime('+1 year')), 'if provided in months, no change on date');

if (true) {
  $t->comment('Processing a single $15 product');
  // auth and capture 15
  $f->setAmount(15);
  $f->setTrialPeriod(0);
  $f->setAmountAfterTrial(0);
  $f->setPaymentPeriod('single');
  $response = $f->processTransaction();
  $t->isa_ok($response, 'AuthorizeNetAIM_Response', 'Response is an AuthorizeNetAIM_Response object');
  $t->ok($f->isSuccessful(), 'Processed a valid transaction');
  $t->is($f->getResponseMessage(), 'This transaction has been approved');
}

if (true) {
  $t->comment('Going to process a $25 monthly product with no trial');
  // auth and capture 25, set recurring for 25/mo starting in 30 days
  $f->setAmount(25);
  $f->setTrialPeriod(0);
  $f->setAmountAfterTrial(0);
  $f->setPaymentPeriod('monthly');
  $response = $f->processTransaction();
  $t->isa_ok($response, 'AuthorizeNetARB_Response', 'Recurring products return an AuthorizeNetARB_Response object');
  $t->is($f->isSuccessful(), 'Processed a valid transaction');
  $t->is($f->getResponseMessage(), 'Successful.'); 
}

if (true) {
  $t->comment('Processing a 7 day $10 trial, then 20/month');
  // auth and capture 10, ARB for 20/mo
  $f->setAmount(10);
  $f->setTrialPeriod(7);
  $f->setAmountAfterTrial(20);
  $f->setPaymentPeriod('monthly');
  $response = $f->processTransaction();
  $t->isa_ok($response, 'AuthorizeNetARB_Response', 'Recurring products return an AuthorizeNetARB_Response object');
  $t->is($f->isSuccessful(), 'Processed a valid transaction');
  $t->is($f->getResponseMessage(), 'Successful.');
}


if (true) {
  $t->comment('Process a 14 day free trial, then 20/year');
  // auth and void 20, ARB for 20/year starting in 14 days
  $f->setAmount(0);
  $f->setTrialPeriod(14);
  $f->setAmountAfterTrial(20);
  $f->setPaymentPeriod('year');
  $response = $f->processTransaction();
  $t->isa_ok($response, 'AuthorizeNetARB_Response', 'Recurring products return an AuthorizeNetARB_Response object');
  $t->is($f->isSuccessful(), 'Processed a valid transaction');
  $t->is($f->getResponseMessage(), 'Successful.');
}

