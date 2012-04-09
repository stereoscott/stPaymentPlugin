<?php

/**
 * PluginAuthNetSubscription form.
 *
 * @package    ##PROJECT_NAME##
 * @subpackage form
 * @author     ##AUTHOR_NAME##
 * @version    SVN: $Id: sfDoctrineFormPluginTemplate.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
abstract class PluginAuthNetSubscriptionForm extends BaseAuthNetSubscriptionForm
{
  public function setup()
  {
    $ret = parent::setup();

    $readOnly = array('cc_last_four_digits', 'created_at', 'updated_at', 'merchant_account_id', 'subscription_id', 'status', 'customer_id', 'start_date', 'interval_length', 'interval_unit', 'trial_occurrences');
    
    if (class_exists('sfWidgetFormPlain')) {
      // these fields should not be updated in the admin form
      foreach ($readOnly as $field) {
        $this->widgetSchema[$field] = new sfWidgetFormPlain();
        unset($this->validatorSchema[$field]);
      }
    } else {
      foreach ($readOnly as $field) {
        unset($this[$field]);
      }
    }
    
    // these are unused... note we should not store exp date in the db
    unset($this['cc_expiration_date'], $this['payment_method'], $this['bank_account_name'], $this['ship_to_first_name'], $this['ship_to_last_name'], 
      $this['ship_to_address'], $this['ship_to_company'], $this['ship_to_city'],
      $this['ship_to_state'], $this['ship_to_zip'], $this['ship_to_country']);
      
    // lets add credit card number, exp date and cardCode/cvv
    $this->widgetSchema['_cc_num'] = new sfWidgetFormInput(array('label'=>'Credit Card Number'));
    $this->widgetSchema['_cvv2'] = new sfWidgetFormInput(array('label'=>'CVV'));
    $years = range(date('Y'), date('Y') + 10);
    $this->widgetSchema['_cc_exp_date'] = new sfWidgetFormDate(array('format'=>'%month%/%year%', 'years' => array_combine($years, $years)));
    
    $this->validatorSchema['_cc_num'] = new stValidatorCreditCardNumber(array('required' => false), array(
      'invalid' => 'This credit card number is not valid.'));
    $this->validatorSchema['_cvv2'] = new sfValidatorString(array('required' => false));
    $this->validatorSchema['_cc_exp_date'] = new stValidatorExpirationDate(array('required' => false));
        
    return $ret;
  }
  
  /**
   * Return -1 on error, 0 if no update required, or 1 if update successful
   *
   * @param string $error 
   * @return int Response code
   */
  public function doApiUpdate(&$error)
  {
    
    $additionalFields = array();
    
    if ($ccnum = $this->getValue('_cc_num')) {
      $ccnum = preg_replace('/[^0-9]/', '', $ccnum);
      $additionalFields['creditCardCardNumber'] = $ccnum;
    }
    
    if ($exp = $this->getValue('_cc_exp_date')) {
      $additionalFields['creditCardExpirationDate'] = date('my', strtotime($exp));
    }

    if ($cvv = $this->getValue('_cvv2')) {
      $additionalFields['creditCardCardCode'] = $cvv;
    }
    
    $subscription = $this->getObject();
    $modified = false;
    if ($subscription->isModified()) {
      // make sure its not just nulls turning to empty strings
      foreach ($subscription->getModified(true) as $fieldName => $oldValue) {
        if (!(empty($oldValue) && empty($subscription[$fieldName]))) {
          $modified = true;
          break;
        }
      }
    }
    
    if (empty($additionalFields) && !$modified) {
       return 0;
    };

    $result = $subscription->doApiUpdate($error, $additionalFields);
    
    return $result ? 1 : -1;
  }
  
  /**
   * Override the default... NOTE YOU MUST MANUALLY CALL updateObject BEFORE SAVING THE FORM!
   * This is because we need to update the object before posting to the API.. and we only save 
   * if it is successful.
   *
   * @param mixed $con An optional connection object
   */
  protected function doSave($con = null)
  {
    if (null === $con)
    {
      $con = $this->getConnection();
    }

    //$this->updateObject();

    $this->getObject()->save($con);

    // embedded forms
    $this->saveEmbeddedForms($con);
  }
}
