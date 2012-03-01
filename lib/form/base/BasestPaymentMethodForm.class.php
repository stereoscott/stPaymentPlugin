<?php
/**
 *
 * @package stPaymentMethod
 * @author Scott Meves
 */
class BasestPaymentMethodForm extends sfForm
{
  protected static $paymentMethods = array('cc' => 'Credit Card', 'invoice' => 'Invoice Me');
    
  protected $cobrand;
  
  public function configure()
  {
    $this->setWidgets(array(
      'payment_method'    => new sfWidgetFormSelect(array('choices' => self::$paymentMethods)),
    ));
    
    $this->setValidators(array(
      'payment_method'   => new sfValidatorChoice(array('choices' => array_flip(self::$paymentMethods))),
    ));
    
    $this->widgetSchema->setLabel('payment_method', 'Payment Method');
    
    //$this->widgetSchema->setNameFormat('[%s]');
  }
  
  public function setCobrand($cobrand)
  {
    $this->cobrand = $cobrand;
    $this->widgetSchema->setHelp('payment_method', 'If you select the invoice option, please provide the billing information you have on file with '.$cobrand);
  }
  
}